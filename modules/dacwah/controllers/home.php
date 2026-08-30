<?php

use System\Engine\Controller;

class DacwahHome extends Controller
{
    public function indexAction() {
        $lectureModel  = $this->load->model('dacwah/lecture');
        $seriesModel   = $this->load->model('dacwah/series');
        $scholarModel  = $this->load->model('dacwah/scholar');
        $categoryModel = $this->load->model('dacwah/category');

        // Latest Published Lectures
        $filtersLatest = ['status' => 'published', 'sort' => 'l.created_at', 'order' => 'DESC'];
        $latestLectures = $lectureModel->getAll($filtersLatest, 1, 8);

        // Featured / Popular Series
        $filtersSeries = ['status' => 'published', 'sort' => 'l.title', 'order' => 'ASC'];
        $featuredSeries = $seriesModel->getAll($filtersSeries, 1, 6);

        // All Scholars
        $filtersScholars = ['status' => 'published', 'sort' => 'name', 'order' => 'ASC'];
        $scholars = $scholarModel->getAll($filtersScholars, 1, 12);

        // All Categories
        $filtersCategories = ['status' => 'published', 'sort' => 'name', 'order' => 'ASC'];
        $categories = $categoryModel->getAll($filtersCategories, 1, 20);

        echo $this->view->inline(function () use (
            $latestLectures,
            $featuredSeries,
            $scholars,
            $categories
        ) {
            echo "<div class=\"container py-4\">";

            // === HERO SECTION ===
            echo "<div class=\"bg-light rounded-3 p-5 mb-5 text-center\">";
            echo "<h1 class=\"display-4 fw-bold mb-3\">Welcome to Dacwah</h1>";
            echo "<p class=\"lead text-muted mb-4\">Discover Islamic lectures, series, books and articles from renowned scholars.</p>";
            echo "<div class=\"d-flex justify-content-center gap-2\">";
            echo "<a href=\"" . $this->url->to('dacwah/lectures') . "\" class=\"btn btn-primary btn-lg\">Browse Lectures</a>";
            echo "<a href=\"" . $this->url->to('dacwah/series') . "\" class=\"btn btn-outline-secondary btn-lg\">View Series</a>";
            echo "</div></div>";

            // === CATEGORIES ===
            if (!empty($categories)) {
                echo "<section class=\"mb-5\">";
                echo "<h2 class=\"h4 mb-4\">Browse by Category</h2>";
                echo "<div class=\"row g-3\">";
                foreach ($categories as $cat) {
                    echo "<div class=\"col-6 col-md-4 col-lg-3\">";
                    echo "<a href=\"" . $this->url->to('dacwah/category', ['slug' => $cat['slug']]) . "\" class=\"text-decoration-none\">";
                    echo "<div class=\"card h-100 border-0 bg-light\">";
                    echo "<div class=\"card-body p-3\">";
                    echo "<h5 class=\"h6 mb-1\">" . escape($cat['name']) . "</h5>";
                    echo "<p class=\"small text-muted mb-0\">" . escape($cat['lecture_count'] ?? 0) . " lectures</p>";
                    echo "</div></div></a></div>";
                }
                echo "</div></section>";
            }

            // === LATEST LECTURES ===
            echo "<section class=\"mb-5\">";
            echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
            echo "<h2 class=\"h4\">Latest Lectures</h2>";
            echo "<a href=\"" . $this->url->to('dacwah/lectures') . "\" class=\"btn btn-sm btn-outline-primary\">View All</a>";
            echo "</div>";

            if (empty($latestLectures)) {
                echo "<p class=\"text-muted\">No lectures published yet.</p>";
            } else {
                echo "<div class=\"row g-4\">";
                foreach ($latestLectures as $lec) {
                    echo "<div class=\"col-md-6 col-lg-4\">";
                    echo "<div class=\"card h-100 shadow-sm\">";
                    echo "<div class=\"card-body\">";
                    echo "<h5 class=\"card-title mb-1\">";
                    echo "<a href=\"" . $this->url->to('dacwah/lecture', ['slug' => $lec['slug']]) . "\" class=\"text-decoration-none text-dark\">";
                    echo escape($lec['title']) . "</a></h5>";
                    echo "<p class=\"card-text small text-muted mb-2\">";
                    if (!empty($lec['scholar_name'])) echo escape($lec['scholar_name']) . " · ";
                    if (!empty($lec['series_title'])) echo "Series: " . escape($lec['series_title']) . " · ";
                    echo escape($lec['lecture_date'] ?? date('Y-m-d', strtotime($lec['created_at'] ?? '')));
                    echo "</p>";
                    echo "<p class=\"card-text small\">" . escape(truncate($lec['description'] ?? '', 100)) . "</p>";
                    echo "</div><div class=\"card-footer bg-white border-top-0\">";
                    echo "<a href=\"" . $this->url->to('dacwah/lecture', ['slug' => $lec['slug']]) . "\" class=\"btn btn-sm btn-primary\">Watch / Listen</a>";
                    echo "</div></div></div>";
                }
                echo "</div>";
            }
            echo "</section>";

            // === SERIES ===
            if (!empty($featuredSeries)) {
                echo "<section class=\"mb-5\">";
                echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
                echo "<h2 class=\"h4\">Series</h2>";
                echo "<a href=\"" . $this->url->to('dacwah/series') . "\" class=\"btn btn-sm btn-outline-primary\">View All</a>";
                echo "</div>";
                echo "<div class=\"row g-4\">";
                foreach ($featuredSeries as $s) {
                    echo "<div class=\"col-md-4 col-lg-4\">";
                    echo "<div class=\"card h-100 shadow-sm\">";
                    echo "<div class=\"card-body\">";
                    echo "<h5 class=\"card-title\">";
                    echo "<a href=\"" . $this->url->to('dacwah/series', ['slug' => $s['slug']]) . "\" class=\"text-decoration-none text-dark\">";
                    echo escape($s['title']) . "</a></h5>";
                    echo "<p class=\"small text-muted mb-2\">" . escape($s['scholar_name'] ?? '') . "</p>";
                    echo "<p class=\"small\">" . escape(truncate($s['description'] ?? '', 80)) . "</p>";
                    echo "<span class=\"badge bg-secondary\">" . (int)($s['total_episodes'] ?? 0) . " Lectures</span>";
                    echo "</div></div></div>";
                }
                echo "</div></section>";
            }

            // === SCHOLARS ===
            if (!empty($scholars)) {
                echo "<section class=\"mb-5\">";
                echo "<div class=\"d-flex justify-content-between align-items-center mb-4\">";
                echo "<h2 class=\"h4\">Scholars</h2>";
                echo "<a href=\"" . $this->url->to('dacwah/scholars') . "\" class=\"btn btn-sm btn-outline-primary\">View All</a>";
                echo "</div>";
                echo "<div class=\"row g-3\">";
                foreach ($scholars as $sch) {
                    echo "<div class=\"col-6 col-md-3 col-lg-2\">";
                    echo "<a href=\"" . $this->url->to('dacwah/scholar', ['slug' => $sch['slug']]) . "\" class=\"text-decoration-none\">";
                    echo "<div class=\"card text-center h-100 border-0 bg-light\">";
                    echo "<div class=\"card-body p-3\">";
                    echo "<h6 class=\"mb-0 small\">" . escape($sch['name']) . "</h6>";
                    echo "</div></div></a></div>";
                }
                echo "</div></section>";
            }

            echo "</div>"; // .container
        });
    }
}