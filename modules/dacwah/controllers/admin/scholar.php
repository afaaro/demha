<?php

use System\Engine\Controller;
use System\Library\Notify;

class DacwahAdminScholar extends Controller
{
    public function indexAction()
    {
        $model = $this->load->model('dacwah/scholar');

        $page   = (int) ($this->request->get('page', 'int', 1));
        $search = $this->request->get('search');
        $status = $this->request->get('status');

        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($status) $filters['status'] = $status;

        $scholars = $model->getAll($filters, $page, 20);

        echo $this->view->inline(function () use ($scholars) {

            echo "<div class=\"container\">";
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
            echo "<h2>Scholars</h2>";
            echo "<a href=\"" . $this->url->to('dacwah/admin/scholar/create') . "\" class=\"btn btn-primary\">+ Add Scholar</a>";
            echo "</div>";

            echo "<div class=\"card\">";
            echo "<div class=\"card-body p-0\">";
            echo "<table class=\"table table-striped mb-0\">";
            echo "<thead><tr><th>Name</th><th>Country</th><th>Status</th><th>Lectures</th><th>Series</th><th style=\"width:140px\"></th></tr></thead>";
            echo "<tbody>";

            if (!$scholars) {
                echo "<tr><td colspan=\"6\" class=\"text-center text-muted py-4\">No scholars added yet.</td></tr>";
            } else {
                foreach ($scholars as $s) {
                    echo "<tr>";
                    echo "<td><strong>" . escape($s['name']) . "</strong>";
                    if (!empty($s['arabic_name'])) {
                        echo "<span class=\"text-muted ms-1\" dir=\"rtl\">" . escape($s['arabic_name']) . "</span>";
                    }
                    echo "</td>";
                    echo "<td>" . escape($s['country'] ?? '-') . "</td>";
                    echo "<td><span class=\"badge bg-" . ($s['status'] === 'active' ? 'success' : 'secondary') . "\">";
                    echo escape($s['status']) . "</span></td>";
                    echo "<td>" . (int)$s['total_lectures'] . "</td>";
                    echo "<td>" . (int)$s['total_series'] . "</td>";
                    echo "<td class=\"text-end\">";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/scholar/edit', ['id' => (int)$s['id']]) . "\" class=\"btn btn-sm btn-outline-secondary\">Edit</a> ";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/scholar/delete', ['id' => (int)$s['id']]) . "\" class=\"btn btn-sm btn-outline-danger\" onclick=\"return confirm('Delete this scholar?')\">Del</a>";
                    echo "</td></tr>";
                }
            }

            echo "</tbody></table></div></div></div>";

        }, 'admin');
    }

    public function createAction()
    {
        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/scholar/store'))
             ->setMethod('POST')
             ->setRules([
                 'name' => 'required|min:2|max:150',
             ]);

        if ($form->isValid()) {
            $model = $this->load->model('dacwah/scholar');
            $result = $model->save($form->validated());
            if ($result['success']) {
                Notify::success('Scholar added successfully.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/scholar'));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Add Scholar</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('name', [
                'label' => 'Name *',
                'rules' => 'required|min:2|max:150',
                'placeholder' => 'e.g. Shaykh Al-Albani',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('arabic_name', [
                'label' => 'Arabic Name',
                'dir'   => 'rtl',
                'placeholder' => 'محمد ناصر الدين الألباني',
            ]) . "</div></div>";

            echo $form->textarea('bio', [
                'label' => 'Biography',
                'rows'  => 4,
                'placeholder' => 'Brief biography...',
            ]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('country', [
                'label' => 'Country',
                'placeholder' => 'e.g. Saudi Arabia',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('status', [
                'active'   => 'Active',
                'inactive' => 'Inactive',
                'deceased' => 'Deceased',
            ], 'active', ['label' => 'Status']) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('photo', [
                'label' => 'Photo URL',
                'placeholder' => '/uploads/scholars/xxx.jpg',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('website', [
                'label' => 'Website',
                'type'  => 'url',
                'placeholder' => 'https://example.com',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-4\">" . $form->input('facebook', ['label' => 'Facebook']) . "</div>";
            echo "<div class=\"col-md-4\">" . $form->input('twitter', ['label' => 'Twitter / X']) . "</div>";
            echo "<div class=\"col-md-4\">" . $form->input('youtube', ['label' => 'YouTube']) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/scholar') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Save Scholar');
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
        $id    = (int) $this->request->get('id', 'int', 0);
        $model = $this->load->model('dacwah/scholar');
        $scholar = $model->getById($id);

        if (!$scholar) {
            Notify::error('Scholar not found.');
            redirect($this->url->to('dacwah/admin/scholar'));
        }

        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/scholar/update', ['id' => $id]))
             ->setMethod('POST')
             ->fill($scholar);

        if ($form->isValid()) {
            $data = $form->validated();
            $data['id'] = $id;
            $result = $model->save($data);
            if ($result['success']) {
                Notify::success('Scholar updated.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/scholar'));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Edit Scholar</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('name', [
                'label' => 'Name *',
                'rules' => 'required|min:2|max:150',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('arabic_name', [
                'label' => 'Arabic Name',
                'dir'   => 'rtl',
            ]) . "</div></div>";

            echo $form->input('slug', [
                'label' => 'URL Slug',
                'help'  => 'Auto-generated if left blank.',
            ]);

            echo $form->textarea('bio', ['label' => 'Biography', 'rows' => 4]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('country', ['label' => 'Country']) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('status', [
                'active'   => 'Active',
                'inactive' => 'Inactive',
                'deceased' => 'Deceased',
            ], null, ['label' => 'Status']) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('photo', ['label' => 'Photo URL']) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('email', ['label' => 'Email', 'type' => 'email']) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-4\">" . $form->input('facebook', ['label' => 'Facebook']) . "</div>";
            echo "<div class=\"col-md-4\">" . $form->input('twitter', ['label' => 'Twitter / X']) . "</div>";
            echo "<div class=\"col-md-4\">" . $form->input('youtube', ['label' => 'YouTube']) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/scholar') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Update Scholar');
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
        $id    = (int) $this->route->param(0);
        $model = $this->load->model('dacwah/scholar');
        $result = $model->delete($id);

        if ($result['success']) {
            Notify::success('Scholar deleted.');
        } else {
            Notify::error($result['errors'][0] ?? 'Delete failed.');
        }
        redirect($this->url->to('dacwah/admin/scholar'));
    }
}