<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

/**
 * Per-user notification / UI preferences.
 *
 * Missing rows are treated as "default on" by the application (see get()).
 * Preferences are read on every notification dispatch path, so the read
 * method MUST be cheap.
 */
final class UserPreferencesRepository extends BaseRepository
{
    /**
     * Default values used when a user has no row yet (or for fill-on-write).
     * Centralised so the defaults are auditable in one place.
     */
    public const DEFAULTS = [
        'notify_appointment_changes' => 1,
        'notify_health_insights' => 1,
        'notify_health_alerts' => 1,
        'notify_system_announcements' => 1,
        'appointment_reminder_opt_in' => 1,
    ];

    /**
     * All preference keys known to the application. The values come from
     * HTML checkboxes; "1" = on, "0" = off. Unknown keys are ignored.
     *
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /**
     * Whether a specific preference is enabled for a user. Treats missing
     * rows as "on" (default).
     */
    public function isEnabled(int $userId, string $key): bool
    {
        $row = $this->fetchRow($userId);
        if ($row === null) {
            return (bool) (self::DEFAULTS[$key] ?? 1);
        }
        return (int) ($row[$key] ?? (self::DEFAULTS[$key] ?? 1)) === 1;
    }

    /**
     * Read a user's preferences, falling back to defaults when no row exists.
     *
     * @return array<string, int>
     */
    public function get(int $userId): array
    {
        $row = $this->fetchRow($userId);
        $out = [];
        foreach (self::DEFAULTS as $key => $default) {
            $out[$key] = $row === null
                ? (int) $default
                : (int) ($row[$key] ?? $default);
        }
        return $out;
    }

    /**
     * Persist a user's preferences. Unknown keys are ignored; checkbox
     * semantics mean every key is always written (so flipping one off
     * cannot leave a stale "on" lying around).
     *
     * @param array<string, int|string> $values
     */
    public function save(int $userId, array $values): void
    {
        $normalised = self::DEFAULTS;
        foreach (self::DEFAULTS as $key => $_) {
            $raw = $values[$key] ?? 0;
            $normalised[$key] = in_array((string) $raw, ['1', 'on', 'true', 1, true], true) ? 1 : 0;
        }

        $sql = 'INSERT INTO user_preferences
                   (user_id, notify_appointment_changes, notify_health_insights,
                    notify_health_alerts, notify_system_announcements,
                    appointment_reminder_opt_in)
               VALUES
                   (:user_id, :a, :b, :c, :d, :e)
               ON DUPLICATE KEY UPDATE
                   notify_appointment_changes = VALUES(notify_appointment_changes),
                   notify_health_insights     = VALUES(notify_health_insights),
                   notify_health_alerts       = VALUES(notify_health_alerts),
                   notify_system_announcements = VALUES(notify_system_announcements),
                   appointment_reminder_opt_in = VALUES(appointment_reminder_opt_in)';

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':a', $normalised['notify_appointment_changes'], PDO::PARAM_INT);
        $stmt->bindValue(':b', $normalised['notify_health_insights'], PDO::PARAM_INT);
        $stmt->bindValue(':c', $normalised['notify_health_alerts'], PDO::PARAM_INT);
        $stmt->bindValue(':d', $normalised['notify_system_announcements'], PDO::PARAM_INT);
        $stmt->bindValue(':e', $normalised['appointment_reminder_opt_in'], PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchRow(int $userId): ?array
    {
        $stmt = $this->prepare(
            'SELECT notify_appointment_changes, notify_health_insights,
                    notify_health_alerts, notify_system_announcements,
                    appointment_reminder_opt_in
               FROM user_preferences
              WHERE user_id = :uid
              LIMIT 1',
            [':uid' => $userId]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
}
