<script setup>
/**
 * TicketTitleInput — Kurztitel-Eingabe
 *
 * Wiederverwendbar im Formular (editierbar) und in der Detailansicht (readonly).
 *
 * Props:
 *   modelValue — Aktueller Titelwert
 *   isReadonly — Readonly-Modus: zeigt Text statt Input
 * Emits:
 *   update:modelValue — Neuer Wert bei Eingabe
 */
defineProps({
  modelValue: String,
  isReadonly: Boolean,
});
defineEmits(['update:modelValue']);
</script>

<template>
  <div class="form-section">
    <label>Kurztitel</label>
    <p v-if="isReadonly" class="readonly-text">{{ modelValue }}</p>
    <input
      v-else
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      required
      placeholder="Was ist passiert?"
    />
  </div>
</template>

<style scoped>
.form-section {
  margin-bottom: 15px;
  display: flex;
  flex-direction: column;
}

label {
  font-size: 0.9rem;
  font-weight: bold;
  margin-bottom: 5px;
  color: #444;
}

input {
  padding: 10px;
  border: 1px solid #ccc;
  border-radius: 6px;
}

.readonly-text {
  font-size: 1.1rem;
  font-weight: 600;
  color: #2c3e50;
  margin: 0;
}
</style>