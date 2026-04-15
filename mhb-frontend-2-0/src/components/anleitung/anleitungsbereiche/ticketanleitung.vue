<template>
  <div class="anleitung-inhalt">
    <p>Das <strong>Ticket-System</strong> dient zum Melden und Verfolgen von Problemen — von IT-Störungen bis zu Gebäudeschäden.</p>

    <h3>Neues Ticket erstellen</h3>
    <ul>
      <li>Klick auf <strong>„➕ Neues Problem melden"</strong> klappt das Formular auf.</li>
      <li>
        Pflichtfelder:
        <ul>
          <li><strong>Titel</strong> — kurze Beschreibung des Problems</li>
          <li>
            <strong>Kategorie</strong> — Hauptkategorie und Spezifizierung:
            <ul>
              <li><em>IT-Support</em>: Software, Hardware, Beamer, Microsoft 365, Sonstiges</li>
              <li><em>Netzwerk</em>: WLAN, LAN, Kein Internet, Sonstiges</li>
              <li><em>Gebäude &amp; Infrastruktur</em>: Möbelschäden, Gebäudeschaden, Heizung/Klima, Sonstiges</li>
            </ul>
            Bei Wahl von <em>„Sonstiges"</em> erscheint ein Freitextfeld zur Präzisierung.
          </li>
          <li><strong>Beschreibung</strong> — ausführliche Schilderung</li>
          <li>
            <strong>Ort</strong> — entweder Gebäude + Raum aus dem Dropdown
            (Hauptgebäude, Moritz-Fiege-Str., Technologiezentrum, Landwirtschaft)
            oder freie Ortsangabe bei „Sonstiger Ort"
          </li>
        </ul>
      </li>
      <li>
        <strong>Dringlichkeit</strong> (Standard: Mittel):
        Info / Frage · Niedrig · Mittel · Hoch · Kritisch
      </li>
      <li><strong>Bilder</strong> können optional hochgeladen werden.</li>
      <li>Der Submit-Button kann erst erfolgreich bedient werden, wenn alle Felder ausgefüllt sind.</li>
    </ul>

    <h3>Ticket-Übersicht</h3>
    <ul>
      <li>Alle aktiven Tickets werden als Karten mit Status-Farbmarkierung angezeigt.</li>
      <li>Über die <strong>Suche</strong> und den <strong>Kategorie-Filter</strong> lässt sich die Liste einschränken.</li>
      <li>Klick auf <strong>„Details ansehen"</strong> öffnet das Ticket-Modal (Die Details zum Ticket).</li>
    </ul>

    <h3>Ticket-Details</h3>
    <ul>
      <li><strong>Notizen</strong> hinzufügen — für Rückfragen oder Statusupdates im Verlauf.</li>
      <li><strong>Ticket abonnieren</strong> — du bekommst E-Mail-Benachrichtigungen bei neuen Notizen und Statusänderungen.</li>
      <li><strong>Raum folgen</strong> — direkt im Detail-Modal aktivierbar; benachrichtigt dich bei allen neuen Tickets für diesen Raum. (Alternativ kann man Räume über das Dashboard abbonieren.)</li>
      <li><strong>Ticket zurückziehen</strong> — nur für den Ersteller. "Löscht" das Ticket. (Strenggenommen wird es in das Archiv verschoben.)</li>
    </ul>

    <h3>Bearbeiter-Funktionen</h3>
    <ul>
      <li>Bearbeiter können <strong>Status</strong>, Titel, Beschreibung, Kategorie, Ort und Priorität direkt im Detail-Modal ändern.</li>
      <li>Im Tab <strong>🗄️ Archiv</strong> (nur für Bearbeiter sichtbar) werden erledigte Tickets angezeigt und können wiederhergestellt werden.</li>
      <li>Der Button <strong>„🧹 Alttickets bereinigen"</strong> löscht alle Tickets, die von einem Bearbeiter auf erledigt gesetzt wurden, die älter als 7 Tage sind.</li>
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
