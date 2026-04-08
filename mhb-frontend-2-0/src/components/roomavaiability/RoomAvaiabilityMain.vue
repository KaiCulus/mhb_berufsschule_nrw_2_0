<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from '@/scripts/axios';
import RoomAvaiabilityShowCalender from '@/components/roomavaiability/roomavaiabilitysubcomponents/RoomAvaiabilityShowCalender.vue';

/**
 * RoomAvaiabilityMain — Raumbuchungsübersicht
 *
 * Zeigt Buchungen für alle konfigurierten Räume in einem wählbaren Zeitraum.
 * Standard: heute bis in 3 Tagen.
 *
 * Der Datums-Watcher lädt automatisch neu sobald Start- oder Enddatum
 * geändert wird. Der "Aktualisieren"-Button dient als manueller Fallback.
 *
 * Neue Räume: Weitere <RoomAvaiabilityShowCalender>-Instanzen mit dem
 * jeweiligen room-name aus der Microsoft-Graph-API ergänzen.
 *
 * Hinweis: Zeitstempel werden als UTC an die API übergeben (T00:00:00Z /
 * T23:59:59Z), damit der gesamte Tag abgedeckt ist unabhängig von der
 * lokalen Zeitzone des Browsers.
 */

const bookings  = ref([]);
const loading   = ref(false);

const startDate = ref(new Date().toISOString().split('T')[0]);
const endDate   = ref(new Date(Date.now() + 3 * 24 * 60 * 60 * 1000).toISOString().split('T')[0]);

const fetchBookings = async () => {
  if (startDate.value > endDate.value) {
    alert('Das Startdatum muss vor dem Enddatum liegen.');
    return;
  }

  loading.value = true;
  try {
    const response = await axios.get('/api/rooms/bookings', {
      params: {
        start: `${startDate.value}T00:00:00Z`,
        end:   `${endDate.value}T23:59:59Z`,
      }
    });
    bookings.value = response.data;
  } catch (error) {
    console.error('Fehler beim Laden der Raumdaten:', error);
  } finally {
    loading.value = false;
  }
};

onMounted(fetchBookings);

watch([startDate, endDate], fetchBookings);
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
      <p>Termine werden abgerufen...</p>
    </div>

    <div v-else class="calendar-grid">
      <RoomAvaiabilityShowCalender room-name="R15"  :bookings="bookings" />
      <RoomAvaiabilityShowCalender room-name="R20N" :bookings="bookings" />
      <RoomAvaiabilityShowCalender room-name="Aula" :bookings="bookings" />
    </div>
  </div>
</template>

<style scoped>
.availability-container {
  padding: 20px;
}

.controls-panel {
  background: #fdfdfd;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #eee;
  display: flex;
  justify-content: space-between;
  align-items: flex-end;
  margin-bottom: 30px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.date-inputs {
  display: flex;
  gap: 20px;
}

.input-group {
  display: flex;
  flex-direction: column;
  gap: 5px;
}

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

.refresh-btn:hover    { background: #0a4d82; }
.refresh-btn:disabled { background: #ccc; cursor: not-allowed; }

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
  gap: 25px;
}

.loading-overlay {
  text-align: center;
  padding: 50px;
  color: #0e64a6;
}

@media (max-width: 768px) {
  .controls-panel { flex-direction: column; align-items: stretch; gap: 20px; }
  .date-inputs    { flex-direction: column; }
}
</style>