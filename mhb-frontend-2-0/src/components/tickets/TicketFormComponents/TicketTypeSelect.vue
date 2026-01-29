<script setup>
import { computed } from 'vue';

const props = defineProps(['category', 'subCategory']);
const emit = defineEmits(['update:category', 'update:subCategory']);

// Definition der Mappings
const subCategoryMap = {
  it_support: [
    { value: 'software', label: 'Software / Programme' },
    { value: 'hardware', label: 'PC / Laptop Hardware' },
    { value: 'display', label: 'Bildschirmübertragung / Beamer' },
    { value: 'microsoft', label: 'Microsoft 365 / Teams Hickups' }
  ],
  network: [
    { value: 'wlan', label: 'WLAN Probleme' },
    { value: 'lan', label: 'LAN / Kabel-Verbindung' },
    { value: 'internet', label: 'Kein Internetzugriff' }
  ],
  facility: [
    { value: 'furniture', label: 'Möbelschäden' },
    { value: 'building', label: 'Gebäudeschaden (Fenster, Türen, Licht)' },
    { value: 'heating', label: 'Heizung / Klima' }
  ]
};

const currentSubOptions = computed(() => {
  return subCategoryMap[props.category] || [];
});

// Wenn die Hauptkategorie wechselt, setzen wir die Sub-Kategorie zurück
const handleCategoryChange = (e) => {
  emit('update:category', e.target.value);
  emit('update:subCategory', ''); 
};
</script>

<template>
  <div class="type-select-container">
    <div class="form-section">
      <label>Was für ein Problem liegt vor?</label>
      <select :value="category" @change="handleCategoryChange" class="main-select">
        <option value="it_support">IT-Support (PC, Software, Office)</option>
        <option value="network">Netzwerk (WLAN / LAN)</option>
        <option value="facility">Gebäude & Infrastruktur (Hausmeister)</option>
      </select>
    </div>

    <div v-if="currentSubOptions.length" class="form-section sub-section">
      <label>Spezifizierung</label>
      <select 
        :value="subCategory" 
        @change="emit('update:subCategory', $event.target.value)"
        class="sub-select"
      >
        <option value="" disabled>Bitte wählen...</option>
        <option v-for="opt in currentSubOptions" :key="opt.value" :value="opt.value">
          {{ opt.label }}
        </option>
      </select>
    </div>
  </div>
</template>

<style scoped>
.form-section { margin-bottom: 15px; display: flex; flex-direction: column; }
label { font-size: 0.9rem; font-weight: bold; margin-bottom: 5px; color: #444; }
select {
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 1rem;
  background: white;
}
.main-select { border-left: 5px solid #0e64a6; }
.sub-select { border-left: 5px solid #ff9800; background-color: #fffaf0; }
.sub-section { animation: fadeIn 0.3s ease; }

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}
</style>