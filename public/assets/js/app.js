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
            return this.request('/tasks?select=*&order=task_column.asc,position.asc');
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
        const priorityClass = {
            'high': 'border-danger',
            'medium': 'border-warning',
            'low': 'border-info'
        }[task.priority] || 'border-secondary';

        const categoryBadge = category
            ? `<span class="badge" style="background-color: ${category.color}">${category.name}</span>`
            : '';

        const dueDate = task.due_date ? `<div class="small text-muted"><i class="far fa-calendar-alt"></i> ${task.due_date}</div>` : '';

        return $(`
            <div class="card mb-3 task-card ${priorityClass} border-start border-4 shadow-sm" data-id="${task.id}">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        ${categoryBadge}
                        <div class="dropdown">
                            <button class="btn btn-link btn-sm p-0 text-muted" data-bs-toggle="dropdown">...</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="#" data-action="edit">${task.title}</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item text-danger" href="#" data-action="delete">Delete</a></li>
                            </ul>
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
            } else {
                $('#taskModalTitle').text('Add Task');
                $(form).find('[name="id"]').val('');
            }

            new bootstrap.Modal(document.getElementById('taskModal')).show();
        },

        async save() {
            const formData = new FormData($('#taskForm')[0]);
            const data = Object.fromEntries(formData.entries());

            // Summernote content
            data.description = $('#summernote').summernote('code');

            // Handle empty values
            if (!data.category_id) delete data.category_id;
            if (!data.due_date) delete data.due_date;
            if (!data.reminder_at) delete data.reminder_at;

            try {
                if (data.id) {
                    await App.Api.updateTask(data.id, data);
                } else {
                    await App.Api.createTask(data);
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
            handle: '.task-card',
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

$(document).ready(async () => {
    await App.Board.init();
    App.DnD.init();
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
            ['color', ['color']],
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
        time_24hr: true,
        disableMobile: 'false'
    });
});

console.log('phpKanMaster initialized');
