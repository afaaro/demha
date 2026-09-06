<?php

use System\Engine\Model;

class ToolsSettingModel extends Model
{
    protected $table = '#__settings';

    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['search'])) {
            $where[] = '(`key` LIKE ? OR `value` LIKE ?)';
            $s = "%{$filters['search']}%";
            $params[] = $s;
            $params[] = $s;
        }
        if (!empty($filters['group'])) {
            $where[] = '`group` = ?';
            $params[] = $filters['group'];
        }

        return $this->db->paginate($this->table, $where, $params, $page, $perPage, '`group`, `key`');
    }

    public function getGroups(): array
    {
        return $this->db->query("SELECT DISTINCT `group` FROM {$this->table} WHERE `group` != '' ORDER BY `group`")->column('group');
    }

    public function getById(int $id): ?array
    {
        return $this->db->query("SELECT * FROM {$this->table} WHERE id = ?", [$id])->row;
    }

    public function save(array $data, ?int $id = null): bool
    {
        $fields = ['key' => $data['key'], 'value' => $data['value'], 'group' => $data['group'] ?? null, 'type' => $data['type'] ?? 'text', 'description' => $data['description'] ?? ''];

        if ($id) {
            return $this->db->query("UPDATE {$this->table} SET `key`=?, `value`=?, `group`=?, `type`=?, `description`=? WHERE id=?",
                [$fields['key'], $fields['value'], $fields['group'], $fields['type'], $fields['description'], $id])->affectedRows() > 0;
        }
        return $this->db->query("INSERT INTO {$this->table} (`key`,`value`,`group`,`type`,`description`) VALUES (?,?,?,?,?)",
            [$fields['key'], $fields['value'], $fields['group'], $fields['type'], $fields['description']])->num_rows > 0;
    }

    public function delete(int $id): bool
    {
        return $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id])->affectedRows() > 0;
    }
}