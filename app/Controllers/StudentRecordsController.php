<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\HealthInsightRepository;
use App\Repositories\HealthRecordRepository;
use App\Repositories\StudentRepository;
use App\Security\Security;
use App\Services\AuditLogService;
use App\Services\Validator;

/**
 * Health record management for healthcare staff and administrators (PROMPT 5).
 *
 * Authorization model (PROMPT 4):
 *  - Listing students requires records.view.any (staff/admin).
 *  - Viewing a student's health profile requires records.view.any.
 *  - Creating/updating profile, medical history and clinic visits requires
 *    records.manage (enforced by PermissionMiddleware on each write route and
 *    re-checked in the controller as a second line of defence).
 *  - The requested numeric id is used only to decide WHICH record; permission
 *    middleware decides WHO may act on it. Row ownership is re-verified.
 *
 * IDOR/BOLA protection: identifiers are typed (int) by the router; a
 * non-numeric value yields 404, and a missing student yields 404. The current
 * user id is taken from the authenticated session, never from request data.
 */
class StudentRecordsController extends BaseController
{
    private const PAGE_SIZE = 50;
    private const MAX_PAGE = 100000;

    public function index(): void
    {
        $rawPage = (string) $this->request->query('page', '1');
        $page = filter_var($rawPage, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1 || $page > self::MAX_PAGE) {
            $page = 1;
        }

        $repo = new StudentRepository();
        $total = $repo->count();
        $pages = (int) max(1, ceil($total / self::PAGE_SIZE));
        if ($page > $pages) {
            $page = $pages;
        }
        $students = $repo->paginated(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        $this->render('records/index', [
            'title' => 'Student Health Records | Student Health Record System',
            'page' => 'records',
            'students' => $students,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    public function show(int $studentId): void
    {
        $repo = new HealthRecordRepository();
        $student = (new StudentRepository())->findById($studentId);

        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $record = $repo->activeProfileForStudent($studentId);

        if ($record !== null && (int) $record['student_id'] !== (int) $studentId) {
            // Defensive: the returned record's owner must match the requested
            // student, otherwise reject (protects against mismatched rows).
            $this->abort(403, 'You are not allowed to view this record.');
        }

        $histories = $repo->medicalHistoriesForStudent($studentId);
        $visits = $repo->clinicVisitsForStudent($studentId);

        AuditLogService::record('view', 'health_record', (string) $studentId, null);

        $this->render('records/show', [
            'title' => $student['last_name'] . ', ' . $student['first_name'] . ' | Health Record',
            'page' => 'records',
            'student' => $student,
            'record' => $record,
            'histories' => $histories,
            'visits' => $visits,
            'insights' => (new HealthInsightRepository())->forStudent($studentId),
            'predictions' => (new \App\Repositories\AiPredictionRepository())->latestForStudent($studentId),
            'symptomAssessments' => (new \App\Repositories\SymptomAssessmentRepository())->latestForStudent($studentId),
            'canManage' => $this->canManage(),
            'canSendAlerts' => $this->canSendAlerts(),
        ]);
    }

    /**
     * Form to create or update a student's health profile.
     */
    public function editProfile(int $studentId): void
    {
        $this->assertCanManage();

        $student = (new StudentRepository())->findById($studentId);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $record = (new HealthRecordRepository())->activeProfileForStudent($studentId);

        $this->render('records/edit_profile', [
            'title' => 'Edit health profile | ' . $student['last_name'] . ', ' . $student['first_name'],
            'page' => 'records',
            'student' => $student,
            'record' => $record,
            'errors' => [],
            'old' => $record ?? [],
        ]);
    }

    /**
     * Persist the health profile (create or update).
     */
    public function updateProfile(int $studentId): void
    {
        $this->assertCanManage();

        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/records/' . $studentId);
            return;
        }

        $student = (new StudentRepository())->findById($studentId);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $data = $this->request->only([
            'blood_group', 'genotype', 'height_cm', 'weight_kg', 'allergies',
            'chronic_conditions', 'disabilities', 'family_history', 'notes',
        ]);

        $validator = (new Validator())
            ->field('blood_group', $data['blood_group'] ?? '')
            ->field('genotype', $data['genotype'] ?? '')
            ->field('height_cm', $data['height_cm'] ?? '')
            ->field('weight_kg', $data['weight_kg'] ?? '')
            ->field('allergies', $data['allergies'] ?? '')
            ->field('chronic_conditions', $data['chronic_conditions'] ?? '')
            ->field('disabilities', $data['disabilities'] ?? '')
            ->field('family_history', $data['family_history'] ?? '')
            ->field('notes', $data['notes'] ?? '')
            ->inList('blood_group', ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'])
            ->inList('genotype', ['AA', 'AS', 'SS', 'AC', 'SC', 'Unknown'])
            ->decimal('height_cm', 50, 250)
            ->decimal('weight_kg', 10, 300)
            ->maxLength('allergies', 2000)
            ->maxLength('chronic_conditions', 2000)
            ->maxLength('disabilities', 2000)
            ->maxLength('family_history', 2000)
            ->maxLength('notes', 4000);

        $repo = new HealthRecordRepository();
        $existing = $repo->activeProfileForStudent($studentId);

        if (!$validator->passes()) {
            $this->render('records/edit_profile', [
                'title' => 'Edit health profile | ' . $student['last_name'] . ', ' . $student['first_name'],
                'page' => 'records',
                'student' => $student,
                'record' => $existing,
                'errors' => $validator->errors(),
                'old' => $data,
            ]);
            return;
        }

        $auth = new \App\Services\AuthService();
        $userId = (int) ($auth->id() ?? 0);

        try {
            if ($existing === null) {
                $repo->createProfile($studentId, $data, $userId);
                AuditLogService::record('create', 'health_record', (string) $studentId, ['profile' => 'created'], $userId);
            } else {
                $repo->updateProfile($studentId, $data, $userId);
                AuditLogService::record('update', 'health_record', (string) $studentId, ['profile' => 'updated'], $userId);
            }
        } catch (\Throwable $e) {
            $this->render('records/edit_profile', [
                'title' => 'Edit health profile | ' . $student['last_name'] . ', ' . $student['first_name'],
                'page' => 'records',
                'student' => $student,
                'record' => $existing,
                'errors' => ['form' => ['The profile could not be saved. Please try again.']],
                'old' => $data,
            ]);
            return;
        }

        \App\Core\Session::flash('success', 'Health profile saved.');
        $this->redirect('/records/' . $studentId);
    }

    /**
     * Add a medical history entry for a student.
     */
    public function addMedicalHistory(int $studentId): void
    {
        $this->assertCanManage();

        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/records/' . $studentId);
            return;
        }

        $student = (new StudentRepository())->findById($studentId);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $data = $this->request->only([
            'condition_name', 'description', 'onset_date', 'is_recurring',
            'severity', 'status',
        ]);

        $validator = (new Validator())
            ->field('condition_name', $data['condition_name'] ?? '')
            ->field('description', $data['description'] ?? '')
            ->field('onset_date', $data['onset_date'] ?? '')
            ->field('is_recurring', $data['is_recurring'] ?? '')
            ->field('severity', $data['severity'] ?? 'mild')
            ->field('status', $data['status'] ?? 'active')
            ->required('condition_name')
            ->maxLength('condition_name', 150)
            ->maxLength('description', 2000)
            ->date('onset_date')
            ->inList('severity', ['mild', 'moderate', 'severe', 'critical'])
            ->inList('status', ['active', 'resolved']);

        if (!$validator->passes()) {
            \App\Core\Session::flash('danger', 'The medical history entry could not be added. Check the highlighted fields.');
            \App\Core\Session::set('danger_fields', $validator->errors());
            \App\Core\Session::set('old_history', $data);
            $this->redirect('/records/' . $studentId);
            return;
        }

        $auth = new \App\Services\AuthService();
        $userId = (int) ($auth->id() ?? 0);

        try {
            $repo = new HealthRecordRepository();
            $id = $repo->createMedicalHistory($studentId, $data, $userId);
            AuditLogService::record('create', 'medical_history', (string) $id, ['student_id' => $studentId], $userId);
        } catch (\Throwable $e) {
            \App\Core\Session::flash('danger', 'The medical history entry could not be added. Please try again.');
            $this->redirect('/records/' . $studentId);
            return;
        }

        \App\Core\Session::flash('success', 'Medical history entry added.');
        $this->redirect('/records/' . $studentId);
    }

    /**
     * Form to record a new clinic visit with clinical detail.
     */
    public function newVisit(int $studentId): void
    {
        $this->assertCanManage();

        $student = (new StudentRepository())->findById($studentId);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $this->render('records/new_visit', [
            'title' => 'New clinic visit | ' . $student['last_name'] . ', ' . $student['first_name'],
            'page' => 'records',
            'student' => $student,
            'errors' => [],
            'old' => [],
        ]);
    }

    /**
     * Persist a new clinic visit (with diagnoses/treatments/medications/vitals).
     */
    public function storeVisit(int $studentId): void
    {
        $this->assertCanManage();

        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/records/' . $studentId);
            return;
        }

        $student = (new StudentRepository())->findById($studentId);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $data = $this->request->only([
            'visited_at', 'visit_type', 'reason', 'chief_complaint',
            'assessment_notes', 'outcome', 'status',
            'diagnosis_name', 'diagnosis_icd', 'diagnosis_severity',
            'treatment_name', 'treatment_type', 'treatment_status',
            'medication_name', 'medication_dosage', 'medication_frequency',
            'temperature_c', 'blood_pressure_sys', 'blood_pressure_dia',
            'heart_rate', 'respiratory_rate', 'oxygen_saturation',
        ]);

        $validator = (new Validator())
            ->field('visited_at', $data['visited_at'] ?? '')
            ->field('visit_type', $data['visit_type'] ?? 'routine')
            ->field('reason', $data['reason'] ?? '')
            ->field('chief_complaint', $data['chief_complaint'] ?? '')
            ->field('assessment_notes', $data['assessment_notes'] ?? '')
            ->field('outcome', $data['outcome'] ?? '')
            ->field('status', $data['status'] ?? 'open')
            ->field('diagnosis_name', $data['diagnosis_name'] ?? '')
            ->field('diagnosis_icd', $data['diagnosis_icd'] ?? '')
            ->field('diagnosis_severity', $data['diagnosis_severity'] ?? 'mild')
            ->field('treatment_name', $data['treatment_name'] ?? '')
            ->field('treatment_type', $data['treatment_type'] ?? 'other')
            ->field('treatment_status', $data['treatment_status'] ?? 'planned')
            ->field('medication_name', $data['medication_name'] ?? '')
            ->field('medication_dosage', $data['medication_dosage'] ?? '')
            ->field('medication_frequency', $data['medication_frequency'] ?? '')
            ->field('temperature_c', $data['temperature_c'] ?? '')
            ->field('blood_pressure_sys', $data['blood_pressure_sys'] ?? '')
            ->field('blood_pressure_dia', $data['blood_pressure_dia'] ?? '')
            ->field('heart_rate', $data['heart_rate'] ?? '')
            ->field('respiratory_rate', $data['respiratory_rate'] ?? '')
            ->field('oxygen_saturation', $data['oxygen_saturation'] ?? '')
            ->required('visited_at')
            ->required('reason')
            ->maxLength('reason', 255)
            ->maxLength('chief_complaint', 4000)
            ->maxLength('assessment_notes', 8000)
            ->datetime('visited_at')
            ->inList('visit_type', ['initial', 'follow_up', 'emergency', 'routine', 'referral'])
            ->inList('outcome', ['treated', 'referred', 'admitted', 'observation', 'discharged'])
            ->inList('status', ['open', 'closed'])
            ->maxLength('diagnosis_name', 150)
            ->maxLength('diagnosis_icd', 20)
            ->inList('diagnosis_severity', ['mild', 'moderate', 'severe', 'critical'])
            ->maxLength('treatment_name', 150)
            ->inList('treatment_type', ['medication', 'procedure', 'therapy', 'counseling', 'referral', 'other'])
            ->inList('treatment_status', ['planned', 'in_progress', 'completed', 'discontinued'])
            ->maxLength('medication_name', 150)
            ->maxLength('medication_dosage', 100)
            ->maxLength('medication_frequency', 100)
            ->decimal('temperature_c', 30, 45)
            ->intBetween('blood_pressure_sys', 50, 260)
            ->intBetween('blood_pressure_dia', 30, 160)
            ->intBetween('heart_rate', 20, 260)
            ->intBetween('respiratory_rate', 4, 80)
            ->intBetween('oxygen_saturation', 40, 100);

        if (!$validator->passes()) {
            \App\Core\Session::flash('danger', 'The clinic visit could not be saved. Check the highlighted fields.');
            \App\Core\Session::set('danger_fields', $validator->errors());
            \App\Core\Session::set('old_visit', $data);
            $this->redirect('/records/' . $studentId . '/visits/new');
            return;
        }

        $diagnoses = [];
        if ($validator->value('diagnosis_name') !== '') {
            $diagnoses[] = [
                'icd_code' => $validator->value('diagnosis_icd'),
                'name' => $validator->value('diagnosis_name'),
                'description' => '',
                'severity' => $validator->value('diagnosis_severity'),
                'is_primary' => true,
                'diagnosed_at' => date('Y-m-d', strtotime((string) $validator->value('visited_at'))),
            ];
        }

        $treatments = [];
        if ($validator->value('treatment_name') !== '') {
            $treatments[] = [
                'name' => $validator->value('treatment_name'),
                'description' => '',
                'treatment_type' => $validator->value('treatment_type'),
                'started_at' => date('Y-m-d', strtotime((string) $validator->value('visited_at'))),
                'ended_at' => '',
                'status' => $validator->value('treatment_status'),
            ];
        }

        $medications = [];
        if ($validator->value('medication_name') !== '') {
            $medications[] = [
                'name' => $validator->value('medication_name'),
                'dosage' => $validator->value('medication_dosage'),
                'frequency' => $validator->value('medication_frequency'),
                'route' => '',
                'quantity' => '',
                'duration_days' => '',
                'instructions' => '',
                'status' => 'active',
                'prescribed_at' => date('Y-m-d', strtotime((string) $validator->value('visited_at'))),
            ];
        }

        $vitals = null;
        if (
            $validator->value('temperature_c') !== ''
            || $validator->value('blood_pressure_sys') !== ''
            || $validator->value('heart_rate') !== ''
        ) {
            $vitals = [
                'temperature_c' => $validator->value('temperature_c'),
                'blood_pressure_sys' => $validator->value('blood_pressure_sys'),
                'blood_pressure_dia' => $validator->value('blood_pressure_dia'),
                'heart_rate' => $validator->value('heart_rate'),
                'respiratory_rate' => $validator->value('respiratory_rate'),
                'oxygen_saturation' => $validator->value('oxygen_saturation'),
                'weight_kg' => '',
                'height_cm' => '',
                'measured_at' => (string) $validator->value('visited_at'),
            ];
        }

        $auth = new \App\Services\AuthService();
        $userId = (int) ($auth->id() ?? 0);

        try {
            $repo = new HealthRecordRepository();
            $id = $repo->createClinicVisit($studentId, $data, $userId, $diagnoses, $treatments, $medications, $vitals);
            AuditLogService::record('create', 'clinic_visit', (string) $id, ['student_id' => $studentId], $userId);
        } catch (\Throwable $e) {
            \App\Core\Session::flash('danger', 'The clinic visit could not be saved. Please try again.');
            $this->redirect('/records/' . $studentId . '/visits/new');
            return;
        }

        \App\Core\Session::flash('success', 'Clinic visit recorded.');
        $this->redirect('/records/' . $studentId);
    }

    /**
     * Permission guard for write operations (defence in depth; the route
     * middleware is the primary control).
     */
    private function assertCanManage(): void
    {
        $auth = new \App\Services\AuthService();
        $userId = $auth->id();

        if ($userId === null || !$this->canManage()) {
            $this->abort(403, 'You do not have permission to manage health records.');
        }
    }

    private function canManage(): bool
    {
        $auth = new \App\Services\AuthService();
        $userId = $auth->id();

        return $userId !== null && \App\Security\AccessControl::can($userId, 'records.manage');
    }

    private function canSendAlerts(): bool
    {
        $auth = new \App\Services\AuthService();
        $userId = $auth->id();

        return $userId !== null && \App\Security\AccessControl::can($userId, 'alerts.manage');
    }
}
