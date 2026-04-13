<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('issue_mappings')) {
            return;
        }

        Schema::create('issue_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('external_id', 255);
            $table->uuid('task_id');
            $table->string('source', 50);
            $table->integer('project_id')->nullable();
            $table->timestampTz('last_synced_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();

            $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
            $table->unique(['source', 'external_id']);
            $table->index('task_id');
        });

        // Grant permissions to anon role (only on PostgreSQL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON issue_mappings TO anon');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_mappings');
    }
};