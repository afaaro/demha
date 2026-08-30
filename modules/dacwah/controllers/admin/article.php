<?php

use System\Engine\Controller;
use System\Library\Notify;

class DacwahAdminArticle extends Controller
{
    public function indexAction()
    {
        $model = $this->load->model('dacwah/article');

        $page      = (int) ($this->request->get('page', 'int', 1));
        $search    = $this->request->get('search');
        $status    = $this->request->get('status');
        $scholar   = $this->request->get('scholar_id');
        $category  = $this->request->get('category_id');

        $filters = [];
        if ($search)    $filters['search']     = $search;
        if ($status)    $filters['status']     = $status;
        if ($scholar)   $filters['scholar_id']  = $scholar;
        if ($category)  $filters['category_id'] = $category;

        $articles = $model->getAll($filters, $page, 20);

        echo $this->view->inline(function () use ($articles) {

            echo "<div class=\"container\">";
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
            echo "<h2>Articles</h2>";
            echo "<a href=\"" . $this->url->to('dacwah/admin/article/create') . "\" class=\"btn btn-primary\">+ Add Article</a>";
            echo "</div>";

            echo "<div class=\"card\">";
            echo "<div class=\"card-body p-0\">";
            echo "<table class=\"table table-striped mb-0\">";
            echo "<thead><tr><th>Title</th><th>Author/Scholar</th><th>Category</th><th>Published</th><th>Status</th><th style=\"width:140px\"></th></tr></thead>";
            echo "<tbody>";

            if (!$articles) {
                echo "<tr><td colspan=\"6\" class=\"text-center text-muted py-4\">No articles added yet.</td></tr>";
            } else {
                foreach ($articles as $item) {
                    echo "<tr>";
                    echo "<td><strong>" . escape($item['title']) . "</strong></td>";
                    echo "<td>" . escape($item['scholar_name'] ?? $item['author'] ?? '-') . "</td>";
                    echo "<td>" . escape($item['category_name'] ?? '-') . "</td>";
                    echo "<td>" . escape($item['published_at'] ?? '-') . "</td>";
                    echo "<td><span class=\"badge bg-" . ($item['status'] === 'published' ? 'success' : 'secondary') . "\">";
                    echo escape($item['status']) . "</span></td>";
                    echo "<td class=\"text-end\">";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/article/edit', ['id' => (int)$item['id']]) . "\" class=\"btn btn-sm btn-outline-secondary\">Edit</a> ";
                    echo "<a href=\"" . $this->url->to('dacwah/admin/article/delete', ['id' => (int)$item['id']]) . "\" class=\"btn btn-sm btn-outline-danger\" onclick=\"return confirm('Delete this article?')\">Del</a>";
                    echo "</td></tr>";
                }
            }

            echo "</tbody></table></div></div></div>";

        }, 'admin');
    }

    public function createAction()
    {
        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/article/store'))
             ->setMethod('POST')
             ->setRules([
                 'title'       => 'required|min:5|max:255',
                 'slug'        => 'required|min:5|max:255|alpha_dash',
                 'content'     => 'required|min:20',
             ]);

        if ($form->isValid()) {
            $model = $this->load->model('dacwah/article');
            $result = $model->save($form->validated());
            if ($result['success']) {
                Notify::success('Article added successfully.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/article/edit', ['id' => $result['id']]));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Add Article</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('title', [
                'label'       => 'Title *',
                'rules'       => 'required|min:5|max:255',
                'placeholder' => 'e.g. The Virtues of Patience in Islam',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('slug', [
                'label'       => 'Slug *',
                'rules'       => 'min:5|max:255|alpha_dash',
                'placeholder' => 'e.g. virtues-of-patience',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('author', [
                'label'       => 'Author Name',
                'placeholder' => 'e.g. Shaykh Al-Islam Ibn Taymiyyah',
            ]) . "</div>";
            $scholars = $this->db->query("SELECT id, name FROM dacwah_scholars ORDER BY name ASC")->pairs;
            echo "<div class=\"col-md-6\">" . $form->select('scholar_id', $scholars, null, [
                'label' => 'Associated Scholar',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            $categories = $this->db->query("SELECT id, name FROM dacwah_categories ORDER BY name ASC")->pairs;
            echo "<div class=\"col-md-6\">" . $form->select('category_id', $categories, null, [
                'label' => 'Category',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('published_at', [
                'label'       => 'Publish Date',
                'type'        => 'date',
            ]) . "</div></div>";

            echo $form->textarea('excerpt', [
                'label'       => 'Excerpt / Summary',
                'rows'        => 3,
                'placeholder' => 'Short summary shown on listings...',
            ]);

            echo $form->textarea('content', [
                'label'       => 'Content *',
                'rows'        => 12,
                'rules'       => 'required|min:20',
                'placeholder' => 'Full article content...',
            ]);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('featured_image', [
                'label'       => 'Featured Image URL',
                'placeholder' => '/uploads/articles/xxx.jpg',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('status', [
                'draft'     => 'Draft',
                'published' => 'Published',
            ], 'draft', ['label' => 'Status']) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/article') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Save Article');
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
        $model = $this->load->model('dacwah/article');
        $article = $model->getById($id);

        if (!$article) {
            Notify::error('Article not found.');
            redirect($this->url->to('dacwah/admin/article'));
        }

        $form = $this->form;
        $form->setAction($this->url->to('dacwah/admin/article/update', ['id' => $id]))
             ->setMethod('POST')
             ->fill($article)
             ->setRules([
                 'title'   => 'required|min:5|max:255',
                 'slug'    => 'required|min:5|max:255|alpha_dash',
                 'content' => 'required|min:20',
             ]);

        if ($form->isValid()) {
            $data = $form->validated();
            $data['id'] = $id;
            $result = $model->save($data);
            if ($result['success']) {
                Notify::success('Article updated.');
                $form->clearOldInput();
                $form->clearFlashedErrors();
                redirect($this->url->to('dacwah/admin/article/edit', ['id' => $id]));
            }
            $form->flashErrors($result['errors']);
        }

        echo $this->view->inline(function () use ($form) {
            echo $form->open();
            echo "<div class=\"container\">";

            echo "<h2 class=\"mb-4\">Edit Article</h2>";
            echo "<div class=\"card\"><div class=\"card-body\">";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('title', [
                'label' => 'Title *',
                'rules' => 'required|min:5|max:255',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('slug', [
                'label' => 'Slug *',
                'rules' => 'required|min:5|max:255|alpha_dash',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('author', ['label' => 'Author Name']) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('scholar_id', [], null, [
                'label' => 'Associated Scholar',
            ]) . "</div></div>";

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->select('category_id', [], null, [
                'label' => 'Category',
            ]) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->input('published_at', ['label' => 'Publish Date', 'type' => 'date']) . "</div></div>";

            echo $form->textarea('excerpt', ['label' => 'Excerpt / Summary', 'rows' => 3]);
            echo $form->textarea('content', ['label' => 'Content *', 'rows' => 12, 'rules' => 'required|min:20']);

            echo "<div class=\"row mb-3\">";
            echo "<div class=\"col-md-6\">" . $form->input('featured_image', ['label' => 'Featured Image URL']) . "</div>";
            echo "<div class=\"col-md-6\">" . $form->select('status', [
                'draft'     => 'Draft',
                'published' => 'Published',
            ], null, ['label' => 'Status']) . "</div></div>";

            echo "<div class=\"d-flex\">";
            echo "<a href=\"" . $this->url->to('dacwah/admin/article') . "\" class=\"btn btn-outline-secondary me-2\">Cancel</a>";
            echo $form->submit('Update Article');
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
        $model = $this->load->model('dacwah/article');
        $result = $model->delete($id);

        if ($result['success']) {
            Notify::success('Article deleted.');
        } else {
            Notify::error($result['errors'][0] ?? 'Delete failed.');
        }
        redirect($this->url->to('dacwah/admin/article'));
    }
}