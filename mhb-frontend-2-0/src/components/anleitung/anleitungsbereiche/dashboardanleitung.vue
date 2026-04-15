<template>
  <div class="anleitung-inhalt">
    <p>Das <strong>Dashboard</strong> ist deine persönliche Übersichtsseite mit drei aufklappbaren Bereichen.</p>

    <h3>Meine Verwaltungsfavoriten &amp; Wissensfavoriten</h3>
    <ul>
      <li>Zeigt alle Dokumente aus Wissen und Verwaltung, die du als Favorit markiert hast.</li>
      <li>Ein Klick auf einen Eintrag öffnet das Dokument direkt im Browser (SharePoint-Link).</li>
      <li>Mit dem <strong>★-Button</strong> rechts neben einem Eintrag entfernst du ihn aus deinen Favoriten.</li>
      <li>Favoriten setzt du auf den jeweiligen Seiten (Verwaltungsdokumente / Wissenssammlung) über das Optionsmenü.</li>
    </ul>

    <h3>Meine Tickets</h3>
    <ul>
      <li>Zeigt alle Tickets, die du selbst erstellt oder die du abonniert hast.</li>
      <li>Über die <strong>Suche</strong> und den <strong>Kategorie-Filter</strong> kannst du deine Tickets filtern.</li>
      <li>
        Klick auf <strong>„Details ansehen"</strong> öffnet das Ticket — dort kannst du:
        <ul>
          <li>Den aktuellen Status einsehen.</li>
          <li>Das Ticket <strong>zurückziehen</strong>.</li>
          <li><strong>Notizen</strong> hinzufügen und den Verlauf lesen.</li>
        </ul>
      </li>
    </ul>

    <h3>Raum-Abonnements</h3>
    <ul>
      <li>Im Bereich „Meine Tickets" findest du die Verwaltung deiner <strong>Raum-Abonnements</strong>.</li>
      <li>Trägst du einen Raum ein (z. B. <em>R18</em>), wirst du per E-Mail benachrichtigt, sobald jemand ein neues Ticket für diesen Raum erstellt.</li>
      <li>Abonnements lassen sich jederzeit über das <strong>×</strong> neben dem Raum-Tag entfernen.</li>
    </ul>
  </div>
</template>

<style scoped>
.anleitung-inhalt h3 {
  margin: 1rem 0 0.4rem;
  font-size: 1rem;
}

.anleitung-inhalt ul {
  margin: 0 0 0.5rem 1.25rem;
  padding: 0;
}

.anleitung-inhalt li {
  margin-bottom: 0.25rem;
}
</style>
