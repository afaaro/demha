<?php

use System\Engine\Controller;

class NewsTag extends Controller {
    public function indexAction(): void {
        $slug = (string) ($this->request->route('slug') ?? $this->request->get('slug', 'string', ''));
        $tag = $this->db->query(
            "SELECT * FROM `#__news_tags` WHERE slug = ? LIMIT 1",
            [$slug]
        )->row;

        if (empty($tag)) {
            redirect_to('news');
        }

        $articles = $this->db->query(
            "SELECT n.*, c.name AS category_name
             FROM `#__news_to_tags` nt
             JOIN `#__news` n ON n.id = nt.news_id
             LEFT JOIN `#__news_categories` c ON c.id = n.category_id
             WHERE nt.tag_id = ? AND n.status = 1
             ORDER BY n.created_at DESC",
            [(int) ($tag['tag_id'] ?? 0)]
        )->rows;

        echo $this->view->inline(static function ($view) use ($tag, $articles): void {
            $tagName = $view->e((string) ($tag['name'] ?? 'Tag'));
            echo '<div class="container py-4">';
            echo '<div class="mb-4"><span class="text-uppercase small fw-bold text-danger">Topic</span><h1 class="display-6 fw-bold mt-2">#' . $tagName . '</h1></div>';
            echo '<div class="row g-4">';

            if (empty($articles)) {
                echo '<div class="col-12"><div class="alert alert-light border">No stories published under this topic yet.</div></div>';
            } else {
                foreach ($articles as $article) {
                    $title = $view->e((string) ($article['title'] ?? 'Untitled'));
                    $slug = (string) ($article['slug'] ?? '');
                    $excerpt = $view->e(substr(strip_tags((string) ($article['body'] ?? '')), 0, 180));
                    $date = $view->e(date('j M Y', strtotime((string) ($article['created_at'] ?? date('Y-m-d H:i:s')))));
                    echo '<div class="col-md-6">';
                    echo '<div class="card h-100 border-0 shadow-sm">';
                    echo '<div class="card-body">';
                    echo '<div class="text-uppercase small fw-bold text-danger">' . $view->e((string) ($article['category_name'] ?? 'General')) . '</div>';
                    echo '<h3 class="mt-2"><a href="' . htmlspecialchars(route_url('news/article', ['slug' => $slug], false), ENT_QUOTES, 'UTF-8') . '" class="text-decoration-none text-reset">' . $title . '</a></h3>';
                    echo '<div class="text-muted small mt-2">' . $date . '</div>';
                    echo '<p class="mt-3 mb-0">' . $excerpt . '...</p>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
            }

            echo '</div>';
            echo '</div>';
        }, 'main');
    }
}
