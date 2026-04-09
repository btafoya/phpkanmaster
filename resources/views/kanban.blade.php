<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>phpKanMaster</title>

    {{-- Bootstrap 5.3 --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    {{-- FontAwesome --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    {{-- Summernote --}}
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote-lite.min.css" rel="stylesheet">

    {{-- Flatpickr --}}
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet">

    {{-- SweetAlert2 --}}
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css" rel="stylesheet">

    {{-- Custom CSS --}}
    <link href="/assets/css/app.css" rel="stylesheet">

    <style>
        .kanban-column {
            min-width: 300px;
            max-width: 300px;
            background: #1a1d23;
            border-radius: 0.5rem;
            padding: 1rem;
            height: calc(100vh - 150px);
            display: flex;
            flex-direction: column;
        }
        .task-list {
            flex-grow: 1;
            overflow-y: auto;
            min-height: 100px;
        }
        .ui-state-highlight {
            height: 100px;
            background: rgba(255,255,255,0.1) !important;
            border: 2px dashed #444;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        .task-card {
            cursor: grab;
            transition: transform 0.1s;
        }
        .task-card:active {
            cursor: grabbing;
            transform: scale(0.98);
        }
    </style>
</head>
<body>
    {{-- Navbar --}}
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">phpKanMaster</span>
            <div class="d-flex">
                <a href="/logout" class="btn btn-outline-light btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    {{-- Category Filters --}}
    <div class="d-flex justify-content-center gap-2 mb-3" id="category-filters">
        <button class="btn btn-sm btn-outline-light active" data-filter="all">All</button>
        <!-- Category pills injected here -->
    </div>

    {{-- Action Buttons --}}
    <div class="d-flex justify-content-end gap-2 mb-3 px-3">
        <button class="btn btn-primary btn-sm" onclick="App.Modal.Task.open()">+ Add Task</button>
        <button class="btn btn-outline-light btn-sm" onclick="App.Modal.Category.open()">Categories</button>
    </div>

    {{-- Main Board --}}
    <main class="container-fluid mt-3">
        <div id="kanban-board" class="d-flex gap-3 overflow-x-auto py-4">
            <div class="kanban-column" data-column="new">
                <div class="column-header d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">New</h5>
                    <span class="badge bg-secondary task-count">0</span>
                </div>
                <div class="task-list" id="list-new"></div>
            </div>
            <div class="kanban-column" data-column="in_progress">
                <div class="column-header d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">In Progress</h5>
                    <span class="badge bg-secondary task-count">0</span>
                </div>
                <div class="task-list" id="list-in_progress"></div>
            </div>
            <div class="kanban-column" data-column="review">
                <div class="column-header d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Review</h5>
                    <span class="badge bg-secondary task-count">0</span>
                </div>
                <div class="task-list" id="list-review"></div>
            </div>
            <div class="kanban-column" data-column="on_hold">
                <div class="column-header d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">On Hold</h5>
                    <span class="badge bg-secondary task-count">0</span>
                </div>
                <div class="task-list" id="list-on_hold"></div>
            </div>
            <div class="kanban-column" data-column="done">
                <div class="column-header d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">Done</h5>
                    <span class="badge bg-secondary task-count">0</span>
                </div>
                <div class="task-list" id="list-done"></div>
            </div>
        </div>
    </main>

    {{-- Task Modal --}}
    <div class="modal fade" id="taskModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content bg-dark text-light border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="taskModalTitle">Add Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="taskForm">
                        <input type="hidden" name="id">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control bg-dark text-light border-secondary" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <div name="description" id="summernote"></div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select bg-dark text-light border-secondary">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" id="categorySelect" class="form-select bg-dark text-light border-secondary"></select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="text" name="due_date" class="form-control bg-dark text-light border-secondary datepicker">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Column</label>
                                <select name="task_column" class="form-select bg-dark text-light border-secondary">
                                    <option value="new">New</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="review">Review</option>
                                    <option value="on_hold">On Hold</option>
                                    <option value="done">Done</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3 p-3 bg-dark border border-secondary rounded">
                            <h6>Pushover Notifications</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="small">Reminder At</label>
                                    <input type="text" name="reminder_at" class="form-control form-control-sm bg-dark text-light border-secondary datepicker">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="small">Priority (-2 to 2)</label>
                                    <input type="number" name="pushover_priority" class="form-control form-control-sm bg-dark text-light border-secondary" value="0" min="-2" max="2">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="saveTaskBtn" class="btn btn-primary">Save Task</button>
                </div>
            </div>
        </div>
    </div>

    {{-- Category Modal --}}
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content bg-dark text-light border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title">Manage Categories</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="categoryList" class="mb-3"></div>
                    <hr class="border-secondary">
                    <h6>Add New Category</h6>
                    <div class="row g-2">
                        <div class="col-8">
                            <input type="text" id="newCatName" class="form-control bg-dark text-light border-secondary" placeholder="Category Name">
                        </div>
                        <div class="col-4">
                            <input type="color" id="newCatColor" class="form-control form-control-color bg-dark border-secondary" value="#6c757d">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" id="addCategoryBtn" class="btn btn-primary w-100">Add Category</button>
                </div>
            </div>
        </div>
    </div>

    {{-- jQuery 3.7.1 --}}
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://code.jquery.com/jquery-migrate-4.0.2.min.js"></script>

    {{-- jQuery UI Sortable --}}
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Summernote --}}
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote-lite.min.js"></script>

    {{-- Flatpickr --}}
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

    {{-- App JS --}}
    <script>
        window.POSTGREST_URL = '{{ env('PGRST_BASE_URL', '/api') }}';
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>
