<script setup>
/**
 * TicketPriorityInfo — Dringlichkeits-Auswahl
 *
 * Zeigt Prioritätsstufen als klickbare Karten mit Farbkodierung.
 *
 * Im Readonly-Modus werden nicht gewählte Karten ausgeblendet
 * (via .disabled { display: none }) — nur die aktive Stufe bleibt sichtbar.
 *
 * Hintergrundfarbe der aktiven Karte: Da CSS-Custom-Properties keine
 * rgba()-Umrechnung erlauben, werden die Farben per style*-Selektor
 * direkt auf den Hex-Wert des --brand-color gemappt.
 *
 * Props:
 *   modelValue — Aktiver Prioritätswert (z.B. 'medium')
 *   isReadonly — Readonly-Modus
 * Emits:
 *   update:modelValue — Neu gewählte Priorität
 */
const props = defineProps({
  modelValue: String,
  isReadonly: Boolean,
});
const emit = defineEmits(['update:modelValue']);

const priorities = [
  { value: 'information', label: 'Info / Frage', color: '#3498db', desc: 'Kein Fehler, eher eine Rückfrage oder Anregung.' },
  { value: 'low',         label: 'Niedrig',      color: '#2ecc71', desc: 'Einschränkung vorhanden, aber Workaround möglich.' },
  { value: 'medium',      label: 'Mittel',        color: '#f1c40f', desc: 'Reguläres Problem, Bearbeitung im normalen Zeitrahmen.' },
  { value: 'high',        label: 'Hoch',          color: '#e67e22', desc: 'Wichtiges Problem, beeinträchtigt den Unterricht/Betrieb.' },
  { value: 'critical',    label: 'Kritisch',      color: '#e74c3c', desc: 'Totalausfall! Sofortige Hilfe notwendig.' },
];
</script>

<template>
  <div class="priority-container" :class="{ 'readonly-mode': isReadonly }">
    <label>Dringlichkeit</label>
    <div class="priority-grid">
      <div
        v-for="p in priorities"
        :key="p.value"
        class="priority-card"
        :class="{ active: modelValue === p.value, disabled: isReadonly && modelValue !== p.value }"
        :style="{ '--brand-color': p.color }"
        @click="!isReadonly && emit('update:modelValue', p.value)"
      >
        <div class="radio-circle" v-if="!isReadonly || modelValue === p.value"></div>
        <div class="content">
          <span class="p-label">{{ p.label }}</span>
          <p class="p-desc" v-if="!isReadonly">{{ p.desc }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.priority-container { margin-bottom: 20px; }

label {
  font-size: 0.9rem;
  font-weight: bold;
  display: block;
  margin-bottom: 10px;
}

.priority-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(110px, 1fr));
  gap: 10px;
}

.priority-card {
  background: white;
  border: 2px solid #eee;
  border-radius: 10px;
  padding: 12px;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
}

.priority-card:hover { border-color: var(--brand-color); background: #fafafa; }

.priority-card.active {
  border-color: var(--brand-color);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
}

/*
 * Hintergrundfarbe der aktiven Karte: rgba() mit CSS-Custom-Properties
 * wird nicht überall unterstützt — deshalb explizite Farb-Selektoren.
 */
.priority-card.active[style*="#3498db"] { background-color: #ebf5fb; }
.priority-card.active[style*="#2ecc71"] { background-color: #eafaf1; }
.priority-card.active[style*="#f1c40f"] { background-color: #fef9e7; }
.priority-card.active[style*="#e67e22"] { background-color: #fdf2e9; }
.priority-card.active[style*="#e74c3c"] { background-color: #fdedec; }

/* Im Readonly-Modus: nicht gewählte Karten ausblenden */
.priority-card.disabled { display: none; }

.readonly-mode .priority-card.active {
  cursor: default;
  border-width: 1px;
  box-shadow: none;
}

.radio-circle {
  width: 16px;
  height: 16px;
  border: 2px solid #ccc;
  border-radius: 50%;
  margin-bottom: 8px;
  position: relative;
}

.priority-card.active .radio-circle {
  border-color: var(--brand-color);
}

.priority-card.active .radio-circle::after {
  content: '';
  position: absolute;
  top: 3px;
  left: 3px;
  width: 6px;
  height: 6px;
  background: var(--brand-color);
  border-radius: 50%;
}

.p-label { font-weight: bold; font-size: 0.85rem; display: block; color: #333; }
.p-desc  { font-size: 0.65rem; color: #777; margin-top: 4px; line-height: 1.2; }
</style>