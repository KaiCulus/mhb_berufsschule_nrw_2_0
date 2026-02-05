<?php
use Kai\MhbBackend20\Auth\Controllers\OAuthController;
use Kai\MhbBackend20\Database\Controllers\TicketController;
use Kai\MhbBackend20\Database\Controllers\DocumentController;
use Kai\MhbBackend20\Database\Controllers\FavoriteController;
use Kai\MhbBackend20\Database\Controllers\AliasController;
use Kai\MhbBackend20\Graph\Controllers\GraphSyncController;
use Kai\MhbBackend20\Graph\Controllers\RaumbuchungsUebersichtController;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware; // Für Profil-Quick-Check

return [
    'static' => [
        // OAuth
        'oauth/login'              => ['GET'  => [OAuthController::class, 'redirectToOAuth']],
        'oauth/callback'           => ['GET'  => [OAuthController::class, 'handleCallback']],
        'oauth/logout'             => ['GET'  => [OAuthController::class, 'logout']],
        'api/me'             => ['GET'  => [OAuthController::class, 'getCurrentUser']],
        
        // User & Profil
        'api/user/profile'         => ['GET'  => [TicketController::class, 'getCurrentUserProfile']], // Oder ähnliche Methode

        // Favoriten (Jetzt ohne ID in der URL -> Sicherer!)
        'api/favorites'            => [
            'GET'    => [FavoriteController::class, 'getFavorites'],
            'POST'   => [FavoriteController::class, 'addFavorite'],
            'DELETE' => [FavoriteController::class, 'removeFavorite']
        ],
        
        // Suche & Aliasse
        'api/aliases'      => ['POST' => [AliasController::class, 'addAlias']],
        'api/aliases/vote' => ['POST' => [AliasController::class, 'toggleVote']],
        
        // Raumplanung & Sync
        'api/rooms/bookings'       => ['GET'  => [RaumbuchungsUebersichtController::class, 'getOverview']],
        'api/sync/get-permissions' => ['GET'  => [GraphSyncController::class, 'getPermissions']],
        
        // Tickets Basis
        'api/tickets'              => [
            'GET'  => [TicketController::class, 'getAll'],
            'POST' => [TicketController::class, 'createTicket']
        ],
        
        // Ticket Aktionen (Wieder hinzugefügt)
        'api/tickets/comment'      => ['POST' => [TicketController::class, 'addComment']],
        'api/tickets/update-field' => ['POST' => [TicketController::class, 'updateField']],
        'api/tickets/subscribe'    => ['POST' => [TicketController::class, 'toggleSubscription']],
        'api/tickets/resolve'      => ['POST' => [TicketController::class, 'resolveTicket']],
        'api/tickets/cleanup'      => ['POST' => [TicketController::class, 'cleanupOldTickets']],
    ],

    'dynamic' => [
        // Dokumente & Suche
        '#^api/documents/([^/]+)$#'      => ['GET' => [DocumentController::class, 'getByScope', 1]],
        '#^api/aliases/([^/]+)/(\d+)$#' => ['GET' => [AliasController::class, 'getAliases', 1, 2]],
        '#^api/favorites/([^/]+)$#'      => ['GET' => [FavoriteController::class,'getFavorites']],


        // Tickets & Dashboard
        '#^api/tickets/user/(\d+)$#'     => ['GET' => [TicketController::class, 'getByUser', 1]],
        '#^api/tickets/detail/(\d+)$#'   => ['GET' => [TicketController::class, 'getDetail', 1]],
        '#^api/tickets/subscribe-room$#' => ['POST' => [TicketController::class, 'toggleRoomSubscription']],
        '#^api/tickets/subscribe-room/(\d+)$#' => ['GET'  => [TicketController::class, 'getRoomSubscriptions']],

        // Synchronisation
        '#^api/sync/execute/([^/]+)$#'   => ['POST' => [GraphSyncController::class, 'executeSync', 1]],
    ]
];