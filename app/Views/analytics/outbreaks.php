<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var string $from */ ?>
<?php /** @var string $to */ ?>
<?php /** @var bool $rangeInvalid */ ?>
<?php /** @var array<int, array<string, mixed>> $results */ ?>
<?php /** @var array<string, mixed> $summary */ ?>
<?php /** @var bool $canManage */ ?>
<?php
$alertBadges = [
    'none'     => ['secondary', 'None'],
    'watch'    => ['info', 'Watch'],
    'warning'  => ['warning', 'Warning'],
    'elevated' => ['danger', 'Elevated'],
];
$fmtDate = static fn (string $date): string => date('j M Y', strtotime($date));
?>
<section aria-labelledby="outbreaks-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(base_url('/analytics/visits')) ?>">Analytics</a></li>
            <li class="breadcrumb-item active" aria-current="page">Outbreak detection</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h1 id="outbreaks-heading" class="h3 mb-0">Outbreak &amp; illness-pattern detection</h1>
    </div>
    <p class="lead">Category-level signals of unusual illness activity. Individual students are never identified.</p>

    <?php if ($rangeInvalid): ?>
        <div class="alert alert-warning" role="alert">
            The selected date range was invalid, so the last 90 days is shown instead.
        </div>
    <?php endif; ?>

    <div class="row g-2 align-items-end mb-3">
        <div class="col-auto">
            <form method="get" action="<?= e(base_url('/analytics/outbreaks')) ?>" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label for="from" class="form-label">From</label>
                    <input type="date" id="from" name="from" class="form-control form-control-sm" value="<?= e($from) ?>">
                </div>
                <div class="col-auto">
                    <label for="to" class="form-label">To</label>
                    <input type="date" id="to" name="to" class="form-control form-control-sm" value="<?= e($to) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary">Apply range</button>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('/analytics/outbreaks')) ?>">Reset</a>
                </div>
            </form>
        </div>
        <?php if ($canManage): ?>
            <div class="col-auto ms-lg-auto">
                <form method="post" action="<?= e(base_url('/analytics/outbreaks/run?from=' . urlencode($from) . '&to=' . urlencode($to))) ?>" class="d-inline">
                    <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                    <button type="submit" class="btn btn-sm btn-success">Run detection</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="alert alert-info" role="alert">
        Detected categories are compared against a rolling 8-week baseline using a z-score.
        A week with fewer than 3 coded diagnoses is never flagged, so a single case is never
        treated as a cluster. Results are aggregate counts only.
    </div>

    <div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <div class="h4 mb-1"><?= e((string) $summary['total_periods']) ?></div>
                    <div class="text-muted small">Periods reviewed</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <div class="h4 mb-1"><?= e((string) $summary['flagged_periods']) ?></div>
                    <div class="text-muted small">Flagged periods</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <div class="h4 mb-1"><?= e((string) $summary['elevated_periods']) ?></div>
                    <div class="text-muted small">Elevated periods</div>
                </div>
            </div>
        </div>
        <div class="col">
            <div class="card h-100">
                <div class="card-body">
                    <div class="h4 mb-1"><?= e((string) $summary['categories']) ?></div>
                    <div class="text-muted small">Categories</div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($summary['last_run'] !== null): ?>
        <p class="text-muted small">Last detection run: <?= e($summary['last_run']) ?> (UTC)</p>
    <?php endif; ?>

    <h2 class="h5 mt-3">Detected signals</h2>
    <?php if ($results === []): ?>
        <div class="alert alert-secondary" role="alert">
            No detection results for the selected range yet. Run detection to compute them.
        </div>
    <?php else: ?>
        <div class="table-scroll" tabindex="0">
            <table class="table table-sm table-hover">
                <caption>Illness categories with weekly observed counts compared against their rolling baseline</caption>
                <thead>
                    <tr>
                        <th scope="col">Category</th>
                        <th scope="col">Period</th>
                        <th scope="col" class="text-end">Baseline</th>
                        <th scope="col" class="text-end">Observed</th>
                        <th scope="col" class="text-end">Z-score</th>
                        <th scope="col">Level</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($results as $row): ?>
                        <?php [$badge, $label] = $alertBadges[$row['alert_level']] ?? ['secondary', (string) $row['alert_level']]; ?>
                        <tr<?= !empty($row['is_flagged']) ? ' class="table-active"' : '' ?>>
                            <th scope="row"><?= e((string) $row['illness_category']) ?></th>
                            <td><?= e($fmtDate((string) $row['period_start']) . ' – ' . $fmtDate((string) $row['period_end'])) ?></td>
                            <td class="text-end"><?= e((string) $row['baseline_count']) ?></td>
                            <td class="text-end"><?= e((string) $row['observed_count']) ?></td>
                            <td class="text-end"><?= $row['z_score'] === null ? '—' : e(number_format((float) $row['z_score'], 3)) ?></td>
                            <td>
                                <span class="badge text-bg-<?= e($badge) ?>"><?= e($label) ?></span>
                                <?php if (!empty($row['is_flagged'])): ?>
                                    <span class="visually-hidden">Flagged</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-2 small text-muted">
            <strong>Levels:</strong>
            <span class="badge text-bg-secondary">None</span> z &lt; 1.5 &middot;
            <span class="badge text-bg-info">Watch</span> 1.5 &ndash; 2.0 &middot;
            <span class="badge text-bg-warning">Warning</span> 2.0 &ndash; 2.5 &middot;
            <span class="badge text-bg-danger">Elevated</span> &ge; 2.5
        </div>
    <?php endif; ?>
</section>
