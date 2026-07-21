<?php

namespace Kai\MhbBackend20\Database\Services\Tickets;

use Kai\MhbBackend20\Database\DB;
use PDO;

/**
 * TicketImageService
 *
 * Kapselt Upload, Validierung, Auslieferung und Löschung von Ticket-Bildern
 * inklusive der Dateisystem-Operationen.
 *
 * Fehlerbehandlung:
 *   Der Service wirft ImageUploadException mit passendem HTTP-Statuscode.
 *   Der Controller fängt sie und übersetzt sie in eine Fehlerantwort —
 *   so bleibt der Service frei von HTTP-Kenntnissen.
 */
class TicketImageService
{
    /** Erlaubte MIME-Typen für Bild-Uploads. */
    public const ALLOWED_IMAGE_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** Maximale Dateigröße pro Bild (5 MB). */
    public const MAX_IMAGE_SIZE = 5 * 1024 * 1024;

    /** Maximale Anzahl an Bildern pro Ticket. */
    public const MAX_IMAGES_PER_TICKET = 5;

    /** MIME-Typ → Dateiendung. */
    private const EXTENSION_MAP = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
    ];

    private \PDO $db;
    private string $uploadDir;
    private string $apiBase;

    public function __construct()
    {
        $this->db        = DB::getInstance()->getConnection();
        $this->uploadDir = rtrim($_ENV['TICKET_IMAGE_PATH'] ?? '/var/www/uploads/ticket_images', '/') . '/';
        $this->apiBase   = rtrim($_ENV['APP_URL'] ?? '', '/');
    }

    // =========================================================================
    // Lesen
    // =========================================================================

    /**
     * Bilder eines Tickets als Metadaten inkl. serve-URL.
     *
     * Der Dateiname wird bewusst nicht mitgegeben, damit das Frontend keine
     * Direktzugriffe auf storage/ konstruieren kann.
     */
    public function getForTicket(int $ticketId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, original_name, uploaded_at
            FROM ticket_images
            WHERE ticket_id = ?
            ORDER BY uploaded_at ASC
        ");
        $stmt->execute([$ticketId]);
        $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($images as &$image) {
            $image['url'] = $this->apiBase . '/api/tickets/images/serve/' . $image['id'];
        }
        unset($image);

        return $images;
    }

    /**
     * Bild-Datensatz inkl. Ticket-Ersteller — für Löschberechtigungen.
     *
     * @return array|null Enthält u.a. uploaded_by, ticket_creator, filename
     */
    public function findWithTicketOwner(int $imageId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT ti.*, t.created_by AS ticket_creator
            FROM ticket_images ti
            JOIN tickets t ON ti.ticket_id = t.id
            WHERE ti.id = ?
        ");
        $stmt->execute([$imageId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Minimaldaten zum Ausliefern einer Bilddatei.
     *
     * @return array|null Enthält filename, original_name, ticket_id
     */
    public function findForServing(int $imageId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT filename, original_name, ticket_id
            FROM ticket_images
            WHERE id = ?
        ");
        $stmt->execute([$imageId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Aktuelle Anzahl Bilder an einem Ticket.
     */
    public function countForTicket(int $ticketId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM ticket_images WHERE ticket_id = ?");
        $stmt->execute([$ticketId]);

        return (int) $stmt->fetchColumn();
    }

    // =========================================================================
    // Upload
    // =========================================================================

    /**
     * Validiert und speichert hochgeladene Bilder.
     *
     * Ablauf pro Datei: Upload-Fehler prüfen → Größe prüfen → MIME per finfo
     * ermitteln (Client-Header wird ignoriert) → unter zufälligem Namen ablegen
     * → DB-Eintrag schreiben.
     *
     * @param array $filesInput $_FILES['images'] — single oder multiple
     * @return string[] Gespeicherte Dateinamen
     * @throws ImageUploadException
     */
    public function store(int $ticketId, array $filesInput, int $userId): array
    {
        $files        = $this->normalizeFileArray($filesInput);
        $currentCount = $this->countForTicket($ticketId);

        if (($currentCount + count($files)) > self::MAX_IMAGES_PER_TICKET) {
            $remaining = self::MAX_IMAGES_PER_TICKET - $currentCount;
            throw new ImageUploadException(
                'Maximal ' . self::MAX_IMAGES_PER_TICKET . " Bilder pro Ticket erlaubt. Noch {$remaining} möglich.",
                400
            );
        }

        $this->ensureUploadDir();

        $stored = [];

        foreach ($files as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new ImageUploadException('Fehler beim Upload einer Datei.', 400);
            }

            if ($file['size'] > self::MAX_IMAGE_SIZE) {
                throw new ImageUploadException('Eine Datei überschreitet die maximale Größe von 5 MB.', 400);
            }

            // MIME-Typ über finfo prüfen — nicht dem Client-Header vertrauen
            $finfo    = new \finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!in_array($mimeType, self::ALLOWED_IMAGE_TYPES, strict: true)) {
                throw new ImageUploadException('Ungültiger Dateityp. Erlaubt: JPEG, PNG, WEBP.', 400);
            }

            // Zufälliger Dateiname — Originalname landet nur in der DB
            $filename = bin2hex(random_bytes(16)) . '.' . self::EXTENSION_MAP[$mimeType];
            $destPath = $this->uploadDir . $filename;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                throw new ImageUploadException('Datei konnte nicht gespeichert werden.', 500);
            }

            $this->db->prepare("
                INSERT INTO ticket_images (ticket_id, filename, original_name, uploaded_by)
                VALUES (?, ?, ?, ?)
            ")->execute([$ticketId, $filename, $file['name'], $userId]);

            $stored[] = $filename;
        }

        return $stored;
    }

    // =========================================================================
    // Löschen
    // =========================================================================

    /**
     * Entfernt Datei und DB-Eintrag.
     * Die Berechtigungsprüfung liegt beim Aufrufer (TicketPolicy).
     */
    public function delete(int $imageId, string $filename): void
    {
        $filePath = $this->uploadDir . basename($filename); // basename() gegen Path-Traversal

        if (is_file($filePath)) {
            unlink($filePath);
        }

        $this->db->prepare("DELETE FROM ticket_images WHERE id = ?")->execute([$imageId]);
    }

    // =========================================================================
    // Ausliefern
    // =========================================================================

    /**
     * Ermittelt den absoluten Pfad einer Bilddatei und prüft Lesbarkeit
     * sowie den tatsächlichen MIME-Typ.
     *
     * @return array{path: string, mime: string, size: int, mtime: int}
     * @throws ImageUploadException wenn die Datei fehlt oder der Typ unerlaubt ist
     */
    public function resolveFile(string $filename): array
    {
        $path = $this->uploadDir . basename($filename); // basename() gegen Path-Traversal

        if (!is_file($path) || !is_readable($path)) {
            throw new ImageUploadException('Datei nicht verfügbar.', 404);
        }

        // MIME aus der tatsächlichen Datei lesen — nicht aus der DB
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($path);

        if (!in_array($mime, self::ALLOWED_IMAGE_TYPES, strict: true)) {
            throw new ImageUploadException('Ungültiger Dateityp.', 415);
        }

        return [
            'path'  => $path,
            'mime'  => $mime,
            'size'  => filesize($path),
            'mtime' => filemtime($path),
        ];
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function ensureUploadDir(): void
    {
        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0755, true) && !is_dir($this->uploadDir)) {
            throw new ImageUploadException('Upload-Verzeichnis konnte nicht angelegt werden.', 500);
        }
    }

    /**
     * Vereinheitlicht $_FILES für Einzel- und Mehrfach-Uploads.
     *
     * PHP strukturiert $_FILES['images'] bei multiple=true transponiert
     * (Array pro Feld statt Array pro Datei). Diese Methode dreht das um.
     *
     * @return array Liste von Datei-Arrays mit name/type/tmp_name/error/size
     */
    private function normalizeFileArray(array $filesInput): array
    {
        if (!is_array($filesInput['name'])) {
            return [$filesInput];
        }

        $normalized = [];

        foreach ($filesInput['name'] as $i => $name) {
            $normalized[] = [
                'name'     => $name,
                'type'     => $filesInput['type'][$i],
                'tmp_name' => $filesInput['tmp_name'][$i],
                'error'    => $filesInput['error'][$i],
                'size'     => $filesInput['size'][$i],
            ];
        }

        return $normalized;
    }
}