<?php
/**
 * Benachrichtigung über eine neue Notiz an einem Ticket.
 *
 * Erwartete Variablen (roh, werden hier escaped):
 *   string $authorName
 *   string $comment
 */
?>
Neue Notiz von <b><?= htmlspecialchars($authorName) ?></b>:<br>
<i><?= nl2br(htmlspecialchars($comment)) ?></i>