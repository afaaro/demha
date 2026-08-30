<?php

return [
    'label' => 'Dacwah',
    'icon'  => 'bi-people',
    'children' => [
        [
            'label'   => 'Scholars',
            'icon'    => 'bi-person-badge',
            'url'     => 'dacwah/admin/scholar',
        ],
        [
            'label'   => 'Categories',
            'icon'    => 'bi-folder',
            'url'     => 'dacwah/admin/category',
        ],
        [
            'label'   => 'Series',
            'icon'    => 'bi-collection-play',
            'url'     => 'dacwah/admin/series',
        ],
        [
            'label'   => 'Lectures',
            'icon'    => 'bi-mic',
            'url'     => 'dacwah/admin/lecture',
        ],
        [
            'label'   => 'Library',
            'icon'    => 'bi-book',
            'children' => [
                ['label' => 'Books',    'url' => 'dacwah/admin/book',    'icon' => 'bi-book-half'],
                ['label' => 'Articles', 'url' => 'dacwah/admin/article', 'icon' => 'bi-file-text'],
            ],
        ],
    ]
];