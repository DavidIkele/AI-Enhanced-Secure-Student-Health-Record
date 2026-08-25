<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * De-identified feature vector builder for the AI decision-support service
 *.
 *
 * Only aggregate, numeric, non-identifying features are ever computed and sent
 * to the FastAPI service. No names, reg numbers, dates, notes or clinical free
 * text leave this boundary — the features are exactly the keys the registered
 * models declare in their features.json manifest.
 *
 * Heuristic definitions are documented per feature below. They are decision
 * SUPPORT only and are deliberately simple, explainable and deterministic.
 */
final class PredictiveFeatures
{
    private const TYPE_MALARIA = 'malaria_risk';
    private const TYPE_ASTHMA = 'asthma_exacerbation';
    private const TYPE_TYPHOID = 'typhoid_risk';

    private const FEVER_KEYWORDS = ['malaria', 'typhoid', 'fever', 'febrile'];
    private const TYPHOID_KEYWORDS = ['typhoid', 'unclean water', 'unsafe water', 'contaminated water', 'food poisoning'];
    private const EXERCISE_KEYWORDS = ['exercise', 'sport', 'physical activity', 'running', 'football', 'gym'];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    /**
     * Compute the feature vector for a given prediction type and student.
     *
     * @return array<string, float> always contains every declared feature (0.0
     *                               when the data source is absent)
     */
    public function build(int $studentId, string $predictionType): array
    {
        return match ($predictionType) {
            self::TYPE_MALARIA => $this->malariaFeatures($studentId),
            self::TYPE_ASTHMA => $this->asthmaFeatures($studentId),
            self::TYPE_TYPHOID => $this->typhoidFeatures($studentId),
            default => throw new \InvalidArgumentException('Unsupported prediction type: ' . $predictionType),
        };
    }

    /**
     * recent_visits_30d — number of clinic visits recorded in the last 30 days.
     * fever_history       — 1.0 when any diagnosis/condition mentions a febrile
     *                        illness (malaria/typhoid/fever/febrile).
     * season_rainy        — 1.0 April–October (Nigeria wet season), else 0.0.
     *
     * @return array<string, float>
     */
    private function malariaFeatures(int $studentId): array
    {
        return [
            'recent_visits_30d' => (float) $this->recentVisitCount($studentId),
            'fever_history' => $this->hasKeywordCondition($studentId, self::FEVER_KEYWORDS) ? 1.0 : 0.0,
            'season_rainy' => $this->isWetSeason() ? 1.0 : 0.0,
        ];
    }

    /**
     * history_asthma   — 1.0 when a medical history / diagnosis references
     *                    asthma or wheeze.
     * recent_visits_30d — clinic visits in the last 30 days.
     * exercise_related — 1.0 when a visit reason or chief complaint references
     *                    exercise / physical activity.
     *
     * @return array<string, float>
     */
    private function asthmaFeatures(int $studentId): array
    {
        return [
            'history_asthma' => $this->hasKeywordCondition($studentId, ['asthma', 'wheeze']) ? 1.0 : 0.0,
            'recent_visits_30d' => (float) $this->recentVisitCount($studentId),
            'exercise_related' => $this->hasVisitKeyword($studentId, self::EXERCISE_KEYWORDS) ? 1.0 : 0.0,
        ];
    }

    /**
     * unclean_water_exposure — 1.0 when a diagnosis/condition/visit reason
     *                          references typhoid or unsafe water.
     * recent_visits_30d      — clinic visits in the last 30 days.
     * fever_history          — 1.0 when any febrile illness is documented.
     *
     * @return array<string, float>
     */
    private function typhoidFeatures(int $studentId): array
    {
        return [
            'unclean_water_exposure' => $this->hasKeywordCondition($studentId, self::TYPHOID_KEYWORDS)
                || $this->hasVisitKeyword($studentId, self::TYPHOID_KEYWORDS) ? 1.0 : 0.0,
            'recent_visits_30d' => (float) $this->recentVisitCount($studentId),
            'fever_history' => $this->hasKeywordCondition($studentId, self::FEVER_KEYWORDS) ? 1.0 : 0.0,
        ];
    }

    private function recentVisitCount(int $studentId): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*)
               FROM clinic_visits
              WHERE student_id = :sid
                AND visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * True when medical_histories.condition_name/description or
     * diagnoses.name/description contains any of the keywords. Keyword matching
     * happens in PHP so the parameterised SQL stays a plain equality/scalar
     * scan with no LIKE injection surface.
     *
     * @param list<string> $keywords
     */
    private function hasKeywordCondition(int $studentId, array $keywords): bool
    {
        $stmt = $this->db->prepare(
            'SELECT condition_name, description FROM medical_histories WHERE student_id = :sid'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($this->matchesAny($row['condition_name'], $row['description'], $keywords)) {
                return true;
            }
        }

        $stmt = $this->db->prepare(
            'SELECT d.name, d.description
               FROM diagnoses d
               JOIN clinic_visits cv ON cv.id = d.clinic_visit_id
              WHERE cv.student_id = :sid'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($this->matchesAny($row['name'], $row['description'], $keywords)) {
                return true;
            }
        }
        return false;
    }

    /**
     * True when a clinic visit reason / chief complaint contains a keyword.
     *
     * @param list<string> $keywords
     */
    private function hasVisitKeyword(int $studentId, array $keywords): bool
    {
        $stmt = $this->db->prepare(
            'SELECT reason, chief_complaint FROM clinic_visits WHERE student_id = :sid'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($this->matchesAny($row['reason'], $row['chief_complaint'], $keywords)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param list<string> $keywords
     */
    private function matchesAny(?string $a, ?string $b, array $keywords): bool
    {
        $haystack = mb_strtolower(trim(($a ?? '') . ' ' . ($b ?? '')));
        if ($haystack === '') {
            return false;
        }
        foreach ($keywords as $keyword) {
            if (str_contains($haystack, $keyword)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Nigeria wet season approximation: April through October inclusive.
     */
    private function isWetSeason(): bool
    {
        $month = (int) date('n');
        return $month >= 4 && $month <= 10;
    }
}
