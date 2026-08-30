<?php

use System\Engine\Controller;

class DacwahLecture extends Controller
{
    public function indexAction()
    {
        $model = $this->load->model('dacwah/lecture');

        $page       = (int) ($this->request->get('page', 'int', 1));
        $search     = $this->request->get('search', 'string', null);
        $scholarId  = $this->request->get('scholar_id', 'int', 0);
        $categoryId = $this->request->get('category_id', 'int', 0);
        $seriesId   = $this->request->get('series_id', 'int', 0);
        $sort       = $this->request->get('sort', 'string', 'l.created_at');
        $order      = $this->request->get('order', 'string', 'DESC');

        $filters = ['status' => 'published'];

        if ($search)     $filters['search']     = $search;
        if ($scholarId)  $filters['scholar_id']  = $scholarId;
        if ($categoryId) $filters['category_id'] = $categoryId;
        if ($seriesId)   $filters['series_id']   = $seriesId;

        $filters['sort']  = $sort;
        $filters['order'] = $order;

        $lectures = $model->getAll($filters, $page, 24);

        $scholars   = $this->db->query("SELECT id, name FROM #__dacwah_scholars WHERE status='published' ORDER BY name ASC")->pairs;
        $categories = $this->db->query("SELECT id, name FROM #__dacwah_categories WHERE status='published' ORDER BY name ASC")->pairs;
        $seriesList = $this->db->query("SELECT id, title FROM #__dacwah_series WHERE status='published' ORDER BY title ASC")->pairs;

        echo $this->view->inline(function () use (
            $lectures,
            $scholars,
            $categories,
            $seriesList,
            $search,
            $scholarId,
            $categoryId,
            $seriesId
        ) {
            echo "<div class=\"container py-4\">";
            echo "<h1 class=\"mb-4\">All Lectures</h1>";

            echo "<div class=\"card mb-4 bg-light\">";
            echo "<div class=\"card-body p-3\">";
            echo "<form method=\"get\" class=\"row g-2 align-items-end\">";

            echo "<div class=\"col-md-3\">";
            echo "<label class=\"form-label small\">Search</label>";
            echo "<input type=\"text\" name=\"search\" class=\"form-control form-control-sm\" placeholder=\"Search...\" value=\"" . escape($search) . "\">";
            echo "</div>";

            echo "<div class=\"col-md-2\">";
            echo "<label class=\"form-label small\">Scholar</label>";
            echo "<select name=\"scholar_id\" class=\"form-select form-select-sm\">";
            echo "<option value=\"\">— All Scholars —</option>";
            foreach ($scholars as $id => $name) {
                $sel = $scholarId == $id ? 'selected' : '';
                echo "<option value=\"{$id}\" {$sel}>" . escape($name) . "</option>";
            }
            echo "</select></div>";

            echo "<div class=\"col-md-2\">";
            echo "<label class=\"form-label small\">Category</label>";
            echo "<select name=\"category_id\" class=\"form-select form-select-sm\">";
            echo "<option value=\"\">— All Categories —</option>";
            foreach ($categories as $id => $name) {
                $sel = $categoryId == $id ? 'selected' : '';
                echo "<option value=\"{$id}\" {$sel}>" . escape($name) . "</option>";
            }
            echo "</select></div>";

            echo "<div class=\"col-md-2\">";
            echo "<label class=\"form-label small\">Series</label>";
            echo "<select name=\"series_id\" class=\"form-select form-select-sm\">";
            echo "<option value=\"\">— All Series —</option>";
            foreach ($seriesList as $id => $title) {
                $sel = $seriesId == $id ? 'selected' : '';
                echo "<option value=\"{$id}\" {$sel}>" . escape($title) . "</option>";
            }
            echo "</select></div>";

            echo "<div class=\"col-md-3\">";
            echo "<button type=\"submit\" class=\"btn btn-primary btn-sm w-100\">Filter</button>";
            echo "</div></form></div></div>";

            if (empty($lectures)) {
                echo "<div class=\"text-center py-5\">";
                echo "<p class=\"text-muted\">No lectures found matching your filters.</p>";
                echo "<a href=\"" . $this->url->to('dacwah/lectures') . "\" class=\"btn btn-outline-primary\">Clear Filters</a>";
                echo "</div>";
            } else {
                echo "<div class=\"row g-4\">";
                foreach ($lectures as $lec) {
                    echo "<div class=\"col-md-6 col-lg-4\">";
                    echo "<div class=\"card h-100 shadow-sm\">";
                    echo "<div class=\"card-body\">";

                    echo "<h5 class=\"card-title mb-1\">";
                    echo "<a href=\"" . $this->url->to('dacwah/lecture', ['slug' => $lec['slug']]) . "\" class=\"text-decoration-none text-dark\">";
                    echo escape($lec['title']) . "</a></h5>";

                    echo "<p class=\"card-text small text-muted mb-2\">";
                    if (!empty($lec['scholar_name'])) {
                        echo "<a href=\"" . $this->url->to('dacwah/scholar', ['slug' => $lec['scholar_slug']]) . "\" class=\"text-decoration-none\">";
                        echo escape($lec['scholar_name']) . "</a>";
                    }
                    if (!empty($lec['series_title'])) {
                        echo " · <a href=\"" . $this->url->to('dacwah/series/view', ['slug' => $lec['series_slug']]) . "\" class=\"text-decoration-none\">";
                        echo escape($lec['series_title']) . "</a>";
                    }
                    echo "</p>";

                    if (!empty($lec['category_name'])) {
                        echo "<span class=\"badge bg-light text-dark border me-1 small\">";
                        echo escape($lec['category_name']) . "</span>";
                    }

                    $date = !empty($lec['lecture_date']) ? date('M j, Y', strtotime($lec['lecture_date'])) : date('M j, Y', strtotime($lec['created_at'] ?? ''));
                    echo "<p class=\"small text-muted mt-2 mb-0\">{$date}</p>";

                    echo "</div>";
                    echo "<div class=\"card-footer bg-white border-top-0\">";
                    echo "<a href=\"" . $this->url->to('dacwah/lecture', ['slug' => $lec['slug']]) . "\" class=\"btn btn-primary btn-sm w-100\">View Lecture</a>";
                    echo "</div></div></div>";
                }
                echo "</div>";
            }

            echo "</div>";
        });
    }

    public function viewAction()
    {
        $model = $this->load->model('dacwah/lecture');

        $slug = $this->request->get('slug', 'string', '');
        if (!$slug) {
            http_response_code(404);
            echo "Lecture not found.";
            return;
        }

        // Get lecture by slug — published only
        $lecture = $this->db->query(
            "SELECT l.*, 
                    sch.name as scholar_name, sch.slug as scholar_slug, sch.bio as scholar_bio,
                    c.name as category_name, c.slug as category_slug,
                    s.title as series_title, s.slug as series_slug, s.total_lessons
             FROM #__dacwah_lectures l
             LEFT JOIN #__dacwah_scholars sch ON l.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON l.category_id = c.id
             LEFT JOIN #__dacwah_series s ON l.series_id = s.id
             WHERE l.slug = ? AND l.status = 'published'",
            [$slug]
        )->row ?: null;

        if (!$lecture) {
            http_response_code(404);
            echo "<div class=\"container py-5 text-center\">";
            echo "<h2>Lecture Not Found</h2>";
            echo "<p class=\"text-muted\">The lecture you requested does not exist or is not published.</p>";
            echo "<a href=\"" . $this->url->to('dacwah/lectures') . "\" class=\"btn btn-primary\">Browse All Lectures</a>";
            echo "</div>";
            return;
        }

        // Load media files (audio/video)
        $media = $model->getMediaFiles($lecture['id']);

        // Related lectures (same scholar or same series)
        $related = $model->getAll(
            [
                'status'    => 'published',
                'scholar_id' => $lecture['scholar_id'],
                'sort'      => 'l.created_at',
                'order'     => 'DESC'
            ],
            1, 5
        );
        // Remove current lecture from related
        foreach ($related as $idx => $r) {
            if ($r['id'] == $lecture['id']) unset($related[$idx]);
        }
        $related = array_slice($related, 0, 4);

        echo $this->view->inline(function () use ($lecture, $media, $related) {
            echo "<div class=\"container py-4\">";

            // Breadcrumbs
            echo "<nav class=\"small mb-3\">";
            echo "<a href=\"" . $this->url->to('dacwah') . "\" class=\"text-decoration-none\">Home</a> / ";
            echo "<a href=\"" . $this->url->to('dacwah/lectures') . "\" class=\"text-decoration-none\">Lectures</a> / ";
            echo "<span class=\"text-muted\">" . escape($lecture['title']) . "</span>";
            echo "</nav>";

            echo "<div class=\"row\">";

            // === MAIN CONTENT ===
            echo "<div class=\"col-lg-8\">";

            // Title & Meta
            echo "<h1 class=\"h2 mb-2\">" . escape($lecture['title']) . "</h1>";

            echo "<p class=\"text-muted mb-3\">";
            if (!empty($lecture['scholar_name'])) {
                echo "By <a href=\"" . $this->url->to('dacwah/scholar', ['slug' => $lecture['scholar_slug']]) . "\" class=\"text-decoration-none fw-medium\">";
                echo escape($lecture['scholar_name']) . "</a>";
            }
            if (!empty($lecture['series_title'])) {
                echo " · Part of <a href=\"" . $this->url->to('dacwah/series/view', ['slug' => $lecture['series_slug']]) . "\" class=\"text-decoration-none\">";
                echo escape($lecture['series_title']) . "</a>";
            }
            $date = !empty($lecture['lecture_date']) ? date('F j, Y', strtotime($lecture['lecture_date'])) : date('F j, Y', strtotime($lecture['created_at'] ?? ''));
            echo " · {$date}";
            echo "</p>";

            // Category Badge
            if (!empty($lecture['category_name'])) {
                echo "<span class=\"badge bg-primary mb-4\">" . escape($lecture['category_name']) . "</span>";
            }

            // === MEDIA PLAYER ===
            if (!empty($media)) {
                echo "<div class=\"card mb-4\">";
                echo "<div class=\"card-body p-3\">";
                echo "<h3 class=\"h6 mb-3\">Listen / Watch</h3>";
                foreach ($media as $file) {
                    $url = escape($file['file_url'] ?? $file['path'] ?? '');
                    $type = strtolower($file['type'] ?? pathinfo($url, PATHINFO_EXTENSION));
                    if (in_array($type, ['mp3','wav','ogg','audio'])) {
                        echo "<div class=\"mb-2\">";
                        echo "<audio controls class=\"w-100\" preload=\"metadata\">";
                        echo "<source src=\"{$url}\" type=\"audio/mpeg\">";
                        echo "Your browser does not support audio. <a href=\"{$url}\" target=\"_blank\">Download</a>";
                        echo "</div>";
                    } elseif (in_array($type, ['mp4','webm','video'])) {
                        echo "<div class=\"mb-2\">";
                        echo "<video controls class=\"w-100\" preload=\"metadata\" style=\"max-height:400px\">";
                        echo "<source src=\"{$url}\" type=\"video/mp4\">";
                        echo "Your browser does not support video. <a href=\"{$url}\" target=\"_blank\">Download</a>";
                        echo "</video></div>";
                    } else {
                        echo "<a href=\"{$url}\" target=\"_blank\" class=\"btn btn-outline-primary btn-sm mb-2\">Download File</a>";
                    }
                }
                echo "</div></div>";
            }

            // === DESCRIPTION ===
            if (!empty($lecture['description'])) {
                echo "<div class=\"card mb-4\">";
                echo "<div class=\"card-body\">";
                echo "<h3 class=\"h6 mb-2\">Description</h3>";
                echo "<div>" . nl2br(escape($lecture['description'])) . "</div>";
                echo "</div></div>";
            }

            echo "</div>"; // .col-lg-8

            // === SIDEBAR ===
            echo "<div class=\"col-lg-4\">";

            // Scholar Info
            if (!empty($lecture['scholar_name'])) {
                echo "<div class=\"card mb-4\">";
                echo "<div class=\"card-body\">";
                echo "<h3 class=\"h6 mb-2\">About the Scholar</h3>";
                echo "<h4 class=\"h6 fw-bold mb-1\">";
                echo "<a href=\"" . $this->url->to('dacwah/scholar', ['slug' => $lecture['scholar_slug']]) . "\" class=\"text-decoration-none\">";
                echo escape($lecture['scholar_name']) . "</a></h4>";
                if (!empty($lecture['scholar_bio'])) {
                    echo "<p class=\"small text-muted mt-2\">" . escape(truncate($lecture['scholar_bio'], 120)) . "</p>";
                }
                echo "</div></div>";
            }

            // Series Info
            if (!empty($lecture['series_title'])) {
                echo "<div class=\"card mb-4\">";
                echo "<div class=\"card-body\">";
                echo "<h3 class=\"h6 mb-2\">Part of Series</h3>";
                echo "<h4 class=\"h6 fw-bold mb-1\">";
                echo "<a href=\"" . $this->url->to('dacwah/series/view', ['slug' => $lecture['series_slug']]) . "\" class=\"text-decoration-none\">";
                echo escape($lecture['series_title']) . "</a></h4>";
                if (!empty($lecture['total_lessons'])) {
                    echo "<p class=\"small text-muted mt-2\">" . (int)$lecture['total_lessons'] . " total lessons in this series</p>";
                }
                echo "</div></div>";
            }

            // Related Lectures
            if (!empty($related)) {
                echo "<div class=\"card\">";
                echo "<div class=\"card-body\">";
                echo "<h3 class=\"h6 mb-3\">More from This Scholar</h3>";
                echo "<ul class=\"list-unstyled mb-0\">";
                foreach ($related as $rel) {
                    echo "<li class=\"mb-2 pb-2 border-bottom\">";
                    echo "<a href=\"" . $this->url->to('dacwah/lecture', ['slug' => $rel['slug']]) . "\" class=\"text-decoration-none\">";
                    echo escape($rel['title']) . "</a>";
                    $relDate = !empty($rel['lecture_date']) ? date('M j, Y', strtotime($rel['lecture_date'])) : date('M j, Y', strtotime($rel['created_at'] ?? ''));
                    echo "<p class=\"small text-muted mb-0\">{$relDate}</p>";
                    echo "</li>";
                }
                echo "</ul></div></div>";
            }

            echo "</div>"; // .col-lg-4

            echo "</div>"; // .row
            echo "</div>"; // .container
        });
    }
}