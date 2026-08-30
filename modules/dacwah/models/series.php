<?php

use System\Engine\Model;

class DacwahSeriesModel extends Model
{
    protected $table = '#__dacwah_series';

    /**
     * Get all published series with scholar & category names.
     */
    public function getAll($filters = [], $page = 1, $limit = 20)
    {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT s.*, sc.name as scholar_name, c.name as category_name, 
                (SELECT COUNT(*) FROM #__dacwah_lectures WHERE series_id = s.id) as total_episodes
                FROM #__dacwah_series s
                LEFT JOIN #__dacwah_scholars sc ON s.scholar_id = sc.id
                LEFT JOIN #__dacwah_categories c ON s.category_id = c.id";
        
        $where = [];
        $params = [];
        
        if (!empty($filters['search'])) {
            $where[] = "s.title LIKE ?";
            $params[] = "%{$filters['search']}%";
        }
        if (!empty($filters['status'])) {
            $where[] = "s.status = ?";
            $params[] = $filters['status'];
        }
        if ($where) $sql .= " WHERE " . implode(" AND ", $where);
        
        $sql .= " ORDER BY s.title LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        
        return $this->db->query($sql, $params)->rows; // ✅ MUST be ->rows
    }

    /**
     * Get single series by ID with scholar & category info.
     */
    public function getById(int $id): ?array
    {
        return $this->db->query(
            "SELECT s.*, sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug
             FROM {$this->table} s
             LEFT JOIN #__dacwah_scholars sch ON s.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON s.category_id = c.id
             WHERE s.id = ? AND s.status = 'published'",
            [$id]
        )->row ?: null;
    }

    /**
     * Get single series by slug.
     */
    public function getBySlug(string $slug): ?array
    {
        return $this->db->query(
            "SELECT s.*, sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug
             FROM {$this->table} s
             LEFT JOIN #__dacwah_scholars sch ON s.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON s.category_id = c.id
             WHERE s.slug = ? AND s.status = 'published'",
            [$slug]
        )->row ?: null;
    }

    /**
     * Get all lessons in a series, ordered by lesson number.
     */
    public function getLessons(int $seriesId): array
    {
        return $this->db->query(
            "SELECT l.*, mf.quality, mf.format, mf.file_url
             FROM #__dacwah_lectures l
             LEFT JOIN #__dacwah_media_files mf ON l.id = mf.lecture_id
             WHERE l.series_id = ? AND l.status = 'published'
             ORDER BY l.lesson_number ASC, l.title ASC",
            [$seriesId]
        )->rows;
    }

    /**
     * Get lesson number in series (for prev/next navigation).
     */
    public function getLessonPosition(int $seriesId, int $lessonId): ?array
    {
        $lessons = $this->getLessons($seriesId);
        $nums = array_column($lessons, 'id');
        $pos = array_search($lessonId, $nums);

        if ($pos === false) {
            return null;
        }

        return [
            'current' => $lessons[$pos]['lesson_number'],
            'prev'    => $pos > 0 ? $lessons[$pos - 1] : null,
            'next'    => isset($lessons[$pos + 1]) ? $lessons[$pos + 1] : null,
            'total'   => count($lessons)
        ];
    }

    /**
     * Save series (create or update).
     */
    public function save(array $data): array
    {
        $errors = [];

        // Validation
        if (empty($data['title'])) {
            $errors['title'] = 'Title is required.';
        }
        if (empty($data['scholar_id'])) {
            $errors['scholar_id'] = 'Scholar is required.';
        }
        if (empty($data['category_id'])) {
            $errors['category_id'] = 'Category is required.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        // Build fields
        $fields = [
            'title'        => trim($data['title']),
            'slug'         => !empty($data['slug']) ? $data['slug'] : $this->createSlug($data['title']),
            'description'  => $data['description'] ?? null,
            'scholar_id'   => (int) $data['scholar_id'],
            'category_id'  => (int) $data['category_id'],
            'thumbnail'    => $data['thumbnail'] ?? null,
            'total_lessons'=> (int) ($data['total_lessons'] ?? 0),
            'completed'    => (int) ($data['completed'] ?? 0),
            'sort_order'   => (int) ($data['sort_order'] ?? 0),
            'is_featured'  => (int) ($data['is_featured'] ?? 0),
            'publish_date' => !empty($data['publish_date']) ? $data['publish_date'] : date('Y-m-d'),
            'status'       => $data['status'] ?? 'published',
        ];

        // Update or Create
        if (!empty($data['id'])) {
            $this->db->update($this->table, $fields, ['id' => (int) $data['id']]);
            $id = (int) $data['id'];
        } else {
            $id = $this->db->insert($this->table, $fields);
        }

        // Update scholar's total series counter
        $this->updateScholarTotal($fields['scholar_id']);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete series (only if no lectures exist).
     */
    public function delete(int $id): array
    {
        $count = $this->db->query(
            "SELECT COUNT(*) as cnt FROM #__dacwah_lectures WHERE series_id = ?",
            [$id]
        )->row['cnt'];

        if ($count > 0) {
            return ['success' => false, 'errors' => ['Cannot delete: series contains lectures.']];
        }

        $series = $this->getById($id);
        $deleted = $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);

        if ($deleted && $series) {
            $this->updateScholarTotal($series['scholar_id']);
        }

        return ['success' => (bool) $deleted];
    }

    /**
     * Recalculate & update total_lessons counter for a series.
     */
    public function updateLessonCount(int $seriesId): void
    {
        $count = $this->db->query(
            "SELECT COUNT(*) as cnt FROM #__dacwah_lectures WHERE series_id = ?",
            [$seriesId]
        )->row['cnt'];

        $this->db->update($this->table, ['total_lessons' => (int) $count], ['id' => $seriesId]);
    }

    /**
     * Auto-generate unique slug.
     */
    private function createSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
        if (empty($slug)) {
            $slug = 'series';
        }

        $base = $slug;
        $counter = 1;
        while ($this->db->query("SELECT id FROM {$this->table} WHERE slug = ?", [$slug])->row) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    /**
     * Update scholar's total_series counter.
     */
    private function updateScholarTotal(int $scholarId): void
    {
        $total = $this->db->query(
            "SELECT COUNT(*) as cnt FROM {$this->table} WHERE scholar_id = ?",
            [$scholarId]
        )->row['cnt'];

        $this->db->query(
            "UPDATE #__dacwah_scholars SET total_series = ? WHERE id = ?",
            [(int) $total, $scholarId]
        );
    }
}