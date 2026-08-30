<?php

use System\Engine\Controller;

class UserHome extends Controller {
    public function indexAction() {
        $isLoggedIn = $this->auth->check();
        $user = $this->auth->user();

        echo $this->view->inline(static function ($view) use ($isLoggedIn, $user): void {
            echo '<h1>User Module</h1>';
            echo '<p>Authentication and RBAC demo area.</p>';

            if ($isLoggedIn) {
                echo '<p>Welcome, <strong>' . htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8') . '</strong></p>';
                echo '<ul>';
                echo '<li><a href="' . route_url('user/account') . '">My account</a></li>';
                echo '<li><a href="' . route_url('user/admin/role') . '">RBAC Admin</a></li>';
                echo '<li><a href="' . route_url('user/logout') . '">Logout</a></li>';
                echo '</ul>';
            } else {
                echo '<ul>';
                echo '<li><a href="' . route_url('user/login') . '">Login</a></li>';
                echo '<li><a href="' . route_url('user/register') . '">Register</a></li>';
                echo '</ul>';
            }
        }, 'main');
    }
}