# Project Anatomy: phpkanmaster
Generated: 2026-04-10 | 241 files | ~355.7K tokens if all read

## Structure
| Path | Lines | ~Tokens | Type |
|------|-------|---------|------|
| public/assets/js/app.js | 1426 | 13.1K | source |
| public/assets/js/pwa.js | 252 | 2.3K | source |
| .claude/plugins/superpowers-laravel/scripts/validate_skills.ts | 197 | 1.8K | source |
| public/sw.js | 172 | 1.6K | source |
| tests/js/globalSetup.js | 108 | 995 | source |
| .claude/plugins/superpowers-laravel/scripts/lint_skill_content.ts | 42 | 387 | source |
| vite.config.js | 17 | 157 | source |
| tests/js/globalTeardown.js | 15 | 138 | source |
| vitest.config.js | 11 | 101 | source |
| tests/js/pgTestState.js | 7 | 64 | source |
| resources/js/bootstrap.js | 5 | 46 | source |
| resources/js/app.js | 2 | 18 | source |
| docker-compose.yml | 104 | 1.0K | config |
| composer.json | 95 | 1.0K | config |
| .env.example | 99 | 936 | config |
| .env.production.example | 77 | 728 | config |
| public/manifest.json | 63 | 689 | config |
| docker/php/Dockerfile | 32 | 303 | config |
| package.json | 25 | 273 | config |
| .claude/plugins/superpowers-laravel/hooks/hooks.json | 16 | 175 | config |
| docker/php/php.ini | 17 | 161 | config |
| .claude/setting.json | 6 | 66 | config |
| tests/js/board.test.js | 103 | 949 | test |
| docs/superpowers/plans/2026-04-09-recurring-tasks.md | 1403 | 11.7K | docs |
| .claude/skills/jquery/SKILL.md | 1013 | 8.4K | docs |
| .claude/skills/bootstrap-overview/references/rails-setup.md | 863 | 7.2K | docs |
| .claude/skills/bootstrap-components/references/interactive-components.md | 722 | 6.0K | docs |
| docs/superpowers/plans/2026-04-08-laravel-bootstrap-auth.md | 713 | 5.9K | docs |
| docs/superpowers/plans/2026-04-08-kanban-ui.md | 664 | 5.5K | docs |
| .claude/plugins/superpowers-laravel/skills/repository-pattern/reference.md | 627 | 5.2K | docs |
| .claude/skills/bootstrap-components/references/css-custom-properties.md | 569 | 4.7K | docs |
| .claude/plugins/superpowers-laravel/skills/livewire/reference.md | 565 | 4.7K | docs |
| .claude/plugins/superpowers-laravel/skills/cache-strategies/reference.md | 563 | 4.7K | docs |
| .claude/plugins/superpowers-laravel/skills/middleware/reference.md | 549 | 4.6K | docs |
| .claude/plugins/superpowers-laravel/skills/inertia/reference.md | 538 | 4.5K | docs |
| .claude/skills/bootstrap-overview/SKILL.md | 533 | 4.4K | docs |
| .claude/skills/bootstrap-layout/SKILL.md | 527 | 4.4K | docs |
| .claude/skills/bootstrap-components/references/static-components.md | 526 | 4.4K | docs |
| .claude/plugins/superpowers-laravel/skills/eloquent-casts-accessors/reference.md | 522 | 4.3K | docs |
| .claude/plugins/superpowers-laravel/skills/service-providers/reference.md | 519 | 4.3K | docs |
| docs/superpowers/plans/2026-04-08-kanban-docker-infra.md | 459 | 3.8K | docs |
| .claude/plugins/superpowers-laravel/skills/observer-pattern/reference.md | 458 | 3.8K | docs |
| docs/superpowers/specs/2026-04-08-completion-design.md | 433 | 3.6K | docs |
| .claude/plugins/superpowers-laravel/skills/factories-seeders/reference.md | 424 | 3.5K | docs |
| .claude/plugins/superpowers-laravel/skills/tdd-with-phpunit/reference.md | 418 | 3.5K | docs |
| .claude/skills/bootstrap-overview/references/javascript-api.md | 410 | 3.4K | docs |
| docs/superpowers/plans/2026-04-09-parent-child-tasks.md | 390 | 3.3K | docs |
| docs/superpowers/specs/2026-04-08-kanban-recurring-tasks.md | 362 | 3.0K | docs |
| .claude/skills/bootstrap-layout/references/sass-customization.md | 347 | 2.9K | docs |
| .claude/skills/bootstrap-components/references/components-reference.md | 344 | 2.9K | docs |
| .claude/skills/pwa-expert/SKILL.md | 338 | 2.8K | docs |
| docs/superpowers/plans/2026-04-08-postgrest-api.md | 318 | 2.6K | docs |
| docs/superpowers/specs/2026-04-09-parent-child-tasks.md | 316 | 2.6K | docs |
| .claude/skills/bootstrap-overview/references/vite-setup.md | 312 | 2.6K | docs |
| README.md | 295 | 2.5K | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-middleware.md | 277 | 2.3K | docs |
| .claude/skills/bootstrap-layout/references/grid-reference.md | 269 | 2.2K | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-policy.md | 258 | 2.1K | docs |
| .claude/skills/launch-readiness-auditor/SKILL.md | 222 | 1.9K | docs |
| .claude/skills/bootstrap-components/SKILL.md | 217 | 1.8K | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-livewire.md | 214 | 1.8K | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-observer.md | 202 | 1.7K | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-test.md | 187 | 1.6K | docs |
| CLAUDE.md | 184 | 1.5K | docs |
| .claude/skills/pwa-expert/references/service-worker-patterns.md | 174 | 1.4K | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-job.md | 173 | 1.4K | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-api-resource.md | 172 | 1.4K | docs |
| .claude/skills/pwa-expert/references/nextjs-integration.md | 142 | 1.2K | docs |
| .claude/skills/pwa-expert/references/update-flow.md | 133 | 1.1K | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-tdd-phpunit.md | 130 | 1.1K | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-factory.md | 129 | 1.1K | docs |
| .claude/skills/pwa-expert/references/offline-handling.md | 128 | 1.1K | docs |
| .claude/skills/pwa-expert/references/install-prompt.md | 126 | 1.1K | docs |
| docs/PLAN-ADDITIONS.md | 100 | 833 | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-migration.md | 97 | 808 | docs |
| DOCKER.md | 93 | 775 | docs |
| .claude/plugins/superpowers-laravel/commands/laravel-model.md | 78 | 650 | docs |
| .claude/plugins/superpowers-laravel/README.md | 59 | 492 | docs |
| .claude/plugins/superpowers-laravel/skills/cache-strategies/SKILL.md | 43 | 358 | docs |
| .claude/plugins/superpowers-laravel/skills/factories-seeders/SKILL.md | 43 | 358 | docs |
| .claude/plugins/superpowers-laravel/skills/inertia/SKILL.md | 43 | 358 | docs |
| .claude/plugins/superpowers-laravel/skills/livewire/SKILL.md | 43 | 358 | docs |
| .claude/plugins/superpowers-laravel/skills/middleware/SKILL.md | 43 | 358 | docs |
| .claude/plugins/superpowers-laravel/skills/observer-pattern/SKILL.md | 43 | 358 | docs |
| .claude/plugins/superpowers-laravel/skills/service-providers/SKILL.md | 43 | 358 | docs |
| .claude/plugins/superpowers-laravel/skills/tdd-with-phpunit/SKILL.md | 43 | 358 | docs |
| .claude/plugins/superpowers-laravel/skills/testing-database/SKILL.md | 43 | 358 | docs |
| .claude/plugins/superpowers-laravel/skills/api-authentication/SKILL.md | 42 | 350 | docs |
| .claude/plugins/superpowers-laravel/skills/api-resources/SKILL.md | 42 | 350 | docs |
| .claude/plugins/superpowers-laravel/skills/eloquent-relations/SKILL.md | 42 | 350 | docs |
| .claude/plugins/superpowers-laravel/skills/migrations/SKILL.md | 42 | 350 | docs |
| .claude/plugins/superpowers-laravel/skills/policies-gates/SKILL.md | 42 | 350 | docs |
| .claude/plugins/superpowers-laravel/skills/queues-jobs/SKILL.md | 42 | 350 | docs |
| .claude/plugins/superpowers-laravel/skills/requests-validation/SKILL.md | 42 | 350 | docs |
| .claude/plugins/superpowers-laravel/skills/tdd-with-pest/SKILL.md | 42 | 350 | docs |
| .claude/plugins/superpowers-laravel/skills/bootstrap-check/SKILL.md | 40 | 333 | docs |
| .claude/plugins/superpowers-laravel/skills/brainstorming/SKILL.md | 40 | 333 | docs |
| .claude/plugins/superpowers-laravel/skills/eloquent-casts-accessors/SKILL.md | 40 | 333 | docs |
| .claude/plugins/superpowers-laravel/skills/executing-plans/SKILL.md | 40 | 333 | docs |
| .claude/plugins/superpowers-laravel/skills/project-structure/SKILL.md | 40 | 333 | docs |
| .claude/plugins/superpowers-laravel/skills/repository-pattern/SKILL.md | 40 | 333 | docs |
| .claude/plugins/superpowers-laravel/skills/runner-selection/SKILL.md | 40 | 333 | docs |
| .claude/plugins/superpowers-laravel/skills/using-laravel-superpowers/SKILL.md | 40 | 333 | docs |
| .claude/plugins/superpowers-laravel/skills/writing-plans/SKILL.md | 40 | 333 | docs |
| .claude/plugins/superpowers-laravel/skills/quality-checks/SKILL.md | 39 | 325 | docs |
| .claude/plugins/superpowers-laravel/docs/skills-best-practices.md | 26 | 217 | docs |
| .claude/plugins/superpowers-laravel/skills/api-authentication/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/api-resources/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/bootstrap-check/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/brainstorming/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/eloquent-relations/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/executing-plans/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/migrations/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/policies-gates/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/project-structure/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/quality-checks/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/queues-jobs/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/requests-validation/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/runner-selection/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/tdd-with-pest/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/using-laravel-superpowers/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/skills/writing-plans/reference.md | 23 | 192 | docs |
| .claude/plugins/superpowers-laravel/docs/project-examples.md | 22 | 183 | docs |
| .claude/plugins/superpowers-laravel/docs/complexity-tiers.md | 16 | 133 | docs |
| docs/PLANNED-ADDITIONS.md | 14 | 117 | docs |
| .claude/plugins/superpowers-laravel/docs/project-catalog.md | 8 | 67 | docs |
| public/robots.txt | 3 | 23 | docs |
| public/assets/css/app.css | 310 | 2.9K | style |
| .claude/skills/bootstrap-layout/examples/sass-customization.scss | 273 | 2.6K | style |
| resources/css/app.css | 2 | 18 | style |
| .claude/skills/bootstrap-components/examples/card-grid-patterns.html | 525 | 5.3K | other |
| .claude/skills/bootstrap-components/examples/pagination-patterns.html | 514 | 5.1K | other |
| .claude/skills/bootstrap-components/examples/progress-spinner-patterns.html | 514 | 5.1K | other |
| .claude/skills/bootstrap-components/examples/list-group-patterns.html | 504 | 5.0K | other |
| resources/views/kanban.blade.php | 493 | 4.7K | other |
| .claude/skills/bootstrap-components/examples/placeholder-patterns.html | 458 | 4.6K | other |
| .claude/skills/bootstrap-components/examples/dropdown-patterns.html | 439 | 4.4K | other |
| .claude/skills/bootstrap-components/examples/badge-button-patterns.html | 434 | 4.3K | other |
| .claude/skills/bootstrap-components/examples/accordion-patterns.html | 428 | 4.3K | other |
| .claude/skills/bootstrap-components/examples/scrollspy-patterns.html | 387 | 3.9K | other |
| .claude/skills/bootstrap-components/examples/collapse-patterns.html | 328 | 3.3K | other |
| .claude/skills/bootstrap-components/examples/toasts-patterns.html | 303 | 3.0K | other |
| .claude/skills/bootstrap-components/examples/alert-patterns.html | 268 | 2.7K | other |
| resources/views/welcome.blade.php | 278 | 2.6K | other |
| .claude/skills/bootstrap-components/examples/modal-patterns.html | 255 | 2.5K | other |
| .claude/skills/bootstrap-components/examples/breadcrumb-patterns.html | 241 | 2.4K | other |
| .claude/skills/bootstrap-components/examples/carousel-patterns.html | 240 | 2.4K | other |
| .claude/skills/bootstrap-layout/examples/responsive-layouts.html | 240 | 2.4K | other |
| .claude/skills/bootstrap-components/examples/navbar-patterns.html | 238 | 2.4K | other |
| .claude/skills/bootstrap-components/examples/tabs-patterns.html | 220 | 2.2K | other |
| .claude/skills/bootstrap-overview/examples/rails-navbar-partial.html.erb | 231 | 2.2K | other |
| .claude/skills/bootstrap-overview/examples/rails-turbo-modal.html.erb | 231 | 2.2K | other |
| .claude/skills/bootstrap-overview/examples/rails-bootstrap-form.html.erb | 229 | 2.2K | other |
| config/session.php | 218 | 2.1K | other |
| .claude/skills/bootstrap-components/examples/offcanvas-patterns.html | 205 | 2.0K | other |
| .claude/skills/bootstrap-components/examples/popovers-tooltips-patterns.html | 200 | 2.0K | other |
| .claude/skills/bootstrap-overview/examples/rtl-starter.html | 184 | 1.8K | other |
| config/database.php | 185 | 1.8K | other |
| config/app.php | 135 | 1.3K | other |
| tests/Feature/SendRemindersTest.php | 134 | 1.3K | other |
| config/logging.php | 133 | 1.3K | other |
| config/auth.php | 130 | 1.2K | other |
| config/queue.php | 130 | 1.2K | other |
| config/mail.php | 119 | 1.1K | other |
| config/cache.php | 118 | 1.1K | other |
| .claude/skills/bootstrap-overview/examples/starter-template.html | 110 | 1.1K | other |
| .claude/skills/bootstrap-overview/examples/rails-flash-messages.html.erb | 100 | 946 | other |
| install.sh | 98 | 927 | other |
| app/Console/Commands/SendReminders.php | 89 | 842 | other |
| .claude/skills/bootstrap-overview/examples/rails-application-layout.html.erb | 88 | 832 | other |
| config/filesystems.php | 81 | 766 | other |
| .claude/plugins/superpowers-laravel/hooks/session-start.sh | 73 | 691 | other |
| app/Models/Task.php | 69 | 653 | other |
| app/Auth/SingleUserProvider.php | 58 | 549 | other |
| database/migrations/0001_01_01_000002_create_jobs_table.php | 58 | 549 | other |
| database/migrations/2026_04_09_110000_create_active_tasks_with_notes_view.php | 58 | 549 | other |
| database/migrations/2026_04_09_100002_add_inherit_parent_category_trigger.php | 52 | 492 | other |
| app/Http/Controllers/Auth/LoginController.php | 51 | 482 | other |
| app/Models/User.php | 50 | 473 | other |
| database/migrations/0001_01_01_000000_create_users_table.php | 50 | 473 | other |
| docker/caddy/Caddyfile | 50 | 473 | other |
| app/Models/RecurrenceRule.php | 48 | 454 | other |
| public/offline.html | 44 | 440 | other |
| app/Auth/SingleUser.php | 46 | 435 | other |
| database/factories/UserFactory.php | 46 | 435 | other |
| app/Http/Controllers/AgentTokenController.php | 45 | 426 | other |
| resources/views/privacy.blade.php | 44 | 416 | other |
| phpunit.xml | 37 | 405 | other |
| resources/views/auth/login.blade.php | 42 | 397 | other |
| agent-test.sh | 40 | 378 | other |
| config/services.php | 39 | 369 | other |
| database/migrations/2026_04_09_090000_create_tasks_table.php | 39 | 369 | other |
| database/migrations/0001_01_01_000001_create_cache_table.php | 36 | 341 | other |
| database/migrations/2026_04_09_090001_create_task_notes_table.php | 36 | 341 | other |
| .gitignore | 33 | 312 | other |
| database/migrations/2026_04_09_100001_create_recurrence_rules_table.php | 32 | 303 | other |
| database/migrations/2026_04_09_110002_fix_task_notes_defaults.php | 32 | 303 | other |
| app/Notifications/TaskReminder.php | 29 | 274 | other |
| database/migrations/2026_04_09_110001_create_agent_role.php | 29 | 274 | other |
| bootstrap/app.php | 28 | 265 | other |
| database/migrations/2026_04_09_022534_alter_sessions_user_id_to_text.php | 28 | 265 | other |
| tests/Feature/ExampleTest.php | 27 | 255 | other |
| database/migrations/2026_04_09_100000_add_disable_notifications_to_tasks.php | 26 | 246 | other |
| database/seeders/DatabaseSeeder.php | 26 | 246 | other |
| public/.htaccess | 26 | 246 | other |
| app/Providers/AppServiceProvider.php | 25 | 236 | other |
| docker/postgrest/postgrest.conf | 23 | 218 | other |
| routes/web.php | 22 | 208 | other |
| public/index.php | 21 | 199 | other |
| .editorconfig | 19 | 180 | other |
| artisan | 19 | 180 | other |
| app/Providers/SingleUserAuthServiceProvider.php | 18 | 170 | other |
| tests/Unit/ExampleTest.php | 17 | 161 | other |
| tests/TestCase.php | 16 | 151 | other |
| routes/console.php | 13 | 123 | other |
| .gitattributes | 12 | 114 | other |
| bootstrap/providers.php | 10 | 95 | other |
| storage/framework/.gitignore | 10 | 95 | other |
| app/Http/Controllers/Controller.php | 9 | 85 | other |
| routes/api.php | 7 | 66 | other |
| phpstan.neon | 5 | 47 | other |
| storage/app/.gitignore | 5 | 47 | other |
| storage/framework/cache/.gitignore | 4 | 38 | other |
| bootstrap/cache/.gitignore | 3 | 28 | other |
| storage/app/private/.gitignore | 3 | 28 | other |
| storage/app/public/.gitignore | 3 | 28 | other |
| storage/framework/cache/data/.gitignore | 3 | 28 | other |
| storage/framework/sessions/.gitignore | 3 | 28 | other |
| storage/framework/testing/.gitignore | 3 | 28 | other |
| storage/framework/views/.gitignore | 3 | 28 | other |
| storage/logs/.gitignore | 3 | 28 | other |
| .claude/plugins/superpowers-laravel/LICENSE | 2 | 19 | other |
| database/.gitignore | 2 | 19 | other |

## Summary
- Source: 12 files, ~20.8K tokens
- Config: 10 files, ~5.4K tokens
- Test: 1 files, ~949 tokens
- Docs: 112 files, ~204.1K tokens
- Style: 3 files, ~5.5K tokens
- Other: 103 files, ~119.1K tokens

## Heaviest files (read these with offset/limit)
1. public/assets/js/app.js — 1426 lines (~13.1K tokens)
2. docs/superpowers/plans/2026-04-09-recurring-tasks.md — 1403 lines (~11.7K tokens)
3. .claude/skills/jquery/SKILL.md — 1013 lines (~8.4K tokens)
4. .claude/skills/bootstrap-overview/references/rails-setup.md — 863 lines (~7.2K tokens)
5. .claude/skills/bootstrap-components/references/interactive-components.md — 722 lines (~6.0K tokens)
6. docs/superpowers/plans/2026-04-08-laravel-bootstrap-auth.md — 713 lines (~5.9K tokens)
7. docs/superpowers/plans/2026-04-08-kanban-ui.md — 664 lines (~5.5K tokens)
8. .claude/plugins/superpowers-laravel/skills/repository-pattern/reference.md — 627 lines (~5.2K tokens)
9. .claude/skills/bootstrap-components/references/css-custom-properties.md — 569 lines (~4.7K tokens)
10. .claude/plugins/superpowers-laravel/skills/livewire/reference.md — 565 lines (~4.7K tokens)
11. .claude/plugins/superpowers-laravel/skills/cache-strategies/reference.md — 563 lines (~4.7K tokens)
12. .claude/plugins/superpowers-laravel/skills/middleware/reference.md — 549 lines (~4.6K tokens)
13. .claude/plugins/superpowers-laravel/skills/inertia/reference.md — 538 lines (~4.5K tokens)
14. .claude/skills/bootstrap-overview/SKILL.md — 533 lines (~4.4K tokens)
15. .claude/skills/bootstrap-layout/SKILL.md — 527 lines (~4.4K tokens)
16. .claude/skills/bootstrap-components/references/static-components.md — 526 lines (~4.4K tokens)
17. .claude/skills/bootstrap-components/examples/card-grid-patterns.html — 525 lines (~5.3K tokens)
18. .claude/plugins/superpowers-laravel/skills/eloquent-casts-accessors/reference.md — 522 lines (~4.3K tokens)
19. .claude/plugins/superpowers-laravel/skills/service-providers/reference.md — 519 lines (~4.3K tokens)
20. .claude/skills/bootstrap-components/examples/pagination-patterns.html — 514 lines (~5.1K tokens)
21. .claude/skills/bootstrap-components/examples/progress-spinner-patterns.html — 514 lines (~5.1K tokens)
22. docs/superpowers/specs/2026-04-08-kanban-design.md — 514 lines (~4.3K tokens)
23. .claude/skills/bootstrap-components/examples/list-group-patterns.html — 504 lines (~5.0K tokens)
24. .claude/skills/bootstrap-overview/references/build-tools.md — 502 lines (~4.2K tokens)
