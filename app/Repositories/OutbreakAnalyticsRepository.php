<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Outbreak detection / illness-pattern analytics (PROMPT 10).
 *
 * Detects unusual spikes in coded diagnoses (those carrying an ICD-10 code)
 * at a category level. Categories are aggregated into weekly buckets and each
 * observed week is compared against a rolling baseline (the preceding 8
 * weeks) using a z-score. Results are stored in the `outbreak_analytics`
 * table (aggregate rows only) and are never attributed to individual
 * students: no identity columns are ever selected.
 *
 * Alert thresholds (z-score):
 *   none      z < 1.5
 *   watch     1.5 <= z < 2.0
 *   warning   2.0 <= z < 2.5
 *   elevated  z >= 2.5
 *
 * Small-cell suppression: a week with fewer than PRIVACY_MIN_OBSERVED coded
 * diagnoses is never flagged, which prevents a single student's case from
 * being treated as a cluster.
 */
final class OutbreakAnalyticsRepository extends BaseRepository
{
    /** Detection period length in days. */
    public const PERIOD_DAYS = 7;

    /** Number of prior weeks used as the rolling baseline. */
    public const BASELINE_WEEKS = 8;

    /** Minimum observed cases before a period may be flagged. */
    public const PRIVACY_MIN_OBSERVED = 3;

    public const WATCH_Z = 1.5;
    public const WARNING_Z = 2.0;
    public const ELEVATED_Z = 2.5;

    /** Hard cap on observed periods per run (protects against huge ranges). */
    public const MAX_PERIODS = 156;

    public const MAX_CATEGORY_LEN = 120;

    /**
     * Recompute weekly category aggregates and upsert the results into
     * `outbreak_analytics`. Existing rows for the same (category, period) are
     * overwritten; new periods are inserted.
     *
     * @return array<string, int> summary of what the run produced
     */
    public function runDetection(?string $from = null, ?string $to = null, ?int $createdBy = null): array
    {
        $from = $from ?? date('Y-m-d', strtotime('-90 days'));
        $to = $to ?? date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $baselineStart = date('Y-m-d', strtotime($from . ' -' . (self::BASELINE_WEEKS * self::PERIOD_DAYS) . ' days'));

        // Coded diagnoses only (icd_code IS NOT NULL) within the baseline
        // horizon. Aggregates are computed in PHP so the weekly bucketing and
        // sliding baseline stay explicit and testable.
        $rows = $this->all(
            'SELECT d.name AS dx_name, DATE(cv.visited_at) AS visit_date
               FROM diagnoses d
               JOIN clinic_visits cv ON cv.id = d.clinic_visit_id
              WHERE d.icd_code IS NOT NULL
                AND DATE(cv.visited_at) BETWEEN :from AND :to',
            [':from' => $baselineStart, ':to' => $to]
        );

        $counts = [];
        foreach ($rows as $row) {
            $category = self::normalizeCategory((string) $row['dx_name']);
            if ($category === '') {
                continue;
            }
            $week = self::weekStart((string) $row['visit_date']);
            $counts[$category][$week] = ($counts[$category][$week] ?? 0) + 1;
        }

        $observedWeeks = self::weeksBetween($from, $to);
        if (count($observedWeeks) > self::MAX_PERIODS) {
            $observedWeeks = array_slice($observedWeeks, -self::MAX_PERIODS);
        }

        // Prepared here (not via prepare()) because BaseRepository::prepare()
        // executes immediately; this statement is bound and executed once per
        // (category, period) below.
        $upsert = $this->db->prepare(
            'INSERT INTO outbreak_analytics
               (illness_category, period_start, period_end, baseline_count,
                observed_count, z_score, alert_level, is_flagged, created_by, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
               baseline_count = VALUES(baseline_count),
               observed_count  = VALUES(observed_count),
               z_score         = VALUES(z_score),
               alert_level     = VALUES(alert_level),
               is_flagged      = VALUES(is_flagged),
               created_by      = VALUES(created_by)'
        );

        $upserted = 0;
        $flagged = 0;
        foreach ($counts as $category => $weekCounts) {
            foreach ($observedWeeks as $weekStart) {
                $observed = $weekCounts[$weekStart] ?? 0;
                if ($observed < 1) {
                    continue; // no row for an empty observed week
                }

                $baseline = [];
                for ($i = 1; $i <= self::BASELINE_WEEKS; $i++) {
                    $prior = date('Y-m-d', strtotime($weekStart . ' -' . ($i * self::PERIOD_DAYS) . ' days'));
                    $baseline[] = $weekCounts[$prior] ?? 0;
                }
                $mean = array_sum($baseline) / count($baseline);
                $std = self::stddev($baseline, $mean);

                $score = self::score($observed, $mean, $std);
                $periodEnd = date('Y-m-d', strtotime($weekStart . ' +' . (self::PERIOD_DAYS - 1) . ' days'));

                $upsert->execute([
                    mb_substr($category, 0, self::MAX_CATEGORY_LEN),
                    $weekStart,
                    $periodEnd,
                    (int) round($mean),
                    $observed,
                    $score['z'],
                    $score['level'],
                    $score['flagged'] ? 1 : 0,
                    $createdBy,
                ]);
                $upserted++;
                if ($score['flagged']) {
                    $flagged++;
                }
            }
        }

        return [
            'periods' => count($observedWeeks),
            'categories' => count($counts),
            'upserted' => $upserted,
            'flagged' => $flagged,
        ];
    }

    /**
     * Stored detection results overlapping the range, newest/flagged first.
     *
     * @return array<int, array<string, mixed>>
     */
    public function results(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? date('Y-m-d', strtotime('-90 days'));
        $to = $to ?? date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return $this->all(
            'SELECT illness_category, period_start, period_end,
                    baseline_count, observed_count, z_score,
                    alert_level, is_flagged, created_at
               FROM outbreak_analytics
              WHERE period_start <= :to AND period_end >= :from
              ORDER BY is_flagged DESC, period_start DESC, illness_category ASC',
            [':from' => $from, ':to' => $to]
        );
    }

    /**
     * Aggregate statistics for the summary cards.
     *
     * @return array<string, int|string|null>
     */
    public function summaryStats(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? date('Y-m-d', strtotime('-90 days'));
        $to = $to ?? date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $row = $this->all(
            'SELECT COUNT(*) AS total_periods,
                    SUM(is_flagged = 1) AS flagged_periods,
                    SUM(alert_level = \'elevated\') AS elevated_periods,
                    COUNT(DISTINCT illness_category) AS categories,
                    MAX(created_at) AS last_run
               FROM outbreak_analytics
              WHERE period_start <= :to AND period_end >= :from',
            [':from' => $from, ':to' => $to]
        );

        $data = $row[0] ?? [];
        return [
            'total_periods' => (int) ($data['total_periods'] ?? 0),
            'flagged_periods' => (int) ($data['flagged_periods'] ?? 0),
            'elevated_periods' => (int) ($data['elevated_periods'] ?? 0),
            'categories' => (int) ($data['categories'] ?? 0),
            'last_run' => isset($data['last_run']) && $data['last_run'] !== null ? (string) $data['last_run'] : null,
        ];
    }

    // ------------------------------------------------------------------
    // Pure helpers (unit-testable without a database)
    // ------------------------------------------------------------------

    /**
     * Normalize a diagnosis name into a category key: trailing parenthetical
     * qualifiers and "- unspecified"/"- other" suffixes are dropped.
     */
    public static function normalizeCategory(string $name): string
    {
        $name = trim($name);
        $name = (string) preg_replace('/\s*\([^)]*\)\s*$/', '', $name);
        $name = (string) preg_replace('/\s*-\s*(?:unspecified|other|not otherwise specified)\s*$/i', '', $name);
        return trim($name);
    }

    /**
     * First day (Monday) of the ISO week containing the given date.
     */
    public static function weekStart(string $date): string
    {
        $ts = strtotime($date);
        $offset = ((int) date('N', $ts)) - 1; // Monday = 0
        return date('Y-m-d', strtotime('-' . $offset . ' days', $ts));
    }

    /**
     * Monday week-start dates from $from to $to inclusive.
     *
     * @return array<int, string>
     */
    public static function weeksBetween(string $from, string $to): array
    {
        $start = strtotime(self::weekStart($from));
        $end = strtotime(self::weekStart($to));
        $weeks = [];
        for ($t = $start; $t <= $end; $t = strtotime('+7 days', $t)) {
            $weeks[] = date('Y-m-d', $t);
        }
        return $weeks;
    }

    /**
     * Sample standard deviation.
     *
     * @param array<int, float> $values
     */
    public static function stddev(array $values, float $mean): float
    {
        $n = count($values);
        if ($n < 2) {
            return 0.0;
        }
        $variance = 0.0;
        foreach ($values as $value) {
            $variance += ($value - $mean) ** 2;
        }
        return sqrt($variance / ($n - 1));
    }

    /**
     * Map (observed, baseline mean, baseline std) to a z-score + alert level.
     * Small cells (below PRIVACY_MIN_OBSERVED) are always suppressed to
     * 'none' so an individual case is never treated as a cluster.
     *
     * @return array{z: float, level: string, flagged: bool}
     */
    public static function score(int $observed, float $mean, float $std): array
    {
        if ($observed < self::PRIVACY_MIN_OBSERVED) {
            return ['z' => 0.0, 'level' => 'none', 'flagged' => false];
        }

        if ($std > 0.0) {
            $z = ($observed - $mean) / $std;
        } elseif ($mean > 0.0) {
            // No variability in the baseline but a positive rate: express the
            // uplift as a relative difference so spikes are still detected.
            $z = ($observed - $mean) / max($mean, 1.0);
        } else {
            // Brand-new category with enough cases: treat as elevated.
            $z = self::ELEVATED_Z;
        }

        $z = round($z, 3);

        $level = 'none';
        if ($z >= self::ELEVATED_Z) {
            $level = 'elevated';
        } elseif ($z >= self::WARNING_Z) {
            $level = 'warning';
        } elseif ($z >= self::WATCH_Z) {
            $level = 'watch';
        }

        return ['z' => $z, 'level' => $level, 'flagged' => $level !== 'none'];
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Flagged detection rows in a range, for illness-pattern alerts (PROMPT 13).
     * Category-level aggregate only — no identities. The row id is used as the
     * notification reference so re-running detection over the same period does
     * not stack duplicate alerts (the same row is re-flagged, not re-notified).
     *
     * @return array<int, array<string, mixed>>
     */
    public function flaggedRows(?string $from = null, ?string $to = null): array
    {
        $from = $from ?? date('Y-m-d', strtotime('-90 days'));
        $to = $to ?? date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return $this->prepare(
            'SELECT id, illness_category, period_start, period_end, alert_level
               FROM outbreak_analytics
              WHERE is_flagged = 1
                AND period_start <= :to AND period_end >= :from
              ORDER BY period_start DESC, illness_category ASC',
            [':from' => $from, ':to' => $to]
        )->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function all(string $sql, array $params): array
    {
        return $this->prepare($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }
}
