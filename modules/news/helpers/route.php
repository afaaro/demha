<?php

return [
    // Frontend
    'news' => [
        'controller' => 'news/home',
        'action' => 'index',
        'public' => true,
    ],
    'news/category' => [
        'controller' => 'news/home',
        'action' => 'category',
        'params' => ['slug'],
        'public' => true,
    ],
    'news/article' => [
        'controller' => 'news/article',
        'action' => 'index',
        'params' => ['slug'],
        'public' => true,
    ],

    // Admin - Categories
    'news/admin/category' => [
        'controller' => 'news/admin/category',
        'action' => 'index',
        'params' => ['page'],
    ],
    'news/admin/category/create' => [
        'controller' => 'news/admin/category',
        'action' => 'create',
    ],
    'news/admin/category/edit' => [
        'controller' => 'news/admin/category',
        'action' => 'edit',
        'params' => ['id'],
    ],
    'news/admin/category/delete' => [
        'controller' => 'news/admin/category',
        'action' => 'delete',
        'params' => ['id'],
    ],

    // Admin - Tags
    'news/admin/tag' => [
        'controller' => 'news/admin/tag',
        'action' => 'index',
        'params' => ['page'],
    ],
    'news/admin/tag/create' => [
        'controller' => 'news/admin/tag',
        'action' => 'create',
    ],
    'news/admin/tag/edit' => [
        'controller' => 'news/admin/tag',
        'action' => 'edit',
        'params' => ['id'],
    ],
    'news/admin/tag/delete' => [
        'controller' => 'news/admin/tag',
        'action' => 'delete',
        'params' => ['id'],
    ],

    // Admin - News Articles
    'news/admin/article' => [
        'controller' => 'news/admin/article',
        'action' => 'index',
        'params' => ['page', 'status', 'category'],
    ],
    'news/admin/article/create' => [
        'controller' => 'news/admin/article',
        'action' => 'create',
    ],
    'news/admin/article/edit' => [
        'controller' => 'news/admin/article',
        'action' => 'edit',
        'params' => ['id'],
    ],
    'news/admin/article/delete' => [
        'controller' => 'news/admin/article',
        'action' => 'delete',
        'params' => ['id'],
    ],
];