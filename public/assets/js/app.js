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

        async getTasks() {
            return this.request('/tasks?select=*,recurrence_rules(id,active)&order=task_column.asc,position.asc');
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

    async init() {
        await this.renderFilters();
        await this.render();
    },

    async renderFilters() {
        const categories = await App.Api.getCategories();
        const $container = $('#category-filters');

        // Keep the 'All' button, remove others
        $container.find('button:not([data-filter="all"])').remove();

        categories.forEach(c => {
            $container.append(`
                <button class="btn btn-sm btn-outline-light" data-filter="${c.id}" style="border-color: ${c.color}; color: ${c.color}">
                    ${c.name}
                </button>
            `);
        });

        $container.off('click').on('click', 'button', (e) => {
            $('.btn-outline-light').removeClass('active');
            $(e.target).addClass('active');
            this.currentFilter = $(e.target).data('filter');
            this.render();
        });
    },

    async render() {
        const tasks = await App.Api.getTasks();
        const categories = await App.Api.getCategories();

        const categoryMap = Object.fromEntries(categories.map(c => [c.id, c]));

        // Clear all lists
        $('.task-list').empty();

        const filteredTasks = this.currentFilter === 'all'
            ? tasks
            : tasks.filter(t => t.category_id === this.currentFilter);

        filteredTasks.forEach(task => {
            const category = categoryMap[task.category_id];
            const card = this.createTaskCard(task, category);
            $(`#list-${task.task_column}`).append(card);
        });

        this.updateCounts();
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

                // Load recurrence rule if present
                const rule = await App.Api.getRecurrenceRuleForTask(taskId);
                if (rule && rule.active) {
                    App.Recurrence._currentRuleId = rule.id;
                    App.Recurrence._loadRule(rule);
                }
            } else {
                $('#taskModalTitle').text('Add Task');
                $(form).find('[name="id"]').val('');
            }

            new bootstrap.Modal(document.getElementById('taskModal')).show();
        },

        async save() {
            const formData = new FormData($('#taskForm')[0]);
            const raw = Object.fromEntries(formData.entries());

            if (!raw.category_id) {
                $('#categorySelect').addClass('is-invalid').focus();
                return;
            }
            $('#categorySelect').removeClass('is-invalid');

            // Build payload with only valid tasks columns (excludes Summernote-injected file inputs)
            const allowed = ['id', 'title', 'description', 'priority', 'category_id', 'due_date', 'task_column', 'reminder_at', 'pushover_priority'];
            const data = {};
            allowed.forEach(k => { if (raw[k] !== undefined) data[k] = raw[k]; });

            // Summernote content
            data.description = $('#summernote').summernote('code');

            // Cast numeric field
            if (data.pushover_priority !== undefined) data.pushover_priority = parseInt(data.pushover_priority, 10) || 0;

            // Handle empty values
            if (!data.id) delete data.id;
            if (!data.due_date) delete data.due_date;
            if (!data.reminder_at) delete data.reminder_at;

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

                App.Alerts.Toast.fire({ icon: 'success', title: 'Task saved' });
                bootstrap.Modal.getInstance(document.getElementById('taskModal')).hide();
                await App.Board.render();
            } catch (e) {
                App.Alerts.Toast.fire({ icon: 'error', title: e.message });
            }
        }
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
