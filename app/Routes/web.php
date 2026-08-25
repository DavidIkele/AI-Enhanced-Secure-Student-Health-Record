<?php

declare(strict_types=1);

use App\Controllers\AppointmentController;
use App\Controllers\AuditLogController;
use App\Controllers\AuthController;
use App\Controllers\AiController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\InsightController;
use App\Controllers\NotificationController;
use App\Controllers\OutbreakController;
use App\Controllers\ProfileController;
use App\Controllers\StudentRecordsController;
use App\Controllers\SystemController;
use App\Controllers\VisitAnalyticsController;
use App\Core\Router;
use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Middleware\RoleMiddleware;

/**
 * Route definitions. Handlers are [FullyQualifiedController, method].
 *
 * Middleware ordering: the auth/guest guards run before the controller.
 */

/** @var Router $router */
$router->get('/', [HomeController::class, 'index'], 'home');
$router->get('/system/health', [SystemController::class, 'health'], 'system.health');

// Authentication
$router->get('/auth/login', [AuthController::class, 'showLogin'], 'auth.login', [GuestMiddleware::class]);
$router->post('/auth/login', [AuthController::class, 'login'], 'auth.login.post', [GuestMiddleware::class]);
// Student self-registration
$router->get('/auth/register', [AuthController::class, 'showRegister'], 'auth.register', [GuestMiddleware::class]);
$router->post('/auth/register', [AuthController::class, 'register'], 'auth.register.post', [GuestMiddleware::class]);
$router->post('/auth/logout', [AuthController::class, 'logout'], 'auth.logout', [AuthMiddleware::class]);

// Protected area (role-specific dashboards)
$router->get('/dashboard', [DashboardController::class, 'index'], 'dashboard', [AuthMiddleware::class]);

// My profile (ownership enforced by session; students only have student rows)
$router->get('/profile', [ProfileController::class, 'show'], 'profile.show', [AuthMiddleware::class]);
$router->get('/profile/edit', [ProfileController::class, 'edit'], 'profile.edit', [AuthMiddleware::class]);
$router->post('/profile', [ProfileController::class, 'update'], 'profile.update', [AuthMiddleware::class]);
$router->post('/profile/password', [ProfileController::class, 'updatePassword'], 'profile.password', [AuthMiddleware::class]);
$router->post('/profile/delete', [ProfileController::class, 'delete'], 'profile.delete', [AuthMiddleware::class]);
// Notification preferences (own user; ownership by session).
$router->get('/profile/preferences', [ProfileController::class, 'preferences'], 'profile.preferences', [AuthMiddleware::class]);
$router->post('/profile/preferences', [ProfileController::class, 'updatePreferences'], 'profile.preferences.update', [AuthMiddleware::class]);
// Download a JSON snapshot of the authenticated user's own data.
$router->get('/profile/data-export', [ProfileController::class, 'dataExport'], 'profile.data_export', [AuthMiddleware::class]);

// Health records - staff & administrators only (RBAC)
$router->get('/records', [StudentRecordsController::class, 'index'], 'records.index', [
    AuthMiddleware::class,
    PermissionMiddleware::require('records.view.any'),
]);
$router->get('/records/{studentId}', [StudentRecordsController::class, 'show'], 'records.show', [
    AuthMiddleware::class,
    PermissionMiddleware::require('records.view.any'),
]);

// Health record management - write operations
$router->get('/records/{studentId}/edit', [StudentRecordsController::class, 'editProfile'], 'records.edit', [
    AuthMiddleware::class,
    PermissionMiddleware::require('records.manage'),
]);
$router->post('/records/{studentId}/profile', [StudentRecordsController::class, 'updateProfile'], 'records.updateProfile', [
    AuthMiddleware::class,
    PermissionMiddleware::require('records.manage'),
]);
$router->post('/records/{studentId}/medical-history', [StudentRecordsController::class, 'addMedicalHistory'], 'records.addMedicalHistory', [
    AuthMiddleware::class,
    PermissionMiddleware::require('records.manage'),
]);
$router->get('/records/{studentId}/visits/new', [StudentRecordsController::class, 'newVisit'], 'records.newVisit', [
    AuthMiddleware::class,
    PermissionMiddleware::require('records.manage'),
]);
$router->post('/records/{studentId}/visits', [StudentRecordsController::class, 'storeVisit'], 'records.storeVisit', [
    AuthMiddleware::class,
    PermissionMiddleware::require('records.manage'),
]);

// Appointments
$router->get('/appointments', [AppointmentController::class, 'index'], 'appointments.index', [AuthMiddleware::class]);
$router->get('/appointments/calendar', [AppointmentController::class, 'calendar'], 'appointments.calendar', [AuthMiddleware::class]);
$router->get('/appointments/availability', [AppointmentController::class, 'availability'], 'appointments.availability', [AuthMiddleware::class]);
$router->get('/appointments/new', [AppointmentController::class, 'create'], 'appointments.create', [
    AuthMiddleware::class,
    PermissionMiddleware::require('appointments.request'),
]);
$router->post('/appointments', [AppointmentController::class, 'store'], 'appointments.store', [
    AuthMiddleware::class,
    PermissionMiddleware::require('appointments.request'),
]);
$router->post('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'], 'appointments.cancel', [AuthMiddleware::class]);
$router->post('/appointments/{id}/approve', [AppointmentController::class, 'approve'], 'appointments.approve', [
    AuthMiddleware::class,
    PermissionMiddleware::require('appointments.approve'),
]);
$router->post('/appointments/{id}/reject', [AppointmentController::class, 'reject'], 'appointments.reject', [
    AuthMiddleware::class,
    PermissionMiddleware::require('appointments.approve'),
]);
$router->get('/appointments/{id}/reschedule', [AppointmentController::class, 'rescheduleForm'], 'appointments.reschedule.form', [AuthMiddleware::class]);
$router->post('/appointments/{id}/reschedule', [AppointmentController::class, 'reschedule'], 'appointments.reschedule', [AuthMiddleware::class]);

// Visit History Analytics
$router->get('/analytics/visits', [VisitAnalyticsController::class, 'visits'], 'analytics.visits', [
    AuthMiddleware::class,
    PermissionMiddleware::require('analytics.view'),
]);

// Outbreak / illness-pattern detection
$router->get('/analytics/outbreaks', [OutbreakController::class, 'index'], 'analytics.outbreaks', [
    AuthMiddleware::class,
    PermissionMiddleware::require('analytics.view'),
]);
$router->post('/analytics/outbreaks/run', [OutbreakController::class, 'run'], 'analytics.outbreaks.run', [
    AuthMiddleware::class,
    PermissionMiddleware::require('analytics.manage'),
]);

// Personalized health insights
$router->post('/records/{studentId}/insights/generate', [InsightController::class, 'generate'], 'insights.generate', [
    AuthMiddleware::class,
    PermissionMiddleware::require('records.manage'),
]);
// Student-facing actions; ownership of the insight is verified in the controller.
$router->post('/profile/insights/{insightId}/read', [InsightController::class, 'markRead'], 'insights.read', [AuthMiddleware::class]);
$router->post('/profile/insights/{insightId}/dismiss', [InsightController::class, 'dismiss'], 'insights.dismiss', [AuthMiddleware::class]);

// AI decision support - server-to-server PHP->FastAPI integration.
// The browser never talks to the FastAPI service directly.
$router->post('/records/{studentId}/predictions/{type}', [AiController::class, 'predict'], 'predictions.run', [
    AuthMiddleware::class,
    PermissionMiddleware::require('records.manage'),
]);
// Symptom assessment (staff only): staff enter the symptoms a student
// described and the AI service suggests possible conditions.
$router->post('/records/{studentId}/symptoms/assess', [AiController::class, 'assessSymptoms'], 'symptoms.assess', [
    AuthMiddleware::class,
    PermissionMiddleware::require('records.manage'),
]);
$router->get('/system/ai/health', [AiController::class, 'health'], 'system.ai.health', [
    AuthMiddleware::class,
    PermissionMiddleware::require('analytics.view'),
]);

// Notifications & alerts
// Inbox + read/unread actions; the notifications.manage check also runs in the
// controller (all seeded roles hold it; defence in depth).
$router->get('/notifications', [NotificationController::class, 'index'], 'notifications.index', [AuthMiddleware::class]);
$router->post('/notifications/{id}/read', [NotificationController::class, 'markRead'], 'notifications.read', [AuthMiddleware::class]);
$router->post('/notifications/read-all', [NotificationController::class, 'markAllRead'], 'notifications.readAll', [AuthMiddleware::class]);
// Authorized health alert to a specific student (alerts.manage) - staff/admin.
$router->post('/records/{studentId}/health-alert', [NotificationController::class, 'sendHealthAlert'], 'health_alerts.send', [
    AuthMiddleware::class,
    PermissionMiddleware::require('alerts.manage'),
]);
// System announcement broadcast (users.manage) - administrators only.
$router->post('/notifications/system', [NotificationController::class, 'sendSystem'], 'notifications.system', [
    AuthMiddleware::class,
    PermissionMiddleware::require('users.manage'),
]);

// Administrative area - administrators only (vertical privilege boundary)
$router->get('/admin/area', [SystemController::class, 'adminArea'], 'admin.area', [
    AuthMiddleware::class,
    RoleMiddleware::oneOf('admin'),
]);

// Audit log - read-only viewer, audit.view permission (admin role).
$router->get('/admin/audit', [AuditLogController::class, 'index'], 'admin.audit', [
    AuthMiddleware::class,
    PermissionMiddleware::require('audit.view'),
]);

// User management - view a single user's account details (admin role only).
$router->get('/admin/user/{id}', [SystemController::class, 'userDetails'], 'admin.user.details', [
    AuthMiddleware::class,
    RoleMiddleware::oneOf('admin'),
]);
// Account activation state changes. POST + CSRF-protected; never a GET link.
$router->post('/admin/user/{id}/deactivate', [SystemController::class, 'deactivateUser'], 'admin.user.deactivate', [
    AuthMiddleware::class,
    RoleMiddleware::oneOf('admin'),
]);
$router->post('/admin/user/{id}/activate', [SystemController::class, 'activateUser'], 'admin.user.activate', [
    AuthMiddleware::class,
    RoleMiddleware::oneOf('admin'),
]);

return $router;
