<?php

namespace Kai\MhbBackend20\Database\Services\Tickets;

use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Common\Cipher;
use PDO;

/**
 * TicketService
 *
 * Kapselt sämtliche Datenbankzugriffe rund um Tickets.
 * Enthält keine HTTP-Logik — gibt Arrays zurück oder wirft Exceptions.
 * Die Controller übersetzen das Ergebnis in JSON-Antworten.
 *
 * Entschlüsselung von Namen/E-Mails passiert hier, damit die Controller
 * den Encryption-Key nicht mehr kennen müssen.
 */
class TicketService
{
    /**
     * Felder die über updateField() aktualisiert werden dürfen.
     * Whitelist verhindert SQL-Injection durch Spaltenname-Interpolation.
     */
    public const ALLOWED_UPDATE_FIELDS = [
        'title', 'description', 'category', 'sub_category',
        'priority', 'location_type', 'building', 'room', 'status',
    ];

    private \PDO $db;
    private string $encKey;

    public function __construct()
    {
        $this->db     = DB::getInstance()->getConnection();
        $this->encKey = $_ENV['APP_ENCRYPTION_KEY'];
    }

    // =========================================================================
    // Lesen
    // =========================================================================

    /**
     * Alle aktiven Tickets (Processor-Übersicht), neueste zuerst.
     */
    public function getAll(): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, u.display_name_encrypted AS creator_name_enc
            FROM tickets t
            JOIN users u ON t.created_by = u.id
            WHERE t.status != 'archived'
            ORDER BY t.created_at DESC
        ");
        $stmt->execute();

        return $this->decryptResults(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            'creator_name_enc',
            'creator_name'
        );
    }

    /**
     * Alle für einen User relevanten Tickets:
     *   - selbst erstellte
     *   - direkt abonnierte
     *   - Tickets in abonnierten Räumen
     *
     * Sicherheit: Die User-ID kommt aus der Session, niemals aus der URL.
     */
    public function getRelevantForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT t.*, u.display_name_encrypted AS creator_name_enc
            FROM tickets t
            JOIN users u ON t.created_by = u.id
            LEFT JOIN ticket_subscriptions s
                   ON t.id = s.ticket_id AND s.user_id = ?
            LEFT JOIN ticket_room_subscriptions rs
                   ON t.room = rs.room_name AND rs.user_id = ?
            WHERE t.status != 'archived'
              AND (
                t.created_by = ?
                OR s.user_id IS NOT NULL
                OR (rs.user_id IS NOT NULL AND t.location_type = 'building')
              )
            ORDER BY t.updated_at DESC
        ");

        // Positionale Parameter (?) statt named (:uid) — PDO erlaubt named params
        // nicht mehrfach im selben Statement bei manchen MySQL-Treibern
        $stmt->execute([$userId, $userId, $userId]);

        return $this->decryptResults(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            'creator_name_enc',
            'creator_name'
        );
    }

    /**
     * Einzelnes Ticket inkl. entschlüsselter Ersteller-/Bearbeiter-Namen.
     *
     * @return array|null null wenn das Ticket nicht existiert
     */
    public function findDetail(int $ticketId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.*,
                   u.display_name_encrypted  AS creator_name_enc,
                   lu.display_name_encrypted AS last_editor_name_enc
            FROM tickets t
            JOIN users u  ON t.created_by = u.id
            LEFT JOIN users lu ON t.last_edited_by = lu.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            return null;
        }

        $ticket['creator_name'] = Cipher::decrypt($ticket['creator_name_enc'], $this->encKey);
        unset($ticket['creator_name_enc']);

        if (!empty($ticket['last_editor_name_enc'])) {
            $ticket['last_editor_name'] = Cipher::decrypt($ticket['last_editor_name_enc'], $this->encKey);
        }
        unset($ticket['last_editor_name_enc']);

        return $ticket;
    }

    /**
     * Kommentare eines Tickets mit entschlüsselten Autoren-Namen.
     */
    public function getComments(int $ticketId): array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, u.display_name_encrypted AS author_name_enc
            FROM ticket_comments c
            JOIN users u ON c.user_id = u.id
            WHERE c.ticket_id = ?
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$ticketId]);

        return $this->decryptResults(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            'author_name_enc',
            'author_name'
        );
    }

    /**
     * Minimaler Ticket-Datensatz für Berechtigungsprüfungen.
     *
     * @return array|null Enthält id, title, created_by, assigned_group_mail
     */
    public function findBasic(int $ticketId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, title, created_by, assigned_group_mail, status
            FROM tickets
            WHERE id = ?
        ");
        $stmt->execute([$ticketId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Ticket inkl. entschlüsselter Ersteller-E-Mail — für Benachrichtigungen.
     *
     * @return array|null Enthält created_by, title, creator_email (Klartext)
     */
    public function findWithCreatorEmail(int $ticketId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT t.created_by, t.title, u.email_encrypted AS creator_email_enc
            FROM tickets t
            JOIN users u ON t.created_by = u.id
            WHERE t.id = ?
        ");
        $stmt->execute([$ticketId]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            return null;
        }

        $ticket['creator_email'] = Cipher::decrypt($ticket['creator_email_enc'], $this->encKey);
        unset($ticket['creator_email_enc']);

        return $ticket;
    }

    // =========================================================================
    // Schreiben
    // =========================================================================

    /**
     * Legt ein neues Ticket an.
     *
     * @param array  $data       Validierte Request-Daten (bereits sanitized)
     * @param int    $userId     Ersteller (Session-ID)
     * @param string $targetMail Zuständige Fachabteilung
     * @return int Neu erzeugte Ticket-ID
     */
    public function create(array $data, int $userId, string $targetMail): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tickets
                (title, description, category, sub_category, priority,
                 location_type, building, room, created_by, assigned_group_mail)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['title'],
            $data['description']  ?? '',
            $data['category'],
            $data['sub_category'] ?? null,
            $data['priority'],
            $data['location_type'],
            $data['building']     ?? null,
            $data['room']         ?? null,
            $userId,
            $targetMail,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Aktualisiert ein einzelnes Feld.
     *
     * WICHTIG: $field MUSS vorher gegen ALLOWED_UPDATE_FIELDS geprüft sein.
     * Spaltennamen können in PDO nicht gebunden werden, daher Interpolation.
     *
     * @throws \InvalidArgumentException wenn $field nicht in der Whitelist steht
     */
    public function updateField(int $ticketId, string $field, string $value, int $editorId): void
    {
        if (!in_array($field, self::ALLOWED_UPDATE_FIELDS, strict: true)) {
            throw new \InvalidArgumentException("Ungültiges Feld: {$field}");
        }

        $this->db->prepare("
            UPDATE tickets
            SET {$field} = ?, last_edited_by = ?, updated_at = NOW()
            WHERE id = ?
        ")->execute([$value, $editorId, $ticketId]);
    }

    /**
     * Setzt den Status eines Tickets und aktualisiert updated_at.
     */
    public function updateStatus(int $ticketId, string $status): void
    {
        $this->db->prepare("UPDATE tickets SET status = ?, updated_at = NOW() WHERE id = ?")
                 ->execute([$status, $ticketId]);
    }

    /**
     * Fügt einen Kommentar hinzu.
     */
    public function addComment(int $ticketId, int $userId, string $comment): void
    {
        $this->db->prepare("
            INSERT INTO ticket_comments (ticket_id, user_id, comment)
            VALUES (?, ?, ?)
        ")->execute([$ticketId, $userId, $comment]);
    }

    // =========================================================================
    // Archiv
    // =========================================================================

    /**
     * Alle archivierten Tickets, zuletzt geändertes zuerst.
     */
    public function getArchived(): array
    {
        $stmt = $this->db->prepare("
            SELECT t.*, u.display_name_encrypted AS creator_name_enc
            FROM tickets t
            JOIN users u ON t.created_by = u.id
            WHERE t.status = 'archived'
            ORDER BY t.updated_at DESC
        ");
        $stmt->execute();

        return $this->decryptResults(
            $stmt->fetchAll(PDO::FETCH_ASSOC),
            'creator_name_enc',
            'creator_name'
        );
    }

    /**
     * Prüft ob ein Ticket existiert und archiviert ist.
     */
    public function isArchived(int $ticketId): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM tickets WHERE id = ? AND status = 'archived'");
        $stmt->execute([$ticketId]);

        return (bool) $stmt->fetch();
    }

    /**
     * Archiviert alle Tickets die seit mehr als $days Tagen als
     * 'resolved_by_staff' markiert sind.
     *
     * @return int Anzahl archivierter Tickets
     */
    public function archiveResolvedOlderThan(int $days = 7): int
    {
        $stmt = $this->db->prepare("
            UPDATE tickets
            SET status = 'archived', updated_at = NOW()
            WHERE status = 'resolved_by_staff'
              AND updated_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        $stmt->execute([$days]);

        return $stmt->rowCount();
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Entschlüsselt ein Feld in allen Zeilen eines Ergebnis-Arrays.
     *
     * Liest $sourceKey, entschlüsselt und legt den Klartext unter $targetKey ab.
     * Das verschlüsselte Feld wird anschließend entfernt.
     */
    private function decryptResults(array $results, string $sourceKey, string $targetKey): array
    {
        foreach ($results as &$item) {
            if (!empty($item[$sourceKey])) {
                $item[$targetKey] = Cipher::decrypt($item[$sourceKey], $this->encKey);
            }
            unset($item[$sourceKey]);
        }
        unset($item); // Referenz aus foreach aufräumen

        return $results;
    }
}