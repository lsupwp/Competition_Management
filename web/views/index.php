<?php
// Route: /
$title = 'Team Competition Management';

// Call hello component
include_once __DIR__ . '/../api/hello.php';

ob_start();
?>
<div class="hero min-h-[60vh]">
    <div class="hero-content text-center">
        <div class="max-w-md">
            <h1 class="text-5xl font-bold">Team Competition</h1>
            <p class="py-6">Team competition management - Create teams, join events, track status</p>

            <div class="alert alert-info mt-4">
                <span><?= htmlspecialchars($helloMessage) ?></span>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-8">
    <?php
    $cardTitle = 'Create Team';
    $cardBody = 'Build your team and compete with friends';
    $cardActions = '<button class="btn btn-primary btn-sm">Create</button>';
    include __DIR__ . '/../templates/components/card.php';
    ?>

    <?php
    $cardTitle = 'Join Events';
    $cardBody = 'Browse and register for competitions';
    $cardActions = '<button class="btn btn-secondary btn-sm">View Events</button>';
    include __DIR__ . '/../templates/components/card.php';
    ?>

    <?php
    $cardTitle = 'Track Progress';
    $cardBody = 'Monitor event status and results';
    $cardActions = '<button class="btn btn-accent btn-sm">Track</button>';
    include __DIR__ . '/../templates/components/card.php';
    ?>
</div>
<?php
$content = ob_get_clean();
include_once __DIR__ . '/../templates/layout.php';
