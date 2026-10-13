<?php

// Dry-run helper: replace the TEST database content with a dump file. Refuses any other database.
// Usage: APP_ENV=testing php load_test.php <dump.sql>
require __DIR__.'/../../backend/vendor/autoload.php';
$app = require __DIR__.'/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$db = config('database.connections.mysql');
if (! app()->environment('testing') || $db['database'] !== 'secondhand_marketplace_test' || DB::selectOne('SELECT DATABASE() name')->name !== 'secondhand_marketplace_test') {
    fwrite(STDERR, "Refusing: only the dedicated test database may be replaced.\n");
    exit(1);
}
DB::statement('SET FOREIGN_KEY_CHECKS=0');
foreach (DB::select('SHOW TABLES') as $row) {
    DB::statement('DROP TABLE `'.array_values((array) $row)[0].'`');
}
DB::statement('SET FOREIGN_KEY_CHECKS=1');
$defaults = tempnam(sys_get_temp_dir(), 'd8');
file_put_contents($defaults, "[client]\nuser=\"{$db['username']}\"\npassword=\"".addcslashes((string) $db['password'], '"\\')."\"\nhost=\"{$db['host']}\"\nport={$db['port']}\n");
$command = '"C:\\Program Files\\MySQL\\MySQL Server 26.7\\bin\\mysql.exe" --defaults-extra-file='.escapeshellarg($defaults).' '.escapeshellarg($db['database']).' < '.escapeshellarg($argv[1]).' 2>&1';
exec($command, $output, $code);
unlink($defaults);
echo 'exit='.$code.' tables='.count(DB::select('SHOW TABLES'))."\n";
foreach ($output as $line) {
    echo preg_replace('/password\S*/i', '[redacted]', $line)."\n";
}
exit($code);
