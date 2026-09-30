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

                <div class="divider"></div>

                <button type="submit" class="btn btn-primary w-full">Create Event</button>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
