<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Master registration invite (optional)
    |--------------------------------------------------------------------------
    |
    | Bootstrap / emergency secret. When set, /register?invite=THIS works and
    | assigns invite_role. Leave empty locally. Prefer Admin → Invites for
    | one-time links. Set manually in .env if a master secret is needed.
    |
    */
    'invite' => env('REGISTRATION_INVITE'),

    /*
    |--------------------------------------------------------------------------
    | Role for master-invite registrations
    |--------------------------------------------------------------------------
    */
    'invite_role' => env('REGISTRATION_INVITE_ROLE', 'admin'),
];
