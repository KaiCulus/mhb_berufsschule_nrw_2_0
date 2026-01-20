<?php
return [
    'scopes' => ['openid', 'profile', 'email', 'User.Read'],
    'tenant_id' => $_ENV['MHB_BE_MSAL_TENANT_ID'],
    'client_id' => $_ENV['MHB_BE_MSAL_CLIENT_ID'],
    'redirect_uri' => $_ENV['MHB_BE_MSAL_REDIRECT_URI'],
];
