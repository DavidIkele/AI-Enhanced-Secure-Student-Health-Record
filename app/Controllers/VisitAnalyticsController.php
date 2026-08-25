<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AnalyticsRepository;
use App\Security\Security;
use App\Services\AuditLogService;
use App\Services\Validator;

/**
 * Visit History Analytics — non-AI aggregate analytics for staff
 * and administrators.
 *
 * Authorization: requires 'analytics.view' permission (staff/admin only).
 * Students do not have this permission.
 *
 * Privacy controls:
 *  - Queries never select student identities (names, reg numbers, emails).
 *  - Small-cell suppression applied to categories with fewer than
 *    AnalyticsRepository::PRIVACY_MIN_CELL distinct students.
 *  - Aggregates are anonymous; a privacy note is displayed on the page.
 */
class VisitAnalyticsController extends BaseController
{
    public function visits(): void
    {
        // Authorization (defence in depth; middleware is primary)
        if (!$this->canViewAnalytics()) {
            $this->abort(403, 'You do not have permission to view analytics.');
        }

        $from = $this->request->query('from', '');
        $to   = $this->request->query('to', '');

        // Validate and normalize date range
        $range = self::normalizeRange($from, $to);
        $from  = $range['from'];
        $to    = $range['to'];

        $repo = new AnalyticsRepository();

        // Fetch all datasets
        $summary           = $repo->summary($from, $to);
        $attendanceTrend   = $repo->attendanceTrend($from, $to);
        $hourlyTrend       = $repo->visitsByHour($from, $to);
        $weekdayTrend      = $repo->visitsByWeekday($from, $to);
        $illnessFreq       = AnalyticsRepository::suppressSmallCells($repo->illnessFrequency($from, $to, 10));
        $recurringCond     = AnalyticsRepository::suppressSmallCells($repo->recurringConditions($from, $to));
        $aggregateStats    = $repo->aggregateStats($from, $to);

        // Prepare chart data for JSON embedding
        $chartData = $this->prepareChartData($attendanceTrend, $hourlyTrend, $weekdayTrend, $illnessFreq, $summary);

        // Audit log
        $auth = new \App\Services\AuthService();
        AuditLogService::record('view', 'visit_analytics', 'range:' . $from . '..' . $to, null, (int) ($auth->id() ?? 0));

        $this->render('analytics/visits', [
            'title'           => 'Visit History Analytics | Student Health Record System',
            'page'            => 'analytics',
            'extra_scripts'   => [base_url('assets/vendor/chartjs/chart.umd.min.js')],
            'from'            => $from,
            'to'              => $to,
            'rangeInvalid'    => $range['invalid'],
            'summary'         => $summary,
            'attendanceTrend' => $attendanceTrend,
            'hourlyTrend'     => $hourlyTrend,
            'weekdayTrend'    => $weekdayTrend,
            'illnessFreq'     => $illnessFreq,
            'recurringCond'   => $recurringCond,
            'aggregateStats'  => $aggregateStats,
            'chartDataJson'   => $chartData,
        ]);
    }

    /**
     * Normalize and validate a date range from request query parameters.
     *
     * Returns ['from' => string|null, 'to' => string|null, 'invalid' => bool].
     * Invalid inputs fall back to a safe default (last 90 days).
     */
    public static function normalizeRange(string $from, string $to): array
    {
        $invalid = false;

        $validator = (new Validator())
            ->field('from', $from)
            ->field('to', $to)
            ->date('from')
            ->date('to');

        if (!$validator->passes()) {
            $invalid = true;
        } elseif ($from !== '' && $to !== '' && $from > $to) {
            $invalid = true;
        }

        // Default: last 90 days
        $defaultTo   = date('Y-m-d');
        $defaultFrom = date('Y-m-d', strtotime('-90 days'));

        return [
            'from'    => $invalid || $from === '' ? $defaultFrom : $from,
            'to'      => $invalid || $to === '' ? $defaultTo : $to,
            'invalid' => $invalid,
        ];
    }

    private function canViewAnalytics(): bool
    {
        $auth = new \App\Services\AuthService();
        $id   = $auth->id();
        return $id !== null && \App\Security\AccessControl::can($id, 'analytics.view');
    }

    /**
     * Prepare chart data as a JSON-safe string for embedding in the view.
     */
    private function prepareChartData(
        array $attendanceTrend,
        array $hourlyTrend,
        array $weekdayTrend,
        array $illnessFreq,
        array $summary
    ): string {
        $data = [
            'attendance' => [
                'labels' => array_column($attendanceTrend, 'period'),
                'visits' => array_column($attendanceTrend, 'visits'),
                'students' => array_column($attendanceTrend, 'students'),
            ],
            'hourly' => [
                'labels' => array_map(static fn (array $r) => sprintf('%02d:00', $r['hour']), $hourlyTrend),
                'visits' => array_column($hourlyTrend, 'visits'),
            ],
            'weekday' => [
                'labels' => array_column($weekdayTrend, 'day'),
                'visits' => array_column($weekdayTrend, 'visits'),
            ],
            'illness' => [
                'labels' => array_map(static fn (array $r) => $r['illness'], $illnessFreq),
                'visits' => array_map(static fn (array $r) => (int) $r['visit_count'], $illnessFreq),
                'students' => array_map(static fn (array $r) => $r['student_count'] ?? 'N/A', $illnessFreq),
                'suppressed' => array_map(static fn (array $r) => (bool) ($r['suppressed'] ?? false), $illnessFreq),
            ],
            'visitTypes' => [
                'labels' => array_column($summary['by_type'], 'label'),
                'visits' => array_column($summary['by_type'], 'count'),
            ],
        ];

        // json_encode with unescaped slashes; then escape '</' to prevent script tag breakout
        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return str_replace('</', '<\/', $json);
    }
}