<?php

use System\Engine\Model;

class HifdhTeacherModel extends Model
{
    protected $table = '#__hifdh_teachers';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['search'])) {
            $where[] = 'name LIKE ? OR email LIKE ?';
            $params[] = "%{$filters['search']}%";
            $params[] = "%{$filters['search']}%";
        }
        if (!empty($filters['status'])) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        return $this->db->paginate($this->table, $where, $params, $page, $perPage, 'name ASC');
    }

    public function getById(int $id): ?array
    {
        return $this->db->query("SELECT * FROM {$this->table} WHERE id = ?", [$id])->row;
    }

    public function getPairs(): array
    {
        return $this->db->query("SELECT id, name FROM {$this->table} WHERE status = 'active' ORDER BY name")->pairs;
    }

    public function getWithStudentCount(): array
    {
        return $this->db->query("
            SELECT t.*, COUNT(s.id) as student_count
            FROM {$this->table} t
            LEFT JOIN #__hifdh_students s ON t.id = s.teacher_id
            GROUP BY t.id
            ORDER BY t.name
        ")->rows;
    }

    public function save(array $data, ?int $id = null): array
    {
        if ($id) {
            $this->db->query(
                "UPDATE {$this->table} SET name=?, email=?, phone=?, bio=?, status=? WHERE id=?",
                [
                    $data['name'],
                    $data['email'] ?? null,
                    $data['phone'] ?? null,
                    $data['bio'] ?? null,
                    $data['status'] ?? 'active',
                    $id
                ]
            );
        } else {
            $this->db->query(
                "INSERT INTO {$this->table} (name, email, phone, bio, status) VALUES (?, ?, ?, ?, ?)",
                [
                    $data['name'],
                    $data['email'] ?? null,
                    $data['phone'] ?? null,
                    $data['bio'] ?? null,
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