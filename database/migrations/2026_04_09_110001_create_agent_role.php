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

        // Revoke from kanban_postgrest and drop view first, then drop role
        DB::statement('REVOKE SELECT ON active_tasks_with_notes FROM agent');
        DB::statement('REVOKE agent FROM kanban_postgrest');
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

        DB::statement('REVOKE agent FROM kanban_postgrest');
        DB::statement('REVOKE SELECT ON active_tasks_with_notes FROM agent');
        DB::statement('DROP ROLE IF EXISTS agent');
    }
};
