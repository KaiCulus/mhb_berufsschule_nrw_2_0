<?php

/**
 * Microsoft Graph API Configuration
 * * Diese Datei bündelt alle Informationen für den Zugriff auf SharePoint/Teams Sites.
 * Profile können einfach erweitert werden, indem ein neuer Key im 'sync_profiles' Array
 * hinzugefügt wird.
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Global Graph API Settings
    |--------------------------------------------------------------------------
    */
    'tenant_id'     => $_ENV['MHB_BE_MSAL_TENANT_ID'] ?? null,
    'client_id'     => $_ENV['MHB_BE_MSAL_CLIENT_ID'] ?? null,
    'client_secret' => $_ENV['MHB_BE_MSAL_CLIENT_SECRET_VALUE'] ?? null,
    
    // Basis-URL für Microsoft Graph
    'base_url'      => 'https://graph.microsoft.com/v1.0',

    /*
    |--------------------------------------------------------------------------
    | Sync Profiles
    |--------------------------------------------------------------------------
    | Hier definierst du die verschiedenen SharePoint-Bereiche.
    | Der TeamsSyncService nutzt diese Keys (z.B. 'verwaltung'), um 
    | die entsprechenden Ordner rekursiv zu scannen.
    */
    'sync_profiles' => [
        
        // Profil für den Verwaltungsbereich (MHB_2.0)
        'verwaltung' => [
            'name'      => 'Schulverwaltung MHB 2.0',
            'site_id'   => $_ENV['MHB_MS_GRAPH_SITE_ID_VERWALTUNG'] ?? null,
            'drive_id'  => $_ENV['MHB_MS_GRAPH_DRIVE_ID_VERWALTUNG'] ?? null,
            'folder_id' => $_ENV['MHB_MS_GRAPH_FOLDER_ID_VERWALTUNG'] ?? null,
        ],

        // Beispiel für ein weiteres Profil (kann später aktiviert werden)
        'lehrmittel' => [
            'name'      => 'Lehrmittel & Inventar',
            'site_id'   => $_ENV['MHB_MS_GRAPH_SITE_ID_LEHRMITTEL'] ?? null,
            'drive_id'  => $_ENV['MHB_MS_GRAPH_DRIVE_ID_LEHRMITTEL'] ?? null,
            'folder_id' => $_ENV['MHB_MS_GRAPH_FOLDER_ID_LEHRMITTEL'] ?? null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Technical Limits
    |--------------------------------------------------------------------------
    */
    'request_timeout' => 30.0, // Guzzle Timeout in Sekunden
    'page_size'       => 200,  // Anzahl der Items pro API-Request (Pagination)
];