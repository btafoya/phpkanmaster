-- docker/db/init/04-schema-updates.sql
-- Run this against existing databases that were created before these schema additions.
-- Safe to run multiple times (uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).

alter table tasks
  add column if not exists disable_notifications boolean not null default false;

create table if not exists recurrence_rules (
  id                  uuid primary key default gen_random_uuid(),
  task_id             uuid references tasks(id) on delete cascade,
  rrule               text not null,
  next_occurrence_at  timestamptz not null,
  end_date            date,
  active              boolean not null default true,
  created_at          timestamptz not null default now(),
  updated_at          timestamptz not null default now()
);

create index if not exists idx_recurrence_rules_active_next
  on recurrence_rules (active, next_occurrence_at);

-- Index on parent_id for efficient child lookups
create index if not exists idx_tasks_parent_id on tasks (parent_id);

-- Computed children column for PostgREST
alter table tasks add column if not exists children jsonb;
update tasks set children = (
  select jsonb_agg(jsonb_build_object('id', id, 'title', title, 'task_column', task_column, 'position', position) order by position)
  from tasks t2 where t2.parent_id = tasks.id
);

grant select, insert, update, delete on recurrence_rules to anon;
grant select on tasks to anon;
