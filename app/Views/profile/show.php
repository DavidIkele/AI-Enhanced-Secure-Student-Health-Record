<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array $student */ ?>
<?php /** @var array $user */ ?>
<?php /** @var bool $canManage */ ?>
<?php /** @var array $insights */ ?>
<?php /** @var array $upcoming */ ?>
<?php /** @var array<string, int> $preferences */ ?>
<?php $statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'completed' => 'Completed', 'no_show' => 'No show']; ?>
<section aria-labelledby="profile-heading">
    <h1 id="profile-heading" class="h3 mb-1">My Profile</h1>
    <p class="lead">Your personal details as recorded in the Student Health Record System.</p>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-primary" href="<?= e(base_url('/profile/edit')) ?>">Edit profile</a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('/appointments/new')) ?>">Request appointment</a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('/appointments')) ?>">All appointments</a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('/notifications')) ?>">Notifications</a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('/profile/preferences')) ?>">Notification preferences</a>
        <a class="btn btn-outline-secondary" href="<?= e(base_url('/profile/data-export')) ?>">Download my data</a>
    </div>

    <div class="row g-4">
        <div class="col-lg-7">
            <h2 class="h5">Personal details</h2>
            <dl class="row">
                <dt class="col-4">Registration number</dt>
                <dd class="col-8"><?= e($student['reg_number']) ?></dd>

                <dt class="col-4">Full name</dt>
                <dd class="col-8"><?= e(trim($student['last_name'] . ' ' . $student['first_name'] . ' ' . $student['other_names'])) ?></dd>

                <dt class="col-4">Date of birth</dt>
                <dd class="col-8"><?= $student['date_of_birth'] !== null ? e($student['date_of_birth']) : 'Not provided' ?></dd>

                <dt class="col-4">Gender</dt>
                <dd class="col-8"><?= $student['gender'] !== null ? e($student['gender']) : 'Not provided' ?></dd>

                <dt class="col-4">Faculty</dt>
                <dd class="col-8"><?= e($student['faculty']) ?></dd>

                <dt class="col-4">Department</dt>
                <dd class="col-8"><?= e($student['department']) ?></dd>

                <dt class="col-4">Level</dt>
                <dd class="col-8"><?= e($student['level_of_study']) ?></dd>

                <dt class="col-4">Email</dt>
                <dd class="col-8"><?= e($student['email']) ?></dd>

                <dt class="col-4">Phone</dt>
                <dd class="col-8"><?= e($student['phone']) ?></dd>

                <dt class="col-4">Emergency contact</dt>
                <dd class="col-8"><?= e($student['emergency_contact_name'] . ' (' . $student['emergency_contact_phone'] . ')') ?></dd>
            </dl>

            <h2 class="h5 mt-4">Account</h2>
            <dl class="row">
                <dt class="col-4">Username</dt>
                <dd class="col-8"><?= e($user['username'] ?? '') ?></dd>
                <dt class="col-4">Last sign-in</dt>
                <dd class="col-8">
                    <?php if (!empty($user['last_login_at'])): ?>
                        <?= e(date('D, j M Y H:i', strtotime((string) $user['last_login_at']))) ?>
                    <?php else: ?>
                        <span class="text-muted">No sign-in recorded yet</span>
                    <?php endif; ?>
                </dd>
                <dt class="col-4">Change password</dt>
                <dd class="col-8">
                    <a href="<?= e(base_url('/profile/edit#new_password')) ?>">Update your password</a>
                </dd>
            </dl>
        </div>

        <div class="col-lg-5">
            <div class="card mb-4" aria-labelledby="upcoming-heading">
                <div class="card-body">
                    <h2 id="upcoming-heading" class="h5 card-title">Upcoming appointments</h2>
                    <?php if ($upcoming === []): ?>
                        <p class="card-text text-muted mb-2">You have no upcoming appointments.</p>
                        <a class="btn btn-sm btn-primary" href="<?= e(base_url('/appointments/new')) ?>">Request appointment</a>
                    <?php else: ?>
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($upcoming as $appt): ?>
                                <li class="border-bottom py-2">
                                    <div class="d-flex justify-content-between gap-2">
                                        <strong><?= e(date('D, j M Y H:i', strtotime((string) $appt['scheduled_at']))) ?></strong>
                                        <span class="badge text-bg-<?= $appt['status'] === 'approved' ? 'success' : 'warning' ?>">
                                            <?= e($statusLabels[$appt['status']] ?? $appt['status']) ?>
                                        </span>
                                    </div>
                                    <div class="small text-muted">
                                        <?= e(trim(($appt['staff_title'] ?? '') . ' ' . $appt['staff_first'] . ' ' . $appt['staff_last'])) ?>
                                        &middot; <?= e((string) $appt['duration_minutes']) ?> min
                                    </div>
                                    <?php if (in_array($appt['status'], ['pending', 'approved'], true)): ?>
                                        <div class="mt-1">
                                            <form method="post" action="<?= e(base_url('/appointments/' . (int) $appt['id'] . '/cancel')) ?>" class="d-inline">
                                                <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                                                <button type="submit" class="btn btn-link btn-sm p-0" data-confirm="Cancel this appointment?">Cancel</button>
                                            </form>
                                            <a class="btn btn-link btn-sm p-0 ms-2" href="<?= e(base_url('/appointments/' . (int) $appt['id'] . '/reschedule')) ?>">Reschedule</a>
                                        </div>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card" aria-labelledby="prefs-heading">
                <div class="card-body">
                    <h2 id="prefs-heading" class="h5 card-title">Notification preferences</h2>
                    <p class="small text-muted mb-2">
                        Appointment updates:
                        <strong><?= ((int) ($preferences['notify_appointment_changes'] ?? 1) === 1) ? 'On' : 'Off' ?></strong>
                        &middot; Reminders:
                        <strong><?= ((int) ($preferences['appointment_reminder_opt_in'] ?? 1) === 1) ? 'On' : 'Off' ?></strong>
                    </p>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('/profile/preferences')) ?>">Manage preferences</a>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canManage): ?>
        <div class="alert alert-info mt-4" role="alert">
            You hold record management permissions. Visit the
            <a href="<?= e(base_url('/records')) ?>">student health records</a> area.
        </div>
    <?php endif; ?>

    <h2 class="h4 mt-5">Health insights</h2>
    <?php if ($insights === []): ?>
        <p class="text-muted">No health insights are available right now. Check back after your next clinic visit.</p>
    <?php else: ?>
        <p class="text-muted">These informational notes are generated from your own health records. They are not a
            diagnosis and never replace advice from a healthcare professional.</p>
        <?php foreach ($insights as $insight): ?>
            <article class="border rounded p-3 my-3" aria-label="<?= e($insight['title']) ?>">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <h3 class="h6 mb-1"><?= e($insight['title']) ?></h3>
                    <?php if (empty($insight['is_read'])): ?>
                        <span class="badge text-bg-primary">Unread</span>
                    <?php else: ?>
                        <span class="badge text-bg-secondary">Read</span>
                    <?php endif; ?>
                </div>
                <p class="mb-2"><?= e($insight['content']) ?></p>
                <div class="small text-muted">
                    Generated <?= e($insight['created_at']) ?>
                    <?php if (!empty($insight['data_version'])): ?>
                        &middot; version <?= e($insight['data_version']) ?>
                    <?php endif; ?>
                </div>
                <div class="mt-2 btn-group" role="group" aria-label="Insight actions">
                    <?php if (empty($insight['is_read'])): ?>
                        <form method="post" action="<?= e(base_url('/profile/insights/' . (int) $insight['id'] . '/read')) ?>" class="d-inline">
                            <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Mark as read</button>
                        </form>
                    <?php endif; ?>
                    <form method="post" action="<?= e(base_url('/profile/insights/' . (int) $insight['id'] . '/dismiss')) ?>" class="d-inline">
                        <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                        <button type="submit" class="btn btn-sm btn-outline-secondary">Dismiss</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Recent health-record activity -->
    <div class="card mt-4" aria-labelledby="activity-heading">
        <div class="card-body">
            <h2 id="activity-heading" class="h5 card-title">Recent clinic visits</h2>
            <?php
            $recentVisits = array_slice((array) $recentVisits, 0, 3);
            ?>
            <?php if ($recentVisits === []): ?>
                <p class="card-text text-muted mb-2">No clinic visits recorded.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0 small">
<?php foreach ($recentVisits as $visit): ?>
                        <li class="border-bottom py-1">
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <div>
                                    <strong><?= e(date('M j', strtotime((string) $visit['visited_at']))) ?></strong>
                                    <span class="text-muted"><?= e($visit['visit_type']) ?></span>
                                </div>
                                <div class="text-end">
                                    <small><?= e(trim(($visit['staff_title'] ?? '') . ' ' . $visit['staff_first'] . ' ' . $visit['staff_last'])) ?></small>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Relevant health statistics -->
    <div class="card mt-3" aria-labelledby="stats-heading">
        <div class="card-body">
            <h2 id="stats-heading" class="h5 card-title">Health summary</h2>
            <p class="small text-muted mb-3">
                Key health metrics from your records
            </p>
            <div class="row g-2">
                <!-- Vital signs summary would go here -->
                <!-- Allergies summary -->
                <div class="col-6">
                    <p class="mb-0 text-muted small">Allergies</p>
                    <p class="mb-0 font-monospace small">
                        <?= e($student['blood_group'] !== 'Unknown' ? $student['blood_group'] : 'Not recorded') ?>
                    </p>
                </div>
                <!-- Height/Weight -->
                <div class="col-6">
                    <p class="mb-0 text-muted small">Height/Weight</p>
                    <p class="mb-0 font-monospace small">
                        <?= e($student['height_cm'] !== null ? number_format($student['height_cm'], 1) . ' cm' : 'Not recorded') ?> /
                        <?= e($student['weight_kg'] !== null ? number_format($student['weight_kg'], 1) . ' kg' : 'Not recorded') ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <h2 class="h4 mt-5">Deactivate account</h2>
    <p class="text-muted">
        Deactivating your account removes your access immediately. Your health records and audit
        history are retained for compliance, and you can contact the health centre if you need
        help. This cannot be undone from this page.
    </p>
    <form method="post" action="<?= e(base_url('/profile/delete')) ?>" class="border border-danger rounded p-3" novalidate>
        <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
        <div class="form-check mb-3">
            <input type="checkbox" class="form-check-input" id="confirm_deactivate" name="confirm_deactivate" value="1" required>
            <label class="form-check-label" for="confirm_deactivate">
                I understand my account will be deactivated and I will be signed out.
            </label>
        </div>
        <button type="submit" class="btn btn-outline-danger" data-confirm="Deactivate your account? You will be signed out immediately.">Deactivate my account</button>
    </form>
</section>
