<?php
/**
 * Bestätigungsmail an den Ersteller eines neuen Tickets.
 *
 * Erwartete Variablen:
 *   int    $ticketId
 *   string $title      Roh (wird hier escaped)
 *   string $userName   Roh (wird hier escaped)
 */
?>
<p>Hallo <?= htmlspecialchars($userName) ?>,</p>
<p>
    dein Ticket '<b><?= htmlspecialchars($title) ?></b>' wurde erfolgreich erstellt.
</p>
<p>Du erhältst eine Benachrichtigung, sobald es bearbeitet wird.</p>