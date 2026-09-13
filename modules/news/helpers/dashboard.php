<?php
return [
    'news_published' => [
        'label'    => '📰 Published News',
        'value'    => '__DB_COUNT__#__news WHERE status = 1',
        'color'    => 'text-success',
        'priority' => 10,
    ],
    'news_drafts' => [
        'label'    => '📝 Drafts',
        'value'    => '__DB_COUNT__#__news WHERE status != 1',
        'color'    => 'text-warning',
        'priority' => 11,
    ],
];