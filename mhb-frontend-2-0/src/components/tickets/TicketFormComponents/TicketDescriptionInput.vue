<script setup>
defineProps({
  modelValue: String,
  isReadonly: Boolean
});
defineEmits(['update:modelValue']);
</script>

<template>
  <div class="form-section">
    <label>Problembeschreibung</label>
    <div v-if="isReadonly" class="description-box">
      {{ modelValue }}
    </div>
    <textarea 
      v-else 
      :value="modelValue" 
      @input="$emit('update:modelValue', $event.target.value)" 
      required 
      rows="4"
    ></textarea>
  </div>
</template>

<style scoped>
.form-section { margin-bottom: 15px; display: flex; flex-direction: column; }
label { font-size: 0.9rem; font-weight: bold; margin-bottom: 8px; color: #555; }
textarea { padding: 10px; border: 1px solid #ccc; border-radius: 6px; resize: vertical; }
.description-box { 
  background: #f9f9f9; padding: 15px; border-radius: 8px; 
  border: 1px solid #eee; line-height: 1.5; white-space: pre-wrap;
}
</style>