<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

function rb_env(string $key, string $default = ''): string
{
    $v = $_ENV[$key] ?? getenv($key);
    return ($v === false || $v === null) ? $default : (string) $v;
}

function rb_load(string $path): void
{
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($lines as $raw) {
        $line = trim($raw);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $pos));
        if ($key === '') {
            continue;
        }
        $value = trim(substr($line, $pos + 1));
        if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function rb_mysql(bool $withDb = false): string
{
    $cmd = sprintf(
        '"%s" -h %s -P %s -u %s --default-character-set=utf8mb4',
        rb_env('MYSQL_CLIENT', 'C:\xampp\mysql\bin\mysql.exe'),
        rb_env('DB_HOST', '127.0.0.1'),
        rb_env('DB_PORT', '3306'),
        rb_env('DB_USERNAME', 'root')
    );
    if ($withDb) {
        $cmd .= ' ' . rb_env('DB_NAME', 'student_health');
    }
    return $cmd;
}

function rb_run(string $cmd, string $label, bool $allowFail = false): void
{
    $out = [];
    $rc = 0;
    exec($cmd . ' 2>&1', $out, $rc);
    if ($rc !== 0 && !$allowFail) {
        fwrite(STDERR, "\n{$label} FAILED (exit {$rc})\n" . implode("\n", $out) . "\n");
        exit(1);
    }
    if ($rc !== 0) {
        fwrite(STDERR, "notice: {$label}: " . trim(implode(' ', $out)) . "\n");
    }
}

$doSchema = in_array('--schema', $argv, true);
$doSeed = in_array('--seed', $argv, true);
if (!$doSchema && !$doSeed) {
    $doSchema = true;
    $doSeed = true;
}

rb_load(dirname(__DIR__) . '/.env');

$db = rb_env('DB_NAME', 'student_health');
$pw = rb_env('DB_PASSWORD', '');
if ($pw !== '') {
    putenv('MYSQL_PWD=' . $pw);
} else {
    putenv('MYSQL_PWD');
}

$dir = __DIR__;
$mysql = rb_mysql();
$mysqlDb = rb_mysql(true);

if ($doSchema) {
    rb_run($mysql . ' -e "CREATE DATABASE IF NOT EXISTS `' . $db . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"', 'CREATE DATABASE', true);
    rb_run($mysqlDb . ' < "' . $dir . '\schema.sql"', 'SCHEMA');
    fwrite(STDOUT, "SCHEMA COMPLETE\n");
}

if ($doSeed) {
    rb_run($mysqlDb . ' < "' . $dir . '\seed_data.sql"', 'SEED');
    fwrite(STDOUT, "SEED COMPLETE\n");
}

fwrite(STDOUT, "DB SETUP COMPLETE\n");