<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Exceptions\AppointmentConflictException;
use PDO;

/**
 * Appointment data access (PROMPT 6).
 *
 * Double-booking prevention uses a transaction + row-lock strategy:
 *  1. Begin a transaction.
 *  2. Lock the healthcare_staff row (SELECT ... FOR UPDATE) so concurrent
 *     booking attempts for the SAME staff member serialise behind the lock.
 *  3. Re-check overlap for active slots (pending/approved).
 *  4. Insert / update, then commit.
 *
 * Only 'pending' and 'approved' appointments occupy a clinic slot; cancelled,
 * rejected, completed and no_show appointments free the slot for re-booking.
 */
final class AppointmentsRepository extends BaseRepository
{
    /** Clinic working hours used to generate availability slots. */
    public const OPEN_HOUR = 9;
    public const CLOSE_HOUR = 16;
    public const DEFAULT_DURATION = 30;

    private const OCCUPYING_STATUSES = ['pending', 'approved'];

    /**
     * Appointment with student and staff names attached.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->prepare(
            'SELECT a.id, a.student_id, a.healthcare_staff_id, a.scheduled_at,
                    a.duration_minutes, a.reason, a.status, a.cancellation_reason,
                    a.admin_notes, a.requested_by, a.handled_by, a.created_at, a.updated_at,
                    s.first_name AS student_first, s.last_name AS student_last, s.reg_number,
                    hs.title AS staff_title, hs.first_name AS staff_first,
                    hs.last_name AS staff_last, hs.role_name
               FROM appointments a
               JOIN students s ON s.id = a.student_id
               JOIN healthcare_staff hs ON hs.id = a.healthcare_staff_id
              WHERE a.id = :id
              LIMIT 1',
            [':id' => $id]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }

    /**
     * Appointments for a single student (their own appointments only).
     *
     * @return array<int, array<string, mixed>>
     */
    public function forStudent(int $studentId): array
    {
        $stmt = $this->prepare(
            'SELECT a.id, a.student_id, a.healthcare_staff_id, a.scheduled_at,
                    a.duration_minutes, a.reason, a.status, a.cancellation_reason,
                    a.admin_notes, a.created_at, a.updated_at,
                    hs.title AS staff_title, hs.first_name AS staff_first,
                    hs.last_name AS staff_last, hs.role_name
               FROM appointments a
               JOIN healthcare_staff hs ON hs.id = a.healthcare_staff_id
              WHERE a.student_id = :sid
              ORDER BY a.scheduled_at DESC, a.id DESC',
            [':sid' => $studentId]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Upcoming appointments for a student (pending or approved and scheduled
     * in the future). Used by the profile area so a student sees "what's
     * next" without having to open the full appointment list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function upcomingForStudent(int $studentId, int $limit = 5): array
    {
        $limit = max(1, min(50, $limit));
        $stmt = $this->prepare(
            'SELECT a.id, a.scheduled_at, a.duration_minutes, a.reason, a.status,
                    hs.title AS staff_title, hs.first_name AS staff_first,
                    hs.last_name AS staff_last, hs.role_name
               FROM appointments a
               JOIN healthcare_staff hs ON hs.id = a.healthcare_staff_id
              WHERE a.student_id = :sid
                AND a.status IN (:st1, :st2)
                AND a.scheduled_at >= NOW()
              ORDER BY a.scheduled_at ASC, a.id ASC
              LIMIT ' . $limit,
            [
                ':sid' => $studentId,
                ':st1' => self::OCCUPYING_STATUSES[0],
                ':st2' => self::OCCUPYING_STATUSES[1],
            ]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Total number of appointments for the staff/administrator list,
     * optionally filtered by status (pagination support).
     */
    public function countForManagement(?string $status = null): int
    {
        $sql = 'SELECT COUNT(*)
                  FROM appointments a
                  JOIN students s ON s.id = a.student_id
                  JOIN healthcare_staff hs ON hs.id = a.healthcare_staff_id';
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE a.status = :status';
            $params[':status'] = $status;
        }
        return (int) $this->prepare($sql, $params)->fetchColumn();
    }

    /**
     * Appointments for staff/administrators, optionally filtered by status,
     * paginated so the result set is always bounded (default 50/page).
     *
     * @return array<int, array<string, mixed>>
     */
    public function allForManagement(?string $status = null, int $limit = 50, int $offset = 0): array
    {
        $limit = max(1, min(200, $limit));
        $offset = max(0, $offset);

        $sql = 'SELECT a.id, a.student_id, a.healthcare_staff_id, a.scheduled_at,
                       a.duration_minutes, a.reason, a.status, a.cancellation_reason,
                       a.admin_notes, a.created_at, a.updated_at,
                       s.first_name AS student_first, s.last_name AS student_last,
                       s.reg_number,
                       hs.title AS staff_title, hs.first_name AS staff_first,
                       hs.last_name AS staff_last, hs.role_name
                  FROM appointments a
                  JOIN students s ON s.id = a.student_id
                  JOIN healthcare_staff hs ON hs.id = a.healthcare_staff_id';
        $params = [];
        if ($status !== null && $status !== '') {
            $sql .= ' WHERE a.status = :status';
            $params[':status'] = $status;
        }
        $sql .= ' ORDER BY a.scheduled_at DESC, a.id DESC LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Appointments in a time range for a staff member (calendar support).
     *
     * @return array<int, array<string, mixed>>
     */
    public function scheduledForStaffBetween(int $staffId, string $from, string $to): array
    {
        $stmt = $this->prepare(
            'SELECT a.id, a.scheduled_at, a.duration_minutes, a.status,
                    s.first_name AS student_first, s.last_name AS student_last
               FROM appointments a
               JOIN students s ON s.id = a.student_id
              WHERE a.healthcare_staff_id = :sid
                AND a.scheduled_at >= :from
                AND a.scheduled_at < :to
              ORDER BY a.scheduled_at ASC',
            [':sid' => $staffId, ':from' => $from, ':to' => $to]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Whether a booking at $scheduledAt for $durationMinutes would overlap an
     * occupying appointment of the same staff member.
     */
    public function hasOverlap(int $staffId, string $scheduledAt, int $durationMinutes, ?int $excludeId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM appointments
                 WHERE healthcare_staff_id = :sid
                   AND status IN (:st1, :st2)
                   AND scheduled_at < :end_time
                   AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > :start_time';
        $params = [
            ':sid' => $staffId,
            ':st1' => self::OCCUPYING_STATUSES[0],
            ':st2' => self::OCCUPYING_STATUSES[1],
            ':end_time' => date('Y-m-d H:i:s', strtotime($scheduledAt) + ($durationMinutes * 60)),
            ':start_time' => $scheduledAt,
        ];
        if ($excludeId !== null) {
            $sql .= ' AND id <> :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $count = (int) $this->prepare($sql, $params)->fetchColumn();
        return $count > 0;
    }

    /**
     * Request/book an appointment with conflict detection under a row lock.
     *
     * @return int new appointment id
     */
    public function create(
        int $studentId,
        int $staffId,
        string $scheduledAt,
        int $durationMinutes,
        string $reason,
        int $requestedBy
    ): int {
        $this->db->beginTransaction();
        try {
            $this->lockStaff($staffId);

            if ($this->hasOverlap($staffId, $scheduledAt, $durationMinutes)) {
                $this->db->rollBack();
                throw new AppointmentConflictException(
                    'That time overlaps an existing appointment for this staff member. Please choose another time.'
                );
            }

            $stmt = $this->db->prepare(
                'INSERT INTO appointments
                   (student_id, healthcare_staff_id, scheduled_at, duration_minutes,
                    reason, status, requested_by)
                 VALUES (:sid, :staff, :scheduled_at, :duration, :reason, :status, :requested_by)'
            );
            $stmt->bindValue(':sid', $studentId, PDO::PARAM_INT);
            $stmt->bindValue(':staff', $staffId, PDO::PARAM_INT);
            $stmt->bindValue(':scheduled_at', $scheduledAt, PDO::PARAM_STR);
            $stmt->bindValue(':duration', $durationMinutes, PDO::PARAM_INT);
            $stmt->bindValue(':reason', $reason, PDO::PARAM_STR);
            $stmt->bindValue(':status', 'pending', PDO::PARAM_STR);
            $stmt->bindValue(':requested_by', $requestedBy, PDO::PARAM_INT);
            $stmt->execute();
            $id = (int) $this->db->lastInsertId();

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Change appointment status (approve / reject / cancel / complete).
     */
    public function setStatus(
        int $id,
        string $status,
        ?int $handledBy,
        ?string $cancellationReason = null,
        ?string $adminNotes = null
    ): void {
        $stmt = $this->db->prepare(
            'UPDATE appointments
                SET status = :status,
                    handled_by = :handled_by,
                    cancellation_reason = :cancellation_reason,
                    admin_notes = :admin_notes
              WHERE id = :id'
        );
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        $stmt->bindValue(':handled_by', $handledBy, PDO::PARAM_INT);
        $stmt->bindValue(':cancellation_reason', $cancellationReason !== '' && $cancellationReason !== null ? $cancellationReason : null, PDO::PARAM_STR);
        $stmt->bindValue(':admin_notes', $adminNotes !== '' && $adminNotes !== null ? $adminNotes : null, PDO::PARAM_STR);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Reschedule an appointment, re-verifying conflict detection under a lock.
     */
    public function reschedule(int $id, string $scheduledAt, int $durationMinutes, int $handledBy): void
    {
        $this->db->beginTransaction();
        try {
            $appointment = $this->findById($id);
            if ($appointment === null) {
                $this->db->rollBack();
                throw new AppointmentConflictException('The appointment could not be found.');
            }

            $this->lockStaff((int) $appointment['healthcare_staff_id']);

            if ($this->hasOverlap((int) $appointment['healthcare_staff_id'], $scheduledAt, $durationMinutes, $id)) {
                $this->db->rollBack();
                throw new AppointmentConflictException(
                    'The new time overlaps an existing appointment for this staff member. Please choose another time.'
                );
            }

            $stmt = $this->db->prepare(
                'UPDATE appointments
                    SET scheduled_at = :scheduled_at,
                        duration_minutes = :duration,
                        handled_by = :handled_by
                  WHERE id = :id'
            );
            $stmt->bindValue(':scheduled_at', $scheduledAt, PDO::PARAM_STR);
            $stmt->bindValue(':duration', $durationMinutes, PDO::PARAM_INT);
            $stmt->bindValue(':handled_by', $handledBy, PDO::PARAM_INT);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Generate clinic availability slots for a staff member on a date.
     *
     * Occupying appointments that overlap the clinic window (OPEN_HOUR to
     * CLOSE_HOUR) are fetched in ONE query; each slot is then marked
     * unavailable with a PHP-side overlap check. This is O(1) queries for the
     * whole day instead of one per slot.
     *
     * @return array<int, array{time:string, available:bool}>
     */
    public function availabilityForStaff(int $staffId, string $date, int $durationMinutes = self::DEFAULT_DURATION): array
    {
        $slots = [];
        $cursor = strtotime($date . ' ' . sprintf('%02d:00:00', self::OPEN_HOUR));
        $close = strtotime($date . ' ' . sprintf('%02d:00:00', self::CLOSE_HOUR));

        if (($cursor + (30 * 60)) > $close) {
            return $slots;
        }

        $windowStart = date('Y-m-d H:i:s', $cursor);
        $windowEnd = date('Y-m-d H:i:s', $close);
        $occupying = $this->occupyingInWindow($staffId, $windowStart, $windowEnd);

        while ($cursor + ($durationMinutes * 60) <= $close) {
            $time = date('Y-m-d H:i:s', $cursor);
            $startTs = $cursor;
            $endTs = $cursor + ($durationMinutes * 60);
            $slots[] = [
                'time' => $time,
                'available' => !self::overlapsAny($occupying, $startTs, $endTs),
            ];
            $cursor += ($durationMinutes * 60);
        }
        return $slots;
    }

    /**
     * Free-slot counts for every day of a month for a staff member. No student
     * identity is ever fetched or returned - only whether/how many slots remain
     * free, so students can browse availability without seeing who booked.
     *
     * One query per month (occupying appointments fetched once, overlap checks
     * done in PHP) instead of one query per day.
     *
     * @return array<string, array{free:int, total:int}> date ('Y-m-d') => counts
     */
    public function availabilityForStaffMonth(int $staffId, string $yearMonth, int $durationMinutes = self::DEFAULT_DURATION): array
    {
        if (!preg_match('#^\d{4}-\d{2}$#', $yearMonth)) {
            return [];
        }
        $durationMinutes = max(1, (int) $durationMinutes);
        [$year, $mon] = array_map('intval', explode('-', $yearMonth));

        $startTs = mktime(0, 0, 0, $mon, 1, $year);
        $endTs = mktime(0, 0, 0, $mon + 1, 1, $year);

        $byDay = [];
        foreach ($this->occupyingInWindow(
            $staffId,
            date('Y-m-d H:i:s', $startTs),
            date('Y-m-d H:i:s', $endTs)
        ) as $row) {
            $byDay[substr((string) $row['scheduled_at'], 0, 10)][] = $row;
        }

        $result = [];
        for ($ts = $startTs; $ts < $endTs; $ts += 86400) {
            $open = mktime(self::OPEN_HOUR, 0, 0, (int) date('n', $ts), (int) date('j', $ts), (int) date('Y', $ts));
            $close = mktime(self::CLOSE_HOUR, 0, 0, (int) date('n', $ts), (int) date('j', $ts), (int) date('Y', $ts));

            if ($open + ($durationMinutes * 60) > $close) {
                continue;
            }

            $date = date('Y-m-d', $ts);
            $dayRows = $byDay[$date] ?? [];
            $free = 0;
            $total = 0;
            for ($cursor = $open; $cursor + ($durationMinutes * 60) <= $close; $cursor += ($durationMinutes * 60)) {
                $total++;
                if (!self::overlapsAny($dayRows, $cursor, $cursor + ($durationMinutes * 60))) {
                    $free++;
                }
            }

            $result[$date] = ['free' => $free, 'total' => $total];
        }
        return $result;
    }

    /**
     * IN list for the occupying statuses (used across queries).
     *
     * @return array<int, string>
     */
    private static function occupyingStatuses(): array
    {
        return self::OCCUPYING_STATUSES;
    }

    /**
     * All occupying appointments that overlap a window for a staff member.
     *
     * @return array<int, array<string, mixed>>
     */
    private function occupyingInWindow(int $staffId, string $windowStart, string $windowEnd): array
    {
        $stmt = $this->prepare(
            'SELECT scheduled_at, duration_minutes
               FROM appointments
              WHERE healthcare_staff_id = :sid
                AND status IN (:st1, :st2)
                AND scheduled_at < :wind_end
                AND DATE_ADD(scheduled_at, INTERVAL duration_minutes MINUTE) > :wind_start',
            [
                ':sid' => $staffId,
                ':st1' => self::OCCUPYING_STATUSES[0],
                ':st2' => self::OCCUPYING_STATUSES[1],
                ':wind_start' => $windowStart,
                ':wind_end' => $windowEnd,
            ]
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * True when any fetched occupying appointment overlaps the [startTs, endTs)
     * slot. Pure PHP so the per-slot check runs O(window-exclusive) time
     * without further database round-trips.
     *
     * @param array<int, array<string, mixed>> $occupying scheduled_at + duration_minutes rows
     */
    private static function overlapsAny(array $occupying, int $startTs, int $endTs): bool
    {
        foreach ($occupying as $row) {
            $oStart = strtotime((string) $row['scheduled_at']);
            $oEnd = $oStart + ((int) $row['duration_minutes'] * 60);
            if ($oStart < $endTs && $oEnd > $startTs) {
                return true;
            }
        }
        return false;
    }

    /**
     * Serialise concurrent booking attempts for the same staff member.
     */
    private function lockStaff(int $staffId): void
    {
        $stmt = $this->db->prepare('SELECT id FROM healthcare_staff WHERE id = :sid FOR UPDATE');
        $stmt->bindValue(':sid', $staffId, PDO::PARAM_INT);
        $stmt->execute();
        if ($stmt->fetchColumn() === false) {
            throw new AppointmentConflictException('The selected healthcare staff member is not available.');
        }
    }
}
