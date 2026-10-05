<?php
// Route: /event/create
require_once __DIR__ . '/../../vendor/autoload.php';

\App\Services\SessionService::start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Create Event - Team Competition';

$eventController = new \App\Controllers\EventController();

// Only owner/admin teams can create events
$userTeams = $eventController->getUserManagedTeams($_SESSION['user']['id']);

$error = '';
$old = [
    'title' => '',
    'description' => '',
    'location' => '',
    'team_id' => '',
    'required_members' => 3,
    'dates' => [],
    'tags' => [],
    'visibility_users' => [],
];

$normalizeDatetimeLocal = static function (?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    // datetime-local needs YYYY-MM-DDTHH:mm (strip seconds / normalize space)
    $value = str_replace(' ', 'T', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value) === 1) {
        return substr($value, 0, 16);
    }
    return $value;
};

$normalizeDateRows = static function ($raw) use ($normalizeDatetimeLocal): array {
    if (!is_array($raw)) {
        return [];
    }
    $rows = [];
    foreach (array_values($raw) as $dateRow) {
        if (!is_array($dateRow)) {
            continue;
        }
        $rows[] = [
            'date_type' => (string)($dateRow['date_type'] ?? 'competition'),
            'description' => (string)($dateRow['description'] ?? ''),
            'start_datetime' => $normalizeDatetimeLocal($dateRow['start_datetime'] ?? ''),
            'end_datetime' => $normalizeDatetimeLocal($dateRow['end_datetime'] ?? ''),
        ];
    }
    return $rows;
};

$normalizeTagRows = static function ($raw): array {
    if (!is_array($raw)) {
        return [];
    }
    $rows = [];
    foreach (array_values($raw) as $tagRow) {
        if (!is_array($tagRow)) {
            continue;
        }
        $rows[] = [
            'name' => (string)($tagRow['name'] ?? ''),
            'color' => (string)($tagRow['color'] ?? '#3b82f6'),
        ];
    }
    return $rows;
};

$captureOldFromPost = static function () use ($normalizeDateRows, $normalizeTagRows): array {
    $visibility = $_POST['visibility_users'] ?? [];
    if (!is_array($visibility)) {
        $visibility = [];
    }

    // Prefer JSON snapshots from the browser — nested dates[n][...] can be truncated
    // or dropped by clients/proxies when many fields are posted together.
    $dates = $normalizeDateRows($_POST['dates'] ?? null);
    $datesJson = json_decode((string)($_POST['dates_json'] ?? ''), true);
    if (is_array($datesJson)) {
        $fromJson = $normalizeDateRows($datesJson);
        if (count($fromJson) >= count($dates)) {
            $dates = $fromJson;
        }
    }

    $tags = $normalizeTagRows($_POST['tags'] ?? null);
    $tagsJson = json_decode((string)($_POST['tags_json'] ?? ''), true);
    if (is_array($tagsJson)) {
        $fromJson = $normalizeTagRows($tagsJson);
        if (count($fromJson) >= count($tags)) {
            $tags = $fromJson;
        }
    }

    $visibilityJson = json_decode((string)($_POST['visibility_json'] ?? ''), true);
    if (is_array($visibilityJson) && $visibilityJson !== []) {
        $visibility = $visibilityJson;
    }

    return [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'location' => $_POST['location'] ?? '',
        'team_id' => $_POST['team_id'] ?? '',
        'required_members' => $_POST['required_members'] ?? 3,
        'dates' => $dates,
        'tags' => $tags,
        'visibility_users' => array_values(array_unique(array_map('intval', $visibility))),
    ];
};

if (empty($userTeams) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $error = 'Only team owners and admins can create events. You need owner or admin role on at least one team.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $captureOldFromPost();

    // Validate CSRF token
    if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please try again.';
        $old = $posted;
    } elseif (empty($userTeams)) {
        $error = 'Only team owners and admins can create events.';
        $old = $posted;
    } else {
        // Rebuild POST dates/tags/visibility from normalized capture for the controller
        $_POST['dates'] = $posted['dates'];
        $_POST['tags'] = $posted['tags'];
        $_POST['visibility_users'] = $posted['visibility_users'];

        $result = $eventController->createEvent($_POST, $_SESSION['user']['id']);

        if ($result['success']) {
            unset($_SESSION['event_create_form']);
            $_SESSION['flash_success'] = 'Event created successfully!';
            header('Location: /event/view?id=' . \App\Services\IdEncoder::encode($result['event_id']));
            exit;
        }

        $error = $result['error'];
        $old = $posted;
        $_SESSION['event_create_form'] = ['old' => $old, 'error' => $error];
        header('Location: /event/create');
        exit;
    }

    // CSRF / no-team failures: PRG so refresh does not resubmit
    $_SESSION['event_create_form'] = ['old' => $old, 'error' => $error];
    header('Location: /event/create');
    exit;
}

if (isset($_SESSION['event_create_form']) && is_array($_SESSION['event_create_form'])) {
    $restored = $_SESSION['event_create_form'];
    unset($_SESSION['event_create_form']);
    if (isset($restored['old']) && is_array($restored['old'])) {
        $old = array_merge($old, $restored['old']);
        $old['dates'] = $normalizeDateRows($old['dates'] ?? []);
        $old['tags'] = $normalizeTagRows($old['tags'] ?? []);
        $old['visibility_users'] = array_values(array_unique(array_map('intval', $old['visibility_users'] ?? [])));
    }
    if (!empty($restored['error'])) {
        $error = (string)$restored['error'];
    }
}

$oldDates = $old['dates'] !== [] ? array_values($old['dates']) : [[
    'date_type' => 'competition',
    'description' => '',
    'start_datetime' => '',
    'end_datetime' => '',
]];
$oldTags = $old['tags'] !== [] ? array_values($old['tags']) : [[
    'name' => '',
    'color' => '#3b82f6',
]];
$dateTypes = [
    'competition' => 'Competition',
    'registration_deadline' => 'Registration Deadline',
    'meeting' => 'Meeting',
    'other' => 'Other',
];
$selectedVisibility = array_values(array_unique(array_map('intval', $old['visibility_users'])));

// Server-side member list after validation error (do not rely on JS alone)
$preloadedMembers = [];
$selectedTeamId = (int)($old['team_id'] ?: 0);
if ($selectedTeamId > 0) {
    foreach ($userTeams as $team) {
        if ((int)$team['id'] === $selectedTeamId) {
            $preloadedMembers = $eventController->getTeamMembers($selectedTeamId);
            break;
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

    <?php if (empty($userTeams)): ?>
        <div class="card bg-base-100 shadow-xl">
            <div class="card-body text-center">
                <p class="text-base-content/70 mb-4">You must be a team owner or admin to create events.</p>
                <a href="/team/manage" class="btn btn-primary">Go to Teams</a>
            </div>
        </div>
    <?php else: ?>
    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST" class="space-y-4" id="create-event-form">
                <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                <input type="hidden" name="dates_json" id="dates_json" value="">
                <input type="hidden" name="tags_json" id="tags_json" value="">
                <input type="hidden" name="visibility_json" id="visibility_json" value="">

                <!-- Event Title -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Event Title</span>
                    </label>
                    <input type="text" name="title" value="<?= htmlspecialchars((string)$old['title']) ?>"
                           class="input input-bordered w-full" placeholder="Enter event title" required maxlength="255" />
                </div>

                <!-- Location -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Location</span>
                        <span class="label-text-alt">Optional</span>
                    </label>
                    <input type="text" name="location" value="<?= htmlspecialchars((string)$old['location']) ?>"
                           class="input input-bordered w-full" placeholder="Event location" maxlength="500" />
                </div>

                <!-- Description -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Description</span>
                        <span class="label-text-alt">Optional</span>
                    </label>
                    <textarea name="description" class="textarea textarea-bordered w-full h-32"
                              placeholder="Event description"><?= htmlspecialchars((string)$old['description']) ?></textarea>
                </div>

                <div class="divider">Team & Members</div>

                <!-- Team Selection -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Team</span>
                    </label>
                    <select name="team_id" id="team_id" class="select select-bordered w-full" required>
                        <option value="">Select a team</option>
                        <?php foreach ($userTeams as $team): ?>
                            <option value="<?= (int)$team['id'] ?>" <?= (string)$old['team_id'] === (string)$team['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($team['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="label">
                        <span class="label-text-alt">Select which team this event is for</span>
                    </label>
                </div>

                <!-- Required Members -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Required Members per Team</span>
                    </label>
                    <input type="number" name="required_members" value="<?= htmlspecialchars((string)$old['required_members']) ?>"
                           class="input input-bordered w-full" min="1" max="100" required />
                    <label class="label">
                        <span class="label-text-alt">How many members from each team are needed?</span>
                    </label>
                </div>

                <!-- Member Selection for Visibility -->
                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Select Participating Members</span>
                    </label>
                    <div id="team-members-container"
                         class="border border-base-300 rounded-lg p-4 max-h-64 overflow-y-auto"
                         data-selected="<?= h(js_json($selectedVisibility)) ?>"
                         data-preloaded="<?= $preloadedMembers !== [] ? '1' : '0' ?>">
                        <?php if ($preloadedMembers !== []): ?>
                        <div class="space-y-2">
                            <?php foreach ($preloadedMembers as $member):
                                $memberId = (int)$member['id'];
                                $isChecked = in_array($memberId, $selectedVisibility, true);
                                ?>
                            <label class="flex items-center gap-3 cursor-pointer hover:bg-base-200 p-2 rounded">
                                <input type="checkbox" name="visibility_users[]" value="<?= $memberId ?>"
                                       class="checkbox checkbox-primary" <?= $isChecked ? 'checked' : '' ?> />
                                <div class="flex-1">
                                    <div class="font-semibold"><?= htmlspecialchars((string)$member['name']) ?></div>
                                    <div class="text-sm text-base-content/70"><?= htmlspecialchars((string)$member['email']) ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="text-base-content/50 text-sm">Select a team to see available members</p>
                        <?php endif; ?>
                    </div>
                    <label class="label">
                        <span class="label-text-alt">Only selected members will see this event</span>
                    </label>
                </div>

                <div class="divider">Event Dates</div>

                <div id="event-dates-container">
                    <?php foreach ($oldDates as $i => $dateRow):
                        $dateType = (string)($dateRow['date_type'] ?? 'competition');
                        if (!isset($dateTypes[$dateType])) {
                            $dateType = 'competition';
                        }
                        ?>
                    <div class="event-date-item border border-base-300 rounded-lg p-4 mb-4">
                        <?php if ($i > 0): ?>
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="font-semibold">Date <?= $i + 1 ?></h3>
                            <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('.event-date-item').remove()">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                        <?php endif; ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Date Type</span>
                                </label>
                                <select name="dates[<?= $i ?>][date_type]" class="select select-bordered" required>
                                    <?php foreach ($dateTypes as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>" <?= $dateType === $value ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($label) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Description</span>
                                </label>
                                <input type="text" name="dates[<?= $i ?>][description]" class="input input-bordered"
                                       placeholder="Optional description"
                                       value="<?= htmlspecialchars((string)($dateRow['description'] ?? '')) ?>">
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">Start Date & Time</span>
                                </label>
                                <input type="datetime-local" name="dates[<?= $i ?>][start_datetime]" class="input input-bordered" required
                                       value="<?= htmlspecialchars((string)($dateRow['start_datetime'] ?? '')) ?>">
                            </div>
                            <div class="form-control">
                                <label class="label">
                                    <span class="label-text">End Date & Time</span>
                                </label>
                                <input type="datetime-local" name="dates[<?= $i ?>][end_datetime]" class="input input-bordered" required
                                       value="<?= htmlspecialchars((string)($dateRow['end_datetime'] ?? '')) ?>">
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-outline w-full" onclick="addEventDate()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Another Date
                </button>

                <div class="divider">Event Tags</div>

                <div id="event-tags-container">
                    <?php foreach ($oldTags as $i => $tagRow): ?>
                    <div class="event-tag-item flex gap-2 items-end mb-3">
                        <div class="form-control flex-1">
                            <label class="label">
                                <span class="label-text">Tag Name</span>
                            </label>
                            <input type="text" name="tags[<?= $i ?>][name]" class="input input-bordered"
                                   placeholder="e.g., Interested, Registered"
                                   value="<?= htmlspecialchars((string)($tagRow['name'] ?? '')) ?>">
                        </div>
                        <div class="form-control w-32">
                            <label class="label">
                                <span class="label-text">Color</span>
                            </label>
                            <input type="color" name="tags[<?= $i ?>][color]" class="input input-bordered h-12"
                                   value="<?= htmlspecialchars((string)($tagRow['color'] ?? '#3b82f6')) ?>">
                        </div>
                        <button type="button" class="btn btn-ghost btn-sm" onclick="this.closest('.event-tag-item').remove()">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" class="btn btn-outline w-full" onclick="addEventTag()">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    Add Another Tag
                </button>

                <div class="divider"></div>

                <button type="submit" class="btn btn-primary w-full">Create Event</button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($userTeams)): ?>
<script>
let dateIndex = <?= (int)count($oldDates) ?>;
let tagIndex = <?= (int)count($oldTags) ?>;

function getSelectedVisibilityIds() {
    const container = document.getElementById('team-members-container');
    try {
        return JSON.parse(container.dataset.selected || '[]').map(id => parseInt(id, 10)).filter(Number.isFinite);
    } catch (e) {
        return [];
    }
}

function rememberSelectedVisibility() {
    const checked = Array.from(document.querySelectorAll('input[name="visibility_users[]"]:checked'))
        .map(el => parseInt(el.value, 10))
        .filter(Number.isFinite);
    document.getElementById('team-members-container').dataset.selected = JSON.stringify(checked);
}

function renderMemberCheckbox(member, selected) {
    const memberId = parseInt(member.id, 10);
    const isChecked = selected.includes(memberId);

    const label = document.createElement('label');
    label.className = 'flex items-center gap-3 cursor-pointer hover:bg-base-200 p-2 rounded';

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.name = 'visibility_users[]';
    checkbox.value = String(memberId);
    checkbox.className = 'checkbox checkbox-primary';
    checkbox.checked = isChecked;

    const textWrap = document.createElement('div');
    textWrap.className = 'flex-1';

    const nameEl = document.createElement('div');
    nameEl.className = 'font-semibold';
    nameEl.textContent = member.name ?? '';

    const emailEl = document.createElement('div');
    emailEl.className = 'text-sm text-base-content/70';
    emailEl.textContent = member.email ?? '';

    textWrap.appendChild(nameEl);
    textWrap.appendChild(emailEl);
    label.appendChild(checkbox);
    label.appendChild(textWrap);
    return label;
}

function setMembersMessage(container, message, isError) {
    container.replaceChildren();
    const p = document.createElement('p');
    p.className = isError ? 'text-error text-sm' : 'text-base-content/50 text-sm';
    p.textContent = message;
    container.appendChild(p);
}

function loadTeamMembers(teamId) {
    const container = document.getElementById('team-members-container');
    const selected = getSelectedVisibilityIds();

    if (!teamId) {
        setMembersMessage(container, 'Select a team to see available members', false);
        return;
    }

    fetch('/api/team-members?team_id=' + encodeURIComponent(teamId))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.members.length > 0) {
                const list = document.createElement('div');
                list.className = 'space-y-2';
                data.members.forEach(member => {
                    list.appendChild(renderMemberCheckbox(member, selected));
                });
                container.replaceChildren(list);
                rememberSelectedVisibility();
            } else {
                setMembersMessage(container, 'No members found in this team', false);
            }
        })
        .catch(error => {
            console.error('Error loading team members:', error);
            setMembersMessage(container, 'Error loading members', true);
        });
}

document.getElementById('team-members-container').addEventListener('change', function(e) {
    if (e.target && e.target.name === 'visibility_users[]') {
        rememberSelectedVisibility();
    }
});

document.getElementById('team_id').addEventListener('change', function() {
    document.getElementById('team-members-container').dataset.selected = '[]';
    loadTeamMembers(this.value);
});

// Restore members after validation error only if PHP did not already render them
const membersContainer = document.getElementById('team-members-container');
const initialTeamId = document.getElementById('team_id').value;
if (initialTeamId && membersContainer.dataset.preloaded !== '1') {
    loadTeamMembers(initialTeamId);
}

document.getElementById('create-event-form').addEventListener('submit', function () {
    const dateRows = Array.from(document.querySelectorAll('#event-dates-container .event-date-item')).map((item) => {
        const typeEl = item.querySelector('select[name*="[date_type]"]');
        const descEl = item.querySelector('input[name*="[description]"]');
        const startEl = item.querySelector('input[name*="[start_datetime]"]');
        const endEl = item.querySelector('input[name*="[end_datetime]"]');
        return {
            date_type: typeEl ? typeEl.value : 'competition',
            description: descEl ? descEl.value : '',
            start_datetime: startEl ? startEl.value : '',
            end_datetime: endEl ? endEl.value : '',
        };
    });
    document.getElementById('dates_json').value = JSON.stringify(dateRows);

    const tagRows = Array.from(document.querySelectorAll('#event-tags-container .event-tag-item')).map((item) => {
        const nameEl = item.querySelector('input[name*="[name]"]');
        const colorEl = item.querySelector('input[name*="[color]"]');
        return {
            name: nameEl ? nameEl.value : '',
            color: colorEl ? colorEl.value : '#3b82f6',
        };
    });
    document.getElementById('tags_json').value = JSON.stringify(tagRows);

    rememberSelectedVisibility();
    const visibility = getSelectedVisibilityIds();
    document.getElementById('visibility_json').value = JSON.stringify(visibility);
});

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
<?php endif; ?>

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
