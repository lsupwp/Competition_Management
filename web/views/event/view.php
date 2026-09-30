<?php
// Route: /event/view
require_once __DIR__ . '/../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Event Details - Team Competition';

$eventController = new \App\Controllers\EventController();

// Get event ID from query param
$eventId = null;
if (isset($_GET['id'])) {
    $eventId = \App\Services\IdEncoder::decode($_GET['id']);
}

if (!$eventId) {
    header('Location: /event');
    exit;
}

// Get event data
$event = $eventController->getEventById($eventId);

if (!$event) {
    $_SESSION['flash_error'] = 'Event not found';
    header('Location: /event');
    exit;
}

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-4xl">
    <div class="mb-6">
        <a href="/event" class="link link-hover text-sm">
            ← Back to Events
        </a>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-3xl font-bold mb-2"><?= htmlspecialchars($event['title']) ?></h1>
                    <div class="flex items-center gap-4 text-sm text-base-content/70">
                        <span>Created by <?= htmlspecialchars($event['creator_name']) ?></span>
                        <span>•</span>
                        <span><?= date('M d, Y', strtotime($event['created_at'])) ?></span>
                    </div>
                </div>
                <?php if ($event['created_by'] === $_SESSION['user']['id']): ?>
                    <a href="/event/edit?id=<?= \App\Services\IdEncoder::encode($event['id']) ?>" class="btn btn-outline btn-sm">
                        Edit Event
                    </a>
                <?php endif; ?>
            </div>

            <?php if (!empty($event['location'])): ?>
                <div class="flex items-center gap-2 mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-base-content/70" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    <span class="text-base-content/80"><?= htmlspecialchars($event['location']) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($event['description'])): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold mb-2">Description</h2>
                    <p class="text-base-content/80 whitespace-pre-wrap"><?= htmlspecialchars($event['description']) ?></p>
                </div>
            <?php endif; ?>

            <!-- Event Dates -->
            <?php if (!empty($event['dates'])): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold mb-3">Event Dates</h2>
                    <div class="space-y-3">
                        <?php foreach ($event['dates'] as $date): ?>
                            <div class="bg-base-200 rounded-lg p-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <div class="font-semibold text-primary mb-1">
                                            <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $date['date_type']))) ?>
                                        </div>
                                        <div class="text-sm text-base-content/70">
                                            <?= date('M d, Y H:i', strtotime($date['start_datetime'])) ?>
                                            <?php if ($date['start_datetime'] !== $date['end_datetime']): ?>
                                                - <?= date('M d, Y H:i', strtotime($date['end_datetime'])) ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($date['description'])): ?>
                                            <div class="text-sm text-base-content/60 mt-1">
                                                <?= htmlspecialchars($date['description']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Event Tags -->
            <?php if (!empty($event['tags'])): ?>
                <div class="mb-6">
                    <h2 class="text-lg font-semibold mb-3">Tags</h2>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($event['tags'] as $tag): ?>
                            <div class="badge badge-lg" style="background-color: <?= htmlspecialchars($tag['color']) ?>20; color: <?= htmlspecialchars($tag['color']) ?>; border-color: <?= htmlspecialchars($tag['color']) ?>">
                                <?= htmlspecialchars($tag['name']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Registrations -->
            <div class="divider"></div>
            
            <div class="mb-6">
                <h2 class="text-lg font-semibold mb-3">
                    Registrations 
                    <span class="badge badge-primary"><?= count($event['registrations']) ?></span>
                </h2>
                
                <?php if (empty($event['registrations'])): ?>
                    <p class="text-base-content/70">No registrations yet</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table table-zebra w-full">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Team</th>
                                    <th>Status</th>
                                    <th>Registered At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($event['registrations'] as $reg): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <div class="font-semibold"><?= htmlspecialchars($reg['user_name']) ?></div>
                                                <div class="text-sm text-base-content/70"><?= htmlspecialchars($reg['user_email']) ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($reg['team_name']): ?>
                                                <?= htmlspecialchars($reg['team_name']) ?>
                                            <?php else: ?>
                                                <span class="text-base-content/50">Individual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $reg['status'] === 'confirmed' ? 'success' : ($reg['status'] === 'cancelled' ? 'error' : 'warning') ?>">
                                                <?= ucfirst($reg['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y H:i', strtotime($reg['registered_at'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
