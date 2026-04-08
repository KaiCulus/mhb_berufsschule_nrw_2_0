<script setup>
import { useRouter } from 'vue-router';

/**
 * menuEntry — Einzelner Navigations-Eintrag
 *
 * Navigiert per Vue Router zur angegebenen Route und schließt
 * danach das übergeordnete Dropdown-Menü via Event.
 *
 * Props:
 *   name  — Anzeigetext des Eintrags
 *   route — Zielpfad für vue-router (z.B. '/dashboard')
 * Emits:
 *   close-menu — Signalisiert headermiddlemain das Menü zu schließen
 */

const router = useRouter();
const emit = defineEmits(['close-menu']);
const props = defineProps({
  name:  { type: String, required: true },
  route: { type: String, required: true },
});

const goToEntry = () => {
  router.push(props.route);
  emit('close-menu');
};
</script>

<template>
  <div class="menu-entry" @click="goToEntry">
    {{ name }}
  </div>
</template>

<style scoped>
.menu-entry {
  width: 100%;
  padding: 12px 20px;
  color: white;
  background-color: #444;
  text-align: left;
  cursor: pointer;
  transition: background-color 0.2s;
}

.menu-entry:hover {
  background-color: #555;
}
</style>