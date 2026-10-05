<header class="navbar bg-base-100 shadow-lg px-2 sm:px-4">
    <div class="container mx-auto flex items-center gap-1 w-full min-w-0">
        <div class="flex-1 min-w-0">
            <a href="/" class="btn btn-ghost text-lg sm:text-xl gap-2 px-1 sm:px-2 normal-case">
                <img src="/assets/logo.png" alt="Team Comp" class="h-8 w-8 rounded-full object-cover shrink-0" />
                <span class="truncate">Team Comp</span>
            </a>
        </div>

        <!-- Desktop nav -->
        <ul class="menu menu-horizontal px-1 hidden lg:flex flex-nowrap">
            <li><a href="/">Home</a></li>
            <li><a href="/team/manage">Teams</a></li>
            <li><a href="/event">Events</a></li>
            <li><a href="/event/calendar">Calendar</a></li>
            <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
            <li><a href="/activity">Activity</a></li>
            <?php endif; ?>
        </ul>

        <div class="flex-none flex items-center gap-1 sm:gap-2">
            <!-- Mobile nav -->
            <div class="dropdown dropdown-end lg:hidden">
                <div tabindex="0" role="button" class="btn btn-ghost btn-square" aria-label="Open menu">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </div>
                <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[20] p-2 shadow bg-base-100 rounded-box w-52">
                    <li><a href="/">Home</a></li>
                    <li><a href="/team/manage">Teams</a></li>
                    <li><a href="/event">Events</a></li>
                    <li><a href="/event/calendar">Calendar</a></li>
                    <?php if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'): ?>
                    <li><a href="/activity">Activity</a></li>
                    <?php endif; ?>
                    <?php if (!isset($_SESSION['user'])): ?>
                    <li><a href="/auth/login">Login</a></li>
                    <li><a href="/auth/register">Register</a></li>
                    <?php endif; ?>
                </ul>
            </div>

            <label class="swap swap-rotate">
                <input type="checkbox" id="theme-toggle" />
                <svg class="swap-off fill-current w-5 h-5 sm:w-6 sm:h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M5.64,17l-.71.71a1,1,0,0,0,0,1.41,1,1,0,0,0,1.41,0l.71-.71A1,1,0,0,0,5.64,17ZM5,12a1,1,0,0,0-1-1H3a1,1,0,0,0,0,2H4A1,1,0,0,0,5,12Zm7-7a1,1,0,0,0,1-1V3a1,1,0,0,0-2,0V4A1,1,0,0,0,12,5ZM5.64,7.05a1,1,0,0,0,.7.29,1,1,0,0,0,.71-.29,1,1,0,0,0,0-1.41l-.71-.71A1,1,0,0,0,4.93,6.34Zm12,.29a1,1,0,0,0,.7-.29l.71-.71a1,1,0,1,0-1.41-1.41L17,5.64a1,1,0,0,0,0,1.41A1,1,0,0,0,17.66,7.34ZM21,11H20a1,1,0,0,0,0,2h1a1,1,0,0,0,0-2Zm-9,8a1,1,0,0,0-1,1v1a1,1,0,0,0,2,0V20A1,1,0,0,0,12,19ZM18.36,17A1,1,0,0,0,17,18.36l.71.71a1,1,0,0,0,1.41,0,1,1,0,0,0,0-1.41ZM12,6.5A5.5,5.5,0,1,0,17.5,12,5.51,5.51,0,0,0,12,6.5Zm0,9A3.5,3.5,0,1,1,15.5,12,3.5,3.5,0,0,1,12,15.5Z"/></svg>
                <svg class="swap-on fill-current w-5 h-5 sm:w-6 sm:h-6" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M21.64,13a1,1,0,0,0-1.05-.14,8.05,8.05,0,0,1-3.37.73A8.15,8.15,0,0,1,9.08,5.49a8.59,8.59,0,0,1,.25-2A1,1,0,0,0,8,2.36,10.14,10.14,0,1,0,22,14.05,1,1,0,0,0,21.64,13Zm-9.5,6.69A8.14,8.14,0,0,1,7.08,5.22v.27A10.15,10.15,0,0,0,17.22,15.63a9.79,9.79,0,0,0,2.1-.22A8.11,8.11,0,0,1,12.14,19.73Z"/></svg>
            </label>

            <?php if (isset($_SESSION['user'])): ?>
            <div class="dropdown dropdown-end">
                <div tabindex="0" role="button" class="btn btn-ghost btn-circle avatar">
                    <div class="w-9 sm:w-10 rounded-full">
                        <?php if (!empty($_SESSION['user']['avatar_url'])): ?>
                        <img alt="User Avatar" src="<?= htmlspecialchars($_SESSION['user']['avatar_url']) ?>" />
                        <?php else: ?>
                        <div class="bg-primary text-primary-content flex items-center justify-center h-full w-full text-lg font-bold">
                            <?= htmlspecialchars(strtoupper(substr((string)($_SESSION['user']['name'] ?? 'U'), 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <ul tabindex="0" class="menu menu-sm dropdown-content mt-3 z-[20] p-2 shadow bg-base-100 rounded-box w-52">
                    <li class="menu-title px-4 py-2">
                        <span class="text-sm font-bold"><?= htmlspecialchars($_SESSION['user']['name'] ?? 'User') ?></span>
                    </li>
                    <li><a href="/settings">Settings</a></li>
                    <li><a href="/team/manage">Manage Team</a></li>
                    <li><a href="/team/join">Join Team</a></li>
                    <li class="border-t border-base-300 mt-2 pt-2">
                        <form method="POST" action="/auth/logout" class="px-0">
                            <?php include __DIR__ . '/components/csrf.php'; ?>
                            <button type="submit" class="text-error w-full text-left">Logout</button>
                        </form>
                    </li>
                </ul>
            </div>
            <?php else: ?>
            <a href="/auth/login" class="btn btn-primary btn-sm hidden sm:inline-flex">Login</a>
            <?php endif; ?>
        </div>
    </div>
</header>
