<?php

return [
    'tools/admin/setting' => [
        'controller' => 'tools/admin/setting',
        'action' => 'index',
        'auth' => true,
        'permission' => 'tools.admin.setting.index',
    ],
    'tools/admin/setting/create' => [
        'controller' => 'tools/admin/setting',
        'action'     => 'create',
        'auth' => true,
        'permission' => 'tools.admin.setting.create',
    ],
    'tools/admin/setting/edit' => [
        'controller' => 'tools/admin/setting',
        'action'     => 'edit',
        'params'     => ['id'],
        'auth' => true,
        'permission' => 'tools.admin.setting.edit',
    ],
    'tools/admin/setting/delete' => [
        'controller' => 'tools/admin/setting',
        'action'     => 'delete',
        'params'     => ['id'],
        'auth' => true,
        'permission' => 'tools.admin.setting.delete',
    ],
    'tools/admin/database'  => [
        'controller' => 'tools/admin/database',
        'action' => 'index',
        'auth' => true,
        'permission' => 'tools.admin.database.index',
    ],

    'tools/admin/database/tables' => [
        'controller' => 'tools/admin/database',
        'action' => 'tables',
        'auth' => true,
        'permission' => 'tools.admin.database.index',
    ],

    'tools/admin/database/optimize' => [
        'controller' => 'tools/admin/database',
        'action' => 'optimize',
        'auth' => true,
        'permission' => 'tools.admin.database.index',
    ],

    'tools/admin/database/export' => [
        'controller' => 'tools/admin/database',
        'action' => 'export',
        'auth' => true,
        'permission' => 'tools.admin.database.index',
    ],

    'tools/admin/logs' => [
        'controller' => 'tools/admin/logs',
        'action' => 'index',
        'auth' => true,
        'permission' => 'tools.admin.logs.index',
    ],
    'tools/admin/logs/clear' => [
        'controller' => 'tools/admin/logs',
        'action' => 'clear',
        'auth' => true,
        'permission' => 'tools.admin.logs.clear',
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

    // Blocks
    'tools/admin/block' => [
        'controller' => 'tools/admin/block',
        'action'     => 'index',
        'auth' => true,
    ],
    'tools/admin/block/create' => [
        'controller' => 'tools/admin/block',
        'action'     => 'create',
        'auth' => true,
    ],
    'tools/admin/block/edit' => [
        'controller' => 'tools/admin/block',
        'action'     => 'edit',
        'params'     => ['id'],
        'auth' => true,
    ],
    'tools/admin/block/delete' => [
        'controller' => 'tools/admin/block',
        'action'     => 'delete',
        'params'     => ['id'],
        'auth' => true,
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
