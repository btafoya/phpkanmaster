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

        // Grant write permissions to agent role for tasks table
        DB::statement('GRANT INSERT ON tasks TO agent');
        DB::statement('GRANT UPDATE ON tasks TO agent');
        DB::statement('GRANT DELETE ON tasks TO agent');

        // Grant write permissions for task_notes (for future note support)
        DB::statement('GRANT INSERT ON task_notes TO agent');
        DB::statement('GRANT UPDATE ON task_notes TO agent');

        // Also grant for categories (to support creating categories if needed)
        DB::statement('GRANT INSERT ON categories TO agent');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('REVOKE INSERT ON tasks FROM agent');
        DB::statement('REVOKE UPDATE ON tasks FROM agent');
        DB::statement('REVOKE DELETE ON tasks FROM agent');
        DB::statement('REVOKE INSERT ON task_notes FROM agent');
        DB::statement('REVOKE UPDATE ON task_notes FROM agent');
        DB::statement('REVOKE INSERT ON categories FROM agent');
    }
};