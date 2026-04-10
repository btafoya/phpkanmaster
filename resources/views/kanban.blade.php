<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>phpKanMaster</title>

    {{-- PWA Meta Tags --}}
    <meta name="theme-color" content="#212529">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="phpKanMaster">
    <meta name="application-name" content="phpKanMaster">
    <meta name="description" content="Personal Kanban task manager">

    {{-- Favicons --}}
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="icon" type="image/png" sizes="16x16 32x32" href="/favicon.ico">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    <link rel="manifest" href="/manifest.json">

    {{-- PWA Icons --}}
    <link rel="icon" type="image/png" sizes="72x72" href="/icons/icon-72.png">
    <link rel="icon" type="image/png" sizes="96x96" href="/icons/icon-96.png">
    <link rel="icon" type="image/png" sizes="128x128" href="/icons/icon-128.png">
    <link rel="icon" type="image/png" sizes="144x144" href="/icons/icon-144.png">
    <link rel="icon" type="image/png" sizes="152x152" href="/icons/icon-152.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png">
    <link rel="icon" type="image/png" sizes="384x384" href="/icons/icon-384.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/icons/icon-512.png">
    <link rel="apple-touch-icon" sizes="72x72" href="/icons/icon-72.png">
    <link rel="apple-touch-icon" sizes="96x96" href="/icons/icon-96.png">
    <link rel="apple-touch-icon" sizes="128x128" href="/icons/icon-128.png">
    <link rel="apple-touch-icon" sizes="144x144" href="/icons/icon-144.png">
    <link rel="apple-touch-icon" sizes="152x152" href="/icons/icon-152.png">
    <link rel="apple-touch-icon" sizes="192x192" href="/icons/icon-192.png">
    <link rel="apple-touch-icon" sizes="384x384" href="/icons/icon-384.png">
    <link rel="apple-touch-icon" sizes="512x512" href="/icons/icon-512.png">

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
    <link href="/assets/css/app.css?v={{ filemtime(public_path('assets/css/app.css')) }}" rel="stylesheet">
</head>

<body>
    {{-- Top Bar --}}
    <nav class="navbar navbar-dark bg-dark px-3 py-1" style="min-height:0">
        <div class="container-fluid p-0">
            <div class="d-flex align-items-center w-100">
                <span class="navbar-brand mb-0 h6 me-auto flex-shrink-0">
                    <a href="/" class="d-flex align-items-center gap-2 text-decoration-none" aria-label="Home" alt="Home">
                        <svg class="img-fluid d-block" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"
                            fill="none" style="height: 45px;">

                            <!-- Transparent background -->
                            <rect width="512" height="512" fill="none" />

                            <!-- Board container -->
                            <rect x="64" y="96" width="384" height="320" rx="24" stroke="#FFFFFF" stroke-width="16" />

                            <!-- Columns (Kanban lanes) -->
                            <rect x="104" y="136" width="80" height="240" rx="12" fill="#FFFFFF" opacity="0.15" />
                            <rect x="216" y="136" width="80" height="240" rx="12" fill="#FFFFFF" opacity="0.35" />
                            <rect x="328" y="136" width="80" height="240" rx="12" fill="#FFFFFF" opacity="0.7" />

                            <!-- Task cards -->
                            <rect x="116" y="160" width="56" height="28" rx="6" fill="#FFFFFF" />
                            <rect x="116" y="200" width="56" height="28" rx="6" fill="#FFFFFF" />

                            <rect x="228" y="160" width="56" height="28" rx="6" fill="#FFFFFF" />
                            <rect x="228" y="200" width="56" height="28" rx="6" fill="#FFFFFF" />
                            <rect x="228" y="240" width="56" height="28" rx="6" fill="#FFFFFF" />

                            <rect x="340" y="160" width="56" height="28" rx="6" fill="#FFFFFF" />
                            <rect x="340" y="200" width="56" height="28" rx="6" fill="#FFFFFF" />

                            <!-- Checkmark (completion indicator) -->
                            <path d="M348 300 L370 322 L408 278" stroke="#FFFFFF" stroke-width="12"
                                stroke-linecap="round" stroke-linejoin="round" />

                        </svg>
                    </a>
                </span>
                <div id="category-filters"
                    class="d-none d-sm-flex align-items-center justify-content-center gap-1 flex-grow-1 overflow-x-auto">
                    <button class="btn btn-sm btn-outline-light active flex-shrink-0" data-filter="all">All</button>
                    <!-- Category pills injected here -->
                </div>
                <div class="d-none d-sm-flex align-items-center flex-shrink-0">
                    <input type="text" id="taskSearch" class="form-control form-control-sm bg-dark text-light border-secondary"
                        placeholder="Search tasks..." style="width: 160px;">
                </div>
                <div class="d-none d-sm-flex align-items-center gap-2 flex-shrink-0">
                    <button class="btn btn-primary btn-sm" onclick="App.Modal.Task.open()">+ Add Task</button>
                    <button class="btn btn-outline-light btn-sm" onclick="App.Modal.Category.open()">Categories</button>
                    <a href="/logout" class="btn btn-outline-secondary btn-sm">Logout</a>
                </div>
                <button class="navbar-toggler border-0 p-1 ms-2 d-sm-none" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarActions" aria-controls="navbarActions" aria-expanded="false"
                    aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            <div class="collapse navbar-collapse mt-2 d-sm-none" id="navbarActions">
                <div id="category-filters-mobile" class="d-flex flex-wrap gap-1 mb-2 w-100 justify-content-center">
                    <button class="btn btn-sm btn-outline-light active" data-filter="all">All</button>
                    <!-- Category pills injected here -->
                </div>
                <div class="d-flex flex-column align-items-stretch gap-2 w-100">
                    <button class="btn btn-primary btn-sm" onclick="App.Modal.Task.open()">+ Add Task</button>
                    <button class="btn btn-outline-light btn-sm" onclick="App.Modal.Category.open()">Categories</button>
                    <a href="/logout" class="btn btn-outline-secondary btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    {{-- Main Board --}}
    <main class="container-fluid">
        <div id="kanban-board" class="d-flex gap-3 overflow-x-auto py-2">
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
        <div class="modal-dialog modal-fullscreen-sm-down modal-lg">
            <div class="modal-content bg-dark text-light border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title" id="taskModalTitle">Add Task</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="taskForm">
                        <input type="hidden" name="id">
                        <input type="hidden" id="task-parent-id" name="parent_id" value="">
                        <div class="mb-3">
                            <label class="form-label">Title</label>
                            <input type="text" name="title" class="form-control bg-dark text-light border-secondary"
                                required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <div name="description" id="summernote"></div>
                        </div>
                        <div class="mb-3" id="taskNotesSection">
                            <div class="d-flex align-items-center mb-2">
                                <label class="form-label mb-0">Notes</label>
                                <button type="button" class="btn btn-sm p-0 ms-auto text-info" id="addNoteBtn" title="Add note">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/></svg>
                                    Add note
                                </button>
                            </div>
                            <div id="notesList" class="mb-2 d-flex flex-column gap-2" style="max-height:200px;overflow-y:auto;"></div>
                        </div>
                        <div class="row" id="priorityGroup">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select bg-dark text-light border-secondary">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                </select>
                            </div>
                        </div>
                        <div class="row" id="categoryGroup">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" id="categorySelect"
                                    class="form-select bg-dark text-light border-secondary" required>
                                    <option value="" disabled selected>— select category —</option>
                                </select>
                            </div>
                        </div>
                        <div class="row" id="dueDateGroup">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Due Date</label>
                                <input type="text" name="due_date"
                                    class="form-control bg-dark text-light border-secondary datepicker">
                            </div>
                        </div>
                        <div class="row" id="taskColumnGroup">
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
                        <div class="row" id="parentTaskGroup">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Parent Task</label>
                                <select name="parent_id" id="parentTaskSelect"
                                    class="form-select bg-dark text-light border-secondary">
                                    <option value="">— none —</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3 p-3 bg-dark border border-secondary rounded">
                            <h6>Pushover Notifications</h6>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label class="small">Reminder At</label>
                                    <input type="text" name="reminder_at"
                                        class="form-control form-control-sm bg-dark text-light border-secondary datepicker">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label class="small">Priority (-2 to 2)</label>
                                    <input type="number" name="pushover_priority"
                                        class="form-control form-control-sm bg-dark text-light border-secondary"
                                        value="0" min="-2" max="2">
                                </div>
                            </div>
                        </div>
                        <div class="mb-3" id="fileAttachmentSection">
                            <label class="form-label">Attachments</label>
                            <div id="fileList" class="mb-2 d-flex flex-wrap gap-2"></div>
                            <input type="file" id="taskFilesInput" class="form-control form-control-sm bg-dark text-light border-secondary" multiple>
                        </div>
                        <div class="mb-3 p-3 bg-dark border border-secondary rounded">
                            <div class="d-flex align-items-center mb-2">
                                <input class="form-check-input me-2" type="checkbox" id="repeatTask">
                                <label class="form-check-label fw-semibold" for="repeatTask">&#x1F501; Repeat this
                                    task</label>
                            </div>
                            <div id="recurrenceFields" style="display:none">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="small">Repeat</label>
                                        <select id="recurrencePattern"
                                            class="form-select form-select-sm bg-dark text-light border-secondary">
                                            <option value="daily">Daily</option>
                                            <option value="every_other_day">Every other day</option>
                                            <option value="weekly" selected>Weekly</option>
                                            <option value="monthly">Monthly</option>
                                            <option value="yearly">Yearly</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="small">Every <span id="intervalLabel">week(s)</span></label>
                                        <input type="number" id="recurrenceInterval"
                                            class="form-control form-control-sm bg-dark text-light border-secondary"
                                            value="1" min="1" max="99">
                                    </div>
                                </div>
                                <div id="weekdaySelector" class="mb-2">
                                    <label class="small d-block mb-1">On these days:</label>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn"
                                            data-day="MO">M</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn"
                                            data-day="TU">T</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn"
                                            data-day="WE">W</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn"
                                            data-day="TH">T</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn"
                                            data-day="FR">F</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn"
                                            data-day="SA">S</button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary weekday-btn"
                                            data-day="SU">S</button>
                                    </div>
                                </div>
                                <div class="mb-2">
                                    <label class="small d-block mb-1">End</label>
                                    <div class="d-flex gap-3 align-items-center flex-wrap">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recurrenceEnd"
                                                id="endNever" value="never" checked>
                                            <label class="form-check-label small" for="endNever">Never</label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="recurrenceEnd"
                                                id="endOnDate" value="on_date">
                                            <label class="form-check-label small" for="endOnDate">On date</label>
                                        </div>
                                        <input type="text" id="recurrenceEndDate"
                                            class="form-control form-control-sm bg-dark text-light border-secondary datepicker"
                                            style="display:none; width:auto">
                                    </div>
                                </div>
                                <div id="recurrencePreview" class="small text-info p-2 border border-secondary rounded"
                                    style="display:none"></div>
                            </div>
                        </div>
                    </form>
                    <div id="noteEditor" class="d-none mb-3">
                        <div name="noteContent" id="noteSummernote"></div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-primary" id="saveNoteBtn">Save note</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelNoteBtn">Cancel</button>
                        </div>
                    </div>
                    {{-- Read-only view content (shown in view mode) --}}
                    <div id="taskViewContent" class="d-none">
                        <h4 id="viewTitle" class="mb-3 text-light"></h4>

                        <div class="mb-3">
                            <label class="form-label text-secondary small text-uppercase mb-1">Description</label>
                            <div id="viewDescription" class="p-2 bg-dark border rounded text-light" style="min-height:60px"></div>
                        </div>

                        <div class="mb-3" id="viewNotesSection">
                            <div class="d-flex align-items-center mb-2">
                                <label class="form-label text-secondary small text-uppercase mb-0">Notes</label>
                                <button type="button" class="btn btn-sm p-0 ms-auto text-info" id="addNoteBtnView" title="Add note">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4z"/></svg>
                                    Add note
                                </button>
                            </div>
                            <div id="viewNotesList" class="d-flex flex-column gap-2" style="max-height:200px;overflow-y:auto;"></div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small text-uppercase mb-1">Priority</label>
                                <div id="viewPriority" class="badge"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small text-uppercase mb-1">Category</label>
                                <div id="viewCategory"></div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small text-uppercase mb-1">Due Date</label>
                                <div id="viewDueDate" class="text-light"></div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-secondary small text-uppercase mb-1">Column</label>
                                <div id="viewColumn" class="badge bg-primary"></div>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label text-secondary small text-uppercase mb-1">Parent Task</label>
                                <div id="viewParentTask" class="text-light"></div>
                            </div>
                        </div>

                        <div class="mb-3 p-3 bg-dark border border-secondary rounded">
                            <label class="form-label text-secondary small text-uppercase mb-2">Notifications</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="small text-secondary d-block">Reminder At</label>
                                    <div id="viewReminderAt" class="text-light"></div>
                                </div>
                                <div class="col-md-6">
                                    <label class="small text-secondary d-block">Priority</label>
                                    <div id="viewPushoverPriority" class="text-light"></div>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3" id="viewAttachmentsSection">
                            <label class="form-label text-secondary small text-uppercase mb-2">Attachments</label>
                            <div id="viewFileList" class="d-flex flex-wrap gap-2"></div>
                        </div>

                        <div class="mb-3" id="viewRecurrenceSection">
                            <label class="form-label text-secondary small text-uppercase mb-1">Recurrence</label>
                            <div id="viewRecurrence" class="text-info"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" id="closeTaskBtn" class="btn btn-secondary" data-bs-dismiss="modal">Close Window</button>
                    <button type="button" id="editTaskBtn" class="btn btn-primary">Edit Task</button>
                    <button type="button" id="saveTaskBtn" class="btn btn-primary d-none">Save Task</button>
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
                            <input type="text" id="newCatName" class="form-control bg-dark text-light border-secondary"
                                placeholder="Category Name">
                        </div>
                        <div class="col-4">
                            <input type="color" id="newCatColor"
                                class="form-control form-control-color bg-dark border-secondary" value="#6c757d">
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

    {{-- jQuery UI Touch Punch for mobile drag support --}}
    <script src="https://cdn.jsdelivr.net/npm/jquery-ui-touch-punch@0.2.3/jquery.ui.touch-punch.min.js"></script>

    {{-- Bootstrap JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    {{-- Summernote --}}
    <script src="https://cdn.jsdelivr.net/npm/summernote@0.9.1/dist/summernote-lite.min.js"></script>

    {{-- Flatpickr --}}
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>

    {{-- SweetAlert2 --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>

    {{-- PWA Registration --}}
    <script src="/assets/js/pwa.js?v={{ filemtime(public_path('assets/js/pwa.js')) }}"></script>

    {{-- App JS --}}
    <script>
        window.POSTGREST_URL = '{{ env('PGRST_BASE_URL', '/api') }}';
    </script>
    <script src="/assets/js/app.js?v={{ filemtime(public_path('assets/js/app.js')) }}"></script>
</body>

</html>
