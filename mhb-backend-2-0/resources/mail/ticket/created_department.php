<?php
/**
 * Info-Mail an die zuständige Fachabteilung bei einem neuen Ticket.
 *
 * Erwartete Variablen (alle roh, werden hier escaped):
 *   int    $ticketId
 *   string $title
 *   string $userName
 *   string $userEmail
 *   string $category
 *   string $subCategory
 *   string $priority
 *   string $locationType   'building' oder anderes
 *   string $building
 *   string $room
 *   string $description
 */

if ($locationType === 'building') {
    $location = 'Gebäude: ' . htmlspecialchars($building)
              . ', Raum: '  . htmlspecialchars($room);
} else {
    $location = 'Sonstiger Ort: ' . htmlspecialchars($room);
}
?>
<h2 style="color: #0e64a6;">Neues Ticket erstellt: #<?= (int) $ticketId ?></h2>
<p><strong>Titel:</strong> <?= htmlspecialchars($title) ?></p>
<hr>
<p><strong>Von:</strong> <?= htmlspecialchars($userName) ?> (<?= htmlspecialchars($userEmail) ?>)</p>
<p><strong>Kategorie:</strong> <?= htmlspecialchars($category) ?> (<?= htmlspecialchars($subCategory) ?>)</p>
<p><strong>Priorität:</strong> <?= htmlspecialchars(strtoupper($priority)) ?></p>
<p><strong>Ort:</strong> <?= $location ?></p>
<hr>
<p><strong>Beschreibung:</strong><br><?= nl2br(htmlspecialchars($description)) ?></p>