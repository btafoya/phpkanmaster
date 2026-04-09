-- docker/db/init/02-schema.sql

-- Categories
create table categories (
  id    uuid primary key default gen_random_uuid(),
  name  text not null unique,
  color text not null default '#6c757d'
);

insert into categories (name, color) values
  ('Personal', '#5b8dee'),
  ('Business', '#f0a500'),
  ('Music',    '#e83e8c'),
  ('Home',     '#28c76f');

-- Tasks
create table tasks (
  id                uuid primary key default gen_random_uuid(),
  title             text not null,
  description       text,
  due_date          date,
  priority          text not null default 'medium'
                      check (priority in ('low', 'medium', 'high')),
  task_column       text not null default 'new'
                      check (task_column in ('new', 'in_progress', 'review', 'on_hold', 'done')),
  position          integer not null default 0,
  category_id       uuid references categories(id) on delete set null,
  parent_id         uuid references tasks(id) on delete cascade,
  reminder_at       timestamptz,
  reminder_sent     boolean not null default false,
  pushover_priority integer not null default 0
                      check (pushover_priority between -2 and 2),
  pushover_retry    integer default 30,
  pushover_expire   integer default 3600,
  disable_notifications boolean not null default false,
  created_at        timestamptz not null default now(),
  updated_at        timestamptz not null default now()
);

-- Task file attachments
create table task_files (
  id         uuid primary key default gen_random_uuid(),
  task_id    uuid references tasks(id) on delete cascade,
  filename   text not null,
  mime_type  text not null,
  data       text not null,
  created_at timestamptz not null default now()
);
