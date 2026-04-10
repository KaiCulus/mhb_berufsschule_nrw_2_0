import { defineStore } from 'pinia';
import axios from '@/scripts/axios';
import { useAuthStore } from '../authentification/auth';

/**
 * Document Store
 *
 * Verwaltet den gesamten Dokumenten-State der Anwendung:
 * - Dokumentenbaum (hierarchisch via parent_id)
 * - Favoriten des eingeloggten Users
 * - Suchzustand
 * - Alias-Operationen
 *
 * Alle API-Calls laufen über den zentralen Axios-Client (@/scripts/axios),
 * der Auth-Header und Basis-URL bereits konfiguriert.
 */
export const useDocumentStore = defineStore('documents', {
  state: () => ({
    documents: [],
    loadedScopes: [],
    favorites: [],
    searchQuery: '',
    searchSelectedIndex: -1,
    loading: false,
  }),

  getters: {
    /**
     * Gibt alle direkten Kinder eines Knotens zurück.
     * Wird vom DocumentTree für das rekursive Rendering genutzt.
     * @param {string|null} parentId - ms_id des Elternknotens, null → Root
     */
    getTree: (state) => (parentId, scope = null) => {
      const searchId = parentId ? String(parentId).trim() : 'root';
      return state.documents.filter((d) => {
        const itemParentId = d.parent_id ? String(d.parent_id).trim() : 'root';
        const parentMatch = itemParentId === searchId;
        const scopeMatch = scope ? d.scope === scope : true;
        return parentMatch && scopeMatch;
      });
    },

    /** Alle Dokumente, die der User als Favorit markiert hat. */
    favoriteItems: (state) => {
      return state.documents.filter((doc) => state.favorites.includes(doc.ms_id));
    },

    /**
     * Favoriten gefiltert nach Scope (z.B. 'verwaltung', 'paedagogik').
     * @param {string} scope
     */
    favoriteItemsByScope: (state) => (scope) => {
      if (!scope) return [];
      return state.documents.filter((doc) => {
        const isFavorite = state.favorites.includes(doc.ms_id);
        const matchesScope =
          String(doc.scope).toLowerCase() === String(scope).toLowerCase();
        return isFavorite && matchesScope;
      });
    },

    /**
     * Volltextsuche über Originalname und Aliase.
     * Gibt erst ab 2 Zeichen Ergebnisse zurück, um zu viele Treffer zu vermeiden.
     */
    filteredDocuments: (state) => {
      const query = state.searchQuery.toLowerCase().trim();
      if (!query || query.length < 2) return [];

      return state.documents.filter((doc) => {
        const nameMatch =
          doc.name_original && doc.name_original.toLowerCase().includes(query);
        const aliasMatch =
          Array.isArray(doc.aliases) &&
          doc.aliases.some((a) => a && a.toLowerCase().includes(query));
        return nameMatch || aliasMatch;
      });
    },

    /**
     * Gibt den Pfad von Root bis zum Zieldokument zurück (Breadcrumb).
     * Direkte Root-Kinder (parent_id === 'root') werden korrekt eingeschlossen.
     * @param {string} targetId - ms_id des Zieldokuments
     */
    getPath: (state) => (targetId) => {
      const path = [];
      let current = state.documents.find((d) => d.ms_id === targetId);

      while (current && current.parent_id) {
        path.unshift(current);
        if (current.parent_id === 'root') break;
        current = state.documents.find((d) => d.ms_id === current.parent_id);
      }
      return path;
    },
  },

  actions: {
    /**
     * Lädt alle Dokumente eines Scopes vom Backend und stempelt
     * jeden Eintrag mit dem Scope-Wert (wird für Scope-Filterung benötigt).
     * @param {string} scope - z.B. 'verwaltung'
     */
    async fetchDocuments(scope) {
      if (this.loadedScopes.includes(scope)) return;
      this.loading = true;
      try {
        const response = await axios.get(`/api/documents/${scope}`);
        const newDocs = response.data.map((doc) => ({ ...doc, scope }));
        this.documents = [
          ...this.documents.filter((d) => d.scope !== scope),
          ...newDocs,
        ];
        this.loadedScopes.push(scope);
        console.log(`${this.documents.length} Dokumente für Scope "${scope}" geladen.`);
      } catch (error) {
        console.error('Fehler beim Laden der Dokumente:', error);
      } finally {
        this.loading = false;
      }
    },

    /**
     * Lädt die Dokumente neu. Z.B. nach einem FolderSync
     */
    async refreshDocuments(scope) {
      this.loadedScopes = this.loadedScopes.filter(s => s !== scope);
      await this.fetchDocuments(scope);
    },

    /**
     * Lädt die Favoriten-IDs des eingeloggten Users.
     * Nutzt die dbId aus dem AuthStore als User-Identifier.
     */
    async fetchFavorites() {
      const authStore = useAuthStore();
      if (!authStore.dbId) return;
      try {
        const response = await axios.get(`/api/favorites/${authStore.dbId}`);
        this.favorites = response.data;
      } catch (error) {
        console.error('Fehler beim Laden der Favoriten:', error);
      }
    },

    /**
     * Toggled den Favoritenstatus eines Dokuments optimistisch im UI
     * und synchronisiert im Hintergrund mit dem Backend.
     * @param {string} documentId - ms_id des Dokuments
     */
    async toggleFavorite(documentId) {
      const authStore = useAuthStore();
      if (!authStore.dbId) {
        console.error('Kein eingeloggter User — Favorit kann nicht geändert werden.');
        return;
      }

      const isFav = this.favorites.includes(documentId);
      try {
        if (isFav) {
          await axios.delete('/api/favorites', {
            data: { userId: authStore.dbId, docId: documentId },
          });
          this.favorites = this.favorites.filter((id) => id !== documentId);
        } else {
          await axios.post('/api/favorites', {
            userId: authStore.dbId,
            docId: documentId,
          });
          this.favorites.push(documentId);
        }
      } catch (error) {
        console.error('Favoriten-Update fehlgeschlagen:', error);
      }
    },

    /** Setzt den aktuell per Tastatur markierten Sucheintrag. */
    setSearchSelectedIndex(index) {
      this.searchSelectedIndex = index;
    },

    /** Leert die Suche und setzt den Selektions-Index zurück. */
    clearSearch() {
      this.searchQuery = '';
      this.searchSelectedIndex = -1;
    },

    /**
     * Lädt alle Aliase für ein Dokument.
     * Aliase werden lokal in der aufrufenden Komponente gehalten (flüchtig).
     * @param {string} scope - Dokumenten-Scope (z.B. 'verwaltung')
     * @param {number} docId - Datenbank-ID des Dokuments
     */
    async fetchAliases(scope, docId) {
      try {
        const response = await axios.get(`/api/aliases/${scope}/${docId}`);
        return response.data;
      } catch (error) {
        console.error('Fehler beim Laden der Aliase:', error);
        return [];
      }
    },

    /**
     * Schlägt einen neuen Alias für ein Dokument vor.
     * @param {number} docId - Datenbank-ID des Dokuments
     * @param {string} aliasText - Der vorgeschlagene Aliasname
     */
    async suggestAlias(docId, aliasText) {
      const authStore = useAuthStore();
      try {
        await axios.post('/api/aliases', {
          docId,
          aliasText,
          userId: authStore.dbId,
        });
      } catch (error) {
        console.error('Alias-Vorschlag fehlgeschlagen:', error);
      }
    },

    /**
     * Toggelt die Stimme des eingeloggten Users für einen Alias.
     * @param {number} aliasId
     */
    async toggleAliasVote(aliasId) {
      const authStore = useAuthStore();
      try {
        await axios.post('/api/aliases/vote', {
          aliasId,
          userId: authStore.dbId,
        });
      } catch (error) {
        console.error('Alias-Vote fehlgeschlagen:', error);
      }
    },
  },
});