<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add NOTIFY triggers on tasks, categories, task_files, and task_notes
     * so the SSE server can push change notifications to the browser.
     */
    public function up(): void
    {
        // PostgreSQL-only — skip entirely for SQLite (test environment)
        $driver = DB::getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }

        // Each table gets a single trigger that fires on INSERT/UPDATE/DELETE
        // and NOTIFYs the appropriate channel with a JSON payload:
        //   {"op":"INSERT|UPDATE|DELETE","table":"<table>"}

        $triggers = [
            ['table' => 'tasks',       'channel' => 'task_changes'],
            ['table' => 'categories',  'channel' => 'category_changes'],
            ['table' => 'task_files',  'channel' => 'file_changes'],
            ['table' => 'task_notes',  'channel' => 'note_changes'],
        ];

        foreach ($triggers as $t) {
            $table = $t['table'];
            $channel = $t['channel'];
            $fn = "notify_{$channel}";
            $trg = "trg_{$channel}";

            // Function: build JSON payload and NOTIFY
            DB::statement(<<<SQL
                CREATE OR REPLACE FUNCTION {$fn}() RETURNS trigger AS \$\$
                BEGIN
                    PERFORM pg_notify(
                        '{$channel}',
                        json_build_object(
                            'op', TG_OP,
                            'table', TG_TABLE_NAME
                        )::text
                    );
                    RETURN NEW;
                END;
                \$\$ LANGUAGE plpgsql
            SQL);

            // Trigger: fire after INSERT/UPDATE/DELETE
            DB::statement("DROP TRIGGER IF EXISTS {$trg} ON {$table}");
            DB::statement("CREATE TRIGGER {$trg} AFTER INSERT OR UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$fn}()");
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver !== 'pgsql') {
            return;
        }

        $triggers = [
            ['table' => 'tasks',       'channel' => 'task_changes'],
            ['table' => 'categories',  'channel' => 'category_changes'],
            ['table' => 'task_files',  'channel' => 'file_changes'],
            ['table' => 'task_notes',  'channel' => 'note_changes'],
        ];

        foreach ($triggers as $t) {
            $table = $t['table'];
            $channel = $t['channel'];
            $fn = "notify_{$channel}";
            $trg = "trg_{$channel}";

            DB::statement("DROP TRIGGER IF EXISTS {$trg} ON {$table}");
            DB::statement("DROP FUNCTION IF EXISTS {$fn}()");
        }
    }
};