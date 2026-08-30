<?php

return [
    'people' => [
        'controller' => 'people/member',
        'action' => 'index',
        'public' => true
    ],

    'people/admin/member' => [
        'controller' => 'people/admin/member',
        'action' => 'index',
        'params' => ['member_id']
    ],

    'people/admin/member/edit' => [
        'controller' => 'people/admin/member',
        'action' => 'edit',
        'params' => ['member_id']
    ],

    'people/admin/member/view' => [
        'controller' => 'people/admin/member',
        'action' => 'view',
        'params' => ['member_id']
    ],

    'people/admin/member/ajax' => [
        'controller' => 'people/admin/member',
        'action' => 'ajax',
        'params' => ['name', 'gender']
    ],

    'people/admin/member/tree' => [
        'controller' => 'people/admin/member',
        'action' => 'tree',
        'params' => ['member_id']
    ],
];