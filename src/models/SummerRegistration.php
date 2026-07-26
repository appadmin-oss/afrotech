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

        // The fee travels with the record because builder add-ons can move it
        // per registration; Setting::fee() is only the base.
        $fee     = isset($d['fee_naira']) ? max(0, (int)$d['fee_naira']) : Setting::fee();
        $answers = isset($d['answers']) && is_array($d['answers'])
            ? json_encode($d['answers'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;

        $cols = [
            'reg_code'      => $code,
            'student_name'  => $d['student_name'] ?? '',
            'student_age'   => (int)($d['student_age'] ?? 0),
            'guardian_name' => $d['guardian_name'] ?? '',
            'email'         => strtolower(trim($d['email'] ?? '')),
            'phone'         => $d['phone'] ?? '',
            'track_slug'    => $d['track_slug'] ?? null,
            'track_name'    => $d['track_name'] ?? null,
            'location_pref' => $d['location_pref'] ?? null,
            'experience'    => in_array($d['experience'] ?? 'none', ['none','some','confident'], true) ? $d['experience'] : 'none',
            'notes'         => $d['notes'] ?? null,
            'fee_naira'     => $fee,
            'source'        => $d['source'] ?? 'web',
            'request_ip'    => $_SERVER['REMOTE_ADDR'] ?? null,
        ];
        // Written only where the form-builder migration has been applied, so a
        // deploy that ships code before SQL still takes registrations.
        if (self::hasBuilderColumns()) {
            $cols['answers_json'] = $answers;
            $cols['form_version'] = (int)($d['form_version'] ?? 1);
            $cols['addons_naira'] = max(0, (int)($d['addons_naira'] ?? 0));
        }

        $names = array_keys($cols);
        $id = (int) Database::insert(
            'INSERT INTO summer_registrations (' . implode(', ', $names) . ') VALUES ('
            . implode(', ', array_fill(0, count($names), '?')) . ')',
            array_values($cols)
        );
        return ['id' => $id, 'reg_code' => $code];
    }

    /** Decoded builder answers for a row. */
    public static function answers(array $row): array {
        $raw = $row['answers_json'] ?? null;
        if (!$raw) return [];
        $d = json_decode((string)$raw, true);
        return is_array($d) ? $d : [];
    }

    /**
     * Has the form-builder migration run? Cached per request — the columns
     * either exist for the whole request or they don't.
     */
    public static function hasBuilderColumns(): bool {
        static $has = null;
        if ($has !== null) return $has;
        if (!Database::available()) return $has = false;
        try {
            $rows = Database::all("SHOW COLUMNS FROM summer_registrations LIKE 'answers_json'");
            return $has = (bool)$rows;
        } catch (Throwable $e) { return $has = false; }
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

    public static function findByCode(string $code): ?array {
        $code = strtoupper(trim($code));
        if (!Database::available()) {
            // Offline/demo soft-fallback so the checkout flow is previewable
            // before the DB is configured. Never reached in production (DB up).
            return [
                'id' => 0, 'reg_code' => $code, 'student_name' => 'Your child',
                'student_age' => 10, 'guardian_name' => '', 'email' => '',
                'phone' => '', 'track_slug' => null, 'track_name' => 'Summer School',
                'location_pref' => null, 'experience' => 'none', 'notes' => null,
                'fee_naira' => Setting::fee(), 'payment_status' => 'unpaid', 'status' => 'pending',
            ];
        }
        return Database::one("SELECT * FROM summer_registrations WHERE reg_code = ?", [$code]);
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
