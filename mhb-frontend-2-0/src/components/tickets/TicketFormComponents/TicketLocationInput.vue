<script setup>
const props = defineProps(['type', 'building', 'room']);
const emit = defineEmits(['update:type', 'update:building', 'update:room']);

// Liste deiner Schulgebäude
const buildings = [
  'Hauptgebäude (A)',
  'Nebengebäude (B)',
  'Sporthalle',
  'Werkstattbereich',
  'Verwaltung'
];

const updateType = (newType) => {
  emit('update:type', newType);
  // Reset der Felder bei Typwechsel für saubere Daten
  if (newType === 'other') {
    emit('update:building', '');
    emit('update:room', '');
  }
};
</script>

<template>
  <div class="location-container">
    <label>Ort des Problems</label>
    
    <div class="type-toggle">
      <button 
        type="button"
        :class="{ active: type === 'building' }" 
        @click="updateType('building')"
      >
        🏫 Gebäude / Raum
      </button>
      <button 
        type="button"
        :class="{ active: type === 'other' }" 
        @click="updateType('other')"
      >
        📍 Sonstiger Ort
      </button>
    </div>

    <div v-if="type === 'building'" class="location-fields animate-slide">
      <div class="field-group">
        <select 
          :value="building" 
          @change="emit('update:building', $event.target.value)"
          required
        >
          <option value="" disabled>Gebäude wählen...</option>
          <option v-for="b in buildings" :key="b" :value="b">{{ b }}</option>
        </select>
      </div>

      <div class="field-group">
        <input 
          type="text" 
          :value="room" 
          @input="emit('update:room', $event.target.value)"
          placeholder="Raumnummer (z.B. A104)"
          required
        />
      </div>
    </div>

    <div v-else class="location-fields animate-slide">
      <div class="field-group">
        <input 
          type="text" 
          :value="room" 
          @input="emit('update:room', $event.target.value)"
          placeholder="Wo genau? (z.B. Schulhof, Parkplatz...)"
          required
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.location-container {
  margin-bottom: 20px;
  padding: 15px;
  background: #f9f9f9;
  border-radius: 8px;
}

label { font-size: 0.9rem; font-weight: bold; display: block; margin-bottom: 10px; }

.type-toggle {
  display: flex;
  gap: 10px;
  margin-bottom: 15px;
}

.type-toggle button {
  flex: 1;
  padding: 10px;
  border: 1px solid #ddd;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.2s;
}

.type-toggle button.active {
  background: #0e64a6;
  color: white;
  border-color: #0e64a6;
  box-shadow: 0 2px 5px rgba(14, 100, 166, 0.3);
}

.location-fields {
  display: flex;
  gap: 10px;
}

.field-group { flex: 1; }

input, select {
  width: 100%;
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
  box-sizing: border-box;
}

.animate-slide {
  animation: slideIn 0.3s ease-out;
}

@keyframes slideIn {
  from { opacity: 0; transform: translateX(-10px); }
  to { opacity: 1; transform: translateX(0); }
}
</style>