-- docker/db/init/01-roles.sql
-- Executed once on first container start (creates pgdata volume)

-- Create anon role (used by PostgREST for unauthenticated access)
CREATE ROLE anon NOLOGIN;

-- Create login role for PostgREST connection (grants anon)
CREATE ROLE kanban_postgrest LOGIN PASSWORD 'postgrest_secret';
GRANT anon TO kanban_postgrest;

-- Create login role for Laravel migrations (optional, can use same as postgrest)
CREATE ROLE kanban_app LOGIN PASSWORD 'kanban_secret';
GRANT anon TO kanban_app;
