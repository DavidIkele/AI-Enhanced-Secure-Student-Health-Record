<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Repositories\HealthcareStaffRepository;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use App\Security\Security;
use App\Services\AuditLogService;
use App\Services\AuthService;
use App\Services\Logger;
use PDO;

/**
 * Operational/monitoring endpoints. The health check performs a genuine
 * database lookup and returns 200 only when the stack is healthy. It exposes
 * no secrets and no environment details.
 */
class SystemController extends BaseController
{
    public function health(): void
    {
        $dbOk = \App\Services\Database::isConnected();

        $payload = [
            'status' => $dbOk ? 'ok' : 'degraded',
            'service' => 'student-health-record',
            'time' => gmdate('c'),
        ];

        if ($dbOk) {
            // Machine clients (Accept: application/json) get a JSON payload;
            // browsers always receive an accessible HTML page.
            $accept = (string) ($this->request->header('Accept') ?? '');
            if (stripos($accept, 'application/json') !== false) {
                $this->renderJson($payload, 200);
                return;
            }
            $this->render('system/health', [
                'title' => 'System health | Student Health Record System',
                'page' => 'system',
                'status' => 'ok',
                'service' => 'student-health-record',
                'time' => $payload['time'],
            ]);
        } else {
            Logger::warning('Health check reported degraded database connection');
            $payload['error'] = 'database unreachable';
            $this->renderJson($payload, 503);
        }
    }

    /**
     * Administrative area (PROMPT 4). Only users with the admin
     * role may reach it; the route is guarded by RoleMiddleware::oneOf('admin').
     */
    public function adminArea(): void
    {
        $pdo = \App\Services\Database::connection();

        // Dashboard statistics
        $totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn();
        $activeUsers = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND is_active = 1')->fetchColumn();
        $totalStudents = (int) $pdo->query('SELECT COUNT(*) FROM students WHERE deleted_at IS NULL')->fetchColumn();
        $totalStaff = (int) $pdo->query('SELECT COUNT(*) FROM healthcare_staff WHERE deleted_at IS NULL')->fetchColumn();

        $stats = [
            'total_users' => $totalUsers,
            'active_users' => $activeUsers,
            'total_students' => $totalStudents,
            'total_staff' => $totalStaff,
        ];

        // Users list with roles
        $stmt = $pdo->prepare(
            'SELECT u.id, u.username, u.email, u.is_active, r.slug as role
               FROM users u
               JOIN roles r ON u.role_id = r.id
              WHERE u.deleted_at IS NULL
              ORDER BY u.created_at DESC'
        );
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->render('admin/dashboard', [
            'title' => 'Administration | Student Health Record System',
            'page' => 'admin',
            'stats' => $stats,
            'users' => $users,
        ]);
    }

    /**
     * Single-user account details for the admin user-management table. Includes
     * the role slug and any linked student/staff profile. The route is guarded
     * by RoleMiddleware::oneOf('admin').
     */
    public function userDetails(int $id): void
    {
        $auth = new AuthService();
        $adminUserId = (int) ($auth->id() ?? 0);

        $pdo = \App\Services\Database::connection();
        $stmt = $pdo->prepare(
            'SELECT u.id, u.username, u.email, u.is_active, u.must_change_password,
                    u.failed_login_attempts, u.locked_until, u.last_login_at, u.created_at,
                    r.slug AS role, r.name AS role_name
               FROM users u
               JOIN roles r ON r.id = u.role_id
              WHERE u.id = :id AND u.deleted_at IS NULL
              LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user === false) {
            $this->abort(404, 'User not found.');
        }

        // Linked profile data (a user is either a student or staff, or neither).
        $linked = null;
        $student = (new StudentRepository())->findByUserId($id);
        if ($student !== null) {
            $linked = ['type' => 'student', 'data' => $student];
        } else {
            $stmt2 = $pdo->prepare(
                'SELECT hs.id, hs.staff_id, hs.title, hs.first_name, hs.last_name,
                        hs.role_name, hs.specialization, hs.department
                   FROM healthcare_staff hs
                  WHERE hs.user_id = :uid AND hs.deleted_at IS NULL
                  LIMIT 1'
            );
            $stmt2->bindValue(':uid', $id, PDO::PARAM_INT);
            $stmt2->execute();
            $staff = $stmt2->fetch(PDO::FETCH_ASSOC);
            if ($staff !== false) {
                $linked = ['type' => 'staff', 'data' => $staff];
            }
        }

        AuditLogService::record('view', 'user', (string) $id, null, $adminUserId);

        $this->render('admin/user_details', [
            'title' => 'User details | Student Health Record System',
            'page' => 'admin',
            'user' => $user,
            'linked' => $linked,
        ]);
    }

    /**
     * Deactivate a user account (admin only). POST + CSRF-protected; the
     * acting admin cannot deactivate their own account.
     */
    public function deactivateUser(int $id): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/admin/area');
            return;
        }

        $auth = new AuthService();
        $adminUserId = (int) ($auth->id() ?? 0);

        if ($id === $adminUserId) {
            Session::flash('danger', 'You cannot deactivate your own account.');
            $this->redirect('/admin/area');
            return;
        }

        $repo = new UserRepository();
        if ($repo->findById($id) === null) {
            $this->abort(404, 'User not found.');
        }

        $repo->setActive($id, false);
        AuditLogService::record('deactivate', 'user', (string) $id, null, $adminUserId);

        Session::flash('success', 'User account deactivated.');
        $this->redirect('/admin/area');
    }

    /**
     * Reactivate a user account (admin only). POST + CSRF-protected.
     */
    public function activateUser(int $id): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/admin/area');
            return;
        }

        $auth = new AuthService();
        $adminUserId = (int) ($auth->id() ?? 0);

        $repo = new UserRepository();
        if ($repo->findById($id) === null) {
            $this->abort(404, 'User not found.');
        }

        $repo->setActive($id, true);
        AuditLogService::record('activate', 'user', (string) $id, null, $adminUserId);

        Session::flash('success', 'User account activated.');
        $this->redirect('/admin/area');
    }
}