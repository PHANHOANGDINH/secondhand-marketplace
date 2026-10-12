<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Additive only: the reports table was the one status column without a CHECK constraint. */
    public function up(): void
    {
        if (DB::table('reports')->whereNotIn('status', ['PENDING', 'IN_REVIEW', 'RESOLVED', 'DISMISSED'])->exists()) {
            throw new RuntimeException('Existing reports carry an unknown status; the CHECK constraint was not added.');
        }
        DB::statement("ALTER TABLE `reports` ADD CONSTRAINT `reports_status_enum_check` CHECK (`status` IN ('PENDING', 'IN_REVIEW', 'RESOLVED', 'DISMISSED'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE `reports` DROP CHECK `reports_status_enum_check`');
    }
};
