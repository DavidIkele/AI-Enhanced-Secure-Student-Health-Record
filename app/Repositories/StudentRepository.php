<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Student data access. All methods use PDO prepared statements.
 */
final class StudentRepository extends BaseRepository
{
    /**
     * Find a student linked to a user account, including health record data.
     *
     * @return array<string, mixed>|null
     */
    public function findByUserId(int $userId): ?array
    {
        $stmt = $this->prepare(
            'SELECT s.id, s.user_id, s.reg_number, s.first_name, s.last_name, s.other_names,
                    s.date_of_birth, s.gender, s.email, s.phone, s.address, s.department, s.faculty,
                    s.level_of_study, s.emergency_contact_name, s.emergency_contact_phone,
                    h.blood_group, h.height_cm, h.weight_kg
               FROM students s
               LEFT JOIN health_records h ON h.student_id = s.id
              WHERE s.user_id = :uid AND s.deleted_at IS NULL
              LIMIT 1',
            [':uid' => $userId]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Find a student by primary key.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->prepare(
            'SELECT s.id, s.user_id, s.reg_number, s.first_name, s.last_name, s.other_names,
                    s.date_of_birth, s.gender, s.email, s.phone, s.address, s.department, s.faculty,
                    s.level_of_study, s.emergency_contact_name, s.emergency_contact_phone,
                    u.username, u.is_active
               FROM students s
               JOIN users u ON u.id = s.user_id AND u.deleted_at IS NULL
              WHERE s.id = :id AND s.deleted_at IS NULL
              LIMIT 1',
            [':id' => $id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Total number of non-deleted students (pagination support).
     */
    public function count(): int
    {
        $stmt = $this->prepare(
            'SELECT COUNT(*)
               FROM students s
               JOIN users u ON u.id = s.user_id AND u.deleted_at IS NULL
              WHERE s.deleted_at IS NULL'
        );
        return (int) $stmt->fetchColumn();
    }

    /**
     * Whether a non-deleted student profile already uses the registration
     * number (prevent duplicate student identities).
     */
    public function existsByRegNumber(string $regNumber): bool
    {
        $stmt = $this->prepare(
            'SELECT 1 FROM students WHERE reg_number = :reg_number AND deleted_at IS NULL LIMIT 1',
            [':reg_number' => $regNumber]
        );
        return $stmt->fetchColumn() !== false;
    }

    /**
     * Create a student profile linked to an existing user account.
     *
     * @param array<string, mixed> $data
     * @return int The new student's id.
     */
    public function create(array $data): int
    {
        $this->prepare(
            'INSERT INTO students
               (user_id, reg_number, first_name, last_name, other_names, date_of_birth,
                gender, email, phone, address, department, faculty, level_of_study,
                emergency_contact_name, emergency_contact_phone)
             VALUES
               (:user_id, :reg_number, :first_name, :last_name, :other_names, :date_of_birth,
                :gender, :email, :phone, :address, :department, :faculty, :level_of_study,
                :emergency_contact_name, :emergency_contact_phone)',
            [
                ':user_id' => $data['user_id'],
                ':reg_number' => $data['reg_number'],
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':other_names' => $data['other_names'] ?? null,
                ':date_of_birth' => $data['date_of_birth'] ?? null,
                ':gender' => $data['gender'] ?? null,
                ':email' => $data['email'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':department' => $data['department'] ?? null,
                ':faculty' => $data['faculty'] ?? null,
                ':level_of_study' => $data['level_of_study'] ?? null,
                ':emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                ':emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            ]
        );
        return (int) $this->db->lastInsertId();
    }

    /**
     * Update a student profile by the linked user id (self-profile editing;
     * ownership is derived from the session, never from the URL).
     *
     * @param int $userId
     * @param array<string, mixed> $data
     */
    public function updateByUserId(int $userId, array $data): void
    {
        $this->prepare(
            'UPDATE students SET
                first_name = :first_name,
                last_name = :last_name,
                other_names = :other_names,
                date_of_birth = :date_of_birth,
                gender = :gender,
                email = :email,
                phone = :phone,
                address = :address,
                department = :department,
                faculty = :faculty,
                level_of_study = :level_of_study,
                emergency_contact_name = :emergency_contact_name,
                emergency_contact_phone = :emergency_contact_phone
              WHERE user_id = :user_id AND deleted_at IS NULL',
            [
                ':first_name' => $data['first_name'],
                ':last_name' => $data['last_name'],
                ':other_names' => $data['other_names'] ?? null,
                ':date_of_birth' => $data['date_of_birth'] ?? null,
                ':gender' => $data['gender'] ?? null,
                ':email' => $data['email'] ?? null,
                ':phone' => $data['phone'] ?? null,
                ':address' => $data['address'] ?? null,
                ':department' => $data['department'] ?? null,
                ':faculty' => $data['faculty'] ?? null,
                ':level_of_study' => $data['level_of_study'] ?? null,
                ':emergency_contact_name' => $data['emergency_contact_name'] ?? null,
                ':emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
                ':user_id' => $userId,
            ]
        );
    }

    /**
     * Soft-delete a student profile by the linked user id. Called together
     * with the user soft-delete when a student deactivates their account.
     */
    public function softDeleteByUserId(int $userId): void
    {
        $this->prepare(
            'UPDATE students SET deleted_at = NOW() WHERE user_id = :user_id AND deleted_at IS NULL',
            [':user_id' => $userId]
        );
    }

    /**
     * Paginated list of non-deleted students (for staff/admin browse), so the
     * result set is always bounded. Ordering matches idx_students_name.
     *
     * @return array<int, array<string, mixed>>
     */
    public function paginated(int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $stmt = $this->db->prepare(
            'SELECT s.id, s.reg_number, s.first_name, s.last_name, s.department, s.faculty,
                    s.level_of_study, s.email, u.username
               FROM students s
               JOIN users u ON u.id = s.user_id AND u.deleted_at IS NULL
              WHERE s.deleted_at IS NULL
              ORDER BY s.last_name, s.first_name
              LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
