<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useAuthStore } from '@/stores/authentification/auth';
import { storeToRefs } from 'pinia';
import HeaderLeft from './headerelements/headerleft/headerleftmain.vue';
import HeaderMiddle from './headerelements/headermiddle/headermiddlemain.vue';
import HeaderRight from './headerelements/headerright/headerrightmain.vue';

/**
 * headermain — Globaler App-Header
 *
 * Sticky Header mit drei Bereichen:
 *   - Links:  Logo (immer sichtbar)
 *   - Mitte:  Navigationsmenü (nur eingeloggt)
 *   - Rechts: Logout-Button (nur eingeloggt)
 *
 * Scroll-Verhalten: Ab dem ersten Scroll-Pixel erhält der Header
 * die Klasse `.scrolled` für einen transparenten Hintergrund-Effekt.
 * Der Event Listener wird beim Unmounten sauber entfernt.
 */

const auth = useAuthStore();
const { isLoggedIn } = storeToRefs(auth);

const header = ref(null);

const handleScroll = () => {
  header.value?.classList.toggle('scrolled', window.scrollY > 0);
};

onMounted(() => {
  window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
  window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
  <div id="Mainheader" ref="header">
    <div class="header-wrapper">
      <div class="flexcontainer">
        <HeaderLeft />
        <div class="noStructureChange" v-if="isLoggedIn">
          <HeaderMiddle />
          <HeaderRight />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
#Mainheader {
  width: 100%;
  background-color: #333;
  color: white;
  padding: 5px 0;
  position: sticky;
  top: 0;
  z-index: 1000;
  transition: background-color 0.3s ease;
}

.header-wrapper {
  position: relative;
}

/* Beim Scrollen: halbtransparenter Hintergrund */
#Mainheader.scrolled {
  background-color: rgba(0, 0, 0, 0.1);
}

@media (max-width: 768px) {
  #header-left,
  #header-middle,
  #header-right {
    margin: 0;
  }
}
</style>