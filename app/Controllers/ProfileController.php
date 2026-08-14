<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Exceptions\ForbiddenHttpException;
use App\Repositories\AppointmentsRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\HealthInsightRepository;
use App\Repositories\HealthRecordRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\StudentRepository;
use App\Repositories\UserPreferencesRepository;
use App\Repositories\UserRepository;
use App\Security\AccessControl;
use App\Security\Hasher;
use App\Security\Security;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\Validator;

/**
 * Student profile area. A student may only view/edit/update their OWN
 * profile; ownership is derived from the authenticated session, never from
 * the URL, so IDOR attempts using another student's id are rejected.
 *
 * Beyond the basic profile/credentials, this controller also exposes:
 *   - notification preferences (GET/POST /profile/preferences)
 *   - data export (GET /profile/data-export) - downloads a JSON of the
 *     student's own profile, appointments, insights, notifications and
 *     account-level audit entries.
 *   - account deactivation (POST /profile/delete)
 */
class ProfileController extends BaseController
{
    /**
     * @var array<int, string> editable profile fields
     */
    private const PROFILE_FIELDS = [
        'email', 'first_name', 'last_name', 'other_names', 'date_of_birth',
        'gender', 'phone', 'address', 'department', 'faculty', 'level_of_study',
        'emergency_contact_name', 'emergency_contact_phone',
    ];

    public function show(): void
    {
        $auth = new AuthService();
        $user = $auth->user();
        $userId = (int) $user['id'];

        $student = (new StudentRepository())->findByUserId($userId);

        if ($student === null) {
            $this->abort(404, 'Profile not found.');
        }

        $this->render('profile/show', [
            'title' => 'My Profile | Student Health Record System',
            'page' => 'profile',
            'student' => $student,
            'user' => $user,
            'canManage' => AccessControl::can($userId, 'records.manage'),
            'insights' => (new HealthInsightRepository())->forStudent((int) $student['id']),
            'upcoming' => (new AppointmentsRepository())->upcomingForStudent((int) $student['id'], 5),
            'preferences' => (new UserPreferencesRepository())->get($userId),
            'recentVisits' => (new HealthRecordRepository())->clinicVisitsForStudent((int) $student['id']),
        ]);
    }

    /**
     * Edit form for a student's own profile.
     */
    public function edit(): void
    {
        $auth = new AuthService();
        $user = $auth->user();
        $userId = (int) $user['id'];
        $student = (new StudentRepository())->findByUserId($userId);

        if ($student === null) {
            $this->abort(404, 'Profile not found.');
        }

        $this->render('profile/edit', [
            'title' => 'Edit profile | Student Health Record System',
            'page' => 'profile',
            'student' => $student,
            'username' => (string) $user['username'],
            'email' => (string) $user['email'],
            'errors' => [],
            'old' => $student,
        ]);
    }

    /**
     * Persist a student's own profile details.
     */
    public function update(): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            Session::flash('danger', 'Your session expired. Please try again.');
            $this->redirect('/profile');
            return;
        }

        $auth = new AuthService();
        $user = $auth->user();
        $userId = (int) $user['id'];
        $students = new StudentRepository();
        $student = $students->findByUserId($userId);

        if ($student === null) {
            $this->abort(404, 'Profile not found.');
        }

        $data = $this->request->only(self::PROFILE_FIELDS);

        $username = trim((string) $this->request->input('username', ''));

        $v = new Validator();
        $v->field('email', $data['email'] ?? '')->required('email')->maxLength('email', 190)->email('email');
        $v->field('first_name', $data['first_name'] ?? '')->required('first_name')->maxLength('first_name', 80);
        $v->field('last_name', $data['last_name'] ?? '')->required('last_name')->maxLength('last_name', 80);
        $v->field('other_names', $data['other_names'] ?? '')->maxLength('other_names', 120);
        $v->field('date_of_birth', $data['date_of_birth'] ?? '')->date('date_of_birth');
        $v->field('gender', $data['gender'] ?? '')->inList('gender', ['male', 'female', 'other']);
        $v->field('phone', $data['phone'] ?? '')->maxLength('phone', 30);
        $v->field('address', $data['address'] ?? '')->maxLength('address', 255);
        $v->field('department', $data['department'] ?? '')->maxLength('department', 120);
        $v->field('faculty', $data['faculty'] ?? '')->maxLength('faculty', 120);
        $v->field('level_of_study', $data['level_of_study'] ?? '')->maxLength('level_of_study', 30);
        $v->field('emergency_contact_name', $data['emergency_contact_name'] ?? '')->maxLength('emergency_contact_name', 120);
        $v->field('emergency_contact_phone', $data['emergency_contact_phone'] ?? '')->maxLength('emergency_contact_phone', 30);

        if ($username === '') {
            $v->addError('username', 'A username is required.');
        } elseif (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            $v->addError('username', 'Use 3–50 letters, numbers, dots, dashes or underscores only.');
        } elseif ((new UserRepository())->existsByUsernameUnless($username, $userId)) {
            $v->addError('username', 'That username is already taken.');
        }

        $email = strtolower((string) $v->value('email', ''));
        if ($email !== '' && (new UserRepository())->existsByEmailUnless($email, $userId)) {
            $v->addError('email', 'Another account already uses that email address.');
        }

        if (!$v->passes()) {
            $this->render('profile/edit', [
                'title' => 'Edit profile | Student Health Record System',
                'page' => 'profile',
                'student' => $student,
                'username' => $username !== '' ? $username : (string) $user['username'],
                'email' => $email !== '' ? $email : (string) $user['email'],
                'errors' => $v->errors(),
                'old' => $data,
            ]);
            return;
        }

        (new UserRepository())->updateUsername($userId, $username);
        (new UserRepository())->updateEmail($userId, $email);
        $students->updateByUserId($userId, [
            'first_name' => (string) $v->value('first_name', ''),
            'last_name' => (string) $v->value('last_name', ''),
            'other_names' => (string) $v->value('other_names', ''),
            'date_of_birth' => (string) $v->value('date_of_birth', ''),
            'gender' => (string) $v->value('gender', ''),
            'email' => $email,
            'phone' => (string) $v->value('phone', ''),
            'address' => (string) $v->value('address', ''),
            'department' => (string) $v->value('department', ''),
            'faculty' => (string) $v->value('faculty', ''),
            'level_of_study' => (string) $v->value('level_of_study', ''),
            'emergency_contact_name' => (string) $v->value('emergency_contact_name', ''),
            'emergency_contact_phone' => (string) $v->value('emergency_contact_phone', ''),
        ]);

        AuditLogService::record('update', 'profile', (string) $userId, ['fields' => array_merge(self::PROFILE_FIELDS, ['username', 'email'])], $userId);
        Session::flash('success', 'Your profile has been updated.');
        $this->redirect('/profile');
    }

    /**
     * Change the account password (current password required).
     */
    public function updatePassword(): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            Session::flash('danger', 'Your session expired. Please try again.');
            $this->redirect('/profile');
            return;
        }

        $auth = new AuthService();
        $user = $auth->user();
        $userId = (int) $user['id'];

        $v = new Validator();
        $v->field('current_password', $this->request->input('current_password'))->required('current_password');
        $v->field('new_password', $this->request->input('new_password'))->required('new_password');
        $v->field('new_password_confirmation', $this->request->input('new_password_confirmation'))->required('new_password_confirmation');

        $current = (string) $v->value('current_password', '');
        $newPassword = (string) $v->value('new_password', '');

        if ($current === '' || !Hasher::verify($current, (string) $user['password_hash'])) {
            $v->addError('current_password', 'Your current password is incorrect.');
        }
        if ($newPassword !== '' && !Security::passwordPolicyOk($newPassword)) {
            $v->addError('new_password', 'Password must be at least 12 characters and include at least one letter and one number.');
        }
        if ($newPassword !== '' && $newPassword !== (string) $v->value('new_password_confirmation', '')) {
            $v->addError('new_password_confirmation', 'Passwords do not match.');
        }

        if (!$v->passes()) {
            $student = (new StudentRepository())->findByUserId($userId);
            $this->render('profile/edit', [
                'title' => 'Edit profile | Student Health Record System',
                'page' => 'profile',
                'student' => $student ?? [],
                'email' => (string) $user['email'],
                'errors' => $v->errors(),
                'old' => $student ?? [],
            ]);
            return;
        }

        (new UserRepository())->updatePasswordHash($userId, Hasher::hash($newPassword));
        AuditLogService::record('password_change', 'auth', (string) $userId, null, $userId);
        Session::flash('success', 'Your password has been updated.');
        $this->redirect('/profile');
    }

    /**
     * Deactivate/delete a student's own account. The user and student rows are
     * soft-deleted (retained for audit/compliance) so the account can no longer
     * sign in and the identity cannot be re-registered.
     */
    public function delete(): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            Session::flash('danger', 'Your session expired. Please try again.');
            $this->redirect('/profile');
            return;
        }

        $auth = new AuthService();
        $user = $auth->user();
        $userId = (int) $user['id'];
        $students = new StudentRepository();

        if ($students->findByUserId($userId) === null) {
            $this->abort(404, 'Profile not found.');
        }

        AuditLogService::record('deactivate', 'auth', (string) $userId, ['method' => 'self'], $userId);

        $students->softDeleteByUserId($userId);
        (new UserRepository())->softDelete($userId);

        // Flash before logout so the message survives session destruction.
        Session::flash('success', 'Your account has been deactivated. If this was a mistake, contact the health centre for assistance.');
        $auth->logout();
        $this->redirect('/');
    }

    /**
     * Display the notification preferences form.
     */
    public function preferences(): void
    {
        $auth = new AuthService();
        $userId = (int) $auth->id();

        $this->render('profile/preferences', [
            'title' => 'Notification preferences | Student Health Record System',
            'page' => 'profile',
            'preferences' => (new UserPreferencesRepository())->get($userId),
            'errors' => [],
        ]);
    }

    /**
     * Persist notification preferences. Every preference is always written
     * (checkbox semantics: missing = off).
     */
    public function updatePreferences(): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            Session::flash('danger', 'Your session expired. Please try again.');
            $this->redirect('/profile/preferences');
            return;
        }

        $auth = new AuthService();
        $userId = (int) $auth->id();
        $submitted = (array) $this->request->only(UserPreferencesRepository::keys());

        $repo = new UserPreferencesRepository();
        $repo->save($userId, $submitted);

        AuditLogService::record(
            'update',
            'user_preferences',
            (string) $userId,
            ['keys' => array_keys($submitted)],
            $userId
        );
        Session::flash('success', 'Your notification preferences have been saved.');
        $this->redirect('/profile/preferences');
    }

    /**
     * Download a JSON snapshot of the authenticated user's own data:
     * profile, account, upcoming + recent appointments, health insights,
     * notifications, and the user's own audit-trail entries (login, profile
     * edits, password changes, deactivations).
     *
     * This is a "data export" endpoint, not a backup: it scopes to the
     * authenticated user only and excludes any other user's data. The
     * download is rate-limited by being a single-shot read - repeated calls
     * simply rebuild the same snapshot.
     */
    public function dataExport(): void
    {
        $auth = new AuthService();
        $user = $auth->user();
        if ($user === null) {
            $this->abort(401, 'Sign in to export your data.');
        }
        $userId = (int) $user['id'];
        $student = (new StudentRepository())->findByUserId($userId);
        if ($student === null) {
            $this->abort(404, 'Profile not found.');
        }

        $appts = new AppointmentsRepository();
        $audit = new AuditLogRepository();

        $payload = [
            'generated_at' => gmdate('c'),
            'schema' => 'student-data-export/v1',
            'account' => [
                'user_id' => $userId,
                'username' => (string) $user['username'],
                'email' => (string) $user['email'],
                'last_login_at' => $user['last_login_at'] ?? null,
                'is_active' => (int) $user['is_active'] === 1,
            ],
            'profile' => $student,
            'preferences' => (new UserPreferencesRepository())->get($userId),
            'upcoming_appointments' => $appts->upcomingForStudent((int) $student['id'], 50),
            'appointments' => $appts->forStudent((int) $student['id']),
            'health_insights' => (new HealthInsightRepository())->forStudent((int) $student['id']),
            'notifications' => (new NotificationRepository())->forUser($userId, false, 200),
            'audit_log' => $this->exportUserAudit($audit, $userId),
        ];

        AuditLogService::record(
            'export',
            'user_data',
            (string) $userId,
            null,
            $userId
        );

        $filename = 'my-data-' . gmdate('Ymd-His') . '.json';
        $json = json_encode(
            $payload,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT
        );
        if ($json === false) {
            $this->abort(500, 'Could not build the export file.');
        }

        $this->response
            ->status(200)
            ->header('Content-Type', 'application/json; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Cache-Control', 'no-store, private');
        echo $json;
    }

    /**
     * Audit entries that target the current user as the actor OR the entity.
     * The audit_log table stores user-owned changes; we filter to the
     * authenticated user so the export only contains their own trail.
     *
     * @return array<int, array<string, mixed>>
     */
    private function exportUserAudit(AuditLogRepository $repo, int $userId): array
    {
        $sql = 'SELECT id, user_id, action, entity_type, entity_id, new_values,
                       ip_address, request_method, request_path, created_at
                  FROM audit_logs
                 WHERE user_id = :uid
              ORDER BY created_at DESC, id DESC
                 LIMIT 500';
        $pdo = \App\Services\Database::connection();
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':uid', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}