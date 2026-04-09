<?php

/**
 * graph.php
 *
 * Konfiguration für die Microsoft Graph API und SharePoint-Synchronisation.
 *
 * Alle Zugangsdaten kommen aus der .env — diese Datei enthält keine Secrets.
 * Pflicht-Variablen (tenant_id, client_id, client_secret) werden in
 * loadEnvironmentConfiguration() (index.php) bereits validiert, daher
 * kein erneuter Fallback auf Exceptions hier nötig.
 *
 * Neue Sync-Profile hinzufügen:
 *   1. Neuen Key in 'sync_profiles' anlegen (z.B. 'paedagogik')
 *   2. Passende .env-Variablen setzen (site_id, drive_id, folder_id)
 *   3. Zugehörige Gruppe in GraphSyncController::ROLE_MAPPING eintragen
 */

return [

    // =========================================================================
    // Microsoft Graph API — Zugangsdaten (Client Credentials Flow)
    // =========================================================================

    // Azure AD Tenant — identisch mit dem OAuth-Login-Tenant
    'tenant_id'     => $_ENV['MHB_BE_MSAL_TENANT_ID']           ?? null,

    // App-Registrierung mit Sites.ReadWrite.All und Mail.Send Berechtigungen
    'client_id'     => $_ENV['MHB_BE_MSAL_CLIENT_ID']           ?? null,
    'client_secret' => $_ENV['MHB_BE_MSAL_CLIENT_SECRET_VALUE'] ?? null,

    // Graph API Basis-URL — v1.0 ist der stabile Produktions-Endpunkt
    'base_url'      => 'https://graph.microsoft.com/v1.0',

    // =========================================================================
    // Sync-Profile — SharePoint Ordner-Definitionen
    // =========================================================================
    // Jedes Profil repräsentiert einen SharePoint-Bereich der synchronisiert
    // werden kann. Der Key (z.B. 'verwaltung') muss in
    // GraphSyncController::ROLE_MAPPING als erlaubter Sync-Typ eingetragen sein.
    //
    // IDs findest du in der SharePoint URL oder via Graph Explorer:
    //   https://developer.microsoft.com/en-us/graph/graph-explorer

    'sync_profiles' => [

        // Verwaltungsdokumente (MHB 2.0)
        'verwaltung' => [
            'name'      => 'Schulverwaltung MHB 2.0',
            'site_id'   => $_ENV['MHB_MS_GRAPH_SITE_ID_VERWALTUNG']   ?? null,
            'drive_id'  => $_ENV['MHB_MS_GRAPH_DRIVE_ID_VERWALTUNG']  ?? null,
            'folder_id' => $_ENV['MHB_MS_GRAPH_FOLDER_ID_VERWALTUNG'] ?? null,
        ],

        // COMMON (Allgemeine Wissenssammlung) (vorbereitet, noch nicht aktiv)
        // Um dieses Profil zu aktivieren:
        //   1. .env-Variablen MHB_MS_GRAPH_*_COMMON setzen
        //   2. 'common' in GraphSyncController::ROLE_MAPPING eintragen
        'common' => [
            'name'      => 'Allgemeine Wissenssammlung',
            'site_id'   => $_ENV['MHB_MS_GRAPH_SITE_ID_COMMON']   ?? null,
            'drive_id'  => $_ENV['MHB_MS_GRAPH_DRIVE_ID_COMMON']  ?? null,
            'folder_id' => $_ENV['MHB_MS_GRAPH_FOLDER_ID_COMMON'] ?? null,
        ],

    ],

    // =========================================================================
    // Technische Limits
    // =========================================================================

    // Guzzle HTTP-Timeout pro Request in Sekunden
    // Bei sehr großen Ordnern ggf. erhöhen (GraphSyncController setzt set_time_limit(300))
    'request_timeout' => 30.0,

    // Maximale Anzahl Items pro Graph API Seite (Pagination)
    // Graph API Maximum: 999 — 200 ist ein guter Kompromiss für Stabilität
    'page_size'       => 200,
];