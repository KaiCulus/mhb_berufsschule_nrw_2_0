import { defineStore } from 'pinia';
import axios from '@/scripts/axios';
import { useAuthStore } from '../authentification/auth';

export const useDocumentStore = defineStore('documents', {
  state: () => ({
    documents: [],
    favorites: [],
    searchQuery: '',
    searchSelectedIndex: -1,
    loading: false
  }),
  getters: {
      // Wandelt die flache Liste in eine Baumstruktur um (für die Anzeige)
      getTree: (state) => (parentId) => {
      if (!parentId) return [];
      
      // Wir trimmen beide IDs, um versteckte Leerzeichen zu vermeiden
      const searchId = String(parentId).trim();
      
      return state.documents.filter(d => {
        const itemParentId = String(d.parent_id).trim();
        return itemParentId === searchId;
      });
    },
      favoriteItems: (state) => {
      return state.documents.filter(doc => state.favorites.includes(doc.ms_id));
    },
    favoriteItemsByScope: (state) => (scope) => {
        if (!scope) return [];
        return state.documents.filter(doc => {
            const isFavorite = state.favorites.includes(doc.ms_id);
            const matchesScope = String(doc.scope).toLowerCase() === String(scope).toLowerCase();
            return isFavorite && matchesScope;
        });
    },
    filteredDocuments: (state) => {
        const query = state.searchQuery.toLowerCase().trim();
        
        // Wenn die Suche leer ist, geben wir sofort ein leeres Array zurück
        if (!query || query.length < 2) return []; // Optional: Erst ab 2 Zeichen suchen

        return state.documents.filter(doc => {
            // 1. Suche im Originalnamen
            const nameMatch = doc.name_original && 
                            doc.name_original.toLowerCase().includes(query);

            // 2. Suche in Aliasen (nur wenn das Array existiert und nicht leer ist)
            const aliasMatch = Array.isArray(doc.aliases) && doc.aliases.some(a => {
            return a && a.toLowerCase().includes(query);
            });

            return nameMatch || aliasMatch;
        });
    }
  },
  actions: {
      async fetchDocuments(scope) {
          this.loading = true;
          try {
              const response = await axios.get(`/api/documents/${scope}`);
              
              // WICHTIG: Jedes Dokument bekommt hier aktiv den Scope-Stempel
              const documentsWithScope = response.data.map(doc => ({
                  ...doc,
                  scope: scope // Dies setzt 'verwaltung' in jedes Objekt
              }));

              this.documents = documentsWithScope;
              
              console.log(`Erfolgreich ${this.documents.length} Dokumente für Scope ${scope} geladen.`);
          } catch (error) {
              console.error("Fehler beim Laden der Dokumente:", error);
          } finally {
              this.loading = false;
          }
      },
        getPath(targetId, rootId) {
        const path = [];
        let current = this.documents.find(d => d.ms_id === targetId);
        
        while (current && current.ms_id !== rootId) {
            path.unshift(current); // Vorne anfügen
            current = this.documents.find(d => d.ms_id === current.parent_id);
        }
        return path;
        },
        async fetchFavorites() {
            // WICHTIG: Store innerhalb der Funktion initialisieren
            const authStore = useAuthStore();        
            if (!authStore.dbId) return;
            try {
                const response = await axios.get(`/api/favorites/${authStore.dbId}`);
                this.favorites = response.data;
            } catch (error) {
                console.error("Fehler beim Laden der Favoriten:", error);
            }
        },

        async toggleFavorite(documentId) {
            const authStore = useAuthStore();        
            if (!authStore.dbId) {
                console.error("User nicht eingeloggt oder keine DB-ID vorhanden.");
                return;
            }
            const isFav = this.favorites.includes(documentId);        
            try {
                if (isFav) {
                    // Bei DELETE muss der Body oft in ein 'data' Objekt gewickelt werden
                    await axios.delete(`/api/favorites`, { 
                        data: { userId: authStore.dbId, docId: documentId } 
                    });
                    this.favorites = this.favorites.filter(id => id !== documentId);
                } else {
                    await axios.post(`/api/favorites`, { 
                        userId: authStore.dbId, 
                        docId: documentId 
                    });
                    this.favorites.push(documentId);
                }
            } catch (error) {
                console.error("Favoriten-Update fehlgeschlagen", error);
            }
        },
        setSearchSelectedIndex(index) {
            this.searchSelectedIndex = index;
        },
        async fetchAliases(docId) {
            const authStore = useAuthStore();
            const response = await axios.get(`/api/aliases/${docId}/${authStore.dbId}`);
            return response.data; // Wir speichern Aliase lokal in der Komponente, da sie flüchtig sind
        },
        async suggestAlias(docId, aliasText) {
            const authStore = useAuthStore();
            await axios.post(`/api/aliases`, {
                docId: docId,
                aliasText: aliasText,
                userId: authStore.dbId
            });
        },
        async toggleAliasVote(aliasId) {
            const authStore = useAuthStore();
            await axios.post(`/api/aliases/vote`, {
                aliasId: aliasId,
                userId: authStore.dbId
            });
        }
    }
});