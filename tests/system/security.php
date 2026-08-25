<?php

declare(strict_types=1);

/**
 * SECURITY TEST SUITE.
 *
 * Covers: SQLi, XSS, CSRF, IDOR/BOLA, privilege escalation, auth bypass,
 * session attacks, rate limiting, SSRF (AiClient base-URL guard), and
 * information disclosure (headers, error pages, generic messages).
 *
 * Tests run against the live app at TEST_BASE_URL via HttpTestClient.
 */

require __DIR__ . '/test_client.php';

$BASE = test_base_url();
$pass = 0;
$fail = 0;
$failures = [];

function check(string $label, bool $ok, string $detail = ''): void
{
    global $pass, $fail, $failures;
    echo ($ok ? 'PASS' : 'FAIL') . " $label" . ($detail !== '' ? " [$detail]" : '') . "\n";
    $ok ? $pass++ : $fail++;
    if (!$ok) {
        $failures[] = $label . ($detail !== '' ? " [$detail]" : '');
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

// Clean up rate-limit state before each login test so the shared test IP
// (127.0.0.1) never trips the IP-keyed brute-force limiter mid-suite.
function resetLoginState(PDO $pdo): void
{
    $pdo->exec('DELETE FROM login_attempts');
    $pdo->exec('UPDATE users SET failed_login_attempts = 0, locked_until = NULL');
}

$pdo = db();
resetLoginState($pdo);

// ======================================================================
// 1. AUTH BYPASS: unauthenticated requests to protected routes must NOT
//    return the protected page. All redirect to /auth/login.
// ======================================================================
echo "\n=== AUTH BYPASS (unauthenticated) ===\n";
$c = new HttpTestClient($BASE);
$protected = [
    '/dashboard',
    '/profile',
    '/records',
    '/records/1',
    '/appointments',
    '/appointments/calendar',
    '/appointments/new',
    '/analytics/visits',
    '/analytics/outbreaks',
    '/system/ai/health',
    '/notifications',
    '/admin/area',
    '/admin/audit',
];
foreach ($protected as $path) {
    $c->get($path);
    $body = $c->body();
    check("unauth blocked: $path", $c->status() === 200 && str_contains($body, 'Sign in') && !str_contains($body, 'Dashboard'), 'status=' . $c->status());
}
// Unauth POST with no token (no session) → should be bounced (redirect to login).
$c->get('/auth/login');
$c->post('/appointments', ['staff_id' => 1]);
check('unauth POST /appointments blocked', $c->status() === 200 && str_contains($c->body(), 'Sign in'));

// ======================================================================
// 2. PRIVILEGE ESCALATION: student must be denied staff/admin routes.
// ======================================================================
echo "\n=== PRIVILEGE ESCALATION (student attempts staff/admin) ===\n";
$c = new HttpTestClient($BASE);
check('student login', loginAs($c, 'ade', 'DevStudent#2026'));
$staffRoutes = [
    '/records',
    '/records/1',
    '/records/1/edit',
    '/records/1/visits/new',
    '/analytics/visits',
    '/analytics/outbreaks',
    '/admin/area',
    '/admin/audit',
    '/system/ai/health',
];
foreach ($staffRoutes as $path) {
    $c->get($path);
    // Expect a 403 (ForbiddenHttpException) — NOT a 200 with protected content.
    $ok = $c->status() === 403;
    check("student denied: $path", $ok, 'status=' . $c->status());
}

// Student POST to staff write route (no CSRF, but permission should fail first)
$c->get('/');
$c->post('/records/1/medical-history', ['condition_name' => 'x']);
check('student denied POST /records/1/medical-history', $c->status() === 403, 'status=' . $c->status());

// Student POST to admin-only broadcast
$c->post('/notifications/system', ['title' => 'x', 'body' => 'y']);
check('student denied POST /notifications/system', $c->status() === 403, 'status=' . $c->status());

// ======================================================================
// 3. IDOR / BOLA: student A must not access student B's data.
// ======================================================================
echo "\n=== IDOR / BOLA (cross-student access) ===\n";
// Student ade tries to read student 2's record page directly
$c->get('/records/2');
check('student blocked from /records/2', $c->status() === 403, 'status=' . $c->status());

// Student tries to cancel another student's appointment (need appointment ids)
$stmt = $pdo->query("SELECT a.id, s.user_id FROM appointments a JOIN students s ON s.id = a.student_id WHERE a.student_id = 2 ORDER BY a.id LIMIT 1");
$otherAppt = $stmt->fetch(PDO::FETCH_ASSOC);
if ($otherAppt) {
    $c->get('/appointments');
    $token = $c->csrfFromPage();
    $c->post("/appointments/{$otherAppt['id']}/cancel", ['_csrf' => $token]);
    check('student blocked cancelling other student appointment', $c->status() === 403, 'status=' . $c->status());
    $stmt = $pdo->prepare('SELECT status FROM appointments WHERE id = ?');
    $stmt->execute([$otherAppt['id']]);
    check('other appointment status unchanged', $stmt->fetchColumn() !== 'cancelled');
} else {
    check('appointment of student 2 exists for IDOR test', false, 'no appointment for student 2');
}

// Student tries to mark another student's insight read (IDOR on insight ownership)
$stmt = $pdo->query("SELECT hi.id FROM health_insights hi WHERE hi.student_id = 2 ORDER BY hi.id LIMIT 1");
$otherInsight = (int) $stmt->fetchColumn();
if ($otherInsight > 0) {
    $c->get('/profile');
    $token = $c->csrfFromPage();
    $c->post("/profile/insights/$otherInsight/read", ['_csrf' => $token]);
    check('student blocked reading other student insight', $c->status() === 403, 'status=' . $c->status());
} else {
    check('insight for student 2 exists for IDOR test', false, 'no insight for student 2');
}

// Student tries to read another user's notification (bala = user 5; ade = user 4)
$stmt = $pdo->query("SELECT id FROM notifications WHERE user_id = (SELECT id FROM users WHERE username='bala') ORDER BY id LIMIT 1");
$otherNotif = (int) $stmt->fetchColumn();
if ($otherNotif > 0) {
    $c->get('/notifications');
    $token = $c->csrfFromPage();
    $c->post("/notifications/$otherNotif/read", ['_csrf' => $token]);
    check('student blocked reading other user notification', $c->status() === 403, 'status=' . $c->status());
} else {
    check('notification for bala exists for IDOR test', false, 'no notification for bala');
}

// ======================================================================
// 4. SQL INJECTION
// ======================================================================
echo "\n=== SQL INJECTION ===\n";
$c = new HttpTestClient($BASE);
check('login with SQLi payload fails safely', loginAs($c, "admin' OR '1'='1", 'DevAdmin#2026') === false || !loginAs($c, "admin' OR '1'='1", 'DevAdmin#2026'), 'authenticated with SQLi?');
resetLoginState($pdo);

// SQLi via route parameter (must not throw 500 / leak; typed int => 404)
$c = new HttpTestClient($BASE);
check('admin login', loginAs($c, 'admin', 'DevAdmin#2026'));
foreach (["1 OR 1=1", "1; DROP TABLE users;--", "1'--", "-1 UNION SELECT * FROM users--"] as $payload) {
    $c->get('/records/' . urlencode($payload));
    $status = $c->status();
    $body = $c->body();
    check("SQLi path param '$payload' does not 500/leak", $status !== 500 && !str_contains(strtolower($body), 'sqlstate') && !str_contains(strtolower($body), 'pdoexception'), 'status=' . $status);
}

// SQLi via search/filter query params (appointments status filter)
$c->get("/appointments?status=' OR '1'='1");
$status = $c->status();
$body = $c->body();
check("SQLi query param does not 500/leak", $status !== 500 && !str_contains(strtolower($body), 'sqlstate') && !str_contains(strtolower($body), 'pdoexception'), 'status=' . $status);

// SQLi via appointments filter date/status (numeric cast)
$c->get("/appointments?date=2026-01-01' OR '1'='1");
check("SQLi date param handled", $c->status() !== 500 && !str_contains(strtolower($c->body()), 'sqlstate'), 'status=' . $c->status());

// SQLi via availability params
$c->get("/appointments/availability?staff_id=1' OR '1'='1&date=2026-12-01");
check("SQLi availability staff_id handled", $c->status() !== 500 && !str_contains(strtolower($c->body()), 'sqlstate'), 'status=' . $c->status());

// SQLi in POST body (medical history) — validation should reject non-conforming data
$c->get('/records/1');
$token = $c->csrfFromPage();
$c->post('/records/1/medical-history', [
    'condition_name' => "x' OR '1'='1",
    'description' => 'test',
    'onset_date' => '2026-01-01',
    'severity' => 'mild',
    'status' => 'active',
    '_csrf' => $token,
]);
check("SQLi in POST condition_name handled (no 500)", $c->status() !== 500 && !str_contains(strtolower($c->body()), 'sqlstate'), 'status=' . $c->status());
// Clean up any row that may have been inserted
$pdo->exec("DELETE FROM medical_histories WHERE description = 'test' AND condition_name LIKE '%OR%'");

// ======================================================================
// 5. XSS
// ======================================================================
echo "\n=== XSS (reflected + stored) ===\n";
$c = new HttpTestClient($BASE);

// Stored XSS: inject script into medical history condition_name, verify escaped on output
$c = new HttpTestClient($BASE);
check('admin login for stored XSS', loginAs($c, 'admin', 'DevAdmin#2026'));
$xss = '<script>window.__xss=1</script>';
$c->get('/records/1');
$token = $c->csrfFromPage();
$c->post('/records/1/medical-history', [
    'condition_name' => $xss,
    'description' => 'XSS cleanup',
    'onset_date' => '2026-01-01',
    'severity' => 'mild',
    'status' => 'active',
    '_csrf' => $token,
]);
$stmt = $pdo->prepare('SELECT id FROM medical_histories WHERE condition_name = ? ORDER BY id DESC LIMIT 1');
$stmt->execute([$xss]);
$xssId = (int) $stmt->fetchColumn();
check('stored XSS payload was persisted', $xssId > 0, 'id=' . $xssId);
if ($xssId > 0) {
    $c->get('/records/1');
    $body = $c->body();
    check('stored XSS payload is escaped on output', str_contains($body, '&lt;script&gt;') || str_contains($body, '&lt;script&gt;window'), 'raw script present: ' . str_contains($body, $xss));
    check('stored XSS raw script NOT in output', !str_contains($body, '<script>window.__xss'));
    $pdo->prepare('DELETE FROM medical_histories WHERE id = ?')->execute([$xssId]);
}

// Reflected XSS via query param on login page (error messages / identifiers)
$c = new HttpTestClient($BASE);
$c->get('/auth/login');
$token = $c->csrfFromPage();
$c->post('/auth/login', ['identifier' => '<script>alert(1)</script>', 'password' => 'x', '_csrf' => $token]);
$body = $c->body();
check('reflected identifier is escaped on login failure', !str_contains($body, '<script>alert(1)</script>') || str_contains($body, '&lt;script&gt;'));
resetLoginState($pdo);

// XSS via query params on records page (page param reflects?)
$c = new HttpTestClient($BASE);
check('admin login for XSS pages', loginAs($c, 'admin', 'DevAdmin#2026'));
$c->get('/records?page=<script>alert(2)</script>');
check('reflected page param is escaped', !str_contains($c->body(), '<script>alert(2)</script>'));

// ======================================================================
// 6. CSRF
// ======================================================================
echo "\n=== CSRF ===\n";
$c = new HttpTestClient($BASE);
check('admin login', loginAs($c, 'admin', 'DevAdmin#2026'));

// No token at all → rejected (redirected back), no DB change
$before = (int) $pdo->query("SELECT COUNT(*) FROM medical_histories")->fetchColumn();
$c->post('/records/1/medical-history', [
    'condition_name' => 'CSRF-NO-TOKEN',
    'description' => 'test',
    'onset_date' => '2026-01-01',
    'severity' => 'mild',
    'status' => 'active',
]);
$after = (int) $pdo->query("SELECT COUNT(*) FROM medical_histories")->fetchColumn();
check('POST without CSRF token rejected (no insert)', $after === $before, "before=$before after=$after");
$pdo->exec("DELETE FROM medical_histories WHERE condition_name = 'CSRF-NO-TOKEN'");

// Wrong token → rejected
$c->post('/records/1/medical-history', [
    'condition_name' => 'CSRF-WRONG-TOKEN',
    'description' => 'test',
    'onset_date' => '2026-01-01',
    'severity' => 'mild',
    'status' => 'active',
    '_csrf' => 'deadbeefdeadbeefdeadbeefdeadbeef',
]);
$after = (int) $pdo->query("SELECT COUNT(*) FROM medical_histories")->fetchColumn();
check('POST with wrong CSRF token rejected (no insert)', $after === $before, "before=$before after=$after");
$pdo->exec("DELETE FROM medical_histories WHERE condition_name = 'CSRF-WRONG-TOKEN'");

// Logout without CSRF → session persists (token required)
$c->get('/dashboard');
$c->post('/auth/logout', []);
$c->get('/dashboard');
check('logout without CSRF does not destroy session', $c->status() === 200 && str_contains($c->body(), 'Dashboard'));

// ======================================================================
// 7. SESSION ATTACKS
// ======================================================================
echo "\n=== SESSION ATTACKS ===\n";
$c = new HttpTestClient($BASE);
// Login page must not expose auth state (GuestMiddleware) and must set session cookie flags
$c->get('/auth/login');
$h = $c->header('set-cookie');
check('session cookie is HttpOnly', str_contains((string) $h, 'HttpOnly'), (string) $h);
check('session cookie is SameSite', str_contains((string) $h, 'SameSite'), (string) $h);

// Session fixation: cookie value should change after login (regeneration)
$beforeLogin = $c->sessionCookieValue();
check('admin login (session fixation check)', loginAs($c, 'admin', 'DevAdmin#2026'));
$afterLogin = $c->sessionCookieValue();
check('session ID regenerated on login', $beforeLogin !== '' && $beforeLogin !== $afterLogin, substr((string) $beforeLogin, 0, 30) . ' vs ' . substr((string) $afterLogin, 0, 30));

// Logout (proper CSRF) must invalidate session
$c->get('/');
$token = $c->csrfFromPage();
$c->post('/auth/logout', ['_csrf' => $token]);
$c->get('/dashboard');
check('session invalidated after logout', $c->status() === 200 && str_contains($c->body(), 'Sign in'));

// ======================================================================
// 8. RATE LIMITING (brute-force protection)
// ======================================================================
echo "\n=== RATE LIMITING ===\n";
resetLoginState($pdo);
$c = new HttpTestClient($BASE);
$rateKey = 'rate_' . substr(bin2hex(random_bytes(4)), 0, 8);
for ($i = 1; $i <= 5; $i++) {
    $c->get('/auth/login');
    $token = $c->csrfFromPage();
    $c->post('/auth/login', ['identifier' => $rateKey, 'password' => 'wrongpass' . $i, '_csrf' => $token]);
}
// 6th attempt should be blocked
$c->get('/auth/login');
$token = $c->csrfFromPage();
$c->post('/auth/login', ['identifier' => $rateKey, 'password' => 'stillwrong', '_csrf' => $token]);
$body = $c->body();
check('6th login attempt rate-limited', str_contains($body, 'Too many login attempts'), 'status=' . $c->status());
resetLoginState($pdo);
// Verify a clean account still logs in after rate-limit keys removed
$c = new HttpTestClient($BASE);
check('login works after rate-limit cleared', loginAs($c, 'admin', 'DevAdmin#2026'));

// ======================================================================
// 9. SSRF — AiClient base-URL allow-list (unit-level via app code)
// ======================================================================
echo "\n=== SSRF (AiClient allow-list) ===\n";
// Exercise validatedBaseUrl: point AI_BASE_URL at an internal-metadata host and
// AI_ALLOWED_HOSTS at a list that excludes it. Real env vars win over .env, so
// a child process with putenv() values exercises the guard before any network.
$root = dirname(__DIR__, 2);
$phpBin = test_php_binary();
$ssrfTest = <<<'PHP'
<?php
declare(strict_types=1);
putenv('AI_BASE_URL=http://169.254.169.254:8000');
putenv('AI_ALLOWED_HOSTS=127.0.0.1,localhost');
putenv('AI_ENABLED=true');
putenv('AI_API_KEY=dev-ai-service-key');
$root = 'ROOT_PLACEHOLDER';
define('ROOT_PATH', $root);
define('APP_PATH', $root . '/app');
define('PUBLIC_PATH', $root . '/public');
require $root . '/app/Core/Autoloader.php';
App\Core\Autoloader::register();
require $root . '/app/Helpers/helpers.php';
require $root . '/app/Config/config.php';
try {
    App\Services\AiClient::predict('malaria_risk', ['fever_days' => 3]);
    echo 'ALLOWED';
} catch (App\Services\AiServiceException $e) {
    echo $e->category() === App\Services\AiServiceException::CATEGORY_CONFIG && str_contains($e->getMessage(), 'SSRF')
        ? 'BLOCKED' : 'WRONGCATEGORY:' . $e->category() . ':' . $e->getMessage();
}
PHP;
$ssrfTest = str_replace('ROOT_PLACEHOLDER', str_replace('\\', '/', $root), $ssrfTest);
file_put_contents(sys_get_temp_dir() . '/p18_ssrf.php', $ssrfTest);
$out = trim(shell_exec(escapeshellarg($phpBin) . ' ' . escapeshellarg(sys_get_temp_dir() . '/p18_ssrf.php') . ' 2>&1'));
@unlink(sys_get_temp_dir() . '/p18_ssrf.php');
check('AiClient blocks disallowed host (SSRF guard)', $out === 'BLOCKED', $out);

// ======================================================================
// 10. INFORMATION DISCLOSURE
// ======================================================================
echo "\n=== INFORMATION DISCLOSURE ===\n";
$c = new HttpTestClient($BASE);
$c->get('/');
check('X-Content-Type-Options: nosniff', $c->header('x-content-type-options') === 'nosniff', $c->header('x-content-type-options'));
check('X-Frame-Options: DENY', $c->header('x-frame-options') === 'DENY', $c->header('x-frame-options'));
check('CSP present', str_contains($c->header('content-security-policy'), 'default-src'), $c->header('content-security-policy'));
check('Referrer-Policy present', $c->header('referrer-policy') === 'strict-origin-when-cross-origin', $c->header('referrer-policy'));
check('Permissions-Policy present', str_contains($c->header('permissions-policy'), 'camera=()'));

// Error pages must not leak stack traces / SQL / credentials
$c->get('/records/999999');
$body = $c->body();
check('404 page does not leak stack/SQL', !str_contains(strtolower($body), 'sqlstate') && !str_contains(strtolower($body), 'stack trace') && !str_contains(strtolower($body), 'pdoexception'));

// Login failure messages are generic (no user enumeration)
resetLoginState($pdo);
$c = new HttpTestClient($BASE);
$c->get('/auth/login');
$t = $c->csrfFromPage();
$c->post('/auth/login', ['identifier' => 'nonexistent_user_xyz', 'password' => 'wrong', '_csrf' => $t]);
$b1 = $c->body();
$c->get('/auth/login');
$t = $c->csrfFromPage();
$c->post('/auth/login', ['identifier' => 'ade', 'password' => 'wrong', '_csrf' => $t]);
$b2 = $c->body();
// Both should show the same generic message (no "unknown user")
check('no user enumeration (generic error)', str_contains($b1, 'Invalid') === str_contains($b2, 'Invalid') && !str_contains($b1, 'unknown'));
resetLoginState($pdo);

// Login form has no password pre-fill / autocomplete hint exposure is fine; check login page fields
$c->get('/auth/login');
check('login form has CSRF token', $c->csrfFromPage() !== '');

// ======================================================================
echo "\n===== SECURITY SUMMARY =====\n";
resetLoginState($pdo);
echo "PASS: $pass  FAIL: $fail\n";
if ($failures) {
    echo 'Failures: ' . implode('; ', $failures) . "\n";
}
exit($fail === 0 ? 0 : 1);
