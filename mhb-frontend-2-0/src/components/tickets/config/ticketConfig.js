/**
 * ticketConfig.js — Zentrale Konfiguration für das Ticket-System
 *
 * Enthält alle Gebäude, Räume und Hilfsfunktionen, die sowohl im
 * Formular (TicketLocationInput) als auch in der Raum-Abo-Verwaltung
 * (TicketSubscribeRoom) benötigt werden.
 *
 * Neue Räume oder Gebäude hier ergänzen — alle Komponenten
 * greifen auf diese Quelle zurück.
 */
export const TICKET_CONFIG = {
  buildings: [
    'Hauptgebäude (R)',
    'Moritz-Fiege-Straße (S)',
    'Technologiezentrum (T)',
    'Landwirtschaft (L)',
  ],

  roomsByBuilding: {
    'Hauptgebäude (R)': [
      'R01', 'R02', 'R04', 'R07', 'R08', 'R09', 'R15', 'R17', 'R17N', 'R18', 'R19', 'R20', 'R21',
      'R21N', 'R22', 'R23', 'R24', 'R24N', 'R25', 'R26', 'R27', 'R28', 'R28N',
      'R29', 'R30', 'R31', 'R32', 'R33', 'R34', 'R35', 'R36', 'R37', 'R38',
      'R38N', 'R39', 'R42', 'R44', 'R46', 'R48', 'R49', 'Aula',
    ],
    'Moritz-Fiege-Straße (S)': [
      'S01', 'S10', 'S11', 'S12', 'S13', 'S20', 'S20N', 'S21', 'S22', 'S23',
      'S30', 'S32', 'Turn1', 'Turn3_1', 'Turn3_3',
    ],
    'Technologiezentrum (T)': [
      'T01', 'T02', 'T11', 'T12', 'T13', 'T14', 'T15', 'T16', 'T17', 'T18', 'T19',
    ],
    'Landwirtschaft (L)': [
      'L11', 'L12', 'L13', 'L21', 'L22', 'L23',
    ],
  },

  /** Gibt alle Räume über alle Gebäude hinweg alphabetisch sortiert zurück. */
  getAllRooms() {
    return Object.values(this.roomsByBuilding).flat().sort();
  },

  /** Prüft ob ein Raumname in irgendeinem Gebäude bekannt ist (case-insensitive). */
  isValidRoom(roomName) {
    if (!roomName) return false;
    const normalized = roomName.toUpperCase().trim();
    return this.getAllRooms().includes(normalized);
  },
};