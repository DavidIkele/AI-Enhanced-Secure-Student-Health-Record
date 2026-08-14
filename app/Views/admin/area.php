<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<section aria-labelledby="admin-heading">
    <h1 id="admin-heading" class="h3">Administration</h1>
    <p class="lead">This area is restricted to the <strong>Administrator</strong> role.</p>

    <div class="list-group w-100" style="max-width: 36rem;">
        <a href="<?= e(base_url('/admin/audit')) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
            <span>
                <strong>Audit log</strong>
                <span class="d-block small text-muted">Read-only record of security and record-management events.</span>
            </span>
            <span aria-hidden="true">&rarr;</span>
        </a>
    </div>
</section>
