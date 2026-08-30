<?php

use System\Engine\Controller;

class DacwahSeries extends Controller
{
    public function indexAction()
    {
        $model = $this->load->model('dacwah/series');

        $page   = (int) ($this->request->get('page', 'int', 1));
        $search = $this->request->get('search', 'string', null);

        $filters = ['status' => 'published'];
        if ($search) $filters['search'] = $search;

        $series = $model->getAll($filters, $page, 20);

        echo $this->view->inline(function () use ($series, $search) {
            echo "<div class=\"container py-4\">";
            echo "<h1 class=\"mb-4\">Lecture Series</h1>";

            echo "<div class=\"mb-4\">";
            echo "<form method=\"get\" class=\"row g-2\">";
            echo "<div class=\"col-md-8\">";
            echo "<input type=\"text\" name=\"search\" class=\"form-control\" placeholder=\"Search series...\" value=\"" . escape($search) . "\">";
            echo "</div><div class=\"col-md-4\">";
            echo "<button type=\"submit\" class=\"btn btn-primary w-100\">Search</button>";
            echo "</div></form></div>";

            if (empty($series)) {
                echo "<div class=\"text-center py-5\">";
                echo "<p class=\"text-muted\">No series found.</p>";
                echo "</div>";
            } else {
                echo "<div class=\"row g-4\">";
                foreach ($series as $s) {
                    echo "<div class=\"col-md-6 col-lg-4\">";
                    echo "<div class=\"card h-100 shadow-sm\">";
                    echo "<div class=\"card-body\">";
                    echo "<h5 class=\"card-title mb-1\">";
                    echo "<a href=\"" . $this->url->to('dacwah/series', ['slug' => $s['slug']]) . "\" class=\"text-decoration-none\">";
                    echo escape($s['title']) . "</a></h5>";
                    echo "<p class=\"small text-muted mb-2\">" . escape($s['scholar_name'] ?? '') . "</p>";
                    echo "<p class=\"small text-muted\">" . escape(truncate($s['description'] ?? '', 80)) . "</p>";
                    echo "</div><div class=\"card-footer bg-white border-top-0\">";
                    echo "<span class=\"badge bg-secondary\">" . (int)($s['total_episodes'] ?? 0) . " Lectures</span>";
                    echo "</div></div></div>";
                }
                echo "</div>";
            }
            echo "</div>";
        });
    }

    public function viewAction()
    {
        $model = $this->load->model('dacwah/series');
        $lectureModel = $this->load->model('dacwah/lecture');

        $slug = $this->request->get('slug', 'string', '');
        if (!$slug) {
            http_response_code(404);
            echo "<div class=\"container py-5 text-center\"><h2>Series Not Found</h2></div>";
            return;
        }

        // Get series details
        $series = $this->db->query(
            "SELECT s.*, sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug
             FROM #__dacwah_series s
             LEFT JOIN #__dacwah_scholars sch ON s.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON s.category_id = c.id
             WHERE s.slug = ? AND s.status = 'published'",
            [$slug]
        )->row ?: null;

        if (!$series) {
            http_response_code(404);
            echo "<div class=\"container py-5 text-center\">";
            echo "<h2>Series Not Found</h2>";
            echo "<p class=\"text-muted\">The series you requested does not exist or is not published.</p>";
            echo "<a href=\"" . $this->url->to('dacwah/series') . "\" class=\"btn btn-primary\">Browse All Series</a>";
            echo "</div>";
            return;
        }

        // Get all lectures in this series
        $lectures = $lectureModel->getAll(
            [
                'status'    => 'published',
                'series_id' => $series['id'],
                'sort'      => 'l.created_at',
                'order'     => 'ASC'
            ],
            1, 100
        );

        echo $this->view->inline(function () use ($series, $lectures) {
            echo "<div class=\"container py-4\">";

            // Breadcrumbs
            echo "<nav class=\"small mb-3\">";
            echo "<a href=\"" . $this->url->to('dacwah') . "\" class=\"text-decoration-none\">Home</a> / ";
            echo "<a href=\"" . $this->url->to('dacwah/series') . "\" class=\"text-decoration-none\">Series</a> / ";
            echo "<span class=\"text-muted\">" . escape($series['title']) . "</span>";
            echo "</nav>";

            echo "<div class=\"row\">";

            // Main Content
            echo "<div class=\"col-lg-8\">";
            echo "<h1 class=\"h2 mb-2\">" . escape($series['title']) . "</h1>";

            echo "<p class=\"text-muted mb-3\">";
            if (!empty($series['scholar_name'])) {
                echo "By <a href=\"" . $this->url->to('dacwah/scholar/view', ['slug' => $series['scholar_slug']]) . "\" class=\"text-decoration-none\">";
                echo escape($series['scholar_name']) . "</a>";
            }
            if (!empty($series['category_name'])) {
                echo " · <a href=\"" . $this->url->to('dacwah/category', ['slug' => $series['category_slug']]) . "\" class=\"text-decoration-none\">";
                echo escape($series['category_name']) . "</a>";
            }
            echo "</p>";

            echo "<span class=\"badge bg-primary mb-4\">" . count($lectures) . " Lectures in Series</span>";

            if (!empty($series['description'])) {
                echo "<div class=\"card mb-4\">";
                echo "<div class=\"card-body\">";
                echo "<h3 class=\"h6 mb-2\">About This Series</h3>";
                echo "<div>" . nl2br(escape($series['description'])) . "</div>";
                echo "</div></div>";
            }

            // Lectures in Series
            echo "<h3 class=\"h4 mb-3\">Lectures in This Series</h3>";
            if (empty($lectures)) {
                echo "<p class=\"text-muted\">No lectures published yet in this series.</p>";
            } else {
                echo "<div class=\"list-group\">";
                foreach ($lectures as $num => $lec) {
                    $part = $num + 1;
                    echo "<a href=\"" . $this->url->to('dacwah/lecture/view', ['slug' => $lec['slug']]) . "\" class=\"list-group-item list-group-item-action\">";
                    echo "<div class=\"d-flex w-100 justify-content-between align-items-center\">";
                    echo "<div><span class=\"badge bg-secondary me-2\">Part {$part}</span>";
                    echo escape($lec['title']) . "</div>";
                    $date = !empty($lec['lecture_date']) ? date('M j', strtotime($lec['lecture_date'])) : date('M j', strtotime($lec['created_at'] ?? ''));
                    echo "<small class=\"text-muted\">{$date}</small>";
                    echo "</div></a>";
                }
                echo "</div>";
            }
            echo "</div>"; // .col-lg-8

            // Sidebar
            echo "<div class=\"col-lg-4\">";
            if (!empty($series['scholar_name'])) {
                echo "<div class=\"card mb-4\">";
                echo "<div class=\"card-body\">";
                echo "<h3 class=\"h6 mb-2\">Scholar</h3>";
                echo "<h4 class=\"h6 fw-bold\">";
                echo "<a href=\"" . $this->url->to('dacwah/scholar/view', ['slug' => $series['scholar_slug']]) . "\" class=\"text-decoration-none\">";
                echo escape($series['scholar_name']) . "</a></h4>";
                echo "</div></div>";
            }
            echo "</div>"; // .col-lg-4

            echo "</div></div>";
        });
    }
}