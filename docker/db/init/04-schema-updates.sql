-- docker/db/init/04-schema-updates.sql
-- Run this against existing databases that were created before these schema additions.
-- Safe to run multiple times (uses IF NOT EXISTS / ADD COLUMN IF NOT EXISTS).

alter table tasks
  add column if not exists disable_notifications boolean not null default false;
