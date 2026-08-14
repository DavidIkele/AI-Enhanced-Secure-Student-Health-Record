<?php $current = $page ?? ''; ?>
<?php $auth = new \App\Services\AuthService(); ?>
<?php $signedIn = $auth->check(); ?>
<?php if ($signedIn) { $navUserId = $auth->id(); $isAdmin = \App\Security\AccessControl::hasRole((int) $navUserId, 'admin'); $canViewRecords = \App\Security\AccessControl::can((int) $navUserId, 'records.view.any'); $canViewAnalytics = \App\Security\AccessControl::can((int) $navUserId, 'analytics.view'); $navCanDashboard = \App\Security\AccessControl::can((int) $navUserId, 'records.manage') || \App\Security\AccessControl::can((int) $navUserId, 'analytics.view'); $navUnread = (int) (new \App\Repositories\NotificationRepository())->countUnread((int) $navUserId); } ?>
<nav class="site-nav" aria-label="Primary">
    <ul class="nav flex-wrap gap-1">
        <li class="nav-item">
            <a class="nav-link px-2 py-1<?= $current === 'home' ? ' active' : '' ?>" <?= $current === 'home' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/')) ?>">Home</a>
        </li>
        <li class="nav-item">
            <a class="nav-link px-2 py-1<?= $current === 'system' ? ' active' : '' ?>" href="<?= e(base_url('/system/health')) ?>">System health</a>
        </li>
        <?php if ($signedIn): ?>
            <?php if ($navCanDashboard): ?>
                <li class="nav-item">
                    <a class="nav-link px-2 py-1<?= $current === 'dashboard' ? ' active' : '' ?>" <?= $current === 'dashboard' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/dashboard')) ?>">Dashboard</a>
                </li>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
                <li class="nav-item">
                    <a class="nav-link px-2 py-1<?= $current === 'admin' ? ' active' : '' ?>" <?= $current === 'admin' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/admin/area')) ?>">Administration</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-2 py-1<?= $current === 'admin-audit' ? ' active' : '' ?>" <?= $current === 'admin-audit' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/admin/audit')) ?>">Audit log</a>
                </li>
            <?php endif; ?>
            <?php if ($canViewRecords): ?>
                <li class="nav-item">
                    <a class="nav-link px-2 py-1<?= $current === 'records' ? ' active' : '' ?>" <?= $current === 'records' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/records')) ?>">Health records</a>
                </li>
            <?php endif; ?>
            <?php if ($canViewAnalytics): ?>
                <li class="nav-item">
                    <a class="nav-link px-2 py-1<?= $current === 'analytics' ? ' active' : '' ?>" <?= $current === 'analytics' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/analytics/visits')) ?>">Analytics</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link px-2 py-1<?= $current === 'outbreaks' ? ' active' : '' ?>" <?= $current === 'outbreaks' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/analytics/outbreaks')) ?>">Outbreak alerts</a>
                </li>
            <?php endif; ?>
            <li class="nav-item">
                <a class="nav-link px-2 py-1<?= $current === 'appointments' ? ' active' : '' ?>" <?= $current === 'appointments' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/appointments')) ?>">Appointments</a>
            </li>
            <li class="nav-item">
                <a class="nav-link px-2 py-1<?= $current === 'notifications' ? ' active' : '' ?>" <?= $current === 'notifications' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/notifications')) ?>">
                    Notifications
                    <?php if (!empty($navUnread) && $navUnread > 0): ?>
                        <span class="badge text-bg-danger ms-1"><span class="visually-hidden">Unread notifications: </span><?= $navUnread ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link px-2 py-1<?= $current === 'profile' ? ' active' : '' ?>" <?= $current === 'profile' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/profile')) ?>">My profile</a>
            </li>
            <li class="nav-item">
                <form method="post" action="<?= e(base_url('/auth/logout')) ?>" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                    <button type="submit" class="btn btn-link nav-link px-2 py-1">Sign out</button>
                </form>
            </li>
        <?php else: ?>
            <li class="nav-item">
                <a class="nav-link px-2 py-1<?= $current === 'login' ? ' active' : '' ?>" <?= $current === 'login' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/auth/login')) ?>">Sign in</a>
            </li>
            <li class="nav-item">
                <a class="nav-link px-2 py-1<?= $current === 'register' ? ' active' : '' ?>" <?= $current === 'register' ? 'aria-current="page"' : '' ?> href="<?= e(base_url('/auth/register')) ?>">Register</a>
            </li>
        <?php endif; ?>
    </ul>
</nav>
