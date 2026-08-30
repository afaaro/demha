<?php

use System\Engine\Model;

class DacwahCategoryModel extends Model
{
    protected $table = '#__dacwah_categories';

    /**
     * Get all categories, optionally hierarchical.
     */
    public function getAll(array $filters = [], bool $hierarchical = false): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR description LIKE ?)';
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $whereSql = implode(' AND ', $where);

        $rows = $this->db->query(
            "SELECT * FROM {$this->table}
             WHERE {$whereSql}
             ORDER BY sort_order, name",
            $params
        )->rows;

        if (!$hierarchical) {
            return $rows;
        }

        return $this->buildTree($rows);
    }

    /**
     * Get single category by ID.
     */
    public function getById(int $id): ?array
    {
        return $this->db->query(
            "SELECT c.*, p.name as parent_name, p.slug as parent_slug
             FROM {$this->table} c
             LEFT JOIN {$this->table} p ON c.parent_id = p.id
             WHERE c.id = ?",
            [$id]
        )->row ?: null;
    }

    /**
     * Get category by slug with all content counts.
     */
    public function getBySlug(string $slug): ?array
    {
        $cat = $this->db->query(
            "SELECT * FROM {$this->table} WHERE slug = ?",
            [$slug]
        )->row ?: null;

        if ($cat) {
            $cat['counts'] = $this->getContentCounts($cat['id']);
        }
        return $cat;
    }

    /**
     * Get content from this category (lectures, series, books, articles).
     */
    public function getContent(int $categoryId, int $limit = 20): array
    {
        return [
            'lectures' => $this->db->query(
                "SELECT l.id, l.title, l.slug, l.type, l.views, sch.name as scholar_name
                 FROM #__dacwah_lectures l
                 LEFT JOIN #__dacwah_scholars sch ON l.scholar_id = sch.id
                 WHERE l.category_id = ? AND l.status = 'published'
                 ORDER BY l.created_at DESC LIMIT {$limit}",
                [$categoryId]
            )->rows,

            'series' => $this->db->query(
                "SELECT s.id, s.title, s.slug, s.total_lessons, sch.name as scholar_name
                 FROM #__dacwah_series s
                 LEFT JOIN #__dacwah_scholars sch ON s.scholar_id = sch.id
                 WHERE s.category_id = ? AND s.status = 'published'
                 ORDER BY s.created_at DESC LIMIT {$limit}",
                [$categoryId]
            )->rows,

            'books' => $this->db->query(
                "SELECT b.id, b.title, b.slug, b.downloads, sch.name as scholar_name
                 FROM #__dacwah_books b
                 LEFT JOIN #__dacwah_scholars sch ON b.scholar_id = sch.id
                 WHERE b.category_id = ? AND b.status = 'published'
                 ORDER BY b.created_at DESC LIMIT {$limit}",
                [$categoryId]
            )->rows,

            'articles' => $this->db->query(
                "SELECT a.id, a.title, a.slug, a.views, sch.name as scholar_name
                 FROM #__dacwah_articles a
                 LEFT JOIN #__dacwah_scholars sch ON a.scholar_id = sch.id
                 WHERE a.category_id = ? AND a.status = 'published'
                 ORDER BY a.created_at DESC LIMIT {$limit}",
                [$categoryId]
            )->rows,
        ];
    }

    /**
     * Save category.
     */
    public function save(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Category name is required.';
        }
        // Prevent circular parent reference
        if (!empty($data['id']) && !empty($data['parent_id']) && (int)$data['parent_id'] === (int)$data['id']) {
            $errors['parent_id'] = 'Category cannot be its own parent.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $fields = [
            'name'        => trim($data['name']),
            'slug'        => !empty($data['slug']) ? $data['slug'] : $this->createSlug($data['name']),
            'description' => $data['description'] ?? null,
            'parent_id'   => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            'icon'        => $data['icon'] ?? null,
            'sort_order'  => (int)($data['sort_order'] ?? 0),
        ];

        if (!empty($data['id'])) {
            $this->db->update($this->table, $fields, ['id' => (int)$data['id']]);
            $id = (int)$data['id'];
        } else {
            $id = $this->db->insert($this->table, $fields);
        }

        $this->updateContentCount($id);
        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete category — only if no content & no children.
     */
    public function delete(int $id): array
    {
        $hasContent = $this->db->query(
            "SELECT 
              (SELECT COUNT(*) FROM #__dacwah_lectures WHERE category_id = ?) +
              (SELECT COUNT(*) FROM #__dacwah_series WHERE category_id = ?) +
              (SELECT COUNT(*) FROM #__dacwah_books WHERE category_id = ?) +
              (SELECT COUNT(*) FROM #__dacwah_articles WHERE category_id = ?) AS total",
            [$id, $id, $id, $id]
        )->row['total'];

        $hasChildren = $this->db->query("SELECT COUNT(*) AS cnt FROM {$this->table} WHERE parent_id = ?", [$id])->row['cnt'];

        if ($hasContent > 0 || $hasChildren > 0) {
            return ['success' => false, 'errors' => ['Cannot delete: category has content or sub-categories.']];
        }

        $deleted = $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        return ['success' => (bool)$deleted];
    }

    /**
     * Recalculate total_content counter.
     */
    public function updateContentCount(int $categoryId): void
    {
        $total = $this->db->query(
            "SELECT 
              (SELECT COUNT(*) FROM #__dacwah_lectures WHERE category_id = ?) +
              (SELECT COUNT(*) FROM #__dacwah_series WHERE category_id = ?) +
              (SELECT COUNT(*) FROM #__dacwah_books WHERE category_id = ?) +
              (SELECT COUNT(*) FROM #__dacwah_articles WHERE category_id = ?) AS total",
            [$categoryId, $categoryId, $categoryId, $categoryId]
        )->row['total'];

        $this->db->update($this->table, ['total_content' => (int)$total], ['id' => $categoryId]);
    }

    // ─── Internal Helpers ───

    private function createSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-')) ?: 'category';
        $base = $slug;
        $counter = 1;
        while ($this->db->query("SELECT id FROM {$this->table} WHERE slug = ?", [$slug])->row) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function buildTree(array $rows, ?int $parentId = null): array
    {
        $tree = [];
        foreach ($rows as $row) {
            if ($row['parent_id'] === $parentId) {
                $row['children'] = $this->buildTree($rows, $row['id']);
                $tree[] = $row;
            }
        }
        return $tree;
    }

    private function getContentCounts(int $categoryId): array
    {
        return [
            'lectures' => $this->db->query("SELECT COUNT(*) AS cnt FROM #__dacwah_lectures WHERE category_id = ? AND status = 'published'", [$categoryId])->row['cnt'],
            'series'   => $this->db->query("SELECT COUNT(*) AS cnt FROM #__dacwah_series WHERE category_id = ? AND status = 'published'", [$categoryId])->row['cnt'],
            'books'    => $this->db->query("SELECT COUNT(*) AS cnt FROM #__dacwah_books WHERE category_id = ? AND status = 'published'", [$categoryId])->row['cnt'],
            'articles' => $this->db->query("SELECT COUNT(*) AS cnt FROM #__dacwah_articles WHERE category_id = ? AND status = 'published'", [$categoryId])->row['cnt'],
        ];
    }
}