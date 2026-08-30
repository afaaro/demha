<?php

use System\Engine\Controller;
use System\Library\Notify;

class DacwahAdminSeries extends Controller
{
    public function indexAction()
    {
        $model = $this->load->model('dacwah/series');

        $page   = (int) ($this->request->get('page', 'int', 1));
        $search = $this->request->get('search', 'string', null);
        $status = $this->request->get('status', 'string', null);

        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($status) $filters['status'] = $status;

        // ✅ Get data
        $series = $model->getAll($filters, $page, 20);

        // ✅ DEBUG: check what getAll returns
        if (!is_array($series)) {
            $series = [];
        }

        echo $this->view->inline(function () use ($series) {

            echo "<div class=\"container\">";
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
            echo "<h2>Series</h2>";
            echo "<a href=\"" . $this->url->to('dacwah/admin/series/create') . "\" class=\"btn btn-primary\">+ Add Series</a>";
            echo "</div>";

            echo "<div class=\"card\">";
            echo "<div class=\"card-body p-0\">";
            echo "<table class=\"table table-striped mb-0\">";
            echo "<thead><tr><th>Title</th><th>Scholar</th><th>Category</th><th>Status</th><th>Episodes</th><th style=\"width:140px\"></th></tr></thead>";
            echo "<tbody>";

            // ✅ Count properly
            if (empty($series)) {
                echo "<tr><td colspan=\"6\" class=\"text-center text-muted py-4\">No series added yet.</td></tr>";
            } else {
                foreach ($series as $item) {
                    echo "<tr>";
                    echo "<td><strong>" . escape($item['title'] ?? '') . "</strong></td>";
                    echo "<td>" . escape($item['scholar_name'] ?? '-') . "</td>";
                    echo "<td>" . escape($item['category_name'] ?? '-') . "</td>";
                    echo "<td><span class=\"badge bg-" . ($item['status'] === 'published' ? 'success' : 'secondary') . "\">";
                    echo escape($item['status'] ?? '-') . "</span></td>";
                    echo "<td>" . (int) ($item['total_episodes'] ?? 0) . "</td>";
                    echo "<td class=\"text-end\">";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/series/edit', ['id' => (int)($item['id'] ?? 0)]) . "\" class=\"btn btn-sm btn-outline-secondary\">Edit</a> ";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/series/delete', ['id' => (int)($item['id'] ?? 0)]) . "\" class=\"btn btn-sm btn-outline-danger\" onclick=\"return confirm('Delete this series?')\">Del</a>";
                    echo "</td></tr>";
                }
            }

            echo "</tbody></table></div></div></div>";

        }, 'admin');
    }

    public function createAction()
    {
        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/series/store'))
             ->setMethod('POST')
             ->setRules([
                 'title'       => 'required|min:3|max:200',
                 'scholar_id'  => 'required|numeric',
                 'category_id' => 'required|numeric',
             ]);

        if ($form->isValid()) {
            $model = $this->load->model('dacwah/series');
            $data = $form->validated();
            $data['slug'] = !empty($data['slug']) ? $data['slug'] : slug($data['title']);
            $data['status'] = $data['status'] ?? 'draft';
            $result = $model->save($data);
            if ($result['success']) {
                Notify::success('Series added successfully.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/series/edit', ['id' => $result['id']]));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Add Series</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('title', [
                'label'       => 'Title *',
                'rules'       => 'required|min:3|max:200',
                'placeholder' => 'e.g. The Fundamentals of Tawhid',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('slug', [
                'label'       => 'Slug *',
                'rules'       => 'min:3|max:200|alpha_dash',
                'placeholder' => 'e.g. fundamentals-of-tawhid',
            ]) . "</div></div>";

            $scholars = $this->db->query("SELECT id, name FROM #__dacwah_scholars ORDER BY name ASC")->pairs;
            $categories = $this->db->query("SELECT id, name FROM #__dacwah_categories ORDER BY name ASC")->pairs;
            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->select('scholar_id', $scholars, null, [
                'label' => 'Scholar *',
                'rules' => 'required|numeric',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('category_id', $categories, null, [
                'label' => 'Category *',
                'rules' => 'required|numeric',
            ]) . "</div></div>";

            echo $form->textarea('description', [
                'label'       => 'Description',
                'rows'        => 4,
                'placeholder' => 'Brief description of the series...',
            ]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('cover_image', [
                'label'       => 'Cover Image URL',
                'placeholder' => '/uploads/series/xxx.jpg',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('status', [
                'draft'     => 'Draft',
                'published' => 'Published',
            ], 'draft', ['label' => 'Status']) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/series') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Save Series');
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
        $model = $this->load->model('dacwah/series');
        $series = $model->getById($id);

        if (!$series) {
            Notify::error('Series not found.');
            redirect($this->url->to('dacwah/admin/series'));
        }

        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/series/update', ['id' => $id]))
             ->setMethod('POST')
             ->fill($series)
             ->setRules([
                 'title'       => 'required|min:3|max:200',
                 'slug'        => 'required|min:3|max:200|alpha_dash',
                 'scholar_id'  => 'required|numeric',
                 'category_id' => 'required|numeric',
             ]);

        if ($form->isValid()) {
            $data = $form->validated();
            $data['id'] = $id;
            $result = $model->save($data);
            if ($result['success']) {
                Notify::success('Series updated.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/series/edit', ['id' => $id]));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Edit Series</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('title', [
                'label' => 'Title *',
                'rules' => 'required|min:3|max:200',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('slug', [
                'label' => 'Slug *',
                'rules' => 'required|min:3|max:200|alpha_dash',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->select('scholar_id', [], null, [
                'label' => 'Scholar *',
                'rules' => 'required|numeric',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('category_id', [], null, [
                'label' => 'Category *',
                'rules' => 'required|numeric',
            ]) . "</div></div>";

            echo $form->textarea('description', [
                'label' => 'Description',
                'rows'  => 4,
            ]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('cover_image', ['label' => 'Cover Image URL']) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('status', [
                'draft'     => 'Draft',
                'published' => 'Published',
            ], null, ['label' => 'Status']) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/series') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Update Series');
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
        $model = $this->load->model('dacwah/series');
        $result = $model->delete($id);

        if ($result['success']) {
            Notify::success('Series deleted.');
        } else {
            Notify::error($result['errors'][0] ?? 'Delete failed.');
        }
        redirect($this->url->to('dacwah/admin/series'));
    }
}