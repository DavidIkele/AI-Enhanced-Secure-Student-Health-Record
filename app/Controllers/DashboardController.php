<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Repositories\AppointmentsRepository;
use App\Repositories\AnalyticsRepository;
use App\Repositories\HealthAlertRepository;
use App\Repositories\HealthRecordRepository;
use App\Security\AccessControl;
use App\Services\AuthService;

/**
 * Role-aware dashboard. Students see their own data; staff/admin see aggregate stats.
 */
class DashboardController extends BaseController
{
    public function index(): void
    {
        $auth = new AuthService();
        $user = $auth->user();
        $userId = $auth->id();
        $isStaff = AccessControl::currentCan('records.manage') || AccessControl::currentCan('analytics.view');

        if ($isStaff) {
            $this->renderStaffDashboard($user, $userId);
            return;
        }

        // The student area has no dashboard: students land on their profile.
        $this->redirect('/profile');
    }

    private function renderStaffDashboard(array $user, int $userId): void
    {
        $analytics = (new AnalyticsRepository())->summary();
        $totalStudents = isset($analytics['unique_students']) ? $analytics['unique_students'] : 0;
        $totalAppointments = isset($analytics['total']) ? $analytics['total'] : 0;
        $totalVisits = isset($analytics['total_visits']) ? $analytics['total_visits'] : 0;

        $appointments = new AppointmentsRepository();
        $pendingAppointmentsAll = $appointments->allForManagement('pending');
        $pendingAppointmentsDisplayed = array_slice($pendingAppointmentsAll, 0, 5);

        $today = date('Y-m-d');
        $todayAppointments = $appointments->scheduledForStaffBetween($userId, $today . ' 00:00:00', $today . ' 23:59:59');
        $todayAppointments = array_filter($todayAppointments, function ($a) {
            return date('Y-m-d', strtotime($a['scheduled_at'])) === date('Y-m-d');
        });

        $healthRecord = new HealthRecordRepository();
        $recentVisits = $healthRecord->clinicVisitsForStudent($userId ?? 0);

        $healthAlert = new HealthAlertRepository();
        $healthAlerts = $healthAlert->forStudent($userId ?? 0);

        $analytics = (new AnalyticsRepository())->summary();

        $this->render('dashboard/index', [
            'title' => 'Staff Dashboard | Student Health Record System',
            'page' => 'dashboard',
            'user' => $user,
            'isStudent' => false,
            'stats' => [
                'total_students' => $totalStudents,
                'total_appointments' => isset($analytics['total']) ? $analytics['total'] : 0,
                'total_visits' => isset($analytics['total_visits']) ? $analytics['total_visits'] : 0,
                'pending_appointments' => count($pendingAppointmentsAll),
            ],
            'todayAppointments' => $todayAppointments,
            'pendingAppointments' => $pendingAppointmentsDisplayed,
            'pendingCount' => count($pendingAppointmentsAll),
            'recentVisits' => $recentVisits,
            'healthAlerts' => $healthAlerts,
            'analytics' => $analytics,
        ]);
    }
}
