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

        DB::statement("
            CREATE OR REPLACE VIEW active_tasks_with_notes AS
            SELECT
                t.id,
                t.title,
                t.description,
                t.due_date,
                t.priority,
                t.task_column,
                t.position,
                t.category_id,
                t.parent_id,
                t.reminder_at,
                t.disable_notifications,
                t.created_at,
                t.updated_at,
                COALESCE(
                    json_agg(
                        json_build_object(
                            'id', n.id,
                            'content', n.content,
                            'created_at', n.created_at,
                            'updated_at', n.updated_at
                        )
                    ) FILTER (WHERE n.id IS NOT NULL),
                    '[]'::json
                ) AS notes
            FROM tasks t
            LEFT JOIN task_notes n ON n.task_id = t.id
            WHERE t.task_column != 'done'
            GROUP BY t.id
            ORDER BY t.position ASC
        ");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS active_tasks_with_notes');
    }
};
