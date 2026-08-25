<?php

declare(strict_types=1);

/**
 * FUNCTIONAL WRITE-PATH TESTING.
 * Exercises create/update flows through the real HTTP app and verifies DB state.
 * Test data is cleaned up afterwards.
 * Usage: php tests/system/functional_write.php
 */

require __DIR__ . '/test_client.php';

$BASE = test_base_url();
$pass = 0;
$fail = 0;
$failures = [];
$cleanup = [];

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    echo ($ok ? 'PASS' : 'FAIL') . " $label" . ($detail !== '' ? " [$detail]" : '') . "\n";
    $ok ? $pass++ : $fail++;
    if (!$ok) {
        $failures[] = $label;
    }
}

function db(): PDO
{
    return test_db();
}

function loginAs(HttpTestClient $c, string $u, string $p): bool
{
    $c->get('/auth/login');
    $t = $c->csrfFromPage();
    $c->post('/auth/login', ['identifier' => $u, 'password' => $p, '_csrf' => $t]);
    return $c->status() === 200 && str_contains($c->body(), 'Dashboard');
}

$pdo = db();

// ======================================================================
// STUDENT: REQUEST AN APPOINTMENT (write)
// ======================================================================
echo "\n=== STUDENT: BOOK APPOINTMENT ===\n";
$c = new HttpTestClient($BASE);
check('student login', loginAs($c, 'ade', 'DevStudent#2026'));
$slot = date('Y-m-d H:i:00', strtotime('+10 days 15:00:00'));
$date = substr($slot, 0, 10);
$c->get('/appointments/new?staff_id=1&date=' . $date);
$token = $c->csrfFromPage();
$c->post('/appointments', [
    'staff_id' => '1',
    'scheduled_at' => $slot,
    'duration_minutes' => '30',
    'reason' => 'Functional test appointment - to be cleaned up',
    '_csrf' => $token,
]);
check('appointment request redirects to list', $c->status() === 200 && str_contains($c->body(), 'Appointments'));
$stmt = $pdo->prepare("SELECT id, status FROM appointments WHERE student_id = (SELECT id FROM students WHERE user_id = (SELECT id FROM users WHERE username='ade')) AND reason LIKE 'Functional test appointment%' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$appt = $stmt->fetch(PDO::FETCH_ASSOC);
check('appointment row created (pending)', is_array($appt) && $appt['status'] === 'pending');
$apptId = $appt ? (int) $appt['id'] : 0;
if ($apptId) {
    $cleanup[] = "appointment:$apptId";
}

// Student tries to book a slot for a DIFFERENT student (IDOR check is in security; here just book valid)
// Student cancels own appointment
$c->get('/appointments');
$token = $c->csrfFromPage();
if ($apptId) {
    $c->post("/appointments/$apptId/cancel", ['_csrf' => $token]);
    check('student cancels own pending appointment', $c->status() === 200);
    $stmt = $pdo->prepare('SELECT status FROM appointments WHERE id = ?');
    $stmt->execute([$apptId]);
    check('appointment status is cancelled', ($stmt->fetchColumn()) === 'cancelled');
    $cleanup[] = "appointment:$apptId";
}
$c->logout();

// ======================================================================
// STAFF: MANAGE APPOINTMENT (approve) + RECORD WRITE
// ======================================================================
echo "\n=== STAFF: APPROVE APPOINTMENT ===\n";
$c = new HttpTestClient($BASE);
check('doctor login', loginAs($c, 'doctor', 'DevDoctor#2026'));

// Create a fresh pending appointment for bala (student 2) to approve
$c->get('/auth/login');
$c->logout();
$c = new HttpTestClient($BASE);
check('student2 login', loginAs($c, 'bala', 'DevStudent#2026'));
$slot = date('Y-m-d H:i:00', strtotime('+11 days 09:00:00'));
$date = substr($slot, 0, 10);
$c->get('/appointments/new?staff_id=1&date=' . $date);
$token = $c->csrfFromPage();
$c->post('/appointments', [
    'staff_id' => '1',
    'scheduled_at' => $slot,
    'duration_minutes' => '30',
    'reason' => 'Functional test appointment B',
    '_csrf' => $token,
]);
$stmt = $pdo->prepare("SELECT id FROM appointments WHERE reason = 'Functional test appointment B' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$apptB = (int) $stmt->fetchColumn();
check('appointment B created', $apptB > 0);
$c->logout();

// doctor approves it
$c = new HttpTestClient($BASE);
check('doctor login again', loginAs($c, 'doctor', 'DevDoctor#2026'));
$c->get('/appointments');
$token = $c->csrfFromPage();
$c->post("/appointments/$apptB/approve", ['_csrf' => $token]);
check('staff approves appointment', $c->status() === 200);
$stmt = $pdo->prepare('SELECT status FROM appointments WHERE id = ?');
$stmt->execute([$apptB]);
check('appointment B is approved', ($stmt->fetchColumn()) === 'approved');
$cleanup[] = "appointment:$apptB";

// ======================================================================
// STAFF: CREATE A MEDICAL HISTORY + CLINIC VISIT (write)
// ======================================================================
echo "\n=== STAFF: RECORD WRITE (visit + medical history) ===\n";
$c->get("/records/1/visits/new");
$token = $c->csrfFromPage();
$c->post('/records/1/visits', [
    'visited_at' => date('Y-m-d 08:30:00', strtotime('+1 day')),
    'visit_type' => 'routine',
    'reason' => 'Functional test visit - cleanup',
    'chief_complaint' => 'Test complaint',
    'assessment_notes' => 'Test assessment',
    'outcome' => 'discharged',
    'status' => 'closed',
    'diagnosis_name' => 'Test diagnosis',
    'diagnosis_icd' => 'Z00',
    'diagnosis_severity' => 'mild',
    'temperature_c' => '36.8',
    'blood_pressure_sys' => '120',
    'blood_pressure_dia' => '80',
    '_csrf' => $token,
]);
check('visit create returns', $c->status() === 200);
$stmt = $pdo->prepare("SELECT id FROM clinic_visits WHERE reason = 'Functional test visit - cleanup' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$visitId = (int) $stmt->fetchColumn();
check('clinic visit row created', $visitId > 0);
if ($visitId) {
    $cleanup[] = "visit:$visitId";
}

// add medical history via profile edit form
$c->get('/records/1/edit');
$token = $c->csrfFromPage();
$c->post('/records/1/medical-history', [
    'condition_name' => 'Functional Test Condition',
    'description' => 'Created by functional test',
    'onset_date' => date('Y-m-d', strtotime('-1 month')),
    'is_recurring' => '0',
    'severity' => 'mild',
    'status' => 'active',
    '_csrf' => $token,
]);
check('medical history add returns', $c->status() === 200);
$stmt = $pdo->prepare("SELECT id FROM medical_histories WHERE condition_name = 'Functional Test Condition' ORDER BY id DESC LIMIT 1");
$stmt->execute();
$mhId = (int) $stmt->fetchColumn();
check('medical history row created', $mhId > 0);
if ($mhId) {
    $cleanup[] = "medical_history:$mhId";
}
$c->logout();

// ======================================================================
// NOTIFICATIONS + INSIGHTS WRITE
// ======================================================================
echo "\n=== NOTIFICATIONS / INSIGHTS WRITE ===\n";
$c = new HttpTestClient($BASE);
check('student login for notifications', loginAs($c, 'ade', 'DevStudent#2026'));
$c->get('/notifications');
$token = $c->csrfFromPage();
$stmt = $pdo->prepare("SELECT id FROM notifications WHERE user_id = (SELECT id FROM users WHERE username='ade') AND is_read = 0 ORDER BY id LIMIT 1");
$stmt->execute();
$notifId = (int) $stmt->fetchColumn();
if ($notifId) {
    $c->post("/notifications/$notifId/read", ['_csrf' => $token]);
    check('mark notification read', $c->status() === 200);
    $stmt = $pdo->prepare('SELECT is_read FROM notifications WHERE id = ?');
    $stmt->execute([$notifId]);
    check('notification is_read=1', ($stmt->fetchColumn()) === 1);
    $cleanup[] = "notification:$notifId"; // restore later
} else {
    check('notification exists to read', false, 'no unread notification for ade');
}
$c->logout();

// ======================================================================
// OUTBREAK ANALYTICS RUN (write via POST)
// ======================================================================
echo "\n=== OUTBREAK ANALYTICS RUN ===\n";
$c = new HttpTestClient($BASE);
check('admin login for outbreak', loginAs($c, 'admin', 'DevAdmin#2026'));
$c->get('/analytics/outbreaks');
$token = $c->csrfFromPage();
$c->post('/analytics/outbreaks/run', ['_csrf' => $token, 'periods' => '12']);
check('outbreak run executes (redirect)', $c->status() === 200 && (str_contains($c->body(), 'Outbreak') || str_contains($c->body(), 'detect')));

// ======================================================================
// CLEANUP
// ======================================================================
echo "\n=== CLEANUP ===\n";
foreach ($cleanup as $entry) {
    [$type, $id] = explode(':', $entry);
    try {
        if ($type === 'appointment') {
            $pdo->prepare('DELETE FROM appointments WHERE id = ?')->execute([$id]);
        } elseif ($type === 'visit') {
            $pdo->prepare('DELETE FROM diagnoses WHERE clinic_visit_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM vital_signs WHERE clinic_visit_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM medications WHERE clinic_visit_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM treatments WHERE clinic_visit_id = ?')->execute([$id]);
            $pdo->prepare('DELETE FROM clinic_visits WHERE id = ?')->execute([$id]);
        } elseif ($type === 'medical_history') {
            $pdo->prepare('DELETE FROM medical_histories WHERE id = ?')->execute([$id]);
        } elseif ($type === 'notification') {
            $pdo->prepare('UPDATE notifications SET is_read = 0 WHERE id = ?')->execute([$id]);
        }
    } catch (Throwable $e) {
        echo "  cleanup $type:$id FAILED: " . $e->getMessage() . "\n";
    }
}
echo 'cleanup done' . "\n";

echo "\n===== FUNCTIONAL WRITE SUMMARY =====\n";
echo "PASS: $pass  FAIL: $fail\n";
if ($failures) {
    echo 'Failures: ' . implode('; ', $failures) . "\n";
}
exit($fail === 0 ? 0 : 1);
