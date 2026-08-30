<?php

return [
    'user' => [
        'controller' => 'user/home',
        'action' => 'index'
    ],
    'user/login' => [
        'controller' => 'user/account',
        'action' => 'login',
        'guest' => true,
    ],
    'user/register' => [
        'controller' => 'user/account',
        'action' => 'register',
        'guest' => true,
    ],
    'user/logout' => [
        'controller' => 'user/account',
        'action' => 'logout',
        'auth' => true,
        'permission' => 'user.auth.logout',
    ],
    'user/account' => [
        'controller' => 'user/account',
        'action' => 'index',
        'auth' => true,
        'permission' => 'user.account.view',
    ],
    'user/account/edit' => [
        'controller' => 'user/account',
        'action' => 'edit',
        'auth' => true,
        'permission' => 'user.account.edit',
    ],
    'user/account/password' => [
        'controller' => 'user/account',
        'action' => 'password',
        'auth' => true,
        'permission' => 'user.account.password',
    ],

    // Groups + permissions admin
    'user/admin/role' => [
        'controller' => 'user/admin/role',
        'action' => 'index',
        'auth' => true,
        'permission' => 'user.admin.role.access',
    ],
    'user/admin/role/create' => [
        'controller' => 'user/admin/role',
        'action' => 'create',
        'auth' => true,
        'permission' => 'user.admin.role.create',
    ],
    'user/admin/role/edit' => [
        'controller' => 'user/admin/role',
        'action' => 'edit',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'user.admin.role.edit',
    ],
    'user/admin/role/delete' => [
        'controller' => 'user/admin/role',
        'action' => 'delete',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'user.admin.role.delete',
    ],
    'user/admin/permission' => [
        'controller' => 'user/admin/permission',
        'action' => 'index',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'user.admin.permission.manage',
    ],
    'user/admin/role/user/assign' => [
        'controller' => 'user/admin/role',
        'action' => 'userAssign',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'user.admin.role.assignments.manage',
    ],

    // User account management admin
    'user/admin/account' => [
        'controller' => 'user/admin/account',
        'action' => 'index',
        'auth' => true,
        'permission' => 'user.admin.account.manage',
    ],
    'user/admin/account/create' => [
        'controller' => 'user/admin/account',
        'action' => 'create',
        'auth' => true,
        'permission' => 'user.admin.account.create',
    ],
    'user/admin/account/edit' => [
        'controller' => 'user/admin/account',
        'action' => 'edit',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'user.admin.account.edit',
    ],
    'user/admin/account/delete' => [
        'controller' => 'user/admin/account',
        'action' => 'delete',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'user.admin.account.delete',
    ],
];