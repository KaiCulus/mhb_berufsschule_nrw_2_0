<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use Kai\MhbBackend20\Database\DB;
use PDO;

/**
 * MaterialController
 *
 * CRUD-Operationen für Materialien und deren Kontaktpersonen.
 *
 * Berechtigungsmodell:
 *   - Erstellen:  Jeder authentifizierte User
 *   - Aktualisieren / Löschen: Nur der Ersteller des Materials
 *   - Suche:  Jeder authentifizierte User
 *
 * Transaktionen:
 *   Create und Update verwenden Transaktionen, da beide Operationen
 *   Einträge in zwei Tabellen schreiben (materials + material_contacts).
 */
class MaterialController extends BaseController
{
    /**
     * Erlaubte Sortierfelder für die Suche.
     * Nur diese Werte dürfen in ORDER BY verwendet werden — verhindert SQL-Injection
     * durch direkte String-Interpolation des Spaltennamens.
     */
    private const ALLOWED_SORT_FIELDS = [
        'name'     => 'm.name',
        'location' => 'm.location',
        'contacts' => 'all_contacts',
    ];

    private \PDO $db;

    public function __construct()
    {
        $this->db = DB::getInstance()->getConnection();
    }

    /**
     * POST api/materials
     *
     * Legt ein neues Material mit optionalen Kontaktpersonen an.
     *
     * Erwarteter Request-Body:
     *   {
     *     "name":        "Beamer XY",
     *     "location":    "Raum 201",
     *     "contacts":    ["Hr. Müller", "IT-Team"],   // optional
     *     "description": "...",                        // optional
     *     "quantity":    "2 Stück"                     // optional
     *   }
     */
    public function create(): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest([
            'name'     => 'string',
            'location' => 'string',
            'contacts' => 'array',
        ]);

        $this->db->beginTransaction();
        try {
            // 1. Material-Datensatz anlegen
            $stmt = $this->db->prepare("
                INSERT INTO materials (name, description, location, quantity, created_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['location'],
                $data['quantity']    ?? null,
                $user['id'],
            ]);

            $materialId = (int) $this->db->lastInsertId();

            // 2. Kontaktpersonen einfügen (leere Namen werden übersprungen)
            $this->insertContacts($materialId, $data['contacts']);

            $this->db->commit();
            $this->jsonResponse(['status' => 'success', 'id' => $materialId], 201);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('MaterialController::create() failed: ' . $e->getMessage());
            $this->errorResponse('Material konnte nicht gespeichert werden.', 500);
        }
    }

    /**
     * POST api/materials/update/{id}
     *
     * Aktualisiert ein Material — nur durch den ursprünglichen Ersteller.
     * Kontakte werden komplett neu gesetzt (delete + insert).
     *
     * @param int $id Material-ID aus der URL
     */
    public function update(int $id): void
    {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest([
            'name'     => 'string',
            'location' => 'string',
            'contacts' => 'array',
        ]);

        // Eigentumscheck: nur der Ersteller darf aktualisieren
        $this->assertOwnership($id, $user['id']);

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE materials
                SET name = ?, description = ?, location = ?, quantity = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['description'] ?? null,
                $data['location'],
                $data['quantity']    ?? null,
                $id,
            ]);

            // Kontakte neu setzen: alte löschen, neue einfügen
            $this->db->prepare("DELETE FROM material_contacts WHERE material_id = ?")->execute([$id]);
            $this->insertContacts($id, $data['contacts']);

            $this->db->commit();
            $this->jsonResponse(['status' => 'updated']);

        } catch (\Throwable $e) {
            $this->db->rollBack();
            error_log('MaterialController::update() failed: ' . $e->getMessage());
            $this->errorResponse('Material konnte nicht aktualisiert werden.', 500);
        }
    }

    /**
     * POST api/materials/delete/{id}
     *
     * Löscht ein Material — nur durch den ursprünglichen Ersteller.
     * Die WHERE-Bedingung auf created_by verhindert unbefugtes Löschen.
     *
     * @param int $id Material-ID aus der URL
     */
    public function delete(int $id): void
    {
        $user = AuthMiddleware::check();

        $stmt = $this->db->prepare("DELETE FROM materials WHERE id = ? AND created_by = ?");
        $stmt->execute([$id, $user['id']]);

        if ($stmt->rowCount() === 0) {
            $this->errorResponse('Material nicht gefunden oder keine Berechtigung.', 403);
        }

        $this->jsonResponse(['status' => 'deleted']);
    }

    /**
     * GET api/materials/search?q=...&sortBy=name&sortDir=ASC
     *
     * Volltextsuche über Name, Standort und Beschreibung.
     * Gibt alle Treffer mit aggregierten Kontaktpersonen zurück.
     *
     * Query-Parameter:
     *   q       — Suchbegriff (Standard: leer → alle)
     *   sortBy  — Spalte: 'name' | 'location' | 'contacts' (Standard: 'name')
     *   sortDir — Richtung: 'ASC' | 'DESC' (Standard: 'ASC')
     */
    public function search(): void
    {
        AuthMiddleware::check();

        $query  = $_GET['q']       ?? '';
        $sortBy = $_GET['sortBy']  ?? 'name';

        // Sortierfeld per Whitelist absichern — verhindert SQL-Injection durch
        // direkte String-Interpolation des Spaltennamens in ORDER BY
        $orderColumn = self::ALLOWED_SORT_FIELDS[$sortBy] ?? self::ALLOWED_SORT_FIELDS['name'];

        // Sortierrichtung explizit auf erlaubte Werte beschränken
        $sortDir = strtoupper($_GET['sortDir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        // ORDER BY verwendet $orderColumn und $sortDir direkt per Interpolation —
        // beide Werte kommen ausschließlich aus den Whitelists oben, nicht aus dem Request
        $stmt = $this->db->prepare("
            SELECT m.*, GROUP_CONCAT(mc.contact_name SEPARATOR ', ') AS all_contacts
            FROM materials m
            LEFT JOIN material_contacts mc ON m.id = mc.material_id
            WHERE m.name LIKE ? OR m.location LIKE ? OR m.description LIKE ?
            GROUP BY m.id
            ORDER BY {$orderColumn} {$sortDir}
        ");

        $searchTerm = "%{$query}%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);

        $this->jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Fügt Kontaktpersonen für ein Material ein.
     * Leere und rein-whitespace Strings werden übersprungen.
     *
     * @param int   $materialId Ziel-Material
     * @param array $contacts   Liste von Kontaktnamen
     */
    private function insertContacts(int $materialId, array $contacts): void
    {
        if (empty($contacts)) {
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO material_contacts (material_id, contact_name) VALUES (?, ?)");

        foreach ($contacts as $name) {
            $name = trim((string) $name);
            if ($name !== '') {
                $stmt->execute([$materialId, $name]);
            }
        }
    }

    /**
     * Prüft ob der gegebene User der Ersteller eines Materials ist.
     * Bricht den Request mit 403 ab, falls nicht.
     *
     * @param int $materialId  Material-ID
     * @param int $userId      Session-User-ID
     */
    private function assertOwnership(int $materialId, int $userId): void
    {
        $stmt = $this->db->prepare("SELECT created_by FROM materials WHERE id = ?");
        $stmt->execute([$materialId]);
        $material = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$material) {
            $this->errorResponse('Material nicht gefunden.', 404);
        }

        if ((int) $material['created_by'] !== $userId) {
            $this->errorResponse('Keine Berechtigung für dieses Material.', 403);
        }
    }
}