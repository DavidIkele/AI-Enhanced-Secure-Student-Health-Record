<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Base model.
 *
 * All Eloquent-style query logic lives in repositories; models represent
 * entities and (later) expose explicit, safe query builders built on PDO
 * prepared statements. This base class only wires up the database handle.
 */
abstract class Model
{
    protected object $db;

    public function __construct()
    {
        $this->db = \App\Services\Database::connection();
    }

    /**
     * The database table the model maps to. Overridden by concrete models.
     */
    abstract public function table(): string;
}