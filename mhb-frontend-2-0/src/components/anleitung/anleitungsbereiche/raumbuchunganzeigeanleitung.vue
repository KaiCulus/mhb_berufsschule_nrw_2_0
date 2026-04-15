<template>
  <div class="anleitung-inhalt">
    <p>Die <strong>Raumbuchungsübersicht</strong> zeigt die Belegung der buchbaren Räume direkt aus dem Microsoft-Kalender — nur zur Einsicht, keine Buchungen möglich.</p>

    <h3>Anzeige</h3>
    <ul>
      <li>Die Seite zeigt drei Räume: <strong>R15</strong>, <strong>R20N</strong> und die <strong>Aula</strong>.</li>
      <li>Jeder Raum hat eine eigene Karte mit allen Buchungen im gewählten Zeitraum.</li>
      <li>Pro Buchung werden <strong>Datum</strong>, <strong>Uhrzeit</strong> (von–bis), <strong>Betreff</strong> und <strong>Organisator</strong> angezeigt.</li>
      <li>Gibt es keine Buchungen im angegebenen Zeitraum für den Raum, steht dort <em>„Frei verfügbar"</em> in grün.</li>
    </ul>

    <h3>Zeitraum wählen</h3>
    <ul>
      <li>Beim Öffnen wird standardmäßig <strong>heute bis in 3 Tagen</strong> angezeigt.</li>
      <li>Start- und Enddatum lassen sich über die Datumsfelder frei wählen — die Ansicht aktualisiert sich automatisch nach jeder Änderung.</li>
      <li>Mit dem Button <strong>„🔄 Ansicht aktualisieren"</strong> kannst du die Daten manuell neu laden.</li>
      <li>Das Startdatum muss vor dem Enddatum liegen.</li>
    </ul>

    <h3>Hinweis</h3>
    <ul>
      <li>Buchungen werden über <strong>Microsoft Outlook oder Teams</strong> vorgenommen — diese Seite dient ausschließlich der Einsicht.</li>
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
