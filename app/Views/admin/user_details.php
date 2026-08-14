<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array<string, mixed> $user */ ?>
<?php /** @var array{type:string, data:array<string, mixed>}|null $linked */ ?>
<section aria-labelledby="user-details-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(base_url('/admin/area')) ?>">Administration</a></li>
            <li class="breadcrumb-item active" aria-current="page">User details</li>
        </ol>
    </nav>

    <h1 id="user-details-heading" class="h3">User details</h1>
    <p class="lead">Account details for <strong><?= e($user['username']) ?></strong>.</p>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 card-title">Account</h2>
                    <dl class="row mb-0 small">
                        <dt class="col-5">Username</dt>
                        <dd class="col-7"><?= e($user['username']) ?></dd>
                        <dt class="col-5">Email</dt>
                        <dd class="col-7"><?= e($user['email']) ?></dd>
                        <dt class="col-5">Role</dt>
                        <dd class="col-7">
                            <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'healthcare_staff' ? 'success' : 'primary') ?>">
                                <?= e($user['role_name'] ?? $user['role']) ?>
                            </span>
                        </dd>
                        <dt class="col-5">Status</dt>
                        <dd class="col-7">
                            <span class="badge bg-<?= $user['is_active'] ? 'success' : 'danger' ?>">
                                <?= $user['is_active'] ? 'Active' : 'Deactivated' ?>
                            </span>
                        </dd>
                        <dt class="col-5">Force password change</dt>
                        <dd class="col-7"><?= $user['must_change_password'] ? 'Yes' : 'No' ?></dd>
                        <dt class="col-5">Failed logins</dt>
                        <dd class="col-7"><?= (int) $user['failed_login_attempts'] ?></dd>
                        <dt class="col-5">Locked</dt>
                        <dd class="col-7"><?= $user['locked_until'] !== null ? e($user['locked_until']) : 'No' ?></dd>
                        <dt class="col-5">Last login</dt>
                        <dd class="col-7"><?= $user['last_login_at'] !== null ? e($user['last_login_at']) : 'Never' ?></dd>
                        <dt class="col-5">Registered</dt>
                        <dd class="col-7"><?= e($user['created_at']) ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 card-title">
                        <?= $linked !== null && $linked['type'] === 'staff' ? 'Staff profile' : 'Student profile' ?>
                    </h2>

                    <?php if ($linked === null): ?>
                        <p class="small text-muted mb-0">No linked student or staff profile.</p>
                    <?php elseif ($linked['type'] === 'staff'): ?>
                        <?php $staff = $linked['data']; ?>
                        <dl class="row mb-0 small">
                            <dt class="col-5">Name</dt>
                            <dd class="col-7"><?= e(trim(($staff['title'] ?? '') . ' ' . $staff['first_name'] . ' ' . $staff['last_name'])) ?></dd>
                            <dt class="col-5">Staff ID</dt>
                            <dd class="col-7"><?= e($staff['staff_id']) ?></dd>
                            <dt class="col-5">Role</dt>
                            <dd class="col-7"><?= e($staff['role_name']) ?></dd>
                            <dt class="col-5">Specialization</dt>
                            <dd class="col-7"><?= e($staff['specialization']) ?></dd>
                            <dt class="col-5">Department</dt>
                            <dd class="col-7"><?= e($staff['department']) ?></dd>
                        </dl>
                    <?php else: ?>
                        <?php $student = $linked['data']; ?>
                        <dl class="row mb-0 small">
                            <dt class="col-5">Name</dt>
                            <dd class="col-7"><?= e($student['last_name'] . ', ' . $student['first_name']) ?></dd>
                            <dt class="col-5">Reg number</dt>
                            <dd class="col-7"><?= e($student['reg_number']) ?></dd>
                            <dt class="col-5">Department</dt>
                            <dd class="col-7"><?= e($student['department']) ?></dd>
                            <dt class="col-5">Faculty</dt>
                            <dd class="col-7"><?= e($student['faculty']) ?></dd>
                            <dt class="col-5">Level</dt>
                            <dd class="col-7"><?= e($student['level_of_study']) ?></dd>
                            <dt class="col-5">Phone</dt>
                            <dd class="col-7"><?= e($student['phone']) ?></dd>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="h5 card-title">Account management</h2>
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
                    <a href="<?= e(base_url('/admin/area')) ?>" class="btn btn-sm btn-outline-secondary">&larr; Back to administration</a>
                </div>
            </div>
        </div>
    </div>
</section>
