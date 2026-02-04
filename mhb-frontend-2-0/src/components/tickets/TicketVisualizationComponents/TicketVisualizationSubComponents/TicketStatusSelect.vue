<script setup>
defineProps(['modelValue', 'isReadonly']);
defineEmits(['update:modelValue']);

const statuses = [
  { value: 'open', label: '🆕 Offen', color: '#e74c3c' },
  { value: 'in_progress', label: '⚙️ In Bearbeitung', color: '#3498db' },
  { value: 'processing_paused', label: '⏳ Bearbeitung Pausiert', color: '#f1c40f' },
  { value: 'waiting_for_external_response', label: '🌐 Warten auf Externe', color: '#9b59b6' },
  { value: 'resolved_by_staff', label: '✅ Erledigt', color: '#27ae60' }
];

const getLabel = (val) => statuses.find(s => s.value === val)?.label || val;
</script>

<template>
  <div class="status-select">
    <label>Aktueller Status</label>
    
    <div 
        v-if="isReadonly" 
        class="status-badge"
        :style="{ backgroundColor: statuses.find(s => s.value === modelValue)?.color + '20', color: statuses.find(s => s.value === modelValue)?.color }"
    >
      {{ getLabel(modelValue) }}
    </div>

    <select 
      v-else 
      :value="modelValue" 
      @change="$emit('update:modelValue', $event.target.value)"
      class="status-dropdown"
    >
      <option v-for="s in statuses" :key="s.value" :value="s.value">
        {{ s.label }}
      </option>
    </select>
  </div>
</template>

<style scoped>
.status-select { margin-bottom: 15px; }
label { display: block; font-weight: bold; font-size: 0.8rem; color: #666; margin-bottom: 5px; }
.status-badge {
  display: inline-block;
  padding: 6px 12px;
  background: #eee;
  border-radius: 20px;
  font-weight: bold;
  font-size: 0.9rem;
}
.status-dropdown {
  width: 100%;
  padding: 8px;
  border-radius: 6px;
  border: 2px solid #0e64a6;
  font-weight: bold;
}
</style>