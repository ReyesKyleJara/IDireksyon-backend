<?php

return [

    /*
    | Host names this app answers to. A request for any other host gets a 400,
    | so a forged host can never end up inside a link the app generates.
    |
    | The up.railway.app host is the one the mobile app calls: removing it
    | would disconnect every installed copy. Use TRUSTED_HOSTS (comma separated)
    | to add a host without a code change.
    */
    'trusted_hosts' => array_values(array_unique(array_filter([
        'www.idireksyon.online',
        'idireksyon.online',
        'idireksyon-backend-production.up.railway.app',
        env('RAILWAY_PUBLIC_DOMAIN'),
        parse_url((string) env('APP_URL'), PHP_URL_HOST) ?: null,
        ...array_map('trim', explode(',', (string) env('TRUSTED_HOSTS', ''))),
    ]))),

    // Starts at one day. Raise it once HTTPS has proven stable. 0 turns it off.
    'hsts_max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 86400),

];
