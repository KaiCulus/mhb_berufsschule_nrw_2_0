<template>
  <div class="anleitung-inhalt">
    <p>Der <strong>Ressourcen-Finder</strong> ist eine gemeinsame Inventarliste: Jeder kann eintragen, was er hat und wo es liegt — und danach suchen.</p>
    <p>Die Seite hat zwei Tabs: <strong>Suchen</strong> und <strong>Eintragen</strong>.</p>

    <h3>Tab: Suchen</h3>
    <ul>
      <li>Das Suchfeld findet ab <strong>2 Zeichen</strong> Einträge nach Name, Ort oder Ansprechpartner.</li>
      <li>Ergebnisse erscheinen als Cards mit Name, Menge, Ort, Ansprechpartner und Beschreibung.</li>
      <li>Darunter folgt das <strong>Gesamtinventar</strong> als Tabelle mit allen Einträgen.</li>
      <li>Die Tabellenspalten <em>Gegenstand</em>, <em>Ort</em> und <em>Ansprechpartner</em> lassen sich durch Klick auf den Spaltenkopf auf- oder absteigend <strong>sortieren</strong> (🔼 / 🔽).</li>
      <li>
        <strong>Bearbeiten und Löschen</strong> (✏️ / 🗑️) sind nur für den Ersteller eines Eintrags sichtbar.
        Beim Löschen erscheint eine Sicherheitsabfrage.
      </li>
    </ul>

    <h3>Tab: Eintragen</h3>
    <ul>
      <li>Pflichtfelder: <strong>Gegenstand</strong> (Name) und <strong>Ort</strong>.</li>
      <li>Optional: Mengenangabe (Freitext, z. B. <em>3 Stück</em>), Beschreibung und <strong>Ansprechpartner</strong>.</li>
      <li>Über <strong>„+ Kontakt hinzufügen"</strong> können mehrere Ansprechpartner eingetragen werden.</li>
      <li>Speichern mit dem Button oder <strong>Enter</strong> — nach dem Speichern leuchtet das Formular kurz auf und der Fokus springt zurück ins Namensfeld für schnelle Folgeeinträge.</li>
    </ul>

    <h3>Eintrag bearbeiten</h3>
    <ul>
      <li>Klick auf ✏️ in der Such- oder Inventartabelle öffnet das Bearbeitungsformular als Modal.</li>
      <li>Alle Felder können geändert und mit <strong>„Änderungen speichern"</strong> übernommen werden.</li>
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
