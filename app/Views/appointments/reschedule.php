<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array<string, mixed> $appointment */ ?>
<?php /** @var array<string, array<int, string>> $errors */ ?>
<?php /** @var array<string, mixed> $old */ ?>
<section aria-labelledby="reschedule-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(base_url('/appointments')) ?>">Appointments</a></li>
            <li class="breadcrumb-item active" aria-current="page">Reschedule</li>
        </ol>
    </nav>

    <h1 id="reschedule-heading" class="h3 mb-3">Reschedule appointment</h1>

    <dl class="row mb-4">
        <dt class="col-sm-3">Currently scheduled</dt>
        <dd class="col-sm-9"><?= e(date('D, j M Y H:i', strtotime((string) $appointment['scheduled_at']))) ?> (<?= e((string) $appointment['duration_minutes']) ?> min)</dd>
        <dt class="col-sm-3">Staff member</dt>
        <dd class="col-sm-9"><?= e(trim(($appointment['staff_title'] ?? '') . ' ' . $appointment['staff_first'] . ' ' . $appointment['staff_last'])) ?></dd>
        <dt class="col-sm-3">Reason</dt>
        <dd class="col-sm-9"><?= e($appointment['reason']) ?></dd>
    </dl>

    <form method="post" action="<?= e(base_url('/appointments/' . (int) $appointment['id'] . '/reschedule')) ?>" class="row g-3" novalidate>
        <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">

        <div class="col-md-5">
            <label for="scheduled_at" class="form-label">New date and time <span aria-hidden="true">*</span></label>
            <input type="datetime-local" class="form-control<?= isset($errors['scheduled_at']) ? ' is-invalid' : '' ?>" id="scheduled_at" name="scheduled_at" value="<?= e($old['scheduled_at'] ?? date('Y-m-d\TH:i', strtotime((string) $appointment['scheduled_at']))) ?>" required aria-describedby="scheduled_at-help scheduled_at-error">
            <div class="form-text" id="scheduled_at-help">Choose a future date and time.</div>
            <?php if (isset($errors['scheduled_at'])): ?>
                <div class="invalid-feedback" id="scheduled_at-error"><?= e($errors['scheduled_at'][0]) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-3">
            <label for="duration_minutes" class="form-label">Duration</label>
            <select class="form-select<?= isset($errors['duration_minutes']) ? ' is-invalid' : '' ?>" id="duration_minutes" name="duration_minutes" aria-describedby="duration_minutes-error">
                <?php foreach ([15, 30, 45, 60, 90, 120] as $mins): ?>
                    <option value="<?= $mins ?>"<?= (int) ($old['duration_minutes'] ?? $appointment['duration_minutes']) === $mins ? ' selected' : '' ?>><?= $mins ?> minutes</option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['duration_minutes'])): ?>
                <div class="invalid-feedback" id="duration_minutes-error"><?= e($errors['duration_minutes'][0]) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Save new time</button>
            <a href="<?= e(base_url('/appointments')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</section>
