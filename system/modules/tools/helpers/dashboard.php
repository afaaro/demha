<?php

// tools/helpers/dashboard.php
return [
    'php_version'     => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'database'        => 'MySQL',
    'server_time'     => date('Y-m-d H:i:s'),
    'memory_usage'    => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
    'max_memory'      => ini_get('memory_limit'),
];