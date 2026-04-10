import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'node',
    include: ['tests/js/**/*.test.js'],
    // globalSetup/globalTeardown only needed for integration tests that need a live DB
    // board.test.js tests pure functions and doesn't need them
  },
});
