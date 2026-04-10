<?php

/**
 * routes.php
 *
 * Zentrale Routen-Definition für das MHB Backend.
 *
 * Zwei Typen:
 *
 *   'static'  — Exakter String-Match auf den Pfad.
 *               Schneller O(1)-Lookup, geeignet für feste URLs.
 *               Format: 'pfad' => ['METHODE' => [Controller::class, 'methode']]
 *
 *   'dynamic' — Regex-Match mit URL-Parametern (Capture Groups).
 *               Wird nur geprüft wenn kein statischer Match gefunden wurde.
 *               Format: '#^regex$#' => ['METHODE' => [Controller::class, 'methode']]
 *               Die Capture Groups werden als Argumente an die Controller-Methode übergeben.
 *
 * Authentifizierung:
 *   Routen unter oauth/* sind öffentlich (Login-Flow).
 *   Alle api/* Routen werden durch AuthMiddleware::check() in den jeweiligen
 *   Controllern geschützt — nicht hier zentral, sondern pro Controller.
 */

use Kai\MhbBackend20\Auth\Controllers\OAuthController;
use Kai\MhbBackend20\Database\Controllers\TicketController;
use Kai\MhbBackend20\Database\Controllers\DocumentController;
use Kai\MhbBackend20\Database\Controllers\FavoriteController;
use Kai\MhbBackend20\Database\Controllers\AliasController;
use Kai\MhbBackend20\Graph\Controllers\GraphSyncController;
use Kai\MhbBackend20\Graph\Controllers\RaumbuchungsUebersichtController;
use Kai\MhbBackend20\Database\Controllers\MaterialController;

return [

    // =========================================================================
    // Statische Routen
    // =========================================================================

    'static' => [

        // --- OAuth / Authentifizierung (öffentlich) --------------------------
        'oauth/login'    => ['GET' => [OAuthController::class, 'redirectToOAuth']],
        'oauth/callback' => ['GET' => [OAuthController::class, 'handleCallback']],
        'oauth/logout'   => ['GET' => [OAuthController::class, 'logout']],

        // --- Session / User --------------------------------------------------
        // Gibt den aktuellen User + Berechtigungen zurück (wird beim App-Start geprüft)
        'api/me'           => ['GET' => [OAuthController::class, 'getCurrentUser']],
        'api/user/profile' => ['GET' => [TicketController::class, 'getCurrentUserProfile']],

        // --- Favoriten -------------------------------------------------------
        // Alle Favoriten-Operationen ohne ID in der URL (sicherer als /favorites/{id})
        'api/favorites' => [
            'GET'    => [FavoriteController::class, 'getFavorites'],
            'POST'   => [FavoriteController::class, 'addFavorite'],
            'DELETE' => [FavoriteController::class, 'removeFavorite'],
        ],

        // --- Aliases ---------------------------------------------------------
        'api/aliases'      => ['POST' => [AliasController::class, 'addAlias']],
        'api/aliases/vote' => ['POST' => [AliasController::class, 'toggleVote']],

        // --- Raumplanung & Sync ----------------------------------------------
        'api/rooms/bookings'       => ['GET'  => [RaumbuchungsUebersichtController::class, 'getOverview']],
        'api/sync/get-permissions' => ['GET'  => [GraphSyncController::class, 'getPermissions']],

        // --- Tickets (Basis) -------------------------------------------------
        'api/tickets' => [
            'GET'  => [TicketController::class, 'getAll'],
            'POST' => [TicketController::class, 'createTicket'],
        ],

        // --- Ticket-Aktionen (kein URL-Parameter nötig) ----------------------
        'api/tickets/comment'         => ['POST' => [TicketController::class, 'addComment']],
        'api/tickets/update-field'    => ['POST' => [TicketController::class, 'updateField']],
        'api/tickets/subscribe'       => ['POST' => [TicketController::class, 'toggleSubscription']],
        'api/tickets/subscribe-room'  => ['POST' => [TicketController::class, 'toggleRoomSubscription']],
        'api/tickets/resolve'         => ['POST' => [TicketController::class, 'resolveTicket']],
        'api/tickets/cleanup'         => ['POST' => [TicketController::class, 'cleanupOldTickets']],

        // --- Ticket-Archiv ---------------------------------------------------
        'api/tickets/archive'         => ['GET'  => [TicketController::class, 'getArchivedTickets']],
        'api/tickets/restore'         => ['POST' => [TicketController::class, 'restoreTicket']],

        // --- Materialien (Basis) ---------------------------------------------
        'api/materials' => ['POST' => [MaterialController::class, 'create']],
    ],

    // =========================================================================
    // Dynamische Routen (mit URL-Parametern)
    // =========================================================================

    'dynamic' => [

        // --- Dokumente -------------------------------------------------------
        // GET api/documents/{scope}
        '#^api/documents/([^/]+)$#' => ['GET' => [DocumentController::class, 'getByScope']],

        // --- Aliases ---------------------------------------------------------
        // GET api/aliases/{scope}/{documentId}
        '#^api/aliases/([^/]+)/([^/]+)$#' => ['GET' => [AliasController::class, 'getAliases']],

        // --- Favoriten -------------------------------------------------------
        // GET api/favorites/{scope}
        '#^api/favorites/([^/]+)$#' => ['GET' => [FavoriteController::class, 'getFavorites']],

        // --- Tickets ---------------------------------------------------------
        // GET api/tickets/user/{userId}
        '#^api/tickets/user/(\d+)$#' => ['GET' => [TicketController::class, 'getByUser']],

        // GET api/tickets/detail/{ticketId}
        '#^api/tickets/detail/(\d+)$#' => ['GET' => [TicketController::class, 'getDetail']],

        // GET api/tickets/subscribe-room/{roomId}
        '#^api/tickets/subscribe-room/(\d+)$#' => ['GET' => [TicketController::class, 'getRoomSubscriptions']],

        // GET api/tickets/canDeleteTicket/{ticketId}
        '#^api/tickets/canDeleteTicket/(\d+)$#' => ['GET' => [TicketController::class, 'getCanDeleteTicket']],

        // --- Ticket-Bilder ---------------------------------------------------
        // POST api/tickets/images/{ticketId}  — Bilder hochladen
        '#^api/tickets/images/(\d+)$#' => [
            'POST'   => [TicketController::class, 'uploadImages'],
            'GET'    => [TicketController::class, 'getImages'],
        ],

        // DELETE api/tickets/images/delete/{imageId}  — Einzelbild löschen
        '#^api/tickets/images/delete/(\d+)$#' => ['DELETE' => [TicketController::class, 'deleteImage']],

        // GET api/tickets/images/serve/{imageId}  — Auth-geschützter Bild-Stream
        '#^api/tickets/images/serve/(\d+)$#'  => ['GET'    => [TicketController::class, 'serveImage']],

        // --- Synchronisation -------------------------------------------------
        // POST api/sync/execute/{folderId}
        '#^api/sync/execute/([^/]+)$#' => ['POST' => [GraphSyncController::class, 'executeSync']],

        // --- Materialien -----------------------------------------------------
        // GET  api/materials/search
        '#^api/materials/search$#' => ['GET' => [MaterialController::class, 'search']],

        // POST api/materials/update/{id}
        '#^api/materials/update/(\d+)$#' => ['POST' => [MaterialController::class, 'update']],

        // POST api/materials/delete/{id}
        '#^api/materials/delete/(\d+)$#' => ['POST' => [MaterialController::class, 'delete']],
    ],
];
