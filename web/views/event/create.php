<?php
// Route: /event/create
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Create Event - Team Competition';

$eventController = new \App\Controllers\EventController();

$error = '';
$old = ['title' => '', 'description' => '', 'location' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please try again.';
    } else {
        $result = $eventController->createEvent($_POST, $_SESSION['user']['id']);
        
        if ($result['success']) {
            $_SESSION['flash_success'] = 'Event created successfully!';
            header('Location: /event/view?id=' . \App\Services\IdEncoder::encode($result['event_id']));
            exit;
        } else {
            $error = $result['error'];
            $old = [
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'location' => $_POST['location'] ?? '',
            ];
        }
    }
}

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="mb-6">
        <a href="/event" class="link link-hover text-sm">
            ← Back to Events
        </a>
    </div>

    <h1 class="text-3xl font-bold mb-8">Create Event</h1>

    <?php if ($error): ?>
        <div class="alert alert-error mb-6">
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST" class="space-y-4">
                <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>

                <!-- Event Title -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Event Title</span>
                    </label>
                    <input type="text" name="title" value="<?= htmlspecialchars($old['title']) ?>" 
                           class="input input-bordered w-full" placeholder="Enter event title" required maxlength="255" />
                </div>

                <!-- Location -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Location</span>
                        <span class="label-text-alt">Optional</span>
                    </label>
                    <input type="text" name="location" value="<?= htmlspecialchars($old['location']) ?>" 
                           class="input input-bordered w-full" placeholder="Event location" maxlength="500" />
                </div>

                <!-- Description -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Description</span>
                        <span class="label-text-alt">Optional</span>
                    </label>
                    <textarea name="description" class="textarea textarea-bordered w-full h-32" 
                              placeholder="Event description"><?= htmlspecialchars($old['description']) ?></textarea>
                </div>

                <div class="divider">Event Dates</div>
                
                <div id="event-dates-container">
                    <div class="event-date-item border border-base-300 rounded-lg p-4 mb-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Date Type</span>
                                </label>
                                <select name="dates[0][date_type]" class="select select-bordered" required>
                                    <option value="competition">Competition</option>
                                    <option value="registration_deadline">Registration Deadline</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Description</span>
                                </label>
                                <input type="text" name="dates[0][description]" class="input input-bordered" placeholder="Optional description">
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Start Date & Time</span>
                                </label>
                                <input type="datetime-local" name="dates[0][start_datetime]" class="input input-bordered" required>
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">End Date & Time</span>
                                </label>
                                <input type="datetime-local" name="dates[0][end_datetime]" class="input input-bordered" required>
                            </div>
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-outline btn-sm" onclick="addEventDate()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Another Date
                </button>

                <div class="divider">Event Tags</div>
                
                <div id="event-tags-container">
                    <div class="event-tag-item flex gap-2 items-end mb-3">
                        <div class="form-control flex-1">
                            <label class="label">
                                <span class="label-text">Tag Name</span>
                            </label>
                            <input type="text" name="tags[0][name]" class="input input-bordered" placeholder="e.g., Interested, Registered">
                        </div>
                        <div class="form-control w-32">
                            <label class="label">
                                <span class="label-text">Color</span>
                            </label>
                            <input type="color" name="tags[0][color]" class="input input-bordered h-12" value="#3b82f6">
                        </div>
                    </div>
                </div>
                
                <button type="button" class="btn btn-outline btn-sm" onclick="addEventTag()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Another Tag
                </button>

                <div class="divider">Visibility</div>
                
                <div class="alert alert-info">
                    <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <div>
                        <p class="font-semibold">Event Visibility</p>
                        <p class="text-sm">By default, events are public and visible to everyone. After creating the event, you can manage visibility to make it private and invite specific users.</p>
                    </div>
                </div>

                <div class="divider"></div>

                <button type="submit" class="btn btn-primary w-full">Create Event</button>
            </form>
        </div>
    </div>
</div>

<script>
let dateIndex = 1;
let tagIndex = 1;

function addEventDate() {
    const container = document.getElementById('event-dates-container');
    const newItem = document.createElement('div');
    newItem.className = 'event-date-item border border-base-300 rounded-lg p-4 mb-4';
    newItem.innerHTML = `
        <div class="flex justify-between items-center mb-3">
            <h3 class="font-semibold">Date ${dateIndex + 1}</h3>
            <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('.event-date-item').remove()">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Date Type</span>
                </label>
                <select name="dates[${dateIndex}][date_type]" class="select select-bordered" required>
                    <option value="competition">Competition</option>
                    <option value="registration_deadline">Registration Deadline</option>
                    <option value="meeting">Meeting</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Description</span>
                </label>
                <input type="text" name="dates[${dateIndex}][description]" class="input input-bordered" placeholder="Optional description">
            </div>
            <div class="form-control">
                <label class="label">
                    <span class="label-text">Start Date & Time</span>
                </label>
                <input type="datetime-local" name="dates[${dateIndex}][start_datetime]" class="input input-bordered" required>
            </div>
            <div class="form-control">
                <label class="label">
                    <span class="label-text">End Date & Time</span>
                </label>
                <input type="datetime-local" name="dates[${dateIndex}][end_datetime]" class="input input-bordered" required>
            </div>
        </div>
    `;
    container.appendChild(newItem);
    dateIndex++;
}

function addEventTag() {
    const container = document.getElementById('event-tags-container');
    const newItem = document.createElement('div');
    newItem.className = 'event-tag-item flex gap-2 items-end mb-3';
    newItem.innerHTML = `
        <div class="form-control flex-1">
            <label class="label">
                <span class="label-text">Tag Name</span>
            </label>
            <input type="text" name="tags[${tagIndex}][name]" class="input input-bordered" placeholder="e.g., Interested, Registered">
        </div>
        <div class="form-control w-32">
            <label class="label">
                <span class="label-text">Color</span>
            </label>
            <input type="color" name="tags[${tagIndex}][color]" class="input input-bordered h-12" value="#3b82f6">
        </div>
        <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('.event-tag-item').remove()">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    `;
    container.appendChild(newItem);
    tagIndex++;
}
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
