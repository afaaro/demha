<?php

use System\Engine\Model;

class DacwahScholarModel extends Model
{
    protected $table = '#__dacwah_scholars';

    /**
     * Get all scholars with content counts.
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $where = ['status != ?'];
        $params = ['inactive'];

        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['search'])) {
            $where[] = '(name LIKE ? OR arabic_name LIKE ? OR country LIKE ?)';
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        $sort = !empty($filters['sort']) ? $filters['sort'] : 'sort_order, name';

        return $this->db->query(
            "SELECT * FROM {$this->table}
             WHERE {$whereSql}
             ORDER BY {$sort}
             LIMIT {$offset}, {$perPage}",
            $params
        )->rows;
    }

    /**
     * Get single scholar by ID with counts & latest content.
     */
    public function getById(int $id): ?array
    {
        $scholar = $this->db->query(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        )->row ?: null;

        if ($scholar) {
            $scholar['latest_lectures'] = $this->getLatestContent($id, 'lectures', 5);
            $scholar['latest_series']   = $this->getLatestContent($id, 'series', 5);
            $scholar['latest_books']    = $this->getLatestContent($id, 'books', 5);
            $scholar['latest_articles'] = $this->getLatestContent($id, 'articles', 5);
        }
        return $scholar;
    }

    /**
     * Get scholar by slug.
     */
    public function getBySlug(string $slug): ?array
    {
        $scholar = $this->db->query(
            "SELECT * FROM {$this->table} WHERE slug = ?",
            [$slug]
        )->row ?: null;

        if ($scholar) {
            $scholar['latest_lectures'] = $this->getLatestContent($scholar['id'], 'lectures', 5);
            $scholar['latest_series']   = $this->getLatestContent($scholar['id'], 'series', 5);
        }
        return $scholar;
    }

    /**
     * Save scholar (create or update).
     */
    public function save(array $data): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Scholar name is required.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $fields = [
            'name'         => trim($data['name']),
            'slug'         => !empty($data['slug']) ? $data['slug'] : $this->createSlug($data['name']),
            'arabic_name'  => $data['arabic_name'] ?? null,
            'bio'          => $data['bio'] ?? null,
            'photo'        => $data['photo'] ?? null,
            'country'      => $data['country'] ?? null,
            'website'      => $data['website'] ?? null,
            'email'        => $data['email'] ?? null,
            'facebook'     => $data['facebook'] ?? null,
            'twitter'      => $data['twitter'] ?? null,
            'youtube'      => $data['youtube'] ?? null,
            'status'       => $data['status'] ?? 'active',
            'sort_order'   => (int)($data['sort_order'] ?? 0),
        ];

        if (!empty($data['id'])) {
            $this->db->update($this->table, $fields, ['id' => (int)$data['id']]);
            $id = (int)$data['id'];
        } else {
            $id = $this->db->insert($this->table, $fields);
        }

        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete scholar — only safe if no content attached.
     */
    public function delete(int $id): array
    {
        $hasContent = $this->db->query(
            "SELECT 
              (SELECT COUNT(*) FROM #__dacwah_lectures WHERE scholar_id = ?) +
              (SELECT COUNT(*) FROM #__dacwah_series WHERE scholar_id = ?) +
              (SELECT COUNT(*) FROM #__dacwah_books WHERE scholar_id = ?) +
              (SELECT COUNT(*) FROM #__dacwah_articles WHERE scholar_id = ?) AS total",
            [$id, $id, $id, $id]
        )->row['total'];

        if ($hasContent > 0) {
            return ['success' => false, 'errors' => ['Cannot delete: scholar has content attached.']];
        }

        $deleted = $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        return ['success' => (bool)$deleted];
    }

    /**
     * Recalculate & update all counters.
     */
    public function updateCounters(int $scholarId): void
    {
        $lectures = $this->db->query("SELECT COUNT(*) AS cnt FROM #__dacwah_lectures WHERE scholar_id = ?", [$scholarId])->row['cnt'];
        $series   = $this->db->query("SELECT COUNT(*) AS cnt FROM #__dacwah_series WHERE scholar_id = ?", [$scholarId])->row['cnt'];
        $books    = $this->db->query("SELECT COUNT(*) AS cnt FROM #__dacwah_books WHERE scholar_id = ?", [$scholarId])->row['cnt'];
        $articles = $this->db->query("SELECT COUNT(*) AS cnt FROM #__dacwah_articles WHERE scholar_id = ?", [$scholarId])->row['cnt'];

        $this->db->update($this->table, [
            'total_lectures' => (int)$lectures,
            'total_series'   => (int)$series,
            'total_books'    => (int)$books,
            'total_articles' => (int)$articles,
        ], ['id' => $scholarId]);
    }

    // ─── Internal Helpers ───

    private function createSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-\x{0600}-\x{06FF}]+/u', '-', $name), '-')) ?: 'scholar';
        $base = $slug;
        $counter = 1;
        while ($this->db->query("SELECT id FROM {$this->table} WHERE slug = ?", [$slug])->row) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function getLatestContent(int $scholarId, string $table, int $limit = 5): array
    {
        $table = match($table) {
            'lectures' => '#__dacwah_lectures',
            'series'   => '#__dacwah_series',
            'books'    => '#__dacwah_books',
            'articles' => '#__dacwah_articles',
        };

        return $this->db->query(
            "SELECT id, title, slug, created_at
             FROM {$table}
             WHERE scholar_id = ? AND status = 'published'
             ORDER BY created_at DESC
             LIMIT {$limit}",
            [$scholarId]
        )->rows;
    }
}