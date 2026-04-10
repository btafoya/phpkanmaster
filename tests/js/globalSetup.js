/**
 * Vitest global setup using @databases/pg-test
 *
 * Starts a Docker-based PostgreSQL instance for testing before any tests run.
 */
import pg from 'pg';
import { state } from './pgTestState.js';

const { Client } = pg;

export async function setup() {
  console.log('Starting test database...');

  // Import pg-test
  const pgTest = (await import('@databases/pg-test')).default;

  // Get database instance
  const { databaseUrl, kill } = await pgTest();
  state.killFn = kill;

  // Set environment variable for tests
  process.env.TEST_DATABASE_URL = databaseUrl;

  console.log(`Test database running at: ${databaseUrl}`);

  // Run migrations
  await runMigrations(databaseUrl);
}

async function runMigrations(databaseUrl) {
  const client = new Client({ connectionString: databaseUrl });

  try {
    await client.connect();

    // Create tasks table
    await client.query(`
      CREATE TABLE IF NOT EXISTS tasks (
        id SERIAL PRIMARY KEY,
        title TEXT NOT NULL DEFAULT '',
        description TEXT,
        task_column TEXT NOT NULL DEFAULT 'new' CHECK (task_column IN ('new', 'in_progress', 'review', 'on_hold', 'done')),
        priority TEXT NOT NULL DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high')),
        position INTEGER NOT NULL DEFAULT 0,
        category_id INTEGER,
        due_date DATE,
        reminder_at TIMESTAMP,
        pushover_priority INTEGER DEFAULT 0,
        disable_notifications BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );
    `);

    // Create categories table
    await client.query(`
      CREATE TABLE IF NOT EXISTS categories (
        id SERIAL PRIMARY KEY,
        name TEXT NOT NULL,
        color TEXT DEFAULT '#6c757d'
      );
    `);

    // Create task_notes table
    await client.query(`
      CREATE TABLE IF NOT EXISTS task_notes (
        id SERIAL PRIMARY KEY,
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );
    `);

    // Create recurrence_rules table
    await client.query(`
      CREATE TABLE IF NOT EXISTS recurrence_rules (
        id SERIAL PRIMARY KEY,
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        frequency TEXT NOT NULL,
        interval_value INTEGER DEFAULT 1,
        until_date DATE,
        count_limit INTEGER,
        days_of_week INTEGER[],
        day_of_month INTEGER,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
      );
    `);

    // Create task_files table
    await client.query(`
      CREATE TABLE IF NOT EXISTS task_files (
        id SERIAL PRIMARY KEY,
        task_id INTEGER NOT NULL REFERENCES tasks(id) ON DELETE CASCADE,
        filename TEXT NOT NULL,
        content TEXT NOT NULL
      );
    `);

    console.log('Test database migrations complete');
  } catch (err) {
    console.error('Migration failed:', err.message);
    process.exit(1);
  } finally {
    await client.end();
  }
}
