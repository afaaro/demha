<?php
use System\Engine\Model;

class NewsAdminTagModel extends Model
{
    public function getTag(int $id): ?array
    {
        $query = $this->db->query("SELECT * FROM #__news_tags WHERE tag_id = ?", [$id]);
        return $query->num_rows ? $query->row : null;
    }

    public function getTags(int $limit = 30, int $offset = 0): array
    {
        $query = $this->db->query(
            "SELECT * FROM #__news_tags ORDER BY name ASC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
        return $query->num_rows ? $query->rows : [];
    }

    public function countTags(): int
    {
        $query = $this->db->query("SELECT COUNT(*) as total FROM #__news_tags");
        return (int) $query->row['total'];
    }

    public function saveTag(int $id, array $data): bool
    {
        if ($id > 0) {
            return $this->db->update('news_tags', $data, ['tag_id' => $id]);
        } else {
            return $this->db->insert('news_tags', $data);
        }
    }

    public function deleteTag(int $id): bool
    {
        return $this->db->delete('news_tags', ['tag_id' => $id]);
    }

    public function slugExists(string $slug, int $excludeId = 0): bool
    {
        $sql = "SELECT COUNT(*) as total FROM #__news_tags WHERE slug = ?";
        $params = [$slug];
        if ($excludeId > 0) {
            $sql .= " AND tag_id != ?";
            $params[] = $excludeId;
        }
        $query = $this->db->query($sql, $params);
        return (int) $query->row['total'] > 0;
    }
}