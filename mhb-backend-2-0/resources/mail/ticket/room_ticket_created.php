<?php
/**
 * Benachrichtigung an Raum-Abonnenten bei einem neuen Ticket im Raum.
 *
 * Erwartete Variablen (roh, werden hier escaped):
 *   string $room
 */
?>
Ein neues Ticket wurde für den Raum <b><?= htmlspecialchars(strtoupper(trim($room))) ?></b> erstellt.