<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Visit History Analytics — non-AI aggregate analytics.
 *
 * All queries return only aggregate counts. Individual student identities
 * (names, reg numbers, emails) are never selected, so the results cannot be
 * used to attribute data to a specific student. Small cell suppression is
 * applied to categories with very few contributing students to reduce the
 * risk of re-identification (see suppressSmallCells / PRIVACY_MIN_CELL).
 *
 * Every method accepts an optional inclusive date range [from, to] using
 * ISO-8601 date strings (YYYY-MM-DD). An omitted bound means "no limit".
 */
final class AnalyticsRepository extends BaseRepository
{
    /**
     * Minimum number of distinct students in a category before its exact
     * count is disclosed. Categories below this are suppressed to avoid
     * re-identifying individuals from very small groups.
     */
    public const PRIVACY_MIN_CELL = 3;

    /**
     * Summary statistics for the range.
     *
     * @return array<string, int|float|array<int, array<string, mixed>>>
     */
    public function summary(?string $from = null, ?string $to = null): array
    {
        $where = $this->dateWhereClause('visited_at', $from, $to);
        $params = $this->dateParams($from, $to);

        $total = $this->scalar(
            'SELECT COUNT(*) FROM clinic_visits' . $where,
            $params
        );

        $uniqueStudents = $this->scalar(
            'SELECT COUNT(DISTINCT student_id) FROM clinic_visits' . $where,
            $params
        );

        $byType = $this->all(
            'SELECT visit_type AS label, COUNT(*) AS count
               FROM clinic_visits' . $where . '
              GROUP BY visit_type
              ORDER BY count DESC, label ASC',
            $params
        );

        $byStatus = $this->all(
            'SELECT status AS label, COUNT(*) AS count
               FROM clinic_visits' . $where . '
              GROUP BY status
              ORDER BY count DESC, label ASC',
            $params
        );

        $byOutcome = $this->all(
            'SELECT COALESCE(outcome, \'not recorded\') AS label, COUNT(*) AS count
               FROM clinic_visits' . $where . '
              GROUP BY COALESCE(outcome, \'not recorded\')
              ORDER BY count DESC, label ASC',
            $params
        );

        return [
            'total_visits' => $total,
            'unique_students' => $uniqueStudents,
            'avg_visits_per_student' => $uniqueStudents > 0 ? round($total / $uniqueStudents, 2) : 0.0,
            'by_type' => $byType,
            'by_status' => $byStatus,
            'by_outcome' => $byOutcome,
        ];
    }

    /**
     * Attendance trend: visits and unique students per calendar month.
     *
     * @return array<int, array<string, mixed>>
     */
    public function attendanceTrend(?string $from = null, ?string $to = null): array
    {
        return $this->all(
            'SELECT DATE_FORMAT(visited_at, \'%Y-%m\') AS period,
                    COUNT(*) AS visits,
                    COUNT(DISTINCT student_id) AS students
               FROM clinic_visits' . $this->dateWhereClause('visited_at', $from, $to) . '
              GROUP BY DATE_FORMAT(visited_at, \'%Y-%m\')
              ORDER BY period ASC',
            $this->dateParams($from, $to)
        );
    }

    /**
     * Time-based trend: visits by hour of the day (0-23).
     *
     * @return array<int, array<string, mixed>>
     */
    public function visitsByHour(?string $from = null, ?string $to = null): array
    {
        $rows = $this->all(
            'SELECT HOUR(visited_at) AS hour, COUNT(*) AS visits
               FROM clinic_visits' . $this->dateWhereClause('visited_at', $from, $to) . '
              GROUP BY HOUR(visited_at)
              ORDER BY hour ASC',
            $this->dateParams($from, $to)
        );

        // Normalise to a full 24-bucket series so charts/accessibility tables
        // always render the same shape regardless of the data present.
        $buckets = [];
        for ($h = 0; $h < 24; $h++) {
            $buckets[$h] = ['hour' => $h, 'visits' => 0];
        }
        foreach ($rows as $row) {
            $buckets[(int) $row['hour']]['visits'] = (int) $row['visits'];
        }
        return array_values($buckets);
    }

    /**
     * Time-based trend: visits by weekday.
     *
     * @return array<int, array<string, mixed>>
     */
    public function visitsByWeekday(?string $from = null, ?string $to = null): array
    {
        $names = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $rows = $this->all(
            'SELECT DAYOFWEEK(visited_at) AS dow, COUNT(*) AS visits
               FROM clinic_visits' . $this->dateWhereClause('visited_at', $from, $to) . '
              GROUP BY DAYOFWEEK(visited_at)
              ORDER BY dow ASC',
            $this->dateParams($from, $to)
        );

        $buckets = [];
        foreach ($names as $i => $name) {
            $buckets[$i] = ['day' => $name, 'visits' => 0];
        }
        foreach ($rows as $row) {
            $buckets[(int) $row['dow'] - 1]['visits'] = (int) $row['visits'];
        }
        return $buckets;
    }

    /**
     * Illness frequency: diagnosis names ranked by number of visits carrying
     * that diagnosis within the range.
     *
     * @return array<int, array<string, mixed>>
     */
    public function illnessFrequency(?string $from = null, ?string $to = null, int $limit = 10): array
    {
        $where = $this->dateConditions('cv.visited_at', $from, $to);
        $params = $this->dateParams($from, $to);

        $sql = 'SELECT d.name AS illness,
                       COUNT(*) AS visit_count,
                       COUNT(DISTINCT cv.student_id) AS student_count
                  FROM diagnoses d
                  JOIN clinic_visits cv ON cv.id = d.clinic_visit_id'
                . ($where === '' ? '' : ' WHERE ' . $where) . '
                 GROUP BY d.name
                 ORDER BY visit_count DESC, illness ASC
                 LIMIT ' . max(1, $limit);

        return $this->all($sql, $params);
    }

    /**
     * Recurring conditions: conditions flagged as recurring in medical
     * histories, plus diagnoses seen on more than one distinct visit within
     * the range (a proxy for recurring presentations).
     *
     * @return array<int, array<string, mixed>>
     */
    public function recurringConditions(?string $from = null, ?string $to = null): array
    {
        $where = $this->dateConditions('mh.created_at', $from, $to);
        $params = $this->dateParams($from, $to);

        $flagged = $this->all(
            'SELECT mh.condition_name AS condition_name,
                    COUNT(DISTINCT mh.student_id) AS student_count,
                    COUNT(*) AS record_count
               FROM medical_histories mh
              WHERE mh.is_recurring = 1'
                . ($where === '' ? '' : ' AND ' . $where) . '
              GROUP BY mh.condition_name
              ORDER BY student_count DESC, record_count DESC, condition_name ASC',
            $params
        );

        // Repeat diagnoses across distinct visits in the range.
        // NOTE: the derived table aliases the visits table as cv2, so it needs
        // its own date conditions; reusing the outer cv.* clause here would be
        // invalid ("Unknown column cv.visited_at") once a range is supplied.
        // Distinct parameter names are required because native prepared
        // statements reject a named parameter referenced in two clauses.
        // Bounds use the same half-open mapping as dateParams().
        $repeatParams = [];
        if ($from !== null) {
            $start = $from . ' 00:00:00';
            $repeatParams[':date_from'] = $start;
            $repeatParams[':date_from_inner'] = $start;
        }
        if ($to !== null) {
            $excl = date('Y-m-d 00:00:00', strtotime($to . ' +1 day'));
            $repeatParams[':date_excl'] = $excl;
            $repeatParams[':date_excl_inner'] = $excl;
        }
        $repeatWhere = $this->dateConditions('cv.visited_at', $from, $to);
        $repeatInnerWhere = $this->dateConditions('cv2.visited_at', $from, $to, '_inner');
        $repeats = $this->all(
            'SELECT d.name AS condition_name,
                    COUNT(DISTINCT cv.student_id) AS student_count,
                    COUNT(DISTINCT cv.id) AS record_count
               FROM diagnoses d
               JOIN clinic_visits cv ON cv.id = d.clinic_visit_id
               JOIN (
                    SELECT d2.name, cv2.student_id, COUNT(DISTINCT cv2.id) AS seen
                      FROM diagnoses d2
                      JOIN clinic_visits cv2 ON cv2.id = d2.clinic_visit_id'
                . ($repeatInnerWhere === '' ? '' : ' WHERE ' . $repeatInnerWhere) . '
                     GROUP BY d2.name, cv2.student_id
                    HAVING COUNT(DISTINCT cv2.id) >= 2
               ) rep ON rep.name = d.name AND rep.student_id = cv.student_id'
                . ($repeatWhere === '' ? '' : ' WHERE ' . $repeatWhere) . '
              GROUP BY d.name
              ORDER BY student_count DESC, record_count DESC, condition_name ASC',
            $repeatParams
        );

        // Merge both sources by condition name.
        $merged = [];
        foreach ($flagged as $row) {
            $merged[$row['condition_name']] = [
                'condition_name' => $row['condition_name'],
                'student_count' => (int) $row['student_count'],
                'record_count' => (int) $row['record_count'],
                'source' => 'flagged',
            ];
        }
        foreach ($repeats as $row) {
            $name = $row['condition_name'];
            if (isset($merged[$name])) {
                $merged[$name]['student_count'] = max($merged[$name]['student_count'], (int) $row['student_count']);
                $merged[$name]['record_count'] += (int) $row['record_count'];
                $merged[$name]['source'] = 'flagged+repeat';
            } else {
                $merged[$name] = [
                    'condition_name' => $name,
                    'student_count' => (int) $row['student_count'],
                    'record_count' => (int) $row['record_count'],
                    'source' => 'repeat',
                ];
            }
        }

        usort($merged, static function (array $a, array $b): int {
            return [$b['student_count'], $b['record_count']] <=> [$a['student_count'], $a['record_count']];
        });

        return array_values($merged);
    }

    /**
     * Aggregate statistics for the accessibility summary.
     *
     * @return array<string, int|float>
     */
    public function aggregateStats(?string $from = null, ?string $to = null): array
    {
        $summary = $this->summary($from, $to);
        $trend = $this->attendanceTrend($from, $to);

        $monthCount = count($trend);
        $total = (int) $summary['total_visits'];
        $avgMonthly = $monthCount > 0 ? round($total / $monthCount, 2) : 0.0;

        return [
            'total_visits' => $total,
            'unique_students' => (int) $summary['unique_students'],
            'avg_visits_per_student' => (float) $summary['avg_visits_per_student'],
            'months_with_visits' => $monthCount,
            'avg_visits_per_month' => $avgMonthly,
        ];
    }

    /**
     * Small cell suppression. For each row the given count key is compared
     * against the privacy minimum; values below the threshold are replaced
     * with null and flagged so views/consumers never disclose an exact small
     * count. The row is returned unmodified otherwise.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function suppressSmallCells(array $rows, string $countKey = 'student_count', int $minCell = self::PRIVACY_MIN_CELL): array
    {
        $out = [];
        foreach ($rows as $row) {
            $value = $row[$countKey] ?? null;
            if (is_numeric($value) && (int) $value < $minCell) {
                $row[$countKey] = null;
                $row['suppressed'] = true;
            } else {
                $row['suppressed'] = false;
            }
            $out[] = $row;
        }
        return $out;
    }

    /**
     * Build the date-range conditions (without the WHERE keyword) for an
     * inclusive date range on a DATETIME column. Bounds are expressed as a
     * half-open interval on the raw column (column >= :date_from AND
     * column < :date_excl) instead of wrapping the column in DATE(), so the
     * range predicate can use a column index. An optional suffix
     * distinguishes parameter names when a query needs more than one range
     * clause.
     */
    private function dateConditions(string $column, ?string $from, ?string $to, string $suffix = ''): string
    {
        $clauses = [];
        if ($from !== null) {
            $clauses[] = "$column >= :date_from$suffix";
        }
        if ($to !== null) {
            $clauses[] = "$column < :date_excl$suffix";
        }
        return implode(' AND ', $clauses);
    }

    /**
     * Build a full WHERE clause including the WHERE keyword, or empty string
     * if no bounds are provided.
     */
    private function dateWhereClause(string $column, ?string $from, ?string $to): string
    {
        $conditions = $this->dateConditions($column, $from, $to);
        return $conditions === '' ? '' : ' WHERE ' . $conditions;
    }

    /**
     * Inclusive date [from, to] is mapped to a half-open interval
     * [from 00:00:00, next-day 00:00:00).
     *
     * @return array<string, string>
     */
    private function dateParams(?string $from, ?string $to): array
    {
        $params = [];
        if ($from !== null) {
            $params[':date_from'] = $from . ' 00:00:00';
        }
        if ($to !== null) {
            $params[':date_excl'] = date('Y-m-d 00:00:00', strtotime($to . ' +1 day'));
        }
        return $params;
    }

    private function scalar(string $sql, array $params): int
    {
        return (int) $this->prepare($sql, $params)->fetchColumn();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function all(string $sql, array $params): array
    {
        return $this->prepare($sql, $params)->fetchAll(PDO::FETCH_ASSOC);
    }
}
