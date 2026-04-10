<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Ensure task_notes.id has gen_random_uuid() default (idempotent)
        DB::statement("ALTER TABLE task_notes ALTER COLUMN id SET DEFAULT gen_random_uuid()");

        // Ensure task_id is nullable for consistency with the create migration
        DB::statement("ALTER TABLE task_notes ALTER COLUMN task_id DROP NOT NULL");
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("ALTER TABLE task_notes ALTER COLUMN id DROP DEFAULT");
        DB::statement("ALTER TABLE task_notes ALTER COLUMN task_id SET NOT NULL");
    }
};
