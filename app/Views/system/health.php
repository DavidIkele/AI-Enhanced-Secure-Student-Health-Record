<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var string $status */ ?>
<?php /** @var string $service */ ?>
<?php /** @var string $time */ ?>
<section aria-labelledby="system-health-heading">
    <h1 id="system-health-heading" class="h3">System health</h1>
    <p class="lead">Operational status of the student health record system.</p>

    <div class="alert alert-<?= $status === 'ok' ? 'success' : 'danger' ?>" role="status">
        <?= $status === 'ok' ? 'All monitored services are operating normally.' : 'One or more services need attention.' ?>
    </div>

    <dl class="row">
        <dt class="col-3">Status</dt>
        <dd class="col-9"><?= e($status) ?></dd>

        <dt class="col-3">Service</dt>
        <dd class="col-9"><?= e($service) ?></dd>

        <dt class="col-3">Checked at</dt>
        <dd class="col-9"><?= e($time) ?> (UTC)</dd>
    </dl>

    <p class="mt-3">
        <a href="<?= e(base_url('/')) ?>" class="btn btn-outline-secondary">&larr; Back to home</a>
    </p>
</section>
