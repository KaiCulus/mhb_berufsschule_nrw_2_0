<?php
namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;
use PDO;

class MaterialController extends BaseController {
    private $db;

    public function __construct() {
        $this->db = DB::getInstance()->getConnection();
    }

    public function create() {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest([
            'name'      => 'string',
            'location'  => 'string',
            'contacts'  => 'array' // Array aus Strings, z.B. ["Hr. Müller", "IT-Team"]
        ]);

        $this->db->beginTransaction();
        try {
            // 1. Material einfügen
            $stmt = $this->db->prepare("
                INSERT INTO materials (name, description, location, quantity, created_by) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'], 
                $data['description'] ?? null, 
                $data['location'], 
                $data['quantity'] ?? null,
                $user['id']
            ]);
            
            $materialId = $this->db->lastInsertId();

            // 2. Kontakte als einfache Strings einfügen
            if (!empty($data['contacts'])) {
                $stmtContact = $this->db->prepare("INSERT INTO material_contacts (material_id, contact_name) VALUES (?, ?)");
                foreach ($data['contacts'] as $name) {
                    if (!empty(trim($name))) {
                        $stmtContact->execute([$materialId, trim($name)]);
                    }
                }
            }

            $this->db->commit();
            $this->jsonResponse(['status' => 'success', 'id' => $materialId]);
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->errorResponse("Fehler: " . $e->getMessage());
        }
    }

    public function update(int $id) {
        $user = AuthMiddleware::check();
        $data = $this->validateRequest([
            'name' => 'string',
            'location' => 'string',
            'contacts' => 'array'
        ]);

        // Berechtigung prüfen
        $stmtCheck = $this->db->prepare("SELECT created_by FROM materials WHERE id = ?");
        $stmtCheck->execute([$id]);
        $material = $stmtCheck->fetch();

        if (!$material || (int)$material['created_by'] !== (int)$user['id']) {
            $this->errorResponse("Nicht autorisiert oder nicht gefunden", 403);
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE materials SET name = ?, description = ?, location = ?, quantity = ? WHERE id = ?");
            $stmt->execute([$data['name'], $data['description'], $data['location'], $data['quantity'], $id]);

            // Kontakte neu setzen (einfachste Variante: löschen und neu anlegen)
            $this->db->prepare("DELETE FROM material_contacts WHERE material_id = ?")->execute([$id]);
            $stmtContact = $this->db->prepare("INSERT INTO material_contacts (material_id, contact_name) VALUES (?, ?)");
            foreach ($data['contacts'] as $name) {
                if (!empty(trim($name))) $stmtContact->execute([$id, trim($name)]);
            }

            $this->db->commit();
            $this->jsonResponse(['status' => 'updated']);
        } catch (\Exception $e) {
            $this->db->rollBack();
            $this->errorResponse($e->getMessage());
        }
    }

    public function delete(int $id) {
        $user = AuthMiddleware::check();
        $stmt = $this->db->prepare("DELETE FROM materials WHERE id = ? AND created_by = ?");
        $stmt->execute([$id, $user['id']]);
        
        if ($stmt->rowCount() === 0) $this->errorResponse("Nicht gefunden oder keine Berechtigung", 403);
        $this->jsonResponse(['status' => 'deleted']);
    }

    public function search() {
        AuthMiddleware::check();
        $query = $_GET['q'] ?? '';
        $sortBy = $_GET['sortBy'] ?? 'name';
        $sortDir = ($_GET['sortDir'] ?? 'ASC') === 'DESC' ? 'DESC' : 'ASC';

        // White-listing der Sortierfelder um SQL Injection zu vermeiden
        $allowedSort = ['name' => 'm.name', 'location' => 'm.location', 'contacts' => 'all_contacts'];
        $orderClause = $allowedSort[$sortBy] ?? 'm.name';

        $stmt = $this->db->prepare("
            SELECT m.*, GROUP_CONCAT(mc.contact_name SEPARATOR ', ') as all_contacts
            FROM materials m
            LEFT JOIN material_contacts mc ON m.id = mc.material_id
            WHERE m.name LIKE ? OR m.location LIKE ? OR m.description LIKE ?
            GROUP BY m.id
            ORDER BY $orderClause $sortDir
        ");
        
        $searchTerm = "%$query%";
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm]);
        $this->jsonResponse($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

}