<script setup>
import { ref, watch } from 'vue';
import axios from '@/scripts/axios';
import { useAuthStore } from '@/stores/authentification/auth';

const authStore = useAuthStore();
const searchQuery = ref('');
const results = ref([]);
const loading = ref(false);

const emit = defineEmits(['edit', 'deleted']);

const search = async () => {
  if (searchQuery.value.length < 2) {
    results.value = [];
    return;
  }
  
  loading.value = true;
  try {
    const res = await axios.get(`/api/materials/search?q=${searchQuery.value}`);
    results.value = res.data;
  } catch (error) {
    console.error("Suche fehlgeschlagen", error);
  } finally {
    loading.value = false;
  }
};

const deleteItem = async (id) => {
  if (!confirm("Möchtest du diesen Gegenstand wirklich löschen?")) return;
  try {
    await axios.post(`/api/materials/delete/${id}`);
    // Aus der aktuellen Ergebnisliste im Frontend entfernen
    results.value = results.value.filter(item => item.id !== id);
    emit('deleted'); // Optional, um die Liste unten auch zu refreshen
  } catch (e) {
    alert("Löschen fehlgeschlagen.");
  }
};

// Suche triggern, wenn sich die Eingabe ändert (mit kleiner Verzögerung)
let timeout = null;
watch(searchQuery, () => {
  clearTimeout(timeout);
  timeout = setTimeout(search, 300);
});
</script>

<template>
 <div class="search-container">
    <div class="search-box">
      <input 
        v-model="searchQuery" 
        type="text" 
        placeholder="🔍 Suche nach Material, Ort oder Ansprechpartner..."
        class="search-input"
      />
    </div>

    <div v-if="loading" class="loading">Suche läuft...</div>

    <div v-if="results.length > 0" class="results-grid">
      <div v-for="item in results" :key="item.id" class="result-card">
        <div class="card-header">
          <h4>{{ item.name }}</h4>
          <div class="header-right">
            <span class="quantity-badge">{{ item.quantity }}</span>
          </div>
        </div>
        <div class="card-body">
          <p><strong>📍 Ort:</strong> {{ item.location }}</p>
          <p><strong>👤 Ansprechpartner:</strong> {{ item.all_contacts || 'Keine Angabe' }}</p>
          <p class="desc">{{ item.description }}</p>
        </div>
        
        <div v-if="item.created_by == authStore.dbId" class="card-actions">
          <button @click="emit('edit', item)" class="edit-btn" title="Bearbeiten">✏️ Bearbeiten</button>
          <button @click="deleteItem(item.id)" class="delete-btn" title="Löschen">🗑️</button>
        </div>
      </div>
    </div>

    <div v-else-if="searchQuery.length >= 2 && !loading" class="no-results">
      Nichts gefunden. Vielleicht mal anders suchen?
    </div>
  </div>
</template>

<style scoped>
.search-container { max-width: 800px; margin: 0 auto; padding: 20px; }
.search-input {
  width: 100%; padding: 15px; border-radius: 10px; border: 2px solid #00b894;
  font-size: 1.1rem; outline: none; box-sizing: border-box;
}
.results-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 20px; margin-top: 30px;
}
.result-card {
  background: white; border-radius: 12px; padding: 15px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.05); border-left: 5px solid #00b894;
}
.card-header { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
.card-header h4 { margin: 0; color: #2d3436; }
.quantity-badge { background: #e8f8f5; color: #00b894; padding: 2px 8px; border-radius: 5px; font-size: 0.8rem; font-weight: bold; }
.desc { font-size: 0.9rem; color: #636e72; margin-top: 10px; font-style: italic; }
.no-results { text-align: center; margin-top: 40px; color: #b2bec3; }

.header-right { display: flex; align-items: center; gap: 8px; }

.result-card {
  position: relative;
  display: flex;
  flex-direction: column;
  /* ... rest wie zuvor ... */
}

.card-actions {
  margin-top: auto;
  padding-top: 15px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  border-top: 1px solid #eee;
}

.edit-btn {
  background: #f1f2f6;
  border: 1px solid #ced4da;
  padding: 5px 10px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 0.85rem;
  color: #2d3436;
}

.edit-btn:hover { background: #dfe6e9; }

.delete-btn {
  background: none;
  border: none;
  cursor: pointer;
  padding: 5px;
  opacity: 0.6;
  transition: opacity 0.2s;
}

.delete-btn:hover { opacity: 1; transform: scale(1.1); }
</style>