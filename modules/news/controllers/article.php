<?php

use System\Engine\Controller;

class NewsArticle extends Controller
{
    public function indexAction(): void
    {
        $slug = (string) $this->request->get('slug', 'string', '');

        // Validate slug format
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            redirect_to('news');
        }

        // Fetch article with category & author
        $article = $this->db->query(
            "SELECT n.*, c.name AS category_name, u.username AS author_name
             FROM `news` n
             LEFT JOIN `news_categories` c ON c.id = n.category_id
             LEFT JOIN `users` u ON u.id = n.author_id
             WHERE n.slug = ? AND n.status = 1
             LIMIT 1",
            [$slug]
        )->row;

        if (empty($article)) {
            redirect_to('news');
        }

        $articleId = (int) $article['id'];

        // Fetch TAGS for this article
        $tags = $this->db->query(
            "SELECT t.name, t.slug
             FROM `news_tags` t
             INNER JOIN `news_to_tags` nt ON nt.tag_id = t.tag_id
             WHERE nt.news_id = ?
             ORDER BY t.name ASC",
            [$articleId]
        )->rows;

        // Set page title
        $herotitle = escape((string) ($article['title'] ?? 'Untitled'));
        $this->view->assign('title', $herotitle);

        // Increment view counter (rate-limited: 1 view per hour per visitor)
        $this->incrementViews($articleId);

        // Related articles (latest excluding current)
        $related = $this->db->query(
            "SELECT n.id, n.title, n.slug, n.created_at, c.name AS category_name
             FROM `news` n
             LEFT JOIN `news_categories` c ON c.id = n.category_id
             WHERE n.status = 1 AND n.id != ?
             ORDER BY n.created_at DESC
             LIMIT 3",
            [$articleId]
        )->rows;

        // Decode & sanitize HTML body
        $rawBody = html_entity_decode((string) ($article['body'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $article['body_safe'] = $this->sanitizeHtml($rawBody);

        echo $this->view->inline(function ($view) use ($article, $tags, $related): void {
            $title     = escape((string) ($article['title'] ?? 'Untitled story'));
            $body      = $article['body_safe'];
            $category  = escape((string) ($article['category_name'] ?? 'General'));
            $date      = escape(date('j M Y', strtotime((string) ($article['created_at'] ?? date('Y-m-d H:i:s')))));
            $author    = escape((string) ($article['author_name'] ?? 'Editor'));
            $views     = (int) ($article['views'] ?? 0);
    ?>
    <style>
        .news-article { max-width: 980px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        .news-article .eyebrow { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.12em; color: #dc2626; font-weight: 800; }
        .news-article h1 { font-size: clamp(2.2rem, 4vw, 3.5rem); line-height: 1.1; letter-spacing: -0.03em; margin: 10px 0 18px; color: #0f172a; }
        .news-article .meta { display: flex; flex-wrap: wrap; gap: 18px; color: #64748b; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.06em; padding-bottom: 1.2rem; border-bottom: 1px solid #e5e7eb; }
        .news-article .content { margin-top: 1.5rem; font-size: 1.08rem; line-height: 1.9; color: #1f2937; }
        .news-article .content p { margin-bottom: 1.4rem; }
        .news-article .content h2, .news-article .content h3 { margin-top: 2rem; margin-bottom: 1rem; color: #0f172a; }
        .news-article .content blockquote { border-left: 4px solid #3b82f6; padding-left: 1.2rem; font-style: italic; color: #475569; margin: 1.5rem 0; }

        /* ✅ TAGS STYLES */
        .news-article .tags { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px dashed #e5e7eb; display: flex; flex-wrap: wrap; gap: 0.6rem; }
        .news-article .tags-label { font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; color: #6b7280; font-weight: 600; width: 100%; margin-bottom: 0.3rem; }
        .news-article .tag { display: inline-block; background: #eff6ff; color: #1d4ed8; font-size: 0.85rem; padding: 0.35rem 0.9rem; border-radius: 999px; text-decoration: none; transition: all 0.15s; }
        .news-article .tag:hover { background: #dbeafe; color: #1e40af; text-decoration: none; }

        .news-article .related { margin-top: 3rem; padding-top: 2rem; border-top: 1px solid #e5e7eb; }
        .news-article .related h3 { font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.08em; color: #475569; margin-bottom: 1.2rem; }
        .news-article .related-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.2rem; }
        .news-article .mini { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.3rem; transition: box-shadow 0.2s; }
        .news-article .mini:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
        .news-article .mini .eyebrow { font-size: 0.65rem; margin-bottom: 0.4rem; }
        .news-article .mini h4 { font-size: 1rem; line-height: 1.4; margin: 0; }
        .news-article .mini h4 a { color: #111827; text-decoration: none; }
        .news-article .mini h4 a:hover { color: #2563eb; text-decoration: underline; }
    </style>

    <div class="news-article">
        <div class="eyebrow"><?= $category ?></div>
        <h1><?= $title ?></h1>
        <div class="meta">
            <span><?= $date ?></span>
            <span>By <?= $author ?></span>
            <span><?= $views ?> view<?= $views !== 1 ? 's' : '' ?></span>
        </div>

        <div class="content">
            <?= $body ?>
        </div>

        <?php if (!empty($tags)): ?>
        <!-- ✅ TAGS DISPLAY -->
        <div class="tags">
            <span class="tags-label">Topics:</span>
            <?php foreach ($tags as $tag):
                $tagName = escape((string) ($tag['name'] ?? ''));
                $tagSlug = (string) ($tag['slug'] ?? '');
                $tagUrl  = $tagSlug !== '' ? $view->url->to('news/tag', ['slug' => $tagSlug]) : '#';
            ?>
            <a href="<?= $tagUrl ?>" class="tag"><?= $tagName ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($related)): ?>
        <div class="related">
            <h3>Related Articles</h3>
            <div class="related-grid">
                <?php foreach ($related as $story):
                    $sTitle = escape((string) ($story['title'] ?? 'Untitled'));
                    $sSlug  = (string) ($story['slug'] ?? '');
                    $sCat   = escape((string) ($story['category_name'] ?? 'General'));
                    $sUrl   = $sSlug !== '' ? $view->url->to('news/article', ['slug' => $sSlug]) : '#';
                ?>
                <div class="mini">
                    <div class="eyebrow"><?= $sCat ?></div>
                    <h4><a href="<?= $sUrl ?>"><?= $sTitle ?></a></h4>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
        }, 'main');
    }

    /**
     * Sanitize HTML body — allow safe tags, remove dangerous attributes
     */
    private function sanitizeHtml(string $html): string
    {
        $allowedTags = ['p', 'br', 'strong', 'b', 'em', 'i', 'ul', 'ol', 'li',
                        'h2', 'h3', 'h4', 'blockquote', 'pre', 'code', 'a'];

        // Keep only safe HTML tags
        $html = strip_tags($html, '<' . implode('><', $allowedTags) . '>');

        // Remove dangerous event handlers & javascript:
        $html = preg_replace('/\s+on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);
        $html = preg_replace('/\s+href\s*=\s*["\']javascript:[^"\']*["\']/i', '', $html);

        return $html;
    }

    /**
     * Increment view counter — rate-limited to 1 view/hour per visitor
     */
    private function incrementViews(int $articleId): void
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // Check if viewed in last hour
        $recent = $this->db->query(
            "SELECT 1 FROM `news_views` 
             WHERE article_id = ? AND ip = ? AND viewed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
             LIMIT 1",
            [$articleId, $ip]
        )->row;

        if (empty($recent)) {
            // Update view count
            $this->db->query(
                "UPDATE `news` SET views = views + 1 WHERE id = ?",
                [$articleId]
            );
            // Log view
            $this->db->query(
                "INSERT INTO `news_views` (article_id, ip, user_agent, viewed_at) 
                 VALUES (?, ?, ?, NOW())",
                [$articleId, $ip, $userAgent]
            );
        }
    }
}