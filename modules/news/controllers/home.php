<?php

use System\Engine\Controller;

class NewsHome extends Controller {
    public function indexAction(): void {
        $featured = $this->db->query(
            "SELECT n.*, c.name AS category_name, u.username AS author_name
             FROM `#__news` n
             LEFT JOIN `#__news_categories` c ON c.id = n.category_id
             LEFT JOIN `#__users` u ON u.id = n.author_id
             WHERE n.status = 1
             ORDER BY n.is_featured DESC, n.created_at DESC
             LIMIT 1"
        )->row;

        if (empty($featured)) {
            $featured = [
                'title' => 'No news yet',
                'slug' => '',
                'body' => 'Publish your first article to bring the newsroom to life.',
                'category_name' => 'General',
                'created_at' => date('Y-m-d H:i:s'),
                'author_name' => 'Editor',
            ];
        }

        $latest = $this->db->query(
            "SELECT n.*, c.name AS category_name
             FROM `#__news` n
             LEFT JOIN `#__news_categories` c ON c.id = n.category_id
             WHERE n.status = 1 AND n.id != ?
             ORDER BY n.is_featured DESC, n.created_at DESC
             LIMIT 5",
            [(int) ($featured['id'] ?? 0)]
        )->rows;

        $topStories = array_slice($latest, 0, 3);
        $moreStories = array_slice($latest, 3);

        echo $this->view->inline(static function ($view) use ($featured, $topStories, $moreStories): void {
            $heroTitle = $view->e((string) ($featured['title'] ?? 'No news yet'));
            $heroSlug = (string) ($featured['slug'] ?? '');
            $heroCategory = $view->e((string) ($featured['category_name'] ?? 'General'));
            $heroDate = $view->e(date('j M Y', strtotime((string) ($featured['created_at'] ?? date('Y-m-d H:i:s')))));
            $heroAuthor = $view->e((string) ($featured['author_name'] ?? 'Editor'));
            $heroSummary = $view->e(substr(strip_tags((string) ($featured['body'] ?? '')), 0, 180));

            echo '<style>
                .bbc-shell { max-width: 1200px; margin: 0 auto; padding: 0 0 2rem; }
                .bbc-topbar { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 0 12px; border-bottom: 1px solid #d9dfe8; }
                .bbc-brand { font-size: 2.25rem; font-weight: 900; letter-spacing: -0.08em; line-height: 1; color: #111827; }
                .bbc-brand span { color: #d71920; }
                .bbc-nav { display: flex; flex-wrap: wrap; gap: 18px; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; color: #475569; font-weight: 700; }
                .bbc-grid { display: grid; grid-template-columns: 2.1fr 1fr; gap: 24px; margin-top: 22px; }
                .bbc-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; overflow: hidden; box-shadow: 0 16px 36px rgba(15, 23, 42, 0.05); }
                .bbc-feature { padding: 0; }
                .bbc-feature .image { min-height: 310px; background: linear-gradient(135deg, #0f172a 0%, #2563eb 35%, #dbeafe 100%); display: flex; align-items: end; padding: 24px; }
                .bbc-feature .image .badge { display: inline-block; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: #fff; border-radius: 999px; padding: 6px 12px; font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; }
                .bbc-feature .body { padding: 24px; }
                .bbc-feature h1 { font-size: clamp(2.1rem, 3vw, 3.3rem); line-height: 1.04; margin: 0 0 12px; color: #0f172a; font-weight: 800; }
                .bbc-feature p { color: #475569; margin: 0; line-height: 1.7; }
                .bbc-meta { display: flex; flex-wrap: wrap; gap: 14px; color: #64748b; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.06em; margin-top: 12px; }
                .bbc-side { display: grid; gap: 16px; }
                .bbc-story { padding: 18px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; }
                .bbc-story .eyebrow { font-size: 0.7rem; text-transform: uppercase; letter-spacing: 0.08em; color: #d71920; font-weight: 800; }
                .bbc-story h3 { font-size: 1.25rem; margin: 8px 0 10px; line-height: 1.25; color: #111827; }
                .bbc-story p { margin: 0; color: #475569; line-height: 1.6; }
                .bbc-list { margin-top: 28px; display: grid; gap: 14px; }
                .bbc-list-item { display: flex; gap: 16px; align-items: flex-start; padding: 14px 0; border-bottom: 1px solid #e7edf5; }
                .bbc-list-item:last-child { border-bottom: 0; }
                .bbc-list-item .kicker { min-width: 78px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 999px; padding: 7px 10px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.06em; color: #334155; text-align: center; }
                .bbc-list-item h4 { margin: 2px 0 5px; font-size: 1.05rem; line-height: 1.35; color: #111827; }
                .bbc-list-item .meta { color: #64748b; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em; }
                @media (max-width: 768px) { .bbc-grid { grid-template-columns: 1fr; } .bbc-brand { font-size: 2rem; } }
            </style>';

            echo '<div class="bbc-shell">';
            echo '<div class="bbc-topbar">';
            echo '<div class="bbc-brand">BBC <span>NEWS</span></div>';
            echo '<nav class="bbc-nav"><span>Home</span><span>World</span><span>Business</span><span>Culture</span><span>Sport</span></nav>';
            echo '</div>';

            echo '<div class="bbc-grid">';
            echo '<article class="bbc-card bbc-feature">';
            echo '<div class="image"><span class="badge">' . $heroCategory . '</span></div>';
            echo '<div class="body">';
            echo '<h1><a href="' . ($heroSlug !== '' ? htmlspecialchars(route_url('news/article', ['slug' => $heroSlug], false), ENT_QUOTES, 'UTF-8') : '#') . '" class="text-decoration-none text-reset">' . $heroTitle . '</a></h1>';
            echo '<div class="bbc-meta"><span>' . $heroDate . '</span><span>' . $heroAuthor . '</span></div>';
            echo '<p class="mt-3">' . $heroSummary . '...</p>';
            echo '</div>';
            echo '</article>';

            echo '<aside class="bbc-side">';
            foreach ($topStories as $story) {
                $storyTitle = $view->e((string) ($story['title'] ?? 'Untitled'));
                $storySlug = (string) ($story['slug'] ?? '');
                $storyCategory = $view->e((string) ($story['category_name'] ?? 'General'));
                $storyDate = $view->e(date('j M Y', strtotime((string) ($story['created_at'] ?? date('Y-m-d H:i:s')))));
                echo '<div class="bbc-story">';
                echo '<div class="eyebrow">' . $storyCategory . '</div>';
                echo '<h3><a href="' . ($storySlug !== '' ? htmlspecialchars(route_url('news/article', ['slug' => $storySlug], false), ENT_QUOTES, 'UTF-8') : '#') . '" class="text-decoration-none text-reset">' . $storyTitle . '</a></h3>';
                echo '<p>' . $storyDate . '</p>';
                echo '</div>';
            }
            echo '</aside>';
            echo '</div>';

            echo '<div class="bbc-list">';
            foreach ($moreStories as $story) {
                $storyTitle = $view->e((string) ($story['title'] ?? 'Untitled'));
                $storySlug = (string) ($story['slug'] ?? '');
                $storyCategory = $view->e((string) ($story['category_name'] ?? 'General'));
                $storyDate = $view->e(date('j M Y', strtotime((string) ($story['created_at'] ?? date('Y-m-d H:i:s')))));
                echo '<div class="bbc-list-item">';
                echo '<div class="kicker">' . $storyCategory . '</div>';
                echo '<div>';
                echo '<div class="meta">' . $storyDate . '</div>';
                echo '<h4><a href="' . ($storySlug !== '' ? htmlspecialchars(route_url('news/article', ['slug' => $storySlug], false), ENT_QUOTES, 'UTF-8') : '#') . '" class="text-decoration-none text-reset">' . $storyTitle . '</a></h4>';
                echo '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
        }, 'main');
    }
}
