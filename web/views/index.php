<?php
// Route: /
require_once __DIR__ . '/../vendor/autoload.php';

if (session_status() === PHP_SESSION_NONE) {
    \App\Services\SessionService::start();
}

$title = 'Team Competition Management';
$isLoggedIn = isset($_SESSION['user']);
$sampleTeams = [];
$sampleEvents = [];
$flashError = '';
$flashSuccess = '';

if (isset($_SESSION['flash_error'])) {
    $flashError = (string)$_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
if (isset($_SESSION['flash_success'])) {
    $flashSuccess = (string)$_SESSION['flash_success'];
    unset($_SESSION['flash_success']);
}

if ($isLoggedIn) {
    $userId = (int)$_SESSION['user']['id'];
    $teamController = new \App\Controllers\TeamController();
    $eventController = new \App\Controllers\EventController();

    $teamResult = $teamController->getTeamsForUser($userId, '', 1, 3);
    $sampleTeams = $teamResult['teams'] ?? [];

    $eventResult = $eventController->getEvents(1, 3, $userId);
    $sampleEvents = $eventResult['events'] ?? [];
}

ob_start();
?>
<?php if ($flashError !== ''): ?>
<div class="max-w-3xl mx-auto pt-6">
    <div class="alert alert-error">
        <span><?= htmlspecialchars($flashError) ?></span>
    </div>
</div>
<?php endif; ?>
<?php if ($flashSuccess !== ''): ?>
<div class="max-w-3xl mx-auto pt-6">
    <div class="alert alert-success">
        <span><?= htmlspecialchars($flashSuccess) ?></span>
    </div>
</div>
<?php endif; ?>
<section class="max-w-3xl mx-auto py-16 md:py-24 text-center">
    <img src="/assets/logo.png" alt="Team Comp" class="mx-auto h-20 w-20 md:h-24 md:w-24 rounded-full object-cover" />
    <h1 class="mt-5 text-4xl md:text-5xl font-bold tracking-tight">Team Comp</h1>
    <p class="mt-4 text-base md:text-lg text-base-content/70 max-w-xl mx-auto">
        Create teams, manage events, and keep competition dates in one place.
    </p>
    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <?php if ($isLoggedIn): ?>
            <a href="/team/create" class="btn btn-primary">Create team</a>
            <a href="/event" class="btn btn-ghost">Browse events</a>
        <?php else: ?>
            <a href="/auth/register" class="btn btn-primary">Get started</a>
            <a href="/auth/login" class="btn btn-ghost">Log in</a>
        <?php endif; ?>
    </div>
</section>

<section class="max-w-3xl mx-auto pb-16 md:pb-24">
    <?php if ($isLoggedIn): ?>
        <div class="divider text-sm text-base-content/50">Your teams</div>
        <?php if (empty($sampleTeams)): ?>
            <p class="text-sm text-base-content/60 text-center">
                No teams yet.
                <a href="/team/create" class="link link-primary">Create one</a>
                or
                <a href="/team/join" class="link link-primary">join with a token</a>.
            </p>
        <?php else: ?>
            <ul class="space-y-2">
                <?php foreach ($sampleTeams as $team): ?>
                    <li>
                        <a href="/team/manage?id=<?= htmlspecialchars(\App\Services\IdEncoder::encode((int)$team['id'])) ?>"
                           class="flex items-center justify-between gap-3 rounded-lg border border-base-300 bg-base-100 px-4 py-3 hover:border-primary/40 transition-colors">
                            <span class="font-medium truncate"><?= htmlspecialchars($team['name']) ?></span>
                            <span class="text-xs text-base-content/50 shrink-0">
                                <?= htmlspecialchars(ucfirst($team['user_role'] ?? 'member')) ?>
                                · <?= (int)($team['member_count'] ?? 0) ?> members
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="mt-3 text-right">
                <a href="/team/manage" class="link link-hover text-sm">All teams</a>
            </div>
        <?php endif; ?>

        <div class="divider text-sm text-base-content/50 mt-10">Your events</div>
        <?php if (empty($sampleEvents)): ?>
            <p class="text-sm text-base-content/60 text-center">
                No events visible yet.
                <a href="/event" class="link link-primary">Browse events</a>
                or open the
                <a href="/event/calendar" class="link link-primary">calendar</a>.
            </p>
        <?php else: ?>
            <ul class="space-y-2">
                <?php foreach ($sampleEvents as $event): ?>
                    <li>
                        <a href="/event/view?id=<?= htmlspecialchars(\App\Services\IdEncoder::encode((int)$event['id'])) ?>"
                           class="flex items-center justify-between gap-3 rounded-lg border border-base-300 bg-base-100 px-4 py-3 hover:border-primary/40 transition-colors">
                            <span class="font-medium truncate"><?= htmlspecialchars($event['title']) ?></span>
                            <span class="text-xs text-base-content/50 shrink-0 truncate max-w-[40%]">
                                <?= htmlspecialchars($event['team_name'] ?? 'Event') ?>
                            </span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
            <div class="mt-3 text-right">
                <a href="/event" class="link link-hover text-sm">All events</a>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="divider text-sm text-base-content/50">Why sign in</div>
        <p class="text-center text-base-content/70 max-w-md mx-auto">
            Create teams, invite members, register for events, and follow competition dates on the calendar.
        </p>
        <div class="mt-5 flex flex-wrap justify-center gap-3">
            <a href="/auth/login" class="btn btn-primary btn-sm">Log in</a>
            <a href="/auth/register" class="btn btn-ghost btn-sm">Create account</a>
        </div>
    <?php endif; ?>
</section>
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
