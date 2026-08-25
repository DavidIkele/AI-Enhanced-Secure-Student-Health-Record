<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Repositories\HealthAlertRepository;
use App\Repositories\NotificationRepository;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use App\Security\AccessControl;
use App\Security\Security;
use App\Services\AuditLogService;
use App\Services\AuthService;

/**
 * Notifications and alerts.
 *
 * The `notifications` table is keyed directly by the recipient user id, so
 * every read/mutation is scoped to the authenticated session user (IDOR/BOLA
 * defence). Notification preview content is generated from fixed, privacy-safe
 * templates and never contains clinical detail (diagnoses, reasons, notes).
 *
 * Who may receive notifications:
 *  - appointment events: the student and staff who own that appointment
 *  - authorized health alerts: the specific student selected by staff
 *    (alerts.manage)
 *  - illness-pattern alerts: users with analytics.view (aggregate only)
 *  - system announcements: all active users, authored by administrators
 *    (users.manage)
 *
 * The recipient set is always resolved server-side; a client can never choose
 * who is notified.
 */
final class NotificationController extends BaseController
{
    /**
     * My notification inbox (read/unread list).
     */
    public function index(): void
    {
        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null || !AccessControl::can($userId, 'notifications.manage')) {
            $this->abort(403, 'You do not have permission to view notifications.');
        }

        $repo = new NotificationRepository();
        $this->render('notifications/index', [
            'title' => 'Notifications | Student Health Record System',
            'page' => 'notifications',
            'notifications' => $repo->forUser($userId),
            'unreadCount' => $repo->countUnread($userId),
            'canBroadcast' => AccessControl::can($userId, 'users.manage'),
        ]);
    }

    /**
     * Mark one of the user's own notifications as read.
     */
    public function markRead(int $id): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/notifications');
            return;
        }

        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null || !AccessControl::can($userId, 'notifications.manage')) {
            $this->abort(403, 'You do not have permission to modify notifications.');
        }

        $repo = new NotificationRepository();
        $notification = $repo->findById($id);
        if ($notification === null) {
            $this->abort(404, 'Notification not found.');
        }
        if ((int) $notification['user_id'] !== $userId) {
            $this->abort(403, 'You are not allowed to modify this notification.');
        }

        if ($repo->markRead($id, $userId)) {
            AuditLogService::record('read', 'notification', (string) $id, ['type' => (string) $notification['type']], $userId);
            Session::flash('success', 'Notification marked as read.');
        }
        $this->redirect('/notifications');
    }

    /**
     * Mark all of the user's notifications read.
     */
    public function markAllRead(): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/notifications');
            return;
        }

        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null || !AccessControl::can($userId, 'notifications.manage')) {
            $this->abort(403, 'You do not have permission to modify notifications.');
        }

        $count = (new NotificationRepository())->markAllRead($userId);
        AuditLogService::record('read_all', 'notification', null, ['count' => $count], $userId);
        Session::flash($count > 0 ? 'success' : 'info', $count > 0 ? 'All notifications marked as read.' : 'You have no unread notifications.');
        $this->redirect('/notifications');
    }

    /**
     * Authorized health alert to a specific student (staff/admin, alerts.manage).
     * Content comes from a fixed privacy-safe template; the clinical reason is
     * never transmitted.
     */
    public function sendHealthAlert(int $studentId): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/records/' . $studentId);
            return;
        }

        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null || !AccessControl::can($userId, 'alerts.manage')) {
            $this->abort(403, 'You do not have permission to send health alerts.');
        }

        $student = (new StudentRepository())->findById($studentId);
        if ($student === null) {
            $this->abort(404, 'Student not found.');
        }

        $templates = self::healthAlertTemplates();
        $template = (string) $this->request->input('template', '');
        if (!isset($templates[$template])) {
            Session::flash('danger', 'Please choose a valid alert type.');
            $this->redirect('/records/' . $studentId);
            return;
        }

        $tpl = $templates[$template];
        $recipientUserId = (int) $student['user_id'];

        $alertId = (new HealthAlertRepository())->create(
            $studentId,
            'personal',
            'warning',
            $tpl['title'],
            $tpl['body'],
            ['template' => $template, 'sent_by' => $userId],
            $userId
        );
        $notificationId = (new NotificationRepository())->create(
            $recipientUserId,
            'alert',
            $tpl['title'],
            $tpl['body'],
            'health_alert',
            $alertId
        );

        AuditLogService::record('create', 'health_alert', (string) $alertId, ['student_id' => $studentId, 'template' => $template], $userId);
        AuditLogService::record('create', 'notification', (string) $notificationId, ['type' => 'alert', 'recipient_user_id' => $recipientUserId], $userId);

        Session::flash('success', 'Health alert sent to ' . $student['last_name'] . ', ' . $student['first_name'] . '.');
        $this->redirect('/records/' . $studentId);
    }

    /**
     * System announcement broadcast to every active user (admin only,
     * users.manage). The same title/body is de-duplicated, so re-sending an
     * identical announcement does not stack duplicates.
     */
    public function sendSystem(): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/notifications');
            return;
        }

        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null || !AccessControl::can($userId, 'users.manage')) {
            $this->abort(403, 'You do not have permission to send system announcements.');
        }

        $title = trim((string) $this->request->input('title', ''));
        $body = trim((string) $this->request->input('body', ''));

        if ($title === '' || mb_strlen($title) > 80 || mb_strlen($body) > 150) {
            Session::flash('danger', 'Provide a title (max 80 characters) and message (max 150 characters).');
            $this->redirect('/notifications');
            return;
        }

        // Stable event fingerprint so identical announcements de-duplicate
        // across users and repeated sends (fits in BIGINT UNSIGNED).
        $referenceId = (int) sprintf('%u', crc32($title . "\n" . $body));
        $recipients = (new UserRepository())->allActiveIds();

        $repo = new NotificationRepository();
        $created = 0;
        foreach ($recipients as $recipientId) {
            if ($repo->hasOfType($recipientId, 'system', 'system', $referenceId)) {
                continue;
            }
            $repo->create($recipientId, 'system', $title, $body, 'system', $referenceId);
            $created++;
        }

        AuditLogService::record(
            'notify',
            'notification',
            null,
            ['type' => 'system', 'recipients' => count($recipients), 'created' => $created],
            $userId
        );

        Session::flash(
            $created > 0 ? 'success' : 'info',
            $created > 0 ? "System announcement sent to {$created} user(s)." : 'That announcement was already sent to everyone.'
        );
        $this->redirect('/notifications');
    }

    // ------------------------------------------------------------------
    // Privacy-safe content templates (pure, unit-testable)
    // ------------------------------------------------------------------

    /**
     * Fixed health-alert templates. Bodies are intentionally generic: they
     * never include a diagnosis, clinical reason or test result.
     *
     * @return array<string, array{title: string, body: string}>
     */
    public static function healthAlertTemplates(): array
    {
        return [
            'follow_up' => [
                'title' => 'Follow-up recommended',
                'body' => 'A clinic follow-up visit is recommended. Please schedule an appointment at your convenience.',
            ],
            'results' => [
                'title' => 'Results available',
                'body' => 'Some of your recent results are ready to be reviewed with clinic staff.',
            ],
            'general' => [
                'title' => 'Health advisory',
                'body' => 'A general health advisory is available from the clinic. Please visit the student health centre for details.',
            ],
            'review' => [
                'title' => 'Review recommended',
                'body' => 'The clinic recommends you book a review appointment when convenient.',
            ],
        ];
    }

    /**
     * Appointment-notification template. Only the event and the appointment
     * date/time are included — never the request reason.
     *
     * @return array{title: string, body: string}
     */
    public static function appointmentNotification(string $event, string $when): array
    {
        $label = date('D, j M Y H:i', max(0, (int) strtotime($when)));

        return match ($event) {
            'requested' => [
                'title' => 'New appointment request',
                'body' => 'A new appointment request has been submitted for ' . $label . '.',
            ],
            'approved' => [
                'title' => 'Appointment approved',
                'body' => 'Your appointment on ' . $label . ' has been approved.',
            ],
            'rejected' => [
                'title' => 'Appointment not approved',
                'body' => 'Your appointment request on ' . $label . ' was not approved.',
            ],
            'cancelled' => [
                'title' => 'Appointment cancelled',
                'body' => 'The appointment on ' . $label . ' has been cancelled.',
            ],
            'rescheduled' => [
                'title' => 'Appointment rescheduled',
                'body' => 'An appointment has been rescheduled to ' . $label . '.',
            ],
            default => ['title' => 'Appointment update', 'body' => 'There has been an update to an appointment on ' . $label . '.'],
        };
    }

    /**
     * Illness-pattern alert template. Category-level aggregate only; no
     * student identities or clinical details.
     *
     * @return array{title: string, body: string}
     */
    public static function illnessPatternNotification(string $category, string $weekStart): array
    {
        return [
            'title' => 'Illness-pattern alert',
            'body' => 'An elevated pattern was detected for ' . $category . ' for the week beginning ' . $weekStart . '. Review the outbreak analytics for details.',
        ];
    }
}
