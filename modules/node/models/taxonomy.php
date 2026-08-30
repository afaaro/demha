<?php

use System\Engine\Model;

class NodeTaxonomyVocabularyModel extends Model
{
    protected $table = '#__node_taxonomy_vocabularies';

    /**
     * Get all vocabularies.
     */
    public function getVocabularies(): array
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} ORDER BY name"
        )->rows;
    }

    /**
     * Get single vocabulary by ID.
     */
    public function getVocabulary(int $id): ?array
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE id = ?",
            [$id]
        )->row ?: null;
    }

    /**
     * Get vocabulary by machine name.
     */
    public function getByMachineName(string $machineName): ?array
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE machine_name = ?",
            [$machineName]
        )->row ?: null;
    }

    /**
     * Save vocabulary.
     */
    public function saveVocabulary(array $data): array
    {
        $errors = [];
        if (empty($data['name'])) {
            $errors['name'] = 'Name is required.';
        }
        if (empty($data['machine_name'])) {
            $errors['machine_name'] = 'Machine name is required.';
        } elseif (!preg_match('/^[a-z0-9_]+$/', $data['machine_name'])) {
            $errors['machine_name'] = 'Only lowercase letters, numbers, and underscores.';
        }

        // Check duplicate machine name
        $exists = $this->db->query(
            "SELECT id FROM {$this->table} WHERE machine_name = ? AND id != ?",
            [$data['machine_name'], (int) ($data['id'] ?? 0)]
        )->row;
        if ($exists) {
            $errors['machine_name'] = 'Machine name already exists.';
        }

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $fields = [
            'name'         => trim($data['name']),
            'machine_name' => strtolower(trim($data['machine_name'])),
            'description'  => $data['description'] ?? null,
        ];

        if (!empty($data['id'])) {
            $this->db->update($this->table, $fields, ['id' => $data['id']]);
            return ['success' => true, 'id' => (int) $data['id']];
        }

        $id = $this->db->insert($this->table, $fields);
        return ['success' => true, 'id' => $id];
    }

    /**
     * Delete vocabulary — only if empty.
     */
    public function deleteVocabulary(int $id): array
    {
        $count = $this->db->query(
            "SELECT COUNT(*) as cnt FROM {$this->table} t
             JOIN #__node_taxonomy_terms term ON term.vocabulary_id = t.id
             WHERE t.id = ?",
            [$id]
        )->row['cnt'];

        if ($count > 0) {
            return ['success' => false, 'errors' => ['Cannot delete: vocabulary has terms.']];
        }

        $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);
        return ['success' => true];
    }
}