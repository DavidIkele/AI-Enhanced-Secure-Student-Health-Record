<?php

declare(strict_types=1);

require __DIR__ . '/test_client.php';

$BASE = 'http://localhost/AI-Enhanced%20Secure%20Web-Based%20Student%20Health/public';
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
    return new PDO(
        'mysql:host=127.0.0.1;port=3307;dbname=student_health;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
}

$pdo = db();
$predIdsBefore = (int) $pdo->query('SELECT COALESCE(MAX(id),0) FROM ai_predictions')->fetchColumn();

echo "\n=== END-TO-END AI (service up) ===\n";
$c = new HttpTestClient($BASE);
$c->get('/auth/login');
$t = $c->csrfFromPage();
$c->post('/auth/login', ['identifier' => 'doctor', 'password' => 'DevDoctor#2026', '_csrf' => $t]);
check('doctor login', $c->status() === 200 && str_contains($c->body(), 'Dashboard'));

// App-level AI health endpoint (JSON)
$c->get('/system/ai/health');
$health = json_decode($c->body(), true);
check('app /system/ai/health returns JSON', is_array($health));
check('AI service reported available', ($health['available'] ?? false) === true, json_encode($health['detail'] ?? null));
check('AI health exposes 3 model types', is_array($health['models'] ?? null) && count($health['models']) === 3);

// Run a real prediction via the PHP controller for student 1 (malaria_risk)
$c->get('/records/1');
$t = $c->csrfFromPage();
$c->post('/records/1/predictions/malaria_risk', ['_csrf' => $t]);
check('prediction POST redirects (no 500)', $c->status() === 200);
$stmt = $pdo->query("SELECT id, status, risk_level, risk_score, confidence, model_version, explanation FROM ai_predictions WHERE student_id = 1 AND prediction_type = 'malaria_risk' ORDER BY id DESC LIMIT 1");
$pred = $stmt->fetch(PDO::FETCH_ASSOC);
check('prediction row created', is_array($pred));
check('prediction status delivered', ($pred['status'] ?? '') === 'delivered', json_encode($pred));
check('risk_level valid', in_array($pred['risk_level'] ?? '', ['low', 'moderate', 'high'], true));
check('risk_score in [0,1]', is_numeric($pred['risk_score'] ?? null) && $pred['risk_score'] >= 0 && $pred['risk_score'] <= 1);
check('confidence in [0,1]', is_numeric($pred['confidence'] ?? null) && $pred['confidence'] >= 0 && $pred['confidence'] <= 1);
check('model_version present', !empty($pred['model_version']));
$predId = (int) ($pred['id'] ?? 0);

// Run typhoid + asthma to cover all 3 types end-to-end
$c->get('/records/1');
$t = $c->csrfFromPage();
$c->post('/records/1/predictions/typhoid_risk', ['_csrf' => $t]);
$stmt = $pdo->query("SELECT status FROM ai_predictions WHERE student_id = 1 AND prediction_type = 'typhoid_risk' ORDER BY id DESC LIMIT 1");
check('typhoid prediction delivered', $stmt->fetchColumn() === 'delivered');

$c->get('/records/1');
$t = $c->csrfFromPage();
$c->post('/records/1/predictions/asthma_exacerbation', ['_csrf' => $t]);
$stmt = $pdo->query("SELECT status FROM ai_predictions WHERE student_id = 1 AND prediction_type = 'asthma_exacerbation' ORDER BY id DESC LIMIT 1");
check('asthma prediction delivered', $stmt->fetchColumn() === 'delivered');

// Unknown prediction type -> 404
$c->post('/records/1/predictions/brain_surgery_risk', ['_csrf' => $t]);
check('unknown prediction type -> 404', $c->status() === 404, 'status=' . $c->status());

// Student (no records.manage) denied prediction
$c->logout();
$c->get('/auth/login');
$t = $c->csrfFromPage();
$c->post('/auth/login', ['identifier' => 'ade', 'password' => 'DevStudent#2026', '_csrf' => $t]);
$c->get('/');
$t = $c->csrfFromPage();
$c->post('/records/1/predictions/malaria_risk', ['_csrf' => $t]);
check('student denied prediction route', $c->status() === 403, 'status=' . $c->status());

// Clean up prediction rows created by this test (and their audit entries).
// Seed data lives in ids 1-2; anything above that was created by test runs.
$pdo->exec("DELETE FROM ai_predictions WHERE id > 2");
$pdo->exec("DELETE FROM audit_logs WHERE entity_type = 'ai_prediction' AND CAST(entity_id AS UNSIGNED) > 2");

echo "\n===== E2E AI SUMMARY =====\n";
echo "PASS: $pass  FAIL: $fail\n";
if ($failures) {
    echo 'Failures: ' . implode('; ', $failures) . "\n";
}
exit($fail === 0 ? 0 : 1);
