<?php
use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class NewsAdminArticle extends Controller
{
    protected object $model;

    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        $this->model = $this->load->model('news/admin/article');
    }

    /**
     * List all articles with stats, filters, and pagination.
     */
    public function indexAction(): void
    {
        if (!$this->auth->can('news.admin.article.view')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        $page = (int) $this->request->get('page', 'int', 1);
        $status = (int) $this->request->get('status', 'int', -1);
        $category = (int) $this->request->get('category', 'int', 0);
        $search = trim($this->request->get('search', 'string', ''));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        try {
            $articles = $this->model->getArticles($limit, $offset, $status, $category, $search);
            $total = $this->model->countArticles($status, $category, $search);
            $stats = $this->model->getStats();
            $categories = $this->model->getAllCategories();
        } catch (\Exception $e) {
            Notify::error('Failed to load articles: ' . $e->getMessage());
            $articles = [];
            $total = 0;
            $stats = ['total' => 0, 'published' => 0, 'drafts' => 0];
            $categories = [];
        }

        echo $this->view->inline(function ($view) use ($articles, $stats, $total, $page, $limit, $status, $category, $search, $categories) {
            echo '<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">';
            echo '  <div>';
            echo '    <h1 class="h3 mb-1">News Desk</h1>';
            echo '    <p class="text-muted mb-0">Manage stories, categories, and tags from one editorial dashboard.</p>';
            echo '  </div>';
            echo '  <div class="d-flex gap-2">';
            echo '    <a class="btn btn-outline-secondary btn-sm" href="' . $view->url->to('news/admin/category') . '">Categories</a>';
            echo '    <a class="btn btn-outline-secondary btn-sm" href="' . $view->url->to('news/admin/tags') . '">Tags</a>';
            echo '    <a class="btn btn-primary" href="' . $view->url->to('news/admin/article/create') . '">Create article</a>';
            echo '  </div>';
            echo '</div>';

            // Stats cards
            echo '<div class="row g-3 mb-4">';
            echo '  <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted small text-uppercase">Total articles</div><div class="fs-3 fw-bold">' . $stats['total'] . '</div></div></div>';
            echo '  <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted small text-uppercase">Published</div><div class="fs-3 fw-bold text-success">' . $stats['published'] . '</div></div></div>';
            echo '  <div class="col-md-4"><div class="border rounded p-3"><div class="text-muted small text-uppercase">Drafts</div><div class="fs-3 fw-bold text-secondary">' . $stats['drafts'] . '</div></div></div>';
            echo '</div>';

            // Filters using form helpers
            echo '<form method="GET" class="row g-3 mb-4">';
            echo '  <div class="col-md-3">';
            echo $view->form->select('status', [
                '-1' => 'All Status',
                '1' => 'Published',
                '0' => 'Draft',
            ], $status, ['class' => 'form-select form-select-sm', 'label' => false]);
            echo '  </div>';
            echo '  <div class="col-md-3">';
            $catOptions = ['0' => 'All Categories'];
            foreach ($categories as $cat) {
                $catOptions[$cat['id']] = $cat['name'];
            }
            echo $view->form->select('category', $catOptions, $category, ['class' => 'form-select form-select-sm', 'label' => false]);
            echo '  </div>';
            echo '  <div class="col-md-4">';
            echo '    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search articles..." value="' . $view->e($search) . '">';
            echo '  </div>';
            echo '  <div class="col-md-2">';
            echo '    <button type="submit" class="btn btn-primary btn-sm w-100">Filter</button>';
            echo '  </div>';
            echo '</form>';

            // Articles table
            echo '<div class="table-responsive">';
            echo '<table class="table table-hover align-middle">';
            echo '  <thead class="table-light">';
            echo '    <tr>';
            echo '      <th>Title</th>';
            echo '      <th>Category</th>';
            echo '      <th>Tags</th>';
            echo '      <th>Author</th>';
            echo '      <th>Status</th>';
            echo '      <th>Date</th>';
            echo '      <th class="text-end">Actions</th>';
            echo '    </tr>';
            echo '  </thead>';
            echo '  <tbody>';

            if (empty($articles)) {
                echo '<tr><td colspan="7" class="text-muted py-4">No news articles found.</td></tr>';
            } else {
                foreach ($articles as $article) {
                    $tags = $this->model->getArticleTags($article['id']);
                    $tagsDisplay = !empty($tags) ? implode(', ', array_column($tags, 'name')) : '<span class="text-muted">No tags</span>';
                    $statusBadge = $article['status'] == 1 ? 'bg-success' : 'bg-secondary';
                    $statusLabel = $article['status'] == 1 ? 'Published' : 'Draft';

                    echo '<tr>';
                    echo '  <td><strong><a href="' . $view->url->to('news/admin/article/edit', ['id' => $article['id']]) . '">' . $view->e($article['title']) . '</a></strong></td>';
                    echo '  <td>' . $view->e($article['category_name'] ?? 'General') . '</td>';
                    echo '  <td>' . $tagsDisplay . '</td>';
                    echo '  <td>' . $view->e($article['author_name'] ?? 'Unknown') . '</td>';
                    echo '  <td><span class="badge ' . $statusBadge . '">' . $statusLabel . '</span></td>';
                    echo '  <td>' . date('d M Y', strtotime($article['created_at'] ?? 'now')) . '</td>';
                    echo '  <td class="text-end">';
                    echo '    <div class="d-flex justify-content-end gap-2">';
                    echo '      <a class="btn btn-outline-primary btn-sm" href="' . $view->url->to('news/admin/article/edit', ['id' => $article['id']]) . '">Edit</a>';
                    echo '      <form method="POST" action="' . $view->url->to('news/admin/article/delete') . '" style="display:inline;" onsubmit="return confirm(\'Delete this article?\')">';
                    echo         $view->form->csrfField();
                    echo '        <input type="hidden" name="id" value="' . $article['id'] . '">';
                    echo '        <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>';
                    echo '      </form>';
                    echo '    </div>';
                    echo '  </td>';
                    echo '</tr>';
                }
            }

            echo '  </tbody>';
            echo '</table>';
            echo '</div>';

            // Pagination
            $pages = ceil($total / $limit);
            if ($pages > 1) {
                echo '<nav><ul class="pagination">';
                for ($i = 1; $i <= $pages; $i++) {
                    $active = ($i == $page) ? 'active' : '';
                    $params = ['page' => $i];
                    if ($status >= 0) $params['status'] = $status;
                    if ($category > 0) $params['category'] = $category;
                    if (!empty($search)) $params['search'] = $search;
                    echo '<li class="page-item ' . $active . '"><a class="page-link" href="' . $view->url->to('news/admin/article', $params) . '">' . $i . '</a></li>';
                }
                echo '</ul></nav>';
            }
        }, 'admin');
    }

    /**
     * Create a new article.
     */
    public function createAction(): void
    {
        if (!$this->auth->can('news.admin.article.create')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        $categories = $this->model->getAllCategories();
        $errors = [];

        if ($this->request->isPost()) {
            if (!$this->form->checkToken()) {
                Notify::error('Invalid security token.');
                redirect($this->url->to('news/admin/article'));
                return;
            }

            $title = trim($this->request->post('title', 'string', ''));
            $slug = trim($this->request->post('slug', 'string', ''));
            $body = trim($this->request->post('body', 'string', ''));
            $categoryId = (int) $this->request->post('category_id', 'int', 0);
            $status = (int) $this->request->post('status', 'int', 0);
            $isFeatured = (int) $this->request->post('is_featured', 'int', 0);
            $tagsCsv = trim($this->request->post('tags', 'string', ''));

            if ($title === '') { $errors[] = 'Title is required.'; }
            if ($body === '') { $errors[] = 'Body is required.'; }
            if ($categoryId <= 0) { $errors[] = 'Category is required.'; }
            if ($slug === '') { $slug = $this->model->makeSlug($title); }
            $slug = $this->model->makeSlug($slug);
            if ($slug === '') { $errors[] = 'A valid URL slug is required.'; }

            if (empty($errors)) {
                if ($this->model->slugExists($slug)) {
                    $slug = $slug . '-' . time();
                }

                $data = [
                    'title' => $title,
                    'slug' => $slug,
                    'body' => $body,
                    'category_id' => $categoryId,
                    'author_id' => $this->auth->data('id') ?? 1,
                    'status' => $status,
                    'is_featured' => $isFeatured,
                    'created_at' => date('Y-m-d H:i:s'),
                ];

                try {
                    $newsId = $this->model->saveArticle(0, $data);
                    if ($newsId) {
                        $this->model->saveArticleTags($newsId, $tagsCsv);
                        Notify::success('Article created successfully.');
                        redirect($this->url->to('news/admin/article'));
                    } else {
                        Notify::error('Failed to save article.');
                    }
                } catch (\Exception $e) {
                    Notify::error('Error: ' . $e->getMessage());
                }
            }
        }

        echo $this->view->inline(function ($view) use ($categories, $errors) {
            echo '<div class="d-flex align-items-center gap-2 mb-4">';
            echo '  <a class="btn btn-outline-secondary btn-sm" href="' . $view->url->to('news/admin/article') . '">Back</a>';
            echo '  <h1 class="h3 mb-0">Create Article</h1>';
            echo '</div>';

            if (!empty($errors)) {
                echo '<div class="alert alert-danger"><ul class="mb-0">';
                foreach ($errors as $error) {
                    echo '<li>' . $view->e($error) . '</li>';
                }
                echo '</ul></div>';
            }

            echo $view->form->open();
            echo '<div class="row g-3">';
            echo '  <div class="col-12">';
            echo $view->form->input('title', [
                'label' => 'Title',
                'value' => old_input('title', ''),
                'required' => true,
            ]);
            echo '  </div>';

            echo '  <div class="col-md-6">';
            echo $view->form->input('slug', [
                'label' => 'Slug (URL-friendly)',
                'value' => old_input('slug', ''),
                'help' => 'Leave empty to auto-generate from title.',
            ]);
            echo '  </div>';

            echo '  <div class="col-md-6">';
            $catOptions = [];
            foreach ($categories as $cat) {
                $catOptions[$cat['id']] = $cat['name'];
            }
            echo $view->form->select('category_id', $catOptions, old_input('category_id', ''), [
                'label' => 'Category',
                'blank' => '— Select Category —',
                'required' => true,
            ]);
            echo '  </div>';

            echo '  <div class="col-md-6">';
            echo $view->form->input('tags', [
                'label' => 'Tags',
                'value' => old_input('tags', ''),
                'help' => 'Comma-separated tags (e.g., World, Economy, Climate)',
                'placeholder' => 'World, Economy, Climate',
            ]);
            echo '  </div>';

            echo '  <div class="col-md-3">';
            echo $view->form->select('status', [
                '1' => 'Published',
                '0' => 'Draft',
            ], old_input('status', '0'), ['label' => 'Status']);
            echo '  </div>';

            echo '  <div class="col-md-3">';
            echo '    <div class="mb-3">';
            echo '      <label class="form-label d-block">Featured</label>';
            echo '      <div class="form-check mt-2">';
            echo '        <input type="checkbox" class="form-check-input" name="is_featured" value="1" ' . (old_input('is_featured', '0') == '1' ? 'checked' : '') . '>';
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';

            echo '  <div class="col-12">';
            echo $view->form->textarea('body', [
                'label' => 'Body',
                'value' => old_input('body', ''),
                'rows' => 12,
                'required' => true,
                'editor' => true, // Enables TinyMCE if your form supports it
            ]);
            echo '  </div>';

            echo '  <div class="col-12">';
            echo $view->form->submit('Save Article', ['class' => 'btn btn-primary']);
            echo '    <a href="' . $view->url->to('news/admin/article') . '" class="btn btn-secondary ms-2">Cancel</a>';
            echo '  </div>';
            echo '</div>';
            echo $view->form->close();
        }, 'admin');
    }

    /**
     * Edit an existing article.
     */
    public function editAction(): void
    {
        if (!$this->auth->can('news.admin.article.edit')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        $articleId = (int) $this->request->get('id', 'int', 0);
        $article = $this->model->getArticle($articleId);

        if (!$article) {
            Notify::error('Article not found.');
            redirect($this->url->to('news/admin/article'));
            return;
        }

        $categories = $this->model->getAllCategories();
        $currentTags = $this->model->getArticleTags($articleId);
        $currentTagString = implode(', ', array_column($currentTags, 'name'));
        $errors = [];

        if ($this->request->isPost()) {
            if (!$this->form->checkToken()) {
                Notify::error('Invalid security token.');
                redirect($this->url->to('news/admin/article'));
                return;
            }

            $title = trim($this->request->post('title', 'string', ''));
            $slug = trim($this->request->post('slug', 'string', ''));
            $body = trim($this->request->post('body', 'string', ''));
            $categoryId = (int) $this->request->post('category_id', 'int', 0);
            $status = (int) $this->request->post('status', 'int', 0);
            $isFeatured = (int) $this->request->post('is_featured', 'int', 0);
            $tagsCsv = trim($this->request->post('tags', 'string', ''));

            if ($title === '') { $errors[] = 'Title is required.'; }
            if ($body === '') { $errors[] = 'Body is required.'; }
            if ($categoryId <= 0) { $errors[] = 'Category is required.'; }
            if ($slug === '') { $slug = $this->model->makeSlug($title); }
            $slug = $this->model->makeSlug($slug);
            if ($slug === '') { $errors[] = 'A valid URL slug is required.'; }

            if (empty($errors)) {
                if ($this->model->slugExists($slug, $articleId)) {
                    $slug = $slug . '-' . time();
                }

                $data = [
                    'title' => $title,
                    'slug' => $slug,
                    'body' => $body,
                    'category_id' => $categoryId,
                    'status' => $status,
                    'is_featured' => $isFeatured,
                    'updated_at' => date('Y-m-d H:i:s'),
                ];

                try {
                    $saved = $this->model->saveArticle($articleId, $data);
                    if ($saved) {
                        $this->model->saveArticleTags($articleId, $tagsCsv);
                        Notify::success('Article updated successfully.');
                        redirect($this->url->to('news/admin/article'));
                    } else {
                        Notify::error('Failed to update article.');
                    }
                } catch (\Exception $e) {
                    Notify::error('Error: ' . $e->getMessage());
                }
            }
        }

        echo $this->view->inline(function ($view) use ($article, $categories, $currentTagString, $errors) {
            echo '<div class="d-flex align-items-center gap-2 mb-4">';
            echo '  <a class="btn btn-outline-secondary btn-sm" href="' . $view->url->to('news/admin/article') . '">Back</a>';
            echo '  <h1 class="h3 mb-0">Edit Article</h1>';
            echo '</div>';

            if (!empty($errors)) {
                echo '<div class="alert alert-danger"><ul class="mb-0">';
                foreach ($errors as $error) {
                    echo '<li>' . $view->e($error) . '</li>';
                }
                echo '</ul></div>';
            }

            echo $view->form->open();

            echo '<div class="row g-3">';
            echo '  <div class="col-12">';
            echo $view->form->input('title', [
                'label' => 'Title',
                'value' => $article['title'],
                'required' => true,
            ]);
            echo '  </div>';

            echo '  <div class="col-md-6">';
            echo $view->form->input('slug', [
                'label' => 'Slug (URL-friendly)',
                'value' => $article['slug'],
                'help' => 'Leave empty to auto-generate from title.',
            ]);
            echo '  </div>';

            echo '  <div class="col-md-6">';
            $catOptions = [];
            foreach ($categories as $cat) {
                $catOptions[$cat['id']] = $cat['name'];
            }
            echo $view->form->select('category_id', $catOptions, $article['category_id'], [
                'label' => 'Category',
                'blank' => '— Select Category —',
                'required' => true,
            ]);
            echo '  </div>';

            echo '  <div class="col-md-6">';
            echo $view->form->input('tags', [
                'label' => 'Tags',
                'value' => $currentTagString,
                'help' => 'Comma-separated tags (e.g., World, Economy, Climate)',
                'placeholder' => 'World, Economy, Climate',
            ]);
            echo '  </div>';

            echo '  <div class="col-md-3">';
            echo $view->form->select('status', [
                '1' => 'Published',
                '0' => 'Draft',
            ], $article['status'], ['label' => 'Status']);
            echo '  </div>';

            echo '  <div class="col-md-3">';
            echo '    <div class="mb-3">';
            echo '      <label class="form-label d-block">Featured</label>';
            echo '      <div class="form-check mt-2">';
            echo '        <input type="checkbox" class="form-check-input" name="is_featured" value="1" ' . ($article['is_featured'] == 1 ? 'checked' : '') . '>';
            echo '      </div>';
            echo '    </div>';
            echo '  </div>';

            echo '  <div class="col-12">';
            echo $view->form->textarea('body', [
                'label' => 'Body',
                'value' => htmlspecialchars_decode($article['body'], ENT_QUOTES | ENT_SUBSTITUTE),
                'rows' => 12,
                'required' => true,
                'editor' => true,
            ]);
            echo '  </div>';

            echo '  <div class="col-12">';
            echo $view->form->submit('Update Article', ['class' => 'btn btn-primary']);
            echo '    <a href="' . $view->url->to('news/admin/article') . '" class="btn btn-secondary ms-2">Cancel</a>';
            echo '  </div>';
            echo '</div>';
            echo $view->form->close();
        }, 'admin');
    }

    /**
     * Delete an article.
     */
    public function deleteAction(): void
    {
        if (!$this->auth->can('news.admin.article.delete')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        if (!$this->form->checkToken()) {
            Notify::error('Invalid security token.');
            redirect($this->url->to('news/admin/article'));
            return;
        }

        $articleId = (int) $this->request->post('id', 'int', 0);
        if ($articleId > 0) {
            try {
                $deleted = $this->model->deleteArticle($articleId);
                if ($deleted) {
                    Notify::success('Article deleted.');
                } else {
                    Notify::error('Failed to delete article.');
                }
            } catch (\Exception $e) {
                Notify::error('Error: ' . $e->getMessage());
            }
        }
        redirect($this->url->to('news/admin/article'));
    }

    /**
     * List and manage categories.
     */
    public function categoriesAction(): void
    {
        if (!$this->auth->can('news.admin.category.view')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        $errors = [];
        if ($this->request->isPost()) {
            if (!$this->form->checkToken()) {
                Notify::error('Invalid security token.');
                redirect($this->url->to('news/admin/article/categories'));
                return;
            }

            $name = trim($this->request->post('name', 'string', ''));
            $slug = trim($this->request->post('slug', 'string', ''));
            $description = trim($this->request->post('description', 'string', ''));

            if ($name === '') { $errors[] = 'Category name is required.'; }
            if ($slug === '') { $slug = $this->model->makeSlug($name); }
            $slug = $this->model->makeSlug($slug);
            if ($slug === '') { $errors[] = 'A valid category slug is required.'; }

            if (empty($errors)) {
                try {
                    $this->model->createCategory(['name' => $name, 'slug' => $slug, 'description' => $description]);
                    Notify::success('Category created.');
                    redirect($this->url->to('news/admin/article/categories'));
                } catch (\Exception $e) {
                    Notify::error('Error: ' . $e->getMessage());
                }
            }
        }

        $categories = $this->model->getAllCategoriesWithCount();

        echo $this->view->inline(function ($view) use ($categories, $errors) {
            echo '<div class="d-flex justify-content-between align-items-center gap-3 mb-4">';
            echo '  <div><h1 class="h3 mb-1">Categories</h1><p class="text-muted mb-0">Organise the newsroom by section.</p></div>';
            echo '  <a class="btn btn-primary btn-sm" href="' . $view->url->to('news/admin/article') . '">Back to articles</a>';
            echo '</div>';

            // Create category form
            echo '<div class="card border-0 shadow-sm mb-4">';
            echo '  <div class="card-body">';
            echo '    <h2 class="h5 mb-3">Create category</h2>';
            echo '    <form method="post" action="' . $view->url->to('news/admin/article/categories') . '">';
            echo $view->form->csrfField();
            echo '      <div class="row g-3">';
            echo '        <div class="col-md-4">';
            echo $view->form->input('name', ['label' => false, 'placeholder' => 'Category name', 'required' => true]);
            echo '        </div>';
            echo '        <div class="col-md-3">';
            echo $view->form->input('slug', ['label' => false, 'placeholder' => 'Slug']);
            echo '        </div>';
            echo '        <div class="col-md-5">';
            echo $view->form->input('description', ['label' => false, 'placeholder' => 'Description']);
            echo '        </div>';
            echo '        <div class="col-12">';
            echo $view->form->submit('Save category', ['class' => 'btn btn-primary']);
            echo '        </div>';
            echo '      </div>';
            echo '    </form>';

            if (!empty($errors)) {
                echo '<div class="alert alert-danger mt-3 mb-0"><ul class="mb-0">';
                foreach ($errors as $error) {
                    echo '<li>' . $view->e($error) . '</li>';
                }
                echo '</ul></div>';
            }
            echo '  </div>';
            echo '</div>';

            // Categories table
            echo '<div class="table-responsive">';
            echo '<table class="table table-hover align-middle">';
            echo '  <thead class="table-light">';
            echo '    <tr><th>Name</th><th>Slug</th><th>Articles</th><th>Description</th></tr>';
            echo '  </thead>';
            echo '  <tbody>';

            if (empty($categories)) {
                echo '<tr><td colspan="4" class="text-muted py-4">No categories created yet.</td></tr>';
            } else {
                foreach ($categories as $cat) {
                    echo '<tr>';
                    echo '  <td><strong>' . $view->e($cat['name']) . '</strong></td>';
                    echo '  <td>' . $view->e($cat['slug']) . '</td>';
                    echo '  <td>' . (int)($cat['article_count'] ?? 0) . '</td>';
                    echo '  <td class="text-muted">' . $view->e($cat['description'] ?? '') . '</td>';
                    echo '</tr>';
                }
            }

            echo '  </tbody>';
            echo '</table>';
            echo '</div>';
        }, 'admin');
    }

    /**
     * List and manage tags.
     */
    public function tagsAction(): void
    {
        if (!$this->auth->can('news.admin.tag.view')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        $errors = [];
        if ($this->request->isPost()) {
            if (!$this->form->checkToken()) {
                Notify::error('Invalid security token.');
                redirect($this->url->to('news/admin/article/tags'));
                return;
            }

            $name = trim($this->request->post('name', 'string', ''));
            $slug = trim($this->request->post('slug', 'string', ''));

            if ($name === '') { $errors[] = 'Tag name is required.'; }
            if ($slug === '') { $slug = $this->model->makeSlug($name); }
            $slug = $this->model->makeSlug($slug);
            if ($slug === '') { $errors[] = 'A valid tag slug is required.'; }

            if (empty($errors)) {
                try {
                    $this->model->createTag(['name' => $name, 'slug' => $slug]);
                    Notify::success('Tag created.');
                    redirect($this->url->to('news/admin/article/tags'));
                } catch (\Exception $e) {
                    Notify::error('Error: ' . $e->getMessage());
                }
            }
        }

        $tags = $this->model->getAllTagsWithCount();

        echo $this->view->inline(function ($view) use ($tags, $errors) {
            echo '<div class="d-flex justify-content-between align-items-center gap-3 mb-4">';
            echo '  <div><h1 class="h3 mb-1">Tags</h1><p class="text-muted mb-0">Build topical clusters for discovery.</p></div>';
            echo '  <a class="btn btn-primary btn-sm" href="' . $view->url->to('news/admin/article') . '">Back to articles</a>';
            echo '</div>';

            // Create tag form
            echo '<div class="card border-0 shadow-sm mb-4">';
            echo '  <div class="card-body">';
            echo '    <h2 class="h5 mb-3">Create tag</h2>';
            echo '    <form method="post" action="' . $view->url->to('news/admin/article/tags') . '">';
            echo $view->form->csrfField();
            echo '      <div class="row g-3">';
            echo '        <div class="col-md-5">';
            echo $view->form->input('name', ['label' => false, 'placeholder' => 'Tag name', 'required' => true]);
            echo '        </div>';
            echo '        <div class="col-md-4">';
            echo $view->form->input('slug', ['label' => false, 'placeholder' => 'Slug']);
            echo '        </div>';
            echo '        <div class="col-md-3">';
            echo $view->form->submit('Save tag', ['class' => 'btn btn-primary w-100']);
            echo '        </div>';
            echo '      </div>';
            echo '    </form>';

            if (!empty($errors)) {
                echo '<div class="alert alert-danger mt-3 mb-0"><ul class="mb-0">';
                foreach ($errors as $error) {
                    echo '<li>' . $view->e($error) . '</li>';
                }
                echo '</ul></div>';
            }
            echo '  </div>';
            echo '</div>';

            // Tags table
            echo '<div class="table-responsive">';
            echo '<table class="table table-hover align-middle">';
            echo '  <thead class="table-light">';
            echo '    <tr><th>Name</th><th>Slug</th><th>Articles</th></tr>';
            echo '  </thead>';
            echo '  <tbody>';

            if (empty($tags)) {
                echo '<tr><td colspan="3" class="text-muted py-4">No tags created yet.</td></tr>';
            } else {
                foreach ($tags as $tag) {
                    echo '<tr>';
                    echo '  <td><strong>' . $view->e($tag['name']) . '</strong></td>';
                    echo '  <td>' . $view->e($tag['slug']) . '</td>';
                    echo '  <td>' . (int)($tag['article_count'] ?? 0) . '</td>';
                    echo '</tr>';
                }
            }

            echo '  </tbody>';
            echo '</table>';
            echo '</div>';
        }, 'admin');
    }
}