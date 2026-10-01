<?php
// Route: /event/calendar
require_once __DIR__ . '/../../vendor/autoload.php';

\App\Services\SessionService::start();

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
                <select name="team" class="select select-bordered select-sm w-full sm:w-auto max-w-xs" onchange="this.form.submit()">
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
            <div id="event-calendar" class="min-h-[60vh] overflow-x-auto"></div>
        </div>
    </div>
</div>

<style>
    /* FC mounts class "fc" on #event-calendar itself (not a child). */
    #event-calendar.fc {
        --fc-border-color: rgba(128, 128, 128, 0.35);
        --fc-page-bg-color: #ffffff;
        --fc-neutral-bg-color: #f3f4f6;
        --fc-list-event-hover-bg-color: rgba(0, 0, 0, 0.04);
        --fc-today-bg-color: rgba(59, 130, 246, 0.12);
        --fc-event-border-color: transparent;
        color: #111827;
        font-family: inherit;
    }
    html[data-theme="dark"] #event-calendar.fc {
        --fc-border-color: rgba(255, 255, 255, 0.12);
        --fc-page-bg-color: #1d232a;
        --fc-neutral-bg-color: #2a323c;
        --fc-list-event-hover-bg-color: rgba(255, 255, 255, 0.06);
        --fc-today-bg-color: rgba(251, 191, 36, 0.18);
        color: #e5e7eb;
    }
    #event-calendar.fc .fc-scrollgrid-section-sticky > *,
    #event-calendar.fc .fc-scrollgrid-section-header > *,
    #event-calendar.fc .fc-col-header,
    #event-calendar.fc .fc-col-header-cell,
    #event-calendar.fc th.fc-col-header-cell,
    #event-calendar.fc .fc-scrollgrid-sync-inner {
        background-color: #ffffff !important;
        color: #111827 !important;
    }
    html[data-theme="dark"] #event-calendar.fc .fc-scrollgrid-section-sticky > *,
    html[data-theme="dark"] #event-calendar.fc .fc-scrollgrid-section-header > *,
    html[data-theme="dark"] #event-calendar.fc .fc-col-header,
    html[data-theme="dark"] #event-calendar.fc .fc-col-header-cell,
    html[data-theme="dark"] #event-calendar.fc th.fc-col-header-cell,
    html[data-theme="dark"] #event-calendar.fc .fc-scrollgrid-sync-inner {
        background-color: #1d232a !important;
        color: #e5e7eb !important;
    }
    #event-calendar.fc .fc-toolbar-title {
        font-size: 1.1rem;
        font-weight: 700;
        color: inherit;
    }
    @media (max-width: 640px) {
        #event-calendar.fc .fc-toolbar {
            flex-direction: column;
            gap: 0.5rem;
            align-items: stretch;
        }
        #event-calendar.fc .fc-toolbar-chunk {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        #event-calendar.fc .fc-toolbar-title {
            font-size: 1rem;
            text-align: center;
        }
        #event-calendar.fc .fc-button {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    }
    #event-calendar.fc .fc-button {
        background: oklch(0.7 0.15 198);
        border: none;
        text-transform: capitalize;
        color: #fff;
    }
    #event-calendar.fc .fc-button-primary:not(:disabled).fc-button-active,
    #event-calendar.fc .fc-button-primary:not(:disabled):active {
        filter: brightness(0.85);
    }
    #event-calendar.fc a.fc-col-header-cell-cushion,
    #event-calendar.fc .fc-col-header-cell-cushion,
    #event-calendar.fc .fc-timegrid-axis-cushion,
    #event-calendar.fc .fc-timegrid-slot-label-cushion,
    #event-calendar.fc a.fc-daygrid-day-number,
    #event-calendar.fc .fc-daygrid-day-number,
    #event-calendar.fc .fc-list-day-text,
    #event-calendar.fc .fc-list-day-side-text {
        color: inherit !important;
        text-decoration: none !important;
    }
    html[data-theme="dark"] #event-calendar.fc a.fc-col-header-cell-cushion,
    html[data-theme="dark"] #event-calendar.fc .fc-col-header-cell-cushion {
        color: #e5e7eb !important;
    }
    #event-calendar.fc .fc-daygrid-event {
        border-radius: 0.375rem;
        padding: 1px 4px;
        font-size: 0.75rem;
        font-weight: 600;
        border: none;
    }
    #event-calendar.fc .fc-daygrid-day-number {
        font-weight: 600;
        padding: 0.5rem;
    }
    #event-calendar.fc .fc-col-header-cell-cushion {
        font-size: 0.75rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        padding: 0.75rem 0;
    }
    #event-calendar.fc .fc-scrollgrid,
    #event-calendar.fc .fc-scrollgrid td,
    #event-calendar.fc .fc-scrollgrid th {
        border-color: var(--fc-border-color);
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
