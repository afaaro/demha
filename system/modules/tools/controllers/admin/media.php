<?php
use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class ToolsAdminMedia extends Controller
{
    public function __construct(Registry $registry)
    {
        parent::__construct($registry);
        // Ensure logger is available
        if (!isset($this->logger)) {
            $this->logger = $registry->get('logger');
        }
    }

    /**
     * Main media manager interface.
     */
    public function indexAction(): void
    {
        if (!$this->auth->can('tools.admin.media.view')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        $folderId = (int) $this->request->get('folder', 'int', 0);
        
        try {
            $currentFolder = $folderId ? $this->media->getFolder($folderId) : null;
            // If folder doesn't exist, reset to root
            if ($folderId && !$currentFolder) {
                $folderId = 0;
                $currentFolder = null;
            }
            $files = $this->media->getFiles($folderId);
            $folders = $this->media->getFolders($folderId);
        } catch (\Exception $e) {
            Notify::error('Failed to load media: ' . $e->getMessage());
            $files = [];
            $folders = [];
            $currentFolder = null;
        }

        $breadcrumb = $this->buildBreadcrumb($folderId);

        echo $this->view->inline(function ($view) use ($files, $folders, $currentFolder, $folderId, $breadcrumb) {
            echo '<div class="d-flex justify-content-between mb-3">';
            echo '  <h3>Media Manager</h3>';
            echo '  <div>';
            echo '    <button class="btn btn-primary me-1" data-bs-toggle="modal" data-bs-target="#uploadModal">Upload</button>';
            echo '    <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#folderModal">New Folder</button>';
            echo '  </div>';
            echo '</div>';

            // Breadcrumb
            echo '<nav aria-label="breadcrumb">';
            echo '  <ol class="breadcrumb">';
            echo '    <li class="breadcrumb-item"><a href="' . $view->url->to('tools/admin/media') . '">Root</a></li>';
            foreach ($breadcrumb as $crumb) {
                echo '<li class="breadcrumb-item' . ($crumb['active'] ? ' active' : '') . '">';
                if (!$crumb['active']) {
                    echo '<a href="' . $view->url->to('tools/admin/media', ['folder' => $crumb['id']]) . '">';
                }
                echo escape($crumb['name']);
                if (!$crumb['active']) {
                    echo '</a>';
                }
                echo '</li>';
            }
            echo '  </ol>';
            echo '</nav>';

            // Folders
            if (!empty($folders)) {
                echo '<div class="row mb-3">';
                foreach ($folders as $folder) {
                    echo '<div class="col-md-2 col-sm-3 col-4">';
                    echo '  <div class="card text-center p-2">';
                    echo '    <a href="' . $view->url->to('tools/admin/media', ['folder' => $folder['id']]) . '">';
                    echo '      <i class="bi bi-folder fs-1"></i>';
                    echo '      <div class="small">' . escape($folder['name']) . '</div>';
                    echo '    </a>';
                    echo '    <div class="btn-group btn-group-sm mt-1">';
                    echo '      <button class="btn btn-outline-secondary" onclick="renameFolder(' . $folder['id'] . ')">Rename</button>';
                    echo '      <button class="btn btn-outline-danger" onclick="deleteFolder(' . $folder['id'] . ')">Delete</button>';
                    echo '    </div>';
                    echo '  </div>';
                    echo '</div>';
                }
                echo '</div>';
            }

            // Files
            if (!empty($files)) {
                echo '<div class="row">';
                foreach ($files as $file) {
                    $url = $this->media->getFileUrl($file);
                    $thumb = $this->media->getThumbnailUrl($file);
                    $isImage = in_array($file['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                    echo '<div class="col-md-2 col-sm-3 col-4">';
                    echo '  <div class="card text-center p-2">';
                    if ($isImage && $thumb) {
                        echo '    <img src="' . escape($thumb) . '" class="img-fluid" alt="' . escape($file['name']) . '">';
                    } else {
                        echo '    <i class="bi bi-file-earmark fs-1"></i>';
                    }
                    echo '    <div class="small text-truncate" title="' . escape($file['name']) . '">' . escape($file['name']) . '</div>';
                    echo '    <div class="btn-group btn-group-sm mt-1">';
                    echo '      <a href="' . escape($url) . '" target="_blank" class="btn btn-outline-primary">View</a>';
                    echo '      <button class="btn btn-outline-secondary" onclick="renameFile(' . $file['id'] . ')">Rename</button>';
                    echo '      <button class="btn btn-outline-danger" onclick="deleteFile(' . $file['id'] . ')">Delete</button>';
                    echo '    </div>';
                    echo '  </div>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<div class="alert alert-info">This folder is empty.</div>';
            }

            // Upload Modal
            echo $this->uploadModal($folderId);

            // Folder Modal
            echo $this->folderModal($folderId);

            // JavaScript
            $this->inlineScripts();

        }, 'admin');
    }

    /**
     * Upload files.
     */
    public function uploadAction(): void
    {
        if (!$this->auth->can('tools.admin.media.upload')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        // CSRF check
        if (!$this->form->checkToken()) {
            Notify::error('Invalid security token.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        $folderId = (int) $this->request->post('folder', 'int', 0);
        $returnUrl = $this->validateReturnUrl(
            $this->request->post('return_url', 'raw', '')
        );

        // If no return URL, use media manager
        if (!$returnUrl) {
            $returnUrl = $this->url->to('tools/admin/media', ['folder' => $folderId]);
        }

        // Validate folder exists
        if ($folderId && !$this->media->getFolder($folderId)) {
            Notify::error('Selected folder does not exist.');
            redirect($returnUrl);
            return;
        }

        $files = $_FILES['files'] ?? [];

        if (empty($files) || $files['error'][0] === UPLOAD_ERR_NO_FILE) {
            Notify::error('No files selected.');
            redirect($returnUrl);
            return;
        }

        $maxSize = $this->config->get('media.max_upload_size', 20 * 1024 * 1024);
        $allowedTypes = $this->config->get('media.allowed_types', ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx']);

        // Handle single file
        if (!isset($files['name'][1])) {
            $singleFile = [
                'name' => $files['name'][0],
                'type' => $files['type'][0],
                'tmp_name' => $files['tmp_name'][0],
                'error' => $files['error'][0],
                'size' => $files['size'][0],
            ];

            $result = $this->media->uploadFile($singleFile, $folderId, $this->auth->user()['id']);
            if ($result['success']) {
                Notify::success('File uploaded: ' . $result['file']['name']);
                $this->logger->info('File uploaded', ['file_id' => $result['file']['id'], 'user_id' => $this->auth->user()['id']]);
            } else {
                Notify::error('Upload failed: ' . $result['message']);
                $this->logger->warning('Upload failed', ['message' => $result['message'], 'user_id' => $this->auth->user()['id']]);
            }
        } else {
            // Multiple files
            $successCount = 0;
            $uploadedFiles = [];
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                
                $singleFile = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i],
                ];

                $result = $this->media->uploadFile($singleFile, $folderId, $this->auth->user()['id']);
                if ($result['success']) {
                    $successCount++;
                    $uploadedFiles[] = $result['file']['name'];
                }
            }

            if ($successCount > 0) {
                Notify::success("Uploaded {$successCount} file(s).");
                $this->logger->info('Multiple files uploaded', ['count' => $successCount, 'files' => $uploadedFiles, 'user_id' => $this->auth->user()['id']]);
            } else {
                Notify::error('No files could be uploaded.');
            }
        }

        redirect($returnUrl);
    }

    /**
     * Create a new folder.
     */
    public function folderCreateAction(): void
    {
        if (!$this->auth->can('tools.admin.media.create')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        if (!$this->form->checkToken()) {
            Notify::error('Invalid security token.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        $parentId = (int) $this->request->post('parent_id', 'int', 0);
        $name = $this->sanitizeFolderName($this->request->post('name', 'string', ''));

        if (!$name) {
            Notify::error('Folder name is required and must be valid.');
            redirect($this->url->to('tools/admin/media', ['folder' => $parentId]));
            return;
        }

        try {
            $success = $this->media->createFolder($name, $parentId);
            if ($success) {
                Notify::success('Folder created: ' . $name);
                $this->logger->info('Folder created', ['folder_name' => $name, 'parent_id' => $parentId, 'user_id' => $this->auth->user()['id']]);
            } else {
                Notify::error('Failed to create folder (name may already exist).');
            }
        } catch (\Exception $e) {
            Notify::error('Failed to create folder: ' . $e->getMessage());
            $this->logger->error('Folder creation failed', ['error' => $e->getMessage(), 'user_id' => $this->auth->user()['id']]);
        }

        redirect($this->url->to('tools/admin/media', ['folder' => $parentId]));
    }

    /**
     * Rename a folder.
     */
    public function folderRenameAction(): void
    {
        if (!$this->auth->can('tools.admin.media.edit')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        if (!$this->form->checkToken()) {
            Notify::error('Invalid security token.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        $id = (int) $this->request->post('id', 'int', 0);
        $name = $this->sanitizeFolderName($this->request->post('name', 'string', ''));

        if (!$id || !$name) {
            Notify::error('Invalid data.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        $folder = $this->media->getFolder($id);
        if (!$folder) {
            Notify::error('Folder not found.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        try {
            $success = $this->media->renameFolder($id, $name);
            if ($success) {
                Notify::success('Folder renamed to: ' . $name);
                $this->logger->info('Folder renamed', ['folder_id' => $id, 'new_name' => $name, 'user_id' => $this->auth->user()['id']]);
            } else {
                Notify::error('Failed to rename folder (name may already exist).');
            }
        } catch (\Exception $e) {
            Notify::error('Failed to rename folder: ' . $e->getMessage());
            $this->logger->error('Folder rename failed', ['error' => $e->getMessage(), 'user_id' => $this->auth->user()['id']]);
        }

        redirect($this->url->to('tools/admin/media', ['folder' => $folder['parent_id']]));
    }

    /**
     * Delete a folder.
     */
    public function folderDeleteAction(): void
    {
        if (!$this->auth->can('tools.admin.media.delete')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        if (!$this->form->checkToken()) {
            Notify::error('Invalid security token.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        $id = (int) $this->request->post('id', 'int', 0);
        $folder = $this->media->getFolder($id);
        if (!$folder) {
            Notify::error('Folder not found.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        $parent = $folder['parent_id'];
        try {
            $success = $this->media->deleteFolder($id);
            if ($success) {
                Notify::success('Folder deleted.');
                $this->logger->info('Folder deleted', ['folder_id' => $id, 'user_id' => $this->auth->user()['id']]);
            } else {
                Notify::error('Failed to delete folder.');
            }
        } catch (\Exception $e) {
            Notify::error('Failed to delete folder: ' . $e->getMessage());
            $this->logger->error('Folder deletion failed', ['error' => $e->getMessage(), 'user_id' => $this->auth->user()['id']]);
        }

        redirect($this->url->to('tools/admin/media', ['folder' => $parent]));
    }

    /**
     * Rename a file.
     */
    public function fileRenameAction(): void
    {
        if (!$this->auth->can('tools.admin.media.edit')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        if (!$this->form->checkToken()) {
            Notify::error('Invalid security token.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        $id = (int) $this->request->post('id', 'int', 0);
        $name = $this->sanitizeFileName($this->request->post('name', 'string', ''));

        if (!$id || !$name) {
            Notify::error('Invalid data.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        $file = $this->media->getFile($id);
        if (!$file) {
            Notify::error('File not found.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        try {
            $success = $this->media->renameFile($id, $name);
            if ($success) {
                Notify::success('File renamed to: ' . $name);
                $this->logger->info('File renamed', ['file_id' => $id, 'new_name' => $name, 'user_id' => $this->auth->user()['id']]);
            } else {
                Notify::error('Failed to rename file.');
            }
        } catch (\Exception $e) {
            Notify::error('Failed to rename file: ' . $e->getMessage());
            $this->logger->error('File rename failed', ['error' => $e->getMessage(), 'user_id' => $this->auth->user()['id']]);
        }

        redirect($this->url->to('tools/admin/media', ['folder' => $file['folder_id']]));
    }

    /**
     * Delete a file.
     */
    public function fileDeleteAction(): void
    {
        if (!$this->auth->can('tools.admin.media.delete')) {
            throw new \RuntimeException('Permission denied.', 403);
        }

        if (!$this->form->checkToken()) {
            Notify::error('Invalid security token.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        $id = (int) $this->request->post('id', 'int', 0);
        $file = $this->media->getFile($id);
        if (!$file) {
            Notify::error('File not found.');
            redirect($this->url->to('tools/admin/media'));
            return;
        }

        $folder = $file['folder_id'];
        try {
            $success = $this->media->deleteFile($id);
            if ($success) {
                Notify::success('File deleted.');
                $this->logger->info('File deleted', ['file_id' => $id, 'user_id' => $this->auth->user()['id']]);
            } else {
                Notify::error('Failed to delete file.');
            }
        } catch (\Exception $e) {
            Notify::error('Failed to delete file: ' . $e->getMessage());
            $this->logger->error('File deletion failed', ['error' => $e->getMessage(), 'user_id' => $this->auth->user()['id']]);
        }

        redirect($this->url->to('tools/admin/media', ['folder' => $folder]));
    }

    /**
     * File picker for TinyMCE.
     */
    public function pickerAction(): void
    {
        if (!$this->auth->can('tools.admin.media.view')) {
            http_response_code(403);
            echo 'Permission denied.';
            return;
        }

        $folderId = (int) $this->request->get('folder', 'int', 0);
        
        try {
            // Validate folder exists
            if ($folderId && !$this->media->getFolder($folderId)) {
                $folderId = 0;
            }
            $files = $this->media->getFiles($folderId);
            $folders = $this->media->getFolders($folderId);
        } catch (\Exception $e) {
            $this->logger->error('Picker error: ' . $e->getMessage());
            $files = [];
            $folders = [];
        }

        $returnUrl = $this->url->to('tools/admin/media/picker', ['folder' => $folderId]);
        $csrfToken = $this->form->getToken();
        echo $this->view->inline(function ($view) use ($folderId, $folders, $files, $returnUrl, $csrfToken) {
            echo '<!DOCTYPE html><html><head>';
            echo '<meta charset="UTF-8">';
            echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
            echo '<title>Media Picker</title>';
            echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">';
            echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">';
            echo '<style>
                body { padding: 1rem; background: #f8f9fa; width: 100%; }
                .upload-area { border: 2px dashed #ccc; padding: 1rem; text-align: center; margin-bottom: 1rem; background: #fff; border-radius: 8px; }
                .upload-area:hover { border-color: #0d6efd; background: #f8f9fa; }
                .file-grid .card { transition: transform 0.2s; cursor: pointer; }
                .file-grid .card:hover { transform: scale(1.03); z-index: 1; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
                .folder-btn { margin-right: 4px; margin-bottom: 4px; }
            </style>';
            echo '</head><body>';

            echo '<div class="container-fluid">';
            echo '<div class="d-flex justify-content-between align-items-center mb-3">';
            echo '  <h4>Media Picker</h4>';
            echo '  <a class="btn btn-sm btn-secondary" href="' . $returnUrl . '"><i class="bi bi-arrow-repeat"></i> Refresh</a>';
            echo '</div>';

            // Upload form
            echo '<div class="upload-area">';
            echo '<form method="POST" enctype="multipart/form-data" action="' . $this->url->to('tools/admin/media/upload') . '">';
            echo '<input type="hidden" name="folder" value="' . $folderId . '">';
            echo '<input type="hidden" name="return_url" value="' . $returnUrl . '">';
            echo $view->form->csrfField();
            echo '<div class="row align-items-end">';
            echo '  <div class="col-md-8">';
            echo '    <label for="fileInput" class="form-label">Upload files</label>';
            echo '    <input type="file" class="form-control" id="fileInput" name="files[]" multiple required>';
            echo '  </div>';
            echo '  <div class="col-md-4">';
            echo '    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-cloud-upload"></i> Upload</button>';
            echo '  </div>';
            echo '</div>';
            echo '</form>';
            echo '</div>';

            // Folder navigation
            echo '<div class="mb-3">';
            echo '<a href="' . $this->url->to('tools/admin/media/picker') . '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-house"></i> Root</a>';
            $parent = $this->media->getFolder($folderId);
            if ($parent && $parent['parent_id']) {
                echo ' <a href="' . $this->url->to('tools/admin/media/picker', ['folder' => $parent['parent_id']]) . '" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-up"></i> ..</a>';
            }
            if ($folderId > 0) {
                echo ' <span class="ms-2 text-muted">Current: ' . escape($parent['name'] ?? '') . '</span>';
            }
            echo '</div>';

            // Folders
            if (!empty($folders)) {
                echo '<div class="mb-3">';
                foreach ($folders as $folder) {
                    echo '<a href="#" onclick="navigateFolder(' . $folder['id'] . '); return false;" class="btn btn-outline-primary btn-sm folder-btn">';
                    echo '<i class="bi bi-folder"></i> ' . escape($folder['name']);
                    echo '</a>';
                }
                echo '</div>';
            }

            // Files
            if (!empty($files)) {
                echo '<div class="row g-2 file-grid">';
                foreach ($files as $file) {
                    $url = $this->media->getFileUrl($file);
                    $thumb = $this->media->getThumbnailUrl($file);
                    $isImage = in_array($file['extension'], ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg']);
                    
                    echo '<div class="col-3">';
                    echo '<div class="card h-100" onclick="insertMedia(\'' . addslashes($url) . '\', \'' . addslashes($file['name']) . '\')">';
                    if ($isImage && $thumb) {
                        echo '<img src="' . $thumb . '" class="card-img-top" style="object-fit:cover;height:120px;" alt="' . escape($file['name']) . '">';
                    } else {
                        echo '<div class="card-img-top text-center p-3"><i class="bi bi-file-earmark fs-1"></i></div>';
                    }
                    echo '<div class="card-body p-1 text-center">';
                    echo '<div class="small text-truncate" title="' . escape($file['name']) . '">' . escape($file['name']) . '</div>';
                    echo '<span class="badge bg-secondary">' . escape($file['extension'] ?? '') . '</span>';
                    echo '</div>';
                    echo '</div>';
                    echo '</div>';
                }
                echo '</div>';
            } else {
                echo '<div class="alert alert-info">No files in this folder.</div>';
            }

            echo '</div>'; // container

            // JavaScript
            echo <<<JS
            <script>
            function insertMedia(url, name) {
                var editor = null;
                if (window.parent && window.parent.tinymce && window.parent.tinymce.activeEditor) {
                    editor = window.parent.tinymce.activeEditor;
                } else if (window.opener && window.opener.tinymce && window.opener.tinymce.activeEditor) {
                    editor = window.opener.tinymce.activeEditor;
                } else if (window.top && window.top.tinymce && window.top.tinymce.activeEditor) {
                    editor = window.top.tinymce.activeEditor;
                }

                if (!editor) {
                    alert('Could not find TinyMCE editor.');
                    return;
                }

                // Check if it's an image
                var isImage = /\.(jpg|jpeg|png|gif|webp|svg)$/i.test(url);
                var html = isImage ? '<img src="' + url + '" alt="' + name + '">' : '<a href="' + url + '">' + name + '</a>';
                editor.insertContent(html);

                // Close dialog
                if (window.parent && window.parent.tinymce && window.parent.tinymce.activeEditor) {
                    var dialogs = window.parent.tinymce.activeEditor.windowManager.getWindows();
                    if (dialogs.length > 0) {
                        dialogs[0].close();
                    }
                } else if (window.opener) {
                    window.close();
                }
            }

            function navigateFolder(folderId) {
                window.location.href = '{$view->url->to('tools/admin/media/picker')}?folder=' + folderId;
            }
            </script>
            JS;

            echo '</body></html>';
        }, '');
    }

    // ===================== Helper Methods =====================

    /**
     * Validate return URL to prevent open redirects.
     */
    protected function validateReturnUrl(string $url): string
    {
        if (empty($url)) {
            return '';
        }

        // Allow relative URLs starting with /
        if (str_starts_with($url, '/')) {
            return $url;
        }

        // Allow full URLs from the same domain
        $baseUrl = $this->request->getBaseUrl();
        if (str_starts_with($url, $baseUrl)) {
            return $url;
        }

        // Invalid – return empty
        $this->logger->warning('Invalid return URL attempted', ['url' => $url]);
        return '';
    }

    /**
     * Sanitize folder name (allow letters, numbers, spaces, hyphens, underscores).
     */
    protected function sanitizeFolderName(string $name): string
    {
        // Remove any path traversal attempts
        $name = str_replace(['..', '/', '\\', "\0"], '', $name);
        // Remove any characters that aren't allowed
        $name = preg_replace('/[^a-zA-Z0-9 _-]/', '', $name);
        // Trim spaces and limit length
        return trim(substr($name, 0, 100));
    }

    /**
     * Sanitize file name (allow letters, numbers, spaces, hyphens, underscores, dots).
     */
    protected function sanitizeFileName(string $name): string
    {
        // Remove path traversal
        $name = str_replace(['..', '/', '\\', "\0"], '', $name);
        // Remove any characters that aren't allowed
        $name = preg_replace('/[^a-zA-Z0-9 ._-]/', '', $name);
        // Trim spaces and limit length
        return trim(substr($name, 0, 100));
    }

    /**
     * Build breadcrumb trail.
     */
    protected function buildBreadcrumb(int $folderId): array
    {
        $crumbs = [];
        $current = $folderId ? $this->media->getFolder($folderId) : null;
        
        while ($current) {
            $crumbs[] = ['id' => $current['id'], 'name' => $current['name'], 'active' => false];
            $current = $current['parent_id'] ? $this->media->getFolder($current['parent_id']) : null;
        }
        
        $crumbs = array_reverse($crumbs);
        if (!empty($crumbs)) {
            $crumbs[count($crumbs) - 1]['active'] = true;
        }
        return $crumbs;
    }

    /**
     * Upload modal HTML.
     */
    protected function uploadModal(int $currentFolderId): string
    {
        $allFolders = $this->media->getFolders(0);
        $folderOptions = [0 => 'Root'];
        foreach ($allFolders as $folder) {
            $folderOptions[$folder['id']] = $folder['name'];
        }

        $optionsHtml = '';
        foreach ($folderOptions as $id => $name) {
            $selected = ($id == $currentFolderId) ? ' selected' : '';
            $optionsHtml .= sprintf(
                '<option value="%d"%s>%s</option>',
                $id,
                $selected,
                escape($name)
            );
        }

        $csrfField = $this->form->csrfField();
        return <<<HTML
        <div class="modal fade" id="uploadModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Upload Files</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" enctype="multipart/form-data" action="{$this->url->to('tools/admin/media/upload')}">
                        {$csrfField}
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="folderSelect" class="form-label">Destination Folder</label>
                                <select class="form-select" id="folderSelect" name="folder">
                                    {$optionsHtml}
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="fileInput" class="form-label">Select files</label>
                                <input type="file" class="form-control" id="fileInput" name="files[]" multiple required>
                                <small class="text-muted">Max file size: 20MB</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Upload</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        HTML;
    }

    /**
     * Folder modal HTML.
     */
    protected function folderModal(int $parentId): string
    {
        $csrfField = $this->form->csrfField();
        return <<<HTML
        <div class="modal fade" id="folderModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">New Folder</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="{$this->url->to('tools/admin/media/folder-create')}">
                        {$csrfField}
                        <input type="hidden" name="parent_id" value="{$parentId}">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="folderName" class="form-label">Folder Name</label>
                                <input type="text" class="form-control" id="folderName" name="name" required>
                                <small class="text-muted">Allowed: letters, numbers, spaces, hyphens, underscores</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        HTML;
    }

    /**
     * Inline JavaScript for actions.
     */
    protected function inlineScripts(): void
    {
        $this->doc->addInlineJs("
            function renameFolder(id) {
                var name = prompt('Enter new folder name:');
                if (name && name.trim()) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '" . $this->url->to('tools/admin/media/folder-rename') . "';
                    
                    var inputs = [
                        {name: 'id', value: id},
                        {name: 'name', value: name.trim()},
                        {name: 'csrf_token', value: '" . $this->form->getToken() . "'}
                    ];
                    inputs.forEach(function(data) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = data.name;
                        input.value = data.value;
                        form.appendChild(input);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            function deleteFolder(id) {
                if (confirm('Delete this folder and all its contents?')) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '" . $this->url->to('tools/admin/media/folder-delete') . "';
                    
                    var inputs = [
                        {name: 'id', value: id},
                        {name: 'csrf_token', value: '" . $this->form->getToken() . "'}
                    ];
                    inputs.forEach(function(data) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = data.name;
                        input.value = data.value;
                        form.appendChild(input);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            function renameFile(id) {
                var name = prompt('Enter new file name:');
                if (name && name.trim()) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '" . $this->url->to('tools/admin/media/file-rename') . "';
                    
                    var inputs = [
                        {name: 'id', value: id},
                        {name: 'name', value: name.trim()},
                        {name: 'csrf_token', value: '" . $this->form->getToken() . "'}
                    ];
                    inputs.forEach(function(data) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = data.name;
                        input.value = data.value;
                        form.appendChild(input);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }

            function deleteFile(id) {
                if (confirm('Delete this file?')) {
                    var form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '" . $this->url->to('tools/admin/media/file-delete') . "';
                    
                    var inputs = [
                        {name: 'id', value: id},
                        {name: 'csrf_token', value: '" . $this->form->getToken() . "'}
                    ];
                    inputs.forEach(function(data) {
                        var input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = data.name;
                        input.value = data.value;
                        form.appendChild(input);
                    });
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        ");
    }
}