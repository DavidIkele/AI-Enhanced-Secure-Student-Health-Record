<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Health record data access. One active profile per student (unique student_id).
 *
 * Scope: the health profile, medical histories, clinic visits and the
 * clinical detail rows (diagnoses, treatments, medications, vital signs) that
 * belong to a visit. All queries use PDO prepared statements exclusively.
 */
final class HealthRecordRepository extends BaseRepository
{
    /**
     * The single health profile for a student.
     *
     * @return array<string, mixed>|null
     */
    public function activeProfileForStudent(int $studentId): ?array
    {
        $stmt = $this->prepare(
            'SELECT id, student_id, blood_group, genotype, height_cm, weight_kg,
                    allergies, chronic_conditions, disabilities, family_history, notes,
                    created_by, updated_by, created_at, updated_at
               FROM health_records
              WHERE student_id = :sid
              LIMIT 1',
            [':sid' => $studentId]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Create the health profile for a student (used when none exists yet).
     *
     * @param array<string, mixed> $data
     */
    public function createProfile(int $studentId, array $data, int $userId): void
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO health_records
                   (student_id, blood_group, genotype, height_cm, weight_kg, allergies,
                    chronic_conditions, disabilities, family_history, notes,
                    created_by, updated_by)
                 VALUES (:sid, :blood_group, :genotype, :height, :weight, :allergies,
                    :chronic, :disabilities, :family_history, :notes,
                    :created_by, :updated_by)'
            );
            $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
            $stmt->bindValue(':blood_group', $data['blood_group'] ?? 'Unknown', PDO::PARAM_STR);
            $stmt->bindValue(':genotype', $data['genotype'] ?? 'Unknown', PDO::PARAM_STR);
            $stmt->bindValue(':height', $data['height_cm'] !== '' && $data['height_cm'] !== null ? (float) $data['height_cm'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':weight', $data['weight_kg'] !== '' && $data['weight_kg'] !== null ? (float) $data['weight_kg'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':allergies', $data['allergies'] !== '' ? $data['allergies'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':chronic', $data['chronic_conditions'] !== '' ? $data['chronic_conditions'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':disabilities', $data['disabilities'] !== '' ? $data['disabilities'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':family_history', $data['family_history'] !== '' ? $data['family_history'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':notes', $data['notes'] !== '' ? $data['notes'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':created_by', $userId, PDO::PARAM_INT);
            $stmt->bindValue(':updated_by', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Update the health profile for a student.
     *
     * @param array<string, mixed> $data
     */
    public function updateProfile(int $studentId, array $data, int $userId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE health_records
                SET blood_group = :blood_group,
                    genotype = :genotype,
                    height_cm = :height,
                    weight_kg = :weight,
                    allergies = :allergies,
                    chronic_conditions = :chronic,
                    disabilities = :disabilities,
                    family_history = :family_history,
                    notes = :notes,
                    updated_by = :user_id
              WHERE student_id = :sid'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':blood_group', $data['blood_group'] ?? 'Unknown', PDO::PARAM_STR);
        $stmt->bindValue(':genotype', $data['genotype'] ?? 'Unknown', PDO::PARAM_STR);
        $stmt->bindValue(':height', $data['height_cm'] !== '' && $data['height_cm'] !== null ? (float) $data['height_cm'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':weight', $data['weight_kg'] !== '' && $data['weight_kg'] !== null ? (float) $data['weight_kg'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':allergies', $data['allergies'] !== '' ? $data['allergies'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':chronic', $data['chronic_conditions'] !== '' ? $data['chronic_conditions'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':disabilities', $data['disabilities'] !== '' ? $data['disabilities'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':family_history', $data['family_history'] !== '' ? $data['family_history'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':notes', $data['notes'] !== '' ? $data['notes'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Medical history entries for a student.
     *
     * @return array<int, array<string, mixed>>
     */
    public function medicalHistoriesForStudent(int $studentId): array
    {
        $stmt = $this->prepare(
            'SELECT id, student_id, condition_name, description, onset_date,
                    is_recurring, severity, status, recorded_by, created_at, updated_at
               FROM medical_histories
              WHERE student_id = :sid
              ORDER BY COALESCE(onset_date, created_at) DESC, created_at DESC',
            [':sid' => $studentId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a medical history entry for a student.
     *
     * @param array<string, mixed> $data
     * @return int new medical history id
     */
    public function createMedicalHistory(int $studentId, array $data, int $userId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medical_histories
               (student_id, condition_name, description, onset_date, is_recurring,
                severity, status, recorded_by)
             VALUES (:sid, :condition_name, :description, :onset_date, :is_recurring,
                :severity, :status, :user_id)'
        );
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':condition_name', $data['condition_name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $data['description'] !== '' ? $data['description'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':onset_date', $data['onset_date'] !== '' ? $data['onset_date'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':is_recurring', !empty($data['is_recurring']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':severity', $data['severity'] ?? 'mild', PDO::PARAM_STR);
        $stmt->bindValue(':status', $data['status'] ?? 'active', PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return (int) $this->db->lastInsertId();
    }

    /**
     * Clinic visits for a student, each with its clinical detail rows.
     *
     * The detail rows (diagnoses, treatments, medications, vital signs) are
     * fetched in bulk with a single IN(...) query per table instead of one
     * query per visit, so the total cost is 5 queries regardless of how many
     * visits the student has (previously 1 + 4N).
     *
     * @return array<int, array<string, mixed>>
     */
    public function clinicVisitsForStudent(int $studentId): array
    {
        $stmt = $this->prepare(
            'SELECT cv.id, cv.student_id, cv.healthcare_staff_id, cv.visited_at, cv.visit_type,
                    cv.reason, cv.chief_complaint, cv.assessment_notes, cv.outcome, cv.status,
                    cv.created_by, cv.created_at, cv.updated_at,
                    hs.title AS staff_title, hs.first_name AS staff_first,
                    hs.last_name AS staff_last, hs.role_name
               FROM clinic_visits cv
               LEFT JOIN healthcare_staff hs ON hs.id = cv.healthcare_staff_id AND hs.deleted_at IS NULL
              WHERE cv.student_id = :sid
              ORDER BY cv.visited_at DESC, cv.created_at DESC',
            [':sid' => $studentId]
        );
        $visits = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($visits === []) {
            return [];
        }

        $visitIds = array_map(static fn (array $visit): int => (int) $visit['id'], $visits);

        $diagnoses = $this->rowsForVisitIds('diagnoses', $visitIds);
        $treatments = $this->rowsForVisitIds('treatments', $visitIds);
        $medications = $this->rowsForVisitIds('medications', $visitIds);
        $vitalSigns = $this->rowsForVisitIds('vital_signs', $visitIds);

        foreach ($visits as &$visit) {
            $visitId = (int) $visit['id'];
            $visit['diagnoses'] = $diagnoses[$visitId] ?? [];
            $visit['treatments'] = $treatments[$visitId] ?? [];
            $visit['medications'] = $medications[$visitId] ?? [];
            $visit['vital_signs'] = $vitalSigns[$visitId] ?? [];
        }
        unset($visit);

        return $visits;
    }

    /**
     * Fetch detail rows for many visits in one query. Returns rows grouped by
     * clinic_visit_id (keyed as int).
     *
     * @param array<int, int> $visitIds
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function rowsForVisitIds(string $table, array $visitIds): array
    {
        $columns = match ($table) {
            'diagnoses' => 'id, clinic_visit_id, icd_code, name, description, severity,
                           is_primary, diagnosed_by, diagnosed_at',
            'treatments' => 'id, clinic_visit_id, diagnosis_id, name, description,
                             treatment_type, started_at, ended_at, status, prescribed_by',
            'medications' => 'id, treatment_id, clinic_visit_id, name, dosage, frequency,
                              route, quantity, duration_days, instructions, status,
                              prescribed_by, prescribed_at',
            'vital_signs' => 'id, clinic_visit_id, temperature_c, blood_pressure_sys,
                              blood_pressure_dia, heart_rate, respiratory_rate,
                              oxygen_saturation, weight_kg, height_cm, bmi, measured_at',
            default => throw new \InvalidArgumentException('Unknown detail table: ' . $table),
        };
        $orderBy = $table === 'vital_signs'
            ? 'ORDER BY measured_at DESC'
            : ($table === 'diagnoses' ? 'ORDER BY is_primary DESC, id ASC' : 'ORDER BY id ASC');

        $byVisit = [];
        $placeholders = implode(',', array_fill(0, count($visitIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT $columns FROM $table WHERE clinic_visit_id IN ($placeholders) $orderBy"
        );
        $stmt->execute($visitIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $byVisit[(int) $row['clinic_visit_id']][] = $row;
        }
        return $byVisit;
    }

    /**
     * Create a clinic visit together with its optional diagnoses, treatments,
     * medications and vital signs in a single transaction.
     *
     * @param array<string, mixed> $data
     * @param array<int, array<string, mixed>> $diagnoses
     * @param array<int, array<string, mixed>> $treatments
     * @param array<int, array<string, mixed>> $medications
     * @param array<string, mixed>|null $vitals
     * @return int new clinic visit id
     */
    public function createClinicVisit(
        int $studentId,
        array $data,
        int $userId,
        array $diagnoses = [],
        array $treatments = [],
        array $medications = [],
        ?array $vitals = null
    ): int {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO clinic_visits
                   (student_id, healthcare_staff_id, visited_at, visit_type, reason,
                    chief_complaint, assessment_notes, outcome, status, created_by)
                 VALUES (:sid, :staff_id, :visited_at, :visit_type, :reason,
                    :chief_complaint, :assessment_notes, :outcome, :status, :user_id)'
            );
            $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
            $stmt->bindValue(':staff_id', null, PDO::PARAM_NULL);
            $stmt->bindValue(':visited_at', $data['visited_at'], PDO::PARAM_STR);
            $stmt->bindValue(':visit_type', $data['visit_type'] ?? 'routine', PDO::PARAM_STR);
            $stmt->bindValue(':reason', $data['reason'], PDO::PARAM_STR);
            $stmt->bindValue(':chief_complaint', $data['chief_complaint'] !== '' ? $data['chief_complaint'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':assessment_notes', $data['assessment_notes'] !== '' ? $data['assessment_notes'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':outcome', $data['outcome'] !== '' ? $data['outcome'] : null, PDO::PARAM_STR);
            $stmt->bindValue(':status', $data['status'] ?? 'open', PDO::PARAM_STR);
            $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $visitId = (int) $this->db->lastInsertId();

            foreach ($diagnoses as $d) {
                $this->insertDiagnosis($visitId, $d, $userId);
            }

            foreach ($treatments as $t) {
                $this->insertTreatment($visitId, $t, $userId);
            }

            foreach ($medications as $m) {
                $this->insertMedication($visitId, $m, $userId);
            }

            if ($vitals !== null) {
                $this->insertVitalSigns($studentId, $visitId, $vitals, $userId);
            }

            $this->db->commit();
            return $visitId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $d
     */
    private function insertDiagnosis(int $visitId, array $d, int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO diagnoses
               (clinic_visit_id, icd_code, name, description, severity, is_primary,
                diagnosed_by, diagnosed_at)
             VALUES (:vid, :icd, :name, :description, :severity, :is_primary,
                :user_id, :diagnosed_at)'
        );
        $stmt->bindValue(':vid', $visitId, PDO::PARAM_INT);
        $stmt->bindValue(':icd', $d['icd_code'] !== '' ? $d['icd_code'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':name', $d['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $d['description'] !== '' ? $d['description'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':severity', $d['severity'] ?? 'mild', PDO::PARAM_STR);
        $stmt->bindValue(':is_primary', !empty($d['is_primary']) ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':diagnosed_at', $d['diagnosed_at'] !== '' ? $d['diagnosed_at'] : null, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param array<string, mixed> $t
     */
    private function insertTreatment(int $visitId, array $t, int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO treatments
               (clinic_visit_id, diagnosis_id, name, description, treatment_type,
                started_at, ended_at, status, prescribed_by)
             VALUES (:vid, NULL, :name, :description, :treatment_type,
                :started_at, :ended_at, :status, :user_id)'
        );
        $stmt->bindValue(':vid', $visitId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $t['name'], PDO::PARAM_STR);
        $stmt->bindValue(':description', $t['description'] !== '' ? $t['description'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':treatment_type', $t['treatment_type'] ?? 'other', PDO::PARAM_STR);
        $stmt->bindValue(':started_at', $t['started_at'] !== '' ? $t['started_at'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':ended_at', $t['ended_at'] !== '' ? $t['ended_at'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':status', $t['status'] ?? 'planned', PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @param array<string, mixed> $m
     */
    private function insertMedication(int $visitId, array $m, int $userId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO medications
               (treatment_id, clinic_visit_id, name, dosage, frequency, route,
                quantity, duration_days, instructions, status, prescribed_by, prescribed_at)
             VALUES (NULL, :vid, :name, :dosage, :frequency, :route,
                :quantity, :duration_days, :instructions, :status, :user_id, :prescribed_at)'
        );
        $stmt->bindValue(':vid', $visitId, PDO::PARAM_INT);
        $stmt->bindValue(':name', $m['name'], PDO::PARAM_STR);
        $stmt->bindValue(':dosage', $m['dosage'], PDO::PARAM_STR);
        $stmt->bindValue(':frequency', $m['frequency'] !== '' ? $m['frequency'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':route', $m['route'] !== '' ? $m['route'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':quantity', $m['quantity'] !== '' ? $m['quantity'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':duration_days', $m['duration_days'] !== '' && $m['duration_days'] !== null ? (int) $m['duration_days'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':instructions', $m['instructions'] !== '' ? $m['instructions'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':status', $m['status'] ?? 'active', PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':prescribed_at', $m['prescribed_at'] !== '' ? $m['prescribed_at'] : null, PDO::PARAM_STR);
        $stmt->execute();
    }

    /**
     * @param array<string, mixed> $v
     */
    private function insertVitalSigns(int $studentId, int $visitId, array $v, int $userId): void
    {
        $bmi = null;
        if ($v['weight_kg'] !== '' && $v['weight_kg'] !== null && $v['height_cm'] !== '' && $v['height_cm'] !== null) {
            $h = (float) $v['height_cm'] / 100.0;
            if ($h > 0) {
                $bmi = round((float) $v['weight_kg'] / ($h * $h), 1);
            }
        }

        $stmt = $this->db->prepare(
            'INSERT INTO vital_signs
               (clinic_visit_id, student_id, temperature_c, blood_pressure_sys,
                blood_pressure_dia, heart_rate, respiratory_rate,
                oxygen_saturation, weight_kg, height_cm, bmi, measured_at, recorded_by)
             VALUES (:vid, :sid, :temperature, :bp_sys, :bp_dia, :heart_rate,
                :resp_rate, :oxygen, :weight, :height, :bmi, :measured_at, :user_id)'
        );
        $stmt->bindValue(':vid', $visitId, PDO::PARAM_INT);
        $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
        $stmt->bindValue(':temperature', $v['temperature_c'] !== '' && $v['temperature_c'] !== null ? (float) $v['temperature_c'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':bp_sys', $v['blood_pressure_sys'] !== '' && $v['blood_pressure_sys'] !== null ? (int) $v['blood_pressure_sys'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':bp_dia', $v['blood_pressure_dia'] !== '' && $v['blood_pressure_dia'] !== null ? (int) $v['blood_pressure_dia'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':heart_rate', $v['heart_rate'] !== '' && $v['heart_rate'] !== null ? (int) $v['heart_rate'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':resp_rate', $v['respiratory_rate'] !== '' && $v['respiratory_rate'] !== null ? (int) $v['respiratory_rate'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':oxygen', $v['oxygen_saturation'] !== '' && $v['oxygen_saturation'] !== null ? (int) $v['oxygen_saturation'] : null, PDO::PARAM_INT);
        $stmt->bindValue(':weight', $v['weight_kg'] !== '' && $v['weight_kg'] !== null ? (float) $v['weight_kg'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':height', $v['height_cm'] !== '' && $v['height_cm'] !== null ? (float) $v['height_cm'] : null, PDO::PARAM_STR);
        $stmt->bindValue(':bmi', $bmi, PDO::PARAM_STR);
        $stmt->bindValue(':measured_at', $v['measured_at'] !== '' ? $v['measured_at'] : date('Y-m-d H:i:s'), PDO::PARAM_STR);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
