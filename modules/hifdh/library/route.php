<?php
return [
    // Dashboard
    'hifdh/admin/dashboard' => ['controller' => 'hifdh/admin/dashboard', 'action' => 'index'],

    // Teachers
    'hifdh/admin/teacher'         => ['controller' => 'hifdh/admin/teacher', 'action' => 'index'],
    'hifdh/admin/teacher/create'  => ['controller' => 'hifdh/admin/teacher', 'action' => 'create'],
    'hifdh/admin/teacher/edit'    => ['controller' => 'hifdh/admin/teacher', 'action' => 'edit', 'params' => ['id']],

    // Students
    'hifdh/admin/student'         => ['controller' => 'hifdh/admin/student', 'action' => 'index'],
    'hifdh/admin/student/create'  => ['controller' => 'hifdh/admin/student', 'action' => 'create'],
    'hifdh/admin/student/edit'    => ['controller' => 'hifdh/admin/student', 'action' => 'edit', 'params' => ['id']],

    // Progress
    'hifdh/admin/progress'         => ['controller' => 'hifdh/admin/progress', 'action' => 'index', 'params' => ['student_id']],
    'hifdh/admin/progress/add'     => ['controller' => 'hifdh/admin/progress', 'action' => 'add'],
    'hifdh/admin/progress/edit'    => ['controller' => 'hifdh/admin/progress', 'action' => 'edit', 'params' => ['id']],
    'hifdh/admin/progress/delete'  => ['controller' => 'hifdh/admin/progress', 'action' => 'delete', 'params' => ['id']],
    'hifdh/admin/progress/report'  => ['controller' => 'hifdh/admin/progress', 'action' => 'report','params' => ['student_id']],

    // Surah Completion Tracker
    'hifdh/admin/surahs' => ['controller' => 'hifdh/admin/surahs', 'action' => 'index', 'params' => ['student_id']],
];