/**
 * phpKanMaster - Main Application
 */

window.App = {
    Api: {
        baseUrl: window.POSTGREST_URL || '/api',

        async request(endpoint, options = {}) {
            const url = `${this.baseUrl}${endpoint}`;
            const response = await fetch(url, {
                ...options,
                headers: {
                    'Content-Type': 'application/json',
                    'Prefer': 'return=representation',
                    ...options.headers,
                },
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || `API Error: ${response.status}`);
            }

            return response.json();
        },

        async getTasks(searchTerm = '') {
            let query = '/tasks?select=*,recurrence_rules(id,active),children:id,title,task_column,position&order=task_column.asc,position.asc';
            if (searchTerm) {
                const encoded = encodeURIComponent(searchTerm);
                query += `&or=(title.ilike.*${encoded}*,description.ilike.*${encoded}*)`;
            }
            return this.request(query);
        },

        async createTask(data) {
            return this.request('/tasks', {
                method: 'POST',
                body: JSON.stringify(data),
            });
        },

        async updateTask(id, data) {
            return this.request(`/tasks?id=eq.${id}`, {
                method: 'PATCH',
                body: JSON.stringify(data),
            });
        },

        async deleteTask(id) {
            return this.request(`/tasks?id=eq.${id}`, {
                method: 'DELETE',
            });
        },

        async getCategories() {
            return this.request('/categories?select=*&order=name.asc');
        },

        async getFilesForTask(taskId) {
            return this.request(`/task_files?task_id=eq.${taskId}&select=*&order=created_at.asc`);
        },

        async createCategory(data) {
            return this.request('/categories', {
                method: 'POST',
                body: JSON.stringify(data),
            });
        },

        async updateCategory(id, data) {
            return this.request(`/categories?id=eq.${id}`, {
                method: 'PATCH',
                body: JSON.stringify(data),
            });
        },

        async deleteCategory(id) {
            return this.request(`/categories?id=eq.${id}`, {
                method: 'DELETE',
            });
        },

        async uploadFile(data) {
            return this.request('/task_files', {
                method: 'POST',
                body: JSON.stringify(data),
            });
        },

        async deleteFile(id) {
            return this.request(`/task_files?id=eq.${id}`, {
                method: 'DELETE',
            });
        },

        async getRecurrenceRuleForTask(taskId) {
            const results = await this.request(`/recurrence_rules?task_id=eq.${taskId}&limit=1`);
            return results[0] || null;
        },

        async createRecurrenceRule(data) {
            return this.request('/recurrence_rules', {
                method: 'POST',
                body: JSON.stringify(data),
            });
        },

        async updateRecurrenceRule(id, data) {
            return this.request(`/recurrence_rules?id=eq.${id}`, {
                method: 'PATCH',
                body: JSON.stringify(data),
            });
        },

        async deleteRecurrenceRule(id) {
            return this.request(`/recurrence_rules?id=eq.${id}`, {
                method: 'DELETE',
            });
        },
    },
};

App.Board = {
    currentFilter: 'all',
    currentSearch: '',

    async init() {
        await this.renderFilters();
        this.initSearch();
        await this.render();
    },

    initSearch() {
        let debounceTimer;
        $('#taskSearch').on('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                this.currentSearch = e.target.value.trim();
                this.render();
            }, 300);
        });
    },

    async renderFilters() {
        const categories = await App.Api.getCategories();
        const $container = $('#category-filters');
        const $mobileContainer = $('#category-filters-mobile');

        // Keep the 'All' button, remove others
        $container.find('button:not([data-filter="all"])').remove();
        $mobileContainer.find('button:not([data-filter="all"])').remove();

        categories.forEach(c => {
            const btn = `<button class="btn btn-sm btn-outline-light" data-filter="${c.id}" style="border-color: ${c.color}; color: ${c.color}">${c.name}</button>`;
            $container.append(btn);
            $mobileContainer.append(btn);
        });

        const filterHandler = (e) => {
            const $btn = $(e.target).closest('button');
            $('.btn-outline-light[data-filter]').removeClass('active');
            $btn.addClass('active');
            this.currentFilter = $btn.data('filter');
            this.render();
        };

        $container.off('click').on('click', 'button', filterHandler);
        $mobileContainer.off('click').on('click', 'button', filterHandler);
    },

    async render() {
        const tasks = await App.Api.getTasks(this.currentSearch);
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

    createParentCard(task, children, category) {
        const priorityClass = `priority-${task.priority || 'low'}`;

        const categoryBadge = category
            ? `<span class="badge" style="background-color: ${category.color}">${category.name}</span>`
            : '';

        const borderStyle = category ? `style="border-left-color: ${category.color}"` : '';

        const dueDate = task.due_date ? `<div class="small text-muted"><i class="far fa-calendar-alt"></i> ${task.due_date}</div>` : '';
        const hasActiveRule = task.recurrence_rules?.some(r => r.active);
        const recurrenceBadge = hasActiveRule ? `<span class="badge bg-secondary ms-1" title="Recurring task" style="font-size:0.65rem">🔁</span>` : '';

        const bellIcon = task.reminder_at
            ? `<button type="button"
                 class="btn btn-link btn-sm p-0 ms-1 bell-icon"
                 data-action="toggle-bell"
                 data-id="${task.id}"
                 data-muted="${task.disable_notifications}"
                 title="${task.disable_notifications ? 'Notifications muted (click to enable)' : 'Notifications enabled (click to mute)'}"
               >${task.disable_notifications ? '🔕' : '🔔'}</button>`
            : '';

        const doneCount = children.filter(c => c.task_column === 'done').length;
        const remainingCount = children.length - doneCount;
        const hasChildren = children.length > 0;

        const childrenSummary = hasChildren
            ? `<div class="small text-muted children-summary mt-2 pt-2 border-top children-toggle" data-action="toggle-children" data-parent-id="${task.id}" style="cursor:pointer">
                 <i class="fas fa-chevron-down me-1"></i>${children.length} subtask${children.length !== 1 ? 's' : ''} (${remainingCount} remaining)
               </div>`
            : '';

        const childTasksHtml = this.renderChildTasks(children);

        const $card = $(`
            <div class="card mb-3 task-card card-task ${priorityClass} shadow-sm" data-id="${task.id}" data-is-parent="true" ${borderStyle}>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-1">
                            ${categoryBadge}${bellIcon}${recurrenceBadge}
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-outline-secondary btn-sm px-2" data-action="edit" title="Edit task"><i class="fas fa-pen"></i></button>
                            <button class="btn btn-outline-danger btn-sm px-2" data-action="delete" title="Delete task"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <h6 class="card-title mb-1">${task.title}</h6>
                    <p class="card-text small text-muted mb-2">${task.description ? task.description.substring(0, 60) + '...' : ''}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        ${dueDate}
                        <span class="badge bg-light text-dark border">${task.priority}</span>
                    </div>
                    <div class="small text-primary mt-2 add-subtask" data-action="add-subtask" data-parent-id="${task.id}" style="cursor:pointer"><i class="fas fa-plus me-1"></i>Add subtask</div>
                    ${childrenSummary}
                    <div class="child-tasks-list d-none mt-2 ps-3 border-start border-2" style="border-color: #dee2e6 !important;">
                        ${childTasksHtml}
                    </div>
                </div>
            </div>
        `);

        return $card;
    },

    renderChildTasks(children) {
        if (!children.length) return '';
        return children.map(child => {
            const isDone = child.task_column === 'done';
            const completedClass = isDone ? 'text-muted text-decoration-line-through' : '';
            return `
            <div class="d-flex align-items-center gap-2 py-1 child-task" data-id="${child.id}" data-task-column="${child.task_column}" data-action="edit-child">
                <span class="${completedClass}">${isDone ? '●' : '○'}</span>
                <span class="${completedClass} flex-grow-1">${child.title}</span>
            </div>`;
        }).join('');
    },

    toggleChildren(parentId) {
        const card = document.querySelector(`.task-card[data-id="${parentId}"]`);
        if (!card) return;
        const childList = card.querySelector('.child-tasks-list');
        const summary = card.querySelector('.children-summary i');
        if (!childList || !summary) return;

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

    toggleChildComplete(childId, currentColumn) {
        const newColumn = currentColumn === 'done' ? 'new' : 'done';
        App.Api.updateTask(childId, { task_column: newColumn })
            .then(() => App.Board.render())
            .catch(() => App.Alerts.Toast.fire({ icon: 'error', title: 'Failed to update subtask' }));
    },

    createTaskCard(task, category) {
        const priorityClass = `priority-${task.priority || 'low'}`;

        const categoryBadge = category
            ? `<span class="badge" style="background-color: ${category.color}">${category.name}</span>`
            : '';

        const borderStyle = category ? `style="border-left-color: ${category.color}"` : '';

        const dueDate = task.due_date ? `<div class="small text-muted"><i class="far fa-calendar-alt"></i> ${task.due_date}</div>` : '';
        const hasActiveRule = task.recurrence_rules?.some(r => r.active);
        const recurrenceBadge = hasActiveRule ? `<span class="badge bg-secondary ms-1" title="Recurring task" style="font-size:0.65rem">🔁</span>` : '';

        const bellIcon = task.reminder_at
            ? `<button type="button"
                 class="btn btn-link btn-sm p-0 ms-1 bell-icon"
                 data-action="toggle-bell"
                 data-id="${task.id}"
                 data-muted="${task.disable_notifications}"
                 title="${task.disable_notifications ? 'Notifications muted (click to enable)' : 'Notifications enabled (click to mute)'}"
               >${task.disable_notifications ? '🔕' : '🔔'}</button>`
            : '';

        return $(`
            <div class="card mb-3 task-card card-task ${priorityClass} shadow-sm" data-id="${task.id}" ${borderStyle}>
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="d-flex align-items-center gap-1">
                            ${categoryBadge}
                            ${bellIcon}
                            ${recurrenceBadge}
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-outline-secondary btn-sm px-2" data-action="edit" title="Edit task"><i class="fas fa-pen"></i></button>
                            <button class="btn btn-outline-danger btn-sm px-2" data-action="delete" title="Delete task"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <h6 class="card-title mb-1">${task.title}</h6>
                    <p class="card-text small text-muted mb-2">${task.description ? task.description.substring(0, 60) + '...' : ''}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        ${dueDate}
                        <span class="badge bg-light text-dark border">${task.priority}</span>
                    </div>
                </div>
            </div>
        `);
    },

    updateCounts() {
        $('.kanban-column').each(function() {
            const count = $(this).find('.task-card').length;
            $(this).find('.task-count').text(count);
        });
    }
};

App.Modal = {
    Task: {
        async open(taskId = null) {
            const form = $('#taskForm')[0];
            form.reset();
            $('#summernote').summernote('code', '');

            // Populate categories
            const categories = await App.Api.getCategories();
            const $select = $('#categorySelect').empty();
            $select.append('<option value="">None</option>');
            categories.forEach(c => $select.append(`<option value="${c.id}">${c.name}</option>`));

            // Populate parent task dropdown (exclude children and self)
            const allTasks = await App.Api.getTasks();
            const parentTasks = allTasks.filter(t => !t.parent_id && t.id !== taskId);
            const $parentSelect = $('#parentTaskSelect').empty();
            $parentSelect.append('<option value="">— none —</option>');
            parentTasks.forEach(t => $parentSelect.append(`<option value="${t.id}">${t.title}</option>`));

            // Reset recurrence section
            App.Recurrence._currentRuleId = null;
            $('#repeatTask').prop('checked', false);
            $('#recurrenceFields').hide();
            $('#recurrencePattern').val('weekly');
            $('#recurrenceInterval').val(1);
            $('#intervalLabel').text('week(s)');
            $('#weekdaySelector').show();
            $('.weekday-btn').removeClass('active btn-secondary').addClass('btn-outline-secondary');
            $('#endNever').prop('checked', true);
            $('#recurrenceEndDate').hide().val('');
            $('#recurrencePreview').hide().text('');

            // Reset file attachments
            $('#fileList').empty();
            $('#taskFilesInput').val('');

            if (taskId) {
                const task = await App.Api.request(`/tasks?id=eq.${taskId}&select=*`);
                const data = task[0];

                $('#taskModalTitle').text('Edit Task');
                $(form).find('[name="id"]').val(data.id);
                $(form).find('[name="title"]').val(data.title);
                $('#summernote').summernote('code', data.description || '');
                $(form).find('[name="priority"]').val(data.priority);
                $(form).find('[name="category_id"]').val(data.category_id);
                $(form).find('[name="due_date"]').val(data.due_date);
                $(form).find('[name="task_column"]').val(data.task_column);
                $(form).find('[name="reminder_at"]').val(data.reminder_at);
                $(form).find('[name="pushover_priority"]').val(data.pushover_priority);
                $(form).find('[name="parent_id"]').val(data.parent_id || '');

                // Load recurrence rule if present
                const rule = await App.Api.getRecurrenceRuleForTask(taskId);
                if (rule && rule.active) {
                    App.Recurrence._currentRuleId = rule.id;
                    App.Recurrence._loadRule(rule);
                }

                // Load existing files for this task
                const files = await App.Api.getFilesForTask(taskId);
                this._renderFileList(files);
            } else {
                $('#taskModalTitle').text('Add Task');
                $(form).find('[name="id"]').val('');
            }

            // Reset subtask state
            $('#task-parent-id').val('');
            $('#taskColumnGroup, #categorySelect, #dueDateGroup, #priorityGroup, #parentTaskGroup').show();
            $('#categorySelect').removeClass('is-invalid');

            new bootstrap.Modal(document.getElementById('taskModal')).show();
        },

        async openSubtask(parentId) {
            const form = $('#taskForm')[0];

            // Set parent-id BEFORE reset so it survives the reset
            $('#task-parent-id').val(parentId);

            form.reset();
            $('#summernote').summernote('code', '');

            // Populate categories
            const categories = await App.Api.getCategories();
            const $select = $('#categorySelect').empty();
            $select.append('<option value="">None</option>');
            categories.forEach(c => $select.append(`<option value="${c.id}">${c.name}</option>`));

            // Reset recurrence section
            App.Recurrence._currentRuleId = null;
            $('#repeatTask').prop('checked', false);
            $('#recurrenceFields').hide();
            $('#recurrencePattern').val('weekly');
            $('#recurrenceInterval').val(1);
            $('#intervalLabel').text('week(s)');
            $('#weekdaySelector').show();
            $('.weekday-btn').removeClass('active btn-secondary').addClass('btn-outline-secondary');
            $('#endNever').prop('checked', true);
            $('#recurrenceEndDate').hide().val('');
            $('#recurrencePreview').hide().text('');

            // Pre-fill parent info
            $('#taskModalTitle').text('Add Subtask');
            $(form).find('[name="id"]').val('');
            $('#task-parent-id').val(parentId);

            // Inherit category from parent task
            const parentTask = await App.Api.request(`/tasks?id=eq.${parentId}&select=category_id`);
            if (parentTask[0]?.category_id) {
                $(form).find('[name="category_id"]').val(parentTask[0].category_id);
            }

            // Hide parent-inherited fields for subtask
            $('#taskColumnGroup, #categorySelect, #dueDateGroup, #priorityGroup, #parentTaskGroup').hide();

            new bootstrap.Modal(document.getElementById('taskModal')).show();
        },

        async save() {
            const formData = new FormData($('#taskForm')[0]);
            const raw = Object.fromEntries(formData.entries());

            // Skip category requirement for subtasks
            if (!raw.category_id && !$('#task-parent-id').val()) {
                $('#categorySelect').addClass('is-invalid').focus();
                return;
            }
            $('#categorySelect').removeClass('is-invalid');

            // Build payload with only valid tasks columns (excludes Summernote-injected file inputs)
            const allowed = ['id', 'title', 'description', 'priority', 'category_id', 'due_date', 'task_column', 'reminder_at', 'pushover_priority', 'parent_id'];
            const data = {};
            allowed.forEach(k => { if (raw[k] !== undefined) data[k] = raw[k]; });

            // Summernote content
            data.description = $('#summernote').summernote('code');

            // Cast numeric field
            if (data.pushover_priority !== undefined) data.pushover_priority = parseInt(data.pushover_priority, 10) || 0;

            // Handle empty values
            if (!data.id) delete data.id;
            if (!data.category_id) delete data.category_id;
            if (!data.due_date) delete data.due_date;
            if (!data.reminder_at) delete data.reminder_at;
            if (!data.parent_id) delete data.parent_id;

            // Handle subtask parent_id
            const parentId = $('#task-parent-id').val() || $('#parentTaskSelect').val();
            if (parentId) {
                data.parent_id = parentId;
                // Subtasks always start in 'new' column
                data.task_column = data.task_column || 'new';
                // Enforce parent's category_id (inherited and read-only)
                const parentTask = await App.Api.request(`/tasks?id=eq.${parentId}&select=category_id`);
                if (parentTask[0]?.category_id) {
                    data.category_id = parentTask[0].category_id;
                } else {
                    delete data.category_id;
                }
                // Skip category requirement for subtasks
            }

            try {
                let savedTaskId = data.id;

                if (data.id) {
                    await App.Api.updateTask(data.id, data);
                } else {
                    const created = await App.Api.createTask(data);
                    savedTaskId = created[0]?.id;
                }

                // Handle recurrence rule
                const repeatEnabled = $('#repeatTask').is(':checked');
                const existingRuleId = App.Recurrence._currentRuleId;

                if (repeatEnabled && savedTaskId) {
                    const rruleJson = App.Recurrence.buildRRule({
                        ...App.Recurrence._getFormData(),
                        reminder_at: data.reminder_at,
                    });

                    if (existingRuleId) {
                        await App.Api.updateRecurrenceRule(existingRuleId, {
                            rrule:  rruleJson,
                            active: true,
                        });
                    } else {
                        const dtstart = data.reminder_at
                            ? new Date(data.reminder_at).toISOString()
                            : new Date().toISOString();

                        await App.Api.createRecurrenceRule({
                            task_id:            savedTaskId,
                            rrule:              rruleJson,
                            next_occurrence_at: dtstart,
                            active:             true,
                        });
                    }
                } else if (!repeatEnabled && existingRuleId) {
                    await App.Api.deleteRecurrenceRule(existingRuleId);
                }

                // Upload pending files
                await this._uploadPendingFiles(savedTaskId);

                App.Alerts.Toast.fire({ icon: 'success', title: 'Task saved' });
                bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
                await App.Board.render();
            } catch (e) {
                App.Alerts.Toast.fire({ icon: 'error', title: e.message });
            }
        },

        _renderFileList(files) {
            const $list = $('#fileList').empty();
            files.forEach(file => {
                const isImage = file.mime_type && file.mime_type.startsWith('image/');
                const icon = isImage
                    ? `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M6.002 5.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0z"/><path d="M2.002 1a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V3a2 2 0 0 0-2-2h-12zm12 1a1 1 0 0 1 1 1v6.5l-3.777-1.947a.5.5 0 0 0-.577.093l-3.71 3.71-2.66-1.772a.5.5 0 0 0-.63.062L1.002 12V3a1 1 0 0 1 1-1h12z"/></svg>`
                    : `<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M4 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2H4zm0 1h8a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1z"/></svg>`;
                $list.append(`
                    <div class="d-flex align-items-center gap-2 px-2 py-1 bg-dark border border-secondary rounded" data-id="${file.id}">
                        <span class="text-info">${icon}</span>
                        <span class="small text-light text-truncate" style="max-width:150px">${file.filename}</span>
                        <button type="button" class="btn btn-sm p-0 text-danger" data-action="delete-file" data-id="${file.id}" title="Remove">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708z"/></svg>
                        </button>
                    </div>
                `);
            });

            // Wire up delete buttons
            $list.find('[data-action="delete-file"]').on('click', async (e) => {
                const fileId = $(e.currentTarget).data('id');
                try {
                    await App.Api.deleteFile(fileId);
                    $(e.currentTarget).closest('[data-id]').remove();
                } catch (err) {
                    App.Alerts.Toast.fire({ icon: 'error', title: 'Failed to delete file' });
                }
            });
        },

        async _uploadPendingFiles(taskId) {
            const input = document.getElementById('taskFilesInput');
            if (!input.files || input.files.length === 0) return;

            for (const file of input.files) {
                const reader = new FileReader();
                const data = await new Promise((resolve, reject) => {
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(file);
                });

                await App.Api.uploadFile({
                    task_id:   taskId,
                    filename:  file.name,
                    mime_type: file.type || 'application/octet-stream',
                    data:      data,
                });
            }

            input.value = '';
        },
    },
    Category: {
        async open() {
            const categories = await App.Api.getCategories();
            const $list = $('#categoryList').empty();

            categories.forEach(c => {
                $list.append(`
                    <div class="d-flex align-items-center justify-content-between mb-2 p-2 bg-dark border border-secondary rounded">
                        <div class="d-flex align-items-center">
                            <input type="color" class="form-control-color me-2" value="${c.color}" data-id="${c.id}" data-action="update-color">
                            <span class="cat-name">${c.name}</span>
                        </div>
                        <button class="btn btn-sm btn-outline-danger" data-id="${c.id}" data-action="delete-cat">Delete</button>
                    </div>
                `);
            });

            new bootstrap.Modal(document.getElementById('categoryModal')).show();
        },

        async saveNew() {
            const name = $('#newCatName').val();
            const color = $('#newCatColor').val();
            if (!name) return;

            try {
                await App.Api.createCategory({ name, color });
                App.Alerts.Toast.fire({ icon: 'success', title: 'Category added' });
                $('#newCatName').val('');
                await this.open(); // Refresh list
            } catch (e) {
                App.Alerts.Toast.fire({ icon: 'error', title: e.message });
            }
        }
    }
};

App.DnD = {
    init() {
        $('.task-list').sortable({
            connectWith: '.task-list',
            placeholder: 'ui-state-highlight',
            delay: 150,
            tolerance: 'pointer',
            update: async (event, ui) => {
                const card = ui.item;
                const newColumn = card.closest('.kanban-column').data('column');
                const taskId = card.data('id');

                // Get new position based on index
                const position = card.index();

                try {
                    await App.Api.updateTask(taskId, {
                        task_column: newColumn,
                        position: position
                    });
                    App.Board.updateCounts();
                } catch (e) {
                    App.Alerts.Toast.fire({ icon: 'error', title: 'Failed to update task position' });
                    App.Board.render(); // Revert UI
                }
            }
        }).disableSelection();
    }
};

App.Alerts = {
    Toast: Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        theme: 'dark',
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    }),
    Confirm: Swal.mixin({
        theme: 'dark',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#252d3d'
    })
};

App.Recurrence = {
    _currentRuleId: null,

    buildRRule(formData) {
        const freqMap = {
            daily:           'DAILY',
            every_other_day: 'DAILY',
            weekly:          'WEEKLY',
            monthly:         'MONTHLY',
            yearly:          'YEARLY',
        };

        const dtstart = formData.reminder_at
            ? new Date(formData.reminder_at).toISOString().replace(/[-:.]/g, '').slice(0, 15) + 'Z'
            : new Date().toISOString().replace(/[-:.]/g, '').slice(0, 15) + 'Z';

        const rrule = {
            FREQ:    freqMap[formData.pattern],
            DTSTART: dtstart,
        };

        if (formData.pattern === 'every_other_day') {
            rrule.INTERVAL = 2;
        } else if (formData.interval > 1) {
            rrule.INTERVAL = formData.interval;
        }

        if (formData.pattern === 'weekly' && formData.weekDays.length > 0) {
            rrule.BYDAY = formData.weekDays.join(',');
        }

        if (formData.endType === 'on_date' && formData.endDate) {
            rrule.UNTIL = new Date(formData.endDate).toISOString().replace(/[-:.]/g, '').slice(0, 15) + 'Z';
        }

        return JSON.stringify(rrule);
    },

    humanReadable(rruleJson) {
        try {
            const r = JSON.parse(rruleJson);
            const interval = r.INTERVAL || 1;
            const freqLabel = { DAILY: 'day', WEEKLY: 'week', MONTHLY: 'month', YEARLY: 'year' };
            const unit = freqLabel[r.FREQ] || r.FREQ.toLowerCase();
            const days = r.BYDAY ? ` on ${r.BYDAY}` : '';
            const until = r.UNTIL ? ` until ${r.UNTIL.slice(0, 8)}` : '';
            return `Every ${interval > 1 ? interval + ' ' : ''}${unit}${interval > 1 ? 's' : ''}${days}${until}`;
        } catch {
            return 'Custom recurrence';
        }
    },

    async toggleNotifications(taskId, currentMuted) {
        const newMuted = !currentMuted;
        const result = await App.Alerts.Confirm.fire({
            title: newMuted ? 'Mute notifications for this task?' : 'Enable notifications for this task?',
            icon:  'question',
        });
        if (!result.isConfirmed) return;

        try {
            await App.Api.request(`/tasks?id=eq.${taskId}`, {
                method: 'PATCH',
                body: JSON.stringify({ disable_notifications: newMuted }),
            });
            App.Alerts.Toast.fire({
                icon:  'success',
                title: newMuted ? 'Notifications muted' : 'Notifications enabled',
            });
            this.updateBellIcon(taskId, newMuted);
        } catch {
            App.Alerts.Toast.fire({ icon: 'error', title: 'Failed to update notifications' });
        }
    },

    updateBellIcon(taskId, muted) {
        const btn = document.querySelector(`.bell-icon[data-id="${taskId}"]`);
        if (!btn) return;
        btn.textContent = muted ? '🔕' : '🔔';
        btn.dataset.muted = String(muted);
        btn.title = muted ? 'Notifications muted (click to enable)' : 'Notifications enabled (click to mute)';
    },

    _loadRule(rule) {
        const r = JSON.parse(rule.rrule);
        const patternMap = {
            DAILY: r.INTERVAL === 2 ? 'every_other_day' : 'daily',
            WEEKLY: 'weekly',
            MONTHLY: 'monthly',
            YEARLY: 'yearly',
        };
        const pattern = patternMap[r.FREQ] || 'weekly';

        $('#repeatTask').prop('checked', true);
        $('#recurrenceFields').show();
        $('#recurrencePattern').val(pattern);
        $('#recurrenceInterval').val(r.INTERVAL && r.INTERVAL !== 2 ? r.INTERVAL : 1);

        if (r.BYDAY) {
            r.BYDAY.split(',').forEach(day => {
                $(`.weekday-btn[data-day="${day}"]`).removeClass('btn-outline-secondary').addClass('btn-secondary active');
            });
        }

        if (r.UNTIL) {
            $('#endOnDate').prop('checked', true);
            $('#recurrenceEndDate').show().val(r.UNTIL.slice(0, 8));
        }

        this._updatePreview();
    },

    _getFormData() {
        return {
            pattern:     $('#recurrencePattern').val(),
            interval:    parseInt($('#recurrenceInterval').val(), 10) || 1,
            weekDays:    $('.weekday-btn.active').map(function() { return $(this).data('day'); }).get(),
            endType:     $('input[name="recurrenceEnd"]:checked').val(),
            endDate:     $('#recurrenceEndDate').val(),
            reminder_at: $('[name="reminder_at"]').val(),
        };
    },

    _updatePreview() {
        const rruleJson = this.buildRRule(this._getFormData());
        const readable  = this.humanReadable(rruleJson);
        $('#recurrencePreview').text(readable).show();
    },

    initModalBindings() {
        $('#repeatTask').on('change', function() {
            $('#recurrenceFields').toggle(this.checked);
            if (this.checked) App.Recurrence._updatePreview();
        });

        $('#recurrencePattern').on('change', function() {
            const isWeekly = $(this).val() === 'weekly';
            $('#weekdaySelector').toggle(isWeekly);
            const labels = {
                daily: 'day(s)', every_other_day: 'day(s)',
                weekly: 'week(s)', monthly: 'month(s)', yearly: 'year(s)',
            };
            $('#intervalLabel').text(labels[$(this).val()] || 'unit(s)');
            App.Recurrence._updatePreview();
        });

        $('#recurrenceInterval').on('input', () => App.Recurrence._updatePreview());

        $(document).on('click', '.weekday-btn', function() {
            $(this).toggleClass('btn-outline-secondary btn-secondary active');
            App.Recurrence._updatePreview();
        });

        $('input[name="recurrenceEnd"]').on('change', function() {
            $('#recurrenceEndDate').toggle($(this).val() === 'on_date');
            App.Recurrence._updatePreview();
        });

        $('#recurrenceEndDate').on('change', () => App.Recurrence._updatePreview());
    },
};

// Global error handlers
window.addEventListener('error', (e) => {
    if (e.message && !e.message.startsWith('ResizeObserver') && !e.message.startsWith('Non-Error')) {
        App.Alerts?.Toast.fire({ icon: 'error', title: 'An error occurred' });
    }
});
window.addEventListener('unhandledrejection', () => {
    App.Alerts?.Toast.fire({ icon: 'error', title: 'An error occurred' });
});

$(document).ready(async () => {
    await App.Board.init();
    App.DnD.init();
    App.Recurrence.initModalBindings();
    console.log('phpKanMaster board initialized');
});

// Event handlers
$(document).on('click', '[data-action="edit"]', function(e) {
    e.preventDefault();
    const id = $(this).closest('.task-card').data('id');
    App.Modal.Task.open(id);
});

$(document).on('click', '[data-action="delete"]', async function(e) {
    e.preventDefault();
    const id = $(this).closest('.task-card').data('id');

    const result = await App.Alerts.Confirm.fire({
        title: 'Delete Task?',
        text: 'This action cannot be undone!'
    });

    if (result.isConfirmed) {
        try {
            await App.Api.deleteTask(id);
            App.Alerts.Toast.fire({ icon: 'success', title: 'Task deleted' });
            await App.Board.render();
        } catch (e) {
            App.Alerts.Toast.fire({ icon: 'error', title: 'Delete failed' });
        }
    }
});

$(document).on('click', '[data-action="toggle-bell"]', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const taskId = $(this).data('id');
    const muted  = $(this).data('muted') === true || $(this).data('muted') === 'true';
    App.Recurrence.toggleNotifications(taskId, muted);
});

$(document).on('click', '[data-action="toggle-children"]', function(e) {
    e.stopPropagation();
    const parentId = $(this).data('parent-id');
    App.Board.toggleChildren(parentId);
});

$(document).on('click', '[data-action="edit-child"]', function(e) {
    e.stopPropagation();
    const childId = $(this).data('id');
    App.Modal.Task.open(childId);
});

$(document).on('click', '[data-action="add-subtask"]', function(e) {
    e.preventDefault();
    e.stopPropagation();
    const parentId = $(this).data('parent-id');
    App.Modal.Task.openSubtask(parentId);
});

$('#saveTaskBtn').on('click', () => App.Modal.Task.save());

// Category event handlers
$('#addCategoryBtn').on('click', () => App.Modal.Category.saveNew());

$(document).on('click', '[data-action="delete-cat"]', async function() {
    const id = $(this).data('id');
    const result = await App.Alerts.Confirm.fire({ title: 'Delete Category?', text: 'Tasks in this category will be set to "None".' });
    if (result.isConfirmed) {
        try {
            await App.Api.deleteCategory(id);
            App.Alerts.Toast.fire({ icon: 'success', title: 'Category deleted' });
            await App.Modal.Category.open();
        } catch (e) {
            App.Alerts.Toast.fire({ icon: 'error', title: 'Delete failed' });
        }
    }
});

$(document).on('input', '[data-action="update-color"]', async function() {
    const id = $(this).data('id');
    const color = $(this).val();
    const name = $(this).siblings('.cat-name').text();
    try {
        await App.Api.updateCategory(id, { color });
    } catch (e) {
        console.error('Color update failed', e);
    }
});

// Initialize Summernote and Flatpickr
$(document).ready(() => {
    $('#summernote').summernote({
        placeholder: 'Task description...',
        height: 200,
        toolbar: [
            ['style', ['style']],
            ['font', ['bold', 'italic', 'underline', 'clear']],
            ['para', ['ul', 'ol', 'paragraph']],
            ['insert', ['link', 'picture', 'video', 'table', 'hr']],
            ['view', ['codeview']]
        ],
        callbacks: {
            onImageUpload: function(files) {
                // Convert image to base64 and insert
                for (let i = 0; i < files.length; i++) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        $('#summernote').summernote('insertImage', e.target.result);
                    };
                    reader.readAsDataURL(files[i]);
                }
            }
        }
    });

    $('.datepicker').flatpickr({
        enableTime: true,
        dateFormat: 'Y-m-d H:i',
        time_24hr: false,
        disableMobile: 'false',
        onReady: function(_selectedDates, _dateStr, instance) {
            const closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'flatpickr-close-btn';
            closeBtn.textContent = '×';
            closeBtn.title = 'Close';
            closeBtn.onclick = function() {
                instance.close();
            };
            instance.calendarContainer.appendChild(closeBtn);
        }
    });
});

console.log('phpKanMaster initialized');
