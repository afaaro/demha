<?php

use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class ShopAdminCategory extends Controller
{
    protected $categories = [];

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        
        $this->categories = $this->load->model('shop/category')->getAllCategories();
    }

    public function indexAction(): void
    {
        $categories = $this->categories;
        $tree = $this->buildCategoryTree($categories);
        $flat = $this->flattenCategoryPaths($tree);

        echo $this->view->inline(function ($view) use ($flat) {
            echo "<div class='d-flex justify-content-between mb-3'>";
            echo "<h3>Categories</h3>";
            echo "<a class='btn btn-primary' href='" . $view->url->to('shop/admin/category/add') . "'>Add Category</a>";
            echo "</div>";

            if (empty($flat)) {
                echo "<div class='alert alert-info'>No categories found.</div>";
            } else {
                echo "<ul class='list-group'>";
                foreach ($flat as $cat) {
                    echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                    echo "<span>" . htmlspecialchars($cat['path']) . "</span>";
                    echo "<div class='btn-group'>";
                        echo "<a class='btn btn-sm btn-outline-primary' href='" . $view->url->to('shop/admin/category/edit', ['id' => $cat['id']]) . "'>Edit</a>";
                        echo "<a class='btn btn-sm btn-outline-danger' href='" . $view->url->to('shop/admin/category/delete', ['id' => $cat['id']]) . "'>Delete</a>";
                    echo "</div>";
                    echo "</li>";
                }
                echo "</ul>";
            }
        }, 'admin');
    }

    public function addAction(): void
    {
        if ($this->request->isPost()) {
            $rules = [
                'name'        => 'required|min:2|unique:shop_categories,name',
                'parent_id'   => 'integer',
                'sort_order'  => 'integer',
                'status'      => 'required|in:1,0',
            ];

            $this->form->setRules($rules);
            $errors = $this->form->validate($this->request->post());

            if (empty($errors)) {
                $data = [
                    'parent_id'   => (int) $this->request->post('parent_id', 'int', 0) ?: null,
                    'name'        => $this->request->post('name'),
                    'slug'        => $this->generateSlug($this->request->post('name')),
                    'description' => $this->request->post('description'),
                    'sort_order'  => (int) $this->request->post('sort_order', 'int', 0),
                    'status'      => $this->request->post('status', 'int', 1),
                ];

                $this->db->insert('shop_categories', $data);
                Notify::success('Category created.');
                redirect($this->url->to('shop/admin/category'));
                return;
            } else {
                $this->form->setErrors($errors);
                Notify::error('Please correct the errors below.');
            }
        }

        echo $this->renderForm();
    }

    public function editAction(): void
    {
        $id = (int) $this->request->route('id');
        if (!$id) {
            Notify::error('Invalid category ID.');
            redirect($this->url->to('shop/admin/category'));
            return;
        }

        $categories = $this->categories;
        $category = null;
        foreach ($categories as $cat) {
            if ((int) $cat['id'] === $id) {
                $category = $cat;
                break;
            }
        }

        if (!$category) {
            $category = $this->db->first('shop_categories', ['id' => $id]);
        }

        if (!$category) {
            Notify::error('Category not found.');
            redirect($this->url->to('shop/admin/category'));
            return;
        }

        if ($this->request->isPost()) {
            $rules = [
                'name'        => 'required|min:2',
                'parent_id'   => 'integer',
                'sort_order'  => 'integer',
                'status'      => 'required|in:1,0',
            ];
            $this->form->setRules($rules);
            $errors = $this->form->validate($this->request->post());

            if (empty($errors)) {
                $data = [
                    'parent_id'   => (int) $this->request->post('parent_id', 'int', 0) ?: null,
                    'name'        => $this->request->post('name'),
                    'slug'        => $this->generateSlug($this->request->post('name'), $id),
                    'description' => $this->request->post('description'),
                    'sort_order'  => (int) $this->request->post('sort_order', 'int', 0),
                    'status'      => $this->request->post('status', 'int', 1),
                ];

                $this->db->update('shop_categories', $data, ['id' => $id]);
                Notify::success('Category updated.');
                redirect($this->url->to('shop/admin/category'));
                return;
            } else {
                $this->form->setErrors($errors);
                Notify::error('Please correct the errors below.');
            }
        }

        echo $this->renderForm($category);
    }

    public function deleteAction(): void
    {
        $id = (int) $this->request->route('id');
        if (!$id) {
            Notify::error('Invalid category ID.');
            redirect($this->url->to('shop/admin/category'));
            return;
        }

        $category = $this->db->first('shop_categories', ['id' => $id]);
        if (!$category) {
            Notify::error('Category not found.');
            redirect($this->url->to('shop/admin/category'));
            return;
        }

        // Check for child categories
        $childCount = $this->db->count('shop_categories', ['parent_id' => $id]);
        if ($childCount > 0) {
            Notify::error('Cannot delete category with child categories. Please reassign or delete child categories first.');
            redirect($this->url->to('shop/admin/category'));
            return;
        }

        // Check for products in this category
        $productCount = $this->db->count('shop_product', ['category_id' => $id]);
        if ($productCount > 0) {
            Notify::error('Cannot delete category with products assigned. Please reassign or delete products first.');
            redirect($this->url->to('shop/admin/category'));
            return;
        }

        // Proceed to delete
        $this->db->delete('shop_categories', ['id' => $id]);
        Notify::success('Category deleted successfully.');
        redirect($this->url->to('shop/admin/category'));
    }

    // ==============================
    // Helpers
    // ==============================

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

    private function generateSlug(string $name, ?int $excludeId = null): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name), '-'));
        $original = $slug;
        $counter = 1;

        while (true) {
            $existing = $this->db->first('shop_categories', ['slug' => $slug]);
            if (!$existing || ($excludeId && (int) $existing['id'] === $excludeId)) {
                break;
            }
            $slug = $original . '-' . $counter++;
        }
        return $slug;
    }

    /**
     * Render the add/edit form – FIXED to use the same Form instance.
     */
    private function renderForm(array $category = []): string
    {
        // Fill the form with existing data (if any)
        $this->form->fill($category);

        $isEdit = !empty($category['id']);
        $parentOptions = $this->getParentOptions($category['id'] ?? null);
        $statusOptions = [1 => 'Active', 0 => 'Hidden'];

        // Capture the current form instance so we can use it inside the closure
        $form = $this->form;

        return $this->view->inline(function ($view) use ($isEdit, $parentOptions, $statusOptions, $category, $form) {
            echo "<div class='d-flex justify-content-between mb-3'>";
            echo "<h3>" . ($isEdit ? 'Edit Category' : 'Add Category') . "</h3>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('shop/admin/category') . "'>Back to Categories</a>";
            echo "</div>";

            echo "<div class='card'>";
            echo "<div class='card-body'>";
            echo $form->start(['method' => 'POST']);

            echo $form->select('parent_id', $parentOptions, '', [
                'label'   => 'Parent Category'
            ]);

            echo $form->input('name', [
                'label' => 'Name',
                'rules' => 'required|min:2',
            ]);

            echo $form->textarea('description', [
                'label' => 'Description',
                'rows'  => 3,
            ]);

            echo $form->input('sort_order', [
                'label' => 'Sort Order',
                'type'  => 'number',
                'help'  => 'Lower numbers appear first.',
            ]);

            echo $form->select('status', $statusOptions, $category['status'] ?? 1, [
                'label'   => 'Status',
                'rules'   => 'required',
            ]);

            echo "<div class='mt-3'>";
            echo $form->submit($isEdit ? 'Update Category' : 'Create Category');
            echo "</div>";

            echo $form->end();
            echo "</div></div>";
        }, 'admin');
    }
}