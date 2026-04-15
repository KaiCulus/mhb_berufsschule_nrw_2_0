<script setup>
import AnleitungMain from '@/components/anleitung/anleitungMain.vue';
import Raumbuchunganzeigeanleitung from '@/components/anleitung/anleitungsbereiche/raumbuchunganzeigeanleitung.vue';
import RoomAvaiabilityMain from '@/components/roomavaiability/RoomAvaiabilityMain.vue';

/**
 * roomavaiabilitypage — Raumbuchungsübersicht
 *
 * Zeigt die aktuelle Belegung aller buchbaren Räume.
 * Delegiert die Darstellungslogik vollständig an RoomAvaiabilityMain.
 *
 * Hinweis: Der Tippfehler "Avaiability" (statt "Availability") ist im
 * gesamten Ordner- und Dateinamen konsistent — bitte nur projektübergreifend
 * korrigieren, da alle Imports übereinstimmen müssen.
 */
</script>

<template>
  <div class="page-container">
    <header class="page-header">
      <h1>🏫 Raumbuchungsübersicht</h1>
      <AnleitungMain
          label="Erklärung: Raumbuchungsübersicht"
      >
          <Raumbuchunganzeigeanleitung />
      </AnleitungMain>
    </header>

    <main>
      <RoomAvaiabilityMain />
    </main>
  </div>
</template>

<style scoped>
.page-container {
  max-width: 1200px;
  margin: 0 auto;
  padding: 40px 20px;
}

.page-header {
  margin-bottom: 40px;
}

.page-header h1 {
  color: #000;
  font-size: 2rem;
  margin-bottom: 20px;
}
</style>