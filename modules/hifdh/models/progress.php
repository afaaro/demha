<?php

use System\Engine\Model;

class HifdhProgressModel extends Model
{
    protected $table = '#__hifdh_progress';

    public function getAllForStudent(int $studentId, ?string $filterType = null, int $page = 1, int $perPage = 20): array
    {
        $where = ['p.student_id = ?'];
        $params = [$studentId];
        if ($filterType) {
            $types = array_map([$this->db, 'escape'], explode(',', $filterType));
            $where[] = "p.type IN ('" . implode("','", $types) . "')";
        }
        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        return $this->db->query("
            SELECT p.*, s.name as surah_name, s.number as surah_number, t.name as teacher_name
            FROM {$this->table} p
            LEFT JOIN #__hifdh_surahs s ON p.surah_id = s.id
            LEFT JOIN #__hifdh_teachers t ON p.teacher_id = t.id
            WHERE {$whereSql}
            ORDER BY p.review_date DESC, p.id DESC
            LIMIT {$offset}, {$perPage}
        ", $params)->rows;
    }

    public function getById(int $id): ?array
    {
        return $this->db->query("
            SELECT p.*, s.name as surah_name, s.number as surah_number, j.number as juz_number
            FROM {$this->table} p
            JOIN #__hifdh_surahs s ON p.surah_id = s.id
            LEFT JOIN #__hifdh_juz j ON p.juz_id = j.id
            WHERE p.id = ?
        ", [$id])->row;
    }

    public function getTotalAyahsMemorized(int $studentId): int
    {
        $result = $this->db->query("
            SELECT SUM(to_ayah - from_ayah + 1) as total
            FROM {$this->table}
            WHERE student_id = ? 
              AND type = 'new'
        ", [$studentId])->row;

        return (int) ($result['total'] ?? 0);
    }

    public function getJuzProgress(int $studentId): array
    {
        return $this->db->query("
            SELECT 
                j.number as juz_number,
                j.name as juz_name,
                SUM(p.to_ayah - p.from_ayah + 1) as ayahs_memorized,
                jsa.total_ayahs_in_juz,
                ROUND((SUM(p.to_ayah - p.from_ayah + 1)) / jsa.total_ayahs_in_juz * 100, 1) as pct
            FROM {$this->table} p
            INNER JOIN #__hifdh_juz_surahs js 
                ON p.surah_id = js.surah_id 
               AND p.from_ayah >= js.from_ayah 
               AND p.to_ayah <= js.to_ayah
            INNER JOIN #__hifdh_juz j ON js.juz_id = j.id
            INNER JOIN (
                SELECT js.juz_id, SUM(js.to_ayah - js.from_ayah + 1) as total_ayahs_in_juz
                FROM #__hifdh_juz_surahs js
                GROUP BY js.juz_id
            ) jsa ON js.juz_id = jsa.juz_id
            WHERE p.student_id = ? 
              AND p.type = 'new'
            GROUP BY js.juz_id
            ORDER BY j.number
        ", [$studentId])->rows;
    }

    /**
     * Auto-resolve juz_id from Surah + Ayah range
     */
    public function resolveJuzId(int $surahId, int $fromAyah, int $toAyah): ?int
    {
        $row = $this->db->query("
            SELECT juz_id 
            FROM #__hifdh_juz_surahs
            WHERE surah_id = ?
              AND ? >= from_ayah 
              AND ? <= to_ayah
            LIMIT 1
        ", [$surahId, $fromAyah, $toAyah])->row;

        return $row ? (int) $row['juz_id'] : null;
    }

    /**
     * Save (create or update)
     */
    public function save(array $data, ?int $id = null): array
    {
        // Auto-resolve Juz if not provided
        $juzId = isset($data['juz_id']) ? $data['juz_id'] : $this->resolveJuzId(
            (int)$data['surah_id'],
            (int)$data['from_ayah'],
            (int)$data['to_ayah']
        );

        if ($id) {
            // Update existing entry
            $this->db->query(
                "UPDATE {$this->table} SET
                    surah_id = ?, juz_id = ?, from_ayah = ?, to_ayah = ?,
                    type = ?, rating = ?, notes = ?, teacher_id = ?, review_date = ?
                 WHERE id = ?",
                [
                    $data['surah_id'],
                    $juzId,
                    $data['from_ayah'],
                    $data['to_ayah'],
                    $data['type'],
                    $data['rating'] ?? null,
                    $data['notes'] ?? null,
                    $data['teacher_id'],
                    $data['review_date'],
                    $id
                ]
            );
        } else {
            // Create new entry
            $this->db->query(
                "INSERT INTO {$this->table}
                    (student_id, surah_id, juz_id, from_ayah, to_ayah, type, rating, notes, teacher_id, review_date)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['student_id'],
                    $data['surah_id'],
                    $juzId,
                    $data['from_ayah'],
                    $data['to_ayah'],
                    $data['type'],
                    $data['rating'] ?? null,
                    $data['notes'] ?? null,
                    $data['teacher_id'],
                    $data['review_date']
                ]
            );
            $id = (int)$this->db->insertId();
        }

        return $this->getById($id);
    }

    /**
     * Delete by ID
     */
    public function delete(int $id): bool
    {
        return $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id])->affectedRows() > 0;
    }
}