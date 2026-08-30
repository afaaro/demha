<?php

return [
    'session_key' => 'auth.user_id',
    'default_group' => 'member',
    'super_group' => 'super_admin',

    'login_route' => 'user/login',
    'post_login_route' => 'user/account',
    'post_logout_route' => 'user/login',

    'auto_install' => true,

    // Change these credentials immediately in production.
    'seed_super_admin' => [
        'username' => getenv('AUTH_ADMIN_USER') ?: 'admin',
        'email' => getenv('AUTH_ADMIN_EMAIL') ?: 'admin@example.com',
        'password' => getenv('AUTH_ADMIN_PASS') ?: 'admin123456',
        'first_name' => 'System',
        'last_name' => 'Admin',
    ],
];
