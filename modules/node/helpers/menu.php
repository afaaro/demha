<?php

return [
    'label' => 'Content',
    'icon'  => 'bi-book',
    'children' => [
        ['label' => 'All Content', 'url' => 'node/admin/post', 'icon' => 'bi-file-text'],
        ['label' => 'Content Types', 'url' => 'node/admin/bundle', 'icon' => 'bi-boxes'],
        ['label' => 'Taxonomies', 'url' => 'node/admin/taxonomy', 'icon' => 'bi-tags'],
        ['label' => 'Fields', 'url' => 'node/admin/field', 'icon' => 'bi-grid', 'hidden' => true],
    ]
];