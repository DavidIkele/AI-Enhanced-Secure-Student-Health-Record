<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Healthcare staff data access (PROMPT 6).
 *
 * Staff records are referenced by appointments (healthcare_staff_id). Only
 * active staff are offered for booking and management views.
 */
final class HealthcareStaffRepository extends BaseRepository
{
    /**
     * List active staff for booking/management dropdowns.
     *
     * @return array<int, array<string, mixed>>
     */
    public function allActive(): array
    {
        $stmt = $this->prepare(
            'SELECT hs.id, hs.user_id, hs.staff_id, hs.title, hs.first_name,
                    hs.last_name, hs.role_name, hs.specialization, hs.department,
                    u.is_active AS user_active
               FROM healthcare_staff hs
               JOIN users u ON u.id = hs.user_id AND u.deleted_at IS NULL
              WHERE hs.deleted_at IS NULL
                AND hs.is_active = 1
                AND u.is_active = 1
              ORDER BY hs.last_name, hs.first_name'
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->prepare(
            'SELECT hs.id, hs.user_id, hs.staff_id, hs.title, hs.first_name,
                    hs.last_name, hs.role_name, hs.specialization, hs.department,
                    u.is_active AS user_active
               FROM healthcare_staff hs
               JOIN users u ON u.id = hs.user_id AND u.deleted_at IS NULL
              WHERE hs.id = :id AND hs.deleted_at IS NULL
                AND hs.is_active = 1
                AND u.is_active = 1
              LIMIT 1',
            [':id' => $id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Display name for a staff row.
     *
     * @param array<string, mixed> $staff
     */
    public static function displayName(array $staff): string
    {
        $name = trim(($staff['title'] ?? '') . ' ' . $staff['first_name'] . ' ' . $staff['last_name']);
        $role = $staff['role_name'] ?? '';
        return $role !== '' ? trim($name) . ' (' . $role . ')' : trim($name);
    }
}
