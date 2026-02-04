<script setup>
import { computed } from 'vue';

const props = defineProps({
  roomName: String,
  bookings: { type: Array, default: () => [] },
  selectedDate: String
});

// Filtert die Bookings, falls die API mehr als nur den aktuellen Tag liefert
const filteredBookings = computed(() => {
  return props.bookings.filter(b => b.room === props.roomName);
});

const formatTime = (isoString) => {
  return new Date(isoString).toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
};
</script>

<template>
  <div class="room-card">
    <div class="room-header">
      <h3>{{ roomName }}</h3>
      <span class="badge">{{ filteredBookings.length }} Buchungen</span>
    </div>

    <div class="booking-list">
        <div v-for="booking in filteredBookings" :key="booking.id" class="booking-item">
            <div class="time-slot">
            <div class="date-label">{{ new Date(booking.start).toLocaleDateString('de-DE', { weekday: 'short', day: '2-digit', month: '2-digit' }) }}</div>
            <div class="time-label">{{ formatTime(booking.start) }} - {{ formatTime(booking.end) }}</div>
            </div>
            <div class="booking-details">
            <span class="subject">{{ booking.subject }}</span>
            <span class="organizer">{{ booking.organizer }}</span>
            </div>
        </div>
      <div v-if="filteredBookings.length === 0" class="no-bookings">
        Frei verfügbar
      </div>
    </div>
  </div>
</template>

<style scoped>
.room-card {
  background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
  overflow: hidden; display: flex; flex-direction: column; border: 1px solid #eee;
}
.room-header {
  background: #0e64a6; color: white; padding: 15px;
  display: flex; justify-content: space-between; align-items: center;
}
.booking-list { padding: 15px; display: flex; flex-direction: column; gap: 10px; }
.booking-item {
  display: flex; gap: 15px; padding: 10px; border-radius: 8px;
  background: #f8f9fa; border-left: 4px solid #0e64a6;
}
.time-slot { 
    font-weight: bold;
    color: #0e64a6;
    min-width: 100px; 
    display: flex;
    flex-direction: column;
}
.date-label { font-size: 0.7rem; text-transform: uppercase; color: #888; }
.time-label { font-weight: bold; color: #0e64a6; }
.booking-details { display: flex; flex-direction: column; }
.subject { font-weight: 600; font-size: 0.95rem; }
.organizer { font-size: 0.8rem; color: #666; }
.no-bookings { text-align: center; color: #27ae60; padding: 20px; font-weight: bold; }
</style>