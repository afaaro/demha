<?php
use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class NewsAdminTag extends Controller
{
    protected object $model;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->model = $this->load->model('news/admin/tag');
    }

    public function indexAction(): void
    {
        if (!$this->auth->can('news.admin.tag.view')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        $page = (int) $this->request->get('page', 'int', 1);
        $limit = 30;
        $offset = ($page - 1) * $limit;

        $tags = $this->model->getTags($limit, $offset);
        $total = $this->model->countTags();

        echo $this->view->inline(function ($view) use ($tags, $total, $page, $limit) {
            echo '<div class="d-flex justify-content-between align-items-center mb-3">';
            echo '  <h2>News Tags</h2>';
            echo '  <a href="' . $view->url->to('news/admin/tag/create') . '" class="btn btn-primary">';
            echo '    <i class="bi bi-plus-circle"></i> Add Tag';
            echo '  </a>';
            echo '</div>';

            if (count($tags) > 0) {
                echo '<div class="table-responsive">';
                echo '<table class="table table-striped table-hover">';
                echo '  <thead>';
                echo '    <tr>';
                echo '      <th>ID</th>';
                echo '      <th>Name</th>';
                echo '      <th>Slug</th>';
                echo '      <th>Actions</th>';
                echo '    </tr>';
                echo '  </thead>';
                echo '  <tbody>';

                foreach ($tags as $tag) {
                    echo '    <tr>';
                    echo '      <td>' . (int)$tag['tag_id'] . '</td>';
                    echo '      <td><a href="' . $view->url->to('news/admin/tag/edit', ['id' => $tag['tag_id']]) . '">' . escape($tag['name']) . '</a></td>';
                    echo '      <td>' . escape($tag['slug']) . '</td>';
                    echo '      <td>';
                    echo '        <a href="' . $view->url->to('news/admin/tag/edit', ['id' => $tag['tag_id']]) . '" class="btn btn-sm btn-outline-primary">Edit</a>';
                    echo '        <a href="' . $view->url->to('news/admin/tag/delete', ['id' => $tag['tag_id']]) . '" class="btn btn-sm btn-outline-danger" onclick="return confirm(\'Delete this tag?\')">Delete</a>';
                    echo '      </td>';
                    echo '    </tr>';
                }

                echo '  </tbody>';
                echo '</table>';
                echo '</div>';

                if ($total > $limit) {
                    echo '<nav><ul class="pagination">';
                    $pages = ceil($total / $limit);
                    for ($i = 1; $i <= $pages; $i++) {
                        $active = ($i == $page) ? 'active' : '';
                        echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $view->url->to('news/admin/tag', ['page' => $i]) . '">' . $i . '</a></li>';
                    }
                    echo '</ul></nav>';
                }
            } else {
                echo '<div class="alert alert-info">No tags found. <a href="' . $view->url->to('news/admin/tag/create') . '">Create one</a>.</div>';
            }
        }, 'admin');
    }

    public function createAction(): void
    {
        $this->editAction(true);
    }

    public function editAction(bool $isNew = false): void
    {
        if (!$this->auth->can('news.admin.tag.edit')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        $id = (int) $this->request->get('id', 'int', 0);
        $tag = $id ? $this->model->getTag($id) : null;
        $error = null;

        if (!$isNew && !$tag) {
            echo $this->view->inline(function () {
                echo '<div class="alert alert-danger">Tag not found.</div>';
                echo '<a href="' . $this->url->to('news/admin/tag') . '" class="btn btn-secondary">Back</a>';
            }, 'admin');
            return;
        }

        if ($this->request->isPost()) {
            if (!$this->form->checkToken()) {
                $error = 'Invalid security token.';
            } else {
                $name = trim($this->request->post('name', 'string', ''));
                $slug = trim($this->request->post('slug', 'string', ''));

                if (empty($slug)) {
                    $slug = $this->generateSlug($name);
                }

                if ($this->model->slugExists($slug, $id)) {
                    $error = 'Slug already exists. Please choose a different one.';
                } else {
                    $data = compact('name', 'slug');
                    $saved = $this->model->saveTag($id, $data);
                    if ($saved) {
                        Notify::success('Tag saved.');
                        redirect($this->url->to('news/admin/tag'));
                    } else {
                        $error = 'Failed to save tag. Please try again.';
                    }
                }
            }
        }

        echo $this->view->inline(function ($view) use ($tag, $isNew, $error) {
            $title = $isNew ? 'New Tag' : 'Edit Tag';
            $actionUrl = $isNew
                ? $view->url->to('news/admin/tag/create')
                : $view->url->to('news/admin/tag/edit', ['id' => $tag['tag_id']]);

            echo '<h2>' . $title . '</h2>';

            if ($error) {
                echo '<div class="alert alert-danger">' . escape($error) . '</div>';
            }

            echo $view->form->open(['url' => $actionUrl, 'method' => 'POST']);

            echo $view->form->input('name', [
                'label' => 'Tag Name',
                'value' => $tag['name'] ?? '',
                'required' => true,
            ]);

            echo $view->form->input('slug', [
                'label' => 'Slug (URL-friendly)',
                'value' => $tag['slug'] ?? '',
                'help' => 'Leave empty to auto-generate from name.',
            ]);

            echo $view->form->submit('Save', ['class' => 'btn btn-primary']);
            echo '<a href="' . $view->url->to('news/admin/tag') . '" class="btn btn-secondary ms-2">Cancel</a>';

            echo $view->form->close();
        }, 'admin');
    }

    public function deleteAction(): void
    {
        if (!$this->auth->can('news.admin.tag.delete')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        if (!$this->form->checkToken()) {
            Notify::error('Invalid security token.');
            redirect($this->url->to('news/admin/tag'));
            return;
        }

        $id = (int) $this->request->post('id', 'int', 0);
        if ($id) {
            $this->model->deleteTag($id);
            Notify::success('Tag deleted.');
        }
        redirect($this->url->to('news/admin/tag'));
    }

    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'tag-' . time();
    }
}