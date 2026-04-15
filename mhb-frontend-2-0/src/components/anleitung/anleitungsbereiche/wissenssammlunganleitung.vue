<template>
  <div class="anleitung-inhalt">
    <p>Die <strong>Allgemeine Wissenssammlung</strong> funktioniert genauso wie die Verwaltungsdokumente — sie zeigt jedoch Inhalte aus dem <em>Kanal Wissenssammlung</em> im Kollegiumsteam.</p>

    <h3>Dokumentenbaum</h3>
    <ul>
      <li>Inhalte sind als <strong>aufklappbarer Ordner-Baum</strong> dargestellt — auf dem Desktop werden alle Hauptordner beim Laden bereits aufgeklappt.</li>
      <li>Klick auf einen <strong>Ordner</strong> klappt ihn auf oder zu.</li>
      <li>Klick auf eine <strong>Datei</strong> öffnet sie direkt im Browser (SharePoint-Link).</li>
      <li>Das <strong>🔗-Symbol</strong> neben einem Ordner öffnet den Ordner in SharePoint.</li>
    </ul>

    <h3>Suche</h3>
    <ul>
      <li>Die Suche findet Inhalte ab <strong>2 Zeichen</strong> — gesucht wird in Originalnamen und <strong>Aliasnamen</strong>.</li>
      <li>Tastaturnavigation: <strong>↓ / ↑</strong> navigiert durch die Treffer, <strong>Enter</strong> öffnet das markierte Dokument, <strong>Escape</strong> leert die Suche.</li>
    </ul>

    <h3>Optionsmenü ( ... )</h3>
    <ul>
      <li><strong>Favorit setzen / entfernen:</strong> ☆ / ★ — Favoriten erscheinen auf dem Dashboard unter „Meine Wissensfavoriten".</li>
      <li><strong>Alias-Vorschläge:</strong> Alternativen Namen vorschlagen und für bestehende Vorschläge abstimmen (👍 / ✅ wenn bereits abgestimmt), um die Suche zu verbessern.</li>
    </ul>

    <h3>Synchronisierung (nur Administratoren)</h3>
    <ul>
      <li>Der Button <strong>„Wissenssammlung synchronisieren"</strong> lädt die aktuelle Struktur neu aus SharePoint.</li>
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
