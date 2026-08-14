<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Exceptions\AppointmentConflictException;
use App\Repositories\AppointmentsRepository;
use App\Repositories\HealthcareStaffRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\StudentRepository;
use App\Security\AccessControl;
use App\Security\Security;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\Validator;

/**
 * Appointment management (PROMPT 6).
 *
 * Authorization model:
 *  - Students (appointments.request) may view their own appointments, request
 *    new appointments, and cancel/reschedule their own pending appointments.
 *  - Healthcare staff/administrators with appointments.manage may view the
 *    full appointment list and cancel/reschedule any appointment.
 *  - appointments.approve grants approval/rejection of pending requests.
 *
 * Double booking is prevented in the repository: booking and rescheduling run
 * inside a transaction that locks the staff row (SELECT ... FOR UPDATE) and
 * re-checks overlap before writing, so concurrent requests cannot both succeed.
 *
 * The visual calendar is an optional extra view; the appointment list remains
 * the primary, fully accessible way to access appointment information.
 */
class AppointmentController extends BaseController
{
    private const PAGE_SIZE = 50;
    private const MAX_PAGE = 100000;

    public function index(): void
    {
        $auth = new AuthService();
        $userId = (int) ($auth->id() ?? 0);
        $repo = new AppointmentsRepository();
        $canManage = AccessControl::can($userId, 'appointments.manage');

        $status = (string) $this->request->query('status', '');

        $rawPage = (string) $this->request->query('page', '1');
        $page = filter_var($rawPage, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1 || $page > self::MAX_PAGE) {
            $page = 1;
        }

        if ($canManage) {
            $total = $repo->countForManagement($status !== '' ? $status : null);
            $pages = (int) max(1, ceil($total / self::PAGE_SIZE));
            if ($page > $pages) {
                $page = $pages;
            }
            $appointments = $repo->allForManagement(
                $status !== '' ? $status : null,
                self::PAGE_SIZE,
                ($page - 1) * self::PAGE_SIZE
            );
        } else {
            $student = (new StudentRepository())->findByUserId($userId);
            $appointments = $student !== null ? $repo->forStudent((int) $student['id']) : [];
            $total = count($appointments);
            $pages = 1;
        }

        $this->render('appointments/index', [
            'title' => 'Appointments | Student Health Record System',
            'page' => 'appointments',
            'appointments' => $appointments,
            'canManage' => $canManage,
            'canApprove' => AccessControl::can($userId, 'appointments.approve'),
            'currentStatus' => $status,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
        ]);
    }

    /**
     * New appointment request form (student-facing).
     */
    public function create(): void
    {
        $auth = new AuthService();
        $userId = (int) ($auth->id() ?? 0);
        $student = (new StudentRepository())->findByUserId($userId);

        if ($student === null) {
            Session::flash('warning', 'Appointment requests are made by students.');
            $this->redirect('/appointments');
            return;
        }

        $staffList = (new HealthcareStaffRepository())->allActive();
        $staffId = (int) $this->request->query('staff_id', 0);
        $date = (string) $this->request->query('date', '');
        $duration = (int) $this->request->query('duration', AppointmentsRepository::DEFAULT_DURATION);
        $time = (string) $this->request->query('time', '');
        $availability = [];

        if ($staffId > 0 && $date !== '' && preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) {
            $availability = (new AppointmentsRepository())->availabilityForStaff($staffId, $date, $duration);
        }

        // Optional prefill from a clicked free slot (date + HH:MM).
        $presetTime = '';
        if ($date !== '' && preg_match('#^([01]\d|2[0-3]):[0-5]\d$#', $time)) {
            $presetTime = $date . 'T' . $time;
        }

        $selectedStaff = null;
        foreach ($staffList as $staff) {
            if ((int) $staff['id'] === $staffId) {
                $selectedStaff = $staff;
                break;
            }
        }

        $month = (string) $this->request->query('month', '');
        if (!preg_match('#^\d{4}-\d{2}$#', $month)) {
            $month = $date !== '' && preg_match('#^\d{4}-\d{2}-\d{2}$#', $date) ? substr($date, 0, 7) : date('Y-m');
        }
        $calendar = $this->buildCalendarData($staffId, $month, $duration);

        $this->render('appointments/new', [
            'title' => 'Request an appointment | Student Health Record System',
            'page' => 'appointments',
            'staffList' => $staffList,
            'selectedStaff' => $selectedStaff,
            'availability' => $availability,
            'monthMap' => $calendar['monthMap'],
            'month' => $calendar['month'],
            'prevMonth' => $calendar['prevMonth'],
            'nextMonth' => $calendar['nextMonth'],
            'presetTime' => $presetTime,
            'preset' => [
                'staff_id' => $staffId,
                'date' => $date,
                'duration' => $duration,
            ],
            'errors' => [],
            'old' => [],
        ]);
    }

    /**
     * Persist a new appointment request (conflict detection inside repo).
     */
    public function store(): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/appointments');
            return;
        }

        $auth = new AuthService();
        $userId = (int) ($auth->id() ?? 0);
        $student = (new StudentRepository())->findByUserId($userId);

        if ($student === null) {
            Session::flash('warning', 'Appointment requests are made by students.');
            $this->redirect('/appointments');
            return;
        }

        $data = $this->request->only([
            'staff_id', 'scheduled_at', 'duration_minutes', 'reason',
        ]);

        $validator = (new Validator())
            ->field('staff_id', $data['staff_id'] ?? '')
            ->field('scheduled_at', $data['scheduled_at'] ?? '')
            ->field('duration_minutes', $data['duration_minutes'] ?? (string) AppointmentsRepository::DEFAULT_DURATION)
            ->field('reason', $data['reason'] ?? '')
            ->required('staff_id')
            ->intBetween('staff_id', 1, 99999999)
            ->required('scheduled_at')
            ->futureDatetime('scheduled_at')
            ->intBetween('duration_minutes', 15, 240)
            ->required('reason')
            ->maxLength('reason', 255);

        $staff = (new HealthcareStaffRepository())->findById((int) $validator->value('staff_id', 0));

        if ($staff === null) {
            $errors = $validator->errors();
            $errors['staff_id'][] = 'Please choose a valid clinic staff member.';
            $this->renderCreateForm($validator->value('scheduled_at', ''), (int) $validator->value('duration_minutes'), $errors, $data);
            return;
        }

        if (!$validator->passes()) {
            $this->renderCreateForm($validator->value('scheduled_at', ''), (int) $validator->value('duration_minutes'), $validator->errors(), $data);
            return;
        }

        $scheduledAt = (string) $validator->value('scheduled_at');
        $duration = (int) $validator->value('duration_minutes');
        $reason = (string) $validator->value('reason');

        $repo = new AppointmentsRepository();
        try {
            $id = $repo->create((int) $student['id'], (int) $validator->value('staff_id'), $scheduledAt, $duration, $reason, $userId);
            AuditLogService::record('create', 'appointment', (string) $id, ['student_id' => (int) $student['id']], $userId);
            // Appointment notification: notify the assigned staff member
            // (body is date/time only — never the request reason).
            if ($staff !== null) {
                $this->notifyAppointment('requested', (int) $staff['user_id'], $scheduledAt, $id, $userId);
            }
        } catch (AppointmentConflictException $e) {
            Session::flash('danger', $e->getMessage());
            $this->redirect('/appointments/new?staff_id=' . (int) $validator->value('staff_id') . '&date=' . urlencode(substr($scheduledAt, 0, 10)) . '&duration=' . $duration);
            return;
        } catch (\Throwable $e) {
            Session::flash('danger', 'The appointment could not be requested. Please try again.');
            $this->redirect('/appointments/new');
            return;
        }

        Session::flash('success', 'Appointment requested. It is awaiting approval.');
        $this->redirect('/appointments');
    }

    /**
     * Cancel an appointment (owner may cancel own pending/approved; staff with
     * appointments.manage may cancel any).
     */
    public function cancel(int $id): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/appointments');
            return;
        }

        $auth = new AuthService();
        $userId = (int) ($auth->id() ?? 0);
        $repo = new AppointmentsRepository();
        $appointment = $repo->findById($id);

        if ($appointment === null) {
            $this->abort(404, 'Appointment not found.');
        }

        if (!$this->canModify($appointment, $userId)) {
            $this->abort(403, 'You are not allowed to modify this appointment.');
        }

        if (!in_array($appointment['status'], ['pending', 'approved'], true)) {
            Session::flash('warning', 'Only pending or approved appointments can be cancelled.');
            $this->redirect('/appointments');
            return;
        }

        $reason = (string) $this->request->input('cancellation_reason', '');
        $repo->setStatus($id, 'cancelled', $userId, $reason, null);
        AuditLogService::record('cancel', 'appointment', (string) $id, ['status' => 'cancelled'], $userId);
        $this->notifyOtherParty($appointment, $userId, $id, 'cancelled');

        Session::flash('success', 'Appointment cancelled.');
        $this->redirect('/appointments');
    }

    /**
     * Approve a pending appointment (appointments.approve).
     */
    public function approve(int $id): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/appointments');
            return;
        }

        $this->assertCanApprove();
        $repo = new AppointmentsRepository();
        $appointment = $repo->findById($id);

        if ($appointment === null) {
            $this->abort(404, 'Appointment not found.');
        }
        if ($appointment['status'] !== 'pending') {
            Session::flash('warning', 'Only pending appointments can be approved.');
            $this->redirect('/appointments');
            return;
        }

        $userId = (int) ((new AuthService())->id() ?? 0);
        $repo->setStatus($id, 'approved', $userId, null, (string) $this->request->input('admin_notes', ''));
        AuditLogService::record('approve', 'appointment', (string) $id, ['status' => 'approved'], $userId);
        $this->notifyOtherParty($appointment, $userId, $id, 'approved');

        Session::flash('success', 'Appointment approved.');
        $this->redirect('/appointments');
    }

    /**
     * Reject a pending appointment (appointments.approve).
     */
    public function reject(int $id): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/appointments');
            return;
        }

        $this->assertCanApprove();
        $repo = new AppointmentsRepository();
        $appointment = $repo->findById($id);

        if ($appointment === null) {
            $this->abort(404, 'Appointment not found.');
        }
        if ($appointment['status'] !== 'pending') {
            Session::flash('warning', 'Only pending appointments can be rejected.');
            $this->redirect('/appointments');
            return;
        }

        $userId = (int) ((new AuthService())->id() ?? 0);
        $reason = (string) $this->request->input('admin_notes', '');
        $repo->setStatus($id, 'rejected', $userId, null, $reason);
        AuditLogService::record('reject', 'appointment', (string) $id, ['status' => 'rejected'], $userId);
        $this->notifyOtherParty($appointment, $userId, $id, 'rejected');

        Session::flash('success', 'Appointment rejected.');
        $this->redirect('/appointments');
    }

    /**
     * Form to reschedule an appointment (owner may reschedule own pending;
     * staff with appointments.manage may reschedule pending/approved).
     */
    public function rescheduleForm(int $id): void
    {
        $auth = new AuthService();
        $userId = (int) ($auth->id() ?? 0);
        $repo = new AppointmentsRepository();
        $appointment = $repo->findById($id);

        if ($appointment === null) {
            $this->abort(404, 'Appointment not found.');
        }
        if (!$this->canModify($appointment, $userId)) {
            $this->abort(403, 'You are not allowed to modify this appointment.');
        }

        $this->render('appointments/reschedule', [
            'title' => 'Reschedule appointment | Student Health Record System',
            'page' => 'appointments',
            'appointment' => $appointment,
            'errors' => [],
            'old' => [],
        ]);
    }

    /**
     * Process a reschedule (conflict detection inside repo).
     */
    public function reschedule(int $id): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/appointments');
            return;
        }

        $auth = new AuthService();
        $userId = (int) ($auth->id() ?? 0);
        $repo = new AppointmentsRepository();
        $appointment = $repo->findById($id);

        if ($appointment === null) {
            $this->abort(404, 'Appointment not found.');
        }
        if (!$this->canModify($appointment, $userId)) {
            $this->abort(403, 'You are not allowed to modify this appointment.');
        }
        if (!in_array($appointment['status'], ['pending', 'approved'], true)) {
            Session::flash('warning', 'Only pending or approved appointments can be rescheduled.');
            $this->redirect('/appointments');
            return;
        }

        $data = $this->request->only([
            'scheduled_at', 'duration_minutes',
        ]);

        $validator = (new Validator())
            ->field('scheduled_at', $data['scheduled_at'] ?? '')
            ->field('duration_minutes', $data['duration_minutes'] ?? (string) $appointment['duration_minutes'])
            ->required('scheduled_at')
            ->futureDatetime('scheduled_at')
            ->intBetween('duration_minutes', 15, 240);

        if (!$validator->passes()) {
            $this->render('appointments/reschedule', [
                'title' => 'Reschedule appointment | Student Health Record System',
                'page' => 'appointments',
                'appointment' => $appointment,
                'errors' => $validator->errors(),
                'old' => $data,
            ]);
            return;
        }

        try {
            $repo->reschedule($id, (string) $validator->value('scheduled_at'), (int) $validator->value('duration_minutes'), $userId);
            AuditLogService::record('reschedule', 'appointment', (string) $id, ['status' => $appointment['status']], $userId);
            $this->notifyOtherParty($appointment, $userId, $id, 'rescheduled', (string) $validator->value('scheduled_at'));
        } catch (AppointmentConflictException $e) {
            Session::flash('danger', $e->getMessage());
            $this->redirect('/appointments/' . $id . '/reschedule');
            return;
        } catch (\Throwable $e) {
            Session::flash('danger', 'The appointment could not be rescheduled. Please try again.');
            $this->redirect('/appointments/' . $id . '/reschedule');
            return;
        }

        Session::flash('success', 'Appointment rescheduled.');
        $this->redirect('/appointments');
    }

    /**
     * Optional visual calendar (staff/admin). The list view remains primary.
     */
    public function calendar(): void
    {
        $auth = new AuthService();
        $userId = (int) ($auth->id() ?? 0);

        if (!AccessControl::can($userId, 'appointments.manage')) {
            $this->abort(403, 'You are not allowed to view the appointment calendar.');
        }

        $month = (string) $this->request->query('month', date('Y-m'));
        if (!preg_match('#^\d{4}-\d{2}$#', $month)) {
            $month = date('Y-m');
        }
        [$year, $mon] = array_map('intval', explode('-', $month));

        $repo = new AppointmentsRepository();
        $staffList = (new HealthcareStaffRepository())->allActive();

        $byStaff = [];
        foreach ($staffList as $staff) {
            $from = sprintf('%04d-%02d-01 00:00:00', $year, $mon);
            $next = mktime(0, 0, 0, $mon + 1, 1, $year);
            $to = date('Y-m-d H:i:s', $next);
            $byStaff[(int) $staff['id']] = $repo->scheduledForStaffBetween((int) $staff['id'], $from, $to);
        }

        $this->render('appointments/calendar', [
            'title' => 'Appointment calendar | Student Health Record System',
            'page' => 'appointments',
            'month' => $month,
            'year' => $year,
            'mon' => $mon,
            'staffList' => $staffList,
            'byStaff' => $byStaff,
            'prevMonth' => date('Y-m', mktime(0, 0, 0, $mon - 1, 1, $year)),
            'nextMonth' => date('Y-m', mktime(0, 0, 0, $mon + 1, 1, $year)),
        ]);
    }

    /**
     * JSON clinic-availability endpoint for the request form (AJAX).
     */
    public function availability(): void
    {
        $auth = new AuthService();
        $userId = (int) ($auth->id() ?? 0);

        if (!AccessControl::can($userId, 'appointments.request')) {
            $this->renderJson(['success' => false, 'error' => 'Not authorized.'], 403);
            return;
        }

        $staffId = (int) $this->request->query('staff_id', 0);
        $date = (string) $this->request->query('date', '');
        $duration = (int) $this->request->query('duration', AppointmentsRepository::DEFAULT_DURATION);

        if ($staffId <= 0 || !preg_match('#^\d{4}-\d{2}-\d{2}$#', $date)) {
            $this->renderJson(['success' => false, 'error' => 'Invalid parameters.'], 422);
            return;
        }

        $staff = (new HealthcareStaffRepository())->findById($staffId);
        if ($staff === null) {
            $this->renderJson(['success' => false, 'error' => 'Unknown staff member.'], 404);
            return;
        }

        $slots = (new AppointmentsRepository())->availabilityForStaff($staffId, $date, $duration);
        $this->renderJson(['success' => true, 'slots' => $slots]);
    }

    /**
     * Permission guard for approval/rejection (defence in depth).
     */
    private function assertCanApprove(): void
    {
        $userId = (int) ((new AuthService())->id() ?? 0);
        if ($userId === 0 || !AccessControl::can($userId, 'appointments.approve')) {
            $this->abort(403, 'You do not have permission to approve appointments.');
        }
    }

    /**
     * Re-render the new-appointment form with validation errors.
     *
     * @param array<string, array<int, string>> $errors
     * @param array<string, mixed> $old
     */
    private function renderCreateForm(string $scheduledAt, int $duration, array $errors, array $old): void
    {
        $staffList = (new HealthcareStaffRepository())->allActive();
        $staffId = (int) ($old['staff_id'] ?? 0);
        $date = substr($scheduledAt, 0, 10);

        $selectedStaff = null;
        foreach ($staffList as $staff) {
            if ((int) $staff['id'] === $staffId) {
                $selectedStaff = $staff;
                break;
            }
        }

        $month = (string) $this->request->query('month', '');
        if (!preg_match('#^\d{4}-\d{2}$#', $month)) {
            $month = $date !== '' ? substr($date, 0, 7) : date('Y-m');
        }
        $calendar = $this->buildCalendarData($staffId, $month, $duration);

        $this->render('appointments/new', [
            'title' => 'Request an appointment | Student Health Record System',
            'page' => 'appointments',
            'staffList' => $staffList,
            'selectedStaff' => $selectedStaff,
            'availability' => [],
            'monthMap' => $calendar['monthMap'],
            'month' => $calendar['month'],
            'prevMonth' => $calendar['prevMonth'],
            'nextMonth' => $calendar['nextMonth'],
            'presetTime' => '',
            'preset' => [
                'staff_id' => $staffId,
                'date' => $date,
                'duration' => $duration > 0 ? $duration : AppointmentsRepository::DEFAULT_DURATION,
            ],
            'errors' => $errors,
            'old' => $old,
        ]);
    }

    /**
     * Build the student-facing availability-calendar data (month free-slot map
     * plus prev/next month links) for a selected staff member.
     *
     * @return array{monthMap:array, month:string, prevMonth:string, nextMonth:string}
     */
    private function buildCalendarData(int $staffId, string $month, int $duration): array
    {
        if ($staffId <= 0 || !preg_match('#^\d{4}-\d{2}$#', $month)) {
            $month = date('Y-m');
        }
        [$year, $mon] = array_map('intval', explode('-', $month));

        return [
            'monthMap' => $staffId > 0
                ? (new AppointmentsRepository())->availabilityForStaffMonth($staffId, $month, $duration)
                : [],
            'month' => $month,
            'prevMonth' => date('Y-m', mktime(0, 0, 0, $mon - 1, 1, $year)),
            'nextMonth' => date('Y-m', mktime(0, 0, 0, $mon + 1, 1, $year)),
        ];
    }

    /**
     * Whether the current user may modify (cancel/reschedule) an appointment.
     *
     * @param array<string, mixed> $appointment
     */
    private function canModify(array $appointment, int $userId): bool
    {
        if (AccessControl::can($userId, 'appointments.manage')) {
            return true;
        }
        $student = (new StudentRepository())->findByUserId($userId);
        return $student !== null && (int) $student['id'] === (int) $appointment['student_id'];
    }

    /**
     * Create an appointment notification (PROMPT 13). Content is generated
     * from the privacy-safe template (event + date/time only); the request
     * reason is never included. De-duplicated by the repository so the same
     * appointment event cannot notify twice.
     */
    private function notifyAppointment(string $event, int $recipientUserId, string $when, int $appointmentId, int $actorUserId): void
    {
        $tpl = NotificationController::appointmentNotification($event, $when);
        $notificationId = (new NotificationRepository())->create(
            $recipientUserId,
            'appointment',
            $tpl['title'],
            $tpl['body'],
            'appointment',
            $appointmentId
        );
        AuditLogService::record(
            'create',
            'notification',
            (string) $notificationId,
            ['type' => 'appointment', 'event' => $event, 'recipient_user_id' => $recipientUserId],
            $actorUserId
        );
    }

    /**
     * Notify the party on the other side of an appointment transition. If the
     * acting user is the owning student, the assigned staff member is notified;
     * otherwise (staff/admin acting) the student is notified.
     *
     * @param array<string, mixed> $appointment
     */
    private function notifyOtherParty(array $appointment, int $userId, int $appointmentId, string $event, ?string $when = null): void
    {
        $student = (new StudentRepository())->findByUserId($userId);
        $isStudentActor = $student !== null && (int) $student['id'] === (int) $appointment['student_id'];
        $when = $when ?? (string) $appointment['scheduled_at'];

        if ($isStudentActor) {
            $staff = (new HealthcareStaffRepository())->findById((int) $appointment['healthcare_staff_id']);
            if ($staff !== null) {
                $this->notifyAppointment($event, (int) $staff['user_id'], $when, $appointmentId, $userId);
            }
        } else {
            $studentOwner = (new StudentRepository())->findById((int) $appointment['student_id']);
            if ($studentOwner !== null) {
                $this->notifyAppointment($event, (int) $studentOwner['user_id'], $when, $appointmentId, $userId);
            }
        }
    }
}
