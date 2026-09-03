<?php

return [
    // ============================================
    // ADMIN ROUTES
    // ============================================
    
    // ---------- Content Management ----------
    'node/admin/post' => [
        'controller' => 'node/admin/post',
        'action' => 'index',
        'params' => ['bundle', 'status', 'search', 'page'],  // /admin/node/post/article/draft/search-term/2 → $params['bundle'] = 'article', $params['status'] = 'draft', $params['search'] = 'search-term', $params['page'] = 2
        'permission' => 'node.manage',
    ],
    
    'node/admin/post/create' => [
        'controller' => 'node/admin/post',
        'action' => 'create',
        'params' => ['bundle'],  // /admin/node/post/create/article → $params['bundle'] = 'article'
        'permission' => 'node.create',
    ],
    
    'node/admin/post/edit' => [
        'controller' => 'node/admin/post',
        'action' => 'edit',
        'permission' => 'node.edit',
        'params' => ['id'],  // /admin/node/post/edit/123 → $params['id'] = 123
    ],
    
    'node/admin/post/delete' => [
        'controller' => 'node/admin/post',
        'action' => 'delete',
        'permission' => 'node.delete',
        'params' => ['id'],
    ],
    
    // ---------- Bundle Management ----------
    'node/admin/bundle' => [
        'controller' => 'node/admin/bundle',
        'action' => 'index',
        'permission' => 'node.bundle.manage',
    ],
    
    'node/admin/bundle/create' => [
        'controller' => 'node/admin/bundle',
        'action' => 'create',
        'permission' => 'node.bundle.manage',
    ],
    
    'node/admin/bundle/edit' => [
        'controller' => 'node/admin/bundle',
        'action' => 'edit',
        'permission' => 'node.bundle.manage',
        'params' => ['id'],
    ],
    
    'node/admin/bundle/delete' => [
        'controller' => 'node/admin/bundle',
        'action' => 'delete',
        'permission' => 'node.bundle.manage',
        'params' => ['id'],
    ],
    
    // ---------- Field Management ----------
    'node/admin/field' => [
        'controller' => 'node/admin/field',
        'action' => 'index',
        'permission' => 'node.field.manage',
        'params' => ['bundle'],  // /admin/node/field/article → $params['bundle'] = 'article'
    ],
    
    'node/admin/field/create' => [
        'controller' => 'node/admin/field',
        'action' => 'create',
        'permission' => 'node.field.manage',
        'params' => ['bundle'],
    ],
    
    'node/admin/field/edit' => [
        'controller' => 'node/admin/field',
        'action' => 'edit',
        'permission' => 'node.field.manage',
        'params' => ['id'],  // /admin/node/field/edit/5 → $params['id'] = 5
    ],
    
    'node/admin/field/delete' => [
        'controller' => 'node/admin/field',
        'action' => 'delete',
        'permission' => 'node.field.manage',
        'params' => ['id'],
    ],
    
    // ---------- Taxonomy Management ----------
    'node/admin/taxonomy' => [
        'controller' => 'node/admin/taxonomy',
        'action' => 'index',
        'permission' => 'node.taxonomy.manage',
    ],
    
    'node/admin/taxonomy/create' => [
        'controller' => 'node/admin/taxonomy',
        'action' => 'create',
        'permission' => 'node.taxonomy.manage',
    ],
    
    'node/admin/taxonomy/edit' => [
        'controller' => 'node/admin/taxonomy',
        'action' => 'edit',
        'permission' => 'node.taxonomy.manage',
        'params' => ['id'],
    ],
    
    'node/admin/taxonomy/delete-vocab' => [
        'controller' => 'node/admin/taxonomy',
        'action' => 'deleteVocab',
        'permission' => 'node.taxonomy.manage',
        'params' => ['id'],
    ],
    
    'node/admin/taxonomy/add-term' => [
        'controller' => 'node/admin/taxonomy',
        'action' => 'addTerm',
        'permission' => 'node.taxonomy.manage',
    ],
    
    'node/admin/taxonomy/edit-term' => [
        'controller' => 'node/admin/taxonomy',
        'action' => 'editTerm',
        'permission' => 'node.taxonomy.manage',
        'params' => ['id'],
    ],
    
    'node/admin/taxonomy/delete-term' => [
        'controller' => 'node/admin/taxonomy',
        'action' => 'deleteTerm',
        'permission' => 'node.taxonomy.manage',
        'params' => ['id'],
    ],
    
    // ---------- Revisions ----------
    'node/admin/revisions' => [
        'controller' => 'node/admin/post',
        'action' => 'revisions',
        'permission' => 'node.manage',
        'params' => ['id'],  // /admin/node/revisions/5 → $params['id'] = 5
    ],
    
    'node/admin/revisions/restore' => [
        'controller' => 'node/admin/post',
        'action' => 'restoreRevision',
        'permission' => 'node.edit',
        'params' => ['id', 'revision_id'],  // /admin/node/revisions/restore/5/123 → $params['id'] = 5, $params['revision_id'] = 123
    ],
    
    // ============================================
    // PUBLIC ROUTES
    // ============================================
    
    // List content by bundle: /content/{bundle}
    'node/public/list' => [
        'controller' => 'node/home',
        'action' => 'list',
        'public' => true,
        'params' => ['bundle'],  // /content/article → $params['bundle'] = 'article'
    ],
    
    // List with pagination: /content/{bundle}/{page}
    'node/public/list/page' => [
        'controller' => 'node/home',
        'action' => 'list',
        'public' => true,
        'params' => ['bundle', 'page'],  // /content/article/2 → $params['bundle'] = 'article', $params['page'] = 2
    ],
    
    // View single content by slug: /content/{slug}
    'node/public/view' => [
        'controller' => 'node/home',
        'action' => 'view',
        'public' => true,
        'params' => ['slug'],  // /content/my-article-slug → $params['slug'] = 'my-article-slug'
    ],
    
    // View single content by ID (fallback): /content/view/{id}
    'node/public/view/id' => [
        'controller' => 'node/home',
        'action' => 'view',
        'public' => true,
        'params' => ['id'],  // /content/view/123 → $params['id'] = 123
    ],
    
    // Search: /search/{query}
    'node/public/search' => [
        'controller' => 'node/home',
        'action' => 'search',
        'public' => true,
        'params' => ['query'],  // /search/islam → $params['query'] = 'islam'
    ],
    
    // Search with pagination: /search/{query}/{page}
    'node/public/search/page' => [
        'controller' => 'node/home',
        'action' => 'search',
        'public' => true,
        'params' => ['query', 'page'],  // /search/islam/2 → $params['query'] = 'islam', $params['page'] = 2
    ],
    
    // List all bundles: /content
    'node/public/bundles' => [
        'controller' => 'node/home',
        'action' => 'bundles',
        'public' => true,
    ],
    
    // Category filter: /content/{bundle}/category/{category}
    'node/public/list/category' => [
        'controller' => 'node/home',
        'action' => 'list',
        'public' => true,
        'params' => ['bundle', 'category'],  // /content/article/category/5 → $params['bundle'] = 'article', $params['category'] = 5
    ],
    
    // Author filter: /content/{bundle}/author/{author}
    'node/public/list/author' => [
        'controller' => 'node/home',
        'action' => 'list',
        'public' => true,
        'params' => ['bundle', 'author'],  // /content/article/author/10 → $params['bundle'] = 'article', $params['author'] = 10
    ],
    
    // RSS Feed: /feed/{bundle}
    'node/public/feed' => [
        'controller' => 'node/home',
        'action' => 'feed',
        'public' => true,
        'params' => ['bundle'],  // /feed/article → $params['bundle'] = 'article'
    ],
    
    // Sitemap: /sitemap
    'node/public/sitemap' => [
        'controller' => 'node/home',
        'action' => 'sitemap',
        'public' => true,
    ],
    
    // ============================================
    // API ROUTES (Optional)
    // ============================================
    
    // API: Get nodes
    'node/api/nodes' => [
        'controller' => 'node/api',
        'action' => 'nodes',
        'public' => false,
        'permission' => 'node.api.access',
        'params' => ['bundle'],  // /api/nodes/article → $params['bundle'] = 'article'
    ],
    
    // API: Get single node
    'node/api/node' => [
        'controller' => 'node/api',
        'action' => 'node',
        'public' => false,
        'permission' => 'node.api.access',
        'params' => ['id'],  // /api/node/123 → $params['id'] = 123
    ],
    
    // API: Get node by slug
    'node/api/node/slug' => [
        'controller' => 'node/api',
        'action' => 'nodeBySlug',
        'public' => false,
        'permission' => 'node.api.access',
        'params' => ['slug'],  // /api/node/slug/my-article → $params['slug'] = 'my-article'
    ],
    
    // API: Get categories (taxonomy terms)
    'node/api/categories' => [
        'controller' => 'node/api',
        'action' => 'categories',
        'public' => false,
        'permission' => 'node.api.access',
        'params' => ['vocabulary'],  // /api/categories/categories → $params['vocabulary'] = 'categories'
    ],
];