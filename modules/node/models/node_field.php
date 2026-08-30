<?php

use System\Engine\Model;

class NodeNodeFieldModel extends Model
{
    protected $table = '#__node_fields';

    /**
     * Get all fields for a bundle, ordered by weight.
     */
    public function getFieldsForBundle(string $bundleName): array
    {
        return $this->db->query("
            SELECT * FROM {$this->table}
            WHERE bundle = ?
            ORDER BY weight, label
        ", [$bundleName])->rows;
    }

    /**
     * Get single field by ID.
     */
    public function getFieldById(int $id): ?array
    {
        return $this->db->query("
            SELECT * FROM {$this->table} WHERE id = ?
        ", [$id])->row;
    }

    /**
     * Check if a machine field name already exists in a bundle.
     */
    public function fieldExists(string $bundleName, string $fieldName, ?int $excludeId = null): bool
    {
        $sql = "SELECT id FROM {$this->table} WHERE bundle = ? AND field_name = ?";
        $params = [$bundleName, $fieldName];

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        return (bool) $this->db->query($sql, $params)->num_rows;
    }

    /**
     * Create a new field.
     */
    public function saveField(array $data): int
    {
        if (empty($data['bundle']) || empty($data['field_name']) || empty($data['field_type']) || empty($data['label'])) {
            throw new InvalidArgumentException('Missing required field properties.');
        }

        // Sanitize
        $fields = [
            'bundle'      => trim($data['bundle']),
            'field_name'  => trim($data['field_name']),
            'field_type'  => trim($data['field_type']),
            'label'       => trim($data['label']),
            'required'    => isset($data['required']) ? (int) $data['required'] : 0,
            'weight'      => isset($data['weight']) ? (int) $data['weight'] : 0,
            'settings'    => !empty($data['settings']) ? $data['settings'] : null,
        ];

        // Prevent duplicates
        if ($this->fieldExists($fields['bundle'], $fields['field_name'])) {
            throw new RuntimeException('Field name already exists in this content type.');
        }

        return $this->db->insert($this->table, $fields);
    }

    /**
     * Update an existing field.
     */
    public function updateField(int $id, array $data): bool
    {
        $fields = [];

        if (isset($data['label']))    $fields['label']    = trim($data['label']);
        if (isset($data['required'])) $fields['required'] = (int) $data['required'];
        if (isset($data['weight']))   $fields['weight']   = (int) $data['weight'];
        if (isset($data['settings'])) $fields['settings'] = $data['settings'] ?: null;

        if (empty($fields)) {
            return false;
        }

        return $this->db->update($this->table, $fields, ['id' => $id]);
    }

    /**
     * Delete a field AND all its stored values.
     */
    public function deleteField(int $id, string $fieldName): bool
    {
        $this->db->beginTransaction();
        try {
            // Delete stored values first
            $this->db->query("DELETE FROM #__node_values WHERE field_name = ?", [$fieldName]);

            // Delete field definition
            $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }
}