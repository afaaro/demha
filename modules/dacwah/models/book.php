<?php

use System\Engine\Model;

class DacwahBookModel extends Model
{
    protected $table = '#__dacwah_books';

    /**
     * Get all published books with scholar & category info.
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['b.status = ?'];
        $params = ['published'];

        if (!empty($filters['scholar_id'])) {
            $where[] = 'b.scholar_id = ?';
            $params[] = (int)$filters['scholar_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'b.category_id = ?';
            $params[] = (int)$filters['category_id'];
        }
        if (!empty($filters['language'])) {
            $where[] = 'b.language = ?';
            $params[] = $filters['language'];
        }
        if (!empty($filters['is_featured'])) {
            $where[] = 'b.is_featured = ?';
            $params[] = 1;
        }
        if (!empty($filters['search'])) {
            $where[] = '(b.title LIKE ? OR b.description LIKE ? OR b.author_name LIKE ?)';
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;
        $sort = !empty($filters['sort']) ? $filters['sort'] : 'b.created_at';
        $order = !empty($filters['order']) ? strtoupper($filters['order']) : 'DESC';

        return $this->db->query(
            "SELECT b.*, sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug
             FROM {$this->table} b
             LEFT JOIN #__dacwah_scholars sch ON b.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON b.category_id = c.id
             WHERE {$whereSql}
             ORDER BY {$sort} {$order}, b.title
             LIMIT {$offset}, {$perPage}",
            $params
        )->rows;
    }

    /**
     * Get single book by ID.
     */
    public function getById(int $id): ?array
    {
        return $this->db->query(
            "SELECT b.*, sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug
             FROM {$this->table} b
             LEFT JOIN #__dacwah_scholars sch ON b.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON b.category_id = c.id
             WHERE b.id = ? AND b.status = 'published'",
            [$id]
        )->row ?: null;
    }

    /**
     * Get single book by slug.
     */
    public function getBySlug(string $slug): ?array
    {
        return $this->db->query(
            "SELECT b.*, sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug
             FROM {$this->table} b
             LEFT JOIN #__dacwah_scholars sch ON b.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON b.category_id = c.id
             WHERE b.slug = ? AND b.status = 'published'",
            [$slug]
        )->row ?: null;
    }

    /**
     * Increment download counter.
     */
    public function incrementDownloads(int $id): void
    {
        $this->db->query("UPDATE {$this->table} SET downloads = downloads + 1 WHERE id = ?", [$id]);
    }

    /**
     * Save book (create or update).
     */
    public function save(array $data): array
    {
        $errors = [];

        if (empty($data['title'])) $errors['title'] = 'Title is required.';
        if (empty($data['scholar_id'])) $errors['scholar_id'] = 'Author/Scholar is required.';
        if (empty($data['category_id'])) $errors['category_id'] = 'Category is required.';

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $fields = [
            'title'        => trim($data['title']),
            'slug'         => !empty($data['slug']) ? $data['slug'] : $this->createSlug($data['title']),
            'description'  => $data['description'] ?? null,
            'scholar_id'   => (int)$data['scholar_id'],
            'category_id'  => (int)$data['category_id'],
            'author_name'  => $data['author_name'] ?? null,
            'publisher'    => $data['publisher'] ?? null,
            'publish_year' => !empty($data['publish_year']) ? (int)$data['publish_year'] : null,
            'isbn'         => $data['isbn'] ?? null,
            'pages'        => !empty($data['pages']) ? (int)$data['pages'] : null,
            'cover_image'  => $data['cover_image'] ?? null,
            'file_url'     => $data['file_url'] ?? null,
            'file_size'    => !empty($data['file_size']) ? (int)$data['file_size'] : 0,
            'language'     => $data['language'] ?? 'en',
            'is_featured'  => (int)($data['is_featured'] ?? 0),
            'status'       => $data['status'] ?? 'published',
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

        // Update scholar's book counter
        $this->updateScholarTotal($fields['scholar_id'], $originalScholarId);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete book.
     */
    public function delete(int $id): array
    {
        $book = $this->getById($id);
        if (!$book) {
            return ['success' => false, 'errors' => ['Book not found.']];
        }

        $deleted = $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);

        if ($deleted) {
            $this->updateScholarTotal(0, $book['scholar_id']);
        }

        return ['success' => (bool)$deleted];
    }

    // ─── Internal Helpers ───

    private function createSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) ?: 'book';
        $base = $slug;
        $counter = 1;
        while ($this->db->query("SELECT id FROM {$this->table} WHERE slug = ?", [$slug])->row) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function updateScholarTotal(int $newScholarId, ?int $oldScholarId): void
    {
        foreach (array_filter([$oldScholarId, $newScholarId]) as $sid) {
            $count = $this->db->query("SELECT COUNT(*) AS cnt FROM {$this->table} WHERE scholar_id = ?", [$sid])->row['cnt'];
            $this->db->query("UPDATE #__dacwah_scholars SET total_books = ? WHERE id = ?", [(int)$count, $sid]);
        }
    }
}