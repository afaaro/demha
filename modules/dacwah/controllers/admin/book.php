<?php

use System\Engine\Controller;
use System\Library\Notify;

class DacwahAdminBook extends Controller
{
    public function indexAction()
    {
        $model = $this->load->model('dacwah/book');

        $page      = (int) ($this->request->get('page', 'int', 1));
        $search    = $this->request->get('search');
        $status    = $this->request->get('status');
        $scholar   = $this->request->get('scholar_id');

        $filters = [];
        if ($search)    $filters['search']    = $search;
        if ($status)    $filters['status']    = $status;
        if ($scholar)   $filters['scholar_id'] = $scholar;

        $books = $model->getAll($filters, $page, 20);

        echo $this->view->inline(function () use ($books) {

            echo "<div class=\"container\">";
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
            echo "<h2>Books</h2>";
            echo "<a href=\"" . $this->url->to('dacwah/admin/book/create') . "\" class=\"btn btn-primary\">+ Add Book</a>";
            echo "</div>";

            echo "<div class=\"card\">";
            echo "<div class=\"card-body p-0\">";
            echo "<table class=\"table table-striped mb-0\">";
            echo "<thead><tr><th>Title</th><th>Author/Scholar</th><th>Publisher</th><th>Year</th><th>Status</th><th style=\"width:140px\"></th></tr></thead>";
            echo "<tbody>";

            if (!$books) {
                echo "<tr><td colspan=\"6\" class=\"text-center text-muted py-4\">No books added yet.</td></tr>";
            } else {
                foreach ($books as $item) {
                    echo "<tr>";
                    echo "<td><strong>" . escape($item['title']) . "</strong></td>";
                    echo "<td>" . escape($item['scholar_name'] ?? $item['author'] ?? '-') . "</td>";
                    echo "<td>" . escape($item['publisher'] ?? '-') . "</td>";
                    echo "<td>" . escape($item['published_year'] ?? '-') . "</td>";
                    echo "<td><span class=\"badge bg-" . ($item['status'] === 'published' ? 'success' : 'secondary') . "\">";
                    echo escape($item['status']) . "</span></td>";
                    echo "<td class=\"text-end\">";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/book/edit', ['id' => (int)$item['id']]) . "\" class=\"btn btn-sm btn-outline-secondary\">Edit</a> ";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/book/delete', ['id' => (int)$item['id']]) . "\" class=\"btn btn-sm btn-outline-danger\" onclick=\"return confirm('Delete this book?')\">Del</a>";
                    echo "</td></tr>";
                }
            }

            echo "</tbody></table></div></div></div>";

        }, 'admin');
    }

    public function createAction()
    {
        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/book/store'))
             ->setMethod('POST')
             ->setRules([
                 'title'       => 'required|min:3|max:255',
                 'slug'        => 'min:3|max:255|alpha_dash',
                 'author'      => 'required|min:2|max:255',
             ]);

        if ($form->isValid()) {
            $model = $this->load->model('dacwah/book');
            $result = $model->save($form->validated());
            if ($result['success']) {
                Notify::success('Book added successfully.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/book/edit', ['id' => $result['id']]));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Add Book</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('title', [
                'label'       => 'Title *',
                'rules'       => 'required|min:3|max:255',
                'placeholder' => 'e.g. The Book of Tawhid',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('slug', [
                'label'       => 'Slug *',
                'rules'       => 'required|min:3|max:255|alpha_dash',
                'placeholder' => 'e.g. book-of-tawhid',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('author', [
                'label'       => 'Author *',
                'rules'       => 'required|min:2|max:255',
                'placeholder' => 'e.g. Muhammad ibn Abdul-Wahhab',
            ]) . "</div>";
            $scholars = $this->db->query("SELECT id, name FROM dacwah_scholars ORDER BY name ASC")->pairs;
            echo "<div class=\"col-md-6\">" . $form->select('scholar_id', $scholars, null, [
                'label' => 'Associated Scholar',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('publisher', [
                'label'       => 'Publisher',
                'placeholder' => 'e.g. Dar-us-Salam',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('published_year', [
                'label'       => 'Year Published',
                'type'        => 'number',
                'placeholder' => '2010',
            ]) . "</div></div>";

            echo $form->textarea('description', [
                'label'       => 'Description',
                'rows'        => 4,
                'placeholder' => 'Brief summary or description...',
            ]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('cover_image', [
                'label'       => 'Cover Image URL',
                'placeholder' => '/uploads/books/xxx.jpg',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('download_url', [
                'label'       => 'Download URL',
                'type'        => 'url',
                'placeholder' => 'https://...pdf',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->select('status', [
                'draft'     => 'Draft',
                'published' => 'Published',
            ], 'draft', ['label' => 'Status']) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/book') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Save Book');
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
        $model = $this->load->model('dacwah/book');
        $book = $model->getById($id);

        if (!$book) {
            Notify::error('Book not found.');
            redirect($this->url->to('dacwah/admin/book'));
        }

        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/book/update', ['id' => $id]))
             ->setMethod('POST')
             ->fill($book)
             ->setRules([
                 'title'  => 'required|min:3|max:255',
                 'slug'   => 'required|min:3|max:255|alpha_dash',
                 'author' => 'required|min:2|max:255',
             ]);

        if ($form->isValid()) {
            $data = $form->validated();
            $data['id'] = $id;
            $result = $model->save($data);
            if ($result['success']) {
                Notify::success('Book updated.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/book/edit', ['id' => $id]));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Edit Book</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('title', [
                'label' => 'Title *',
                'rules' => 'required|min:3|max:255',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('slug', [
                'label' => 'Slug *',
                'rules' => 'required|min:3|max:255|alpha_dash',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('author', [
                'label' => 'Author *',
                'rules' => 'required|min:2|max:255',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('scholar_id', [], null, [
                'label' => 'Associated Scholar',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('publisher', ['label' => 'Publisher']) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('published_year', ['label' => 'Year Published', 'type' => 'number']) . "</div></div>";

            echo $form->textarea('description', ['label' => 'Description', 'rows' => 4]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('cover_image', ['label' => 'Cover Image URL']) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('download_url', ['label' => 'Download URL', 'type' => 'url']) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->select('status', [
                'draft'     => 'Draft',
                'published' => 'Published',
            ], null, ['label' => 'Status']) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/book') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Update Book');
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
        $model = $this->load->model('dacwah/book');
        $result = $model->delete($id);

        if ($result['success']) {
            Notify::success('Book deleted.');
        } else {
            Notify::error($result['errors'][0] ?? 'Delete failed.');
        }
        redirect($this->url->to('dacwah/admin/book'));
    }
}