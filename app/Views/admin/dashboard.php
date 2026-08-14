<?php /** @var string $title */ ?>
<?php /** @var array $stats */ ?>
<?php /** @var array $users */ ?>
<?php /** @var array $recentAuditLogs */ ?>
<section aria-labelledby="admin-heading">
    <h1 id="admin-heading" class="h3">Administration</h1>
    <p class="lead">This area is restricted to the <strong>Administrator</strong> role.</p>

    <!-- System statistics -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary o-h-20">
                <div class="card-body">
                    <h2 class="card-title"><?= isset($stats['total_users']) ? number_format($stats['total_users']) : '0' ?></h2>
                    <p class="card-text">Total Users</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success o-h-20">
                <div class="card-body">
                    <h2 class="card-title"><?= isset($stats['active_users']) ? number_format($stats['active_users']) : '0' ?></h2>
                    <p class="card-text">Active Users</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info o-h-20">
                <div class="card-body">
                    <h2 class="card-title"><?= isset($stats['total_students']) ? number_format($stats['total_students']) : '0' ?></h2>
                    <p class="card-text">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning o-h-20">
                <div class="card-body">
                    <h2 class="card-title"><?= isset($stats['total_staff']) ? number_format($stats['total_staff']) : '0' ?></h2>
                    <p class="card-text">Total Staff</p>
                </div>
            </div>
        </div>
    </div>

    <!-- User management -->
    <div class="card mb-4" aria-labelledby="user-management-heading">
        <div class="card-body">
            <h2 id="user-management-heading" class="h5 card-title">User Management</h2>
            <p class="small text-muted">
                View and manage all user accounts.
            </p>
            <div class="table-responsive">
                <table class="table table-striped small">
                    <thead>
                        <tr>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><?= e($user['username']) ?></td>
                            <td><?= e($user['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'healthcare_staff' ? 'success' : 'primary') ?>">
                                    <?= e($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $user['is_active'] ? 'Active' : 'Deactivated' ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <a href="<?= e(base_url('/admin/user/' . (int) $user['id'])) ?>" class="btn btn-sm btn-outline-primary">Details</a>
                                    <?php if ($user['is_active']): ?>
                                        <form method="post" action="<?= e(base_url('/admin/user/' . (int) $user['id'] . '/deactivate')) ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Deactivate this user account?">Deactivate</button>
                                        </form>
                                    <?php else: ?>
                                        <form method="post" action="<?= e(base_url('/admin/user/' . (int) $user['id'] . '/activate')) ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success">Activate</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Role management -->
    <div class="card mb-4" aria-labelledby="role-management-heading">
        <div class="card-body">
            <h2 id="role-management-heading" class="h5 card-title">Role Management</h2>
            <p class="small text-muted">
                Manage roles and permissions.
            </p>
            <p class="text-muted small">
                Administrators have full system access. Healthcare staff can access authorized student records.
                Students can view their own profile and health records.
            </p>
        </div>
    </div>

    <!-- Audit log access -->
    <div class="card mb-4" aria-labelledby="audit-access-heading">
        <div class="card-body">
            <h2 id="audit-access-heading" class="h5 card-title">Audit Log Access</h2>
            <p class="small text-muted">
                Read-only record of security and record-management events.
            </p>
            <a href="<?= e(base_url('/admin/audit')) ?>" class="btn btn-link btn-sm">View audit log</a>
        </div>
    </div>

    <!-- Security events -->
    <div class="card mb-4" aria-labelledby="security-events-heading">
        <div class="card-body">
            <h2 id="security-events-heading" class="h5 card-title">Security Events</h2>
            <p class="small text-muted">
                Security-related events requiring administrator attention.
            </p>
            <p class="text-muted small">
                No critical security events at this time.
            </p>
        </div>
    </div>

    <!-- System configuration -->
    <div class="card mb-4" aria-labelledby="system-config-heading">
        <div class="card-body">
            <h2 id="system-config-heading" class="h5 card-title">System Configuration</h2>
            <p class="small text-muted">
                Core system settings.
            </p>
            <dl class="row">
                <dt class="col-4">Application name</dt>
                <dd class="col-8"><?= e(config('app.name')) ?></dd>
                <dt class="col-4">Environment</dt>
                <dd class="col-8"><?= e(config('app.env')) ?></dd>
                <dt class="col-4">Debug mode</dt>
                <dd class="col-8"><?= config('app.debug') ? 'Enabled' : 'Disabled' ?></dd>
            </dl>
        </div>
    </div>
</section>