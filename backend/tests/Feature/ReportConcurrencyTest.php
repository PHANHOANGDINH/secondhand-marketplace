<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReportConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    /** Separate committed fixtures and PHP processes exercise real MySQL row locks. */
    private function race(array $jobs): array
    {
        $dir = storage_path('framework/testing');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $prefix = $dir.'/report-'.Str::uuid();
        $go = $prefix.'-go';
        $processes = [];
        $paths = [$go];
        try {
            foreach ($jobs as $i => $job) {
                $ready = $prefix.'-'.$i;
                $paths[] = $ready;
                $process = new Process([PHP_BINARY, base_path('tests/Support/report-worker.php'), json_encode([...$job, 'ready' => $ready, 'go' => $go], JSON_THROW_ON_ERROR)], base_path(), ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'secondhand_marketplace_test', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array'], null, 35);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 20;
            while (count(array_filter(array_slice($paths, 1), 'is_file')) !== count($jobs)) {
                foreach ($processes as $process) {
                    if (! $process->isRunning()) {
                        $this->fail('Worker exited before barrier: '.$process->getErrorOutput());
                    }
                }
                if (microtime(true) > $deadline) {
                    $this->fail('Workers did not reach barrier');
                } usleep(10000);
            }
            file_put_contents($go, 'go');
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                try {
                    $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    $this->fail('Worker returned invalid JSON: '.$process->getOutput());
                }
            }

            return $results;
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            } foreach ($paths as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function test_concurrent_submissions_create_only_one_open_report(): void
    {
        $p = Product::factory()->active()->create();
        $u = User::factory()->create();
        $job = ['action' => 'create', 'user' => $u->id, 'data' => ['target_type' => 'PRODUCT', 'target_id' => $p->id, 'reason' => 'SPAM']];
        $statuses = array_column($this->race([$job, $job]), 'status');
        sort($statuses);
        $this->assertSame([201, 409], $statuses);
        $this->assertDatabaseCount('reports', 1);
    }

    public function test_competing_terminal_actions_have_one_result_and_notification(): void
    {
        $r = Report::factory()->create(['status' => 'IN_REVIEW']);
        $users = User::factory()->admin()->count(2)->create();
        $jobs = [];
        foreach (['RESOLVED', 'DISMISSED'] as $i => $status) {
            $jobs[] = ['action' => 'moderate', 'user' => $users[$i]->id, 'report' => $r->id, 'data' => ['expected_status' => 'IN_REVIEW', 'status' => $status]];
        }
        $statuses = array_column($this->race($jobs), 'status');
        sort($statuses);
        $this->assertSame([200, 409], $statuses);
        $this->assertContains($r->fresh()->status->value, ['RESOLVED', 'DISMISSED']);
        $this->assertDatabaseCount('notifications', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }
}
