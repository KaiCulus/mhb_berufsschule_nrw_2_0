<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from '@/scripts/axios';
import RoomAvaiabilityShowCalender from '@/components/roomavaiability/roomavaiabilitysubcomponents/RoomAvaiabilityShowCalender.vue';

const bookings = ref([]);
const loading = ref(false);

// Heute als Startdatum
const startDate = ref(new Date().toISOString().split('T')[0]);
// Standardmäßig 3 Tage später als Enddatum
const endDate = ref(new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);

const fetchBookings = async () => {
  // Validierung: Start darf nicht nach Ende liegen
  if (startDate.value > endDate.value) {
    alert("Das Startdatum muss vor dem Enddatum liegen.");
    return;
  }

  loading.value = true;
  try {
    // Umwandlung in ISO-Format für den Microsoft Graph (00:00 Uhr Start bis 23:59 Uhr Ende)
    const start = `${startDate.value}T00:00:00Z`;
    const end = `${endDate.value}T23:59:59Z`;
    
    const response = await axios.get('/api/rooms/bookings', {
      params: { start, end }
    });
    bookings.value = response.data.data;
  } catch (error) {
    console.error("Fehler beim Laden der Raumdaten", error);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchBookings);

// Wir beobachten beide Daten. Sobald sich eines ändert, laden wir neu.
watch([startDate, endDate], () => {
  fetchBookings();
});
</script>

<template>
  <div class="availability-container">
    <div class="controls-panel">
      <div class="date-inputs">
        <div class="input-group">
          <label>Von:</label>
          <input type="date" v-model="startDate" class="date-field" />
        </div>
        <div class="input-group">
          <label>Bis:</label>
          <input type="date" v-model="endDate" class="date-field" />
        </div>
      </div>
      
      <button @click="fetchBookings" class="refresh-btn" :disabled="loading">
        <span v-if="loading">⏳ Lade...</span>
        <span v-else>🔄 Ansicht aktualisieren</span>
      </button>
    </div>

    <div v-if="loading" class="loading-overlay">
      <div class="spinner"></div>
      <p>Termine werden abgerufen...</p>
    </div>

    <div v-else class="calendar-grid">
      <RoomAvaiabilityShowCalender 
        room-name="R15" 
        :bookings="bookings" 
      />
      <RoomAvaiabilityShowCalender 
        room-name="R20N" 
        :bookings="bookings" 
      />
      <RoomAvaiabilityShowCalender 
        room-name="Aula" 
        :bookings="bookings" 
      />
    </div>
  </div>
</template>

<style scoped>
.availability-container { padding: 20px; }

.controls-panel {
  background: #fdfdfd;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 30px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.date-inputs { display: flex; gap: 20px; }

.input-group { display: flex; flex-direction: column; gap: 5px; }

.input-group label {
  font-size: 0.8rem;
  font-weight: bold;
  color: #666;
}

.date-field {
  padding: 10px;
  border-radius: 8px;
  border: 1px solid #ccc;
  outline-color: #0e64a6;
  font-family: inherit;
}

.refresh-btn { 
  padding: 10px 25px;
  border-radius: 8px;
  border: none; 
  background: #0e64a6;
  color: white;
  cursor: pointer;
  font-weight: bold;
  transition: background 0.2s;
}

.refresh-btn:hover { background: #0a4d82; }
.refresh-btn:disabled { background: #ccc; cursor: not-allowed; }

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 25px;
}

/* Simpler Spinner */
.loading-overlay {
  text-align: center;
  padding: 50px;
  color: #0e64a6;
}

@media (max-width: 768px) {
  .controls-panel { flex-direction: column; align-items: stretch; gap: 20px; }
  .date-inputs { flex-direction: column; }
}
</style>