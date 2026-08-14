<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array<int, array<string, mixed>> $rows */ ?>
<?php /** @var int $total */ ?>
<?php /** @var int $page */ ?>
<?php /** @var int $pages */ ?>
<?php /** @var string $action */ ?>
<?php /** @var array<int, string> $actions */ ?>
<?php
$details = static function (?string $json): string {
    if ($json === null || $json === '') {
        return '';
    }
    $decoded = json_decode($json, true);
    if (!is_array($decoded) || $decoded === []) {
        return '';
    }
    $parts = [];
    foreach ($decoded as $key => $value) {
        $parts[] = e((string) $key) . ': ' . e(is_scalar($value) ? (string) $value : json_encode($value));
    }
    return implode('<br>', $parts);
};
?>
<section aria-labelledby="audit-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page">Audit log</li>
        </ol>
    </nav>

    <h1 id="audit-heading" class="h3">Audit log</h1>
    <p class="lead">Read-only record of security-relevant and record-management events. Passwords, tokens, API keys and clinical details are never stored in this log.</p>

    <form method="get" action="<?= e(base_url('/admin/audit')) ?>" class="row g-2 align-items-end mb-3" novalidate>
        <div class="col-auto">
            <label for="audit-action-filter" class="form-label">Filter by event</label>
            <select class="form-select" id="audit-action-filter" name="action">
                <option value="">All events</option>
                <?php foreach ($actions as $option): ?>
                    <option value="<?= e($option) ?>"<?= $option === $action ? ' selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-outline-primary">Apply filter</button>
        </div>
        <?php if ($action !== ''): ?>
            <div class="col-auto">
                <a class="btn btn-outline-secondary" href="<?= e(base_url('/admin/audit')) ?>">Clear filter</a>
            </div>
        <?php endif; ?>
    </form>

    <p><?= e((string) $total) ?> event<?= $total === 1 ? '' : 's' ?> recorded.</p>

    <?php if ($rows === []): ?>
        <div class="alert alert-secondary" role="status">No audit events found.</div>
    <?php else: ?>
        <div class="table-responsive" tabindex="0">
            <table class="table table-striped align-middle">
                <caption class="visually-hidden">Audit log events with time, actor, action, target and details.</caption>
                <thead>
                    <tr>
                        <th scope="col">Time</th>
                        <th scope="col">Actor</th>
                        <th scope="col">Event</th>
                        <th scope="col">Target</th>
                        <th scope="col">Details</th>
                        <th scope="col">Source</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td class="text-nowrap"><?= e(date('Y-m-d H:i:s', strtotime((string) $row['created_at']))) ?></td>
                            <td><?= $row['username'] !== null ? e((string) $row['username']) : '<span class="text-muted">system</span>' ?></td>
                            <td>
                                <span class="badge text-bg-light"><?= e((string) $row['action']) ?></span>
                            </td>
                            <td>
                                <?= e((string) $row['entity_type']) ?><?= $row['entity_id'] !== null ? '#' . e((string) $row['entity_id']) : '' ?>
                            </td>
                            <td class="text-break"><?= $details($row['new_values'] !== null ? (string) $row['new_values'] : null) ?></td>
                            <td class="text-nowrap small text-muted">
                                <?= e((string) $row['ip_address']) ?><br>
                                <?= $row['request_method'] !== null ? e((string) $row['request_method']) . ' ' : '' ?><?= e((string) $row['request_path']) ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav aria-label="Audit log pages">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <?php $qs = http_build_query(array_filter(['action' => $action]) + ['page' => $i]); ?>
                        <li class="page-item<?= $i === $page ? ' active' : '' ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>>
                            <a class="page-link" href="<?= e(base_url('/admin/audit?' . $qs)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
