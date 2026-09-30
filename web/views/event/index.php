<?php
// Route: /event
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Events - Team Competition';

$eventController = new \App\Controllers\EventController();

// Get page number
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 10;

// Get events
$eventData = $eventController->getEvents($page, $perPage);
$events = $eventData['events'];
$totalPages = $eventData['totalPages'];
$total = $eventData['total'];

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-6xl">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold">Events</h1>
        <a href="/event/create" class="btn btn-primary">Create Event</a>
    </div>

    <?php if (empty($events)): ?>
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body text-center py-16">
                <h2 class="text-2xl font-bold mb-4">No events yet</h2>
                <p class="text-base-content/70 mb-6">Create your first event to get started</p>
                <a href="/event/create" class="btn btn-primary">Create Event</a>
            </div>
        </div>
    <?php else: ?>
        <div class="mb-4 text-sm text-base-content/70">
            Showing <?= count($events) ?> of <?= $total ?> events
        </div>
        
        <div class="grid gap-6">
            <?php foreach ($events as $event): ?>
                <div class="card bg-base-100 shadow-xl hover:shadow-2xl transition-shadow">
                    <div class="card-body">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <h2 class="card-title text-2xl mb-2">
                                    <a href="/event/view?id=<?= \App\Services\IdEncoder::encode($event['id']) ?>" class="link link-hover">
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
                                
                                <!-- Event Dates -->
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
                                
                                <!-- Event Tags -->
                                <?php if (!empty($event['tags'])): ?>
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        <?php foreach ($event['tags'] as $tag): ?>
                                            <div class="badge" style="background-color: <?= htmlspecialchars($tag['color']) ?>20; color: <?= htmlspecialchars($tag['color']) ?>; border-color: <?= htmlspecialchars($tag['color']) ?>">
                                                <?= htmlspecialchars($tag['name']) ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="flex items-center gap-4 text-sm text-base-content/70">
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
                                        <span><?= $event['registration_count'] ?> registered</span>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                        <span><?= date('M d, Y', strtotime($event['created_at'])) ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <a href="/event/view?id=<?= \App\Services\IdEncoder::encode($event['id']) ?>" class="btn btn-ghost btn-circle">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="flex justify-center mt-8">
                <div class="join">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>" class="join-item btn btn-sm">«</a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <a href="?page=<?= $i ?>" class="join-item btn btn-sm <?= $i === $page ? 'btn-active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>" class="join-item btn btn-sm">»</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
