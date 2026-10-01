<?php
// Route: /event/calendar
require_once __DIR__ . '/../../vendor/autoload.php';

session_start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Event Calendar - Team Competition';

$eventController = new \App\Controllers\EventController();
$userId = (int)$_SESSION['user']['id'];
$userTeams = $eventController->getUserTeams($userId);

$filterTeamId = null;
$filterTeam = null;
$teamQuery = '';
if (!empty($_GET['team'])) {
    $filterTeamId = \App\Services\IdEncoder::decode($_GET['team']);
    if ($filterTeamId) {
        $filterTeam = $eventController->getTeamById((int)$filterTeamId);
        if ($filterTeam) {
            $teamQuery = urlencode(\App\Services\IdEncoder::encode($filterTeamId));
            $title = 'Calendar — ' . $filterTeam['name'] . ' - Team Competition';
        } else {
            $filterTeamId = null;
        }
    } else {
        $filterTeamId = null;
    }
}

$feedUrl = '/api/events-calendar';
if ($teamQuery !== '') {
    $feedUrl .= '?team=' . $teamQuery;
}

ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js"></script>

<div class="container mx-auto px-4 py-8 max-w-7xl">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-3xl font-bold">
                <?php if ($filterTeam): ?>
                    Calendar — <?= htmlspecialchars($filterTeam['name']) ?>
                <?php else: ?>
                    Event Calendar
                <?php endif; ?>
            </h1>
            <p class="text-sm text-base-content/70 mt-1">Month view of event dates you can see</p>
        </div>
        <div class="flex flex-wrap gap-2 items-center">
            <form method="GET" class="flex gap-2 items-center">
                <select name="team" class="select select-bordered select-sm" onchange="this.form.submit()">
                    <option value="">All teams</option>
                    <?php foreach ($userTeams as $team): ?>
                        <option value="<?= htmlspecialchars(\App\Services\IdEncoder::encode($team['id'])) ?>"
                            <?= $filterTeamId && (int)$filterTeamId === (int)$team['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($team['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
            <a href="/event" class="btn btn-ghost btn-sm">Team list</a>
        </div>
    </div>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body p-2 md:p-4">
            <div id="event-calendar" class="min-h-[70vh]"></div>
        </div>
    </div>
</div>

<style>
    #event-calendar .fc {
        --fc-border-color: color-mix(in oklch, var(--color-base-content) 15%, transparent);
        --fc-page-bg-color: transparent;
        --fc-neutral-bg-color: var(--color-base-200);
        --fc-list-event-hover-bg-color: color-mix(in oklch, var(--color-base-content) 8%, transparent);
        --fc-today-bg-color: color-mix(in oklch, var(--color-primary) 12%, transparent);
        --fc-event-border-color: transparent;
        --fc-button-text-color: var(--color-primary-content);
        color: var(--color-base-content);
        font-family: inherit;
    }
    #event-calendar .fc .fc-toolbar-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--color-base-content);
    }
    #event-calendar .fc .fc-button {
        background: var(--color-primary);
        border: none;
        text-transform: capitalize;
        color: var(--color-primary-content);
    }
    #event-calendar .fc .fc-button-primary:not(:disabled).fc-button-active,
    #event-calendar .fc .fc-button-primary:not(:disabled):active {
        background: var(--color-primary-focus, var(--color-primary));
        filter: brightness(0.9);
    }
    #event-calendar .fc .fc-col-header,
    #event-calendar .fc .fc-col-header-cell,
    #event-calendar .fc th {
        background: var(--color-base-200) !important;
        color: var(--color-base-content) !important;
    }
    #event-calendar .fc .fc-col-header-cell-cushion,
    #event-calendar .fc .fc-timegrid-axis-cushion,
    #event-calendar .fc .fc-timegrid-slot-label-cushion,
    #event-calendar .fc .fc-daygrid-day-number,
    #event-calendar .fc .fc-list-day-text,
    #event-calendar .fc .fc-list-day-side-text {
        color: var(--color-base-content) !important;
        text-decoration: none;
    }
    #event-calendar .fc .fc-daygrid-event {
        border-radius: 0.375rem;
        padding: 1px 4px;
        font-size: 0.75rem;
        font-weight: 600;
        border: none;
    }
    #event-calendar .fc .fc-daygrid-day-number {
        font-weight: 600;
        padding: 0.5rem;
    }
    #event-calendar .fc .fc-col-header-cell-cushion {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 0.75rem 0;
    }
    #event-calendar .fc .fc-scrollgrid,
    #event-calendar .fc .fc-scrollgrid td,
    #event-calendar .fc .fc-scrollgrid th {
        border-color: color-mix(in oklch, var(--color-base-content) 15%, transparent);
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('event-calendar');
    const feedUrl = <?= json_encode($feedUrl) ?>;

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,listWeek'
        },
        navLinks: true,
        nowIndicator: true,
        dayMaxEvents: 3,
        eventDisplay: 'block',
        events: feedUrl,
        eventClick: function(info) {
            if (info.event.url) {
                info.jsEvent.preventDefault();
                window.location.href = info.event.url;
            }
        },
        eventDidMount: function(info) {
            const team = info.event.extendedProps.team_name;
            if (team) {
                info.el.setAttribute('title', team + ' — ' + info.event.title);
            }
        }
    });

    calendar.render();
});
</script>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
