<?php

$cookiePath = '/' . trim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$cookiePath = $cookiePath === '//' ? '/' : $cookiePath;
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);

return [
    'name'              => 'PHPSESSID',
    'gc_probability'    => 1,
    'gc_divisor'        => 100,
    'cookie_lifetime'   => 86400,
    'cookie_path'       => $cookiePath,
    'cookie_domain'     => '',
    'cookie_secure'     => $isHttps,
    'cookie_httponly'   => true,
    'cookie_samesite'   => 'Lax',
    'use_cookies'       => true,
    'use_only_cookies'  => true,
    'use_strict_mode'   => true,
    'use_trans_sid'     => false,
];
