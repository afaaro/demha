<?php
use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class NewsAdminCategory extends Controller
{
    protected object $model;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->model = $this->load->model('news/admin/category');
    }

    public function indexAction(): void
    {
        if (!$this->auth->can('news.admin.category.view')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        $page = (int) $this->request->get('page', 'int', 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $categories = $this->model->getCategories($limit, $offset);
        $total = $this->model->countCategories();

        echo $this->view->inline(function ($view) use ($categories, $total, $page, $limit) {
            echo '<div class="d-flex justify-content-between align-items-center mb-3">';
            echo '  <h2>News Categories</h2>';
            echo '  <a href="' . $view->url->to('news/admin/category/create') . '" class="btn btn-primary">';
            echo '    <i class="bi bi-plus-circle"></i> Add Category';
            echo '  </a>';
            echo '</div>';

            if (count($categories) > 0) {
                echo '<div class="table-responsive">';
                echo '<table class="table table-striped table-hover">';
                echo '  <thead>';
                echo '    <tr>';
                echo '      <th>ID</th>';
                echo '      <th>Name</th>';
                echo '      <th>Slug</th>';
                echo '      <th>Description</th>';
                echo '      <th>Parent</th>';
                echo '      <th>Actions</th>';
                echo '    </tr>';
                echo '  </thead>';
                echo '  <tbody>';

                foreach ($categories as $cat) {
                    echo '    <tr>';
                    echo '      <td>' . (int)$cat['id'] . '</td>';
                    echo '      <td><a href="' . $view->url->to('news/admin/category/edit', ['id' => $cat['id']]) . '">' . escape($cat['name']) . '</a></td>';
                    echo '      <td>' . escape($cat['slug']) . '</td>';
                    echo '      <td>' . escape(substr($cat['description'] ?? '', 0, 80)) . '</td>';
                    echo '      <td>' . escape($cat['parent_name'] ?? '—') . '</td>';
                    echo '      <td>';
                    echo '        <a href="' . $view->url->to('news/admin/category/edit', ['id' => $cat['id']]) . '" class="btn btn-sm btn-outline-primary">Edit</a>';
                    echo '        <a href="' . $view->url->to('news/admin/category/delete', ['id' => $cat['id']]) . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Delete this category?\')">Delete</a>';
                    echo '      </td>';
                    echo '    </tr>';
                }

                echo '  </tbody>';
                echo '</table>';
                echo '</div>';

                // Pagination
                if ($total > $limit) {
                    echo '<nav><ul class="pagination">';
                    $pages = ceil($total / $limit);
                    for ($i = 1; $i <= $pages; $i++) {
                        $active = ($i == $page) ? 'active' : '';
                        echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $view->url->to('news/admin/category', ['page' => $i]) . '">' . $i . '</a></li>';
                    }
                    echo '</ul></nav>';
                }
            } else {
                echo '<div class="alert alert-info">No categories found. <a href="' . $view->url->to('news/admin/category/create') . '">Create one</a>.</div>';
            }
        }, 'admin');
    }

    public function createAction(): void
    {
        $this->editAction(true);
    }

    public function editAction(bool $isNew = false): void
    {
        if (!$this->auth->can('news.admin.category.edit')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        $id = (int) $this->request->get('id', 'int', 0);
        $category = $id ? $this->model->getCategory($id) : null;
        $error = null;

        if (!$isNew && !$category) {
            echo $this->view->inline(function () {
                echo '<div class="alert alert-danger">Category not found.</div>';
                echo '<a href="' . $this->url->to('news/admin/category') . '" class="btn btn-secondary">Back</a>';
            }, 'admin');
            return;
        }

        $parents = $this->model->getParentOptions($id);

        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $data['parent_id'] = (int) $this->request->post('parent_id', 'int', 0);
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateSlug($data['name']);
            }

            $saved = $this->model->saveCategory($id, $data);
            if ($saved) {
                Notify::success('Category saved.');
                redirect($this->url->to('news/admin/category'));
            } else {
                Notify::error('Failed to save category. Please check the input.');
            }
        }

        echo $this->view->inline(function ($view) use ($category, $parents, $isNew, $error) {
            $title = $isNew ? 'New Category' : 'Edit Category';
            $actionUrl = $isNew
                ? $view->url->to('news/admin/category/create')
                : $view->url->to('news/admin/category/edit', ['id' => $category['id']]);

            echo '<h2>' . $title . '</h2>';

            if ($error) {
                echo '<div class="alert alert-danger">' . escape($error) . '</div>';
            }

            echo $view->form->open(['url' => $actionUrl, 'method' => 'POST']);

            echo $view->form->input('name', [
                'label' => 'Category Name',
                'value' => $category['name'] ?? '',
                'required' => true,
            ]);

            echo $view->form->input('slug', [
                'label' => 'Slug (URL-friendly)',
                'value' => $category['slug'] ?? '',
                'help' => 'Leave empty to auto-generate from name.',
            ]);

            echo $view->form->textarea('description', [
                'label' => 'Description',
                'value' => $category['description'] ?? '',
                'rows' => 4,
            ]);

            echo $view->form->select('parent_id', $parents, $category['parent_id'] ?? 0, [
                'label' => 'Parent Category',
                'placeholder' => '— None —',
            ]);

            echo $view->form->submit('Save', ['class' => 'btn btn-primary']);
            echo '<a href="' . $view->url->to('news/admin/category') . '" class="btn btn-secondary ms-2">Cancel</a>';

            echo $view->form->close();
        }, 'admin');
    }

    public function deleteAction(): void
    {
        if (!$this->auth->can('news.admin.category.delete')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        if (!$this->form->checkToken()) {
            Notify::error('Invalid security token.');
            redirect($this->url->to('news/admin/category'));
            return;
        }

        $id = (int) $this->request->post('id', 'int', 0);
        if ($id) {
            $success = $this->model->deleteCategory($id);
            if ($success) {
                Notify::success('Category deleted.');
            } else {
                Notify::error('Failed to delete category. It may have sub-categories or articles.');
            }
        }
        redirect($this->url->to('news/admin/category'));
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'category-' . time();
    }
}