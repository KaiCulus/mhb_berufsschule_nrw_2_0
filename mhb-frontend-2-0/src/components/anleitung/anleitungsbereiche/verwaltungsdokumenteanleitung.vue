<template>
  <div class="anleitung-inhalt">
    <p>Unter <strong>Verwaltungsdokumente (MHB)</strong> findest du alle Dokumente, die aus dem SharePoint synchronisiert wurden.</p>

    <h3>Dokumentenbaum</h3>
    <ul>
      <li>Die Dokumente sind als <strong>aufklappbarer Ordner-Baum</strong> dargestellt — auf dem Desktop werden alle Hauptordner beim Laden bereits aufgeklappt.</li>
      <li>Ordner auf der obersten Ebene sind farblich kodiert; alle Unterordner und Dateien eines Astes tragen dieselbe Farbe.</li>
      <li>Klick auf einen <strong>Ordner</strong> klappt ihn auf oder zu.</li>
      <li>Klick auf eine <strong>Datei</strong> öffnet sie direkt im Browser (SharePoint-Link).</li>
      <li>Das <strong>🔗-Symbol</strong> neben einem Ordner öffnet den Ordner selbst in SharePoint.</li>
      <li>Der <strong>Tooltip</strong> am 📁- oder 📄-Symbol zeigt den vollständigen Ordnerpfad.</li>
    </ul>

    <h3>Suche</h3>
    <ul>
      <li>Die Suche findet Dokumente ab <strong>2 Zeichen</strong> — gesucht wird in Originalnamen und <strong>Aliasnamen</strong>.</li>
      <li>Gefundene Aliase werden als 🏷️-Tags unter dem Ergebnis angezeigt.</li>
      <li>Tastaturnavigation: <strong>↓ / ↑</strong> durch die Liste, <strong>Enter</strong> öffnet das markierte Dokument, <strong>Escape</strong> leert die Suche.</li>
      <li>Über das <strong>...-Menü</strong> in der Ergebnisliste öffnest du die Optionen direkt aus der Suche heraus.</li>
    </ul>

    <h3>Optionsmenü ( ... )</h3>
    <ul>
      <li>Das <strong>...-Symbol</strong> rechts an jedem Ordner oder Dokument öffnet das Optionsmenü.</li>
      <li><strong>Favorit setzen / entfernen:</strong> ☆ / ★ — Favoriten erscheinen auf dem Dashboard.</li>
      <li>
        <strong>Alias-Vorschläge:</strong> Jeder kann einen alternativen Namen für ein Dokument vorschlagen.
        Andere User können für Vorschläge abstimmen (👍 / ✅ wenn bereits abgestimmt).
        Alias-Vorschläge verbessern die Auffindbarkeit über die Suche.
      </li>
    </ul>

    <h3>Synchronisierung (nur Administratoren)</h3>
    <ul>
      <li>Der Button <strong>„Verwaltungsdokumente synchronisieren"</strong> lädt die aktuelle Ordnerstruktur neu aus SharePoint.</li>
      <li>Nur für Admins sichtbar und nutzbar.</li>
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
