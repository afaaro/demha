<?php

use System\Engine\Controller;
use System\Library\Notify;

class DacwahAdminLecture extends Controller
{
    public function indexAction()
    {
        $model = $this->load->model('dacwah/lecture');

        $page      = (int) ($this->request->get('page', 'int', 1));
        $search    = $this->request->get('search');
        $status    = $this->request->get('status');
        $scholar   = $this->request->get('scholar_id');
        $series    = $this->request->get('series_id');

        $filters = [];
        if ($search)    $filters['search']    = $search;
        if ($status)    $filters['status']    = $status;
        if ($scholar)   $filters['scholar_id'] = $scholar;
        if ($series)    $filters['series_id']  = $series;

        $lectures = $model->getAll($filters, $page, 20);

        echo $this->view->inline(function () use ($lectures) {

            echo "<div class=\"container\">";
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
            echo "<h2>Lectures</h2>";
            echo "<a href=\"" . $this->url->to('dacwah/admin/lecture/create') . "\" class=\"btn btn-primary\">+ Add Lecture</a>";
            echo "</div>";

            echo "<div class=\"card\">";
            echo "<div class=\"card-body p-0\">";
            echo "<table class=\"table table-striped mb-0\">";
            echo "<thead><tr><th>Title</th><th>Scholar</th><th>Series</th><th>Date</th><th>Status</th><th style=\"width:140px\"></th></tr></thead>";
            echo "<tbody>";

            if (!$lectures) {
                echo "<tr><td colspan=\"6\" class=\"text-center text-muted py-4\">No lectures added yet.</td></tr>";
            } else {
                foreach ($lectures as $item) {
                    echo "<tr>";
                    echo "<td><strong>" . escape($item['title']) . "</strong></td>";
                    echo "<td>" . escape($item['scholar_name'] ?? '-') . "</td>";
                    echo "<td>" . escape($item['series_title'] ?? '-') . "</td>";
                    echo "<td>" . escape($item['lecture_date'] ?? '-') . "</td>";
                    echo "<td><span class=\"badge bg-" . ($item['status'] === 'published' ? 'success' : 'secondary') . "\">";
                    echo escape($item['status']) . "</span></td>";
                    echo "<td class=\"text-end\">";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/lecture/edit', ['id' => (int)$item['id']]) . "\" class=\"btn btn-sm btn-outline-secondary\">Edit</a> ";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/lecture/delete', ['id' => (int)$item['id']]) . "\" class=\"btn btn-sm btn-outline-danger\" onclick=\"return confirm('Delete this lecture?')\">Del</a>";
                    echo "</td></tr>";
                }
            }

            echo "</tbody></table></div></div></div>";

        }, 'admin');
    }

    public function createAction()
    {
        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/lecture/store'))
             ->setMethod('POST')
             ->setRules([
                 'title'        => 'required|min:3|max:255',
                 'slug'         => 'min:3|max:255|alpha_dash',
                 'scholar_id'   => 'required|numeric',
                 'category_id'  => 'required|numeric',
                 'lecture_date' => 'date',
             ]);

        if ($form->isValid()) {
            $model = $this->load->model('dacwah/lecture');
            $result = $model->save($form->validated());
            if ($result['success']) {
                Notify::success('Lecture added successfully.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/lecture'));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Add Lecture</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('title', [
                'label'       => 'Title *',
                'rules'       => 'required|min:3|max:255',
                'placeholder' => 'e.g. The Importance of Patience',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('slug', [
                'label'       => 'Slug *',
                'rules'       => 'min:3|max:255|alpha_dash',
                'placeholder' => 'e.g. importance-of-patience',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            $scholars = $this->db->query("SELECT id, name FROM dacwah_scholars ORDER BY name ASC")->pairs;
            echo "<div class=\"col-md-6\">" . $form->select('scholar_id', $scholars, null, [
                'label' => 'Scholar *',
                'rules' => 'required|numeric',
            ]) . "</div>";
            
            $categories= $this->db->query("SELECT id, name FROM dacwah_categories ORDER BY name ASC")->pairs;
            echo "<div class=\"col-md-6\">" . $form->select('category_id', $categories, null, [
                'label' => 'Category *',
                'rules' => 'required|numeric',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            $series = $this->db->query("SELECT id, title FROM dacwah_series ORDER BY title ASC")->pairs;
            echo "<div class=\"col-md-6\">" . $form->select('series_id', $series, null, [
                'label' => 'Series',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('lecture_date', [
                'label'       => 'Lecture Date *',
                'type'        => 'date',
                'rules'       => 'date',
            ]) . "</div></div>";

            echo $form->textarea('description', [
                'label'       => 'Description',
                'rows'        => 4,
                'placeholder' => 'Brief description or summary...',
            ]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('video_url', [
                'label'       => 'Video URL',
                'type'        => 'url',
                'placeholder' => 'https://youtube.com/watch?v=...',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('audio_url', [
                'label'       => 'Audio URL',
                'type'        => 'url',
                'placeholder' => 'https://...mp3',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('duration', [
                'label'       => 'Duration (minutes)',
                'type'        => 'number',
                'placeholder' => '60',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('status', [
                'draft'     => 'Draft',
                'published' => 'Published',
            ], 'draft', ['label' => 'Status']) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/lecture') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Save Lecture');
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
        $model = $this->load->model('dacwah/lecture');
        $lecture = $model->getById($id);

        if (!$lecture) {
            Notify::error('Lecture not found.');
            redirect($this->url->to('dacwah/admin/lecture'));
        }

        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/lecture/update', ['id' => $id]))
             ->setMethod('POST')
             ->fill($lecture)
             ->setRules([
                 'title'        => 'required|min:3|max:255',
                 'slug'         => 'min:3|max:255|alpha_dash',
                 'scholar_id'   => 'required|numeric',
                 'category_id'  => 'required|numeric',
                 'lecture_date' => 'date',
             ]);

        if ($form->isValid()) {
            $data = $form->validated();
            $data['id'] = $id;
            $result = $model->save($data);
            if ($result['success']) {
                Notify::success('Lecture updated.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/lecture'));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Edit Lecture</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('title', [
                'label' => 'Title *',
                'rules' => 'required|min:3|max:255',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('slug', [
                'label' => 'Slug *',
                'rules' => 'min:3|max:255|alpha_dash',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            $scholars = $this->db->query("SELECT id, name FROM dacwah_scholars ORDER BY name ASC")->pairs;
            echo "<div class=\"col-md-6\">" . $form->select('scholar_id', $scholars, null, [
                'label' => 'Scholar *',
                'rules' => 'required|numeric',
            ]) . "</div>";
            $categories= $this->db->query("SELECT id, name FROM dacwah_categories ORDER BY name ASC")->pairs;
            echo "<div class=\"col-md-6\">" . $form->select('category_id', $categories, null, [
                'label' => 'Category *',
                'rules' => 'required|numeric',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            $series = $this->db->query("SELECT id, title FROM dacwah_series ORDER BY title ASC")->pairs;
            echo "<div class=\"col-md-6\">" . $form->select('series_id', $series, null, [
                'label' => 'Series',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('lecture_date', [
                'label' => 'Lecture Date *',
                'type'  => 'date',
                'rules' => 'date',
            ]) . "</div></div>";

            echo $form->textarea('description', [
                'label' => 'Description',
                'rows'  => 4,
            ]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('video_url', ['label' => 'Video URL', 'type' => 'url']) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('audio_url', ['label' => 'Audio URL', 'type' => 'url']) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('duration', ['label' => 'Duration (minutes)', 'type' => 'number']) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('status', [
                'draft'     => 'Draft',
                'published' => 'Published',
            ], null, ['label' => 'Status']) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/lecture') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Update Lecture');
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
        $model = $this->load->model('dacwah/lecture');
        $result = $model->delete($id);

        if ($result['success']) {
            Notify::success('Lecture deleted.');
        } else {
            Notify::error($result['errors'][0] ?? 'Delete failed.');
        }
        redirect($this->url->to('dacwah/admin/lecture'));
    }
}