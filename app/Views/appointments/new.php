<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array<int, array<string, mixed>> $staffList */ ?>
<?php /** @var array<string, mixed>|null $selectedStaff */ ?>
<?php /** @var array<int, array{time:string, available:bool}> $availability */ ?>
<?php /** @var array<string, array{free:int, total:int}> $monthMap */ ?>
<?php /** @var string $month */ ?>
<?php /** @var string $prevMonth */ ?>
<?php /** @var string $nextMonth */ ?>
<?php /** @var string $presetTime */ ?>
<?php /** @var array{staff_id:int, date:string, duration:int} $preset */ ?>
<?php /** @var array<string, array<int, string>> $errors */ ?>
<?php /** @var array<string, mixed> $old */ ?>
<section aria-labelledby="new-appointment-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(base_url('/appointments')) ?>">Appointments</a></li>
            <li class="breadcrumb-item active" aria-current="page">Request appointment</li>
        </ol>
    </nav>

    <h1 id="new-appointment-heading" class="h3 mb-3">Request an appointment</h1>
    <p class="lead">Choose a clinic staff member and a future time. Your request will be reviewed before it is confirmed.</p>

    <form method="post" action="<?= e(base_url('/appointments')) ?>" class="row g-3 mb-4" novalidate>
        <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">

        <div class="col-md-6">
            <label for="staff_id" class="form-label">Clinic staff member <span aria-hidden="true">*</span></label>
            <select class="form-select<?= isset($errors['staff_id']) ? ' is-invalid' : '' ?>" id="staff_id" name="staff_id" required aria-describedby="staff_id-error">
                <option value="">Choose a staff member&hellip;</option>
                <?php foreach ($staffList as $staff): ?>
                    <option value="<?= (int) $staff['id'] ?>"<?= (int) $preset['staff_id'] === (int) $staff['id'] ? ' selected' : '' ?>>
                        <?= e(\App\Repositories\HealthcareStaffRepository::displayName($staff)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['staff_id'])): ?>
                <div class="invalid-feedback" id="staff_id-error"><?= e($errors['staff_id'][0]) ?></div>
            <?php endif; ?>
            <div class="mt-2">
                <a id="show-availability-link" class="btn btn-sm btn-outline-primary"
                   href="<?= e(base_url('/appointments/new?staff_id=' . (int) $preset['staff_id'] . '&month=' . $month . '&duration=' . (int) $preset['duration'])) ?>"
                   data-month="<?= e($month) ?>">
                    Show availability calendar
                </a>
            </div>
        </div>

        <div class="col-md-3">
            <label for="scheduled_at" class="form-label">Date and time <span aria-hidden="true">*</span></label>
            <input type="datetime-local" class="form-control<?= isset($errors['scheduled_at']) ? ' is-invalid' : '' ?>" id="scheduled_at" name="scheduled_at" value="<?= e(($old['scheduled_at'] ?? '') !== '' ? $old['scheduled_at'] : $presetTime) ?>" required aria-describedby="scheduled_at-help scheduled_at-error">
            <div class="form-text" id="scheduled_at-help">Choose a future date and time (clinic hours <?= e((string) \App\Repositories\AppointmentsRepository::OPEN_HOUR) ?>:00&ndash;<?= e((string) \App\Repositories\AppointmentsRepository::CLOSE_HOUR) ?>:00).</div>
            <?php if (isset($errors['scheduled_at'])): ?>
                <div class="invalid-feedback" id="scheduled_at-error"><?= e($errors['scheduled_at'][0]) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-3">
            <label for="duration_minutes" class="form-label">Duration</label>
            <select class="form-select<?= isset($errors['duration_minutes']) ? ' is-invalid' : '' ?>" id="duration_minutes" name="duration_minutes" aria-describedby="duration_minutes-error">
                <?php foreach ([15, 30, 45, 60, 90, 120] as $mins): ?>
                    <option value="<?= $mins ?>"<?= (int) $preset['duration'] === $mins ? ' selected' : '' ?>><?= $mins ?> minutes</option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['duration_minutes'])): ?>
                <div class="invalid-feedback" id="duration_minutes-error"><?= e($errors['duration_minutes'][0]) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <label for="reason" class="form-label">Reason for visit <span aria-hidden="true">*</span></label>
            <input type="text" class="form-control<?= isset($errors['reason']) ? ' is-invalid' : '' ?>" id="reason" name="reason" maxlength="255" required value="<?= e($old['reason'] ?? '') ?>" placeholder="e.g. Annual check-up, review of test results" aria-describedby="reason-help reason-error">
            <div class="form-text" id="reason-help">Briefly describe the purpose of the visit.</div>
            <?php if (isset($errors['reason'])): ?>
                <div class="invalid-feedback" id="reason-error"><?= e($errors['reason'][0]) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Request appointment</button>
            <a href="<?= e(base_url('/appointments')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <?php if ($selectedStaff !== null): ?>
        <?php
        $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        [$calY, $calM] = array_map('intval', explode('-', $month));
        $startDow = (int) date('N', mktime(0, 0, 0, $calM, 1, $calY)); // 1=Mon..7=Sun
        $gridStart = mktime(0, 0, 0, $calM, 1 - ($startDow - 1), $calY);
        $todayTs = mktime(0, 0, 0, (int) date('n'), (int) date('j'), (int) date('Y'));
        ?>
        <div class="card mb-4" aria-labelledby="availability-calendar-heading">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                    <h2 id="availability-calendar-heading" class="h5 mb-0">
                        Available days for <?= e(\App\Repositories\HealthcareStaffRepository::displayName($selectedStaff)) ?>
                    </h2>
                    <div class="btn-group" role="group" aria-label="Month navigation">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/appointments/new?staff_id=' . (int) $preset['staff_id'] . '&month=' . $prevMonth . '&duration=' . (int) $preset['duration'])) ?>">&larr; Previous month</a>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/appointments/new?staff_id=' . (int) $preset['staff_id'] . '&duration=' . (int) $preset['duration'])) ?>">This month</a>
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/appointments/new?staff_id=' . (int) $preset['staff_id'] . '&month=' . $nextMonth . '&duration=' . (int) $preset['duration'])) ?>">Next month &rarr;</a>
                    </div>
                </div>
                <p class="text-muted small">
                    Free days are shown in green. Click a day to see the available hours; click a free hour to prefill the form.
                </p>
                <div class="table-responsive" tabindex="0">
                    <table class="table table-bordered align-top calendar-grid">
                        <caption class="visually-hidden">Available booking days and hours for <?= e(\App\Repositories\HealthcareStaffRepository::displayName($selectedStaff)) ?> in <?= e(date('F Y', mktime(0, 0, 0, $calM, 1, $calY))) ?>. Booking identities are never shown.</caption>
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
                                        $isCurrentMonth = (int) date('n', $ts) === $calM && (int) date('Y', $ts) === $calY;
                                        $dayNum = (int) date('j', $ts);
                                        $dateKey = date('Y-m-d', $ts);
                                        $dayData = $monthMap[$dateKey] ?? null;
                                        $isPast = $ts < $todayTs;
                                        $hasFree = $isCurrentMonth && !$isPast && $dayData !== null && $dayData['free'] > 0;
                                        ?>
                                        <td class="align-top text-center<?= $hasFree ? ' bg-success-subtle' : '' ?>" style="min-width:6rem;">
                                            <div class="fw-bold small"><?= $isCurrentMonth ? $dayNum : '&nbsp;' ?></div>
                                            <?php if ($isCurrentMonth): ?>
                                                <?php if ($isPast): ?>
                                                    <div class="small text-muted">&ndash;</div>
                                                <?php elseif ($dayData !== null && $dayData['free'] > 0): ?>
                                                    <a class="btn btn-sm btn-success"
                                                       href="<?= e(base_url('/appointments/new?staff_id=' . (int) $preset['staff_id'] . '&month=' . $month . '&date=' . $dateKey . '&duration=' . (int) $preset['duration'])) ?>">
                                                        <?= (int) $dayData['free'] ?> free
                                                    </a>
                                                <?php elseif ($dayData !== null): ?>
                                                    <div class="small text-muted">Full</div>
                                                <?php else: ?>
                                                    <div class="small text-muted">&ndash;</div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endfor; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($availability !== []): ?>
        <div aria-live="polite">
            <h2 class="h5">Clinic availability for <?= e(date('l, j F Y', strtotime((string) $preset['date']))) ?></h2>
            <p class="text-muted small" id="availability-status">Free slots are shown in green. Click a free slot to prefill the form above.</p>
            <div class="table-responsive" tabindex="0">
                <table class="table table-sm align-middle">
                    <caption class="visually-hidden">Available clinic slots on <?= e($preset['date']) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col">Time</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($availability as $slot): ?>
                            <tr>
                                <th scope="row"><?= e(date('H:i', strtotime($slot['time']))) ?></th>
                                <td>
                                    <?php if ($slot['available']): ?>
                                        <a class="btn btn-sm btn-success"
                                           href="<?= e(base_url('/appointments/new?staff_id=' . (int) $preset['staff_id'] . '&month=' . $month . '&date=' . $preset['date'] . '&duration=' . (int) $preset['duration'] . '&time=' . substr($slot['time'], 11, 5))) ?>">
                                            Choose this time
                                        </a>
                                    <?php else: ?>
                                        <span class="badge text-bg-secondary">Booked</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>
