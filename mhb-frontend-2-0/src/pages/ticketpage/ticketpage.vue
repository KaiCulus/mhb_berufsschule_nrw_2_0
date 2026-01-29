<script setup>
import { ref } from 'vue';
import TicketFormMain from '@/components/tickets/TicketFormMain.vue';
import TicketVisualization from '@/components/tickets/TicketVisualization.vue';

const showForm = ref(false);

const toggleForm = () => {
  showForm.value = !showForm.value;
};

</script>

<template>
  <div class="ticket-page-container">
    <h1>Ticket-System</h1>
    <div class="header-section">
      <button 
        @click="toggleForm" 
        :class="['toggle-btn', { 'is-active': showForm }]"
      >
        {{ showForm ? '✖ Schließen' : '➕ Neues Problem melden' }}
      </button>
    </div>

    <transition name="fade-slide">
      <div v-if="showForm" class="form-wrapper">
        <TicketFormMain />
      </div>
    </transition>

    <hr class="separator" />

    <div class="list-section">
      <h2>Aktuelle Meldungen</h2>
      <TicketVisualization mode="all" />
    </div>
  </div>
</template>

<style scoped>
.ticket-page-container {
  padding: 20px;
  max-width: 1200px;
  margin: 0 auto;
}

.header-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 30px;
}

.toggle-btn {
  padding: 12px 24px;
  border-radius: 8px;
  border: none;
  background-color: #0e64a6;
  color: white;
  font-weight: bold;
  cursor: pointer;
  transition: all 0.3s ease;
}

.toggle-btn.is-active {
  background-color: #e74c3c;
}

.toggle-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.form-wrapper {
  background: #f9f9f9;
  padding: 20px;
  border-radius: 12px;
  border: 1px solid #eee;
  margin-bottom: 30px;
}

.separator {
  border: 0;
  height: 1px;
  background: #eee;
  margin: 40px 0;
}

/* Vue Transition Animationen */
.fade-slide-enter-active, .fade-slide-leave-active {
  transition: all 0.4s ease;
}
.fade-slide-enter-from, .fade-slide-leave-to {
  opacity: 0;
  transform: translateY(-20px);
}
</style>