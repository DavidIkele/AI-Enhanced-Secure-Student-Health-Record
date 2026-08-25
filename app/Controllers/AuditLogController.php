<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AuditLogRepository;
use App\Security\AccessControl;
use App\Services\AuditLogService;
use App\Services\AuthService;

/**
 * Audit-log viewer.
 *
 * Access is restricted to the `audit.view` permission (seeded on the
 * Administrator role only). The viewer is strictly read-only: there is no
 * route or method that updates or deletes audit entries, and the underlying
 * repository issues SELECT statements only.
 *
 * Privacy: rows show entity references and opaque change summaries, never
 * passwords, tokens, keys or clinical content (see AuditLogService).
 *
 * Viewing the audit log is itself an audited administrative action.
 */
final class AuditLogController extends BaseController
{
    private const PAGE_SIZE = 50;
    private const MAX_PAGE = 100000;

    public function index(): void
    {
        $auth = new AuthService();
        $userId = $auth->id();
        if ($userId === null || !AccessControl::can($userId, 'audit.view')) {
            $this->abort(403, 'You do not have permission to view audit logs.');
        }

        $rawPage = (string) $this->request->query('page', '1');
        $page = filter_var($rawPage, FILTER_VALIDATE_INT);
        if ($page === false || $page < 1 || $page > self::MAX_PAGE) {
            $page = 1;
        }

        $action = trim((string) $this->request->query('action', ''));

        $repo = new AuditLogRepository();
        $total = $repo->count($action);
        $pages = (int) max(1, ceil($total / self::PAGE_SIZE));
        if ($page > $pages) {
            $page = $pages;
        }
        $offset = ($page - 1) * self::PAGE_SIZE;

        $rows = $repo->recent(self::PAGE_SIZE, $offset, $action);

        AuditLogService::record(
            'view',
            'audit_logs',
            null,
            ['page' => $page, 'action' => $action === '' ? null : $action],
            $userId
        );

        $this->render('admin/audit_logs', [
            'title' => 'Audit Log | Student Health Record System',
            'page' => 'admin-audit',
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'action' => $action,
            'actions' => $repo->actions(),
        ]);
    }
}
