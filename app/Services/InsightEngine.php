<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Personalized health-insight engine.
 *
 * Generates informational, non-diagnostic insights from a student's recorded
 * clinical data. Every insight:
 *
 *   - is informational and understandable
 *   - is NEVER a diagnosis, prescription, or recommendation to start/stop/
 *     change treatment
 *   - directs the student to a healthcare professional where appropriate
 *   - is privacy-preserving (derived from the student's own records only)
 *
 * The generators are pure (no database access) so they can be verified in
 * isolation. `data_version` records the generator version for auditability.
 */
final class InsightEngine
{
    /** Version of the rule set that produced the insights. */
    public const DATA_VERSION = 'rule-based-v1.0';

    public const INSIGHT_TYPES = [
        'visit_pattern',
        'medical_history',
        'recurring_condition',
        'allergy',
        'vital_signs',
        'follow_up',
        'preventive',
    ];

    /** Words that must never appear in insight content (non-diagnostic guard). */
    private const DISALLOWED_TERMS = [
        'you have been diagnosed',
        'you are suffering from',
        'prescrib',
        'prescription',
        'medication',
        'dosage',
        'dose of',
        'stop taking',
        'start taking',
        'change your treatment',
        'cure',
        'treat your',
    ];

    /**
     * Generate every insight that currently applies to a student.
     *
     * @param array<string, mixed>|null $record    health_records row (or null)
     * @param array<int, array<string, mixed>> $histories medical_histories rows
     * @param array<int, array<string, mixed>> $visits   clinic_visits rows with
     *                                                   nested vital_signs
     * @return array<int, array{insight_type:string, title:string, content:string, data_version:string}>
     */
    public static function generateAll(?array $record, array $histories, array $visits): array
    {
        $generators = [
            self::visitVolumeInsight(...),
            self::medicalHistoryInsight(...),
            self::recurringConditionInsight(...),
            self::allergyInsight(...),
            self::vitalsInsight(...),
            self::followUpInsight(...),
            self::preventiveCheckupInsight(...),
        ];

        $insights = [];
        foreach ($generators as $generate) {
            $insight = $generate($record, $histories, $visits);
            if ($insight !== null) {
                $insights[] = $insight;
            }
        }

        return $insights;
    }

    /**
     * Whether generated text contains language that could be read as a
     * diagnosis or treatment advice. Used by the verification suite.
     */
    public static function containsDisallowedLanguage(string $text): bool
    {
        $lower = mb_strtolower($text);
        foreach (self::DISALLOWED_TERMS as $term) {
            if (mb_strpos($lower, $term) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Frequent-visit pattern: several visits inside a short window.
     *
     * @param array<int, array<string, mixed>> $visits
     * @return array<string, string>|null
     */
    public static function visitVolumeInsight(?array $record, array $histories, array $visits): ?array
    {
        $since = date('Y-m-d H:i:s', strtotime('-30 days'));
        $count = 0;
        foreach ($visits as $visit) {
            if (strtotime((string) ($visit['visited_at'] ?? '')) >= strtotime($since)) {
                $count++;
            }
        }
        if ($count < 2) {
            return null;
        }

        return [
            'insight_type' => 'visit_pattern',
            'title' => 'Frequent clinic visits',
            'content' => 'Your records show ' . $count
                . ' clinic visit(s) in the last 30 days. This is informational only. '
                . 'If you have questions about your care, please speak with a healthcare professional.',
            'data_version' => self::DATA_VERSION,
        ];
    }

    /**
     * Active medical-history conditions worth keeping visible to the student.
     *
     * @param array<int, array<string, mixed>> $histories
     * @return array<string, string>|null
     */
    public static function medicalHistoryInsight(?array $record, array $histories, array $visits): ?array
    {
        $active = [];
        foreach ($histories as $h) {
            if (($h['status'] ?? '') === 'active' && trim((string) $h['condition_name']) !== '') {
                $active[] = trim((string) $h['condition_name']);
            }
        }
        if ($active === []) {
            return null;
        }

        return [
            'insight_type' => 'medical_history',
            'title' => 'Active health-history records',
            'content' => 'You have an active health-history record for: ' . implode(', ', array_slice($active, 0, 5))
                . '. This is informational only. Please discuss any concerns with a healthcare professional.',
            'data_version' => self::DATA_VERSION,
        ];
    }

    /**
     * Recurring conditions on record.
     *
     * @param array<int, array<string, mixed>> $histories
     * @return array<string, string>|null
     */
    public static function recurringConditionInsight(?array $record, array $histories, array $visits): ?array
    {
        $recurring = [];
        foreach ($histories as $h) {
            if (!empty($h['is_recurring']) && trim((string) $h['condition_name']) !== '') {
                $recurring[] = trim((string) $h['condition_name']);
            }
        }
        if ($recurring === []) {
            return null;
        }

        return [
            'insight_type' => 'recurring_condition',
            'title' => 'Recurring conditions on record',
            'content' => 'A recurring condition is recorded for: ' . implode(', ', array_slice($recurring, 0, 5))
                . '. This is informational only. Clinic staff can help you understand how to manage your wellbeing.',
            'data_version' => self::DATA_VERSION,
        ];
    }

    /**
     * Allergies on file (so the student remembers to inform staff).
     *
     * @return array<string, string>|null
     */
    public static function allergyInsight(?array $record, array $histories, array $visits): ?array
    {
        $allergies = trim((string) ($record['allergies'] ?? ''));
        if ($allergies === '' || $allergies === 'None' || $allergies === 'none') {
            return null;
        }

        return [
            'insight_type' => 'allergy',
            'title' => 'Allergy record reminder',
            'content' => 'An allergy record (' . mb_substr($allergies, 0, 100)
                . ') is on file. Please make sure clinic staff are aware of this during any visit.',
            'data_version' => self::DATA_VERSION,
        ];
    }

    /**
     * Repeated elevated blood-pressure readings (data-descriptive only).
     *
     * @param array<int, array<string, mixed>> $visits
     * @return array<string, string>|null
     */
    public static function vitalsInsight(?array $record, array $histories, array $visits): ?array
    {
        $high = [];
        foreach ($visits as $visit) {
            foreach (($visit['vital_signs'] ?? []) as $vs) {
                $sys = $vs['blood_pressure_sys'] ?? null;
                $dia = $vs['blood_pressure_dia'] ?? null;
                if ($sys !== null && $dia !== null && (int) $sys >= 140 && (int) $dia >= 90) {
                    $high[] = [
                        'read_at' => (string) ($vs['measured_at'] ?? ''),
                        'sys' => (int) $sys,
                        'dia' => (int) $dia,
                    ];
                }
            }
        }
        if (count($high) < 2) {
            return null;
        }

        return [
            'insight_type' => 'vital_signs',
            'title' => 'Blood-pressure readings above 140/90 mmHg',
            'content' => 'Your records contain ' . count($high)
                . ' blood-pressure reading(s) at or above 140/90 mmHg. This is informational only and is not a '
                . 'diagnosis. Please consult a healthcare professional if you have concerns.',
            'data_version' => self::DATA_VERSION,
        ];
    }

    /**
     * Recent referral/admission visit: encourage following clinic guidance.
     *
     * @param array<int, array<string, mixed>> $visits
     * @return array<string, string>|null
     */
    public static function followUpInsight(?array $record, array $histories, array $visits): ?array
    {
        $since = date('Y-m-d H:i:s', strtotime('-90 days'));
        $found = false;
        foreach ($visits as $visit) {
            if (strtotime((string) ($visit['visited_at'] ?? '')) >= strtotime($since)
                && in_array((string) ($visit['outcome'] ?? ''), ['referred', 'admitted'], true)) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            return null;
        }

        return [
            'insight_type' => 'follow_up',
            'title' => 'Follow-up after a recent visit',
            'content' => 'A recent visit recorded a referral or admission. Please follow any guidance given by the '
                . 'clinic and contact a healthcare professional if you have any concerns.',
            'data_version' => self::DATA_VERSION,
        ];
    }

    /**
     * Preventive: more than 12 months since a routine check-up.
     *
     * @param array<int, array<string, mixed>> $visits
     * @return array<string, string>|null
     */
    public static function preventiveCheckupInsight(?array $record, array $histories, array $visits): ?array
    {
        $latestRoutine = 0;
        foreach ($visits as $visit) {
            if (($visit['visit_type'] ?? '') === 'routine') {
                $t = strtotime((string) ($visit['visited_at'] ?? ''));
                if ($t > $latestRoutine) {
                    $latestRoutine = $t;
                }
            }
        }
        if ($latestRoutine > 0 && (time() - $latestRoutine) < 365 * 24 * 3600) {
            return null;
        }

        return [
            'insight_type' => 'preventive',
            'title' => 'Routine check-up',
            'content' => 'It has been more than a year since a routine check-up was recorded for you, or you have no '
                . 'routine check-up on file. Consider booking a routine appointment at the clinic if you wish.',
            'data_version' => self::DATA_VERSION,
        ];
    }
}
