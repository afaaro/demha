<?php

use System\Engine\Controller;

class UserAdminRole extends Controller
{
	public function indexAction(): void
	{
		$groups = $this->auth->allGroups();
		$users = $this->auth->allUsers();

		echo $this->view->inline(static function ($view) use ($groups, $users): void {
			echo '<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-4">';
			echo '<div><h1 class="h3 mb-1">RBAC Administration</h1><p class="text-muted mb-0">Manage groups, permission rules, and user assignments from one place.</p></div>';
			echo '<div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/account') . '">Back to account</a><a class="btn btn-outline-primary btn-sm" href="' . route_url('user/logout') . '">Logout</a></div>';
			echo '</div>';

			echo '<div class="card border-0 shadow-sm mb-4">';
			echo '<div class="card-body">';
			echo '<div class="d-flex align-items-center justify-content-between mb-3">';
			echo '<h2 class="h5 mb-0">Groups</h2><a class="btn btn-primary btn-sm" href="' . route_url('user/admin/role/create') . '">Create Group</a>';
			echo '</div>';
			echo '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>ID</th><th>Name</th><th>Label</th><th>Status</th><th>System</th><th class="text-end">Actions</th></tr></thead><tbody>';
			foreach ($groups as $group) {
				$id = (int) $group['id'];
				echo '<tr>';
				echo '<td>' . $id . '</td>';
				echo '<td><strong>' . $view->e((string) ($group['name'] ?? '')) . '</strong></td>';
				echo '<td>' . $view->e((string) ($group['label'] ?? '')) . '</td>';
				echo '<td>' . ((int) ($group['status'] ?? 0) === 1 ? '<span class="badge text-bg-success">active</span>' : '<span class="badge text-bg-secondary">inactive</span>') . '</td>';
				echo '<td>' . ((int) ($group['system'] ?? 0) === 1 ? '<span class="badge text-bg-info">yes</span>' : '<span class="badge text-bg-light">no</span>') . '</td>';
				echo '<td class="text-end"><div class="d-flex justify-content-end gap-2">';
				echo '<a class="btn btn-outline-primary btn-sm" href="' . route_url('user/admin/role/edit', ['id' => $id]) . '">Edit</a>';
				echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/admin/permission', ['id' => $id]) . '">Permissions</a>';
				if ((int) ($group['system'] ?? 0) !== 1) {
					echo '<a class="btn btn-outline-danger btn-sm" onclick="return confirm(\'Delete this group?\')" href="' . route_url('user/admin/role/delete', ['id' => $id]) . '">Delete</a>';
				}
				echo '</div></td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
			echo '</div></div>';

			echo '<div class="card border-0 shadow-sm">';
			echo '<div class="card-body">';
			echo '<h2 class="h5 mb-3">User Assignments</h2>';
			echo '<div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>ID</th><th>Username</th><th>Email</th><th class="text-end">Action</th></tr></thead><tbody>';
			foreach ($users as $user) {
				$id = (int) $user['id'];
				echo '<tr>';
				echo '<td>' . $id . '</td>';
				echo '<td><strong>' . $view->e((string) ($user['username'] ?? '')) . '</strong></td>';
				echo '<td>' . $view->e((string) ($user['email'] ?? '')) . '</td>';
				echo '<td class="text-end"><a class="btn btn-outline-primary btn-sm" href="' . route_url('user/admin/role/user/assign', ['id' => $id]) . '">Manage</a></td>';
				echo '</tr>';
			}
			echo '</tbody></table></div>';
			echo '</div></div>';
		}, 'admin');
	}

	public function createAction(): void
	{
		$errors = [];
		if ($this->request->isPost()) {
			if (!$this->form->checkToken(null, 'group_create_form')) {
				$errors[] = 'Invalid CSRF token.';
			}

			$post = $this->request->post(null, 'raw', []);
			$validationErrors = $this->form->validate($post, [
				'name' => 'required|regex:/^[a-z0-9_-]+$/|min:3|max:100|unique:groups,name',
				'label' => 'required|min:2|max:150',
				'description' => 'max:255',
			]);

			if (!empty($validationErrors)) {
				$errors = array_values($validationErrors);
			}

			if (empty($errors)) {
				try {
					$this->auth->createGroup(
						(string) $this->request->post('name', 'raw', ''),
						(string) $this->request->post('label', 'raw', ''),
						(string) $this->request->post('description', 'raw', '')
					);

					redirect_to('user/admin/role');
				} catch (\Throwable $e) {
					$errors[] = $e->getMessage();
				}
			}
		}

		$this->form->setErrors($this->mapFieldErrors($errors, ['name', 'label', 'description']));

		$form = $this->form;
		echo $this->view->inline(static function ($view) use ($form, $errors): void {
			echo '<div class="d-flex align-items-center justify-content-between gap-2 mb-4">';
			echo '<div><h1 class="h3 mb-1">Create Group</h1><p class="text-muted mb-0">Define a new group and give it a clear label and description.</p></div>';
			echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/admin/role') . '">Back</a></div>';
			if (!empty($errors)) {
				echo '<div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2">';
				foreach ($errors as $error) {
					echo '<li>' . $view->e((string) $error) . '</li>';
				}
				echo '</ul></div>';
			}
			echo $form->start([
				'id' => 'group_create_form',
				'method' => 'POST',
				'action' => route_url('user/admin/role/create'),
			]);
			echo $form->input('name', [
				'label' => 'Name (slug)',
				'placeholder' => 'content_editor',
				'help' => 'Use lowercase letters, numbers, underscore or dash.',
			]);
			echo $form->input('label', ['label' => 'Label']);
			echo $form->textarea('description', ['label' => 'Description', 'rows' => 3]);
			echo '<div class="d-flex gap-2 mt-4">' . $form->submit('Create Group', ['class' => 'btn btn-primary']) . ' <a class="btn btn-outline-secondary" href="' . route_url('user/admin/role') . '">Cancel</a></div>';
			echo $form->end();
		}, 'admin');
	}

	public function editAction(): void
	{
		$groupId = (int) $this->request->route('id', 0);
		if ($groupId <= 0) {
			redirect_to('user/admin/role');
		}

		$group = $this->auth->groupById($groupId);
		if (!$group) {
			redirect_to('user/admin/role');
		}

		$errors = [];
		if ($this->request->isPost()) {
			if (!$this->form->checkToken(null, 'group_edit_form')) {
				$errors[] = 'Invalid CSRF token.';
			}

			$post = $this->request->post(null, 'raw', []);
			$validationErrors = $this->form->validate($post, [
				'label' => 'required|min:2|max:150',
				'description' => 'max:255',
				'status' => 'required|in:0,1',
			]);

			if (!empty($validationErrors)) {
				$errors = array_values($validationErrors);
			}

			if (empty($errors)) {
				try {
					$this->auth->updateGroup(
						$groupId,
						(string) $this->request->post('label', 'raw', ''),
						(string) $this->request->post('description', 'raw', ''),
						(int) $this->request->post('status', 'int', 0)
					);

					redirect_to('user/admin/role');
				} catch (\Throwable $e) {
					$errors[] = $e->getMessage();
				}
			}
		}

		$group = $this->auth->groupById($groupId) ?? $group;
		$this->form->fill($group);
		$this->form->setErrors($this->mapFieldErrors($errors, ['label', 'description', 'status']));

		$form = $this->form;
		echo $this->view->inline(static function ($view) use ($form, $errors, $group, $groupId): void {
			echo '<div class="d-flex align-items-center justify-content-between gap-2 mb-4">';
			echo '<div><h1 class="h3 mb-1">Edit Group</h1><p class="text-muted mb-0">Update the group label, description, and activity state.</p></div>';
			echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/admin/role') . '">Back</a></div>';
			if (!empty($errors)) {
				echo '<div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2">';
				foreach ($errors as $error) {
					echo '<li>' . $view->e((string) $error) . '</li>';
				}
				echo '</ul></div>';
			}
			echo $form->start([
				'id' => 'group_edit_form',
				'method' => 'POST',
				'action' => route_url('user/admin/role/group/edit', ['id' => $groupId]),
			]);
			echo $form->input('name', ['label' => 'Name', 'value' => (string) ($group['name'] ?? ''), 'disabled' => true]);
			echo $form->input('label', ['label' => 'Label']);
			echo $form->textarea('description', ['label' => 'Description', 'rows' => 3]);
			echo $form->select('status', [1 => 'Active', 0 => 'Inactive'], (string) ($group['status'] ?? '1'), ['label' => 'Status']);
			echo '<div class="d-flex gap-2 mt-4">' . $form->submit('Save', ['class' => 'btn btn-primary']) . ' <a class="btn btn-outline-secondary" href="' . route_url('user/admin/role') . '">Cancel</a></div>';
			echo $form->end();
		}, 'admin');
	}

	public function deleteAction(): void
	{
		$groupId = (int) $this->request->route('id', 0);
		if ($groupId > 0) {
			try {
				$this->auth->deleteGroup($groupId);
			} catch (\Throwable $e) {
				// Silently continue to keep navigation simple.
			}
		}

		redirect_to('user/admin/role');
	}
	
	public function userAssignAction(): void
	{
		$userId = (int) $this->request->get('id', 'int', 0);
		if ($userId <= 0) {
			redirect_to('user/admin/role');
		}

		$user = $this->db->findOne('users', $userId);
		if (!$user) {
			redirect_to('user/admin/role');
		}

		if ($this->request->isPost()) {
			if (!$this->form->checkToken(null, 'user_assign_form')) {
				http_response_code(419);
				echo 'Invalid CSRF token';
				exit;
			}

			$groupIds = $this->request->post('groups', 'array', []);
			$groupIds = is_array($groupIds) ? array_map('intval', $groupIds) : [];

			$permissions = $this->request->post('permissions', 'array', []);
			$permissions = is_array($permissions) ? array_map('strval', $permissions) : [];

			$this->auth->syncUserGroups($userId, $groupIds);
			$this->auth->syncUserDirectPermissions($userId, $permissions);

			redirect_to('user/admin/role/user/assign', ['id' => $userId]);
		}

		$allGroups = $this->auth->allGroups();
		$allPermissions = $this->auth->allPermissions();
		$assignments = $this->auth->assignmentsForUser($userId);

		$selectedGroupIds = array_map(
			static fn(array $row) => (int) ($row['id'] ?? 0),
			$assignments['groups']
		);

		$selectedPermissions = $assignments['permissions'];

		$form = $this->form;
		echo $this->view->inline(static function ($view) use ($form, $user, $userId, $allGroups, $selectedGroupIds, $allPermissions, $selectedPermissions): void {
			echo '<div class="d-flex align-items-center justify-content-between gap-2 mb-4">';
			echo '<div><h1 class="h3 mb-1">Manage Assignments: ' . $view->e((string) ($user['username'] ?? '')) . '</h1><p class="text-muted mb-0">Adjust groups and direct permissions for this user.</p></div>';
			echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('user/admin/role') . '">Back</a></div>';
			echo $form->start([
				'id' => 'user_assign_form',
				'method' => 'POST',
				'action' => route_url('user/admin/role/user/assign', ['id' => $userId]),
			]);

			echo '<div class="card border-0 bg-light mb-4"><div class="card-body"><h3 class="h5 mb-3">Groups</h3>';
			foreach ($allGroups as $group) {
				$groupId = (int) ($group['id'] ?? 0);
				$checked = in_array($groupId, $selectedGroupIds, true) ? ' checked' : '';
				echo '<label class="d-flex align-items-start gap-2 mb-2"><input type="checkbox" name="groups[]" value="' . $groupId . '"' . $checked . '><span>' . $view->e((string) ($group['label'] ?? $group['name'] ?? '')) . ' <small class="text-muted">(' . $view->e((string) ($group['name'] ?? '')) . ')</small></span></label>';
			}
			echo '</div></div>';

			echo '<div class="card border-0 bg-light"><div class="card-body"><h3 class="h5 mb-2">Direct Permissions</h3><p class="text-muted mb-3">Direct permissions are additive on top of group permissions.</p><div class="row g-3">';
			foreach ($allPermissions as $permission) {
				$name = (string) ($permission['name'] ?? '');
				$checked = in_array($name, $selectedPermissions, true) ? ' checked' : '';
				echo '<div class="col-md-6"><label class="d-flex align-items-start gap-2"><input type="checkbox" name="permissions[]" value="' . $view->e($name) . '"' . $checked . '><span><strong>' . $view->e((string) ($permission['label'] ?? $name)) . '</strong><br><small class="text-muted">' . $view->e($name) . '</small></span></label></div>';
			}
			echo '</div></div></div>';

			echo '<div class="d-flex gap-2 mt-4">' . $form->submit('Save Assignments', ['class' => 'btn btn-primary']) . ' <a class="btn btn-outline-secondary" href="' . route_url('user/admin/role') . '">Back</a></div>';
			echo $form->end();
		}, 'admin');
	}
	
	private function mapFieldErrors(array $errors, array $fields): array
	{
		$fieldErrors = [];
		foreach ($fields as $index => $field) {
			if (isset($errors[$field])) {
				$fieldErrors[$field] = (string) $errors[$field];
				continue;
			}

			if (isset($errors[$index])) {
				$fieldErrors[$field] = (string) $errors[$index];
			}
		}

		return $fieldErrors;
	}
}
