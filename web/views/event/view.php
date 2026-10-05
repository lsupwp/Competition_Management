<?php
// Route: /event/view
require_once __DIR__ . '/../../vendor/autoload.php';

\App\Services\SessionService::start();

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

$userId = $_SESSION['user']['id'];
if (!$eventController->canUserSeeEvent($eventId, $userId)) {
    $_SESSION['flash_error'] = 'You do not have permission to view this event';
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

    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-error mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-start mb-4">
                <div class="min-w-0">
                    <h1 class="text-2xl sm:text-3xl font-bold mb-2 break-words"><?= htmlspecialchars($event['title']) ?></h1>
                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-base-content/70">
                        <span>Created by <?= htmlspecialchars($event['creator_name']) ?></span>
                        <span class="hidden sm:inline">•</span>
                        <span><?= date('M d, Y', strtotime($event['created_at'])) ?></span>
                        <?php if (!empty($event['team_id']) && !empty($event['team_name'])): ?>
                            <span class="hidden sm:inline">•</span>
                            <a href="/event?team=<?= urlencode(\App\Services\IdEncoder::encode($event['team_id'])) ?>"
                               class="link link-hover text-primary">
                                <?= htmlspecialchars($event['team_name']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 shrink-0">
                    <?php
                    $viewerId = (int)$_SESSION['user']['id'];
                    $canEditEvent = $eventController->canUserEditEvent($eventId, $viewerId);
                    $canDeleteEvent = $eventController->canUserDeleteEvent($eventId, $viewerId);
                    ?>
                    <?php if ($canEditEvent): ?>
                        <a href="/event/edit?id=<?= h(\App\Services\IdEncoder::encode($event['id'])) ?>" class="btn btn-outline btn-sm">
                            Edit Event
                        </a>
                    <?php endif; ?>
                    <?php if ($canDeleteEvent): ?>
                        <form method="POST" action="/event/delete"
                              data-confirm="Delete this event? This cannot be undone easily."
                              data-confirm-title="Delete Event"
                              data-confirm-text="Delete"
                              data-confirm-class="btn-error">
                            <input type="hidden" name="event_id" value="<?= htmlspecialchars(\App\Services\IdEncoder::encode($event['id'])) ?>">
                            <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                            <button type="submit" class="btn btn-error btn-outline btn-sm">Delete</button>
                        </form>
                    <?php endif; ?>
                </div>
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
                            <?php $tagColor = css_hex_color($tag['color'] ?? null); ?>
                            <div class="badge badge-lg" style="background-color: <?= h($tagColor) ?>20; color: <?= h($tagColor) ?>; border-color: <?= h($tagColor) ?>">
                                <?= htmlspecialchars($tag['name']) ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Registration Section -->
            <div class="divider"></div>
            
            <?php
            $userId = (int)$_SESSION['user']['id'];
            $isRegistered = $eventController->isUserRegistered($eventId, $userId);
            $canSeeEvent = $eventController->canUserSeeEvent($eventId, $userId);
            $isEventOwner = (int)$event['created_by'] === $userId;
            $encodedEventId = \App\Services\IdEncoder::encode($eventId);
            // Only event's team (if any) — not all user teams
            $registerTeams = [];
            if (!empty($event['team_id'])) {
                foreach ($eventController->getUserTeams($userId) as $team) {
                    if ((int)$team['id'] === (int)$event['team_id']) {
                        $registerTeams[] = $team;
                        break;
                    }
                }
            }
            ?>
            
            <div class="mb-6">
                <h2 class="text-lg font-semibold mb-3">
                    Register for Event
                </h2>
                
                <?php if (!$canSeeEvent): ?>
                    <div class="alert alert-warning">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>You don't have permission to view this event.</span>
                    </div>
                <?php elseif ($isRegistered): ?>
                    <div class="alert alert-success">
                        <svg xmlns="http://www.w3.org/2000/svg" class="stroke-current shrink-0 h-6 w-6" fill="none" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>You are already registered for this event!</span>
                    </div>
                <?php else: ?>
                    <form method="POST" action="/event/register">
                        <input type="hidden" name="event_id" value="<?= htmlspecialchars($encodedEventId) ?>">
                        <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                        
                        <div class="form-control mb-4">
                            <label class="label">
                                <span class="label-text">Registration Type</span>
                            </label>
                            <select name="registration_type" class="select select-bordered w-full" required>
                                <option value="individual">Individual Registration</option>
                                <?php foreach ($registerTeams as $team): ?>
                                    <option value="team_<?= (int)$team['id'] ?>">
                                        Team: <?= htmlspecialchars($team['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            Register Now
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Registrations List -->
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
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($event['registrations'] as $reg):
                                    $regUserId = (int)$reg['user_id'];
                                    $isOwnRow = $regUserId === $userId;
                                    $canUnregister = $isOwnRow;
                                    $canKick = $isEventOwner && !$isOwnRow;
                                ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <div class="font-semibold">
                                                    <?= htmlspecialchars($reg['user_name']) ?>
                                                    <?php if ($isOwnRow): ?>
                                                        <span class="badge badge-ghost badge-sm">You</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-sm text-base-content/70">
                                                    <?php if ($isEventOwner || $isOwnRow): ?>
                                                        <?= htmlspecialchars($reg['user_email']) ?>
                                                    <?php else: ?>
                                                        ···
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($reg['team_id']) && !empty($reg['team_name'])): ?>
                                                <a href="/event?team=<?= urlencode(\App\Services\IdEncoder::encode($reg['team_id'])) ?>"
                                                   class="link link-hover text-primary">
                                                    <?= htmlspecialchars($reg['team_name']) ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-base-content/50">Individual</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-<?= $reg['status'] === 'confirmed' ? 'success' : ($reg['status'] === 'cancelled' ? 'error' : 'warning') ?>">
                                                <?= h(ucfirst((string)$reg['status'])) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M d, Y H:i', strtotime($reg['registered_at'])) ?></td>
                                        <td>
                                            <?php if ($canUnregister): ?>
                                                <form method="POST" action="/event/unregister" class="inline"
                                                      data-confirm="Unregister from this event?"
                                                      data-confirm-title="Unregister"
                                                      data-confirm-text="Unregister"
                                                      data-confirm-class="btn-error">
                                                    <input type="hidden" name="event_id" value="<?= htmlspecialchars($encodedEventId) ?>">
                                                    <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                                                    <button type="submit" class="btn btn-ghost btn-xs text-error">Unregister</button>
                                                </form>
                                            <?php elseif ($canKick): ?>
                                                <form method="POST" action="/event/unregister" class="inline"
                                                      data-confirm="Remove this user from the event?"
                                                      data-confirm-title="Kick Member"
                                                      data-confirm-text="Kick"
                                                      data-confirm-class="btn-error">
                                                    <input type="hidden" name="event_id" value="<?= htmlspecialchars($encodedEventId) ?>">
                                                    <input type="hidden" name="user_id" value="<?= htmlspecialchars(\App\Services\IdEncoder::encode($regUserId)) ?>">
                                                    <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                                                    <button type="submit" class="btn btn-ghost btn-xs text-error">Kick</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-base-content/40">—</span>
                                            <?php endif; ?>
                                        </td>
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
include_once __DIR__ . '/../../templates/layout.php';
