// Derzeit noch nicht in Benutzung. Kann später für UI Präferenzen o.Ä. verwendet werden.
import { defineStore } from 'pinia';

/**
 * User Preferences Store
 *
 * Speichert benutzerspezifische UI-Einstellungen, die über Sessions hinaus
 * erhalten bleiben sollen (z.B. Anzeigemodus, Sprache).
 * Authentifizierungsdaten → auth.js
 * Dokumenten-/Favoritendaten → documents.js
 */
export const useUserstore = defineStore('userstore', {
  state: () => ({
    // Hier können später UI-Präferenzen rein, z.B.:
    // theme: 'light',
    // language: 'de',
  }),
  persist: true,
});