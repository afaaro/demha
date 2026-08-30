<?php
require_once __DIR__ . '/maincore.php';

$action = new System\Engine\Action($registry);
$registry->set('action', $action);
$action->execute();