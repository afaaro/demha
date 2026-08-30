<?php

use System\Engine\Model;

class NodeNodeBundleModel extends Model
{
    protected $table = '#__node_bundles';

    /**
     * Get all bundles.
     */
    public function getBundles(): array
    {
        return $this->db->query("SELECT * FROM {$this->table} ORDER BY name")->rows;
    }

    /**
     * Get all bundles with field count.
     */
    public function getBundlesWithFieldCount(): array
    {
        return $this->db->query("
            SELECT b.*, COUNT(f.bundle) AS field_count
            FROM {$this->table} b
            LEFT JOIN #__node_fields f ON b.machine_name = f.bundle
            GROUP BY b.id
            ORDER BY b.name
        ")->rows;
    }

    /**
     * Get a bundle by machine name.
     */
    public function getBundle(string $machineName): ?array
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE machine_name = ?",
            [$machineName]
        )->row;
    }

    /**
     * Get a bundle by ID.
     */
    public function getBundleById(int $id): ?array
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        )->row;
    }

    /**
     * Create or update a bundle.
     */
    public function saveBundle(array $data): array
    {
        if (empty($data['name']) || empty($data['machine_name'])) {
            return [
                'success' => false,
                'errors' => ['name' => 'Name and machine name are required.']
            ];
        }

        $fields = [
            'name'        => trim($data['name']),
            'machine_name'=> trim($data['machine_name']),
            'description' => !empty($data['description']) ? trim($data['description']) : null,
        ];

        // Prevent duplicate machine_name
        $excludeId = $data['id'] ?? 0;
        $duplicate = $this->db->query(
            "SELECT id FROM {$this->table} WHERE machine_name = ? AND id != ?",
            [$fields['machine_name'], $excludeId]
        )->row;

        if ($duplicate) {
            return [
                'success' => false,
                'errors' => ['machine_name' => 'This machine name is already taken.']
            ];
        }

        // Update or Create
        if (!empty($data['id'])) {
            $this->db->update($this->table, $fields, ['id' => $data['id']]);
            $id = (int) $data['id'];
        } else {
            $id = $this->db->insert($this->table, $fields);
        }

        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete a bundle by ID.
     */
    public function deleteBundle(int $id): bool
    {
        $result = $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        return $result && $result->affectedRows() > 0;
    }
}