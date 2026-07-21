<?php

namespace Kai\MhbBackend20\Database\Services\Tickets;

use Kai\MhbBackend20\Database\DB;
use Kai\MhbBackend20\Common\Cipher;
use PDO;

/**
 * TicketSubscriptionService
 *
 * Verwaltet Ticket- und Raum-Abonnements und ermittelt die Empfängerliste
 * für Benachrichtigungen.
 *
 * Bewusste Trennung vom Versand: dieser Service liefert nur E-Mail-Adressen.
 * Der eigentliche Mailversand liegt im TicketNotificationService. Dadurch
 * lässt sich die Empfängerermittlung testen ohne Mails zu verschicken.
 */
class TicketSubscriptionService
{
    /**
     * Erlaubte Tabellen für toggle().
     * Verhindert SQL-Injection durch direkten Tabellennamen im Query.
     */
    private const ALLOWED_SUBSCRIPTION_TABLES = [
        'ticket_subscriptions',
        'ticket_room_subscriptions',
    ];

    private \PDO $db;
    private string $encKey;

    public function __construct()
    {
        $this->db     = DB::getInstance()->getConnection();
        $this->encKey = $_ENV['APP_ENCRYPTION_KEY'];
    }

    /**
     * Schaltet ein Ticket-Abonnement um.
     *
     * @return string 'subscribed' oder 'unsubscribed'
     */
    public function toggleTicket(int $userId, int $ticketId): string
    {
        return $this->toggle('ticket_subscriptions', [
            'user_id'   => $userId,
            'ticket_id' => $ticketId,
        ]);
    }

    /**
     * Schaltet ein Raum-Abonnement um.
     * Der Raumname wird normalisiert (trim + uppercase), damit
     * 'a201' und 'A201 ' dasselbe Abonnement treffen.
     *
     * @return array{status: string, room: string}
     */
    public function toggleRoom(int $userId, string $room): array
    {
        $normalized = strtoupper(trim($room));

        $status = $this->toggle('ticket_room_subscriptions', [
            'user_id'   => $userId,
            'room_name' => $normalized,
        ]);

        return ['status' => $status, 'room' => $normalized];
    }

    /**
     * Alle Raum-Abonnements eines Users als flache Liste von Raumnamen.
     */
    public function getRoomsForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT room_name
            FROM ticket_room_subscriptions
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Ermittelt alle Empfänger für ein Ticket-Update.
     *
     * Zusammengeführte Gruppen (per UNION, dadurch keine Duplikate):
     *   1. Direkte Ticket-Abonnenten
     *   2. Ersteller des Tickets
     *   3. Raum-Abonnenten (nur wenn location_type = 'building')
     *
     * @param int      $ticketId
     * @param int|null $excludeUserId User der die Aktion ausgelöst hat —
     *                                bekommt keine Benachrichtigung
     * @return string[] Entschlüsselte E-Mail-Adressen
     */
    public function getNotificationRecipients(int $ticketId, ?int $excludeUserId = null): array
    {
        $stmt = $this->db->prepare("
            SELECT u.id AS user_id, u.email_encrypted FROM users u
            JOIN ticket_subscriptions s ON u.id = s.user_id
            WHERE s.ticket_id = :tid1

            UNION

            SELECT u.id AS user_id, u.email_encrypted FROM users u
            JOIN tickets t ON u.id = t.created_by
            WHERE t.id = :tid2

            UNION

            SELECT u.id AS user_id, u.email_encrypted FROM users u
            JOIN ticket_room_subscriptions rs ON u.id = rs.user_id
            JOIN tickets t ON rs.room_name = t.room
            WHERE t.id = :tid3 AND t.location_type = 'building'
        ");

        $stmt->execute([
            ':tid1' => $ticketId,
            ':tid2' => $ticketId,
            ':tid3' => $ticketId,
        ]);

        $recipients = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if ($excludeUserId !== null && (int) $row['user_id'] === $excludeUserId) {
                continue;
            }
            $recipients[] = Cipher::decrypt($row['email_encrypted'], $this->encKey);
        }

        return $recipients;
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    /**
     * Generischer Toggle für Abonnement-Tabellen.
     *
     * Sicherheit: Tabellenname wird gegen eine Whitelist geprüft bevor er
     * interpoliert wird. Die Spaltennamen stammen aus den Array-Keys, die
     * ausschließlich intern von toggleTicket()/toggleRoom() gesetzt werden —
     * niemals aus Request-Daten.
     *
     * @param array $conditions Spalte → Wert Mapping für WHERE und INSERT
     * @return string 'subscribed' oder 'unsubscribed'
     * @throws \InvalidArgumentException bei unbekannter Tabelle
     */
    private function toggle(string $table, array $conditions): string
    {
        if (!in_array($table, self::ALLOWED_SUBSCRIPTION_TABLES, strict: true)) {
            throw new \InvalidArgumentException("Ungültige Tabelle: {$table}");
        }

        $whereClause = implode(' AND ', array_map(fn($k) => "{$k} = ?", array_keys($conditions)));
        $values      = array_values($conditions);

        $stmtCheck = $this->db->prepare("SELECT 1 FROM {$table} WHERE {$whereClause}");
        $stmtCheck->execute($values);

        if ($stmtCheck->fetch()) {
            $this->db->prepare("DELETE FROM {$table} WHERE {$whereClause}")->execute($values);
            return 'unsubscribed';
        }

        $cols         = implode(', ', array_keys($conditions));
        $placeholders = implode(', ', array_fill(0, count($conditions), '?'));

        $this->db->prepare("INSERT INTO {$table} ({$cols}) VALUES ({$placeholders})")
                 ->execute($values);

        return 'subscribed';
    }
}