# phpKanMaster — Parent/Child Task Relationships Spec

**Date:** 2026-04-09
**Extends:** `2026-04-08-kanban-design.md`
**Status:** Draft

---

## 1. Overview

Enable hierarchical task management by allowing tasks to have subtasks (children). A parent task groups related work while children represent individual actionable items.

**Key Behaviors:**
- Parent tasks show a collapsible list of children directly on the card
- Completing all children marks the parent as "all complete" (visual indicator)
- Children can be moved between columns independently (no column inheritance)
- Deleting a parent cascades to delete all children
- Children inherit `category_id` from parent by default (can be overridden)

---

## 2. Database Schema

Schema already supports parent/child via `parent_id` on `tasks` table (added in `04-schema-updates.sql`):

```sql
-- tasks.parent_id already exists:
parent_id uuid references tasks(id) on delete cascade
```

**Indexes** (add if not present in `04-schema-updates.sql`):

```sql
create index idx_tasks_parent_id on tasks (parent_id);
```

---

## 3. UI Design

### 3.1 Parent Task Card — Collapsible Children

**Default state:** Children collapsed, shown as a count badge.

```
┌─────────────────────────────────────────────┐
│ ● My Parent Task                    [⋮] 🔔 │
│                                             │
│ Due: Apr 15  │  🏷 Business                 │
│                                             │
│ ▼ 3 subtasks (2 remaining)                  │
└─────────────────────────────────────────────┘
```

**Expanded state:**

```
┌─────────────────────────────────────────────┐
│ ● My Parent Task                    [⋮] 🔔 │
│                                             │
│ Due: Apr 15  │  🏷 Business                 │
│                                             │
│ ▲ 3 subtasks (2 remaining)                  │
├─────────────────────────────────────────────┤
│   ○ Subtask 1                        [⋮]   │
│   ○ Subtask 2                        [⋮]   │
│   ○ Subtask 3 (done)                 [⋮]   │
└─────────────────────────────────────────────┘
```

**Legend:**
- `▼` collapsed / `▲` expanded (clickable to toggle)
- `3 subtasks (2 remaining)` — shows completion count
- Child cards are indented 24px with left border
- Child cards show only: checkbox, title, menu `[⋮]`
- Child cards do NOT show: due date, category, bell (inherited from parent)

### 3.2 Child Task Card States

| State | Visual |
|-------|--------|
| Incomplete | ○ (empty circle) |
| Complete | ● (filled circle, muted text) |

### 3.3 Add Child Task

**Trigger:** "Add subtask" link below expanded children list, or context menu on parent card.

```
┌─────────────────────────────────────────────┐
│ + Add subtask                               │
└─────────────────────────────────────────────┘
```

Clicking opens a simplified task modal (title only, no description/dates initially).

### 3.4 Context Menu — Parent Task

Existing `[⋮]` menu adds items:

| Menu Item | Action |
|-----------|--------|
| Add subtask | Opens simplified task modal with parent_id pre-filled |
| Expand all subtasks | Expands all child groups across all parent cards |
| Collapse all subtasks | Collapses all child groups |

### 3.5 Drag and Drop

**Behavior:**
- Parent cards drag independently
- Child cards drag within parent card only (no cross-parent moves)
- Drop on parent card does NOT convert to child (parents receive children via modal only)

---

## 4. Completion Propagation

### Completion Behavior: Manual (Chosen)

Parent shows "1/3 complete" indicator but does NOT auto-complete when all children are done. User must manually mark parent as complete.

---

## 5. API Changes

### 5.1 GET /api/tasks — Response Shape

```json
{
  "id": "uuid",
  "title": "Parent Task",
  "task_column": "in_progress",
  "children": [
    {
      "id": "uuid",
      "title": "Subtask 1",
      "task_column": "new",
      "position": 0
    }
  ]
}
```

**PostgREST Config:** Add computed relationship for `tasks.children` (self-referential):

```sql
-- In 04-schema-updates.sql or PostgREST config
alter table tasks add column children jsonb;
update tasks set children = (
  select jsonb_agg(jsonb_build_object('id', id, 'title', title, 'task_column', task_column, 'position', position))
  from tasks t2 where t2.parent_id = tasks.id
);
```

### 5.2 PATCH /api/tasks — Toggle Child Completion

```javascript
// Toggle child task complete/incomplete
PATCH /api/tasks?id=eq.{child-id}
{ "task_column": "done" }  // or original column to reopen
```

### 5.3 POST /api/tasks — Create Child Task

```javascript
{
  "title": "New subtask",
  "parent_id": "{parent-uuid}",
  "category_id": "{inherited-from-parent-uuid}",
  "position": 0
}
```

### 5.4 Cascade Delete

PostgreSQL `on delete cascade` on `parent_id` FK already handles this.

---

## 6. JavaScript App Structure

### 6.1 Data Fetching

```javascript
App.Api.getTasks = async function() {
    const response = await fetch('/api/tasks?select=*,children:id,title,task_column,position');
    const tasks = await response.json();
    return tasks;
};
```

### 6.2 Render Updates

Modify `App.Board.renderColumn` to:

1. Separate tasks into `parentTasks` (no `parent_id`) and `childTasks` (has `parent_id`)
2. Attach children to their parents as a data property
3. Render parent cards first, then children nested within parent cards

```javascript
App.Board.renderColumn = function(columnId, tasks) {
    const parents = tasks.filter(t => !t.parent_id);
    const childrenByParent = tasks.filter(t => t.parent_id)
        .reduce((acc, child) => {
            (acc[child.parent_id] ||= []).push(child);
            return acc;
        }, {});

    parents.forEach(parent => {
        const children = childrenByParent[parent.id] || [];
        const html = App.Board.renderParentCard(parent, children);
        // ... append to column
    });
};
```

### 6.3 Toggle Children Expansion

```javascript
App.Board.toggleChildren = function(parentId) {
    const card = document.querySelector(`[data-id="${parentId}"]`);
    const childList = card.querySelector('.child-tasks-list');
    const toggle = card.querySelector('.children-toggle');

    if (childList.classList.contains('d-none')) {
        childList.classList.remove('d-none');
        toggle.textContent = '▲';
    } else {
        childList.classList.add('d-none');
        toggle.textContent = '▼';
    }
};
```

### 6.4 Toggle Child Complete

```javascript
App.Board.toggleChildComplete = async function(childId, currentColumn) {
    const newColumn = currentColumn === 'done' ? 'new' : 'done';
    try {
        const response = await fetch(`/api/tasks?id=eq.${childId}`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ task_column: newColumn })
        });
        if (response.ok) {
            App.Board.refreshColumn(task_column); // or update card in place
        }
    } catch (err) {
        App.Alerts.Toast.fire({ icon: 'error', title: 'Failed to update subtask' });
    }
};
```

---

## 7. Add/Edit Task Modal — Parent Field

**Visible dropdown** for selecting a parent task (shown below the Column selector):

```
┌─ Parent Task ────────────────────┐
│ [— none — ▼]                     │
└──────────────────────────────────┘
```

- Populated with all tasks that are not already children (excludes `parent_id` set tasks and self when editing)
- When a parent is selected, the child starts in `new` column regardless of the Column selector value

**Hidden field** `parent_id` for pre-filling when opened from parent's "Add subtask":

```html
<input type="hidden" id="task-parent-id" value="">
```

When `parent_id` is set via "Add subtask" flow:
- Skip column selector (child starts in `new`)
- Skip category selector (inherits from parent)
- Skip due date/priority (optional fields)
- Only title and description editable

---

## 8. Edge Cases

| Scenario | Behavior |
|----------|----------|
| Drag child to another column | Allowed — child moves independently |
| Delete parent | Cascade deletes all children |
| Delete child | Parent remains, updates child count |
| Child has its own children | Not supported (max depth = 1) |
| Move parent to `done` | Does NOT auto-complete children |
| Add child to task in `done` column | Allowed — parent shows mixed state |

---

## 9. Testing Considerations

- Create parent → add children → verify count badge
- Expand/collapse children list
- Toggle child completion → verify count updates
- Delete parent → verify children deleted
- Drag child to different column
- Add subtask from context menu
- Verify PostgREST computed `children` field works

---

## 10. Future Enhancements (Out of Scope)

- Nested subtasks (depth > 1)
- Drag child to make it a top-level task
- Bulk edit children (rename, change category)
- Child task templates (save as template)
- WBS/Work Breakdown Structure view mode
