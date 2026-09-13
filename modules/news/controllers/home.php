<?php

use System\Engine\Controller;

class NewsHome extends Controller
{
    public function indexAction(): void
    {
        $this->view->assign('title', 'News');

        // FEATURED ARTICLE — prioritizes is_featured first, then latest
        $featured = $this->db->query(
            "SELECT n.*, c.name AS category_name
             FROM `news` n
             LEFT JOIN `news_categories` c ON c.id = n.category_id
             WHERE n.status = 1
             ORDER BY n.is_featured DESC, n.created_at DESC
             LIMIT 1"
        )->row;

        // Fallback when no articles exist
        if (empty($featured)) {
            $featured = [
                'id' => 0,
                'title' => 'No news yet',
                'slug' => '',
                'body' => 'Publish your first article to bring the newsroom to life.',
                'category_name' => 'General',
                'created_at' => date('Y-m-d H:i:s'),
            ];
        }

        // LATEST ARTICLES — exclude featured, get next 5
        $latest = $this->db->query(
            "SELECT n.*, c.name AS category_name
             FROM `news` n
             LEFT JOIN `news_categories` c ON c.id = n.category_id
             WHERE n.status = 1 AND n.id != ?
             ORDER BY n.is_featured DESC, n.created_at DESC
             LIMIT 5",
            [(int) ($featured['id'] ?? 0)]
        )->rows;

        $topStories  = array_slice($latest, 0, 3);
        $moreStories = array_slice($latest, 3);

        echo $this->view->inline(function ($view) use ($featured, $topStories, $moreStories): void {
            // Featured article data — USE truncate_summary() to strip HTML & decode entities
            $heroTitle   = escape((string) ($featured['title'] ?? 'No news yet'));
            $heroSlug    = (string) ($featured['slug'] ?? '');
            $heroCat     = escape((string) ($featured['category_name'] ?? 'General'));
            $heroDate    = escape(date('j M Y', strtotime((string) ($featured['created_at'] ?? date('Y-m-d H:i:s')))));
            $heroSummary = truncate($featured['body'] ?? '', 180); // ✅ Strips <p> + decodes HTML
            $heroUrl     = $heroSlug !== '' ? $view->url->to('news/article', ['slug' => $heroSlug]) : '#';
    ?>
    <style>
        .news-shell { max-width: 1240px; margin: 0 auto; padding: 1.5rem 1rem 3rem; }
        .news-header { display: flex; align-items: center; justify-content: space-between; padding-bottom: 1rem; border-bottom: 1px solid #e5e7eb; margin-bottom: 1.5rem; }
        .news-brand { font-size: 2.25rem; font-weight: 900; letter-spacing: -0.05em; color: #0f172a; text-decoration: none; }
        .news-brand span { color: #dc2626; }
        .news-grid { display: grid; grid-template-columns: 2.2fr 1fr; gap: 1.5rem; }
        .news-featured { background: linear-gradient(135deg, #0f172a 0%, #1e40af 55%, #3b82f6 100%); border-radius: 16px; overflow: hidden; color: #fff; }
        .news-featured-img { padding: 2.5rem; }
        .news-featured-badge { display: inline-block; background: rgba(255,255,255,0.18); border: 1px solid rgba(255,255,255,0.3); padding: 0.3rem 0.9rem; border-radius: 999px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
        .news-featured-body { padding: 0 2.5rem 2.5rem; }
        .news-featured h2 { font-size: clamp(1.8rem, 3.5vw, 2.8rem); line-height: 1.1; margin: 1rem 0 0.8rem; font-weight: 800; }
        .news-featured h2 a { color: #fff; text-decoration: none; }
        .news-featured h2 a:hover { text-decoration: underline; }
        .news-featured p { font-size: 1.05rem; opacity: 0.92; line-height: 1.7; margin-bottom: 0.5rem; }
        .news-meta { display: flex; gap: 1.2rem; font-size: 0.85rem; opacity: 0.75; text-transform: uppercase; letter-spacing: 0.05em; }
        .news-sidebar { display: grid; gap: 1rem; }
        .news-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 1.3rem; box-shadow: 0 1px 3px rgba(0,0,0,0.04); transition: box-shadow 0.2s; }
        .news-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.07); }
        .news-card-cat { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.08em; color: #dc2626; font-weight: 700; margin-bottom: 0.4rem; }
        .news-card h3 { font-size: 1.15rem; margin: 0 0 0.5rem; line-height: 1.35; }
        .news-card h3 a { color: #111827; text-decoration: none; }
        .news-card h3 a:hover { color: #2563eb; text-decoration: underline; }
        .news-card-date { font-size: 0.85rem; color: #6b7280; }
        .news-more { margin-top: 2.5rem; }
        .news-more h3 { font-size: 1.1rem; text-transform: uppercase; letter-spacing: 0.08em; color: #4b5563; border-bottom: 2px solid #e5e7eb; padding-bottom: 0.6rem; margin-bottom: 1rem; }
        .news-list-item { display: flex; align-items: flex-start; gap: 1rem; padding: 1rem 0; border-bottom: 1px solid #f1f5f9; }
        .news-list-item:last-child { border-bottom: none; }
        .news-list-cat { min-width: 80px; background: #f1f5f9; border-radius: 999px; padding: 0.35rem 0.5rem; font-size: 0.7rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; color: #475569; text-align: center; }
        .news-list-content h4 { margin: 0 0 0.3rem; font-size: 1rem; }
        .news-list-content h4 a { color: #1e293b; text-decoration: none; }
        .news-list-content h4 a:hover { color: #2563eb; text-decoration: underline; }
        .news-list-date { font-size: 0.8rem; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; }

        @media (max-width: 900px) {
            .news-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 576px) {
            .news-featured-img, .news-featured-body { padding-left: 1.2rem; padding-right: 1.2rem; }
            .news-brand { font-size: 1.6rem; }
        }
    </style>

    <div class="news-shell">
        <!-- Header -->
        <div class="news-header">
            <a href="<?= $view->url->to('news') ?>" class="news-brand"><span>NEWS</span></a>
        </div>

        <!-- Featured + Top Stories Grid -->
        <div class="news-grid">
            <!-- Featured Article -->
            <article class="news-featured">
                <div class="news-featured-img">
                    <span class="news-featured-badge"><?= $heroCat ?></span>
                </div>
                <div class="news-featured-body">
                    <h2><a href="<?= $heroUrl ?>"><?= $heroTitle ?></a></h2>
                    <p><?= $heroSummary ?></p>
                    <div class="news-meta">
                        <span><?= $heroDate ?></span>
                    </div>
                </div>
            </article>

            <!-- Sidebar / Top Stories -->
            <aside class="news-sidebar">
                <?php foreach ($topStories as $story):
                    $sTitle = escape((string) ($story['title'] ?? 'Untitled'));
                    $sSlug  = (string) ($story['slug'] ?? '');
                    $sCat   = escape((string) ($story['category_name'] ?? 'General'));
                    $sDate  = escape(date('j M Y', strtotime((string) ($story['created_at'] ?? date('Y-m-d H:i:s')))));
                    $sUrl   = $sSlug !== '' ? $view->url->to('news/article', ['slug' => $sSlug]) : '#';
                ?>
                <div class="news-card">
                    <div class="news-card-cat"><?= $sCat ?></div>
                    <h3><a href="<?= $sUrl ?>"><?= $sTitle ?></a></h3>
                    <div class="news-card-date"><?= $sDate ?></div>
                </div>
                <?php endforeach; ?>
            </aside>
        </div>

        <!-- More Stories List -->
        <?php if (!empty($moreStories)): ?>
        <div class="news-more">
            <h3>More Stories</h3>
            <?php foreach ($moreStories as $story):
                $sTitle = escape((string) ($story['title'] ?? 'Untitled'));
                $sSlug  = (string) ($story['slug'] ?? '');
                $sCat   = escape((string) ($story['category_name'] ?? 'General'));
                $sDate  = escape(date('j M Y', strtotime((string) ($story['created_at'] ?? date('Y-m-d H:i:s')))));
                $sUrl   = $sSlug !== '' ? $view->url->to('news/article', ['slug' => $sSlug]) : '#';
            ?>
            <div class="news-list-item">
                <div class="news-list-cat"><?= $sCat ?></div>
                <div class="news-list-content">
                    <h4><a href="<?= $sUrl ?>"><?= $sTitle ?></a></h4>
                    <div class="news-list-date"><?= $sDate ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
        }, 'main');
    }
}