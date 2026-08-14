<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array<int, array<string, mixed>> $appointments */ ?>
<?php /** @var bool $canManage */ ?>
<?php /** @var bool $canApprove */ ?>
<?php /** @var string $currentStatus */ ?>
<?php /** @var int $total */ ?>
<?php /** @var int $page */ ?>
<?php /** @var int $pages */ ?>
<?php $statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'completed' => 'Completed', 'no_show' => 'No show']; ?>
<section aria-labelledby="appointments-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page">Appointments</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h1 id="appointments-heading" class="h3 mb-0">Appointments</h1>
        <div class="btn-group" role="group" aria-label="Appointment actions">
            <a class="btn btn-sm btn-primary" href="<?= e(base_url('/appointments/new')) ?>">Request appointment</a>
            <?php if ($canManage): ?>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('/appointments/calendar')) ?>">View calendar</a>
            <?php endif; ?>
        </div>
    </div>
    <p class="lead"><?= $canManage ? 'Manage all appointment requests and schedules.' : 'View and manage your clinic appointments.' ?></p>

    <?php if ($canManage): ?>
        <form method="get" action="<?= e(base_url('/appointments')) ?>" class="row g-2 mb-3">
            <div class="col-auto">
                <label for="status" class="visually-hidden">Filter by status</label>
                <select class="form-select form-select-sm" id="status" name="status">
                    <option value=""<?= $currentStatus === '' ? ' selected' : '' ?>>All statuses</option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= e($key) ?>"<?= $currentStatus === $key ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-outline-primary">Filter</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if ($appointments === []): ?>
        <div class="alert alert-secondary" role="alert">No appointments found.</div>
    <?php else: ?>
        <div class="table-responsive" tabindex="0">            <table class="table table-hover align-middle">
                <caption class="visually-hidden">List of appointments</caption>
                <thead>
                    <tr>
                        <?php if ($canManage): ?>
                            <th scope="col">Student</th>
                        <?php endif; ?>
                        <th scope="col">Staff member</th>
                        <th scope="col">Scheduled</th>
                        <th scope="col">Duration</th>
                        <th scope="col">Reason</th>
                        <th scope="col">Status</th>
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($appointments as $appt): ?>
                        <tr>
                            <?php if ($canManage): ?>
                                <th scope="row"><?= e($appt['student_last'] . ', ' . $appt['student_first']) ?></th>
                            <?php endif; ?>
                            <td><?= e(trim(($appt['staff_title'] ?? '') . ' ' . $appt['staff_first'] . ' ' . $appt['staff_last'])) ?></td>
                            <td><?= e(date('D, j M Y H:i', strtotime((string) $appt['scheduled_at']))) ?></td>
                            <td><?= e((string) $appt['duration_minutes']) ?> min</td>
                            <td><?= e($appt['reason']) ?></td>
                            <td>
                                <span class="badge text-bg-<?= $appt['status'] === 'approved' ? 'success' : ($appt['status'] === 'pending' ? 'warning' : ($appt['status'] === 'rejected' ? 'danger' : ($appt['status'] === 'cancelled' ? 'secondary' : 'info'))) ?>">
                                    <span class="visually-hidden">Status: </span><?= e($statusLabels[$appt['status']] ?? $appt['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if (in_array($appt['status'], ['pending', 'approved'], true)): ?>
                                        <form method="post" action="<?= e(base_url('/appointments/' . (int) $appt['id'] . '/cancel')) ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                                            <label for="cancellation_reason_<?= (int) $appt['id'] ?>" class="visually-hidden">Cancellation reason</label>
                                            <input type="text" id="cancellation_reason_<?= (int) $appt['id'] ?>" name="cancellation_reason" class="form-control form-control-sm mb-1" maxlength="255" placeholder="Reason (optional)">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Cancel this appointment?">Cancel</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if ($appt['status'] === 'pending' && $canApprove): ?>
                                        <form method="post" action="<?= e(base_url('/appointments/' . (int) $appt['id'] . '/approve')) ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                                            <input type="hidden" name="admin_notes" value="">
                                            <button type="submit" class="btn btn-sm btn-success" data-confirm="Approve this appointment?">Approve</button>
                                        </form>
                                        <form method="post" action="<?= e(base_url('/appointments/' . (int) $appt['id'] . '/reject')) ?>" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                                            <label for="reject_notes_<?= (int) $appt['id'] ?>" class="visually-hidden">Rejection reason</label>
                                            <input type="text" id="reject_notes_<?= (int) $appt['id'] ?>" name="admin_notes" class="form-control form-control-sm mb-1" maxlength="255" placeholder="Reason (optional)">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Reject this appointment?">Reject</button>
                                        </form>
                                    <?php endif; ?>

                                    <?php if (in_array($appt['status'], ['pending', 'approved'], true)): ?>
                                        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/appointments/' . (int) $appt['id'] . '/reschedule')) ?>">Reschedule</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav aria-label="Appointment pages">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <?php $qs = http_build_query(array_filter(['status' => $currentStatus]) + ['page' => $i]); ?>
                        <li class="page-item<?= $i === $page ? ' active' : '' ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>>
                            <a class="page-link" href="<?= e(base_url('/appointments?' . $qs)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <p class="mt-3">
        <a href="<?= e(base_url('/appointments/new')) ?>" class="btn btn-outline-primary">Request a new appointment</a>
    </p>
</section>
