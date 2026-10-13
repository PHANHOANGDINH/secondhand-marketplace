<?php

// Read-only, row-level snapshot of the connected database: for every table with an `id`
// column, id => sha256 of the whole row. Usage: php rows.php <out.json> [before.json]
// With a "before" file it reports changed, removed and added rows per table.
require __DIR__.'/../../backend/vendor/autoload.php';
$app = require __DIR__.'/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$name = DB::connection()->getDatabaseName();
$pdo = DB::connection()->getPdo();
$result = ['database' => $name, 'tables' => []];
foreach (DB::select('SHOW TABLES') as $row) {
    $table = array_values((array) $row)[0];
    $columns = array_map(fn ($c) => $c->Field, DB::select("SHOW COLUMNS FROM `$table`"));
    // Operational tables (sessions, cache, jobs) are not business data.
    if (! in_array('id', $columns, true) || in_array($table, ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'migrations', 'personal_access_tokens'], true)) {
        continue;
    }
    $rows = [];
    foreach ($pdo->query("SELECT * FROM `$table`", PDO::FETCH_ASSOC) as $data) {
        $rows[(string) $data['id']] = hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }
    $result['tables'][$table] = $rows;
}
file_put_contents($argv[1], json_encode($result));
echo "db=$name tables=".count($result['tables']).' rows='.array_sum(array_map('count', $result['tables']))."\n";
if (isset($argv[2])) {
    $before = json_decode(file_get_contents($argv[2]), true, flags: JSON_THROW_ON_ERROR)['tables'];
    $changed = $removed = $added = [];
    foreach ($before as $table => $rows) {
        foreach ($rows as $id => $hash) {
            if (! isset($result['tables'][$table][$id])) {
                $removed[$table][] = $id;
            } elseif ($result['tables'][$table][$id] !== $hash) {
                $changed[$table][] = $id;
            }
        }
    }
    foreach ($result['tables'] as $table => $rows) {
        $new = count(array_diff_key($rows, $before[$table] ?? []));
        if ($new > 0) {
            $added[$table] = $new;
        }
    }
    echo 'changed old rows: '.json_encode($changed)."\nremoved old rows: ".json_encode($removed)."\nadded rows: ".json_encode($added)."\n";
}
