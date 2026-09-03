<?php

use System\Engine\Model;

class HifdhStudentModel extends Model
{
    protected $table = '#__hifdh_students';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = 's.name LIKE ?';
            $params[] = "%{$filters['search']}%";
        }
        if (!empty($filters['status'])) {
            $where[] = 's.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['teacher_id'])) {
            $where[] = 's.teacher_id = ?';
            $params[] = $filters['teacher_id'];
        }
        if (!empty($filters['level'])) {
            $where[] = 's.level = ?';
            $params[] = $filters['level'];
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        return $this->db->query("
            SELECT s.*, t.name as teacher_name,
                   (SELECT SUM(to_ayah - from_ayah + 1) 
                    FROM #__hifdh_progress 
                    WHERE student_id = s.id AND type = 'new') as total_ayahs
            FROM {$this->table} s
            LEFT JOIN #__hifdh_teachers t ON s.teacher_id = t.id
            WHERE {$whereSql}
            ORDER BY s.name ASC
            LIMIT {$offset}, {$perPage}
        ", $params)->rows;
    }

    public function getById(int $id): ?array
    {
        return $this->db->query("
            SELECT s.*, t.name as teacher_name, t.email as teacher_email
            FROM {$this->table} s
            LEFT JOIN #__hifdh_teachers t ON s.teacher_id = t.id
            WHERE s.id = ?
        ", [$id])->row;
    }

    public function getPairs(): array
    {
        return $this->db->query("SELECT id, name FROM {$this->table} WHERE status = 'active' ORDER BY name")->pairs;
    }

    public function getByTeacher(int $teacherId): array
    {
        return $this->db->query("
            SELECT * FROM {$this->table} 
            WHERE teacher_id = ? AND status = 'active'
            ORDER BY name
        ", [$teacherId])->rows;
    }

    public function save(array $data, ?int $id = null): array
    {
        if ($id) {
            $this->db->query(
                "UPDATE {$this->table} SET
                    teacher_id = ?, name = ?, age = ?, level = ?,
                    target_juz = ?, start_date = ?, notes = ?, status = ?
                 WHERE id = ?",
                [
                    $data['teacher_id'] ?? null,
                    $data['name'],
                    $data['age'] ?? null,
                    $data['level'] ?? 'beginner',
                    $data['target_juz'] ?? 30,
                    $data['start_date'] ?? null,
                    $data['notes'] ?? null,
                    $data['status'] ?? 'active',
                    $id
                ]
            );
        } else {
            $exist = $this->db->query("SELECT id FROM {$this->table} WHERE name = ? AND teacher_id = ?", [$data['name'], $data['teacher_id'] ?? null])->row;
            if ($exist) {
                throw new Exception('A student with this name already exists for the selected teacher.');
            }
            
            $this->db->query(
                "INSERT INTO {$this->table}
                    (teacher_id, name, age, level, target_juz, start_date, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $data['teacher_id'] ?? null,
                    $data['name'],
                    $data['age'] ?? null,
                    $data['level'] ?? 'beginner',
                    $data['target_juz'] ?? 30,
                    $data['start_date'] ?? null,
                    $data['notes'] ?? null,
                    $data['status'] ?? 'active'
                ]
            );
            $id = (int)$this->db->insert_id();
        }
        return $this->getById($id);
    }

    public function delete(int $id): bool
    {
        $result = $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        return $result->affectedRows() > 0;
    }
}