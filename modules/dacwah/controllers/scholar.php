<?php
use System\Engine\Controller;

class DacwahScholar extends Controller
{
    public function indexAction()
    {
        $model = $this->load->model('dacwah/scholar');

        $page   = (int) ($this->request->get('page', 'int', 1));
        $search = $this->request->get('search', 'string', null);

        $filters = ['status' => 'published'];
        if ($search) $filters['search'] = $search;

        $scholars = $model->getAll($filters, $page, 24);

        echo $this->view->inline(function () use ($scholars) {
            echo "<div class=\"container py-4\">";
            echo "<h1 class=\"mb-4\">Scholars</h1>";

            if (empty($scholars)) {
                echo "<div class=\"text-center py-5\"><p class=\"text-muted\">No scholars found.</p></div>";
            } else {
                echo "<div class=\"row g-4\">";
                foreach ($scholars as $s) {
                    echo "<div class=\"col-md-6 col-lg-4\">";
                    echo "<div class=\"card h-100 shadow-sm\">";
                    echo "<div class=\"card-body text-center\">";
                    echo "<h5 class=\"card-title mb-1\">";
                    echo "<a href=\"" . $this->url->to('dacwah/scholar', ['slug' => $s['slug']]) . "\" class=\"text-decoration-none\">";
                    echo escape($s['name']) . "</a></h5>";
                    if (!empty($s['title'])) {
                        echo "<p class=\"small text-muted mb-2\">" . escape($s['title']) . "</p>";
                    }
                    if (!empty($s['bio'])) {
                        echo "<p class=\"small\">" . escape(truncate($s['bio'], 100)) . "</p>";
                    }
                    echo "</div><div class=\"card-footer bg-white border-top-0 text-center\">";
                    echo "<a href=\"" . $this->url->to('dacwah/scholar', ['slug' => $s['slug']]) . "\" class=\"btn btn-primary btn-sm\">View Lectures</a>";
                    echo "</div></div></div>";
                }
                echo "</div>";
            }
            echo "</div>";
        });
    }

    public function viewAction()
    {
        $model = $this->load->model('dacwah/scholar');
        $lectureModel = $this->load->model('dacwah/lecture');
        $seriesModel = $this->load->model('dacwah/series');

        $slug = $this->request->get('slug', 'string', '');
        if (!$slug) {
            http_response_code(404);
            return;
        }

        // Get scholar
        $scholar = $this->db->query(
            "SELECT * FROM #__dacwah_scholars WHERE slug = ? AND status = 'published'",
            [$slug]
        )->row ?: null;

        if (!$scholar) {
            http_response_code(404);
            echo "<div class=\"container py-5 text-center\">";
            echo "<h2>Scholar Not Found</h2>";
            echo "<a href=\"" . $this->url->to('dacwah/scholars') . "\" class=\"btn btn-primary mt-3\">Browse All Scholars</a>";
            echo "</div>";
            return;
        }

        // Get their lectures & series
        $lectures = $lectureModel->getAll(
            ['status' => 'published', 'scholar_id' => $scholar['id'], 'sort' => 'l.created_at', 'order' => 'DESC'],
            1, 30
        );
        $series = $seriesModel->getAll(
            ['status' => 'published', 'scholar_id' => $scholar['id'], 'sort' => 'title', 'order' => 'ASC'],
            1, 20
        );

        echo $this->view->inline(function () use ($scholar, $lectures, $series) {
            echo "<div class=\"container py-4\">";

            // Breadcrumbs
            echo "<nav class=\"small mb-3\">";
            echo "<a href=\"" . $this->url->to('dacwah') . "\" class=\"text-decoration-none\">Home</a> / ";
            echo "<a href=\"" . $this->url->to('dacwah/scholars') . "\" class=\"text-decoration-none\">Scholars</a> / ";
            echo "<span class=\"text-muted\">" . escape($scholar['name']) . "</span>";
            echo "</nav>";

            echo "<div class=\"row\">";

            // Scholar Info
            echo "<div class=\"col-lg-4\">";
            echo "<div class=\"card mb-4\">";
            echo "<div class=\"card-body text-center\">";
            echo "<h1 class=\"h3 mb-1\">" . escape($scholar['name']) . "</h1>";
            if (!empty($scholar['title'])) {
                echo "<p class=\"text-muted mb-3\">" . escape($scholar['title']) . "</p>";
            }
            echo "<div class=\"d-flex justify-content-center gap-3 text-center mb-2\">";
            echo "<div><strong>" . count($lectures) . "</strong><br><small class=\"text-muted\">Lectures</small></div>";
            echo "<div><strong>" . count($series) . "</strong><br><small class=\"text-muted\">Series</small></div>";
            echo "</div></div></div>";

            if (!empty($scholar['bio'])) {
                echo "<div class=\"card mb-4\">";
                echo "<div class=\"card-body\">";
                echo "<h3 class=\"h6 mb-2\">Biography</h3>";
                echo "<div>" . nl2br(escape($scholar['bio'])) . "</div>";
                echo "</div></div>";
            }
            echo "</div>"; // .col-lg-4

            // Lectures & Series
            echo "<div class=\"col-lg-8\">";

            // Series First
            if (!empty($series)) {
                echo "<h3 class=\"h4 mb-3\">Series</h3>";
                echo "<div class=\"row g-2 mb-4\">";
                foreach ($series as $s) {
                    echo "<div class=\"col-md-6\">";
                    echo "<a href=\"" . $this->url->to('dacwah/series', ['slug' => $s['slug']]) . "\" class=\"text-decoration-none\">";
                    echo "<div class=\"card p-2 h-100\">";
                    echo escape($s['title']);
                    echo "<span class=\"badge bg-secondary mt-1\">" . (int)($s['total_episodes'] ?? 0) . " lectures</span>";
                    echo "</div></a></div>";
                }
                echo "</div>";
            }

            // Lectures
            echo "<h3 class=\"h4 mb-3\">Lectures</h3>";
            if (empty($lectures)) {
                echo "<p class=\"text-muted\">No lectures published yet.</p>";
            } else {
                echo "<div class=\"list-group\">";
                foreach ($lectures as $lec) {
                    echo "<a href=\"" . $this->url->to('dacwah/lecture', ['slug' => $lec['slug']]) . "\" class=\"list-group-item list-group-item-action\">";
                    echo "<div class=\"d-flex w-100 justify-content-between\">";
                    echo "<div>";
                    if (!empty($lec['series_title'])) {
                        echo "<span class=\"badge bg-light text-dark me-2\">" . escape($lec['series_title']) . "</span>";
                    }
                    echo escape($lec['title']);
                    echo "</div>";
                    $date = !empty($lec['lecture_date']) ? date('M j, Y', strtotime($lec['lecture_date'])) : date('M j, Y', strtotime($lec['created_at'] ?? ''));
                    echo "<small class=\"text-muted\">{$date}</small>";
                    echo "</div></a>";
                }
                echo "</div>";
            }

            echo "</div></div></div>";
        });
    }
}