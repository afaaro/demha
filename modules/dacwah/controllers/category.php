<?php

use System\Engine\Controller;

class DacwahCategory extends Controller
{
    public function indexAction()
    {
        $model = $this->load->model('dacwah/category');

        $page   = (int) ($this->request->get('page', 'int', 1));
        $search = $this->request->get('search', 'string', null);

        $filters = ['status' => 'published'];
        if ($search) $filters['search'] = $search;

        $categories = $model->getAll($filters, $page, 30);

        echo $this->view->inline(function () use ($categories) {
            echo "<div class=\"container py-4\">";
            echo "<h1 class=\"mb-4\">Browse by Category</h1>";

            if (empty($categories)) {
                echo "<div class=\"text-center py-5\">";
                echo "<p class=\"text-muted\">No categories found.</p>";
                echo "</div>";
            } else {
                echo "<div class=\"row g-4\">";
                foreach ($categories as $cat) {
                    echo "<div class=\"col-md-4 col-lg-3\">";
                    echo "<div class=\"card h-100 shadow-sm\">";
                    echo "<div class=\"card-body\">";
                    echo "<h5 class=\"card-title mb-1\">";
                    echo "<a href=\"" . $this->url->to('dacwah/category', ['slug' => $cat['slug']]) . "\" class=\"text-decoration-none\">";
                    echo escape($cat['name']) . "</a></h5>";
                    if (!empty($cat['description'])) {
                        echo "<p class=\"small text-muted mt-2\">" . escape(truncate($cat['description'], 80)) . "</p>";
                    }
                    echo "</div><div class=\"card-footer bg-white border-top-0\">";
                    echo "<span class=\"badge bg-primary\">" . (int)($cat['lecture_count'] ?? 0) . " Lectures</span>";
                    echo "</div></div></div>";
                }
                echo "</div>";
            }
            echo "</div>";
        });
    }

    public function viewAction()
    {
        $model = $this->load->model('dacwah/category');
        $lectureModel = $this->load->model('dacwah/lecture');
        $seriesModel  = $this->load->model('dacwah/series');

        $slug = $this->request->get('slug', 'string', '');
        if (!$slug) {
            http_response_code(404);
            return;
        }

        // Get category details
        $category = $this->db->query(
            "SELECT * FROM {$model->table} WHERE slug = ? AND status = 'published'",
            [$slug]
        )->row ?: null;

        if (!$category) {
            http_response_code(404);
            echo "<div class=\"container py-5 text-center\">";
            echo "<h2>Category Not Found</h2>";
            echo "<p class=\"text-muted\">The category you requested does not exist or is not published.</p>";
            echo "<a href=\"" . $this->url->to('dacwah/categories') . "\" class=\"btn btn-primary\">Browse All Categories</a>";
            echo "</div>";
            return;
        }

        // Get lectures in this category
        $lectures = $lectureModel->getAll(
            [
                'status'      => 'published',
                'category_id' => $category['id'],
                'sort'        => 'l.created_at',
                'order'       => 'DESC'
            ],
            1, 30
        );

        // Get series in this category
        $series = $seriesModel->getAll(
            [
                'status'      => 'published',
                'category_id' => $category['id'],
                'sort'        => 'title',
                'order'       => 'ASC'
            ],
            1, 20
        );

        echo $this->view->inline(function () use ($category, $lectures, $series) {
            echo "<div class=\"container py-4\">";

            // Breadcrumbs
            echo "<nav class=\"small mb-3\">";
            echo "<a href=\"" . $this->url->to('dacwah') . "\" class=\"text-decoration-none\">Home</a> / ";
            echo "<a href=\"" . $this->url->to('dacwah/categories') . "\" class=\"text-decoration-none\">Categories</a> / ";
            echo "<span class=\"text-muted\">" . escape($category['name']) . "</span>";
            echo "</nav>";

            // Category Header
            echo "<div class=\"mb-4\">";
            echo "<h1 class=\"h2 mb-2\">" . escape($category['name']) . "</h1>";
            if (!empty($category['description'])) {
                echo "<p class=\"text-muted\">" . escape($category['description']) . "</p>";
            }
            echo "<span class=\"badge bg-primary\">" . count($lectures) . " Lectures</span>";
            if (!empty($series)) {
                echo " <span class=\"badge bg-secondary\">" . count($series) . " Series</span>";
            }
            echo "</div>";

            // Series in this Category
            if (!empty($series)) {
                echo "<h3 class=\"h4 mb-3\">Series in this Category</h3>";
                echo "<div class=\"row g-2 mb-4\">";
                foreach ($series as $s) {
                    echo "<div class=\"col-md-6 col-lg-4\">";
                    echo "<a href=\"" . $this->url->to('dacwah/series', ['slug' => $s['slug']]) . "\" class=\"text-decoration-none\">";
                    echo "<div class=\"card p-2 h-100 shadow-sm\">";
                    echo "<h5 class=\"h6 mb-1\">" . escape($s['title']) . "</h5>";
                    echo "<p class=\"small text-muted mb-1\">" . escape($s['scholar_name'] ?? '') . "</p>";
                    echo "<span class=\"badge bg-secondary\">" . (int)($s['total_episodes'] ?? 0) . " Lectures</span>";
                    echo "</div></a></div>";
                }
                echo "</div>";
            }

            // All Lectures in this Category
            echo "<h3 class=\"h4 mb-3\">Lectures</h3>";
            if (empty($lectures)) {
                echo "<p class=\"text-muted\">No lectures published yet in this category.</p>";
            } else {
                echo "<div class=\"list-group\">";
                foreach ($lectures as $lec) {
                    echo "<a href=\"" . $this->url->to('dacwah/lecture', ['slug' => $lec['slug']]) . "\" class=\"list-group-item list-group-item-action\">";
                    echo "<div class=\"d-flex w-100 justify-content-between align-items-center\">";
                    echo "<div>";
                    echo "<h5 class=\"h6 mb-1\">" . escape($lec['title']) . "</h5>";
                    echo "<p class=\"small text-muted mb-0\">";
                    if (!empty($lec['scholar_name'])) {
                        echo escape($lec['scholar_name']);
                    }
                    if (!empty($lec['series_title'])) {
                        echo " · " . escape($lec['series_title']);
                    }
                    echo "</p></div>";
                    $date = !empty($lec['lecture_date']) ? date('M j, Y', strtotime($lec['lecture_date'])) : date('M j, Y', strtotime($lec['created_at'] ?? ''));
                    echo "<small class=\"text-muted\">{$date}</small>";
                    echo "</div></a>";
                }
                echo "</div>";
            }

            echo "</div>";
        });
    }
}