<?php

return [
    // ========== ADMIN ROUTES ==========

    // Scholars
    'dacwah/admin/scholar' => [
        'controller' => 'dacwah/admin/scholar',
        'action'     => 'index',
    ],
    'dacwah/admin/scholar/create' => [
        'controller' => 'dacwah/admin/scholar',
        'action'     => 'create',
    ],
    'dacwah/admin/scholar/edit' => [
        'controller' => 'dacwah/admin/scholar',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'dacwah/admin/scholar/store' => [
        'controller' => 'dacwah/admin/scholar',
        'action'     => 'store',
    ],
    'dacwah/admin/scholar/update' => [
        'controller' => 'dacwah/admin/scholar',
        'action'     => 'update',
        'params'     => ['id'],
    ],
    'dacwah/admin/scholar/delete' => [
        'controller' => 'dacwah/admin/scholar',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    // Categories
    'dacwah/admin/category' => [
        'controller' => 'dacwah/admin/category',
        'action'     => 'index',
    ],
    'dacwah/admin/category/create' => [
        'controller' => 'dacwah/admin/category',
        'action'     => 'create',
    ],
    'dacwah/admin/category/edit' => [
        'controller' => 'dacwah/admin/category',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'dacwah/admin/category/store' => [
        'controller' => 'dacwah/admin/category',
        'action'     => 'store',
    ],
    'dacwah/admin/category/update' => [
        'controller' => 'dacwah/admin/category',
        'action'     => 'update',
        'params'     => ['id'],
    ],
    'dacwah/admin/category/delete' => [
        'controller' => 'dacwah/admin/category',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    // Series
    'dacwah/admin/series' => [
        'controller' => 'dacwah/admin/series',
        'action'     => 'index',
    ],
    'dacwah/admin/series/create' => [
        'controller' => 'dacwah/admin/series',
        'action'     => 'create',
    ],
    'dacwah/admin/series/edit' => [
        'controller' => 'dacwah/admin/series',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'dacwah/admin/series/store' => [
        'controller' => 'dacwah/admin/series',
        'action'     => 'store',
    ],
    'dacwah/admin/series/update' => [
        'controller' => 'dacwah/admin/series',
        'action'     => 'update',
        'params'     => ['id'],
    ],
    'dacwah/admin/series/delete' => [
        'controller' => 'dacwah/admin/series',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    // Lectures
    'dacwah/admin/lecture' => [
        'controller' => 'dacwah/admin/lecture',
        'action'     => 'index',
    ],
    'dacwah/admin/lecture/create' => [
        'controller' => 'dacwah/admin/lecture',
        'action'     => 'create',
    ],
    'dacwah/admin/lecture/edit' => [
        'controller' => 'dacwah/admin/lecture',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'dacwah/admin/lecture/store' => [
        'controller' => 'dacwah/admin/lecture',
        'action'     => 'store',
    ],
    'dacwah/admin/lecture/update' => [
        'controller' => 'dacwah/admin/lecture',
        'action'     => 'update',
        'params'     => ['id'],
    ],
    'dacwah/admin/lecture/delete' => [
        'controller' => 'dacwah/admin/lecture',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    // Books
    'dacwah/admin/book' => [
        'controller' => 'dacwah/admin/book',
        'action'     => 'index',
    ],
    'dacwah/admin/book/create' => [
        'controller' => 'dacwah/admin/book',
        'action'     => 'create',
    ],
    'dacwah/admin/book/edit' => [
        'controller' => 'dacwah/admin/book',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'dacwah/admin/book/store' => [
        'controller' => 'dacwah/admin/book',
        'action'     => 'store',
    ],
    'dacwah/admin/book/update' => [
        'controller' => 'dacwah/admin/book',
        'action'     => 'update',
        'params'     => ['id'],
    ],
    'dacwah/admin/book/delete' => [
        'controller' => 'dacwah/admin/book',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    // Articles
    'dacwah/admin/article' => [
        'controller' => 'dacwah/admin/article',
        'action'     => 'index',
    ],
    'dacwah/admin/article/create' => [
        'controller' => 'dacwah/admin/article',
        'action'     => 'create',
    ],
    'dacwah/admin/article/edit' => [
        'controller' => 'dacwah/admin/article',
        'action'     => 'edit',
        'params'     => ['id'],
    ],
    'dacwah/admin/article/store' => [
        'controller' => 'dacwah/admin/article',
        'action'     => 'store',
    ],
    'dacwah/admin/article/update' => [
        'controller' => 'dacwah/admin/article',
        'action'     => 'update',
        'params'     => ['id'],
    ],
    'dacwah/admin/article/delete' => [
        'controller' => 'dacwah/admin/article',
        'action'     => 'delete',
        'params'     => ['id'],
    ],

    // ========== PUBLIC FRONTEND ROUTES ==========

    'dacwah' => [
        'controller' => 'dacwah/home',
        'action'     => 'index',
    ],

    // Lectures
    'dacwah/lectures' => [
        'controller' => 'dacwah/lecture',
        'action'     => 'index',
    ],
    'dacwah/lecture' => [
        'controller' => 'dacwah/lecture',
        'action'     => 'view',
        'params'     => ['slug'],
    ],

    // Series
    'dacwah/series' => [
        'controller' => 'dacwah/series',
        'action'     => 'index',
    ],
    'dacwah/series/view' => [
        'controller' => 'dacwah/series',
        'action'     => 'view',
        'params'     => ['slug'],
    ],

    // Scholars
    'dacwah/scholars' => [
        'controller' => 'dacwah/scholar',
        'action'     => 'index',
    ],
    'dacwah/scholar/view' => [
        'controller' => 'dacwah/scholar',
        'action'     => 'view',
        'params'     => ['slug'],
    ],

    // Categories
    'dacwah/categories' => [
        'controller' => 'dacwah/category',
        'action'     => 'index',
    ],
    'dacwah/category' => [
        'controller' => 'dacwah/category',
        'action'     => 'view',
        'params'     => ['slug'],
    ],

    // Books
    'dacwah/books' => [
        'controller' => 'dacwah/book',
        'action'     => 'index',
    ],
    'dacwah/book' => [
        'controller' => 'dacwah/book',
        'action'     => 'view',
        'params'     => ['slug'],
    ],

    // Articles
    'dacwah/articles' => [
        'controller' => 'dacwah/article',
        'action'     => 'index',
    ],
    'dacwah/article' => [
        'controller' => 'dacwah/article',
        'action'     => 'view',
        'params'     => ['slug'],
    ],
];