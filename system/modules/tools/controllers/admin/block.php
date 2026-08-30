<?php

use System\Engine\Controller;
use System\Library\Notify;

class ToolsAdminBlock extends Controller
{
	private const FORM_ID = 'tools_block_form';
	private const DELETE_FORM_ID = 'tools_block_delete_form';

	public function indexAction(): void
	{
		$this->block->ensureTable();

		$blocks = $this->db->query('SELECT * FROM #__block ORDER BY `region` ASC, `weight` ASC, `id` ASC')->rows;

		echo $this->view->inline(static function ($view) use ($blocks): void {
			echo '<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-4">';
			echo '<div><h1 class="h3 mb-1">Block Manager</h1><p class="text-muted mb-0">Place blocks into theme regions and control where they appear.</p></div>';
			echo '<div class="d-flex gap-2"><a class="btn btn-outline-secondary btn-sm" href="' . route_url('tools/admin/module') . '">Back to modules</a><a class="btn btn-primary btn-sm" href="' . route_url('tools/admin/block/create') . '">Add Block</a></div>';
			echo '</div>';

			echo '<div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive">';
			echo '<table class="table table-hover align-middle mb-0">';
			echo '<thead class="table-light"><tr><th>Title</th><th>Region</th><th>Type</th><th>Visibility</th><th>Weight</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>';

			if (empty($blocks)) {
				echo '<tr><td colspan="7" class="text-muted py-4">No blocks defined yet.</td></tr>';
			} else {
				foreach ($blocks as $block) {
					$id = (int) $block['id'];
					$status = (int) ($block['status'] ?? 0) === 1;

					echo '<tr>';
					echo '<td><strong>' . $view->e((string) ($block['title'] ?? '')) . '</strong></td>';
					echo '<td><code>' . $view->e((string) ($block['region'] ?? '')) . '</code></td>';
					echo '<td>' . $view->e((string) ($block['body_type'] ?? 'html')) . '</td>';
					echo '<td>' . $view->e((string) ($block['visibility'] ?? 'include')) . '</td>';
					echo '<td>' . (int) ($block['weight'] ?? 0) . '</td>';
					echo '<td>' . ($status ? '<span class="badge text-bg-success">enabled</span>' : '<span class="badge text-bg-secondary">disabled</span>') . '</td>';
					echo '<td class="text-end"><div class="d-flex justify-content-end gap-2">';
					echo '<a class="btn btn-outline-primary btn-sm" href="' . route_url('tools/admin/block/edit', ['id' => $id]) . '">Edit</a>';
					echo '<form method="post" action="' . route_url('tools/admin/block/delete', ['id' => $id]) . '" class="d-inline-block">';
					echo csrf_field(self::DELETE_FORM_ID);
					echo '<button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm(\'Delete this block?\')">Delete</button>';
					echo '</form>';
					echo '</div></td>';
					echo '</tr>';
				}
			}

			echo '</tbody></table></div></div></div>';
		}, 'admin');
	}

	public function createAction(): void
	{
		$this->block->ensureTable();
		$this->saveForm(null);
	}

	public function editAction(): void
	{
		$this->block->ensureTable();

		$id = (int) $this->request->get('id', 'int', 0);
		$block = $id > 0 ? $this->db->first('block', ['id' => $id]) : [];
		if (empty($block)) {
			Notify::error('Block not found.');
			redirect_to('tools/admin/block');
		}

		$this->saveForm($block);
	}

	public function deleteAction(): void
	{
		if (!$this->request->isPost()) {
			redirect_to('tools/admin/block');
		}

		if (!$this->form->checkToken(null, self::DELETE_FORM_ID)) {
			Notify::error('Invalid CSRF token.');
			redirect_to('tools/admin/block');
		}

		$id = (int) $this->request->get('id', 'int', 0);
		if ($id > 0) {
			$this->db->delete('block', ['id' => $id]);
			Notify::success('Block deleted.');
		}

		redirect_to('tools/admin/block');
	}

	private function saveForm(?array $block): void
	{
		$isEdit = $block !== null;
		$id = $isEdit ? (int) $block['id'] : 0;
		$errors = [];

		if ($this->request->isPost()) {
			if (!$this->form->checkToken(null, self::FORM_ID)) {
				$errors[] = 'Invalid CSRF token.';
			}

			$post = $this->request->post(null, 'raw', []);
			$validationErrors = $this->form->validate($post, [
				'title' => 'max:150',
				'region' => 'required|regex:/^[a-z0-9_-]+$/i|max:60',
				'body_type' => 'required|in:html,module',
				'body' => 'required',
				'visibility' => 'required|in:include,exclude',
				'weight' => 'nullable|numeric',
			]);

			if (!empty($validationErrors)) {
				$errors = array_values($validationErrors);
			}

			if (empty($errors)) {
				$data = [
					'title' => (string) $this->request->post('title', 'raw', ''),
					'region' => (string) $this->request->post('region', 'raw', ''),
					'body_type' => (string) $this->request->post('body_type', 'raw', 'html'),
					'body' => (string) $this->request->post('body', 'raw', ''),
					'visibility' => (string) $this->request->post('visibility', 'raw', 'include'),
					'paths' => (string) $this->request->post('paths', 'raw', ''),
					'modules' => (string) $this->request->post('modules', 'raw', ''),
					'status' => (int) $this->request->post('status', 'raw', 0) === 1 ? 1 : 0,
					'weight' => (int) $this->request->post('weight', 'raw', 0),
				];

				try {
					if ($isEdit) {
						$this->db->update('block', $data, ['id' => $id]);
						Notify::success('Block updated.');
					} else {
						$this->db->insert('block', $data);
						Notify::success('Block created.');
					}

					redirect_to('tools/admin/block');
				} catch (\Throwable $e) {
					$errors[] = $e->getMessage();
				}
			}
		}

		$this->form->setErrors($this->mapFieldErrors($errors, ['title', 'region', 'body_type', 'body', 'visibility', 'weight']));

		if (!$this->request->isPost() && $isEdit) {
			$this->form->fill($block);
		}

		$form = $this->form;
		$regions = [
			'sidebar_left' => 'Sidebar Left',
			'sidebar_right' => 'Sidebar Right',
			'content_top' => 'Content Top',
			'content_bottom' => 'Content Bottom',
			'footer' => 'Footer',
		];
		$bodyTypes = ['html' => 'HTML', 'module' => 'Module'];
		$visibilityModes = ['include' => 'Show only on matches', 'exclude' => 'Hide on matches'];
		$statusChecked = $isEdit ? (int) ($block['status'] ?? 0) === 1 : true;
		$formAction = $isEdit ? route_url('tools/admin/block/edit', ['id' => $id]) : route_url('tools/admin/block/create');
		$heading = $isEdit ? 'Edit Block' : 'Add Block';

		echo $this->view->inline(static function ($view) use ($form, $errors, $regions, $bodyTypes, $visibilityModes, $statusChecked, $formAction, $heading): void {
			echo '<div class="d-flex align-items-center justify-content-between gap-2 mb-4">';
			echo '<div><h1 class="h3 mb-1">' . $view->e($heading) . '</h1><p class="text-muted mb-0">Blocks render into theme regions, and can be limited to specific modules or URL paths.</p></div>';
			echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('tools/admin/block') . '">Back</a></div>';

			if (!empty($errors)) {
				echo '<div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-2">';
				foreach ($errors as $error) {
					echo '<li>' . $view->e((string) $error) . '</li>';
				}
				echo '</ul></div>';
			}

			echo $form->start(['id' => 'tools_block_form', 'method' => 'POST', 'action' => $formAction]);
			echo $form->input('title', ['label' => 'Title', 'help' => 'Optional heading shown above the block.']);
			echo $form->select('region', $regions, null, ['label' => 'Region', 'help' => 'Matches the region name used in the theme template.']);
			echo $form->select('body_type', $bodyTypes, null, ['label' => 'Content Type']);
			echo $form->textarea('body', ['label' => 'Body', 'rows' => 6, 'help' => 'HTML content, or a module slug (with library/block.php) when Content Type is Module.']);
			echo $form->select('visibility', $visibilityModes, null, ['label' => 'Visibility Mode']);
			echo $form->textarea('paths', ['label' => 'URL Paths', 'rows' => 3, 'help' => 'One route pattern per line, "*" wildcard supported (e.g. news/*). Leave blank to ignore.']);
			echo $form->input('modules', ['label' => 'Modules', 'help' => 'Comma separated module slugs (e.g. news,forum). Leave blank to ignore.']);
			echo $form->input('weight', ['label' => 'Weight', 'type' => 'number', 'help' => 'Lower numbers render first within a region.']);
			echo $form->checkbox('status', ['label' => 'Enabled', 'value' => '1', 'checked' => $statusChecked]);
			echo '<div class="d-flex gap-2 mt-4">' . $form->submit('Save Block', ['class' => 'btn btn-primary']) . ' <a class="btn btn-outline-secondary" href="' . route_url('tools/admin/block') . '">Cancel</a></div>';
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
