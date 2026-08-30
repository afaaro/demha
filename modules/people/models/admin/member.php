<?php

use System\Engine\Model;

class PeopleAdminMemberModel extends Model
{
    /**
     * Fetch a member by ID.
     */
    public function getMemberById(int $member_id = 0): ?array
    {
        $query = $this->db->query(
            "SELECT * FROM #__people_member WHERE id = ?",
            [$member_id]
        );
        return $query->num_rows ? $query->row : null;
    }

    /**
     * Get father of a member (by father_id).
     */
    public function get_father(int $father_id = 0): ?array
    {
        return $this->getMemberById($father_id);
    }

    /**
     * Get mother of a member (by mother_id).
     */
    public function get_mother(int $mother_id = 0): ?array
    {
        return $this->getMemberById($mother_id);
    }

    /**
     * Get all children of a given parent (by father_id or mother_id).
     */
    public function get_children(int $parent_id = 0): array {
        if ($parent_id <= 0) return [];
        $query = $this->db->query(
            "SELECT * FROM #__people_member WHERE father_id = ? OR mother_id = ?",
            [$parent_id, $parent_id]
        );
        return $query->num_rows ? $query->rows : [];
    }

    /**
     * Get spouse(s) of a member (from relationship table).
     * Returns rows from #__people_relationship, not member data.
     * You may want to join member table to get full spouse details.
     */
    public function get_spouse(int $member_id = 0): array
    {
        // Using the same table name as controller: people_relationship
        $query = $this->db->query(
            "SELECT * FROM #__people_relationship WHERE male_id = ? OR female_id = ?",
            [$member_id, $member_id]
        );
        return $query->num_rows ? $query->rows : [];
    }

    /**
     * Get siblings of a member (same father and mother, excluding self).
     */
    public function get_siblings(int $member_id = 0): array {
        $member = $this->getMemberById($member_id);
        if (!$member || $member['father_id'] <= 0 || $member['mother_id'] <= 0) {
            return [];
        }

        $query = $this->db->query(
            "SELECT * FROM #__people_member
            WHERE father_id = ? AND mother_id = ? AND id != ?",
            [$member['father_id'], $member['mother_id'], $member_id]
        );
        return $query->num_rows ? $query->rows : [];
    }

    /**
     * Get parents (father and mother) of a member.
     * Returns array of two rows (if both exist).
     */
    public function get_parents(int $member_id = 0): array
    {
        $query = $this->db->query(
            "SELECT * FROM #__people_member
             WHERE id IN (SELECT father_id FROM #__people_member WHERE id = ?)
                OR id IN (SELECT mother_id FROM #__people_member WHERE id = ?)",
            [$member_id, $member_id]
        );
        return $query->num_rows ? $query->rows : [];
    }

    public function getSpouseDetails(int $member_id): array {
        $sql = "SELECT m.*
                FROM #__people_relationship r
                JOIN #__people_member m ON (m.id = r.male_id OR m.id = r.female_id)
                WHERE (r.male_id = ? OR r.female_id = ?) AND m.id != ?";
        $query = $this->db->query($sql, [$member_id, $member_id, $member_id]);
        return $query->num_rows ? $query->rows : [];
    }

    /**
     * Save (insert or update) a member record.
     * Returns true on success, false on failure.
     */
    public function saveMember(int $member_id, array $data): bool
    {
        // Basic validation – ensure required fields are present
        if (empty($data['fullname'])) {
            // Log error or throw exception; for now return false
            return false;
        }

        try {
            if ($member_id > 0) {
                // Update existing member
                $this->db->update('people_member', $data, ['id' => $member_id]);
            } else {
                // Insert new member
                $newId = $this->db->insert('people_member', $data);
                // Optionally, you can set the new ID for further use
            }
            return true;
        } catch (\Exception $e) {
            // Log the error: error_log($e->getMessage());
            return false;
        }
    }

    /**
     * Ensure a relationship (marriage) exists between father and mother.
     * If both IDs are valid and no relationship exists, insert one.
     */
    public function ensureRelationship(int $male_id, int $female_id): void
    {
        // Only proceed if both IDs are positive (valid)
        if ($male_id <= 0 || $female_id <= 0) {
            return;
        }

        // Check if a relationship already exists
        $query = $this->db->query(
            "SELECT COUNT(id) as total FROM #__people_relationship
             WHERE male_id = ? AND female_id = ?",
            [$male_id, $female_id]
        );
        $exists = (int) $query->row['total'] > 0;

        if (!$exists) {
            // Insert the relationship
            $this->db->insert('people_relationship', [
                'male_id'        => $male_id,
                'female_id'      => $female_id,
                'marital_status' => 'married'
            ]);
        }
    }

    private function member_details(int $member_id) {
        $html = "";
        $spouses = $this->model->get_parents($member_id);
        if (count($spouses) > 0) {
            $html .= "<ul>";
            foreach ($spouses as $spouse) {
                $gender = $spouse['gender'];
                $married = ($gender == 'male') ? "husband" : "wife";

                $html .= "<li>";
                $html .= "<div class='item $gender $married'><a href='" .
                    $this->url->to('people/admin/member', ['member_id' => $spouse['id']]) .
                    "' title='" . escape($spouse['fullname']) . "'>" .
                    escape($spouse['fullname']) . "</a><small>&nbsp;&nbsp;{$married}</small></div>";

                if ($gender == 'female') {
                    $spouse_children = $this->model->get_children($spouse['id']);
                } else {
                    $spouse_children = $this->model->get_children($member_id);
                }

                if (count($spouse_children) > 0) {
                    $html .= "<ul>";
                    foreach ($spouse_children as $item) {
                        $child_gender = ($item['gender'] == 'male') ? "male" : "female";
                        $child_label = ($item['gender'] == 'male') ? "son" : "daughter";
                        $children = $this->model->get_children($item['id']);

                        $html .= "<li>";
                        $html .= "<div class='item $child_gender'><a href='" .
                            $this->url->to('people/admin/member', ['member_id' => $item['id']]) .
                            "' title='" . escape($item['fullname']) . "'>" .
                            escape($item['fullname']) . "</a><small>&nbsp;&nbsp;{$child_label}</small></div>";
                        if (count($children) > 0) {
                            $html .= $this->member_details($item['id']);
                        }
                        $html .= "</li>";
                    }
                    $html .= "</ul>";
                }
                $html .= "</li>";
            }
            $html .= "</ul>";
        }
        return $html;
    }
}