<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var string $month */ ?>
<?php /** @var int $year */ ?>
<?php /** @var int $mon */ ?>
<?php /** @var array<int, array<string, mixed>> $staffList */ ?>
<?php /** @var array<int, array<int, array<string, mixed>>> $byStaff */ ?>
<?php /** @var string $prevMonth */ ?>
<?php /** @var string $nextMonth */ ?>
<?php
$dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$startDow = (int) date('N', mktime(0, 0, 0, $mon, 1, $year)); // 1=Mon..7=Sun
$daysInMonth = (int) date('t', mktime(0, 0, 0, $mon, 1, $year));
$gridStart = mktime(0, 0, 0, $mon, 1 - ($startDow - 1), $year);
$cells = 7 * 6; // six weeks of a month grid
?>
<section aria-labelledby="calendar-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(base_url('/appointments')) ?>">Appointments</a></li>
            <li class="breadcrumb-item active" aria-current="page">Calendar</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h1 id="calendar-heading" class="h3 mb-0">Appointment calendar</h1>
        <div class="btn-group" role="group" aria-label="Calendar navigation">
            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/appointments/calendar?month=' . $prevMonth)) ?>">&larr; Previous month</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/appointments/calendar')) ?>">This month</a>
            <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/appointments/calendar?month=' . $nextMonth)) ?>">Next month &rarr;</a>
        </div>
    </div>
    <p class="lead"><?= e(date('F Y', mktime(0, 0, 0, $mon, 1, $year))) ?></p>
    <p class="text-muted small">
        This calendar is an optional visual aid. The
        <a href="<?= e(base_url('/appointments')) ?>">full appointment list</a> is the primary way to access appointment information.
    </p>

    <?php if ($staffList === []): ?>
        <div class="alert alert-secondary" role="alert">No active clinic staff are available.</div>
    <?php else: ?>
        <?php foreach ($staffList as $staff): ?>
            <h2 class="h5 mt-4"><?= e(\App\Repositories\HealthcareStaffRepository::displayName($staff)) ?></h2>
            <div class="table-responsive" tabindex="0">
                <table class="table table-bordered align-top calendar-grid">
                    <caption class="visually-hidden">Appointments for <?= e(\App\Repositories\HealthcareStaffRepository::displayName($staff)) ?> in <?= e(date('F Y', mktime(0, 0, 0, $mon, 1, $year))) ?></caption>
                    <thead>
                        <tr>
                            <?php foreach ($dayNames as $d): ?>
                                <th scope="col" class="text-center"><?= e($d) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($row = 0; $row < 6; $row++): ?>
                            <tr>
                                <?php for ($col = 0; $col < 7; $col++): ?>
                                    <?php
                                    $ts = $gridStart + (($row * 7) + $col) * 86400;
                                    $day = (int) date('j', $ts);
                                    $isCurrentMonth = (int) date('n', $ts) === $mon && (int) date('Y', $ts) === $year;
                                    $dateKey = date('Y-m-d', $ts);
                                    $dayAppointments = array_filter(
                                        $byStaff[(int) $staff['id']] ?? [],
                                        static fn ($a) => substr((string) $a['scheduled_at'], 0, 10) === $dateKey
                                    );
                                    ?>
                                    <td class="<?= $isCurrentMonth ? '' : 'table-secondary text-muted' ?> align-top">
                                        <div class="fw-bold small"><?= $isCurrentMonth ? $day : '' ?></div>
                                        <?php if ($isCurrentMonth): ?>
                                            <?php foreach ($dayAppointments as $a): ?>
                                                <div class="small border rounded px-1 mb-1">
                                                    <span class="fw-semibold"><?= e(date('H:i', strtotime((string) $a['scheduled_at']))) ?></span>
                                                    <span class="badge text-bg-<?= $a['status'] === 'approved' ? 'success' : ($a['status'] === 'pending' ? 'warning' : 'secondary') ?>"><?= e($a['status']) ?></span>
                                                    <span class="d-block"><?= e($a['student_last'] . ', ' . $a['student_first']) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </td>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <p class="mt-3">
        <a href="<?= e(base_url('/appointments')) ?>" class="btn btn-outline-secondary">&larr; Back to appointment list</a>
    </p>
</section>
