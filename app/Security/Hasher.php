<?php

declare(strict_types=1);

namespace App\Security;

/**
 * Password hashing/verification (Argon2id preferred, bcrypt fallback).
 *
 * Argon2id is used when available (PHP 7.4+/8.2+ with argon2 support);
 * otherwise bcrypt. password_verify() transparently verifies hashes from
 * either algorithm.
 */
final class Hasher
{
    public static function hash(string $plain): string
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;

        $options = $algo === PASSWORD_ARGON2ID
            ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]
            : ['cost' => 12];

        return password_hash($plain, $algo, $options);
    }

    public static function verify(string $plain, string $hash): bool
    {
        return password_verify($plain, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT;
        $options = $algo === PASSWORD_ARGON2ID
            ? ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 2]
            : ['cost' => 12];
        return password_needs_rehash($hash, $algo, $options);
    }
}
