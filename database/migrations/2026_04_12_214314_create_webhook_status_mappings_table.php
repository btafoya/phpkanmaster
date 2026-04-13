<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('webhook_status_mappings')) {
            return;
        }

        Schema::create('webhook_status_mappings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source', 50);
            $table->integer('external_status');
            $table->string('kanban_column', 20);
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['source', 'external_status']);
            $table->index('source');
        });

        // Grant permissions to anon role (only on PostgreSQL)
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('GRANT SELECT, INSERT, UPDATE, DELETE ON webhook_status_mappings TO anon');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_status_mappings');
    }
};