<?php

use System\Engine\Model;

class DacwahArticleModel extends Model
{
    protected $table = '#__dacwah_articles';

    /**
     * Get all published articles/fatwas with scholar & category info.
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['a.status = ?'];
        $params = ['published'];

        if (!empty($filters['scholar_id'])) {
            $where[] = 'a.scholar_id = ?';
            $params[] = (int)$filters['scholar_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'a.category_id = ?';
            $params[] = (int)$filters['category_id'];
        }
        if (!empty($filters['article_type'])) {
            $where[] = 'a.article_type = ?';
            $params[] = $filters['article_type'];
        }
        if (!empty($filters['is_featured'])) {
            $where[] = 'a.is_featured = ?';
            $params[] = 1;
        }
        if (!empty($filters['search'])) {
            $where[] = '(a.title LIKE ? OR a.content LIKE ? OR a.reference LIKE ?)';
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        $sort = !empty($filters['sort']) ? $filters['sort'] : 'a.created_at';
        $order = !empty($filters['order']) ? strtoupper($filters['order']) : 'DESC';

        return $this->db->query(
            "SELECT a.*, sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug
             FROM {$this->table} a
             LEFT JOIN #__dacwah_scholars sch ON a.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON a.category_id = c.id
             WHERE {$whereSql}
             ORDER BY {$sort} {$order}, a.title
             LIMIT {$offset}, {$perPage}",
            $params
        )->rows;
    }

    /**
     * Get single article by ID.
     */
    public function getById(int $id): ?array
    {
        return $this->db->query(
            "SELECT a.*, sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug
             FROM {$this->table} a
             LEFT JOIN #__dacwah_scholars sch ON a.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON a.category_id = c.id
             WHERE a.id = ? AND a.status = 'published'",
            [$id]
        )->row ?: null;
    }

    /**
     * Get single article by slug.
     */
    public function getBySlug(string $slug): ?array
    {
        return $this->db->query(
            "SELECT a.*, sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug
             FROM {$this->table} a
             LEFT JOIN #__dacwah_scholars sch ON a.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON a.category_id = c.id
             WHERE a.slug = ? AND a.status = 'published'",
            [$slug]
        )->row ?: null;
    }

    /**
     * Increment view counter.
     */
    public function incrementViews(int $id): void
    {
        $this->db->query("UPDATE {$this->table} SET views = views + 1 WHERE id = ?", [$id]);
    }

    /**
     * Save article (create or update).
     */
    public function save(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) $errors['title'] = 'Title is required.';
        if (empty($data['content'])) $errors['content'] = 'Content is required.';
        if (empty($data['category_id'])) $errors['category_id'] = 'Category is required.';

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $fields = [
            'title'         => trim($data['title']),
            'slug'          => !empty($data['slug']) ? $data['slug'] : $this->createSlug($data['title']),
            'content'       => $data['content'],
            'scholar_id'    => !empty($data['scholar_id']) ? (int)$data['scholar_id'] : null,
            'category_id'   => (int)$data['category_id'],
            'article_type'  => $data['article_type'] ?? 'article',
            'reference'     => $data['reference'] ?? null,
            'is_featured'   => (int)($data['is_featured'] ?? 0),
            'status'        => $data['status'] ?? 'published',
        ];

        $originalScholarId = null;
        if (!empty($data['id'])) {
            $existing = $this->getById((int)$data['id']);
            if ($existing) $originalScholarId = $existing['scholar_id'];
            $this->db->update($this->table, $fields, ['id' => (int)$data['id']]);
            $id = (int)$data['id'];
        } else {
            $id = $this->db->insert($this->table, $fields);
        }

        $this->updateScholarTotal($fields['scholar_id'], $originalScholarId);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete article.
     */
    public function delete(int $id): array
    {
        $article = $this->getById($id);
        if (!$article) {
            return ['success' => false, 'errors' => ['Article not found.']];
        }

        $deleted = $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);

        if ($deleted) {
            $this->updateScholarTotal(0, $article['scholar_id']);
        }

        return ['success' => (bool)$deleted];
    }

    // ─── Internal Helpers ───

    private function createSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) ?: 'article';
        $base = $slug;
        $counter = 1;
        while ($this->db->query("SELECT id FROM {$this->table} WHERE slug = ?", [$slug])->row) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function updateScholarTotal(?int $newScholarId, ?int $oldScholarId): void
    {
        foreach (array_filter([$oldScholarId, $newScholarId]) as $sid) {
            $count = $this->db->query("SELECT COUNT(*) AS cnt FROM {$this->table} WHERE scholar_id = ?", [$sid])->row['cnt'];
            $this->db->query("UPDATE #__dacwah_scholars SET total_articles = ? WHERE id = ?", [(int)$count, $sid]);
        }
    }
}