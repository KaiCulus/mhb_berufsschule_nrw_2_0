<?php

namespace Kai\MhbBackend20\Database\Controllers;

use Kai\MhbBackend20\Common\BaseController;
use Kai\MhbBackend20\Auth\Middleware\AuthMiddleware;

/**
 * UserController
 *
 * User-bezogene Endpunkte die nichts mit Tickets zu tun haben.
 *
 * getProfile() lag vorher im TicketController — dort war es fachlich
 * fehl am Platz und hat die Zuständigkeit des Controllers verwässert.
 */
class UserController extends BaseController
{
    /**
     * GET api/user/profile
     *
     * Profil des aktuellen Users (Name, E-Mail, Gruppen).
     * Wird in der Navigation und auf der Profilseite verwendet.
     */
    public function getProfile(): void
    {
        $user = AuthMiddleware::check();

        $this->jsonResponse([
            'id'     => $user['id'],
            'name'   => $user['name'],
            'email'  => $user['email'],
            'groups' => $user['groups'] ?? [],
        ]);
    }
}