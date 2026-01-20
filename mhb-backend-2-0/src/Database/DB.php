<?php
    namespace Kai\MhbBackend20\Database;

    use PDO;
    use PDOException;

    class DB {
        private static ?PDO $instance = null;

        public static function getConnection(): PDO {
            if (self::$instance === null) {
                try {
                    $dsn = "mysql:host=" . $_ENV['DB_HOST'] . ";dbname=" . $_ENV['DB_NAME'] . ";charset=utf8mb4";
                    self::$instance = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                    ]);
                } catch (PDOException $e) {
                    die("Datenbankverbindung fehlgeschlagen: " . $e->getMessage());
                }
            }
            return self::$instance;
        }
    }