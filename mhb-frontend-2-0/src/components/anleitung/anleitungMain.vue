<script setup>
import { ref, watch } from 'vue';

/**
 * anleitungMain — Generischer Toggle-Wrapper für Anleitungsbereiche
 *
 * Props:
 *   label         — Überschrift des Toggle-Buttons
 *   initiallyOpen — Ob der Bereich beim Laden bereits aufgeklappt ist
 *
 * Slot: default — Anleitungsinhalt der jeweiligen Seite
 *
 * Verwendung auf home.vue (mehrere Bereiche):
 *   <AnleitungMain label="Dashboard" :initially-open="openSection === 'dashboard'">
 *     <DashboardAnleitung />
 *   </AnleitungMain>
 *
 * Verwendung auf einer Einzelseite:
 *   <AnleitungMain label="Tickets / Schadensmeldungen">
 *     <TicketAnleitung />
 *   </AnleitungMain>
 */

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  initiallyOpen: {
    type: Boolean,
    default: false,
  },
});

const isOpen = ref(props.initiallyOpen);

// Reagiert auf nachträgliche Änderungen von initiallyOpen
// (z.B. wenn der Query-Parameter erst nach dem Mount gesetzt wird)
watch(() => props.initiallyOpen, (val) => {
  if (val) isOpen.value = true;
});
</script>

<template>
  <div class="anleitung-bereich">
    <button
      class="anleitung-toggle"
      :class="{ offen: isOpen }"
      @click="isOpen = !isOpen"
    >
      <span>{{ label }}</span>
      <span class="anleitung-pfeil">{{ isOpen ? '▲' : '▼' }}</span>
    </button>

    <div v-if="isOpen" class="anleitung-content">
      <slot />
    </div>
  </div>
</template>

<style scoped>
.anleitung-bereich {
  border: 1px solid #ccc;
  border-radius: 6px;
  margin-bottom: 0.5rem;
  overflow: hidden;
}

.anleitung-toggle {
  width: 100%;
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.75rem 1rem;
  background: #f5f5f5;
  border: none;
  cursor: pointer;
  font-size: 1rem;
  font-weight: 600;
  text-align: left;
}

.anleitung-toggle:hover {
  background: #e8e8e8;
}

.anleitung-toggle.offen {
  background: #e0ecff;
}

.anleitung-pfeil {
  font-size: 0.75rem;
  margin-left: 0.5rem;
}

.anleitung-content {
  padding: 0.75rem 1rem 1rem;
  background: #fff;
}
</style>
