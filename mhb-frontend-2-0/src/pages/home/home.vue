<script setup>
import { computed } from 'vue';
import { useAuthStore } from '@/stores/authentification/auth';
import { useRouter, useRoute } from 'vue-router';
import { storeToRefs } from 'pinia';
import AnleitungMain from '@/components/anleitung/anleitungMain.vue';
import DashboardAnleitung from '@/components/anleitung/anleitungsbereiche/dashboardanleitung.vue';
import VerwaltungsdokumenteAnleitung from '@/components/anleitung/anleitungsbereiche/verwaltungsdokumenteanleitung.vue';
import RaumbuchungAnleitung from '@/components/anleitung/anleitungsbereiche/raumbuchunganzeigeanleitung.vue';
import TicketAnleitung from '@/components/anleitung/anleitungsbereiche/ticketanleitung.vue';
import MaterialsucheAnleitung from '@/components/anleitung/anleitungsbereiche/materialsucheanleitung.vue';
import WissenssammlungAnleitung from '@/components/anleitung/anleitungsbereiche/wissenssammlunganleitung.vue';
import Changelog from '@/components/anleitung/changelog.vue';

/**
 * home
 *
 * Startseite der Anwendung.
 * Eingeloggte User sehen die Anleitung aller Bereiche als Toggle-Elemente.
 * Nicht eingeloggte User werden zum Login weitergeleitet.
 *
 * Query-Parameter `section` öffnet beim Aufruf direkt den passenden Anleitungsbereich,
 * z. B. /?section=tickets — wird von anleitungLinkElement.vue gesetzt.
 */

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();
const { isLoggedIn } = storeToRefs(auth);

const s = computed(() => route.query.section ?? null);

const goToLogin = () => {
  router.push('/login');
};
</script>

<template>
  <div v-if="isLoggedIn" class="anleitung-seite">
    <h2 class="anleitung-titel">Anleitung</h2>
<AnleitungMain
      label="Änderungen seit Veröffentlichung"
    >
    <Changelog />
    </AnleitungMain>
    <AnleitungMain label="Dashboard" :initially-open="s === 'dashboard'">
      <DashboardAnleitung />
    </AnleitungMain>

    <AnleitungMain label="Verwaltungsdokumente (MHB)" :initially-open="s === 'mhb'">
      <VerwaltungsdokumenteAnleitung />
    </AnleitungMain>

    <AnleitungMain label="Raumbuchungsübersicht" :initially-open="s === 'rooms'">
      <RaumbuchungAnleitung />
    </AnleitungMain>

    <AnleitungMain label="Tickets / Schadensmeldungen" :initially-open="s === 'tickets'">
      <TicketAnleitung />
    </AnleitungMain>

    <AnleitungMain label="Materialsuche" :initially-open="s === 'material'">
      <MaterialsucheAnleitung />
    </AnleitungMain>

    <AnleitungMain label="Wissenssammlung" :initially-open="s === 'wissen'">
      <WissenssammlungAnleitung />
    </AnleitungMain>
  </div>

  <div v-else>
    <button @click="goToLogin">
      Zum Loginbereich
    </button>
  </div>
</template>

<style scoped>
.anleitung-seite {
  max-width: 800px;
  margin: 0 auto;
  padding: 1rem;
}

.anleitung-titel {
  margin-bottom: 1rem;
  font-size: 1.4rem;
}
</style>
