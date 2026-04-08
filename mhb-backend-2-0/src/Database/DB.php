<?php

namespace Kai\MhbBackend20\Database;

use PDO;
use PDOException;

/**
 * DB
 *
 * Singleton-Wrapper um eine PDO-Datenbankverbindung.
 *
 * Stellt sicher dass während eines Request-Lebenszyklus nur eine einzige
 * Datenbankverbindung geöffnet wird — alle Controller teilen sich dieselbe Instanz.
 *
 * Verwendung:
 *   $db = DB::getInstance()->getConnection();
 *   $stmt = $db->prepare("SELECT ...");
 *
 * Konfiguration via .env:
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS
 *   (werden in index.php::loadEnvironmentConfiguration() als Pflichtfelder validiert)
 */
class DB
{
    private static ?DB $instance = null;
    private PDO $connection;

    /**
     * Privater Konstruktor — verhindert direktes "new DB()".
     * Verbindungsaufbau nur über getInstance().
     *
     * @throws \RuntimeException Wenn die Datenbankverbindung fehlschlägt
     */
    private function __construct()
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $_ENV['DB_HOST'],
                $_ENV['DB_NAME']
            );

            $this->connection = new PDO($dsn, $_ENV['DB_USER'], $_ENV['DB_PASS'], [
                // Exceptions statt silent errors
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                // Ergebnisse standardmäßig als assoziative Arrays
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Native Prepared Statements erzwingen (verhindert Emulation, sicherer)
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);

        } catch (PDOException $e) {
            // Verbindungsdetails (Passwort, Host) nicht nach außen geben
            error_log('DB: Verbindung fehlgeschlagen: ' . $e->getMessage());
            throw new \RuntimeException('Datenbankverbindung fehlgeschlagen.');
        }
    }

    /**
     * Gibt die einzige DB-Instanz zurück, legt sie beim ersten Aufruf an.
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Gibt die PDO-Verbindung zurück.
     */
    public function getConnection(): PDO
    {
        return $this->connection;
    }

    /**
     * Verhindert das Klonen der Singleton-Instanz.
     */
    private function __clone() {}
}