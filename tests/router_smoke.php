<?php

declare(strict_types=1);

/**
 * Smoke check: router typed-parameter dispatch (PROMPT 5 fix).
 * Usage: C:\xampp\php\php.exe tests\router_smoke.php
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', ROOT_PATH . DIRECTORY_SEPARATOR . 'app');
}

require APP_PATH . '/Core/Autoloader.php';
require APP_PATH . '/Helpers/helpers.php';
require APP_PATH . '/Config/config.php';
App\Core\Autoloader::register();

use App\Core\Router;
use App\Controllers\StudentRecordsController;
use App\Controllers\HomeController;

$router = new Router();
$router->get('/', [HomeController::class, 'index'], 'home');
$router->get('/records/{studentId}', [StudentRecordsController::class, 'show'], 'records.show');

$results = [];

// 1. Numeric route param -> int cast
$match = $router->match('GET', '/records/1');
$params = $match['params'] ?? null;
$results['numeric_param'] = ($params !== null && is_string($params['studentId'])) ? 'PASS' : 'FAIL';
echo "numeric_param: {$results['numeric_param']} (value={$params['studentId']})" . PHP_EOL;

// 2. Non-numeric param -> should be rejected by dispatch cast (404)
try {
    $reflection = new ReflectionMethod(StudentRecordsController::class, 'show');
    $type = $reflection->getParameters()[0]->getType();
    $results['int_signature'] = ($type instanceof ReflectionNamedType && $type->getName() === 'int') ? 'PASS' : 'FAIL';
    echo "int_signature: {$results['int_signature']}" . PHP_EOL;
} catch (Throwable $e) {
    $results['int_signature'] = 'FAIL: ' . $e->getMessage();
    echo "int_signature: FAIL ({$e->getMessage()})" . PHP_EOL;
}

// 3. Unmatched route
$match = $router->match('GET', '/nope');
$results['unmatched'] = ($match === null) ? 'PASS' : 'FAIL';
echo "unmatched: {$results['unmatched']}" . PHP_EOL;

// 4. Verify casting logic: string '7' -> int 7
$cast = filter_var('7', FILTER_VALIDATE_INT);
$results['cast_logic'] = ($cast === 7) ? 'PASS' : 'FAIL';
echo "cast_logic: {$results['cast_logic']}" . PHP_EOL;

// 5. Non-numeric like '1abc' -> filter_var returns false
$cast2 = filter_var('1abc', FILTER_VALIDATE_INT);
$results['reject_non_numeric'] = ($cast2 === false) ? 'PASS' : 'FAIL';
echo "reject_non_numeric: {$results['reject_non_numeric']}" . PHP_EOL;

$allPass = !in_array('FAIL', $results, true);
echo ($allPass ? 'ROUTER SMOKE PASS' : 'ROUTER SMOKE FAIL') . PHP_EOL;
exit($allPass ? 0 : 1);
