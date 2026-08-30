<?php

return [
    'tools/admin/database'  => [
        'controller' => 'tools/admin/database',
        'action' => 'index',
        'auth' => true,
        'permission' => 'tools.admin.database.manage',
    ],

    'tools/admin/database/tables' => [
        'controller' => 'tools/admin/database',
        'action' => 'tables',
        'auth' => true,
        'permission' => 'tools.admin.database.manage',
    ],

    'tools/admin/database/optimize' => [
        'controller' => 'tools/admin/database',
        'action' => 'optimize',
        'auth' => true,
        'permission' => 'tools.admin.database.manage',
    ],

    'tools/admin/database/export' => [
        'controller' => 'tools/admin/database',
        'action' => 'export',
        'auth' => true,
        'permission' => 'tools.admin.database.manage',
    ],

    'tools/admin/logs' => [
        'controller' => 'tools/admin/logs',
        'action' => 'index',
        'auth' => true,
        'permission' => 'tools.admin.database.manage',
    ],
    'tools/admin/logs/clear' => [
        'controller' => 'tools/admin/logs',
        'action' => 'clear',
        'auth' => true,
        'permission' => 'tools.admin.database.manage',
    ],

    'tools/admin/module' => [
        'controller' => 'tools/admin/module',
        'action' => 'index',
        'auth' => true,
        'permission' => 'tools.admin.module.manage',
    ],
    'tools/admin/module/install' => [
        'controller' => 'tools/admin/module',
        'action' => 'install',
        'auth' => true,
        'permission' => 'tools.admin.module.install',
    ],
    'tools/admin/module/uninstall' => [
        'controller' => 'tools/admin/module',
        'action' => 'uninstall',
        'auth' => true,
        'permission' => 'tools.admin.module.uninstall',
    ],
    'tools/admin/module/upgrade' => [
        'controller' => 'tools/admin/module',
        'action' => 'upgrade',
        'auth' => true,
        'permission' => 'tools.admin.module.upgrade',
    ],
    'tools/admin/block' => [
        'controller' => 'tools/admin/block',
        'action' => 'index',
        'auth' => true,
        'permission' => 'tools.admin.block.manage',
    ],
    'tools/admin/block/create' => [
        'controller' => 'tools/admin/block',
        'action' => 'create',
        'auth' => true,
        'permission' => 'tools.admin.block.create',
    ],
    'tools/admin/block/edit' => [
        'controller' => 'tools/admin/block',
        'action' => 'edit',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'tools.admin.block.edit',
    ],
    'tools/admin/block/delete' => [
        'controller' => 'tools/admin/block',
        'action' => 'delete',
        'params' => ['id'],
        'auth' => true,
        'permission' => 'tools.admin.block.delete',
    ],

    // Admin Media routes
    'tools/admin/media' => [
        'controller' => 'tools/admin/media',
        'action'     => 'index',
    ],
    'tools/admin/media/upload' => [
        'controller' => 'tools/admin/media',
        'action'     => 'upload',
        'params'     => ['folder'],
    ],
    'tools/admin/media/folder-create' => [
        'controller' => 'tools/admin/media',
        'action'     => 'folderCreate',
    ],
    'tools/admin/media/folder-rename' => [
        'controller' => 'tools/admin/media',
        'action'     => 'folderRename',
    ],
    'tools/admin/media/folder-delete' => [
        'controller' => 'tools/admin/media',
        'action'     => 'folderDelete',
    ],
    'tools/admin/media/file-rename' => [
        'controller' => 'tools/admin/media',
        'action'     => 'fileRename',
    ],
    'tools/admin/media/file-delete' => [
        'controller' => 'tools/admin/media',
        'action'     => 'fileDelete',
    ],
    'tools/admin/media/picker' => [
        'controller' => 'tools/admin/media',
        'action'     => 'picker',
        'params'     => ['folder'],
    ],
];
