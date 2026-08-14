<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Base repository. Repositories encapsulate data access and always use PDO
 * prepared statements. Concrete repositories add typed, safe query methods.
 */
abstract class BaseRepository
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = \App\Services\Database::connection();
    }

    /**
     * Run a prepared statement and return the statement for iteration.
     *
     * @param array<int|string, mixed> $params
     */
    protected function prepare(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}