<?php /** @var string $title */ ?>
<?php /** @var array|null $user */ ?>
<section aria-labelledby="dashboard-heading">
    <h1 id="dashboard-heading" class="h3">Dashboard</h1>
    <p class="lead">
        Welcome back<?= $user !== null ? ', ' . e($user['username']) : '' ?>. You are signed in.
    </p>

    <!-- Dashboard statistics -->
    <div class="row g-4 mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary o-h-20">
                <div class="card-body">
                    <h2 class="card-title"><?= isset($stats['total_students']) ? number_format($stats['total_students']) : '0' ?></h2>
                    <p class="card-text">Total Students</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success o-h-20">
                <div class="card-body">
                    <h2 class="card-title"><?= isset($stats['total_appointments']) ? number_format($stats['total_appointments']) : '0' ?></h2>
                    <p class="card-text">Total Appointments</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info o-h-20">
                <div class="card-body">
                    <h2 class="card-title"><?= isset($stats['total_visits']) ? number_format($stats['total_visits']) : '0' ?></h2>
                    <p class="card-text">Total Clinic Visits</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning o-h-20">
                <div class="card-body">
                    <h2 class="card-title"><?= isset($stats['pending_appointments']) ? number_format($stats['pending_appointments']) : '0' ?></h2>
                    <p class="card-text">Pending Appointments</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Authorized student search -->
    <div class="card mb-4" aria-labelledby="student-search-heading">
        <div class="card-body">
            <h2 id="student-search-heading" class="h5 card-title">Authorized Student Search</h2>
            <p class="small text-muted">
                Search by registration number, name, or email to access a student's health records.
            </p>
            <form method="get" action="<?= e(base_url('/records')) ?>" class="row g-2">
                <div class="col-md-4">
                    <input type="text" class="form-control" name="reg_number" placeholder="Reg number" aria-label="Registration number">
                </div>
                <div class="col-md-4">
                    <input type="text" class="form-control" name="name" placeholder="Student name" aria-label="Student name">
                </div>
                <div class="col-md-4">
                    <input type="email" class="form-control" name="email" placeholder="Email" aria-label="Email">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Today's appointments -->
    <div class="card mb-4" aria-labelledby="today-appointments-heading">
        <div class="card-header">
            <h2 class="h5 mb-0" id="today-appointments-heading">Today's Appointments</h2>
        </div>
        <div class="card-body p-0">
            <?php
            $today = date('Y-m-d');
            $todayAppointments = isset($todayAppointments) ? $todayAppointments : [];
            if ($todayAppointments === []):
            ?>
                <p class="card-text text-muted pt-2">No appointments scheduled for today.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($todayAppointments as $appt): ?>
                        <li class="border-bottom py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= e(date('H:i', strtotime((string) $appt['scheduled_at']))) ?></strong>
                                    <span class="badge text-bg-<?= $appt['status'] === 'approved' ? 'success' : 'warning' ?>">
                                        <?= e($statusLabels[$appt['status']] ?? $appt['status']) ?>
                                    </span>
                                </div>
                                <div>
                                    <small><?= e(trim(($appt['staff_title'] ?? '') . ' ' . ($appt['staff_first'] ?? '') . ' ' . ($appt['staff_last'] ?? ''))) ?></small>
                                    &middot; <?= e((string) $appt['duration_minutes']) ?> min
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Pending appointments -->
    <div class="card mb-4" aria-labelledby="pending-appointments-heading">
        <div class="card-header">
            <h2 class="h5 mb-0" id="pending-appointments-heading">Pending Appointments</h2>
        </div>
        <div class="card-body p-0">
            <?php
            $pendingAppointments = isset($pendingAppointments) ? $pendingAppointments : [];
            $pendingCount = isset($pendingCount) ? $pendingCount : count($pendingAppointments);
            if ($pendingAppointments === []):
            ?>
                <p class="card-text text-muted pt-2">No pending appointments.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($pendingAppointments as $appt): ?>
                        <li class="border-bottom py-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= e(date('M j, H:i', strtotime((string) $appt['scheduled_at']))) ?></strong>
                                    <span class="badge text-bg-warning">Pending</span>
                                </div>
                                <div>
                                    <small><?= e(trim(($appt['staff_title'] ?? '') . ' ' . ($appt['staff_first'] ?? '') . ' ' . ($appt['staff_last'] ?? ''))) ?></small>
                                    &middot; <?= e((string) $appt['duration_minutes']) ?> min
                                    &middot; Reg: <?= e($appt['reg_number'] ?? 'N/A') ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                    <?php if ($pendingCount > count($pendingAppointments)): ?>
                        <li class="text-center text-muted small pt-2">
                            View all <?= $pendingCount ?> pending appointments
                            <a href="<?= e(base_url('/appointments')) ?>">→</a>
                        </li>
                    <?php endif; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent clinic visits -->
    <div class="card mb-4" aria-labelledby="recent-visits-heading">
        <div class="card-body">
            <h2 id="recent-visits-heading" class="h5 card-title">Recent Clinic Visits</h2>
            <?php
            $recentVisits = isset($recentVisits) ? array_slice($recentVisits, 0, 5) : [];
            if ($recentVisits === []):
            ?>
                <p class="card-text text-muted">No clinic visits recorded.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($recentVisits as $visit): ?>
                        <li class="border-bottom py-1">
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <div>
                                    <strong><?= e(date('M j, H:i', strtotime((string) $visit['visited_at']))) ?></strong>
                                    <span class="text-muted"><?= e($visit['visit_type']) ?></span>
                                </div>
                                <div class="text-end">
                                    <small><?= e(trim(($visit['staff_title'] ?? '') . ' ' . ($visit['staff_first'] ?? '') . ' ' . ($visit['staff_last'] ?? ''))) ?></small>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Health analytics -->
    <div class="card mb-4" aria-labelledby="health-analytics-heading">
        <div class="card-body">
            <h2 id="health-analytics-heading" class="h5 card-title">Health Analytics</h2>
            <p class="small text-muted">
                Aggregated visit data (individual identities are never displayed).
            </p>
            <div class="row g-3">
                <div class="col-6">
                    <p class="mb-0 text-muted small">Total visits</p>
                    <p class="mb-0 font-monospace small"><?= isset($analytics['total']) ? number_format($analytics['total']) : '0' ?></p>
                </div>
                <div class="col-6">
                    <p class="mb-0 text-muted small">Unique students</p>
                    <p class="mb-0 font-monospace small"><?= isset($analytics['unique_students']) ? number_format($analytics['unique_students']) : '0' ?></p>
                </div>
                <div class="col-12">
                    <p class="mb-0 text-muted small">Visits by type</p>
                    <div class="small">
                        <?php if (isset($analytics['byType']) && $analytics['byType'] !== []): ?>
                            <?php foreach ($analytics['byType'] as $item): ?>
                                <span class="me-2 text-capitalize small"><?= e($item['label']) ?>: <?= e($item['count']) ?><br>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Authorized health alerts -->
    <div class="card mb-4" aria-labelledby="health-alerts-heading">
        <div class="card-body">
            <h2 id="health-alerts-heading" class="h5 card-title">Authorized Health Alerts</h2>
            <?php
            $alerts = isset($healthAlerts) ? $healthAlerts : [];
            if ($alerts === []):
            ?>
                <p class="card-text text-muted">No active health alerts.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0 small">
                    <?php foreach ($alerts as $alert): ?>
                        <li class="border-bottom py-1">
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <div>
                                    <strong><?= e($alert['title']) ?></strong>
                                    <span class="badge bg-<?= $alert['severity'] === 'critical' ? 'danger' : ($alert['severity'] === 'warning' ? 'warning' : 'info') ?>">
                                        <?= e($alert['severity']) ?>
                                    </span>
                                </div>
                                <div class="text-end">
                                    <small><?= e($alert['created_at']) ?></small>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- AI assessment access -->
    <div class="card mb-4" aria-labelledby="ai-assessment-heading">
        <div class="card-body">
            <h2 id="ai-assessment-heading" class="h5 card-title">AI-Assisted Health Assessment</h2>
            <p class="small text-muted">
                AI-assisted health-risk estimation is available for authorized students.
                This is decision-support only and does not constitute a medical diagnosis.
            </p>
            <p class="mb-0">
                <a href="<?= e(base_url('/records')) ?>" class="text-primary">View student records for AI assessment</a>
                <br>
                <small class="text-muted">All AI predictions require proper authorization and are logged.</small>
            </p>
        </div>
    </div>
</section>

<?php
$statusLabels = ['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled', 'completed' => 'Completed', 'no_show' => 'No show'];