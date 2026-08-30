<?php

use System\Engine\Model;

class DacwahLectureModel extends Model
{
    protected $table = '#__dacwah_lectures';

    /**
     * Get all published lectures with scholar, category & series info.
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = [];
        $params = [];

        // Admin: show ALL statuses by default; frontend defaults to published only
        $showAll = !isset($filters['status']) || $filters['status'] === '';
        if (!$showAll) {
            $where[] = 'l.status = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['scholar_id'])) {
            $where[] = 'l.scholar_id = ?';
            $params[] = (int)$filters['scholar_id'];
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'l.category_id = ?';
            $params[] = (int)$filters['category_id'];
        }
        if (!empty($filters['series_id'])) {
            $where[] = 'l.series_id = ?';
            $params[] = (int)$filters['series_id'];
        }
        if (isset($filters['type']) && $filters['type'] !== '') {
            $where[] = 'l.type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['is_featured'])) {
            $where[] = 'l.is_featured = ?';
            $params[] = 1;
        }
        if (!empty($filters['search'])) {
            $where[] = '(l.title LIKE ? OR l.description LIKE ?)';
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }

        $whereSql = $where ? implode(' AND ', $where) : '1=1';
        $offset   = ($page - 1) * $perPage;
        $sort     = !empty($filters['sort']) ? $filters['sort'] : 'l.created_at';
        $order    = !empty($filters['order']) ? strtoupper($filters['order']) : 'DESC';

        return $this->db->query(
            "SELECT l.*, 
                    sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug,
                    s.title as series_title, s.slug as series_slug
            FROM {$this->table} l
            LEFT JOIN #__dacwah_scholars sch ON l.scholar_id = sch.id
            LEFT JOIN #__dacwah_categories c ON l.category_id = c.id
            LEFT JOIN #__dacwah_series s ON l.series_id = s.id
            WHERE {$whereSql}
            ORDER BY {$sort} {$order}, l.title
            LIMIT {$offset}, {$perPage}",
            $params
        )->rows;
    }

    /**
     * Get single lecture by ID with full details & media files.
     */
    public function getById(int $id, bool $withMedia = true, bool $publishedOnly = false): ?array
    {
        $where = ['l.id = ?'];
        $params = [$id];

        // Frontend: restrict to published; Admin: show any status
        if ($publishedOnly) {
            $where[] = "l.status = 'published'";
        }

        $whereSql = implode(' AND ', $where);

        $lecture = $this->db->query(
            "SELECT l.*, 
                    sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug,
                    s.title as series_title, s.slug as series_slug, s.total_lessons
            FROM {$this->table} l
            LEFT JOIN #__dacwah_scholars sch ON l.scholar_id = sch.id
            LEFT JOIN #__dacwah_categories c ON l.category_id = c.id
            LEFT JOIN #__dacwah_series s ON l.series_id = s.id
            WHERE {$whereSql}",
            $params
        )->row ?: null;

        if ($lecture && $withMedia) {
            $lecture['media'] = $this->getMediaFiles($id);
        }

        return $lecture;
    }

    /**
     * Get single lecture by slug.
     */
    public function getBySlug(string $slug, bool $withMedia = true): ?array
    {
        $lecture = $this->db->query(
            "SELECT l.*, sch.name as scholar_name, sch.slug as scholar_slug,
                    c.name as category_name, c.slug as category_slug,
                    s.title as series_title, s.slug as series_slug, s.total_lessons
             FROM {$this->table} l
             LEFT JOIN #__dacwah_scholars sch ON l.scholar_id = sch.id
             LEFT JOIN #__dacwah_categories c ON l.category_id = c.id
             LEFT JOIN #__dacwah_series s ON l.series_id = s.id
             WHERE l.slug = ? AND l.status = 'published'",
            [$slug]
        )->row ?: null;

        if ($lecture && $withMedia) {
            $lecture['media'] = $this->getMediaFiles((int)$lecture['id']);
        }

        return $lecture;
    }

    /**
     * Get media files (all formats/qualities) for a lecture.
     */
    public function getMediaFiles(int $lectureId): array
    {
        return $this->db->query(
            "SELECT * FROM #__dacwah_media_files WHERE lecture_id = ? ORDER BY format, quality",
            [$lectureId]
        )->rows;
    }

    /**
     * Get prev/next lecture within the same series.
     */
    public function getNavigation(int $lectureId, ?int $seriesId = null): array
    {
        $lecture = $this->getById($lectureId, false);
        if (!$lecture) {
            return ['prev' => null, 'next' => null];
        }

        $seriesId = $seriesId ?? $lecture['series_id'];

        if ($seriesId) {
            // Within series: navigate by lesson_number
            $prev = $this->db->query(
                "SELECT id, title, slug, lesson_number FROM {$this->table}
                 WHERE series_id = ? AND lesson_number < ? AND status = 'published'
                 ORDER BY lesson_number DESC LIMIT 1",
                [$seriesId, $lecture['lesson_number']]
            )->row;

            $next = $this->db->query(
                "SELECT id, title, slug, lesson_number FROM {$this->table}
                 WHERE series_id = ? AND lesson_number > ? AND status = 'published'
                 ORDER BY lesson_number ASC LIMIT 1",
                [$seriesId, $lecture['lesson_number']]
            )->row;
        } else {
            // Standalone: navigate by date
            $prev = $this->db->query(
                "SELECT id, title, slug FROM {$this->table}
                 WHERE scholar_id = ? AND publish_date <= ? AND id < ? AND status = 'published'
                 ORDER BY id DESC LIMIT 1",
                [$lecture['scholar_id'], $lecture['publish_date'] ?? date('Y-m-d'), $lectureId]
            )->row;

            $next = $this->db->query(
                "SELECT id, title, slug FROM {$this->table}
                 WHERE scholar_id = ? AND publish_date >= ? AND id > ? AND status = 'published'
                 ORDER BY id ASC LIMIT 1",
                [$lecture['scholar_id'], $lecture['publish_date'] ?? date('Y-m-d'), $lectureId]
            )->row;
        }

        return ['prev' => $prev, 'next' => $next];
    }

    /**
     * Increment view counter.
     */
    public function incrementViews(int $id): void
    {
        $this->db->query("UPDATE {$this->table} SET views = views + 1 WHERE id = ?", [$id]);
    }

    /**
     * Increment download counter.
     */
    public function incrementDownloads(int $id): void
    {
        $this->db->query("UPDATE {$this->table} SET downloads = downloads + 1 WHERE id = ?", [$id]);
    }

    /**
     * Save lecture (create or update) + auto-update series & scholar counters.
     */
    public function save(array $data): array
    {
        $errors = [];

        // Validation
        if (empty($data['title'])) $errors['title'] = 'Title is required.';
        if (empty($data['scholar_id'])) $errors['scholar_id'] = 'Scholar is required.';
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
            'series_id'    => !empty($data['series_id']) ? (int)$data['series_id'] : null,
            'lesson_number'=> !empty($data['lesson_number']) ? (int)$data['lesson_number'] : null,
            'type'         => $data['type'] ?? 'audio',
            'thumbnail'    => $data['thumbnail'] ?? null,
            'duration'     => !empty($data['duration']) ? (int)$data['duration'] : 0,
            'publish_date' => !empty($data['publish_date']) ? $data['publish_date'] : date('Y-m-d'),
            'is_featured'  => (int)($data['is_featured'] ?? 0),
            'status'       => $data['status'] ?? 'published',
        ];

        $originalSeriesId = null;
        $originalScholarId = null;

        if (!empty($data['id'])) {
            $existing = $this->getById((int)$data['id'], false);
            if ($existing) {
                $originalSeriesId = $existing['series_id'];
                $originalScholarId = $existing['scholar_id'];
            }
            $this->db->update($this->table, $fields, ['id' => (int)$data['id']]);
            $id = (int)$data['id'];
        } else {
            $id = $this->db->insert($this->table, $fields);
        }

        // Attach media files
        if (!empty($data['media']) && is_array($data['media'])) {
            $this->saveMediaFiles($id, $data['media']);
        }

        // Update counters if changed
        $this->updateCounters($fields['scholar_id'], $fields['series_id'], $originalScholarId, $originalSeriesId);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete lecture + update counters.
     */
    public function delete(int $id): array
    {
        $lecture = $this->getById($id, false);
        if (!$lecture) {
            return ['success' => false, 'errors' => ['Lecture not found.']];
        }

        // Delete media files first
        $this->db->query("DELETE FROM #__dacwah_media_files WHERE lecture_id = ?", [$id]);

        $deleted = $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);

        if ($deleted) {
            $this->updateCounters(0, null, $lecture['scholar_id'], $lecture['series_id']);
        }

        return ['success' => (bool)$deleted];
    }

    /**
     * Save/replace media files for a lecture.
     */
    public function saveMediaFiles(int $lectureId, array $mediaList): void
    {
        // Remove existing before inserting fresh
        $this->db->query("DELETE FROM #__dacwah_media_files WHERE lecture_id = ?", [$lectureId]);

        foreach ($mediaList as $media) {
            if (!empty($media['file_url'])) {
                $this->db->insert('#__dacwah_media_files', [
                    'lecture_id' => $lectureId,
                    'quality'    => $media['quality'] ?? 'default',
                    'format'     => $media['format'] ?? 'mp3',
                    'file_url'   => $media['file_url'],
                    'file_size'  => (int)($media['file_size'] ?? 0),
                    'bandwidth'  => (int)($media['bandwidth'] ?? 0),
                ]);
            }
        }
    }

    // ─── Internal Helpers ───

    private function createSlug(string $title): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-')) ?: 'lecture';
        $base = $slug;
        $counter = 1;
        while ($this->db->query("SELECT id FROM {$this->table} WHERE slug = ?", [$slug])->row) {
            $slug = $base . '-' . $counter++;
        }
        return $slug;
    }

    private function updateCounters(int $newScholarId, ?int $newSeriesId, ?int $oldScholarId, ?int $oldSeriesId): void
    {
        // Scholar counters
        foreach (array_filter([$oldScholarId, $newScholarId]) as $sid) {
            $count = $this->db->query("SELECT COUNT(*) as cnt FROM {$this->table} WHERE scholar_id = ?", [$sid])->row['cnt'];
            $this->db->query("UPDATE #__dacwah_scholars SET total_lectures = ? WHERE id = ?", [(int)$count, $sid]);
        }

        // Series counters
        foreach (array_filter([$oldSeriesId, $newSeriesId]) as $sid) {
            $count = $this->db->query("SELECT COUNT(*) as cnt FROM {$this->table} WHERE series_id = ?", [$sid])->row['cnt'];
            $this->db->query("UPDATE #__dacwah_series SET total_lessons = ? WHERE id = ?", [(int)$count, $sid]);
        }
    }
}