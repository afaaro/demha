<?php

use System\Engine\Controller;
use System\Library\Notify;

class UserAccount extends Controller {
    public function indexAction() {
        $user = $this->auth->user();
        if (!$user) {
            redirect_to('user/login');
        }
        $auth = $this->auth;
        echo $this->view->inline(static function ($view) use ($auth, $user): void {
            echo '<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-4">';
            echo '<div><h1 class="h3 mb-1">My Account</h1><p class="text-muted mb-0">View your profile details and manage your access.</p></div>';
            echo '<div class="d-flex gap-2"><a class="btn btn-outline-primary btn-sm" href="' . route_url('user/account/edit') . '">Edit account</a><a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/account/password') . '">Change password</a></div>';
            echo '</div>';

            echo '<div class="row g-4">';
            echo '<div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Profile</h2><div class="row g-3">';
            echo '<div class="col-md-6"><div class="text-muted small">Username</div><div class="fw-semibold">' . $view->e((string) ($user['username'] ?? '')) . '</div></div>';
            echo '<div class="col-md-6"><div class="text-muted small">Email</div><div class="fw-semibold">' . $view->e((string) ($user['email'] ?? '')) . '</div></div>';
            echo '<div class="col-12"><div class="text-muted small">Name</div><div class="fw-semibold">' . $view->e(trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')))) . '</div></div>';
            echo '</div></div></div></div>';

            echo '<div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Quick access</h2><div class="d-grid gap-2">';
            echo '<a class="btn btn-outline-primary" href="' . route_url('user/account/edit') . '">Edit account</a>';
            echo '<a class="btn btn-outline-secondary" href="' . route_url('user/account/password') . '">Change password</a>';
            echo '<a class="btn btn-outline-dark" href="' . route_url('user/logout') . '">Logout</a>';
            if ($auth->can('admin.account.view')) {
                echo '<a class="btn btn-primary" href="' . route_url('user/admin/role') . '">Open RBAC Admin</a>';
            }
            echo '</div></div></div></div>';
            echo '</div>';

            echo '<div class="row g-4 mt-1">';
            echo '<div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Groups</h2><ul class="mb-0">';
            foreach ($auth->groups() as $group) {
                echo '<li>' . $view->e((string) ($group['label'] ?? $group['name'] ?? '')) . ' <span class="text-muted">(' . $view->e((string) ($group['name'] ?? '')) . ')</span></li>';
            }
            echo '</ul></div></div></div>';

            echo '<div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body"><h2 class="h5 mb-3">Permissions</h2><ul class="mb-0">';
            foreach ($auth->permissions() as $permission) {
                echo '<li>' . $view->e((string) $permission) . '</li>';
            }
            echo '</ul></div></div></div>';
            echo '</div>';
        }, 'main');
    }
    
    public function editAction() {
        $user = $this->auth->user();
        if (!$user) {
            redirect_to('user/login');
        }

        $errors = [];
        $fieldErrors = [];
        if ($this->request->isPost()) {
            if (!$this->form->checkToken(null, 'account_edit_form')) {
                $errors[] = 'Invalid security token.';
            }

            $fieldErrors = $this->form->validate(
                $this->request->post(null, 'raw', []),
                [
                    'email' => 'required|email|max:190|unique:users,email,' . (int) $user['id'] . ',id',
                    'first_name' => 'max:100',
                    'last_name' => 'max:100',
                ]
            );

            if (empty($errors) && empty($fieldErrors)) {
                $email = strtolower(trim((string) $this->request->post('email', 'raw', '')));
                $firstName = trim((string) $this->request->post('first_name', 'raw', ''));
                $lastName = trim((string) $this->request->post('last_name', 'raw', ''));

                $this->db->update('users', [
                    'email' => $email,
                    'first_name' => $firstName !== '' ? $firstName : null,
                    'last_name' => $lastName !== '' ? $lastName : null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => (int) $user['id']]);

                redirect_to('user/account');
            }
        }

        $freshUser = $this->db->findOne('users', (int) $user['id']);
        $this->form->fill($freshUser);
        $this->form->setErrors($fieldErrors);

        $form = $this->form;
        echo $this->view->inline(static function ($view) use ($form, $errors): void {
            echo '<div class="d-flex align-items-center justify-content-between gap-2 mb-4">';
            echo '<div><h1 class="h3 mb-1">Edit Account</h1><p class="text-muted mb-0">Update your profile details and keep your contact information current.</p></div>';
            echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/account') . '">Back</a></div>';
            if (!empty($errors)) {
                echo '<div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2">';
                foreach ($errors as $error) {
                    echo '<li>' . $view->e((string) $error) . '</li>';
                }
                echo '</ul></div>';
            }
            echo $form->start([
                'id' => 'account_edit_form',
                'method' => 'POST',
                'action' => route_url('user/account/edit'),
            ]);
            echo '<div class="row g-3">';
            echo '<div class="col-md-6">' . $form->input('email', ['type' => 'email', 'label' => 'Email', 'required' => true, 'placeholder' => 'you@example.com']) . '</div>';
            echo '<div class="col-md-6">' . $form->input('first_name', ['label' => 'First Name']) . '</div>';
            echo '<div class="col-md-6">' . $form->input('last_name', ['label' => 'Last Name']) . '</div>';
            echo '</div>';
            echo '<div class="d-flex gap-2 mt-4">' . $form->submit('Save', ['class' => 'btn btn-primary']) . ' <a class="btn btn-outline-secondary" href="' . route_url('user/account') . '">Cancel</a></div>';
            echo $form->end();
        }, 'main');
    }

    public function loginAction(): void
    {
        if ($this->auth->check()) {
            redirect_to((string) $this->config->get('auth.post_login_route', 'user/account'));
        }

        $next = trim((string) $this->request->get('next', 'raw', ''));
        if ($next === '' && isset($_SERVER['HTTP_REFERER'])) {
            $referer = (string) $_SERVER['HTTP_REFERER'];
            if ($referer !== '' && !str_contains($referer, '/user/login') && !str_contains($referer, '/user/register')) {
                $next = $referer;
            }
        }

        $errors = [];
        if ($this->form->isValid()) {
            $data = $this->form->validated();
            debug($data);
            if ($this->auth->attempt($data['identity'] ?? '', $data['password'] ?? '',$this->request->ip())) {
                if ($next !== '') {
                    redirect_to($next);
                }

                redirect_to((string) $this->config->get('auth.post_login_route', 'user/account'));
            } else {
                $errors[] = 'Invalid username/email or password.';
            }
        }

        $form = $this->form;
        echo $this->view->inline(static function ($view) use ($form, $errors, $next): void {
            echo '<div class="row justify-content-center">';
            echo '<div class="col-lg-5"><div class="card border-0 shadow-sm"><div class="card-body p-4">';
            echo '<h1 class="h3 mb-1">Login</h1><p class="text-muted mb-4">Welcome back. Sign in to continue to your account.</p>';
            if (!empty($errors)) {
                echo '<div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2">';
                foreach ($errors as $error) {
                    echo '<li>' . $view->e((string) $error) . '</li>';
                }
                echo '</ul></div>';
            }
            echo $form->open();
            echo $form->input('identity', ['label' => 'Username or Email', 'required' => true, 'rules' => 'required|min:3|max:190']);
            echo $form->input('password', ['type' => 'password', 'label' => 'Password', 'required' => true, 'rules' => 'required|min:8|max:255']);
            echo '<div class="d-grid mt-3">' . $form->submit('Login', ['class' => 'btn btn-primary']) . '</div>';
            echo '<p class="mt-3 mb-0 text-center">No account? <a href="' . route_url('user/register') . '">Register here</a></p>';
            echo $form->end();
            echo '</div></div></div></div>';
        }, 'main');
    }

    public function registerAction(): void
    {
        if ($this->auth->check()) {
            redirect_to((string) $this->config->get('auth.post_login_route', 'user/account'));
        }

        $errors = [];
        $fieldErrors = [];
        if ($this->request->isPost()) {
            if (!$this->form->checkToken(null, 'register_form')) {
                $errors[] = 'Invalid security token.';
            }

            $fieldErrors = $this->form->validate(
                $this->request->post(null, 'raw', []),
                [
                    'username' => 'required|alpha_dash|min:3|max:100|unique:users,username',
                    'email' => 'required|email|max:190|unique:users,email',
                    'first_name' => 'max:100',
                    'last_name' => 'max:100',
                    'password' => 'required|min:8|max:255',
                    'password_confirm' => 'required|match:password',
                ]
            );

            if (empty($errors) && empty($fieldErrors)) {
                $password = (string) $this->request->post('password', 'raw', '');
                try {
                    $this->auth->register([
                        'username' => (string) $this->request->post('username', 'raw', ''),
                        'email' => (string) $this->request->post('email', 'raw', ''),
                        'password' => $password,
                        'first_name' => (string) $this->request->post('first_name', 'raw', ''),
                        'last_name' => (string) $this->request->post('last_name', 'raw', ''),
                    ]);

                    $this->auth->attempt(
                        (string) $this->request->post('username', 'raw', ''),
                        $password,
                        $this->request->ip()
                    );

                    redirect_to((string) $this->config->get('auth.post_login_route', 'user/account'));
                } catch (\Throwable $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        $this->form->setErrors($fieldErrors);

        $form = $this->form;
        echo $this->view->inline(static function ($view) use ($form, $errors): void {
            echo '<div class="row justify-content-center">';
            echo '<div class="col-lg-7"><div class="card border-0 shadow-sm"><div class="card-body p-4">';
            echo '<h1 class="h3 mb-1">Register</h1><p class="text-muted mb-4">Create your account and start using the platform.</p>';
            if (!empty($errors)) {
                echo '<div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2">';
                foreach ($errors as $error) {
                    echo '<li>' . $view->e((string) $error) . '</li>';
                }
                echo '</ul></div>';
            }
            echo $form->start([
                'id' => 'register_form',
                'method' => 'POST',
                'action' => route_url('user/register'),
            ]);
            echo '<div class="row g-3">';
            echo '<div class="col-md-6">' . $form->input('username', ['label' => 'Username', 'required' => true]) . '</div>';
            echo '<div class="col-md-6">' . $form->input('email', ['type' => 'email', 'label' => 'Email', 'required' => true]) . '</div>';
            echo '<div class="col-md-6">' . $form->input('first_name', ['label' => 'First Name']) . '</div>';
            echo '<div class="col-md-6">' . $form->input('last_name', ['label' => 'Last Name']) . '</div>';
            echo '<div class="col-md-6">' . $form->input('password', ['type' => 'password', 'label' => 'Password', 'required' => true]) . '</div>';
            echo '<div class="col-md-6">' . $form->input('password_confirm', ['type' => 'password', 'label' => 'Confirm Password', 'required' => true]) . '</div>';
            echo '</div>';
            echo '<div class="d-grid mt-4">' . $form->submit('Create Account', ['class' => 'btn btn-primary']) . '</div>';
            echo '<p class="mt-3 mb-0 text-center">Already registered? <a href="' . route_url('user/login') . '">Login</a></p>';
            echo $form->end();
            echo '</div></div></div></div>';
        }, 'main');
    }

    public function logoutAction(): void
    {
        $this->auth->logout();
        redirect_to((string) $this->config->get('auth.post_logout_route', 'user/login'));
    }

    public function passwordAction(): void
    {
        $user = $this->auth->user();
        if (!$user) {
            redirect_to('user/login');
        }

        $errors = [];
        $fieldErrors = [];
        if ($this->request->isPost()) {
            if (!$this->form->checkToken(null, 'account_password_form')) {
                $errors[] = 'Invalid security token.';
            }

            $fieldErrors = $this->form->validate(
                $this->request->post(null, 'raw', []),
                [
                    'current_password' => 'required',
                    'new_password' => 'required|min:8|max:255',
                    'new_password_confirm' => 'required|match:new_password',
                ]
            );

            if (!isset($fieldErrors['current_password']) && !password_verify((string) $this->request->post('current_password', 'raw', ''), (string) ($user['password_hash'] ?? ''))) {
                $fieldErrors['current_password'] = 'Current password is incorrect.';
            }

            if (empty($errors) && empty($fieldErrors)) {
                $this->db->update('users', [
                    'password_hash' => password_hash((string) $this->request->post('new_password', 'raw', ''), PASSWORD_DEFAULT),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], ['id' => (int) $user['id']]);

                redirect_to('user/account');
            }
        }

        $this->form->setErrors($fieldErrors);

        $form = $this->form;
        echo $this->view->inline(static function ($view) use ($form, $errors): void {
            echo '<div class="row justify-content-center">';
            echo '<div class="col-lg-6"><div class="card border-0 shadow-sm"><div class="card-body p-4">';
            echo '<div class="d-flex align-items-center justify-content-between gap-2 mb-4">';
            echo '<div><h1 class="h3 mb-1">Change Password</h1><p class="text-muted mb-0">Update your password to keep your account secure.</p></div>';
            echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/account') . '">Back</a></div>';
            if (!empty($errors)) {
                echo '<div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2">';
                foreach ($errors as $error) {
                    echo '<li>' . $view->e((string) $error) . '</li>';
                }
                echo '</ul></div>';
            }
            echo $form->start([
                'id' => 'account_password_form',
                'method' => 'POST',
                'action' => route_url('user/account/password'),
            ]);
            echo $form->input('current_password', ['type' => 'password', 'label' => 'Current Password', 'required' => true]);
            echo $form->input('new_password', ['type' => 'password', 'label' => 'New Password', 'required' => true]);
            echo $form->input('new_password_confirm', ['type' => 'password', 'label' => 'Confirm New Password', 'required' => true]);
            echo '<div class="d-flex gap-2 mt-4">' . $form->submit('Update Password', ['class' => 'btn btn-primary']) . ' <a class="btn btn-outline-secondary" href="' . route_url('user/account') . '">Cancel</a></div>';
            echo $form->end();
            echo '</div></div></div></div>';
        }, 'main');
    }
}