<?php

use System\Engine\Controller;

class NewsArticle extends Controller {
    public function indexAction(): void {
        $slug = (string) $this->request->get('slug', 'string', '');
        // Generate a nonce for inline scripts/styles
        // $nonce = base64_encode(random_bytes(16));
        // header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'nonce-{$nonce}'; img-src 'self' data:;");
        // Validate slug format
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            redirect_to('news');
        }
        
        $article = $this->db->query(
            "SELECT n.*, c.name AS category_name, u.username AS author_name
             FROM `#__news` n
             LEFT JOIN `#__news_categories` c ON c.id = n.category_id
             LEFT JOIN `#__users` u ON u.id = n.author_id
             WHERE n.slug = ? AND n.status = 1
             LIMIT 1",
            [$slug]
        )->row;

        if (empty($article)) {
            redirect_to('news');
        }


        // Rate-limited view counter
        $this->incrementViews((int) $article['id']);
        
        $related = $this->db->query(
            "SELECT n.id, n.title, n.slug, n.created_at, c.name AS category_name
             FROM `#__news` n
             LEFT JOIN `#__news_categories` c ON c.id = n.category_id
             WHERE n.status = 1 AND n.id != ?
             ORDER BY n.created_at DESC
             LIMIT 3",
            [(int) $article['id']]
        )->rows;
        
        // Sanitize article body - CRITICAL FIX
        $article['body_safe'] = $this->sanitizeHtml((string) ($article['body'] ?? ''));
        
        echo $this->view->inline(static function ($view) use ($article, $related): void {
            $title = $view->e((string) ($article['title'] ?? 'Untitled story'));
            $body = $article['body_safe'];
            $body = preg_replace('~(?:\.\./)+~', $view->request->getBasePath() . '/', $body);
            $category = $view->e((string) ($article['category_name'] ?? 'General'));
            $date = $view->e(date('j M Y', strtotime((string) ($article['created_at'] ?? date('Y-m-d H:i:s')))));
            $author = $view->e((string) ($article['author_name'] ?? 'Editor'));
            $views = (int) ($article['views'] ?? 0);

            echo '<style>
                .news-article { max-width: 980px; margin: 0 auto; padding: 0 0 2rem; }
                .news-article .eyebrow { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.12em; color: #d71920; font-weight: 800; }
                .news-article h1 { font-size: clamp(2.2rem, 4vw, 4rem); line-height: 1.04; letter-spacing: -0.05em; margin: 10px 0 18px; color: #0f172a; }
                .news-article .meta { display: flex; flex-wrap: wrap; gap: 18px; color: #64748b; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; }
                .news-article .lede { background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 100%); color: white; padding: 1.5rem 1.75rem; border-radius: 18px; margin: 24px 0; }
                .news-article .content { margin-top: 1.2rem;  font-size: 1.07rem; line-height: 1.9; color: #1f2937; }
                .news-article .content p { margin-bottom: 1.2rem; }
                .news-article .related { margin-top: 2.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb; }
                .news-article .related-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 18px; margin-top: 18px; }
                .news-article .mini { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; }
                .news-article .mini .body { padding: 16px; }
                .news-article .mini h4 { font-size: 1.08rem; line-height: 1.35; margin: 6px 0; }
            </style>';

            echo '<div class="news-article">';
            echo '<div class="eyebrow">' . $category . '</div>';
            echo '<h1>' . $title . '</h1>';
            echo '<div class="meta"><span>' . $date . '</span><span>By ' . $author . '</span><span>' . $views . ' views</span></div>';
            echo '<div class="content">' . htmlspecialchars_decode($body) . '</div>';

            if (!empty($related)) {
                echo '<div class="related">';
                echo '<h3 class="mb-0">More stories</h3>';
                echo '<div class="related-grid">';
                foreach ($related as $story) {
                    $storyTitle = $view->e((string) ($story['title'] ?? 'Untitled'));
                    $storySlug = (string) ($story['slug'] ?? '');
                    $storyCategory = $view->e((string) ($story['category_name'] ?? 'General'));
                    echo '<div class="mini">';
                    echo '<div class="body">';
                    echo '<div class="eyebrow" style="margin-bottom: 0; font-size: 0.65rem;">' . $storyCategory . '</div>';
                    echo '<h4><a href="' . htmlspecialchars(route_url('news/article', ['slug' => $storySlug], false), ENT_QUOTES, 'UTF-8') . '" class="text-decoration-none text-reset">' . $storyTitle . '</a></h4>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
                echo '</div>';
            }

            echo '</div>';
        }, 'main');
    }

    private function sanitizeHtml(string $html): string {
        // Use HTML Purifier or similar library
        // OR implement a whitelist approach:
        $allowedTags = [
            'p', 'br', 'strong', 'em', 'ul', 'ol', 'li',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
            'blockquote', 'pre', 'code', 'a', 'img',
            'span', 'div' // Be careful with these
        ];
        
        // Remove dangerous attributes and protocols
        $html = strip_tags($html, '<' . implode('><', $allowedTags) . '>');
        
        // Remove dangerous event handlers (onclick, onerror, etc.)
        $html = preg_replace('/\s*on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        
        // Remove javascript: protocol
        $html = preg_replace('/\s*href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $html);
        
        // Sanitize image src to prevent path traversal
        $html = preg_replace_callback(
            '/<img[^>]*src=["\']([^"\']*)["\'][^>]*>/i',
            function($matches) {
                $src = $matches[1];
                $src = str_replace(['..', './', '\\'], '', $src);
                return str_replace($matches[1], $src, $matches[0]);
            },
            $html
        );
        
        return $html;
    }

    private function incrementViews(int $articleId): void {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Check for duplicate views (simple rate limiting)
        $recentView = $this->db->query(
            "SELECT 1 FROM `#__news_views` 
             WHERE article_id = ? AND ip = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             LIMIT 1",
            [$articleId, $ip]
        )->row;
        
        if (empty($recentView)) {
            $this->db->query(
                "UPDATE `#__news` SET views = views + 1 WHERE id = ?",
                [$articleId]
            );
            
            $this->db->query(
                "INSERT INTO `#__news_views` (article_id, ip, user_agent, viewed_at) 
                 VALUES (?, ?, ?, NOW())",
                [$articleId, $ip, $userAgent]
            );
        }
    }
}
