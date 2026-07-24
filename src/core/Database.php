<?php

class Database {
    private static ?PDO $pdo = null;
    private static ?bool $available = null;

    public static function pdo(): PDO {
        if (self::$pdo) return self::$pdo;
        if (self::$available === false) throw new RuntimeException('DB previously unavailable');

        $cfg = require AFT_ROOT . '/config/database.php';
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$available = true;
        } catch (PDOException $e) {
            self::$available = false;
            error_log('[aft/db] ' . $e->getMessage());
            throw $e;
        }
        return self::$pdo;
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $bindings = []): array {
        $st = self::pdo()->prepare($sql);
        $st->execute($bindings);
        return $st->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function one(string $sql, array $bindings = []): ?array {
        $st = self::pdo()->prepare($sql);
        $st->execute($bindings);
        $row = $st->fetch();
        return $row ?: null;
    }

    public static function scalar(string $sql, array $bindings = []) {
        $st = self::pdo()->prepare($sql);
        $st->execute($bindings);
        return $st->fetchColumn();
    }

    public static function exec(string $sql, array $bindings = []): int {
        $st = self::pdo()->prepare($sql);
        $st->execute($bindings);
        return $st->rowCount();
    }

    public static function insert(string $sql, array $bindings = []): string {
        self::exec($sql, $bindings);
        return self::pdo()->lastInsertId();
    }

    /** True when a working DB is reachable. Pages soft-fall-back to seeded
     *  copy when MySQL is unconfigured locally. */
    public static function available(): bool {
        if (self::$available !== null) return self::$available;
        try { self::pdo(); return self::$available = true; }
        catch (Throwable $e) { return self::$available = false; }
    }
}
