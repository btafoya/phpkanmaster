import { defineConfig } from 'vitest/config';

export default defineConfig({
  test: {
    environment: 'node',
    include: ['tests/js/**/*.test.js'],
    globalSetup: './tests/js/globalSetup.js',
    globalTeardown: './tests/js/globalTeardown.js',
  },
});
