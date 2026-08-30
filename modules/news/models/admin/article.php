<?php
use System\Engine\Model;

class NewsAdminArticleModel extends Model
{
    public function getArticle(int $id): ?array
    {
        $query = $this->db->query("SELECT * FROM #__news WHERE id = ?", [$id]);
        return $query->num_rows ? $query->row : null;
    }

    public function getArticles(int $limit = 20, int $offset = 0, int $status = -1, int $category = 0, string $search = ''): array
    {
        $sql = "SELECT n.*, c.name AS category_name, u.username AS author_name
                FROM #__news n
                LEFT JOIN #__news_categories c ON c.id = n.category_id
                LEFT JOIN #__users u ON u.id = n.author_id
                WHERE 1";
        $params = [];

        if ($status >= 0) {
            $sql .= " AND n.status = ?";
            $params[] = $status;
        }
        if ($category > 0) {
            $sql .= " AND n.category_id = ?";
            $params[] = $category;
        }
        if (!empty($search)) {
            $sql .= " AND (n.title LIKE ? OR n.body LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $sql .= " ORDER BY n.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;

        $query = $this->db->query($sql, $params);
        return $query->num_rows ? $query->rows : [];
    }

    public function countArticles(int $status = -1, int $category = 0, string $search = ''): int
    {
        $sql = "SELECT COUNT(*) as total FROM #__news n WHERE 1";
        $params = [];

        if ($status >= 0) {
            $sql .= " AND n.status = ?";
            $params[] = $status;
        }
        if ($category > 0) {
            $sql .= " AND n.category_id = ?";
            $params[] = $category;
        }
        if (!empty($search)) {
            $sql .= " AND (n.title LIKE ? OR n.body LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        $query = $this->db->query($sql, $params);
        return (int) $query->row['total'];
    }

    public function getStats(): array
    {
        $total = (int) $this->db->query("SELECT COUNT(*) as total FROM #__news")->row['total'];
        $published = (int) $this->db->query("SELECT COUNT(*) as total FROM #__news WHERE status = 1")->row['total'];
        $drafts = (int) $this->db->query("SELECT COUNT(*) as total FROM #__news WHERE status = 0")->row['total'];

        return ['total' => $total, 'published' => $published, 'drafts' => $drafts];
    }

    public function saveArticle(int $id, array $data)
    {
        if ($id > 0) {
            $this->db->update('news', $data, ['id' => $id]);
            return $id;
        } else {
            return $this->db->insert('news', $data);
        }
    }

    public function deleteArticle(int $id): bool
    {
        $this->db->query("DELETE FROM #__news_to_tags WHERE news_id = ?", [$id]);
        return $this->db->delete('news', ['id' => $id]);
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $sql = "SELECT COUNT(*) as total FROM #__news WHERE slug = ?";
        $params = [$slug];
        if ($excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $query = $this->db->query($sql, $params);
        return (int) $query->row['total'] > 0;
    }

    public function getAllCategories(): array
    {
        $query = $this->db->query("SELECT id, name FROM #__news_categories ORDER BY name");
        return $query->num_rows ? $query->rows : [];
    }

    public function getArticleTags(int $newsId): array
    {
        $query = $this->db->query(
            "SELECT t.* FROM #__news_to_tags nt
             JOIN #__news_tags t ON t.tag_id = nt.tag_id
             WHERE nt.news_id = ?
             ORDER BY t.name ASC",
            [$newsId]
        );
        return $query->num_rows ? $query->rows : [];
    }

    public function saveArticleTags(int $newsId, string $tagsCsv): void
    {
        $this->db->query("DELETE FROM #__news_to_tags WHERE news_id = ?", [$newsId]);

        if ($tagsCsv === '') {
            return;
        }

        $tags = preg_split('/\s*,\s*/', $tagsCsv, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($tags as $tagName) {
            $tagName = trim($tagName);
            if ($tagName === '') {
                continue;
            }

            $slug = $this->makeSlug($tagName);
            $existing = $this->db->query("SELECT tag_id FROM #__news_tags WHERE slug = ? LIMIT 1", [$slug])->row;

            if (!empty($existing)) {
                $tagId = (int) ($existing['tag_id'] ?? 0);
            } else {
                $this->db->insert('news_tags', ['name' => $tagName, 'slug' => $slug]);
                $tagId = (int) $this->db->lastInsertId();
            }

            if ($tagId > 0) {
                $this->db->query("INSERT IGNORE INTO #__news_to_tags (news_id, tag_id) VALUES (?, ?)", [$newsId, $tagId]);
            }
        }
    }

    public function makeSlug(string $value): string
    {
        $value = preg_replace('/[^a-zA-Z0-9\s\-_]+/', '', $value);
        $value = preg_replace('/[\s_]+/', '-', strtolower(trim($value)));
        $value = preg_replace('/-+/', '-', trim($value, '-'));
        return $value;
    }

    public function createCategory(array $data): int
    {
        return $this->db->insert('news_categories', $data);
    }

    public function createTag(array $data): int
    {
        return $this->db->insert('news_tags', $data);
    }

    public function getAllCategoriesWithCount(): array
    {
        return $this->db->query(
            "SELECT c.*, COUNT(n.id) AS article_count
            FROM #__news_categories c
            LEFT JOIN #__news n ON n.category_id = c.id
            GROUP BY c.id
            ORDER BY c.name ASC"
        )->rows;
    }

    public function getAllTagsWithCount(): array
    {
        return $this->db->query(
            "SELECT t.*, COUNT(nt.news_id) AS article_count
            FROM #__news_tags t
            LEFT JOIN #__news_to_tags nt ON nt.tag_id = t.tag_id
            GROUP BY t.tag_id
            ORDER BY t.name ASC"
        )->rows;
    }
}