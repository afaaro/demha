<?php

use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class NodeAdminPost extends Controller
{
    private $nodeService;
    private $taxonomyModel;
    private $bundleModel;
    private array $fieldDefinitions = [];

    public function __construct()
    {
        parent::__construct(Registry::getInstance());
        $this->nodeService    = $this->load->library('node/node_service');
        $this->taxonomyModel  = $this->load->model('node/taxonomy');
        $this->bundleModel    = $this->load->model('node/node_bundle');
    }

    /**
     * List all content with filters and pagination.
     */
    public function indexAction(): void
    {
        $bundle = $this->request->get('bundle', 'raw');
        $status = $this->request->get('status', 'raw');
        $search = $this->request->get('search', 'raw');
        $page   = (int) ($this->request->get('page', 'int') ?: 1);
        $limit  = 20;

        $filters = [];
        if ($status) $filters['status'] = $status;
        if ($search) $filters['search'] = $search;

        $result   = $this->nodeService->list($bundle, $filters, $page, $limit);
        $bundles  = $this->bundleModel->getBundles();

        echo $this->view->inline(function ($view) use ($result, $bundles, $bundle, $status, $search) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1><i class='bi bi-file-text me-2'></i>Content</h1>";
            echo "<a class='btn btn-primary' href='" . $view->url->to('node/admin/post/create') . "?bundle=" . ($bundle ?: 'article') . "'>
                <i class='bi bi-plus-lg me-1'></i>Add Content
            </a>";
            echo "</div>";

            echo $this->renderFilterForm($view, $bundles, $bundle, $status, $search);

            if (empty($result['data'])) {
                echo "<div class='alert alert-info'>No content found.</div>";
            } else {
                echo $this->renderTable($view, $result['data']);
                echo $this->renderPagination($result['total'], $result['page'], $result['limit'], [
                    'bundle' => $bundle,
                    'status' => $status,
                    'search' => $search
                ]);
            }
        }, 'admin');
    }

    /**
     * Create new content.
     */
    public function createAction(): void
    {
        $bundle = $this->request->get('bundle', 'raw');
        if (!$bundle) {
            Notify::error('Content type is required.');
            redirect_to('node/admin/bundle');
        }

        $bundleInfo = $this->bundleModel->getBundle($bundle);
        if (!$bundleInfo) {
            Notify::error('Content type not found.');
            redirect_to('node/admin/bundle');
        }

        $fields = $this->nodeService->getFields($bundle);
        $this->fieldDefinitions = $fields;

        // Build validation rules
        $rules = $this->buildValidationRules($fields);

        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $data['bundle'] = $bundle;
            $data = $this->processSubmittedData($data, $fields);

            $result = $this->nodeService->create($bundle, $data);
            if ($result['success']) {
                Notify::success('Content created successfully.');
                $this->form->clearOldInput();
                $this->form->clearFlashedErrors();
                redirect_to('node/admin/post', ['bundle' => $bundle]);
            } else {
                Notify::success(implode('<br>', $result['errors']));
                $this->form->setErrors($result['errors']);
            }
        }

        // Fill with submitted data on validation fail + defaults
        $this->form->fill(array_merge(['status' => 'draft'], $this->form->getOldInput()));

        $formHtml = $this->buildFormFields($fields, null, $rules);
        echo $this->view->inline(function ($view) use ($bundle, $bundleInfo, $formHtml) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Create " . htmlspecialchars($bundleInfo['name']) . "</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('node/admin/post', ['bundle' => $bundle]) . "'>
                <i class='bi bi-arrow-left me-1'></i>Back
            </a>";
            echo "</div>";

            if (!empty($bundleInfo['description'])) {
                echo "<div class='alert alert-info'>" . htmlspecialchars($bundleInfo['description']) . "</div>";
            }

            echo $this->form->open(['enctype' => 'multipart/form-data']);
            echo "<input type='hidden' name='bundle' value='" . htmlspecialchars($bundle) . "'>";
            echo $formHtml;
            echo $this->form->submit('Create', ['class' => 'btn btn-primary']);
            echo $this->form->close();
        }, 'admin');
    }

    /**
     * Edit existing content.
     */
    public function editAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        $node = $this->nodeService->get($id);
        if (!$node) {
            Notify::error('Content not found.');
            redirect_to('node/admin/post');
        }

        $bundle = $node['bundle'];
        $bundleInfo = $this->bundleModel->getBundle($bundle);
        $fields = $this->nodeService->getFields($bundle);
        $this->fieldDefinitions = $fields;

        $rules = $this->buildValidationRules($fields, $id);

        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $data = $this->processSubmittedData($data, $fields, $node);

            $result = $this->nodeService->update($id, $data);
            if ($result['success']) {
                Notify::success('Content updated successfully.');
                $this->form->clearOldInput();
                $this->form->clearFlashedErrors();
                redirect_to('node/admin/post', ['bundle' => $bundle]);
            } else {
                Notify::error(implode('<br>', $result['errors']));
                $this->form->setErrors($result['errors']);
            }
        }

        // Merge node data with submitted input on validation fail
        $this->form->fill(array_merge($node, $this->form->getOldInput()));

        $formHtml = $this->buildFormFields($fields, $node, $rules);
        echo $this->view->inline(function ($view) use ($id, $bundle, $node, $bundleInfo, $formHtml) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Edit: " . htmlspecialchars($node['title']) . "</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('node/admin/post', ['bundle' => $bundle]) . "'>
                <i class='bi bi-arrow-left me-1'></i>Back
            </a>";
            echo "</div>";

            echo $this->form->open(['enctype' => 'multipart/form-data']);
            echo "<input type='hidden' name='id' value='$id'>";
            echo $formHtml;
            echo $this->form->submit('Update', ['class' => 'btn btn-primary']);
            echo $this->form->close();
        }, 'admin');
    }

    /**
     * Delete content.
     */
    public function deleteAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        if (!$id) {
            Notify::error('Invalid content ID.');
            redirect_to('node/admin/post');
        }

        if ($this->nodeService->delete($id)) {
            Notify::success('Content deleted.');
        } else {
            Notify::error('Failed to delete.');
        }
        redirect_to('node/admin/post');
    }

    // ─── Helpers ─────────────────────────────────────────────

    /**
     * Build validation rules from field definitions.
     */
    private function buildValidationRules(array $fields, ?int $excludeId = null): array
    {
        $rules = [
            'title'  => 'required|min:3|max:255',
            'slug'   => 'nullable|alpha_dash|max:255',
            'status' => 'required|in:draft,published,archived',
        ];

        // Unique title rule
        $rules['title'] .= $excludeId
            ? "|unique:node_entities,title,$excludeId,id"
            : "|unique:node_entities,title";

        // Custom field rules
        foreach ($fields as $field) {
            $name = $field['field_name'];
            $required = (bool) $field['required'];
            $rules[$name] = $required
                ? ($field['field_type'] === 'checkbox' ? 'accepted' : 'required')
                : 'nullable';
        }
        return $rules;
    }

    /**
     * Build form HTML from field definitions.
     */
    private function buildFormFields(array $fields, ?array $node, array &$rules): string
    {
        $html = '';

        // Title
        $html .= $this->form->input('title', [
            'label' => 'Title',
            'placeholder' => 'Enter a title...',
            'required' => true,
        ]);

        // Slug
        $html .= $this->form->input('slug', [
            'label' => 'URL Slug',
            'help'  => 'Auto-generated from title if left blank',
        ]);

        // Custom fields
        foreach ($fields as $field) {
            $name = $field['field_name'];
            $label = $field['label'];
            $required = (bool) $field['required'];
            $type = $field['field_type'];
            $settings = json_decode($field['settings'] ?? '{}', true);

            $html .= match ($type) {
                'text' => $this->form->input($name, compact('label', 'required')),
                'textarea' => $this->form->textarea($name, compact('label', 'required')),
                'richtext' => $this->form->textarea($name, ['label' => $label, 'editor' => true, 'required' => $required]),
                'number' => $this->form->input($name, ['type' => 'number', 'label' => $label, 'required' => $required]),
                'date'   => $this->form->input($name, ['type' => 'date', 'label' => $label, 'required' => $required]),
                'image', 'file' => $this->form->input($name, ['type' => 'file', 'label' => $label, 'required' => $required]),
                'select' => $this->form->select($name, $settings['options'] ?? [], null, [
                    'label' => $label, 'required' => $required, 'blank' => "-- Select $label --"
                ]),
                'checkbox' => $this->form->checkbox($name, ['label' => $label, 'required' => $required]),
                'taxonomy' => $this->buildTaxonomySelect($name, $label, $settings['vocabulary'] ?? null, $required),
                'entity_reference' => $this->buildEntitySelect($name, $label, $settings['target_bundle'] ?? null, $required),
                default => '',
            };
        }

        // Status
        $html .= $this->form->select('status', [
            'draft'     => 'Draft',
            'published' => 'Published',
            'archived'  => 'Archived',
        ], null, ['label' => 'Status']);

        return $html;
    }

    /**
     * Process submitted data: auto-slug, file uploads.
     */
    private function processSubmittedData(array $data, array $fields, ?array $node = null): array
    {
        // Auto-generate slug
        if (empty($data['slug'])) {
            $data['slug'] = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $data['title']), '-'));
        }

        // Handle file uploads
        foreach ($fields as $field) {
            if (in_array($field['field_type'], ['image', 'file'], true)) {
                $name = $field['field_name'];
                $file = $this->request->files($name);
                if ($file && $file['error'] === UPLOAD_ERR_OK) {
                    $data[$name] = $this->uploadFile($file);
                } elseif ($node && empty($file['name'] ?? '')) {
                    // Preserve existing value if no new file
                    $data[$name] = $node[$name] ?? null;
                }
            }
        }

        $data['status'] = $data['status'] ?? 'draft';
        return $data;
    }

    /**
     * Handle file upload securely.
     */
    private function uploadFile(array $file): string
    {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $dir = rtrim('uploads/nodes/', '/') . '/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);

        $filename = date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $path = $dir . $filename;
        move_uploaded_file($file['tmp_name'], $path);
        return $path;
    }

    private function buildTaxonomySelect(string $name, string $label, ?string $vocabulary, bool $required): string
    {
        if (!$vocabulary) return '';
        $terms = $this->taxonomyModel->getTerms($vocabulary);
        $opts = array_column($terms, 'name', 'id');
        return $this->form->select($name, $opts, null, [
            'label' => $label, 'required' => $required, 'blank' => "-- Select $label --"
        ]);
    }

    private function buildEntitySelect(string $name, string $label, ?string $targetBundle, bool $required): string
    {
        if (!$targetBundle) return '';
        $result = $this->nodeService->list($targetBundle, ['status' => 'published'], 1, 100);
        $opts = array_column($result['data'] ?? [], 'title', 'id');
        return $this->form->select($name, $opts, null, [
            'label' => $label, 'required' => $required, 'blank' => "-- Select $label --"
        ]);
    }

    private function renderFilterForm($view, array $bundles, ?string $bundle, ?string $status, ?string $search): string
    {
        $html = "<form method='get' class='row g-3 mb-4' action='" . $view->url->to('node/admin/post') . "'>";
        $html .= "<div class='col-md-3'><select name='bundle' class='form-select'>
            <option value=''>All Types</option>" . implode('', array_map(fn($b) =>
            "<option value='{$b['machine_name']}' " . ($b['machine_name'] === $bundle ? 'selected' : '') . ">" . htmlspecialchars($b['name']) . "</option>",
        $bundles)) . "</select></div>";

        $html .= "<div class='col-md-2'><select name='status' class='form-select'>
            <option value=''>All Status</option>
            <option value='published' " . ($status === 'published' ? 'selected' : '') . ">Published</option>
            <option value='draft' " . ($status === 'draft' ? 'selected' : '') . ">Draft</option>
            <option value='archived' " . ($status === 'archived' ? 'selected' : '') . ">Archived</option>
        </select></div>";

        $html .= "<div class='col-md-4'><input type='text' name='search' class='form-control' placeholder='Search by title...' value='" . htmlspecialchars((string)$search) . "'></div>";
        $html .= "<div class='col-md-2'><button type='submit' class='btn btn-secondary w-100'>Filter</button></div>";
        return $html . "</form>";
    }

    private function renderTable($view, array $nodes): string
    {
        $html = "<div class='table-responsive'><table class='table table-striped table-hover'><thead><tr>
            <th>ID</th><th>Title</th><th>Type</th><th>Status</th><th>Created</th><th>Actions</th>
        </tr></thead><tbody>";

        foreach ($nodes as $n) {
            $badge = match ($n['status']) {
                'published' => 'bg-success', 'draft' => 'bg-warning text-dark', 'archived' => 'bg-secondary', default => 'bg-secondary'
            };
            $html .= "<tr>
                <td>{$n['id']}</td>
                <td><a href='" . $view->url->to('node/view', ['slug' => $n['slug']]) . "' target='_blank'>" . htmlspecialchars($n['title']) . "</a></td>
                <td><span class='badge bg-info'>" . htmlspecialchars($n['bundle']) . "</span></td>
                <td><span class='badge $badge'>" . htmlspecialchars($n['status']) . "</span></td>
                <td>" . date('Y-m-d', strtotime($n['created_at'] ?? 'now')) . "</td>
                <td>
                    <a class='btn btn-sm btn-outline-primary me-1' href='" . $view->url->to('node/admin/post/edit', ['id' => $n['id']]) . "'><i class='bi bi-pencil'></i></a>
                    <a class='btn btn-sm btn-outline-danger' onclick=\"return confirm('Delete this content?')\" href='" . $view->url->to('node/admin/post/delete', ['id' => $n['id']]) . "'><i class='bi bi-trash'></i></a>
                </td>
            </tr>";
        }
        return $html . "</tbody></table></div>";
    }

    private function renderPagination(int $total, int $page, int $limit, array $queryParams): string
    {
        $last = (int)ceil($total / $limit);
        if ($last <= 1) return '';

        $qs = http_build_query(array_filter($queryParams, fn($v) => $v !== null && $v !== ''));
        $html = '<nav><ul class="pagination justify-content-center">';
        $prev = max(1, $page - 1);
        $next = min($last, $page + 1);

        $html .= "<li class='page-item " . ($page === 1 ? 'disabled' : '') . "'><a class='page-link' href='?page=$prev" . ($qs ? "&$qs" : '') . "'>&laquo;</a></li>";
        for ($i = 1; $i <= $last; $i++) {
            $html .= "<li class='page-item " . ($i === $page ? 'active' : '') . "'><a class='page-link' href='?page=$i" . ($qs ? "&$qs" : '') . "'>$i</a></li>";
        }
        $html .= "<li class='page-item " . ($page === $last ? 'disabled' : '') . "'><a class='page-link' href='?page=$next" . ($qs ? "&$qs" : '') . "'>&raquo;</a></li>";
        return $html . '</ul></nav>';
    }
}