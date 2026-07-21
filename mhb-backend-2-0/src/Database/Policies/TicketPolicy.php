<?php

namespace Kai\MhbBackend20\Database\Policies;

use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;

/**
 * TicketPolicy
 *
 * Zentrale Berechtigungslogik für Tickets und Ticket-Bilder.
 *
 * Vorher war die Prüfung `hasGroup(PROCESSOR) || created_by === user['id']`
 * an vier Stellen im TicketController dupliziert. Jede Änderung am
 * Berechtigungsmodell musste dadurch an mehreren Stellen nachgezogen werden.
 *
 * Konvention:
 *   Alle Methoden erwarten das Ticket/Bild als assoziatives Array direkt
 *   aus der Datenbank und den Session-User aus AuthMiddleware::check().
 */
final class TicketPolicy
{
    public const ROLE_PROCESSOR = 'MHB_BE_MSAL_TICKETPROCESSORS';

    /**
     * Ist der aktuelle User Mitglied der Processor-Gruppe?
     */
    public static function isProcessor(): bool
    {
        return AuthMiddleware::hasGroup(self::ROLE_PROCESSOR);
    }

    /**
     * Ist der User Ersteller des Tickets?
     *
     * @param array $ticket Ticket-Datensatz (benötigt: created_by)
     * @param array $user   Session-User (benötigt: id)
     */
    public static function isCreator(array $ticket, array $user): bool
    {
        return (int) $ticket['created_by'] === (int) $user['id'];
    }

    /**
     * Darf der User Felder des Tickets bearbeiten?
     * Ersteller und Processors.
     */
    public static function canEdit(array $ticket, array $user): bool
    {
        return self::isProcessor() || self::isCreator($ticket, $user);
    }

    /**
     * Darf der User das Ticket löschen?
     * Ausschließlich der Ersteller — bewusst NICHT Processors,
     * damit ein Bearbeiter fremde Tickets nur auflösen, nicht entfernen kann.
     */
    public static function canDelete(array $ticket, array $user): bool
    {
        return self::isCreator($ticket, $user);
    }

    /**
     * Darf der User Bilder zu diesem Ticket hochladen?
     * Gleiche Regel wie canEdit().
     */
    public static function canUploadImage(array $ticket, array $user): bool
    {
        return self::canEdit($ticket, $user);
    }

    /**
     * Darf der User dieses Bild löschen?
     * Uploader, Ticket-Ersteller oder Processor.
     *
     * @param array $image Bild-Datensatz (benötigt: uploaded_by, ticket_creator)
     * @param array $user  Session-User
     */
    public static function canDeleteImage(array $image, array $user): bool
    {
        if (self::isProcessor()) {
            return true;
        }

        $isUploader      = (int) $image['uploaded_by']    === (int) $user['id'];
        $isTicketCreator = (int) $image['ticket_creator'] === (int) $user['id'];

        return $isUploader || $isTicketCreator;
    }
}