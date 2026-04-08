-- docker/db/init/03-permissions.sql

-- Grant all CRUD permissions to the anon role
grant select, insert, update, delete on tasks, categories, task_files to anon;
