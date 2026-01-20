<template>
  <div class="p-4 border rounded">
    <h3>DB-Test (ID: {{ auth.dbId }})</h3>
    <button @click="getThirdLetter" class="bg-blue-500 text-white p-2">
      Hole 3. Buchstaben
    </button>
    <p v-if="result">Ergebnis: {{ result }}</p>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useAuthStore } from '@/stores/authentification/auth'

const auth = useAuthStore();
const result = ref('');

async function getThirdLetter() {
  // DEBUG: Schau in die Browser-Konsole (F12 -> Console)
  const authHeader = auth.getAuthHeader();
  console.log("Sende Header:", authHeader); 

  try {
    const response = await fetch(`https://localhost:443/api/test-letter?id=${auth.dbId}`, {
      headers: { 
        ...authHeader, // Hier muss 'Authorization': 'Bearer ...' drin sein
        'Content-Type': 'application/json'
      }
    });

    /*if (response.status === 401) {
      console.warn("Token abgelaufen oder ungültig");
      auth.login(); // Neu einloggen
      return;
    }*/

    const data = await response.json();
    result.value = data.letter;
  } catch (error) {
    console.error(error);
  }
}
</script>