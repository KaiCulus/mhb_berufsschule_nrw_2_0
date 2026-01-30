 <script setup>
import { computed } from 'vue';
const props = defineProps({
  category: String,
  subCategory: String,
  isReadonly: Boolean
});
const emit = defineEmits(['update:category', 'update:subCategory']);

const categoryLabels = {
  it_support: 'IT-Support',
  network: 'Netzwerk',
  facility: 'Gebäude & Infrastruktur'
};

const subCategoryMap = {
  it_support: [
    { value: 'software', label: 'Software / Programme' },
    { value: 'hardware', label: 'PC / Laptop Hardware' },
    { value: 'display', label: 'Bildschirmübertragung / Beamer' },
    { value: 'microsoft', label: 'Microsoft 365 / Teams Hickups' },
    { value: 'other', label: 'Sonstiges (Bitte angeben...)' }
  ],
  network: [
    { value: 'wlan', label: 'WLAN Probleme' },
    { value: 'lan', label: 'LAN / Kabel-Verbindung' },
    { value: 'internet', label: 'Kein Internetzugriff' },
    { value: 'other', label: 'Sonstiges (Bitte angeben...)' }
  ],

  facility: [
    { value: 'furniture', label: 'Möbelschäden' },
    { value: 'building', label: 'Gebäudeschaden (Fenster, Türen, Licht)' },
    { value: 'heating', label: 'Heizung / Klima' },
    { value: 'other', label: 'Sonstiges (Bitte angeben...)' }
  ]
};


const currentSubOptions = computed(() => {
  return subCategoryMap[props.category] || [];
});
// Prüfen, ob "Sonstiges" gewählt wurde oder ob ein manueller Text drinsteht,
// der nicht in den Standard-Optionen ist
const isOtherSelected = computed(() => {
  if (props.isReadonly) return false; // Im Readonly zeigen wir kein Input-Feld
  if (props.subCategory === 'other') return true;
  if (!props.subCategory) return false;
  const standardValues = currentSubOptions.value.map(o => o.value);
  return !standardValues.includes(props.subCategory);
});

const handleCategoryChange = (e) => {
  if (props.isReadonly) return;
  emit('update:category', e.target.value);
  emit('update:subCategory', ''); 
};


const handleSubSelectChange = (e) => {
  if (props.isReadonly) return;
  emit('update:subCategory', e.target.value);
};
</script>

<template>
  <div class="type-select-container">
    <div v-if="isReadonly" class="readonly-type-display">
      <div class="type-row">
        <span class="main-label">{{ categoryLabels[category] }}</span>
        <span v-if="subCategory" class="separator">/</span>
        <span v-if="subCategory" class="sub-label">{{ displaySubCategory }}</span>
      </div>
    </div>

    <template v-else>
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
        <select :value="isOtherSelected ? 'other' : subCategory" @change="handleSubSelectChange" class="sub-select">
          <option value="" disabled>Bitte wählen...</option>
          <option v-for="opt in currentSubOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
        </select>
      </div>

      <div v-if="isOtherSelected" class="form-section other-input-section">
        <label>Bitte präzisieren:</label>
        <input 
          type="text" 
          :value="subCategory === 'other' ? '' : subCategory"
          @input="emit('update:subCategory', $event.target.value)"
          placeholder="Beschreibe kurz das Problem-Thema..."
          class="other-input"
          autofocus
        />
      </div>
    </template>
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
.other-input {
  border-left: 5px solid #e67e22;
  outline: none;
}

.other-input:focus { border-color: #d35400; box-shadow: 0 0 5px rgba(230, 126, 34, 0.2); }
.main-select { border-left: 5px solid #0e64a6; }
.sub-select { border-left: 5px solid #ff9800; background-color: #fffaf0; }
.sub-section, .other-input-section { animation: fadeIn 0.3s ease; }

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}
.readonly-type-display { margin-bottom: 15px; }
.type-row { display: flex; align-items: center; gap: 8px; font-weight: 600; }
.main-label { color: #0e64a6; }
.sub-label { color: #e67e22; }
.separator { color: #ccc; }
</style> 
