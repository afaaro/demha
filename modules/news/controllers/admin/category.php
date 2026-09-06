<?php
use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class NewsAdminCategory extends Controller
{
    protected $categories = [];
    protected object $model;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->model = $this->load->model('news/admin/category');
        $this->categories = $this->model->getCategories(1000, 0);
    }

    public function indexAction(): void
    {
        $limit = (int) $this->request->get('limit', 'int', 20);
        $offset = (int) $this->request->get('offset', 'int', 0);
        $categories = $this->model->getCategories($limit, $offset);
        $tree = $this->buildCategoryTree($categories);
        $flat = $this->flattenCategoryPaths($tree);

        echo $this->view->inline(function ($view) use ($flat) {
            echo "<div class='d-flex justify-content-between mb-3'>";
            echo "<h3>Categories</h3>";
            echo "<a class='btn btn-primary' href='" . $view->url->to('news/admin/category/create') . "'>Add Category</a>";
            echo "</div>";

            if (empty($flat)) {
                echo "<div class='alert alert-info'>No categories found.</div>";
            } else {
                echo "<ul class='list-group'>";
                foreach ($flat as $cat) {
                    echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                    echo "<span>" . htmlspecialchars($cat['path']) . "</span>";
                    echo "<div class='btn-group'>";
                        echo "<a class='btn btn-sm btn-outline-primary' href='" . $view->url->to('news/admin/category/edit', ['id' => $cat['id']]) . "'>Edit</a>";
                        echo "<a class='btn btn-sm btn-outline-danger' href='" . $view->url->to('news/admin/category/delete', ['id' => $cat['id']]) . "'>Delete</a>";
                    echo "</div>";
                    echo "</li>";
                }
                echo "</ul>";
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

        $parents = $this->getParentOptions($category['id'] ?? null);

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

    private function buildCategoryTree(array $categories, ?int $parentId = null): array
    {
        $branch = [];
        foreach ($categories as $cat) {
            if ((int) $cat['parent_id'] === (int) $parentId) {
                $children = $this->buildCategoryTree($categories, (int) $cat['id']);
                if (!empty($children)) {
                    $cat['children'] = $children;
                }
                $branch[] = $cat;
            }
        }
        return $branch;
    }

    private function flattenCategoryPaths(array $tree, string $prefix = ''): array
    {
        $result = [];
        foreach ($tree as $cat) {
            $fullPath = $prefix ? "$prefix → {$cat['name']}" : $cat['name'];
            $result[] = [
                'id'   => (int) $cat['id'],
                'path' => $fullPath,
            ];
            if (!empty($cat['children'])) {
                $result = array_merge($result, $this->flattenCategoryPaths($cat['children'], $fullPath));
            }
        }
        return $result;
    }

    private function getParentOptions(?int $excludeId = null): array
    {
        $tree = $this->buildCategoryTree($this->categories);
        $flat = $this->flattenCategoryPaths($tree);
        $options = ['' => '-- None --'];

        foreach ($flat as $cat) {
            if ($excludeId !== null && (int) $cat['id'] === $excludeId) {
                continue;
            }
            $options[(int) $cat['id']] = $cat['path'];
        }
        return $options;
    }
}