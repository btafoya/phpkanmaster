-- docker/db/init/05-task-notes.sql

create table task_notes (
    id         uuid primary key default gen_random_uuid(),
    task_id    uuid references tasks(id) on delete cascade,
    content    text not null,
    created_at timestamptz not null default now(),
    updated_at timestamptz not null default now()
);