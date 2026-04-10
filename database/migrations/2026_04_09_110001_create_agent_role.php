<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP ROLE IF EXISTS agent');
        DB::statement('CREATE ROLE agent NOLOGIN');
        DB::statement('GRANT agent TO kanban_postgrest');
        DB::statement('GRANT SELECT ON active_tasks_with_notes TO agent');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP ROLE IF EXISTS agent');
    }
};
