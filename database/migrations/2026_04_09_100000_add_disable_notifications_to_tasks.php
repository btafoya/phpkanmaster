<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasColumn('tasks', 'disable_notifications')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('disable_notifications')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('disable_notifications');
        });
    }
};
