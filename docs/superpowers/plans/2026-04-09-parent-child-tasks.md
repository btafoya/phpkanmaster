# Implementation Plan: Parent/Child Task Relationships

**Spec:** `2026-04-09-parent-child-tasks.md`
**Date:** 2026-04-09

---

## Step 1: Database — Add index and children computed column

**File:** `docker/db/init/04-schema-updates.sql`

```sql
-- Index on parent_id for efficient child lookups
create index if not exists idx_tasks_parent_id on tasks (parent_id);

-- Computed children column for PostgREST
alter table tasks add column if not exists children jsonb;
update tasks set children = (
  select jsonb_agg(jsonb_build_object('id', id, 'title', title, 'task_column', task_column, 'position', position) order by position)
  from tasks t2 where t2.parent_id = tasks.id
);
```

Grant select on children to anon (add to end of `04-schema-updates.sql`):
```sql
grant select on tasks to anon;
```

---

## Step 2: API — Modify getTasks to include children

**File:** `public/assets/js/app.js`

In `App.Api.getTasks`, change select to include children:

```javascript
async getTasks() {
    return this.request('/tasks?select=*,recurrence_rules(id,active),children:id,title,task_column,position&order=task_column.asc,position.asc');
},
```

---

## Step 3: JS — Modify App.Board.render to separate parents and children

**File:** `public/assets/js/app.js`, in `App.Board.render()`

Replace the flat `filteredTasks.forEach` with parent/child grouping:

```javascript
async render() {
    const tasks = await App.Api.getTasks();
    const categories = await App.Api.getCategories();

    const categoryMap = Object.fromEntries(categories.map(c => [c.id, c]));

    // Clear all lists
    $('.task-list').empty();

    const filteredTasks = this.currentFilter === 'all'
        ? tasks
        : tasks.filter(t => t.category_id === this.currentFilter);

    // Separate parents (no parent_id) from children (has parent_id)
    const parents = filteredTasks.filter(t => !t.parent_id);
    const childrenByParent = filteredTasks
        .filter(t => t.parent_id)
        .reduce((acc, child) => {
            (acc[child.parent_id] ||= []).push(child);
            return acc;
        }, {});

    parents.forEach(parent => {
        const children = childrenByParent[parent.id] || [];
        const category = categoryMap[parent.category_id];
        const card = this.createParentCard(parent, children, category);
        $(`#list-${parent.task_column}`).append(card);
    });

    this.updateCounts();
},
```

---

## Step 4: JS — Add createParentCard method

**File:** `public/assets/js/app.js`, in `App.Board`

Add new method that wraps `createTaskCard` with a children section:

```javascript
createParentCard(task, children, category) {
    // Render base card (same as createTaskCard but without appending)
    const priorityClass = `priority-${task.priority || 'low'}`;
    const categoryBadge = category
        ? `<span class="badge" style="background-color: ${category.color}">${category.name}</span>`
        : '';
    const borderStyle = category ? `style="border-left-color: ${category.color}"` : '';
    const dueDate = task.due_date ? `<div class="small text-muted"><i class="far fa-calendar-alt"></i> ${task.due_date}</div>` : '';
    const hasActiveRule = task.recurrence_rules?.some(r => r.active);
    const recurrenceBadge = hasActiveRule ? `<span class="badge bg-secondary ms-1" style="font-size:0.65rem">🔁</span>` : '';
    const bellIcon = task.reminder_at
        ? `<button type="button" class="btn btn-link btn-sm p-0 ms-1 bell-icon" data-action="toggle-bell" data-id="${task.id}">${task.disable_notifications ? '🔕' : '🔔'}</button>`
        : '';

    const doneCount = children.filter(c => c.task_column === 'done').length;
    const remainingCount = children.length - doneCount;
    const childrenSummary = children.length > 0
        ? `<div class="small text-muted children-summary mt-2 pt-2 border-top children-toggle" data-action="toggle-children" data-parent-id="${task.id}" style="cursor:pointer">
             <i class="fas fa-chevron-down me-1"></i>${children.length} subtask${children.length !== 1 ? 's' : ''} (${remainingCount} remaining)
           </div>`
        : `<div class="small text-muted children-summary mt-2 pt-2 border-top children-toggle" data-action="toggle-children" data-parent-id="${task.id}" style="cursor:pointer;display:none">
             <i class="fas fa-chevron-down me-1"></i>0 subtasks
           </div>`;

    const childrenHtml = children.length > 0 ? this.renderChildTasks(children) : '';
    const addSubtaskHtml = `<div class="small text-primary mt-2 add-subtask" data-action="add-subtask" data-parent-id="${task.id}" style="cursor:pointer"><i class="fas fa-plus me-1"></i>Add subtask</div>`;

    const $card = $(`
        <div class="card mb-3 task-card card-task ${priorityClass} shadow-sm" data-id="${task.id}" data-is-parent="true" ${borderStyle}>
            <div class="card-body p-3">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div class="d-flex align-items-center gap-1">
                        ${categoryBadge}${bellIcon}${recurrenceBadge}
                    </div>
                    <div class="d-flex gap-1">
                        <button class="btn btn-link btn-sm p-0 text-muted" data-action="edit" title="Edit task"><i class="fas fa-pen"></i></button>
                        <button class="btn btn-link btn-sm p-0 text-danger" data-action="delete" title="Delete task"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <h6 class="card-title mb-1">${task.title}</h6>
                <p class="card-text small text-muted mb-2">${task.description ? task.description.substring(0, 60) + '...' : ''}</p>
                <div class="d-flex justify-content-between align-items-center">
                    ${dueDate}
                    <span class="badge bg-light text-dark border">${task.priority}</span>
                </div>
                ${childrenSummary}
                <div class="child-tasks-list d-none mt-2 ps-3 border-start border-2" style="border-color: #dee2e6 !important;">
                    ${childrenHtml}
                    ${addSubtaskHtml}
                </div>
            </div>
        </div>
    `);

    return $card;
},

renderChildTasks(children) {
    return children.map(child => {
        const isDone = child.task_column === 'done';
        const completedClass = isDone ? 'text-muted text-decoration-line-through' : '';
        return $(`
            <div class="d-flex align-items-center gap-2 py-1 child-task" data-id="${child.id}" data-task-column="${child.task_column}" data-action="toggle-child" style="cursor:pointer">
                <span class="${completedClass}">${isDone ? '●' : '○'}</span>
                <span class="${completedClass}">${child.title}</span>
            </div>
        `).get(0);
    }).map($el => $el.outerHTML).join('');
},
```

---

## Step 5: JS — Add toggleChildren method

**File:** `public/assets/js/app.js`, in `App.Board`

```javascript
toggleChildren(parentId) {
    const card = document.querySelector(`.task-card[data-id="${parentId}"]`);
    const childList = card.querySelector('.child-tasks-list');
    const summary = card.querySelector('.children-summary i');

    if (childList.classList.contains('d-none')) {
        childList.classList.remove('d-none');
        summary.classList.remove('fa-chevron-down');
        summary.classList.add('fa-chevron-up');
    } else {
        childList.classList.add('d-none');
        summary.classList.remove('fa-chevron-up');
        summary.classList.add('fa-chevron-down');
    }
},
```

---

## Step 6: JS — Add toggleChildComplete method

**File:** `public/assets/js/app.js`, in `App.Board`

```javascript
toggleChildComplete(childId, currentColumn) {
    const newColumn = currentColumn === 'done' ? 'new' : 'done';
    App.Api.updateTask(childId, { task_column: newColumn })
        .then(() => App.Board.render())
        .catch(() => App.Alerts.Toast.fire({ icon: 'error', title: 'Failed to update subtask' }));
},
```

---

## Step 7: JS — Event delegation for new actions

**File:** `public/assets/js/app.js`, in `App.Board.init()`

Add delegation for the new data-actions:

```javascript
$(document)
    .on('click', '[data-action="toggle-children"]', function(e) {
        e.stopPropagation();
        const parentId = $(this).data('parent-id');
        App.Board.toggleChildren(parentId);
    })
    .on('click', '[data-action="toggle-child"]', function(e) {
        e.stopPropagation();
        const $el = $(this);
        const childId = $el.data('id');
        const currentColumn = $el.data('task-column');
        App.Board.toggleChildComplete(childId, currentColumn);
    })
    .on('click', '[data-action="add-subtask"]', function(e) {
        e.stopPropagation();
        const parentId = $(this).data('parent-id');
        App.Modal.Task.openSubtask(parentId);
    });
```

---

## Step 8: JS — Add openSubtask method to App.Modal.Task

**File:** `public/assets/js/app.js`, in `App.Modal.Task`

Add new method for creating subtasks (simplified modal):

```javascript
async openSubtask(parentId) {
    const form = $('#taskForm')[0];
    form.reset();
    $('#summernote').summernote('code', '';

    $('#taskModalTitle').text('Add Subtask');
    $(form).find('[name="id"]').val('');
    $('#task-parent-id').val(parentId);

    // Hide parent-inherited fields for subtask
    $('#taskColumnGroup, #categorySelect, #dueDateGroup, #priorityGroup').hide();

    new bootstrap.Modal(document.getElementById('taskModal')).show();
},
```

Add hidden field in `kanban.blade.php` or in the modal HTML:

```html
<input type="hidden" id="task-parent-id" name="parent_id" value="">
```

---

## Step 9: JS — Modify save to handle parent_id

**File:** `public/assets/js/app.js`, in `App.Modal.Task.save()`

Add `parent_id` to allowed fields and to the data payload when present:

```javascript
// In allowed array:
const allowed = ['id', 'title', 'description', 'priority', 'category_id', 'due_date', 'task_column', 'reminder_at', 'pushover_priority', 'parent_id'];

// After building data object, handle parent_id:
const parentId = $('#task-parent-id').val();
if (parentId) {
    data.parent_id = parentId;
    data.task_column = data.task_column || 'new'; // Children always start in 'new'
}
```

Also reset field visibility in `open()`:

```javascript
$('#taskColumnGroup, #categorySelect, #dueDateGroup, #priorityGroup').show();
$('#task-parent-id').val('');
```

---

## Step 10: Context menu — Add "Add subtask" option

**File:** `public/assets/js/app.js`

Find where context menu items are defined and add:

```javascript
// Add to the task card context menu / edit dropdown:
`<a class="dropdown-item" href="#" data-action="add-subtask" data-id="${task.id}"><i class="fas fa-level-down-alt me-2"></i>Add subtask</a>`
```

Add handler:

```javascript
.on('click', '[data-action="add-subtask"]', function(e) {
    e.preventDefault();
    const id = $(this).data('id');
    App.Modal.Task.openSubtask(id);
});
```

---

## Step 11: DnD — Restrict child drag within parent

**File:** `public/assets/js/app.js`, in `App.DnD.setupSortable`

Find the jQuery UI Sortable setup and make child lists independently sortable:

```javascript
// Existing: make columns sortable
$('.task-list').sortable({ ... });

// Add: make child lists sortable (within parent only)
$('.child-tasks-list').sortable({
    items: '.child-task',
    handle: '[data-action="toggle-child"]',
    axis: 'y',
    update: function(e, ui) {
        // Reorder within parent - PATCH position for all children
        const parentId = ui.item.closest('.task-card').dataset.id;
        const children = [...$(this).find('.child-task')].map((el, idx) => ({
            id: el.dataset.id,
            position: idx
        }));
        children.forEach(c => App.Api.updateTask(c.id, { position: c.position }));
    }
});
```

**Note:** Child drag does NOT move between parents. Children are identified by `parent_id` on the task record, so dropping in a different parent's child list has no effect.

---

## Step 12: Apply schema changes and test

1. **Apply schema to existing database:**
   ```bash
   docker compose exec db psql -U kanmaster -d kanmaster -f /docker-entrypoint-initdb.d/04-schema-updates.sql
   ```

2. **Restart PostgREST to pick up schema changes:**
   ```bash
   docker compose restart postgrest
   ```

3. **Test scenarios:**
   - Create parent task → add 3 subtasks → verify count badge shows "3 subtasks (3 remaining)"
   - Toggle one subtask to done → verify "2 remaining"
   - Expand/collapse children list
   - Delete parent → verify children cascade deleted
   - Drag subtask to different column
   - Add subtask via context menu

---

## Files to Modify

| File | Changes |
|------|---------|
| `docker/db/init/04-schema-updates.sql` | Add idx_tasks_parent_id index, children computed column, grant select |
| `public/assets/js/app.js` | Steps 2–11 (Api.getTasks, Board.render, createParentCard, toggleChildren, toggleChildComplete, openSubtask, event handlers, DnD, context menu) |
| `resources/views/kanban.blade.php` | Add hidden `#task-parent-id` field to task modal |

---

## Verification Checklist

- [ ] PHPStan passes: `composer test`
- [ ] Subtask count badge appears on parent cards
- [ ] Children expand/collapse on click
- [ ] Toggling child completion updates count badge
- [ ] "Add subtask" opens simplified modal
- [ ] Context menu shows "Add subtask"
- [ ] Deleting parent cascades to children
- [ ] Child drag works within parent
- [ ] No regression in existing task CRUD
