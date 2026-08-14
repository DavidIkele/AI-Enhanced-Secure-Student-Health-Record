<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var string $from */ ?>
<?php /** @var string $to */ ?>
<?php /** @var bool $rangeInvalid */ ?>
<?php /** @var array<string, mixed> $summary */ ?>
<?php /** @var array<int, array<string, mixed>> $attendanceTrend */ ?>
<?php /** @var array<int, array<string, mixed>> $hourlyTrend */ ?>
<?php /** @var array<int, array<string, mixed>> $weekdayTrend */ ?>
<?php /** @var array<int, array<string, mixed>> $illnessFreq */ ?>
<?php /** @var array<int, array<string, mixed>> $recurringCond */ ?>
<?php /** @var array<string, mixed> $aggregateStats */ ?>
<?php /** @var string $chartDataJson */ ?>
<?php $fmtMonth = static fn (string $ym): string => date('M Y', strtotime($ym . '-01')); ?>
<section aria-labelledby="analytics-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page">Visit history analytics</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h1 id="analytics-heading" class="h3 mb-0">Visit history analytics</h1>
    </div>
    <p class="lead">Aggregate, non-AI statistics on clinic visits. Individual students are never identified.</p>

    <?php if ($rangeInvalid): ?>
        <div class="alert alert-warning" role="alert">
            The selected date range was invalid, so the last 90 days is shown instead.
        </div>
    <?php endif; ?>

    <form method="get" action="<?= e(base_url('/analytics/visits')) ?>" class="row g-2 align-items-end mb-3">
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
            <a class="btn btn-sm btn-outline-secondary" href="<?= e(base_url('/analytics/visits')) ?>">Reset</a>
        </div>
    </form>

    <div class="alert alert-info" role="alert">
        This page shows aggregated clinic statistics only. Where a category contains three or fewer
        distinct students, the exact figure is hidden (<abbr title="not disclosed">N/A</abbr>) to
        prevent re-identification of individuals from small groups.
    </div>

    <?php if ((int) $aggregateStats['total_visits'] === 0): ?>
        <div class="alert alert-secondary" role="alert">No clinic visits found in the selected range.</div>
    <?php else: ?>

        <h2 class="h5 mt-3">Overview</h2>
        <div class="row row-cols-2 row-cols-lg-4 g-3 mb-3">
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="h4 mb-1"><?= e((string) $aggregateStats['total_visits']) ?></div>
                        <div class="text-muted small">Total visits</div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="h4 mb-1"><?= e((string) $aggregateStats['unique_students']) ?></div>
                        <div class="text-muted small">Unique students</div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="h4 mb-1"><?= e((string) $aggregateStats['avg_visits_per_student']) ?></div>
                        <div class="text-muted small">Avg visits / student</div>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card h-100">
                    <div class="card-body">
                        <div class="h4 mb-1"><?= e((string) $aggregateStats['avg_visits_per_month']) ?></div>
                        <div class="text-muted small">Avg visits / month</div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($summary['by_type'] !== [] || $summary['by_status'] !== [] || $summary['by_outcome'] !== []): ?>
            <div class="row g-3 mb-3">
                <?php if ($summary['by_type'] !== []): ?>
                    <div class="col-md-4">
                        <h3 class="h6">Visit types</h3>
                        <div class="table-scroll" tabindex="0">
                            <table class="table table-sm">
                                <caption class="visually-hidden">Number of visits by visit type</caption>
                                <thead>
                                    <tr><th scope="col">Type</th><th scope="col" class="text-end">Visits</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($summary['by_type'] as $row): ?>
                                        <tr><th scope="row"><?= e($row['label']) ?></th><td class="text-end"><?= e((string) $row['count']) ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($summary['by_status'] !== []): ?>
                    <div class="col-md-4">
                        <h3 class="h6">Visit status</h3>
                        <div class="table-scroll" tabindex="0">
                            <table class="table table-sm">
                                <caption class="visually-hidden">Number of visits by status</caption>
                                <thead>
                                    <tr><th scope="col">Status</th><th scope="col" class="text-end">Visits</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($summary['by_status'] as $row): ?>
                                        <tr><th scope="row"><?= e($row['label']) ?></th><td class="text-end"><?= e((string) $row['count']) ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($summary['by_outcome'] !== []): ?>
                    <div class="col-md-4">
                        <h3 class="h6">Visit outcome</h3>
                        <div class="table-scroll" tabindex="0">
                            <table class="table table-sm">
                                <caption class="visually-hidden">Number of visits by outcome</caption>
                                <thead>
                                    <tr><th scope="col">Outcome</th><th scope="col" class="text-end">Visits</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($summary['by_outcome'] as $row): ?>
                                        <tr><th scope="row"><?= e($row['label']) ?></th><td class="text-end"><?= e((string) $row['count']) ?></td></tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <h2 class="h5 mt-4">Monthly attendance trend</h2>
        <div class="row g-3 mb-3">
            <div class="col-lg-7">
                <div class="chart-box" role="img" aria-label="Bar chart of clinic visits per month. Follow the table below for exact values.">
                    <canvas id="chart-attendance" aria-hidden="true"></canvas>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="table-scroll" tabindex="0">
                    <table class="table table-sm table-hover">
                        <caption>Visits and unique students per month</caption>
                        <thead>
                            <tr><th scope="col">Month</th><th scope="col" class="text-end">Visits</th><th scope="col" class="text-end">Unique students</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceTrend as $row): ?>
                                <tr>
                                    <th scope="row"><?= e($fmtMonth((string) $row['period'])) ?></th>
                                    <td class="text-end"><?= e((string) $row['visits']) ?></td>
                                    <td class="text-end"><?= e((string) $row['students']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <h2 class="h5 mt-4">Visits by weekday</h2>
        <div class="row g-3 mb-3">
            <div class="col-lg-7">
                <div class="chart-box" role="img" aria-label="Bar chart of clinic visits by weekday. Follow the table below for exact values.">
                    <canvas id="chart-weekday" aria-hidden="true"></canvas>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="table-scroll" tabindex="0">
                    <table class="table table-sm table-hover">
                        <caption>Visits by weekday</caption>
                        <thead>
                            <tr><th scope="col">Weekday</th><th scope="col" class="text-end">Visits</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($weekdayTrend as $row): ?>
                                <tr>
                                    <th scope="row"><?= e($row['day']) ?></th>
                                    <td class="text-end"><?= e((string) $row['visits']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <h2 class="h5 mt-4">Visits by hour</h2>
        <div class="row g-3 mb-3">
            <div class="col-lg-7">
                <div class="chart-box" role="img" aria-label="Bar chart of clinic visits by hour of day. Follow the table below for exact values.">
                    <canvas id="chart-hourly" aria-hidden="true"></canvas>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="table-scroll chart-hour-table" tabindex="0">
                    <table class="table table-sm">
                        <caption>Visits by hour of the day</caption>
                        <thead>
                            <tr><th scope="col">Hour</th><th scope="col" class="text-end">Visits</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($hourlyTrend as $row): ?>
                                <tr>
                                    <th scope="row"><?= e(sprintf('%02d:00', $row['hour'])) ?></th>
                                    <td class="text-end"><?= e((string) $row['visits']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <h2 class="h5 mt-4">Most common illnesses</h2>
        <div class="row g-3 mb-3">
            <div class="col-lg-7">
                <div class="chart-box" role="img" aria-label="Bar chart of the most common illnesses by number of visits. Follow the table below for exact values.">
                    <canvas id="chart-illness" aria-hidden="true"></canvas>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="table-scroll" tabindex="0">
                    <table class="table table-sm table-hover">
                        <caption>Most common illnesses by number of visits and distinct students. N/A means the exact figure is withheld for privacy.</caption>
                        <thead>
                            <tr><th scope="col">Illness</th><th scope="col" class="text-end">Visits</th><th scope="col" class="text-end">Students</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($illnessFreq as $row): ?>
                                <tr>
                                    <th scope="row"><?= e((string) $row['illness']) ?></th>
                                    <td class="text-end"><?= e((string) $row['visit_count']) ?></td>
                                    <td class="text-end"><?= !empty($row['suppressed']) || $row['student_count'] === null ? '<abbr title="not disclosed">N/A</abbr>' : e((string) $row['student_count']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <h2 class="h5 mt-4">Recurring conditions</h2>
        <?php if ($recurringCond === []): ?>
            <div class="alert alert-secondary" role="alert">No recurring conditions recorded in the selected range.</div>
        <?php else: ?>
            <div class="table-scroll" tabindex="0">
                <table class="table table-sm table-hover">
                    <caption>Conditions flagged as recurring or seen on more than one distinct visit</caption>
                    <thead>
                        <tr><th scope="col">Condition</th><th scope="col" class="text-end">Students</th><th scope="col" class="text-end">Records</th><th scope="col">Source</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recurringCond as $row): ?>
                            <tr>
                                <th scope="row"><?= e((string) $row['condition_name']) ?></th>
                                <td class="text-end"><?= e((string) $row['student_count']) ?></td>
                                <td class="text-end"><?= e((string) $row['record_count']) ?></td>
                                <td><?= e(ucfirst(str_replace('-', ' ', (string) $row['source']))) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</section>

<script type="application/json" id="analytics-chart-data"><?= $chartDataJson ?></script>