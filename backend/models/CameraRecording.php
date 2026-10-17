<?php

declare(strict_types=1);

final class CameraRecording
{
    public static function find(string $publicId, bool $lock = false): ?array
    {
        $statement = db()->prepare('SELECT * FROM camera_recordings WHERE public_id = :id LIMIT 1'
            . ($lock ? ' FOR UPDATE' : ''));
        $statement->execute(['id' => $publicId]);
        return $statement->fetch() ?: null;
    }

    public static function recent(int $parkingId): array
    {
        $statement = db()->prepare("SELECT * FROM camera_recordings WHERE parking_id = :parking AND state = 'ready'"
            . ' ORDER BY started_at DESC, id DESC LIMIT 20');
        $statement->execute(['parking' => $parkingId]);
        return $statement->fetchAll();
    }

    public static function latestReadyDay(int $parkingId): ?string
    {
        $statement = db()->prepare(
            "SELECT DATE_FORMAT(DATE_SUB(MAX(ended_at), INTERVAL 1 MICROSECOND), '%Y-%m-%d')"
            . " FROM camera_recordings WHERE parking_id = :parking AND state = 'ready'"
        );
        $statement->execute(['parking' => $parkingId]);
        $date = $statement->fetchColumn();

        return is_string($date) && $date !== '' ? $date : null;
    }

    /**
     * @param list<array{start:string,end:string}> $intervals
     * @return array{total:int,intervals:list<int>}
     */
    public static function countReadyIntervals(
        int $parkingId,
        string $rangeStart,
        string $rangeEnd,
        array $intervals
    ): array {
        $columns = ['COUNT(*) AS total'];
        $parameters = ['parking' => $parkingId, 'range_start' => $rangeStart, 'range_end' => $rangeEnd];
        foreach ($intervals as $index => $interval) {
            // Aliases and parameter names come only from the bounded integer index.
            $columns[] = 'SUM(CASE WHEN started_at < :interval_end_' . $index
                . ' AND ended_at > :interval_start_' . $index
                . ' THEN 1 ELSE 0 END) AS interval_' . $index;
            $parameters['interval_start_' . $index] = $interval['start'];
            $parameters['interval_end_' . $index] = $interval['end'];
        }

        $statement = db()->prepare(
            'SELECT ' . implode(', ', $columns)
            . " FROM camera_recordings WHERE parking_id = :parking AND state = 'ready'"
            . ' AND started_at < :range_end AND ended_at > :range_start'
        );
        $statement->execute($parameters);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        $counts = [];
        foreach ($intervals as $index => $_interval) {
            $counts[] = (int) ($row['interval_' . $index] ?? 0);
        }

        return ['total' => (int) ($row['total'] ?? 0), 'intervals' => $counts];
    }

    public static function readyInRange(
        int $parkingId,
        string $rangeStart,
        string $rangeEnd,
        int $limit,
        int $offset
    ): array {
        $statement = db()->prepare(
            "SELECT * FROM camera_recordings WHERE parking_id = :parking AND state = 'ready'"
            . ' AND started_at < :range_end AND ended_at > :range_start'
            . ' ORDER BY started_at ASC, id ASC LIMIT :row_limit OFFSET :row_offset'
        );
        $statement->bindValue(':parking', $parkingId, PDO::PARAM_INT);
        $statement->bindValue(':range_start', $rangeStart);
        $statement->bindValue(':range_end', $rangeEnd);
        $statement->bindValue(':row_limit', $limit, PDO::PARAM_INT);
        $statement->bindValue(':row_offset', $offset, PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll();
    }

    public static function expiredPending(int $creator): array
    {
        $statement = db()->prepare("SELECT * FROM camera_recordings WHERE created_by = :creator AND state = 'pending'"
            . ' AND created_at < DATE_SUB(NOW(3), INTERVAL 1 HOUR) FOR UPDATE');
        $statement->execute(['creator' => $creator]);
        return $statement->fetchAll();
    }

    public static function pendingCount(int $creator): int
    {
        $statement = db()->prepare("SELECT COUNT(*) FROM camera_recordings WHERE created_by = :creator AND state = 'pending'");
        $statement->execute(['creator' => $creator]);
        return (int) $statement->fetchColumn();
    }

    public static function begin(string $publicId, int $parkingId, int $creator, string $format): void
    {
        $statement = db()->prepare('INSERT INTO camera_recordings'
            . ' (public_id, parking_id, created_by, format, started_at, created_at, updated_at)'
            . ' VALUES (:public_id, :parking_id, :creator, :format, NOW(3), NOW(3), NOW(3))');
        $statement->execute(['public_id' => $publicId, 'parking_id' => $parkingId, 'creator' => $creator, 'format' => $format]);
    }

    public static function ready(string $publicId, int $duration, int $bytes, string $sha256): void
    {
        $statement = db()->prepare("UPDATE camera_recordings SET state = 'ready', duration_ms = :duration,"
            . ' byte_size = :bytes, sha256 = :sha256,'
            . ' ended_at = DATE_ADD(started_at, INTERVAL :microseconds MICROSECOND), updated_at = NOW(3)'
            . " WHERE public_id = :id AND state = 'pending'");
        $statement->execute(['duration' => $duration, 'bytes' => $bytes, 'sha256' => $sha256,
            'microseconds' => $duration * 1000, 'id' => $publicId]);
    }

    public static function deletePending(string $publicId): void
    {
        $statement = db()->prepare("DELETE FROM camera_recordings WHERE public_id = :id AND state = 'pending'");
        $statement->execute(['id' => $publicId]);
    }

    public static function totalBytes(): int
    {
        return (int) db()->query("SELECT COALESCE(SUM(byte_size), 0) FROM camera_recordings WHERE state = 'ready'")->fetchColumn();
    }
}
