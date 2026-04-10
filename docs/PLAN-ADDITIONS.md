# Plan: PLANNED-ADDITIONS.md Items

## Issue 1: Subtask title click opens edit view instead of view modal

### Root Cause
`public/assets/js/app.js:1239` — the `[data-action="edit-child"]` handler calls `App.Modal.Task.open(childId)` with no second argument, defaulting to `'edit'` mode. The fix is to pass `'view'` as the second argument.

### Fix
**File:** `public/assets/js/app.js`

**Line ~1239** — Change:
```javascript
$(document).on('click', '[data-action="edit-child"]', function(e) {
    e.stopPropagation();
    const childId = $(this).data('id');
    App.Modal.Task.open(childId);
});
```

To:
```javascript
$(document).on('click', '[data-action="edit-child"]', function(e) {
    e.stopPropagation();
    const childId = $(this).data('id');
    App.Modal.Task.open(childId, 'view');
});
```

---

## Issue 2: Make subtasks appear in both parent card list AND respective status column

### Root Cause
`public/assets/js/app.js:206-224` — during board render, tasks with `parent_id` are filtered out of the main task list and only rendered inside their parent's card. The filtering happens here:
```javascript
const parents = filteredTasks.filter(t => !t.parent_id);
```

### Fix
Modify the board render logic to render **all** tasks as cards in their respective columns (not just parents), while the parent card's subtask list remains unchanged.

**File:** `public/assets/js/app.js`

**Lines ~206-224** — Change the render loop to iterate over all `filteredTasks` instead of just `parents`:

```javascript
// Current code splits parents and children:
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
```

**Change to:**
```javascript
// Still build childrenByParent for parent card subtask lists
const childrenByParent = filteredTasks
    .filter(t => t.parent_id)
    .reduce((acc, child) => {
        (acc[child.parent_id] ||= []).push(child);
        return acc;
    }, {});

// Render ALL tasks (parents and children) as cards in their respective columns
filteredTasks.forEach(task => {
    const isSubtask = !!task.parent_id;
    const children = childrenByParent[task.id] || [];
    const category = categoryMap[task.category_id];

    if (isSubtask) {
        // Subtasks render as normal cards (no expand/collapse, no add-subtask link)
        const card = this.createSubtaskCard(task);
        $(`#list-${task.task_column}`).append(card);
    } else {
        // Parent tasks get the full card with subtask list
        const card = this.createParentCard(task, children, category);
        $(`#list-${task.task_column}`).append(card);
    }
});
```

**New method needed** — `createSubtaskCard(task)`:
A simplified version of `createParentCard` for subtask cards that appear in columns. Should render as a normal task card (clickable to view/edit) but **without** the subtask expand/collapse toggle and without the "Add subtask" link.

### Verification
1. Subtasks should appear as cards in their own column (e.g., a subtask with `task_column: 'in_progress'` appears in the In Progress column)
2. Subtasks should still appear in their parent's subtask list when expanded
3. Clicking a subtask card in a column should open the view modal (same as regular task view)
4. Clicking a subtask title in the parent's subtask list should also open the view modal (Issue 1 fix)
