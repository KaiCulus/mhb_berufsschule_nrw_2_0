<script setup>
import { ref, onMounted } from 'vue';
import axios from '@/scripts/axios';
import { useAuthStore } from '@/stores/authentification/auth';

const authStore = useAuthStore();
const materials = ref([]);
const loading = ref(true);
const sort = ref({ by: 'name', dir: 'ASC' });

const emit = defineEmits(['edit']);

const fetchMaterials = async () => {
  loading.value = true;
  const res = await axios.get(`/api/materials/search?q=&sortBy=${sort.value.by}&sortDir=${sort.value.dir}`);
  materials.value = res.data;
  loading.value = false;
};

const toggleSort = (field) => {
  if (sort.value.by === field) {
    sort.value.dir = sort.value.dir === 'ASC' ? 'DESC' : 'ASC';
  } else {
    sort.value.by = field;
    sort.value.dir = 'ASC';
  }
  fetchMaterials();
};

const deleteItem = async (id) => {
  if (!confirm("Wirklich löschen?")) return;
  await axios.post(`/api/materials/delete/${id}`);
  fetchMaterials();
};

onMounted(fetchMaterials);
defineExpose({ refresh: fetchMaterials });
</script>

<template>
  <div class="material-list-container">
    <h3>📋 Gesamtinventar ({{ materials.length }} Gegenstände)</h3>
    
    <div v-if="loading" class="loading-inline">Daten werden geladen...</div>
    
    <div v-else class="table-responsive">
      <table class="inventory-table">
        <thead>
          <tr>
            <th @click="toggleSort('name')" class="sortable">
              Gegenstand {{ sort.by === 'name' ? (sort.dir === 'ASC' ? '🔼' : '🔽') : '' }}
            </th>
            <th>Menge</th>
            <th @click="toggleSort('location')" class="sortable">
              Ort {{ sort.by === 'location' ? (sort.dir === 'ASC' ? '🔼' : '🔽') : '' }}
            </th>
            <th @click="toggleSort('contacts')" class="sortable">
              Ansprechpartner {{ sort.by === 'contacts' ? (sort.dir === 'ASC' ? '🔼' : '🔽') : '' }}
            </th>
            <th>Aktionen</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="item in materials" :key="item.id">
            <td class="name-cell">
              <strong>{{ item.name }}</strong>
              <small v-if="item.description && item.description !== 'Selbsterklärend'">
                {{ item.description }}
              </small>
            </td>

            <td><span class="badge">{{ item.quantity }}</span></td>

            <td>📍 {{ item.location }}</td>

            <td class="contact-cell">{{ item.all_contacts || '-' }}</td>

            <td>
              <div v-if="item.created_by == authStore.dbId" class="action-btns">
                <button @click="emit('edit', item)" class="edit-btn" title="Bearbeiten">✏️</button>
                <button @click="deleteItem(item.id)" class="delete-btn" title="Löschen">🗑️</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      
      <div v-if="materials.length === 0" class="empty-state">
        Keine Materialien gefunden.
      </div>
    </div>
  </div>
</template>

<style scoped>
.material-list-container {
  margin-top: 40px;
  background: white;
  padding: 20px;
  border-radius: 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}

.table-responsive { overflow-x: auto; }

.inventory-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
  font-size: 0.95rem;
}

.inventory-table th {
  text-align: left;
  background: #f8f9fa;
  padding: 12px;
  border-bottom: 2px solid #00b894;
  color: #2d3436;
}

.inventory-table td {
  padding: 12px;
  border-bottom: 1px solid #eee;
  vertical-align: top;
}

.name-cell small {
  display: block;
  color: #777;
  font-size: 0.8rem;
  margin-top: 4px;
}

.badge {
  background: #e8f8f5;
  color: #00b894;
  padding: 2px 8px;
  border-radius: 4px;
  font-weight: bold;
  font-size: 0.85rem;
}

.contact-cell { color: #636e72; font-size: 0.9rem; }

.empty-state { text-align: center; padding: 30px; color: #999; }

tr:hover { background-color: #f9fffb; }


.sortable { cursor: pointer; user-select: none; }
.sortable:hover { color: #00b894; }
.action-btns { display: flex; gap: 10px; }
.edit-icon, .delete-icon { background: none; border: none; cursor: pointer; filter: grayscale(1); }
.edit-icon:hover, .delete-icon:hover { filter: grayscale(0); transform: scale(1.2); }
</style>