/**
 * Vitest global teardown for @databases/pg-test
 *
 * Stops the Docker-based PostgreSQL test instance after all tests complete.
 */
import { state } from './pgTestState.js';

export async function teardown() {
  if (state.killFn) {
    console.log('Stopping test database...');
    await state.killFn();
    console.log('Test database stopped');
  }
}
