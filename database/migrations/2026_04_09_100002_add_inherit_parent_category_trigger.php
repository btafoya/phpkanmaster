<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            create or replace function inherit_parent_category()
            returns trigger language plpgsql security definer as \$\$
            begin
              if NEW.parent_id is not null and NEW.category_id is distinct from (
                select category_id from tasks where id = NEW.parent_id
              ) then
                NEW.category_id := (select category_id from tasks where id = NEW.parent_id);
              end if;
              return NEW;
            end;
            \$\$
        ");

        DB::statement("
            drop trigger if exists trigger_inherit_parent_category on tasks
        ");

        DB::statement("
            create trigger trigger_inherit_parent_category
              before insert or update of parent_id, category_id on tasks
              for each row execute function inherit_parent_category()
        ");

        // Ensure anon role can insert/update tasks
        DB::statement('grant select, insert, update on tasks to anon');
    }

    public function down(): void
    {
        DB::statement('drop trigger if exists trigger_inherit_parent_category on tasks');
        DB::statement('drop function if exists inherit_parent_category');
    }
};
