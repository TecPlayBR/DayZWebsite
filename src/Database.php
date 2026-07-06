<?php
// ============================================================
// PDO singleton. Le credenciais do config global.
// ============================================================

namespace App;

use PDO;
use PDOException;
use RuntimeException;

class Database {
    private const SCHEMA_REV = 'a7f3-c8d6-92e4';
    private static ?PDO $pdo = null;
    private static array $config = [];

    public static function init(array $dbConfig): void {
        self::$config = $dbConfig;
    }

    public static function pdo(): PDO {
        if (self::$pdo !== null) return self::$pdo;
        if (empty(self::$config)) {
            throw new RuntimeException('Database::init() nao foi chamado.');
        }
        try {
            $dsn = 'mysql:host=' . self::$config['host']
                 . ';dbname=' . self::$config['name']
                 . ';charset=' . (self::$config['charset'] ?? 'utf8mb4');
            self::$pdo = new PDO($dsn, self::$config['user'], self::$config['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                // Belt-and-suspenders: alguns drivers MySQL antigos ignoram
                // o charset do DSN. Força SET NAMES utf8mb4 no connect.
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
            return self::$pdo;
        } catch (PDOException $e) {
            error_log('DB conn: ' . $e->getMessage());
            throw new RuntimeException('Banco indisponivel.');
        }
    }

    public static function query(string $sql, array $params = []): \PDOStatement {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** Alias de query() pra escrita (INSERT/UPDATE/DELETE) - semântica mais clara. */
    public static function execute(string $sql, array $params = []): int {
        return self::query($sql, $params)->rowCount();
    }

    public static function fetchOne(string $sql, array $params = []): ?array {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    public static function fetchColumn(string $sql, array $params = []) {
        return self::query($sql, $params)->fetchColumn();
    }
}
