<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Session;
use App\Repositories\NotificationRepository;
use App\Repositories\OutbreakAnalyticsRepository;
use App\Repositories\UserRepository;
use App\Security\AccessControl;
use App\Security\Security;
use App\Services\AuditLogService;
use App\Services\AuthService;

/**
 * Outbreak / illness-pattern detection.
 *
 * Authorization:
 *  - analytics.view   view the stored detection results (staff/admin).
 *  - analytics.manage run/recompute detection (staff/admin).
 *
 * Privacy: only category-level aggregates are shown; individual student
 * identities are never queried. Periods with very few cases are suppressed
 * by the repository (see PRIVACY_MIN_OBSERVED).
 */
class OutbreakController extends BaseController
{
    public function index(): void
    {
        if (!$this->can('analytics.view')) {
            $this->abort(403, 'You do not have permission to view outbreak analytics.');
        }

        $range = $this->normalizeRange();
        $repo = new OutbreakAnalyticsRepository();

        $results = $repo->results($range['from'], $range['to']);
        $summary = $repo->summaryStats($range['from'], $range['to']);

        AuditLogService::record(
            'view',
            'outbreak_analytics',
            'range:' . $range['from'] . '..' . $range['to'],
            null,
            $this->userId()
        );

        $this->render('analytics/outbreaks', [
            'title'         => 'Outbreak detection | Student Health Record System',
            'page'          => 'outbreaks',
            'from'          => $range['from'],
            'to'            => $range['to'],
            'rangeInvalid'  => $range['invalid'],
            'results'       => $results,
            'summary'       => $summary,
            'canManage'     => $this->can('analytics.manage'),
        ]);
    }

    /**
     * Recompute detection for the requested range (POST, CSRF-protected).
     */
    public function run(): void
    {
        if (!Security::verifyCsrfToken($this->request->input('_csrf'))) {
            $this->redirect('/analytics/outbreaks');
            return;
        }

        if (!$this->can('analytics.manage')) {
            $this->abort(403, 'You do not have permission to run outbreak detection.');
        }

        $range = $this->normalizeRange();
        $repo = new OutbreakAnalyticsRepository();
        $outcome = $repo->runDetection($range['from'], $range['to'], $this->userId());

        AuditLogService::record(
            'run',
            'outbreak_analytics',
            'range:' . $range['from'] . '..' . $range['to'],
            ['upserted' => $outcome['upserted'], 'flagged' => $outcome['flagged']],
            $this->userId()
        );

        // Illness-pattern alerts: notify authorized staff/admin
        // (analytics.view) about flagged periods. Content is category-level
        // aggregate only and references the detection row, so re-running the
        // same range never stacks duplicate alerts.
        if ($outcome['flagged'] > 0) {
            $this->broadcastIllnessPatterns($range['from'], $range['to']);
        }

        Session::flash(
            'success',
            sprintf(
                'Detection complete: %d period(s) reviewed, %d flagged.',
                $outcome['upserted'],
                $outcome['flagged']
            )
        );
        $this->redirect(
            '/analytics/outbreaks?from=' . urlencode($range['from']) . '&to=' . urlencode($range['to'])
        );
    }

    /**
     * @return array{from: string, to: string, invalid: bool}
     */
    private function normalizeRange(): array
    {
        return VisitAnalyticsController::normalizeRange(
            (string) $this->request->query('from', ''),
            (string) $this->request->query('to', '')
        );
    }

    private function can(string $permission): bool
    {
        $id = $this->userId();
        return $id !== 0 && AccessControl::can($id, $permission);
    }

    /**
     * Broadcast illness-pattern alerts to authorized users (analytics.view).
     * One notification per flagged detection row; content never identifies
     * individual students.
     */
    private function broadcastIllnessPatterns(string $from, string $to): void
    {
        $recipients = (new UserRepository())->userIdsWithPermission('analytics.view');
        if ($recipients === []) {
            return;
        }

        $flagged = (new OutbreakAnalyticsRepository())->flaggedRows($from, $to);
        if ($flagged === []) {
            return;
        }

        $repo = new NotificationRepository();
        $created = 0;
        foreach ($flagged as $row) {
            $tpl = NotificationController::illnessPatternNotification(
                (string) $row['illness_category'],
                (string) $row['period_start']
            );
            foreach ($recipients as $recipientId) {
                if ($repo->hasOfType($recipientId, 'outbreak', 'outbreak', (int) $row['id'])) {
                    continue;
                }
                $repo->create($recipientId, 'outbreak', $tpl['title'], $tpl['body'], 'outbreak', (int) $row['id']);
                $created++;
            }
        }

        AuditLogService::record(
            'notify',
            'notification',
            'outbreak:' . $from . '..' . $to,
            ['type' => 'outbreak', 'recipients' => count($recipients), 'flagged_rows' => count($flagged), 'created' => $created],
            $this->userId()
        );
    }

    private function userId(): int
    {
        return (int) ((new AuthService())->id() ?? 0);
    }
}
