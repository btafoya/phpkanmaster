<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SendRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_tasks_table_has_disable_notifications_column(): void
    {
        $this->assertTrue(Schema::hasColumn('tasks', 'disable_notifications'));
    }
}
