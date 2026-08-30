<?php

use System\Engine\Controller;
use System\Library\Notify;

class DacwahAdminCategory extends Controller
{
    public function indexAction()
    {
        $model = $this->load->model('dacwah/category');

        $page   = (int) ($this->request->get('page', 'int', 1));
        $search = $this->request->get('search');

        $filters = [];
        if ($search) $filters['search'] = $search;

        $categories = $model->getAll($filters, $page, 20);

        echo $this->view->inline(function () use ($categories) {

            echo "<div class=\"container\">";
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
            echo "<h2>Categories</h2>";
            echo "<a href=\"" . $this->url->to('dacwah/admin/category/create') . "\" class=\"btn btn-primary\">+ Add Category</a>";
            echo "</div>";

            echo "<div class=\"card\">";
            echo "<div class=\"card-body p-0\">";
            echo "<table class=\"table table-striped mb-0\">";
            echo "<thead><tr><th>Name</th><th>Slug</th><th>Description</th><th>Sort Order</th><th style=\"width:140px\"></th></tr></thead>";
            echo "<tbody>";

            if (!$categories) {
                echo "<tr><td colspan=\"5\" class=\"text-center text-muted py-4\">No categories added yet.</td></tr>";
            } else {
                foreach ($categories as $cat) {
                    echo "<tr>";
                    echo "<td><strong>" . escape($cat['name']) . "</strong></td>";
                    echo "<td>" . escape($cat['slug']) . "</td>";
                    echo "<td>" . escape($cat['description'] ?? '-') . "</td>";
                    echo "<td>" . (int)$cat['sort_order'] . "</td>";
                    echo "<td class=\"text-end\">";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/category/edit', ['id' => (int)$cat['id']]) . "\" class=\"btn btn-sm btn-outline-secondary\">Edit</a> ";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/category/delete', ['id' => (int)$cat['id']]) . "\" class=\"btn btn-sm btn-outline-danger\" onclick=\"return confirm('Delete this category?')\">Del</a>";
                    echo "</td></tr>";
                }
            }

            echo "</tbody></table></div></div></div>";

        }, 'admin');
    }

    public function createAction()
    {
        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/category/store'))
             ->setMethod('POST')
             ->setRules([
                 'name'       => 'required|min:2|max:100',
                 'slug'       => 'required|min:2|max:100|alpha_dash',
                 'sort_order' => 'required|numeric|min:0',
             ]);

        if ($form->isValid()) {
            $model = $this->load->model('dacwah/category');
            $result = $model->save($form->validated());
            if ($result['success']) {
                Notify::success('Category added successfully.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/category/edit', ['id' => $result['id']]));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Add Category</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('name', [
                'label'       => 'Name *',
                'rules'       => 'required|min:2|max:100',
                'placeholder' => 'e.g. Lectures',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('slug', [
                'label'       => 'Slug *',
                'rules'       => 'required|min:2|max:100|alpha_dash',
                'placeholder' => 'e.g. lectures',
            ]) . "</div></div>";

            echo $form->textarea('description', [
                'label'       => 'Description',
                'rows'        => 4,
                'placeholder' => 'Brief description...',
            ]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('sort_order', [
                'label'       => 'Sort Order *',
                'type'        => 'number',
                'rules'       => 'required|numeric|min:0',
                'placeholder' => '0',
            ]) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/category') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Save Category');
            echo "</div></div></div></div>";

            echo $form->close();
        }, 'admin');
    }

    public function storeAction()
    {
        $this->createAction();
    }

    public function editAction()
    {
        $id    = (int) $this->request->get('id', 'int');
        $model = $this->load->model('dacwah/category');
        $category = $model->getById($id);

        if (!$category) {
            Notify::error('Category not found.');
            redirect($this->url->to('dacwah/admin/category'));
        }

        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/category/update', ['id' => $id]))
             ->setMethod('POST')
             ->fill($category)
             ->setRules([
                 'name'       => 'required|min:2|max:100',
                 'slug'       => 'required|min:2|max:100|alpha_dash',
                 'sort_order' => 'required|numeric|min:0',
             ]);

        if ($form->isValid()) {
            $data = $form->validated();
            $data['id'] = $id;
            $result = $model->save($data);
            if ($result['success']) {
                Notify::success('Category updated.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/category/edit', ['id' => $id]));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Edit Category</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('name', [
                'label' => 'Name *',
                'rules' => 'required|min:2|max:100',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('slug', [
                'label' => 'Slug *',
                'rules' => 'required|min:2|max:100|alpha_dash',
            ]) . "</div></div>";

            echo $form->textarea('description', [
                'label' => 'Description',
                'rows'  => 4,
            ]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('sort_order', [
                'label' => 'Sort Order *',
                'type'  => 'number',
                'rules' => 'required|numeric|min:0',
            ]) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/category') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Update Category');
            echo "</div></div></div></div>";

            echo $form->close();
        }, 'admin');
    }

    public function updateAction()
    {
        $this->editAction();
    }

    public function deleteAction()
    {
        $id    = (int) $this->request->get('id', 'int');
        $model = $this->load->model('dacwah/category');
        $result = $model->delete($id);

        if ($result['success']) {
            Notify::success('Category deleted.');
        } else {
            Notify::error($result['errors'][0] ?? 'Delete failed.');
        }
        redirect($this->url->to('dacwah/admin/category'));
    }
}