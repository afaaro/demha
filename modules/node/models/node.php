<?php

use System\Engine\Model;

class NodeNodeModel extends Model
{
    protected $table = '#__node_entities';
    protected $valuesTable = '#__node_values';
    protected $fieldsTable = '#__node_fields';

    /**
     * Load single node by ID — with all custom field values merged in.
     */
    public function load(int $id): ?array
    {
        $node = $this->db->query("
            SELECT e.*, b.name as bundle_name, b.description as bundle_description
            FROM {$this->table} e
            LEFT JOIN #__node_bundles b ON e.bundle = b.machine_name
            WHERE e.id = ?
        ", [$id])->row;

        if (!$node) {
            return null;
        }

        // Merge custom field values
        $node = $this->mergeFieldValues($node);
        return $node;
    }

    /**
     * Load single node by slug.
     */
    public function loadBySlug(string $slug): ?array
    {
        $node = $this->db->query("
            SELECT e.*, b.name as bundle_name, b.description as bundle_description
            FROM {$this->table} e
            LEFT JOIN #__node_bundles b ON e.bundle = b.machine_name
            WHERE e.slug = ? AND e.status = 'published'
        ", [$slug])->row;

        if (!$node) {
            return null;
        }

        return $this->mergeFieldValues($node);
    }

    /**
     * Create or Update — smart save that handles core + custom fields.
     * Returns consistent array: ['success' => bool, 'id' => int, 'errors' => []]
     */
    public function save(string $bundle, array $data, ?int $id = null): array
    {
        $this->db->beginTransaction();
        try {
            // Separate core entity fields from custom field values
            $core = $this->extractCoreFields($data);
            $custom = $this->extractCustomFields($bundle, $data);

            if ($id) {
                // UPDATE
                $core['updated_at'] = date('Y-m-d H:i:s');
                $this->db->update($this->table, $core, ['id' => $id]);
            } else {
                // CREATE
                $core['bundle']      = $bundle;
                $core['created_at']  = date('Y-m-d H:i:s');
                $core['updated_at']  = date('Y-m-d H:i:s');
                $id = $this->db->insert($this->table, $core);
            }

            // Save all custom field values
            $this->saveFieldValues($id, $custom);

            $this->db->commit();
            return ['success' => true, 'id' => $id, 'errors' => []];
        } catch (Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'id' => $id, 'errors' => [$e->getMessage()]];
        }
    }

    /**
     * Delete node AND all its field values atomically.
     */
    public function delete(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            // Delete values first (foreign key safety)
            $this->db->query("DELETE FROM {$this->valuesTable} WHERE entity_id = ?", [$id]);
            // Delete entity
            $this->db->query("DELETE FROM {$this->table} WHERE id = ?", [$id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Query list with filters, search, pagination.
     * Returns array: data[], total, page, limit
     */
    public function query(array $filters = [], ?string $bundle = null, int $page = 1, int $limit = 20): array
    {
        $where = ['1=1'];
        $params = [];

        if ($bundle) {
            $where[] = "e.bundle = ?";
            $params[] = $bundle;
        }

        if (!empty($filters['status'])) {
            $where[] = "e.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(e.title LIKE ? OR e.slug LIKE ?)";
            $searchTerm = "%{$filters['search']}%";
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }

        $whereSql = implode(' AND ', $where);
        $offset = ($page - 1) * $limit;

        // Total
        $total = $this->db->query(
            "SELECT COUNT(DISTINCT e.id) as total FROM {$this->table} e WHERE $whereSql",
            $params
        )->row['total'];

        // Data
        $rows = $this->db->query("
            SELECT e.*, b.name as bundle_name
            FROM {$this->table} e
            LEFT JOIN #__node_bundles b ON e.bundle = b.machine_name
            WHERE $whereSql
            ORDER BY e.created_at DESC
            LIMIT $offset, $limit
        ", $params)->rows;

        // Merge field values for each row
        foreach ($rows as &$row) {
            $this->mergeFieldValues($row);
        }

        return [
            'data'  => $rows,
            'total' => (int) $total,
            'page'  => $page,
            'limit' => $limit,
        ];
    }

    /**
     * Get field definitions for a bundle.
     */
    public function getFields(string $bundle): array
    {
        return $this->db->query("
            SELECT * FROM {$this->fieldsTable}
            WHERE bundle = ?
            ORDER BY weight, label
        ", [$bundle])->rows;
    }

    // ─── INTERNAL HELPERS ─────────────────────────────────────

    /**
     * Merge custom field values into the node array.
     */
    protected function mergeFieldValues(array &$node): array
    {
        if (empty($node['id'])) {
            return $node;
        }

        $values = $this->db->query("
            SELECT field_name, value FROM {$this->valuesTable}
            WHERE entity_id = ?
        ", [$node['id']])->rows;

        foreach ($values as $v) {
            $node[$v['field_name']] = $this->decodeValue($v['value']);
        }

        return $node;
    }

    /**
     * Save custom field values (upsert: insert or update).
     */
    protected function saveFieldValues(int $entityId, array $fields): void
    {
        foreach ($fields as $name => $value) {
            $encoded = $this->encodeValue($value);

            // Upsert: update if exists, insert if not
            $exists = $this->db->query(
                "SELECT id FROM {$this->valuesTable} WHERE entity_id = ? AND field_name = ?",
                [$entityId, $name]
            )->row;

            if ($exists) {
                $this->db->update($this->valuesTable,
                    ['value' => $encoded],
                    ['id' => $exists['id']]
                );
            } else {
                $this->db->insert($this->valuesTable, [
                    'entity_id'  => $entityId,
                    'field_name' => $name,
                    'value'      => $encoded,
                ]);
            }
        }
    }

    /**
     * Extract core entity fields from data array.
     */
    protected function extractCoreFields(array $data): array
    {
        $coreFields = ['title', 'slug', 'status', 'created_at', 'updated_at'];
        $filtered = [];
        foreach ($coreFields as $field) {
            if (array_key_exists($field, $data)) {
                $filtered[$field] = $data[$field];
            }
        }
        return $filtered;
    }

    /**
     * Identify custom fields by comparing against bundle's field definitions.
     */
    protected function extractCustomFields(string $bundle, array $data): array
    {
        $definitions = $this->getFields($bundle);
        $custom = [];
        foreach ($definitions as $def) {
            $name = $def['field_name'];
            if (array_key_exists($name, $data)) {
                $custom[$name] = $data[$name];
            }
        }
        return $custom;
    }

    /**
     * Encode values for storage — handles arrays as JSON.
     */
    protected function encodeValue($value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }

    /**
     * Decode values from storage — auto-detect JSON.
     */
    protected function decodeValue(?string $value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }
        return $value;
    }
}