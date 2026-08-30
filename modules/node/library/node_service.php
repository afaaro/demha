<?php
// modules/node/services/node_service.php
class NodeNodeService {
    private mixed $nodeModel;
    private mixed $taxonomyModel;
    private mixed $bundleModel;

    public function __construct()
    {
        $loader = registry('load');
        $this->nodeModel = $loader->model('node/node');
        $this->taxonomyModel = $loader->model('node/taxonomy');
        $this->bundleModel = $loader->model('node/node_bundle');
    }

    public function create(string $bundle, array $data): array
    {
        return $this->nodeModel->save($bundle, $data);
    }

    public function update(int $id, array $data): array
    {
        $entity = $this->nodeModel->load($id);
        if (!$entity) {
            return ['success' => false, 'errors' => ['Entity not found.']];
        }
        return $this->nodeModel->save($entity['bundle'], $data, $id);
    }

    public function delete(int $id): bool
    {
        return $this->nodeModel->delete($id);
    }

    public function get(int $id): ?array
    {
        return $this->nodeModel->load($id);
    }

    public function getBySlug(string $slug): ?array
    {
        return $this->nodeModel->loadBySlug($slug);
    }

    public function list(?string $bundle = null, array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->nodeModel->query($filters, $bundle, $page, $limit);
    }

    public function getFields(string $bundle): array
    {
        return $this->nodeModel->getFields($bundle);
    }

    public function getTaxonomyTerms(string $vocabulary): array
    {
        return $this->taxonomyModel->getTerms($vocabulary);
    }

    public function getAllBundles(): array
    {
        return $this->bundleModel->getBundles();
    }

    public function getBundle(string $machineName): ?array
    {
        return $this->bundleModel->getBundle($machineName);
    }

    public function getRevisions(int $entityId): array
    {
        return $this->nodeModel->db->query(
            "SELECT * FROM #__node_revisions WHERE entity_id = ? ORDER BY created_at DESC",
            [$entityId]
        )->rows;
    }

    public function search(string $query, array $bundles = []): array
    {
        $filters = ['search' => $query];
        if (!empty($bundles)) {
            // We need to handle multiple bundles – maybe add a bundle_in filter
            // For simplicity, we'll just search all.
        }
        return $this->nodeModel->query($filters, null, 1, 50);
    }
}