<?php

use System\Engine\Controller;

class PeopleMember extends Controller {
    public function indexAction() {
        $member_id = $this->request->get('member_id', 'int', 0);
        if (!empty($member_id)) {
            
        }
    }
}