<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Security\AccessControl;
use App\Security\RateLimiter;
use App\Security\Security;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\Validator;

/**
 * Authentication controller: login, logout and student registration.
 *
 * Login is CSRF-protected. Failed attempts are rate-limited and locked out
 * server-side. Error messages are deliberately generic to prevent account
 * enumeration.
 */
class AuthController extends BaseController
{
    /**
     * Post-login landing page: staff/admin keep the dashboard, students go
     * straight to their profile (the student area has no dashboard).
     */
    private function landingPath(): string
    {
        $userId = (new AuthService())->id();
        if ($userId !== null && (AccessControl::can($userId, 'records.manage') || AccessControl::can($userId, 'analytics.view'))) {
            return '/dashboard';
        }
        return '/profile';
    }

    /**
     * @var array<int, string> fields the registration form returns in `old`
     */
    private const REGISTER_FIELDS = [
        'username', 'email', 'reg_number', 'first_name', 'last_name', 'other_names',
        'date_of_birth', 'gender', 'phone', 'address', 'department', 'faculty',
        'level_of_study', 'emergency_contact_name', 'emergency_contact_phone',
    ];

    public function showRegister(): void
    {
        $this->render('auth/register', [
            'title' => 'Create account | Student Health Record System',
            'page' => 'register',
            'errors' => [],
            'old' => [],
        ]);
    }

    public function register(): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            Session::flash('danger', 'Your session expired. Please try again.');
            $this->redirect('/auth/register');
            return;
        }

        if ((new AuthService())->check()) {
            $this->redirect($this->landingPath());
            return;
        }

        $v = new Validator();
        $v->field('username', $this->request->input('username'))->required('username')->maxLength('username', 50);
        $v->field('email', $this->request->input('email'))->required('email')->maxLength('email', 190)->email('email');
        $v->field('password', $this->request->input('password'))->required('password');
        $v->field('password_confirmation', $this->request->input('password_confirmation'))->required('password_confirmation');
        $v->field('reg_number', $this->request->input('reg_number'))->required('reg_number')->maxLength('reg_number', 30);
        $v->field('first_name', $this->request->input('first_name'))->required('first_name')->maxLength('first_name', 80);
        $v->field('last_name', $this->request->input('last_name'))->required('last_name')->maxLength('last_name', 80);
        $v->field('other_names', $this->request->input('other_names'))->maxLength('other_names', 120);
        $v->field('date_of_birth', $this->request->input('date_of_birth'))->date('date_of_birth');
        $v->field('gender', $this->request->input('gender'))->inList('gender', ['male', 'female', 'other']);
        $v->field('phone', $this->request->input('phone'))->maxLength('phone', 30);
        $v->field('address', $this->request->input('address'))->maxLength('address', 255);
        $v->field('department', $this->request->input('department'))->maxLength('department', 120);
        $v->field('faculty', $this->request->input('faculty'))->maxLength('faculty', 120);
        $v->field('level_of_study', $this->request->input('level_of_study'))->maxLength('level_of_study', 30);
        $v->field('emergency_contact_name', $this->request->input('emergency_contact_name'))->maxLength('emergency_contact_name', 120);
        $v->field('emergency_contact_phone', $this->request->input('emergency_contact_phone'))->maxLength('emergency_contact_phone', 30);

        $username = (string) $v->value('username', '');
        if ($username !== '' && !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
            $v->addError('username', 'Use 3\u201350 letters, numbers, dots, dashes or underscores only.');
        }

        $password = (string) $v->value('password', '');
        if ($password !== '' && !Security::passwordPolicyOk($password)) {
            $v->addError('password', 'Password must be at least 12 characters and include at least one letter and one number.');
        }
        if ((string) $v->value('password', '') !== (string) $v->value('password_confirmation', '')) {
            $v->addError('password_confirmation', 'Passwords do not match.');
        }

        if (!$v->passes()) {
            $this->render('auth/register', [
                'title' => 'Create account | Student Health Record System',
                'page' => 'register',
                'errors' => $v->errors(),
                'old' => $this->oldValues($v),
            ]);
            return;
        }

        $result = (new AuthService())->register([
            'username' => $username,
            'email' => (string) $v->value('email', ''),
            'password' => $password,
            'reg_number' => (string) $v->value('reg_number', ''),
            'first_name' => (string) $v->value('first_name', ''),
            'last_name' => (string) $v->value('last_name', ''),
            'other_names' => (string) $v->value('other_names', ''),
            'date_of_birth' => (string) $v->value('date_of_birth', ''),
            'gender' => (string) $v->value('gender', ''),
            'phone' => (string) $v->value('phone', ''),
            'address' => (string) $v->value('address', ''),
            'department' => (string) $v->value('department', ''),
            'faculty' => (string) $v->value('faculty', ''),
            'level_of_study' => (string) $v->value('level_of_study', ''),
            'emergency_contact_name' => (string) $v->value('emergency_contact_name', ''),
            'emergency_contact_phone' => (string) $v->value('emergency_contact_phone', ''),
        ]);

        if (!$result['success']) {
            Session::flash('danger', 'Registration could not be completed. Please review the errors below.');
            $this->render('auth/register', [
                'title' => 'Create account | Student Health Record System',
                'page' => 'register',
                'errors' => $result['errors'] ?? ['_form' => ['Registration failed.']],
                'old' => $this->oldValues($v),
            ]);
            return;
        }

        AuditLogService::record(
            'register',
            'auth',
            isset($result['user_id']) ? (string) $result['user_id'] : null,
            null,
            isset($result['user_id']) ? (int) $result['user_id'] : null
        );

        Session::flash('success', 'Registration successful. You can now sign in.');
        $this->redirect('/auth/login');
    }

    /**
     * @return array<string, mixed>
     */
    private function oldValues(Validator $v): array
    {
        $old = [];
        foreach (self::REGISTER_FIELDS as $field) {
            $old[$field] = $v->value($field, '');
        }
        return $old;
    }

    public function showLogin(): void
    {
        $this->render('auth/login', [
            'title' => 'Sign in | Student Health Record System',
            'page' => 'login',
        ]);
    }

    public function login(): void
    {
        // CSRF protection for the POST.
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            Session::flash('danger', 'Your session expired. Please try again.');
            $this->redirect('/auth/login');
            return;
        }

        $identifier = (string) $this->request->input('identifier', '');
        $password = (string) $this->request->input('password', '');
        $ipAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');

        $auth = new AuthService();
        $result = $auth->attempt($identifier, $password, $ipAddress);

        if ($result['success']) {
            Session::flash('success', 'You are signed in.');
            $this->redirect($this->landingPath());
            return;
        }

        Session::flash('danger', $result['error'] ?? 'Sign-in failed. Please try again.');
        $this->redirect('/auth/login');
    }

    public function logout(): void
    {
        // CSRF protection for the POST.
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            Session::flash('danger', 'Your session expired. Please try again.');
            $this->redirect('/auth/login');
            return;
        }

        // Capture the actor BEFORE the session is destroyed so the logout
        // event stays attributable to the correct user.
        $userId = (new AuthService())->id();

        // Set the flash BEFORE destroying the session so the message survives
        // the fresh session created on logout (Session::destroy preserves it).
        Session::flash('success', 'You are signed out.');
        (new AuthService())->logout();

        AuditLogService::record('logout', 'auth', $userId === null ? null : (string) $userId, null, $userId);

        $this->redirect('/auth/login');
    }
}
