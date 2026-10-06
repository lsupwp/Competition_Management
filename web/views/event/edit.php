<?php
// Route: /event/edit
require_once __DIR__ . '/../../vendor/autoload.php';

\App\Services\SessionService::start();

if (!isset($_SESSION['user'])) {
    header('Location: /auth/login');
    exit;
}

$title = 'Edit Event - Team Competition';

$eventController = new \App\Controllers\EventController();
$userId = $_SESSION['user']['id'];

$eventId = null;
if (isset($_GET['id'])) {
    $eventId = \App\Services\IdEncoder::decode($_GET['id']);
}

if (!$eventId) {
    header('Location: /event');
    exit;
}

$event = $eventController->getEventById($eventId);
if (!$event || !$eventController->canUserSeeEvent($eventId, $userId)) {
    $_SESSION['flash_error'] = 'Event not found';
    header('Location: /event');
    exit;
}

if (!$eventController->canUserEditEvent($eventId, $userId)) {
    $_SESSION['flash_error'] = 'Only the event creator can edit this event';
    header('Location: /event/view?id=' . \App\Services\IdEncoder::encode($eventId));
    exit;
}

$userTeams = $eventController->getUserManagedTeams($userId);
$visibilityUserIds = $eventController->getEventVisibilityUserIds($eventId);
$registeredUserIds = $eventController->getRegisteredUserIds($eventId);
$registeredCount = count($registeredUserIds);
$minRequiredMembers = max(1, $registeredCount);
// Registered always stay visible in UI selection
$visibilityUserIds = array_values(array_unique(array_merge($visibilityUserIds, $registeredUserIds)));
$teamMembers = !empty($event['team_id'])
    ? $eventController->getTeamMembers((int)$event['team_id'])
    : [];

$error = '';
$old = [
    'title' => $event['title'],
    'description' => $event['description'] ?? '',
    'location' => $event['location'] ?? '',
    'team_id' => $event['team_id'] ?? '',
    'required_members' => $event['required_members'] ?? 3,
    'dates' => $event['dates'],
    'tags' => $event['tags'],
    'visibility_users' => $visibilityUserIds,
];

$dateTypeOptions = [
    'competition' => 'Competition',
    'registration_deadline' => 'Registration Deadline',
    'meeting' => 'Meeting',
    'other' => 'Other',
];

$normalizeDatetimeLocal = static function (?string $value): string {
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $value = str_replace(' ', 'T', $value);
    if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}/', $value) === 1) {
        return substr($value, 0, 16);
    }
    $ts = strtotime($value);
    return $ts ? date('Y-m-d\TH:i', $ts) : '';
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

$captureOldFromPost = static function () use ($normalizeDateRows, $normalizeTagRows, $registeredUserIds): array {
    $visibility = $_POST['visibility_users'] ?? [];
    if (!is_array($visibility)) {
        $visibility = [];
    }
    $visibilityJson = json_decode((string)($_POST['visibility_json'] ?? ''), true);
    if (is_array($visibilityJson) && $visibilityJson !== []) {
        $visibility = $visibilityJson;
    }

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

    return [
        'title' => $_POST['title'] ?? '',
        'description' => $_POST['description'] ?? '',
        'location' => $_POST['location'] ?? '',
        'team_id' => $_POST['team_id'] ?? '',
        'required_members' => $_POST['required_members'] ?? 3,
        'dates' => $dates,
        'tags' => $tags,
        'visibility_users' => array_values(array_unique(array_merge(
            array_map('intval', $visibility),
            $registeredUserIds
        ))),
    ];
};

$formSessionKey = 'event_edit_form_' . (int)$eventId;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = $captureOldFromPost();

    if (!\App\Services\CsrfService::validateToken($_POST['csrf_token'] ?? null)) {
        $error = 'Invalid security token. Please try again.';
        $old = $posted;
        $_SESSION[$formSessionKey] = ['old' => $old, 'error' => $error];
        header('Location: /event/edit?id=' . rawurlencode(\App\Services\IdEncoder::encode($eventId)));
        exit;
    }

    $_POST['dates'] = $posted['dates'];
    $_POST['tags'] = $posted['tags'];
    $_POST['visibility_users'] = $posted['visibility_users'];

    $result = $eventController->updateEvent($eventId, $_POST, $userId);

    if ($result['success']) {
        unset($_SESSION[$formSessionKey]);
        $_SESSION['flash_success'] = 'Event updated successfully!';
        header('Location: /event/view?id=' . \App\Services\IdEncoder::encode($eventId));
        exit;
    }

    $error = $result['error'];
    $old = $posted;
    $_SESSION[$formSessionKey] = ['old' => $old, 'error' => $error];
    header('Location: /event/edit?id=' . rawurlencode(\App\Services\IdEncoder::encode($eventId)));
    exit;
}

if (isset($_SESSION[$formSessionKey]) && is_array($_SESSION[$formSessionKey])) {
    $restored = $_SESSION[$formSessionKey];
    unset($_SESSION[$formSessionKey]);
    if (isset($restored['old']) && is_array($restored['old'])) {
        $old = array_merge($old, $restored['old']);
        $old['dates'] = $normalizeDateRows($old['dates'] ?? []);
        $old['tags'] = $normalizeTagRows($old['tags'] ?? []);
        $old['visibility_users'] = array_values(array_unique(array_map('intval', $old['visibility_users'] ?? [])));
    }
    if (!empty($restored['error'])) {
        $error = (string)$restored['error'];
    }
    $teamMembers = !empty($old['team_id'])
        ? $eventController->getTeamMembers((int)$old['team_id'])
        : [];
    $visibilityUserIds = $old['visibility_users'];
}

// Normalize DB dates to datetime-local for first paint
$old['dates'] = $normalizeDateRows($old['dates'] ?? []);
$old['tags'] = $normalizeTagRows($old['tags'] ?? []);

$encodedId = \App\Services\IdEncoder::encode($eventId);

ob_start();
?>
<div class="container mx-auto px-4 py-8 max-w-2xl">
    <div class="mb-6">
        <a href="/event/view?id=<?= htmlspecialchars($encodedId) ?>" class="link link-hover text-sm">
            ← Back to Event
        </a>
    </div>

    <h1 class="text-3xl font-bold mb-8">Edit Event</h1>

    <?php if ($error): ?>
        <div class="alert alert-error mb-6">
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="card bg-base-100 shadow-xl">
        <div class="card-body">
            <form method="POST" class="space-y-4" id="edit-event-form">
                <?php include __DIR__ . '/../../templates/components/csrf.php'; ?>
                <input type="hidden" name="dates_json" id="dates_json" value="">
                <input type="hidden" name="tags_json" id="tags_json" value="">
                <input type="hidden" name="visibility_json" id="visibility_json" value="">

                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Event Title</span>
                    </label>
                    <input type="text" name="title" value="<?= htmlspecialchars($old['title']) ?>"
                           class="input input-bordered w-full" placeholder="Enter event title" required maxlength="255" />
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Location</span>
                        <span class="label-text-alt">Optional</span>
                    </label>
                    <input type="text" name="location" value="<?= htmlspecialchars($old['location']) ?>"
                           class="input input-bordered w-full" placeholder="Event location" maxlength="500" />
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Description</span>
                        <span class="label-text-alt">Optional</span>
                    </label>
                    <textarea name="description" class="textarea textarea-bordered w-full h-32"
                              placeholder="Event description"><?= htmlspecialchars($old['description']) ?></textarea>
                </div>

                <div class="divider">Team & Members</div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Team</span>
                    </label>
                    <select name="team_id" id="team_id" class="select select-bordered w-full" required>
                        <option value="">Select a team</option>
                        <?php foreach ($userTeams as $team): ?>
                            <option value="<?= (int)$team['id'] ?>" <?= (int)$old['team_id'] === (int)$team['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($team['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Required Members per Team</span>
                    </label>
                    <input type="number" name="required_members"
                           value="<?= htmlspecialchars((string)max((int)$old['required_members'], $minRequiredMembers)) ?>"
                           class="input input-bordered w-full"
                           min="<?= (int)$minRequiredMembers ?>" max="100" required />
                    <label class="label">
                        <span class="label-text-alt">
                            Cannot be below registered count (<?= (int)$registeredCount ?>)
                        </span>
                    </label>
                </div>

                <div class="form-control">
                    <label class="label">
                        <span class="label-text font-semibold">Select Participating Members</span>
                    </label>
                    <div id="team-members-container" class="border border-base-300 rounded-lg p-4 max-h-64 overflow-y-auto"
                         data-selected="<?= h(js_json(array_values($visibilityUserIds))) ?>"
                         data-locked="<?= h(js_json(array_values($registeredUserIds))) ?>">
                        <?php if (empty($teamMembers)): ?>
                            <p class="text-base-content/50 text-sm">Select a team to see available members</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($teamMembers as $member):
                                    $memberId = (int)$member['id'];
                                    $isRegistered = in_array($memberId, $registeredUserIds, true);
                                    $isChecked = $isRegistered || in_array($memberId, $visibilityUserIds, true);
                                ?>
                                    <label class="flex items-center gap-3 <?= $isRegistered ? '' : 'cursor-pointer hover:bg-base-200' ?> p-2 rounded">
                                        <?php if ($isRegistered): ?>
                                            <input type="hidden" name="visibility_users[]" value="<?= $memberId ?>">
                                            <input type="checkbox" class="checkbox checkbox-primary" checked disabled />
                                        <?php else: ?>
                                            <input type="checkbox" name="visibility_users[]" value="<?= $memberId ?>"
                                                   class="checkbox checkbox-primary"
                                                   <?= $isChecked ? 'checked' : '' ?> />
                                        <?php endif; ?>
                                        <div class="flex-1">
                                            <div class="font-semibold flex items-center gap-2">
                                                <?= htmlspecialchars($member['name']) ?>
                                                <?php if ($isRegistered): ?>
                                                    <span class="badge badge-success badge-sm">Registered</span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="text-sm text-base-content/70"><?= htmlspecialchars($member['email']) ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <label class="label">
                        <span class="label-text-alt">Registered members stay visible and cannot be revoked. Unchecked members will not see this event.</span>
                    </label>
                </div>

                <div class="divider">Event Dates</div>

                <div id="event-dates-container">
                    <?php
                    $dates = !empty($old['dates']) ? array_values($old['dates']) : [[
                        'date_type' => 'competition',
                        'description' => '',
                        'start_datetime' => '',
                        'end_datetime' => '',
                    ]];
                    foreach ($dates as $i => $date):
                        $dateType = $date['date_type'] ?? 'competition';
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
                                    <label class="label"><span class="label-text">Date Type</span></label>
                                    <select name="dates[<?= $i ?>][date_type]" class="select select-bordered" required>
                                        <?php foreach ($dateTypeOptions as $value => $label): ?>
                                            <option value="<?= h($value) ?>" <?= $dateType === $value ? 'selected' : '' ?>>
                                                <?= h($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text">Description</span></label>
                                    <input type="text" name="dates[<?= $i ?>][description]" class="input input-bordered"
                                           value="<?= htmlspecialchars($date['description'] ?? '') ?>" placeholder="Optional description">
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text">Start Date & Time</span></label>
                                    <input type="datetime-local" name="dates[<?= $i ?>][start_datetime]" class="input input-bordered"
                                           value="<?= htmlspecialchars((string)($date['start_datetime'] ?? '')) ?>" required>
                                </div>
                                <div class="form-control">
                                    <label class="label"><span class="label-text">End Date & Time</span></label>
                                    <input type="datetime-local" name="dates[<?= $i ?>][end_datetime]" class="input input-bordered"
                                           value="<?= htmlspecialchars((string)($date['end_datetime'] ?? '')) ?>" required>
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
                    <?php
                    $tags = !empty($old['tags']) ? array_values($old['tags']) : [['name' => '', 'color' => '#3b82f6']];
                    foreach ($tags as $i => $tag):
                    ?>
                        <div class="event-tag-item flex gap-2 items-end mb-3">
                            <div class="form-control flex-1">
                                <label class="label"><span class="label-text">Tag Name</span></label>
                                <input type="text" name="tags[<?= $i ?>][name]" class="input input-bordered"
                                       value="<?= htmlspecialchars($tag['name'] ?? '') ?>" placeholder="e.g., Interested, Registered">
                            </div>
                            <div class="form-control w-32">
                                <label class="label"><span class="label-text">Color</span></label>
                                <input type="color" name="tags[<?= $i ?>][color]" class="input input-bordered h-12"
                                       value="<?= htmlspecialchars($tag['color'] ?? '#3b82f6') ?>">
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

                <div class="flex gap-3">
                    <a href="/event/view?id=<?= htmlspecialchars($encodedId) ?>" class="btn btn-ghost flex-1">Cancel</a>
                    <button type="submit" class="btn btn-primary flex-1">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let dateIndex = <?= count($dates) ?>;
let tagIndex = <?= count($tags) ?>;
const lockedVisibilityIds = <?= js_json(array_values($registeredUserIds)) ?>;

function getSelectedVisibilityIds() {
    const container = document.getElementById('team-members-container');
    try {
        return JSON.parse(container.dataset.selected || '[]');
    } catch (e) {
        return [];
    }
}

function getLockedVisibilityIds() {
    const container = document.getElementById('team-members-container');
    try {
        return JSON.parse(container.dataset.locked || '[]');
    } catch (e) {
        return lockedVisibilityIds;
    }
}

function rememberSelectedVisibility() {
    const locked = getLockedVisibilityIds();
    const checked = Array.from(document.querySelectorAll('input[name="visibility_users[]"]'))
        .filter(el => el.type === 'hidden' || el.checked)
        .map(el => parseInt(el.value, 10));
    const merged = Array.from(new Set([...checked, ...locked]));
    document.getElementById('team-members-container').dataset.selected = JSON.stringify(merged);
}

function renderMemberCheckbox(member, selected, locked) {
    const memberId = parseInt(member.id, 10);
    const isLocked = locked.includes(memberId);
    const isChecked = isLocked || selected.includes(memberId);

    const label = document.createElement('label');
    label.className = isLocked
        ? 'flex items-center gap-3 p-2 rounded'
        : 'flex items-center gap-3 cursor-pointer hover:bg-base-200 p-2 rounded';

    if (isLocked) {
        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'visibility_users[]';
        hidden.value = String(memberId);
        label.appendChild(hidden);
    }

    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.className = 'checkbox checkbox-primary';
    checkbox.checked = isChecked;
    if (isLocked) {
        checkbox.disabled = true;
    } else {
        checkbox.name = 'visibility_users[]';
        checkbox.value = String(memberId);
    }
    label.appendChild(checkbox);

    const textWrap = document.createElement('div');
    textWrap.className = 'flex-1';

    const nameRow = document.createElement('div');
    nameRow.className = 'font-semibold flex items-center gap-2';
    const nameEl = document.createElement('span');
    nameEl.textContent = member.name ?? '';
    nameRow.appendChild(nameEl);
    if (isLocked) {
        const badge = document.createElement('span');
        badge.className = 'badge badge-success badge-sm';
        badge.textContent = 'Registered';
        nameRow.appendChild(badge);
    }

    const emailEl = document.createElement('div');
    emailEl.className = 'text-sm text-base-content/70';
    emailEl.textContent = member.email ?? '';

    textWrap.appendChild(nameRow);
    textWrap.appendChild(emailEl);
    label.appendChild(textWrap);
    return label;
}

document.getElementById('team-members-container').addEventListener('change', function(e) {
    if (e.target && e.target.name === 'visibility_users[]') {
        rememberSelectedVisibility();
    }
});

document.getElementById('team_id').addEventListener('change', function() {
    const teamId = this.value;
    const container = document.getElementById('team-members-container');
    const selected = getSelectedVisibilityIds();
    const locked = getLockedVisibilityIds();

    if (!teamId) {
        container.innerHTML = '<p class="text-base-content/50 text-sm">Select a team to see available members</p>';
        return;
    }

    fetch('/api/team-members?team_id=' + encodeURIComponent(teamId))
        .then(response => response.json())
        .then(data => {
            const list = document.createElement('div');
            list.className = 'space-y-2';

            if (data.success && data.members.length > 0) {
                data.members.forEach(member => {
                    list.appendChild(renderMemberCheckbox(member, selected, locked));
                });
                // Keep locked registered users even if not in new team list
                const listedIds = data.members.map(m => parseInt(m.id, 10));
                locked.forEach(lockedId => {
                    if (!listedIds.includes(lockedId)) {
                        const hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'visibility_users[]';
                        hidden.value = String(lockedId);
                        list.appendChild(hidden);
                    }
                });
                container.replaceChildren(list);
                rememberSelectedVisibility();
            } else {
                const empty = document.createElement('p');
                empty.className = 'text-base-content/50 text-sm mb-2';
                empty.textContent = 'No members found in this team';
                list.appendChild(empty);
                locked.forEach(lockedId => {
                    const hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = 'visibility_users[]';
                    hidden.value = String(lockedId);
                    list.appendChild(hidden);
                });
                container.replaceChildren(list);
            }
        })
        .catch(() => {
            const err = document.createElement('p');
            err.className = 'text-error text-sm';
            err.textContent = 'Error loading members';
            container.replaceChildren(err);
        });
});

document.getElementById('edit-event-form').addEventListener('submit', function () {
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
    document.getElementById('visibility_json').value = JSON.stringify(getSelectedVisibilityIds());
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
                <label class="label"><span class="label-text">Date Type</span></label>
                <select name="dates[${dateIndex}][date_type]" class="select select-bordered" required>
                    <option value="competition">Competition</option>
                    <option value="registration_deadline">Registration Deadline</option>
                    <option value="meeting">Meeting</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">Description</span></label>
                <input type="text" name="dates[${dateIndex}][description]" class="input input-bordered" placeholder="Optional description">
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">Start Date & Time</span></label>
                <input type="datetime-local" name="dates[${dateIndex}][start_datetime]" class="input input-bordered" required>
            </div>
            <div class="form-control">
                <label class="label"><span class="label-text">End Date & Time</span></label>
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
            <label class="label"><span class="label-text">Tag Name</span></label>
            <input type="text" name="tags[${tagIndex}][name]" class="input input-bordered" placeholder="e.g., Interested, Registered">
        </div>
        <div class="form-control w-32">
            <label class="label"><span class="label-text">Color</span></label>
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

<?php
$content = ob_get_clean();
include_once __DIR__ . '/../../templates/layout.php';
