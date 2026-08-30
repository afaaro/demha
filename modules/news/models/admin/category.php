<?php
use System\Engine\Model;

class NewsAdminCategoryModel extends Model
{
    public function getCategory(int $id): ?array
    {
        $query = $this->db->query("SELECT * FROM #__news_categories WHERE id = ?", [$id]);
        return $query->num_rows ? $query->row : null;
    }

    public function getCategories(int $limit = 20, int $offset = 0): array
    {
        $sql = "SELECT c.*, p.name as parent_name
                FROM #__news_categories c
                LEFT JOIN #__news_categories p ON c.parent_id = p.id
                ORDER BY c.name ASC
                LIMIT ? OFFSET ?";
        $query = $this->db->query($sql, [$limit, $offset]);
        return $query->num_rows ? $query->rows : [];
    }

    public function countCategories(): int
    {
        $query = $this->db->query("SELECT COUNT(*) as total FROM #__news_categories");
        return (int) $query->row['total'];
    }

    public function saveCategory(int $id, array $data): bool
    {
        // Convert 0, empty strings, or invalid parent IDs to NULL
        if (array_key_exists('parent_id', $data)) {
            if (empty($data['parent_id']) || (int)$data['parent_id'] <= 0) {
                $data['parent_id'] = null;
            } else {
                $data['parent_id'] = (int)$data['parent_id'];
            }
        }

        // Prevent a category from setting itself as its own parent
        if ($id > 0 && isset($data['parent_id']) && $data['parent_id'] === $id) {
            $data['parent_id'] = null;
        }

        if ($id > 0) {
            return $this->db->update('news_categories', $data, ['id' => $id]);
        }

        return $this->db->insert('news_categories', $data);
    }

    public function deleteCategory(int $id): bool
    {
        // Check if category has sub-categories or articles
        $children = $this->db->query(
            "SELECT COUNT(*) as total FROM #__news_categories WHERE parent_id = ?",
            [$id]
        )->row['total'] ?? 0;

        if ($children > 0) {
            return false; // Has children
        }

        $articles = $this->db->query(
            "SELECT COUNT(*) as total FROM #__news WHERE category_id = ?",
            [$id]
        )->row['total'] ?? 0;

        if ($articles > 0) {
            return false; // Has articles
        }

        return $this->db->delete('news_categories', ['id' => $id]);
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $sql = "SELECT COUNT(*) as total FROM #__news_categories WHERE slug = ?";
        $params = [$slug];
        if ($excludeId > 0) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        $query = $this->db->query($sql, $params);
        return (int) $query->row['total'] > 0;
    }

    public function getParentOptions(int $excludeId = 0): array
    {
        $sql = "SELECT id, name FROM #__news_categories ORDER BY name";
        $query = $this->db->query($sql);
        $options = [];
        foreach ($query->rows as $row) {
            if ($row['id'] != $excludeId) {
                $options[$row['id']] = $row['name'];
            }
        }
        return $options;
    }
}