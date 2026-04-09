# Launch Readiness Audit: phpKanMaster

## Executive Summary
- **Overall Score**: 72/100
- **Launch Readiness**: SOFT LAUNCH (MVP achieved, needs polish)
- **Estimated Time to Shippable**: 1-2 weeks
- **Confidence**: High (core features complete, tests passing)

---

## Health Scores

| Dimension | Score | Notes |
|-----------|-------|-------|
| Feature Completeness | 80/100 | Core kanban complete; subtasks/recurrence done. File attachments untested. |
| Test Coverage | 45/100 | 3 test files, 11 tests, 8 errors (pre-existing Laravel 12 RefreshDatabase issue). Tests for SendReminders are solid. |
| Error Handling | 75/100 | SweetAlert2 toasts, JSON error responses via PostgREST, but no global exception handler. |
| Security Posture | 85/100 | Single-user bcrypt auth, no SQL injection (PostgREST parameterized), HTTPS via Caddy. |
| Documentation | 90/100 | Excellent README + CLAUDE.md. Inline code comments minimal. |
| Build & Deploy | 95/100 | Docker Compose fully configured, automated install script present. |
| Performance | 70/100 | CDN assets load fast; no query optimization done on PostgREST level. |
| User Experience | 75/100 | Functional kanban with drag-drop, modals, filters. Mobile touch support exists. PWA install prompt present. |

---

## Feature Triage

### Ship It (>80% done)
- **Kanban board** - Columns, task cards, drag-and-drop ✓
- **Task CRUD** - Create, edit, delete tasks via PostgREST ✓
- **Categories** - Create, color, filter tasks ✓
- **Priority & Due Dates** - Full implementation ✓
- **Subtasks** - Parent-child relationships with category inheritance ✓
- **Recurring Tasks** - RRule-based recurrence with `reminders:send` ✓
- **Notifications** - Pushover, Twilio, RocketChat scaffolding ✓
- **PWA** - Service worker, offline caching, install prompt ✓
- **Authentication** - Single-user bcrypt, session-based ✓

### Sprint It (50-80% done)
- **File Attachments** - Schema exists (`task_files`), API endpoints exist in JS, but no upload UI
- **Mobile UX** - Touch-friendly but no dedicated mobile layout refinements
- **Search/Filter** - Category filter only; no text search across tasks

### Defer It (<50% done)
- **Multi-board** - Only single board supported; may not be needed for single-user
- **Task Comments/Activity Log** - Not in schema
- **Keyboard Shortcuts** - None implemented

### Cut It (Not worth it)
- **User Management** - Explicitly single-user; multi-user would require complete rewrite

---

## Critical Blockers

1. **Test Suite Errors (8/11 tests failing)** - Severity: **High**
   - Cause: Laravel 12 changed `RefreshDatabase` behavior; tests use `RefreshDatabase` trait but DB isn't properly set up for SQLite in-memory testing
   - Impact: CI unreliable, hard to catch regressions
   - Fix: Replace `RefreshDatabase` with direct `DatabaseMigrations` or manual migration setup

2. **Missing Text Search** - Severity: **Medium**
   - Users cannot search task titles/descriptions
   - Fix: Add PostgREST `ilike` filter on `/tasks?title=ilike.*search*`

3. **File Attachment UI Missing** - Severity: **Medium**
   - Backend exists (`task_files` table, `uploadFile` JS method), but no UI to attach files to tasks
   - Fix: Add file input to task modal

4. **No Analytics/Monitoring** - Severity: **Medium**
   - No error tracking (Sentry), no usage metrics
   - Fix: Add Laravel logging to external provider, or at minimum log to `storage/logs/`

---

## MVP Definition

phpKanMaster MVP for a personal productivity tool is essentially **current state minus polish**. The core value prop (kanban board with tasks, categories, recurring reminders) works.

**MVP Core (already working):**
1. Single-user login
2. Kanban board with 5 columns
3. Task CRUD with priority and due dates
4. Category filtering
5. Drag-and-drop reordering
6. Recurring tasks via cron scheduler
7. PWA install for offline access

---

## 2-Week Sprint Plan

### Week 1: Fix Critical Issues
| Day | Task |
|-----|------|
| 1-2 | Fix test suite - replace `RefreshDatabase` with direct migrations |
| 3-4 | Add text search to kanban (PostgREST `ilike` query) |
| 5 | Add file attachment UI to task modal |

### Week 2: Polish & Launch Prep
| Day | Task |
|-----|------|
| 1-2 | Mobile UX polish - refine touch interactions, test on real device |
| 3-4 | Add error logging/monitoring (at minimum log to file) |
| 5 | Write privacy policy, finalize `.env` production config, deployment docs |

---

## Recommendations

1. **Fix the tests first** - 8/11 errors is a red flag that will bite you. Replace `RefreshDatabase` with `DatabaseMigrations` and use SQLite explicitly.

2. **Add text search before launch** - Without search, users with many tasks will struggle to find anything.

3. **Ship the PWA** - You already have service worker + install prompt. This is a strong differentiator for a productivity app - users can add it to home screen like a native app.

4. **Add a simple privacy policy** - Since this is single-user and runs locally, you just need to state that data stays local. 1 page is enough.
