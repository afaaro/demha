<?php

use System\Engine\Controller;

class UserAdminAccount extends Controller
{
	public function indexAction(): void
	{
		$users = $this->db->query(
			'SELECT id, username, email, first_name, last_name, status, created_at FROM #__users ORDER BY id DESC'
		)->rows;

		$currentUserId = (int) $this->auth->id();
		echo $this->view->inline(static function ($view) use ($users, $currentUserId): void {
			echo '<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-4">';
			echo '<div><h1 class="h3 mb-1">User Accounts</h1><p class="text-muted mb-0">Manage account status, identities, and access across the application.</p></div>';
			echo '<div class="d-flex gap-2">';
			echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/admin/role') . '">Back to RBAC</a>';
			echo '<a class="btn btn-primary btn-sm" href="' . route_url('user/admin/account/create') . '">Create User</a>';
			echo '</div></div>';

			echo '<div class="card border-0 shadow-sm">';
			echo '<div class="card-body p-0">';
			echo '<div class="table-responsive">';
			echo '<table class="table table-hover align-middle mb-0">';
			echo '<thead class="table-light"><tr><th>ID</th><th>Username</th><th>Email</th><th>Name</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>';

			if (empty($users)) {
				echo '<tr><td colspan="6" class="text-muted py-4">No users found.</td></tr>';
			} else {
				foreach ($users as $user) {
					$id = (int) ($user['id'] ?? 0);
					$status = ((int) ($user['status'] ?? 0) === 1) ? '<span class="badge text-bg-success">active</span>' : '<span class="badge text-bg-secondary">inactive</span>';
					echo '<tr>';
					echo '<td>' . $id . '</td>';
					echo '<td><strong>' . $view->e((string) ($user['username'] ?? '')) . '</strong></td>';
					echo '<td>' . $view->e((string) ($user['email'] ?? '')) . '</td>';
					echo '<td>' . $view->e(trim((string) (($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')))) . '</td>';
					echo '<td>' . $status . '</td>';
					echo '<td class="text-end"><div class="d-flex justify-content-end gap-2">';
					echo '<a class="btn btn-outline-primary btn-sm" href="' . route_url('user/admin/account/edit', ['id' => $id]) . '">Edit</a>';

					if ($id !== $currentUserId) {
						echo '<a class="btn btn-outline-danger btn-sm" href="' . route_url('user/admin/account/delete', ['id' => $id]) . '" onclick="return confirm(\'Delete this user?\')">Delete</a>';
					}

					echo '</div></td>';
					echo '</tr>';
				}
			}

			echo '</tbody></table>';
			echo '</div></div></div>';
		}, 'admin');
	}

	public function createAction(): void
	{
		$errors = [];
		$fieldErrors = [];

		if ($this->request->isPost()) {
			if (!$this->form->checkToken(null, 'admin_user_create_form')) {
				$errors[] = 'Invalid CSRF token.';
			}

			$post = $this->request->post(null, 'raw', []);
			$fieldErrors = $this->form->validate($post, [
				'username' => 'required|alpha_dash|min:3|max:100|unique:users,username',
				'email' => 'required|email|max:190|unique:users,email',
				'first_name' => 'max:100',
				'last_name' => 'max:100',
				'password' => 'required|min:8|max:255',
				'password_confirm' => 'required|match:password',
				'status' => 'required|in:0,1',
			]);

			if (empty($errors) && empty($fieldErrors)) {
				$username = trim((string) $this->request->post('username', 'raw', ''));
				$email = strtolower(trim((string) $this->request->post('email', 'raw', '')));
				$firstName = trim((string) $this->request->post('first_name', 'raw', ''));
				$lastName = trim((string) $this->request->post('last_name', 'raw', ''));
				$password = (string) $this->request->post('password', 'raw', '');
				$status = (int) $this->request->post('status', 'int', 1);

				$this->db->insert('users', [
					'username' => $username,
					'email' => $email,
					'password_hash' => password_hash($password, PASSWORD_DEFAULT),
					'first_name' => $firstName !== '' ? $firstName : null,
					'last_name' => $lastName !== '' ? $lastName : null,
					'status' => $status,
					'created_at' => date('Y-m-d H:i:s'),
				]);

				$userId = $this->db->insert_id();

				$groupIds = $this->request->post('groups', 'array', []);
				$groupIds = is_array($groupIds) ? array_map('intval', $groupIds) : [];
				$this->auth->syncUserGroups($userId, $groupIds);

				redirect_to('user/admin/account');
			}
		}

		$allGroups = $this->auth->allGroups();
		$this->form->setErrors($fieldErrors);

		$form = $this->form;
		echo $this->view->inline(static function ($view) use ($form, $allGroups, $errors): void {
			echo '<div class="d-flex align-items-center justify-content-between gap-2 mb-4">';
			echo '<div><h1 class="h3 mb-1">Create User</h1><p class="text-muted mb-0">Create a new account and assign one or more groups.</p></div>';
			echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/admin/account') . '">Back</a></div>';

			if (!empty($errors)) {
				echo '<div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2">';
				foreach ($errors as $error) {
					echo '<li>' . $view->e((string) $error) . '</li>';
				}
				echo '</ul></div>';
			}
			echo $form->start([
				'id' => 'admin_user_create_form',
				'method' => 'POST',
				'action' => route_url('user/admin/account/create'),
			]);

			echo '<div class="row g-3">';
			echo '<div class="col-md-6">' . $form->input('username', ['label' => 'Username', 'required' => true]) . '</div>';
			echo '<div class="col-md-6">' . $form->input('email', ['label' => 'Email', 'type' => 'email', 'required' => true]) . '</div>';
			echo '<div class="col-md-6">' . $form->input('first_name', ['label' => 'First Name']) . '</div>';
			echo '<div class="col-md-6">' . $form->input('last_name', ['label' => 'Last Name']) . '</div>';
			echo '<div class="col-md-6">' . $form->input('password', ['label' => 'Password', 'type' => 'password', 'required' => true]) . '</div>';
			echo '<div class="col-md-6">' . $form->input('password_confirm', ['label' => 'Confirm Password', 'type' => 'password', 'required' => true]) . '</div>';
			echo '<div class="col-md-6">' . $form->select('status', [1 => 'Active', 0 => 'Inactive'], '1', ['label' => 'Status']) . '</div>';
			echo '</div>';

			echo '<div class="card border-0 bg-light mt-4">';
			echo '<div class="card-body">';
			echo '<h3 class="h5 mb-3">Groups</h3>';
			foreach ($allGroups as $group) {
				$id = (int) ($group['id'] ?? 0);
				echo $form->checkbox('groups[]', [
					'label' => (string) ($group['label'] ?? $group['name'] ?? ''),
					'value' => (string) $id,
					'no_hidden' => true,
				]);
			}
			echo '</div></div>';

			echo '<div class="d-flex gap-2 mt-4">' . $form->submit('Create User', ['class' => 'btn btn-primary']) . ' <a class="btn btn-outline-secondary" href="' . route_url('user/admin/account') . '">Cancel</a></div>';
			echo $form->end();
		}, 'admin');
	}

	public function editAction(): void
	{
		$userId = (int) $this->request->route('id', 0);
		if ($userId <= 0) {
			redirect_to('user/admin/account');
		}

		$user = $this->db->findOne('users', $userId);
		if (!$user) {
			redirect_to('user/admin/account');
		}

		$errors = [];
		$fieldErrors = [];

		if ($this->request->isPost()) {
			if (!$this->form->checkToken(null, 'admin_user_edit_form')) {
				$errors[] = 'Invalid CSRF token.';
			}

			$post = $this->request->post(null, 'raw', []);
			$fieldErrors = $this->form->validate($post, [
				'email' => 'required|email|max:190|unique:users,email,' . $userId . ',id',
				'first_name' => 'max:100',
				'last_name' => 'max:100',
				'status' => 'required|in:0,1',
				'password' => 'nullable|min:8|max:255',
				'password_confirm' => 'nullable|match:password',
			]);

			if (empty($errors) && empty($fieldErrors)) {
				$email = strtolower(trim((string) $this->request->post('email', 'raw', '')));
				$firstName = trim((string) $this->request->post('first_name', 'raw', ''));
				$lastName = trim((string) $this->request->post('last_name', 'raw', ''));
				$status = (int) $this->request->post('status', 'int', 1);
				$password = (string) $this->request->post('password', 'raw', '');

				$update = [
					'email' => $email,
					'first_name' => $firstName !== '' ? $firstName : null,
					'last_name' => $lastName !== '' ? $lastName : null,
					'status' => $status,
					'updated_at' => date('Y-m-d H:i:s'),
				];

				if ($password !== '') {
					$update['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
				}

				$this->db->update('users', $update, ['id' => $userId]);

				$groupIds = $this->request->post('groups', 'array', []);
				$groupIds = is_array($groupIds) ? array_map('intval', $groupIds) : [];
				$this->auth->syncUserGroups($userId, $groupIds);

				redirect_to('user/admin/account');
			}
		}

		$allGroups = $this->auth->allGroups();
		$assignments = $this->auth->assignmentsForUser($userId);
		$selectedGroups = array_map(
			static fn(array $row): int => (int) ($row['id'] ?? 0),
			(array) ($assignments['groups'] ?? [])
		);

		$this->form->fill($user);
		$this->form->setErrors($fieldErrors);

		$form = $this->form;
		echo $this->view->inline(static function ($view) use ($form, $user, $userId, $allGroups, $selectedGroups, $errors): void {
			echo '<div class="d-flex align-items-center justify-content-between gap-2 mb-4">';
			echo '<div><h1 class="h3 mb-1">Edit User: ' . $view->e((string) ($user['username'] ?? '')) . '</h1><p class="text-muted mb-0">Update account information and group assignments.</p></div>';
			echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/admin/account') . '">Back</a></div>';
			if (!empty($errors)) {
				echo '<div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2">';
				foreach ($errors as $error) {
					echo '<li>' . $view->e((string) $error) . '</li>';
				}
				echo '</ul></div>';
			}
			echo $form->start([
				'id' => 'admin_user_edit_form',
				'method' => 'POST',
				'action' => route_url('user/admin/account/edit', ['id' => $userId]),
			]);

			echo '<div class="row g-3">';
			echo '<div class="col-md-6">' . $form->input('username', [
				'label' => 'Username',
				'value' => (string) ($user['username'] ?? ''),
				'disabled' => true,
			]) . '</div>';
			echo '<div class="col-md-6">' . $form->input('email', ['label' => 'Email', 'type' => 'email', 'required' => true]) . '</div>';
			echo '<div class="col-md-6">' . $form->input('first_name', ['label' => 'First Name']) . '</div>';
			echo '<div class="col-md-6">' . $form->input('last_name', ['label' => 'Last Name']) . '</div>';
			echo '<div class="col-md-6">' . $form->input('password', ['label' => 'New Password (optional)', 'type' => 'password']) . '</div>';
			echo '<div class="col-md-6">' . $form->input('password_confirm', ['label' => 'Confirm New Password', 'type' => 'password']) . '</div>';
			echo '<div class="col-md-6">' . $form->select('status', [1 => 'Active', 0 => 'Inactive'], (string) ($user['status'] ?? '1'), ['label' => 'Status']) . '</div>';
			echo '</div>';

			echo '<div class="card border-0 bg-light mt-4">';
			echo '<div class="card-body">';
			echo '<h3 class="h5 mb-3">Groups</h3>';
			foreach ($allGroups as $group) {
				$id = (int) ($group['id'] ?? 0);
				echo $form->checkbox('groups[]', [
					'label' => (string) ($group['label'] ?? $group['name'] ?? ''),
					'value' => (string) $id,
					'checked' => in_array($id, $selectedGroups, true),
					'no_hidden' => true,
				]);
			}
			echo '</div></div>';

			echo '<div class="d-flex gap-2 mt-4">' . $form->submit('Save User', ['class' => 'btn btn-primary']) . ' <a class="btn btn-outline-secondary" href="' . route_url('user/admin/account') . '">Cancel</a></div>';
			echo $form->end();
		}, 'admin');
	}

	public function deleteAction(): void
	{
		$userId = (int) $this->request->route('id', 0);
		if ($userId <= 0) {
			redirect_to('user/admin/account');
		}

		if ($userId === (int) $this->auth->id()) {
			redirect_to('user/admin/account');
		}

		$this->db->delete('users', ['id' => $userId]);
		redirect_to('user/admin/account');
	}
}