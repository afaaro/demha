<?php

use System\Engine\Model;

/**
 * Model for managing Node Taxonomies (Vocabularies) and Terms
 */
class NodeTaxonomyModel extends Model
{
    /**
     * Database table for vocabularies (with prefix placeholder)
     *
     * @var string
     */
    protected $table = '#__node_taxonomy_vocabularies';

    /**
     * Related terms table name
     *
     * @var string
     */
    protected string $termsTable = '#__node_taxonomy_terms';

    // -------------------------------------------------------------------------
    // Vocabulary Methods
    // -------------------------------------------------------------------------

    /**
     * Get all vocabularies, ordered alphabetically by name
     *
     * @return array List of vocabulary records
     */
    public function getTaxonomies(): array
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} ORDER BY `name` ASC"
        )->rows ?? [];
    }

    /**
     * Get a single vocabulary by its primary key ID
     *
     * @param int $id Vocabulary ID
     * @return array|null Vocabulary record or null if not found
     */
    public function getTaxonomy(int $id): ?array
    {
        return $this->db->query(
            "SELECT * FROM {$this->table} WHERE `id` = ?",
            [$id]
        )->row ?: null;
    }

    // -------------------------------------------------------------------------
    // Terms Methods
    // -------------------------------------------------------------------------

    /**
     * Get terms by vocabulary machine name (used in indexAction listing)
     *
     * @param string $machineName Vocabulary machine name
     * @return array Terms belonging to this vocabulary
     */
    public function getTerms(string $machineName): array
    {
        return $this->db->query(
            "SELECT t.* FROM {$this->termsTable} t
             INNER JOIN {$this->table} v ON t.vocabulary_id = v.id
             WHERE v.machine_name = ?
             ORDER BY t.name ASC",
            [$machineName]
        )->rows ?? [];
    }

    /**
     * Get all terms grouped by vocabulary ID
     *
     * @return array [vocabulary_id => [term1, term2, ...]]
     */
    public function getTermsGrouped(): array
    {
        $all = $this->db->query(
            "SELECT * FROM {$this->termsTable} ORDER BY `name` ASC"
        )->rows ?? [];

        $grouped = [];
        foreach ($all as $term) {
            $taxId = $term['vocabulary_id'] ?? 0;
            $grouped[$taxId][] = $term;
        }
        return $grouped;
    }

    /**
     * Get terms belonging to a specific vocabulary by ID
     *
     * @param int $vocabularyId
     * @return array
     */
    public function getTermsByTaxonomy(int $vocabularyId): array
    {
        return $this->db->query(
            "SELECT * FROM {$this->termsTable} WHERE `vocabulary_id` = ? ORDER BY `name` ASC",
            [$vocabularyId]
        )->rows ?? [];
    }

    /**
     * Get a single term by ID
     *
     * @param int $termId
     * @return array|null Term record or null if not found
     */
    public function getTerm(int $termId): ?array
    {
        return $this->db->query(
            "SELECT * FROM {$this->termsTable} WHERE `id` = ?",
            [$termId]
        )->row ?: null;
    }

    /**
     * Save (create or update) a term — matches Controller expectations
     *
     * @param array $data Term data: id (optional), vocabulary_id, name, slug, description
     * @return int Term ID
     */
    public function saveTerm(array $data): int
    {
        // Generate slug if not provided
        $slug = !empty($data['slug']) ? $data['slug'] : $this->slugify($data['name'] ?? '');
        $description = $data['description'] ?? null;

        if (!empty($data['id'])) {
            // Update existing term
            $this->db->query(
                "UPDATE {$this->termsTable}
                 SET `name` = ?, `slug` = ?, `description` = ?, `vocabulary_id` = ?
                 WHERE `id` = ?",
                [$data['name'], $slug, $description, $data['vocabulary_id'], $data['id']]
            );
            return (int) $data['id'];
        }

        // Insert new term
        $this->db->query(
            "INSERT INTO {$this->termsTable} (`vocabulary_id`, `name`, `slug`, `description`)
             VALUES (?, ?, ?, ?)",
            [$data['vocabulary_id'], $data['name'], $slug, $description]
        );
        return (int) $this->db->getLastId();
    }

    /**
     * Delete a term by ID
     *
     * @param int $termId
     * @return void
     */
    public function deleteTerm(int $termId): void
    {
        $this->db->query(
            "DELETE FROM {$this->termsTable} WHERE `id` = ?",
            [$termId]
        );
    }

    // -------------------------------------------------------------------------
    // Internal Helpers
    // -------------------------------------------------------------------------

    /**
     * Generate a URL-friendly slug from text
     *
     * @param string $text
     * @return string
     */
    protected function slugify(string $text): string
    {
        return slug($text);
    }
}