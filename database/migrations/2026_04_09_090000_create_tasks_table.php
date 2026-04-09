<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('tasks')) {
            return;
        }

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('title');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->string('priority', 10)->default('medium');
            $table->string('task_column', 20)->default('new');
            $table->integer('position')->default(0);
            $table->uuid('category_id')->nullable();
            $table->uuid('parent_id')->nullable();
            $table->timestampTz('reminder_at')->nullable();
            $table->boolean('reminder_sent')->default(false);
            $table->integer('pushover_priority')->default(0);
            $table->integer('pushover_retry')->nullable();
            $table->integer('pushover_expire')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
