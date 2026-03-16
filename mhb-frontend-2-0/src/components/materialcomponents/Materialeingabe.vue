<script setup>
import { ref, reactive, onMounted, watch, nextTick } from 'vue';
import axios from '@/scripts/axios';
import { useAuthStore } from '@/stores/authentification/auth';

const authStore = useAuthStore();
const emit = defineEmits(['saved', 'cancel', 'itemAdded']);

const props = defineProps({
  editData: { type: Object, default: null } // Falls vorhanden -> Update Modus
});

// Refs für UI-Effekte
const nameInput = ref(null);
const showSuccess = ref(false);

// Initialzustand
const form = reactive({
  name: '',
  description: 'Selbsterklärend',
  location: '',
  quantity: 'x>0',
  contacts: ['']
});

const loading = ref(false);

const fillForm = (data) => {
  if (!data) return;
  form.name = data.name;
  form.description = data.description;
  form.location = data.location;
  form.quantity = data.quantity;
  form.contacts = data.all_contacts ? data.all_contacts.split(', ') : [''];
};

onMounted(() => {
  if (props.editData) {
    fillForm(props.editData);
  } else {
    form.contacts[0] = authStore.user?.name || '';
  }
  // Fokus direkt beim Laden auf das Namensfeld
  nameInput.value?.focus();
});

watch(() => props.editData, (newVal) => {
  if (newVal) fillForm(newVal);
}, { deep: true });

const addContactField = () => form.contacts.push('');
const removeContactField = (index) => {
  if (form.contacts.length > 1) form.contacts.splice(index, 1);
};

const submitForm = async () => {
  if (!form.name || !form.location) return;

  loading.value = true;
  try {
    const payload = { ...form, contacts: form.contacts.filter(c => c.trim() !== '') };
    
    if (props.editData) {
      await axios.post(`/api/materials/update/${props.editData.id}`, payload);
      emit('saved');
    } else {
      await axios.post('/api/materials', payload);
      
      // Feedback einblenden
      showSuccess.value = true;
      setTimeout(() => showSuccess.value = false, 2000);

      // Reset & Fokus
      form.name = '';
      form.description = 'Selbsterklärend';
      form.quantity = 'x>0';
      
      // Warten bis DOM updated, dann Fokus setzen
      await nextTick();
      nameInput.value?.focus();
      
      emit('itemAdded');
    }
  } catch (error) {
    console.error(error);
  } finally {
    loading.value = false;
  }
};

</script>

<template>
  <div 
    class="material-form card" 
    :class="{ 'success-glow': showSuccess }"
    @keydown.enter="submitForm"
  >
    <div class="header-row">
      <h3>{{ editData ? '✏️ Bearbeiten' : '📦 Neu erfassen' }}</h3>
      <Transition name="fade">
        <span v-if="showSuccess" class="success-msg">✅ Gespeichert!</span>
      </Transition>
    </div>
    
    <div class="form-grid">
      <div class="field-group">
        <label>Was hast du? (Gegenstand)</label>
        <input 
          ref="nameInput" 
          v-model="form.name" 
          type="text" 
          placeholder="z.B. Heißklebepistole" 
          required 
        />
      </div>

      <div class="field-group">
        <label>Wo befindet es sich?</label>
        <input v-model="form.location" type="text" placeholder="z.B. Schrank Flur TZ, R17N" required />
      </div>

      <div class="field-group">
        <label>Grobe Mengenangabe</label>
        <input v-model="form.quantity" type="text" />
      </div>

      <div class="field-group full-width">
        <label>Beschreibung / Details</label>
        <textarea v-model="form.description" rows="2"></textarea>
      </div>

      <div class="field-group full-width">
        <label>Ansprechpartner</label>
        <div v-for="(contact, index) in form.contacts" :key="index" class="contact-input-row">
          <input v-model="form.contacts[index]" type="text" placeholder="Name" />
          <button v-if="form.contacts.length > 1" @click="removeContactField(index)" type="button" class="remove-btn">×</button>
        </div>
        <button @click="addContactField" type="button" class="add-contact-btn">+ Kontakt hinzufügen</button>
      </div>
    </div>

    <div class="actions">
       <button v-if="editData" @click="emit('cancel')" class="cancel-btn" type="button">Abbrechen</button>
       <button @click="submitForm" :disabled="loading" class="submit-btn" type="button">
         {{ loading ? '...' : (editData ? 'Änderungen speichern' : 'Eintragen [Enter]') }}
       </button>
    </div>
  </div>
</template>

<style scoped>
.material-form {
  max-width: 600px;
  margin: 20px auto;
  padding: 25px;
  background: #fff;
  border-radius: 12px;
  box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  border: 1px solid #eee;
  transition: all 0.3s ease;
}

.form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 15px;
}

.full-width { grid-column: span 2; }

.field-group { display: flex; flex-direction: column; gap: 5px; }

label { font-weight: bold; font-size: 0.9rem; color: #444; }

input, textarea {
  padding: 10px;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 1rem;
}

.contact-input-row {
  display: flex;
  gap: 8px;
  margin-bottom: 8px;
}

.remove-btn {
  background: #ff7675;
  color: white;
  border: none;
  border-radius: 6px;
  width: 40px;
  cursor: pointer;
}

.add-contact-btn {
  background: #f1f2f6;
  border: 1px dashed #ced4da;
  padding: 8px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.85rem;
  width: 100%;
}

.submit-btn {
  width: 100%;
  margin-top: 25px;
  padding: 12px;
  background: #00b894;
  color: white;
  border: none;
  border-radius: 8px;
  font-weight: bold;
  cursor: pointer;
  transition: background 0.2s;
}

.submit-btn:hover { background: #00a087; }
.submit-btn:disabled { background: #ccc; }

/* Visuelles Feedback: Grünes Aufleuchten */
.success-glow {
  animation: glow 1.5s ease-out;
}

@keyframes glow {
  0% { box-shadow: 0 0 0px rgba(0, 184, 148, 0); border-color: #ddd; }
  20% { box-shadow: 0 0 20px rgba(0, 184, 148, 0.4); border-color: #00b894; }
  100% { box-shadow: 0 0 0px rgba(0, 184, 148, 0); border-color: #ddd; }
}

.header-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 15px;
}

.success-msg {
  color: #00b894;
  font-weight: bold;
  font-size: 0.9rem;
}

.fade-enter-active, .fade-leave-active { transition: opacity 0.5s; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

</style>