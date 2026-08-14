<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array<string, int> $preferences */ ?>
<?php /** @var array<string, array<int, string>> $errors */ ?>
<?php $csrf = \App\Security\Security::csrfToken(); ?>
<?php
$fields = [
    'notify_appointment_changes' => 'Appointment updates',
    'notify_health_insights' => 'Personalised health insights',
    'notify_health_alerts' => 'Personal health alerts',
    'notify_system_announcements' => 'System announcements',
    'appointment_reminder_opt_in' => 'Appointment reminders',
];
$descriptions = [
    'notify_appointment_changes' => 'When your appointment is approved, rejected, rescheduled, or cancelled by the clinic.',
    'notify_health_insights' => 'Informational notes generated from your own records (not a medical diagnosis).',
    'notify_health_alerts' => 'Alerts about your personal health, sent by authorised clinic staff.',
    'notify_system_announcements' => 'Health-centre wide announcements from the administration team.',
    'appointment_reminder_opt_in' => 'A reminder ahead of each confirmed appointment.',
];
?>
<section aria-labelledby="preferences-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(base_url('/profile')) ?>">My profile</a></li>
            <li class="breadcrumb-item active" aria-current="page">Notification preferences</li>
        </ol>
    </nav>

    <h1 id="preferences-heading" class="h3 mb-1">Notification preferences</h1>
    <p class="lead">Choose which notifications appear in your inbox. Changes take effect immediately.</p>

    <form method="post" action="<?= e(base_url('/profile/preferences')) ?>" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <fieldset class="border rounded p-3 mb-3">
            <legend class="h5 float-none w-auto px-2">Notifications</legend>
            <?php foreach ($fields as $key => $label): ?>
                <?php $checked = (int) ($preferences[$key] ?? 1) === 1; ?>
                <div class="form-check">
                    <input
                        type="checkbox"
                        class="form-check-input"
                        id="<?= e($key) ?>"
                        name="<?= e($key) ?>"
                        value="1"
                        <?= $checked ? 'checked' : '' ?>
                    >
                    <label class="form-check-label" for="<?= e($key) ?>">
                        <strong><?= e($label) ?></strong>
                    </label>
                    <div class="form-text"><?= e($descriptions[$key] ?? '') ?></div>
                </div>
            <?php endforeach; ?>
        </fieldset>

        <p class="text-muted small">
            You can change these preferences at any time. Disabling a category
            hides future notifications of that type from your inbox, but does
            not delete notifications you have already received.
        </p>

        <div class="d-flex flex-wrap gap-2">
            <button type="submit" class="btn btn-primary">Save preferences</button>
            <a class="btn btn-outline-secondary" href="<?= e(base_url('/profile')) ?>">Back to profile</a>
        </div>
    </form>
</section>
