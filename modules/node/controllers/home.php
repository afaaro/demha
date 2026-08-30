<?php

use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class NodeHome extends Controller
{
    private mixed $nodeService;
    private mixed $taxonomyModel;
    private mixed $bundleModel;

    public function __construct()
    {
        parent::__construct(Registry::getInstance());
        $this->nodeService = $this->load->library('node/node_service');
        $this->taxonomyModel = $this->load->model('node/taxonomy');
        $this->bundleModel = $this->load->model('node/node_bundle');
    }

    /**
     * List all published nodes of a given bundle.
     * URL: /content/{bundle}?page=2&category=5&search=keyword
     */
    public function listAction(): void
    {
        $bundle = $this->request->get('bundle', 'raw');
        if (!$bundle) {
            Notify::error('Bundle type is required.');
            redirect_to('/');
            return;
        }

        // Get bundle info
        $bundleInfo = $this->bundleModel->getBundle($bundle);
        if (!$bundleInfo) {
            Notify::error('Content type not found.');
            redirect_to('/');
            return;
        }

        // Build filters
        $filters = ['status' => 'published'];
        $category = (int) $this->request->get('category', 'int');
        if ($category) {
            // Assuming a field named 'category' of type 'taxonomy'
            $filters['field_filters'] = ['category' => $category];
        }
        $search = $this->request->get('search', 'raw');
        if ($search) {
            $filters['search'] = $search;
        }

        $page = (int) ($this->request->get('page', 'int') ?: 1);
        $result = $this->nodeService->list($bundle, $filters, $page);

        // Get taxonomy terms for filter dropdown (if 'category' field exists)
        $categories = [];
        $fields = $this->nodeService->getFields($bundle);
        foreach ($fields as $field) {
            if ($field['field_name'] === 'category' && $field['field_type'] === 'taxonomy') {
                $settings = json_decode($field['settings'] ?? '{}', true);
                if (!empty($settings['vocabulary'])) {
                    $categories = $this->taxonomyModel->getTerms($settings['vocabulary']);
                }
                break;
            }
        }

        echo $this->view->inline(function ($view) use ($result, $bundleInfo, $categories, $bundle, $search, $category) {
            // Bundle header
            echo "<div class='bundle-header'>";
            echo "<h1>" . htmlspecialchars($bundleInfo['name']) . "</h1>";
            if ($bundleInfo['description']) {
                echo "<p class='text-muted'>" . htmlspecialchars($bundleInfo['description']) . "</p>";
            }
            echo "</div>";

            // Filter bar (categories)
            if (!empty($categories)) {
                echo "<div class='filter-bar mb-4'>";
                echo "<div class='btn-group' role='group'>";
                echo "<a href='" . $view->url->to('content/list', ['bundle' => $bundle]) . "' class='btn btn-outline-primary " . ($category ? '' : 'active') . "'>All</a>";
                foreach ($categories as $term) {
                    $active = ($category == $term['id']) ? 'active' : '';
                    echo "<a href='" . $view->url->to('content/list', ['bundle' => $bundle, 'category' => $term['id']]) . "' class='btn btn-outline-primary $active'>" . htmlspecialchars($term['name']) . "</a>";
                }
                echo "</div>";
                echo "</div>";
            }

            // Search form
            echo "<form method='get' class='mb-4' action='" . $view->url->to('content/list', ['bundle' => $bundle]) . "'>";
            echo "<div class='input-group'>";
            echo "<input type='text' name='search' class='form-control' placeholder='Search...' value='" . htmlspecialchars($search) . "'>";
            echo "<button class='btn btn-primary' type='submit'>Search</button>";
            echo "</div>";
            echo "</form>";

            // Node list
            if (empty($result['data'])) {
                echo "<div class='alert alert-info'>No content found.</div>";
            } else {
                echo $this->renderList($result['data'], $view);
                echo $this->renderPagination($result['total'], $result['page'], $result['limit'], $view);
            }
        }, 'public');
    }

    /**
     * View a single node by slug or ID.
     * URL: /content/view/{slug}   or   /content/view?id=123
     */
    public function viewAction(): void
    {
        $slug = $this->request->get('slug', 'raw');
        $id = (int) $this->request->get('id', 'int');
        $node = null;

        if ($slug) {
            $node = $this->nodeService->getBySlug($slug);
        } elseif ($id) {
            $node = $this->nodeService->get($id);
        }

        if (!$node) {
            Notify::error('Content not found.');
            redirect_to('/');
            return;
        }

        // Increment view counter if a 'views' field exists
        if (isset($node['views'])) {
            $this->nodeService->update($node['id'], ['views' => ($node['views'] ?? 0) + 1]);
            $node['views']++;
        }

        // Get the bundle info
        $bundleInfo = $this->bundleModel->getBundle($node['bundle']);

        // Get related content (same bundle, same category if exists)
        $related = [];
        if (isset($node['category'])) {
            $related = $this->nodeService->list($node['bundle'], [
                'status' => 'published',
                'field_filters' => ['category' => $node['category']],
                'exclude' => $node['id']
            ], 1, 5);
        }

        echo $this->view->inline(function ($view) use ($node, $bundleInfo, $related) {
            // Article/Node header
            echo "<article class='node-full'>";
            echo "<header class='node-header'>";
            echo "<h1>" . htmlspecialchars($node['title']) . "</h1>";
            echo "<div class='node-meta'>";
            echo "<span class='bundle badge bg-info'>" . htmlspecialchars($bundleInfo['name'] ?? $node['bundle']) . "</span>";
            echo "<span class='date'>" . date('F d, Y', strtotime($node['created_at'])) . "</span>";
            if (isset($node['views'])) {
                echo "<span class='views'><i class='bi bi-eye'></i> " . $node['views'] . "</span>";
            }
            echo "</div>";
            echo "</header>";

            // Render fields
            $fields = $this->nodeService->getFields($node['bundle']);
            echo "<div class='node-body'>";
            foreach ($fields as $field) {
                $name = $field['field_name'];
                if (!isset($node[$name]) || $node[$name] === null || $node[$name] === '') {
                    continue;
                }
                $value = $node[$name];
                echo "<div class='field-{$name} mb-3'>";
                echo "<strong>" . htmlspecialchars($field['label']) . ":</strong> ";
                echo $this->formatFieldValue($value, $field['field_type']);
                echo "</div>";
            }
            echo "</div>";

            // Related content
            if (!empty($related['data'])) {
                echo "<section class='related-content mt-5'>";
                echo "<h3>Related Content</h3>";
                echo $this->renderList($related['data'], $view);
                echo "</section>";
            }

            echo "</article>";
        }, 'public');
    }

    /**
     * Render a list of nodes as a grid/list.
     */
    private function renderList(array $nodes, $view): string
    {
        if (empty($nodes)) {
            return '';
        }

        $html = '<div class="row row-cols-1 row-cols-md-2 g-4">';
        foreach ($nodes as $node) {
            $url = $view->url->to('content/view', ['slug' => $node['slug']]);
            $html .= '<div class="col">';
            $html .= '<div class="card h-100">';
            // Optional: if there's an image field, show it as a card image
            if (isset($node['image']) && !empty($node['image'])) {
                $html .= "<img src='/{$node['image']}' class='card-img-top' style='height:200px;object-fit:cover;' alt='...'>";
            }
            $html .= '<div class="card-body">';
            $html .= "<h5 class='card-title'><a href='$url'>" . htmlspecialchars($node['title']) . "</a></h5>";
            // Show excerpt if available (e.g., from a 'abstract' field)
            if (isset($node['abstract']) && !empty($node['abstract'])) {
                $html .= "<p class='card-text'>" . htmlspecialchars(substr(strip_tags($node['abstract']), 0, 150)) . "...</p>";
            }
            // Show author if entity_reference to scholar
            if (isset($node['author'])) {
                $author = $this->nodeService->get((int)$node['author']);
                if ($author) {
                    $html .= "<p class='card-text'><small class='text-muted'>By " . htmlspecialchars($author['title']) . "</small></p>";
                }
            }
            $html .= "</div>";
            $html .= '<div class="card-footer text-muted">' . date('M d, Y', strtotime($node['created_at'])) . '</div>';
            $html .= "</div></div>";
        }
        $html .= '</div>';
        return $html;
    }

    /**
     * Format a field value based on its type.
     */
    private function formatFieldValue(mixed $value, string $type): string
    {
        if ($type === 'taxonomy') {
            $term = $this->taxonomyModel->getTerm((int)$value);
            return $term ? htmlspecialchars($term['name']) : '';
        }
        if ($type === 'entity_reference') {
            $target = $this->nodeService->get((int)$value);
            if ($target) {
                $url = $this->url->to('content/view', ['slug' => $target['slug']]);
                return "<a href='$url'>" . htmlspecialchars($target['title']) . "</a>";
            }
            return '';
        }
        if ($type === 'image') {
            return "<img src='/$value' style='max-width:300px; max-height:300px;' class='img-fluid'>";
        }
        if ($type === 'file') {
            return "<a href='/$value' download class='btn btn-sm btn-outline-primary'><i class='bi bi-download'></i> Download</a>";
        }
        if ($type === 'date') {
            return date('F d, Y', strtotime($value));
        }
        if ($type === 'richtext') {
            return $value; // Trusted HTML; ensure you sanitize if needed.
        }
        return htmlspecialchars($value);
    }

    /**
     * Render pagination links.
     */
    private function renderPagination(int $total, int $page, int $limit, $view): string
    {
        $last = (int) ceil($total / $limit);
        if ($last <= 1) {
            return '';
        }

        $currentUrl = $view->url->current();
        $queryParams = $this->request->getAll();
        unset($queryParams['page']);

        $html = '<nav><ul class="pagination justify-content-center">';
        $prev = $page > 1 ? $page - 1 : 1;
        $next = $page < $last ? $page + 1 : $last;

        // Previous
        $queryParams['page'] = $prev;
        $url = $view->url->to('content/list', $queryParams);
        $html .= "<li class='page-item " . ($page == 1 ? 'disabled' : '') . "'><a class='page-link' href='$url'>&laquo;</a></li>";

        // Pages
        for ($i = 1; $i <= $last; $i++) {
            $queryParams['page'] = $i;
            $url = $view->url->to('content/list', $queryParams);
            $active = ($i == $page) ? 'active' : '';
            $html .= "<li class='page-item $active'><a class='page-link' href='$url'>$i</a></li>";
        }

        // Next
        $queryParams['page'] = $next;
        $url = $view->url->to('content/list', $queryParams);
        $html .= "<li class='page-item " . ($page == $last ? 'disabled' : '') . "'><a class='page-link' href='$url'>&raquo;</a></li>";

        $html .= '</ul></nav>';
        return $html;
    }
}