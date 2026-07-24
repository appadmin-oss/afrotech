<?php

/**
 * SummerRegistration — the /summer intake pipeline. When the DB is down the
 * submission is written to storage/summer.log so no lead is ever lost.
 */
class SummerRegistration {
    public const FEE = 40000;

    /** Persist a registration. Returns [id, reg_code]. */
    public static function create(array $d): array {
        $code = Database::available()
            ? Ids::unique('summer_registrations', 'reg_code', fn() => Ids::summer())
            : Ids::summer();

        if (!Database::available()) {
            $dir = AFT_ROOT . '/storage';
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            @file_put_contents($dir . '/summer.log',
                json_encode(['reg_code' => $code] + $d) . "\n", FILE_APPEND | LOCK_EX);
            return ['id' => 0, 'reg_code' => $code];
        }

        $id = (int) Database::insert(
            "INSERT INTO summer_registrations
                (reg_code, student_name, student_age, guardian_name, email, phone,
                 track_slug, track_name, location_pref, experience, notes, fee_naira,
                 source, request_ip)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            [
                $code,
                $d['student_name'] ?? '',
                (int)($d['student_age'] ?? 0),
                $d['guardian_name'] ?? '',
                strtolower(trim($d['email'] ?? '')),
                $d['phone'] ?? '',
                $d['track_slug'] ?? null,
                $d['track_name'] ?? null,
                $d['location_pref'] ?? null,
                in_array($d['experience'] ?? 'none', ['none','some','confident'], true) ? $d['experience'] : 'none',
                $d['notes'] ?? null,
                self::FEE,
                $d['source'] ?? 'web',
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]
        );
        return ['id' => $id, 'reg_code' => $code];
    }

    /** Filtered, paginated list for the registrar console. */
    public static function query(array $f = []): array {
        if (!Database::available()) return [];
        $where = [];
        $args  = [];
        if (!empty($f['status']) && $f['status'] !== 'all') { $where[] = 'status = ?'; $args[] = $f['status']; }
        if (!empty($f['track'])) { $where[] = 'track_slug = ?'; $args[] = $f['track']; }
        if (!empty($f['q'])) {
            $where[] = '(student_name LIKE ? OR guardian_name LIKE ? OR email LIKE ? OR reg_code LIKE ?)';
            $like = '%' . $f['q'] . '%';
            array_push($args, $like, $like, $like, $like);
        }
        $sql = 'SELECT * FROM summer_registrations';
        if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
        $sql .= ' ORDER BY created_at DESC LIMIT 500';
        return Database::all($sql, $args);
    }

    public static function find(int $id): ?array {
        if (!Database::available()) return null;
        return Database::one("SELECT * FROM summer_registrations WHERE id = ?", [$id]);
    }

    public static function setStatus(int $id, string $status, int $adminId = 0): void {
        if (!Database::available()) return;
        if (!in_array($status, ['pending','confirmed','waitlisted','cancelled'], true)) return;
        Database::exec(
            "UPDATE summer_registrations SET status = ?, handled_by = ? WHERE id = ?",
            [$status, $adminId ?: null, $id]
        );
    }

    public static function setPayment(int $id, string $payment): void {
        if (!Database::available()) return;
        if (!in_array($payment, ['unpaid','paid','waived'], true)) return;
        Database::exec("UPDATE summer_registrations SET payment_status = ? WHERE id = ?", [$payment, $id]);
    }

    /** Dashboard counters. */
    public static function stats(): array {
        $base = ['total'=>0,'pending'=>0,'confirmed'=>0,'waitlisted'=>0,'cancelled'=>0,'paid'=>0,'revenue'=>0];
        if (!Database::available()) return $base;
        $rows = Database::all("SELECT status, COUNT(*) c FROM summer_registrations GROUP BY status");
        foreach ($rows as $r) { $base[$r['status']] = (int)$r['c']; $base['total'] += (int)$r['c']; }
        $base['paid']    = (int) Database::scalar("SELECT COUNT(*) FROM summer_registrations WHERE payment_status='paid'");
        $base['revenue'] = (int) Database::scalar("SELECT COALESCE(SUM(fee_naira),0) FROM summer_registrations WHERE payment_status='paid'");
        return $base;
    }

    public static function recent(int $limit = 6): array {
        if (!Database::available()) return [];
        return Database::all("SELECT * FROM summer_registrations ORDER BY created_at DESC LIMIT " . (int)$limit);
    }
}
