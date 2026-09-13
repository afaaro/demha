<?php

use System\Engine\Controller;

class NewsTag extends Controller
{
    public function indexAction(): void
    {
        $slug = (string) ($this->request->route('slug') ?? $this->request->get('slug', 'string', ''));

        // Validate slug format
        if ($slug === '' || !preg_match('/^[a-z0-9-]+$/', $slug)) {
            redirect_to('news');
        }

        // Fetch tag by slug — matches your table `news_tags` primary key is `tag_id`
        $tag = $this->db->query(
            "SELECT * FROM `news_tags` WHERE slug = ? LIMIT 1",
            [$slug]
        )->row;

        if (empty($tag)) {
            redirect_to('news');
        }

        $tagId = (int) ($tag['tag_id'] ?? 0);

        // Fetch all articles with this tag
        $articles = $this->db->query(
            "SELECT n.*, c.name AS category_name
             FROM `news_to_tags` nt
             INNER JOIN `news` n ON n.id = nt.news_id
             LEFT JOIN `news_categories` c ON c.id = n.category_id
             WHERE nt.tag_id = ? AND n.status = 1
             ORDER BY n.created_at DESC",
            [$tagId]
        )->rows;

        // Set page title
        $tagName = escape((string) ($tag['name'] ?? 'Tag'));
        $this->view->assign('title', "{$tagName} — News");

        echo $this->view->inline(function ($view) use ($tag, $articles): void {
            $tagName = escape((string) ($tag['name'] ?? 'Tag'));
    ?>
    <style>
        .news-tag-page { max-width: 1024px; margin: 0 auto; padding: 1.5rem 1rem 4rem; }
        .news-tag-header { margin-bottom: 2.5rem; padding-bottom: 1.5rem; border-bottom: 1px solid #e5e7eb; }
        .news-tag-badge { display: inline-block; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.12em; color: #dc2626; font-weight: 800; margin-bottom: 0.5rem; }
        .news-tag-header h1 { font-size: clamp(2rem, 4vw, 3rem); margin: 0; color: #0f172a; }
        .news-tag-header h1 span { color: #2563eb; }
        .news-tag-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1.5rem; }
        .news-tag-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: all 0.2s; }
        .news-tag-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,0.08); transform: translateY(-2px); }
        .news-tag-card-cat { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #dc2626; font-weight: 700; margin-bottom: 0.6rem; }
        .news-tag-card h3 { font-size: 1.15rem; line-height: 1.4; margin: 0 0 0.6rem; }
        .news-tag-card h3 a { color: #111827; text-decoration: none; }
        .news-tag-card h3 a:hover { color: #2563eb; text-decoration: underline; }
        .news-tag-card-date { font-size: 0.85rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.8rem; }
        .news-tag-card-excerpt { color: #4b5563; line-height: 1.6; font-size: 0.95rem; margin: 0; }
        .news-tag-empty { text-align: center; padding: 4rem 2rem; color: #6b7280; }
        .news-tag-empty h3 { color: #374151; margin-bottom: 0.5rem; }
    </style>

    <div class="news-tag-page">
        <!-- Header -->
        <div class="news-tag-header">
            <div class="news-tag-badge">Topic / Tag</div>
            <h1><span>#</span><?= $tagName ?></h1>
        </div>

        <?php if (empty($articles)): ?>
            <div class="news-tag-empty">
                <h3>No stories yet</h3>
                <p>There are no articles published under this topic.</p>
                <p><a href="<?= $view->url->to('news') ?>">← Back to all news</a></p>
            </div>
        <?php else: ?>
            <div class="news-tag-grid">
                <?php foreach ($articles as $article):
                    $title   = escape((string) ($article['title'] ?? 'Untitled'));
                    $slug    = (string) ($article['slug'] ?? '');
                    $excerpt = $this->truncateExcerpt((string) ($article['body'] ?? ''), 160);
                    $date    = escape(date('j M Y', strtotime((string) ($article['created_at'] ?? date('Y-m-d H:i:s')))));
                    $catName = escape((string) ($article['category_name'] ?? 'General'));
                    $url     = $slug !== '' ? $view->url->to('news/article', ['slug' => $slug]) : '#';
                ?>
                <div class="news-tag-card">
                    <div class="news-tag-card-cat"><?= $catName ?></div>
                    <h3><a href="<?= $url ?>"><?= $title ?></a></h3>
                    <div class="news-tag-card-date"><?= $date ?></div>
                    <p class="news-tag-card-excerpt"><?= $excerpt ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
        }, 'main');
    }

    /**
     * Clean excerpt — strip HTML + decode entities (works with Somali/Arabic!)
     */
    private function truncateExcerpt(string $html, int $maxLength = 160): string
    {
        // Decode HTML entities first (&lt; → <, &amp; → &, etc.)
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Strip ALL HTML tags
        $text = strip_tags($text);
        
        // Trim whitespace
        $text = trim(preg_replace('/\s+/', ' ', $text));
        
        // Safe multi-byte truncation
        if (mb_strlen($text, 'UTF-8') > $maxLength) {
            $text = rtrim(mb_substr($text, 0, $maxLength, 'UTF-8')) . '…';
        }
        
        return escape($text);
    }
}