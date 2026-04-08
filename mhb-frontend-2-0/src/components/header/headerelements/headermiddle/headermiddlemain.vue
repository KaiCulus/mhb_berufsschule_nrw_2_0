<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import MenuEntry from './headermiddleSubcomponents/menuEntry.vue';

/**
 * headermiddlemain — Hamburger-Navigationsmenü
 *
 * Klappt ein vollbreites Dropdown-Menü auf/zu.
 *
 * Besonderheit beim Öffnen: Die CSS-Transition benötigt einen minimalen
 * Verzug (10ms), damit der Browser das Element erst rendert bevor die
 * .visible-Klasse gesetzt wird — sonst überspringt er die Animation.
 *
 * Schließen: Klick außerhalb von Burger-Icon und Menü schließt automatisch.
 * Navigationseinträge senden zusätzlich ein 'close-menu'-Event.
 *
 * Menüeinträge hier zentral pflegen:
 */
const menuEntries = [
  { name: 'Dashboard',             route: '/dashboard' },
  { name: 'Verwaltungsdokumente',  route: '/mhb'       },
  { name: 'Raumbuchungsübersicht', route: '/rooms'     },
  { name: 'Tickets/Schadensmeldung', route: '/tickets' },
  { name: 'Materialsuche',         route: '/material'  },
];

const isVisible = ref(false);

const toggleVisibility = () => {
  isVisible.value = !isVisible.value;
  // Minimaler Verzug damit der Browser das Element rendert
  // bevor die .visible-Klasse die CSS-Transition auslöst
  setTimeout(() => {
    document.getElementById('mainMenu')?.classList.toggle('visible', isVisible.value);
  }, 10);
};

const closeMenuOnOutsideClick = (event) => {
  if (
    !event.target.closest('#header-middle') &&
    !event.target.closest('#mainMenu')
  ) {
    isVisible.value = false;
  }
};

onMounted(() => {
  window.addEventListener('click', closeMenuOnOutsideClick);
});

onUnmounted(() => {
  window.removeEventListener('click', closeMenuOnOutsideClick);
});
</script>

<template>
  <div id="header-middle" @click="toggleVisibility">
    <span>☰</span>
  </div>

  <div v-if="isVisible" id="mainMenu">
    <div class="dropdownmenu-main">
      <MenuEntry
        v-for="(entry, index) in menuEntries"
        :key="index"
        :name="entry.name"
        :route="entry.route"
        @close-menu="isVisible = false"
      />
    </div>
  </div>
</template>

<style scoped>
#header-middle {
  display: flex;
  align-items: center;
  cursor: pointer;
}

#header-middle span {
  font-size: 2em;
}

#mainMenu {
  position: absolute;
  top: 100%;
  left: 0;
  width: 100vw;
  background-color: #333;
  z-index: 1001;
  transition: opacity 0.3s, transform 0.3s;
}

/* Ausgeblendet: unsichtbar und nicht klickbar, aber im DOM für die Transition */
#mainMenu:not(.visible) {
  opacity: 0;
  transform: translateY(-10px);
  pointer-events: none;
}

.dropdownmenu-main {
  display: flex;
  flex-direction: column;
  width: 100%;
  max-width: 1200px;
  margin: 0 auto;
  padding: 10px 0;
}
</style>