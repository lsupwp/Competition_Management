<?php
// Route: /event
require_once __DIR__ . '/../../vendor/autoload.php';

\App\Services\SessionService::start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Events - Team Competition';

$eventController = new \App\Controllers\EventController();
$userId = (int)$_SESSION['user']['id'];

$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;

$filterTeamId = null;
$filterTeam = null;
if (!empty($_GET['team'])) {
    $decodedTeamId = \App\Services\IdEncoder::decode($_GET['team']);
    if ($decodedTeamId) {
        $filterTeam = $eventController->getTeamByIdForMember((int)$decodedTeamId, $userId);
        if ($filterTeam) {
            $filterTeamId = (int)$filterTeam['id'];
        }
        // Non-members: ignore team filter (no name disclosure)
    }
}

$canCreateEvent = !empty($eventController->getUserManagedTeams($userId));
$teams = [];
$events = [];
$totalPages = 1;
$total = 0;
$queryBase = '?';
$availableTags = [];
$filters = [
    'q' => trim((string)($_GET['q'] ?? '')),
    'tag' => trim((string)($_GET['tag'] ?? '')),
    'date_from' => trim((string)($_GET['date_from'] ?? '')),
    'date_to' => trim((string)($_GET['date_to'] ?? '')),
];
$hasActiveFilters = $filters['q'] !== '' || $filters['tag'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== '';

if ($filterTeam) {
    $title = 'Events — ' . $filterTeam['name'] . ' - Team Competition';
    $availableTags = $eventController->getAvailableEventTags($userId, $filterTeamId);
    $eventData = $eventController->getEvents($page, $perPage, $userId, $filterTeamId, $filters);
    $events = $eventData['events'];
    $totalPages = $eventData['totalPages'];
    $total = $eventData['total'];

    $queryParts = ['team=' . urlencode(\App\Services\IdEncoder::encode($filterTeamId))];
    if ($filters['q'] !== '') {
        $queryParts[] = 'q=' . urlencode($filters['q']);
    }
    if ($filters['tag'] !== '') {
        $queryParts[] = 'tag=' . urlencode($filters['tag']);
    }
    if ($filters['date_from'] !== '') {
        $queryParts[] = 'date_from=' . urlencode($filters['date_from']);
    }
    if ($filters['date_to'] !== '') {
        $queryParts[] = 'date_to=' . urlencode($filters['date_to']);
    }
    $queryBase = '?' . implode('&', $queryParts) . '&';
} else {
    $teams = $eventController->getTeamsForEventIndex($userId);
}

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success mb-6">
            <span><?= htmlspecialchars($_SESSION['flash_success']) ?></span>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-error mb-6">
            <span><?= htmlspecialchars($_SESSION['flash_error']) ?></span>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-center mb-8">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold break-words">
                <?php if ($filterTeam): ?>
                    Events — <?= htmlspecialchars($filterTeam['name']) ?>
                <?php else: ?>
                    Events
                <?php endif; ?>
            </h1>
            <?php if ($filterTeam): ?>
                <a href="/event" class="link link-hover text-sm">← Back to teams</a>
            <?php else: ?>
                <p class="text-sm text-base-content/70 mt-1">Select a team to view its events</p>
            <?php endif; ?>
        </div>
        <?php if ($canCreateEvent): ?>
            <a href="/event/create" class="btn btn-primary w-full sm:w-auto shrink-0">Create Event</a>
        <?php endif; ?>
    </div>

    <?php if (!$filterTeam): ?>
        <?php if (empty($teams)): ?>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body text-center py-16">
                    <h2 class="text-2xl font-bold mb-4">No teams yet</h2>
                    <p class="text-base-content/70 mb-6">Join or create a team to see events</p>
                    <a href="/team/manage" class="btn btn-primary">Go to Teams</a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid gap-4 md:grid-cols-2">
                <?php foreach ($teams as $team): ?>
                    <a href="/event?team=<?= urlencode(\App\Services\IdEncoder::encode($team['id'])) ?>"
                       class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow">
                        <div class="card-body">
                            <div class="flex items-center gap-4">
                                <?php if (!empty($team['logo_url'])): ?>
                                    <div class="avatar">
                                        <div class="w-14 rounded-full">
                                            <img src="<?= safe_upload_url($team['logo_url'] ?? '') ?>" alt="">
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="avatar placeholder">
                                        <div class="bg-primary text-primary-content w-14 rounded-full">
                                            <span class="text-xl"><?= htmlspecialchars(mb_substr($team['name'], 0, 1)) ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-1 min-w-0">
                                    <h2 class="card-title text-xl"><?= htmlspecialchars($team['name']) ?></h2>
                                    <?php if (!empty($team['description'])): ?>
                                        <p class="text-sm text-base-content/70 line-clamp-2">
                                            <?= htmlspecialchars($team['description']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="flex flex-wrap gap-2 mt-2">
                                        <span class="badge badge-ghost badge-sm"><?= htmlspecialchars(ucfirst($team['role'])) ?></span>
                                        <span class="badge badge-primary badge-outline badge-sm">
                                            <?= (int)$team['event_count'] ?> event<?= (int)$team['event_count'] === 1 ? '' : 's' ?>
                                        </span>
                                    </div>
                                </div>
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-base-content/40 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php elseif ($filterTeam): ?>
        <form method="GET" action="/event" class="card bg-base-100 shadow-md mb-6">
            <div class="card-body gap-4">
                <input type="hidden" name="team" value="<?= htmlspecialchars(\App\Services\IdEncoder::encode($filterTeamId)) ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div class="form-control">
                        <label class="label py-1"><span class="label-text">Search title</span></label>
                        <input type="search" name="q" value="<?= htmlspecialchars($filters['q']) ?>"
                               class="input input-bordered w-full" placeholder="Event title...">
                    </div>
                    <div class="form-control">
                        <label class="label py-1"><span class="label-text">Tag</span></label>
                        <select name="tag" class="select select-bordered w-full">
                            <option value="">All tags</option>
                            <?php foreach ($availableTags as $tagName): ?>
                                <option value="<?= htmlspecialchars($tagName) ?>" <?= $filters['tag'] === $tagName ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tagName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-control">
                        <label class="label py-1"><span class="label-text">Date from</span></label>
                        <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from']) ?>"
                               class="input input-bordered w-full">
                    </div>
                    <div class="form-control">
                        <label class="label py-1"><span class="label-text">Date to</span></label>
                        <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to']) ?>"
                               class="input input-bordered w-full">
                    </div>
                </div>
                <div class="flex flex-wrap gap-2 justify-end items-center">
                    <button type="submit" class="btn btn-primary btn-sm">Apply</button>
                    <a href="/event?team=<?= urlencode(\App\Services\IdEncoder::encode($filterTeamId)) ?>" class="btn btn-ghost btn-sm">Clear</a>
                </div>
            </div>
        </form>

        <?php if (empty($events)): ?>
            <div class="card bg-base-100 shadow-xl">
                <div class="card-body text-center py-16">
                    <h2 class="text-2xl font-bold mb-4"><?= $hasActiveFilters ? 'No matching events' : 'No events yet' ?></h2>
                    <p class="text-base-content/70 mb-6">
                        <?= $hasActiveFilters ? 'Try different search or filter options' : 'No visible events for this team' ?>
                    </p>
                    <?php if ($hasActiveFilters): ?>
                        <a href="/event?team=<?= urlencode(\App\Services\IdEncoder::encode($filterTeamId)) ?>" class="btn btn-ghost">Clear filters</a>
                    <?php elseif ($canCreateEvent): ?>
                        <a href="/event/create" class="btn btn-primary">Create Event</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
        <div class="mb-4 text-sm text-base-content/70">
            Showing <?= count($events) ?> of <?= (int)$total ?> events
        </div>
        
        <div class="grid gap-6">
            <?php foreach ($events as $event): ?>
                <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow">
                    <div class="card-body">
                        <div class="flex flex-col gap-3 sm:flex-row sm:justify-between sm:items-start">
                            <div class="flex-1">
                                <h2 class="card-title text-2xl mb-2">
                                    <a href="/event/view?id=<?= h(\App\Services\IdEncoder::encode($event['id'])) ?>" class="link link-hover">
                                        <?= htmlspecialchars($event['title']) ?>
                                    </a>
                                </h2>
                                
                                <?php if (!empty($event['location'])): ?>
                                    <div class="flex items-center gap-2 text-sm text-base-content/70 mb-2">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        <span><?= htmlspecialchars($event['location']) ?></span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($event['description'])): ?>
                                    <p class="text-base-content/80 mb-4"><?= htmlspecialchars(substr($event['description'], 0, 200)) ?><?= strlen($event['description']) > 200 ? '...' : '' ?></p>
                                <?php endif; ?>
                                
                                <?php if (!empty($event['dates'])): ?>
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        <?php foreach (array_slice($event['dates'], 0, 3) as $date): ?>
                                            <div class="badge badge-outline gap-2">
                                                <span class="font-semibold"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $date['date_type']))) ?>:</span>
                                                <span><?= date('M d, Y', strtotime($date['start_datetime'])) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($event['dates']) > 3): ?>
                                            <div class="badge badge-ghost">+<?= count($event['dates']) - 3 ?> more</div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($event['tags'])): ?>
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        <?php foreach ($event['tags'] as $tag): ?>
                                            <?php $tagColor = css_hex_color($tag['color'] ?? null); ?>
                                            <div class="badge" style="background-color: <?= h($tagColor) ?>20; color: <?= h($tagColor) ?>; border-color: <?= h($tagColor) ?>">
                                                <?= htmlspecialchars($tag['name']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-base-content/70">
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        <span>Created by <?= htmlspecialchars($event['creator_name']) ?></span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                        </svg>
                                        <span><?= (int)$event['registration_count'] ?> registered</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span><?= date('M d, Y', strtotime($event['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="/event/view?id=<?= h(\App\Services\IdEncoder::encode($event['id'])) ?>" class="btn btn-ghost btn-circle">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-8">
                <div class="join">
                    <?php if ($page > 1): ?>
                        <a href="<?= h($queryBase) ?>page=<?= (int)($page - 1) ?>" class="join-item btn btn-sm">«</a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="<?= h($queryBase) ?>page=<?= (int)$i ?>" class="join-item btn btn-sm <?= $i === $page ? 'btn-active' : '' ?>">
                            <?= (int)$i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= h($queryBase) ?>page=<?= (int)($page + 1) ?>" class="join-item btn btn-sm">»</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
