<?php

namespace System\Engine;

use System\Engine\Registry;
use System\Library\Database;

class Media
{
    protected Registry $registry;
    protected Database $db;
    protected string $basePath;
    protected string $baseUrl;
    protected array $allowedExtensions = [
        'image' => ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp'],
        'document' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv'],
        'video' => ['mp4', 'webm', 'ogg', 'avi', 'mov'],
        'audio' => ['mp3', 'wav', 'ogg', 'aac'],
        'archive' => ['zip', 'rar', '7z', 'tar', 'gz'],
    ];

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->basePath = BASEDIR . 'storage/media/';
        $this->baseUrl = BASEURL . '/storage/media/';

        if (!is_dir($this->basePath)) {
            mkdir($this->basePath, 0755, true);
        }
    }

    /**
     * Get all files in a folder
     */
    public function getFiles(int $folderId = 0): array
    {
        return $this->db->find('media', ['folder_id' => $folderId], 'name ASC');
    }

    /**
     * Get all subfolders
     */
    public function getFolders(int $parentId = 0): array
    {
        return $this->db->find('media_folder', ['parent_id' => $parentId], 'name ASC');
    }

    /**
     * Get a single file by ID
     */
    public function getFile(int $id): ?array
    {
        return $this->db->findOne('media', $id) ?: null;
    }

    /**
     * Get a single folder by ID
     */
    public function getFolder(int $id): ?array
    {
        return $this->db->findOne('media_folder', $id) ?: null;
    }

    /**
     * Create a folder
     */
    public function createFolder(string $name, int $parentId = 0): bool
    {
        $name = $this->sanitizeName($name);
        if (!$name) return false;

        // Check if folder already exists
        $exists = $this->db->first('media_folder', ['parent_id' => $parentId, 'name' => $name]);
        if ($exists) return false;

        $this->db->insert('media_folder', [
            'parent_id' => $parentId,
            'name'      => $name,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Create physical folder
        $path = $this->getFolderPath($parentId) . $name . '/';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return true;
    }

    /**
     * Rename a folder
     */
    public function renameFolder(int $id, string $newName): bool
    {
        $folder = $this->getFolder($id);
        if (!$folder) return false;

        $newName = $this->sanitizeName($newName);
        if (!$newName) return false;

        // Check sibling uniqueness
        $exists = $this->db->first('media_folder', [
            'parent_id' => $folder['parent_id'],
            'name'      => $newName,
        ]);
        if ($exists && $exists['id'] != $id) return false;

        // Update DB
        $this->db->update('media_folder', ['name' => $newName], ['id' => $id]);

        // Rename physical folder
        $oldPath = $this->getFolderPath($folder['parent_id']) . $folder['name'] . '/';
        $newPath = $this->getFolderPath($folder['parent_id']) . $newName . '/';
        if (is_dir($oldPath) && !is_dir($newPath)) {
            rename($oldPath, $newPath);
        }

        return true;
    }

    /**
     * Delete a folder (and all its contents)
     */
    public function deleteFolder(int $id): bool
    {
        $folder = $this->getFolder($id);
        if (!$folder) return false;

        // Delete all files in this folder
        $files = $this->getFiles($id);
        foreach ($files as $file) {
            $this->deleteFile($file['id']);
        }

        // Delete subfolders recursively
        $subfolders = $this->getFolders($id);
        foreach ($subfolders as $sub) {
            $this->deleteFolder($sub['id']);
        }

        // Remove physical folder
        $path = $this->getFolderPath($folder['parent_id']) . $folder['name'] . '/';
        if (is_dir($path)) {
            rmdir($path);
        }

        $this->db->delete('media_folder', ['id' => $id]);
        return true;
    }

    /**
     * Upload a file
     */
    public function uploadFile(array $file, int $folderId = 0, ?int $userId = null): array
    {
        $result = ['success' => false, 'message' => '', 'file' => null];

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $result['message'] = 'Upload error: ' . $file['error'];
            return $result;
        }

        if ($file['size'] === 0) {
            $result['message'] = 'File is empty.';
            return $result;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!$this->isAllowedExtension($extension)) {
            $result['message'] = 'File type not allowed.';
            return $result;
        }

        // Generate unique filename
        $filename = $this->generateFilename($file['name']);
        $path = $this->getFolderPath($folderId);
        $fullPath = $path . $filename;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            $result['message'] = 'Failed to move uploaded file.';
            return $result;
        }

        // Get image dimensions if applicable
        $width = $height = null;
        if (in_array($extension, $this->allowedExtensions['image'])) {
            $imageInfo = getimagesize($fullPath);
            if ($imageInfo) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }

        // Insert into DB
        $data = [
            'folder_id'  => $folderId,
            'user_id'    => $userId ?? 0,
            'name'       => pathinfo($file['name'], PATHINFO_FILENAME),
            'filename'   => $filename,
            'path'       => $this->getRelativePath($folderId) . $filename,
            'extension'  => $extension,
            'mime'       => $file['type'] ?: mime_content_type($fullPath),
            'size'       => $file['size'],
            'width'      => $width,
            'height'     => $height,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        $this->db->insert('media', $data);
        $id = $this->db->insert_id();

        $result['success'] = true;
        $result['file'] = $this->getFile($id);
        $result['message'] = 'File uploaded successfully.';

        // Generate thumbnail for images
        if ($width && $height) {
            $this->generateThumbnail($fullPath, $extension);
        }

        return $result;
    }

    /**
     * Delete a file
     */
    public function deleteFile(int $id): bool
    {
        $file = $this->getFile($id);
        if (!$file) return false;

        $fullPath = $this->basePath . $file['path'];
        if (is_file($fullPath)) {
            unlink($fullPath);
        }

        // Delete thumbnail
        $thumbPath = $this->getThumbnailPath($fullPath);
        if (is_file($thumbPath)) {
            unlink($thumbPath);
        }

        $this->db->delete('media', ['id' => $id]);
        return true;
    }

    /**
     * Rename a file
     */
    public function renameFile(int $id, string $newName): bool
    {
        $file = $this->getFile($id);
        if (!$file) return false;

        $newName = $this->sanitizeName($newName);
        if (!$newName) return false;

        // Check if a file with the same name exists in the same folder
        $exists = $this->db->first('media', [
            'folder_id' => $file['folder_id'],
            'name'      => $newName,
        ]);
        if ($exists && $exists['id'] != $id) return false;

        // Update physical file
        $oldPath = $this->basePath . $file['path'];
        $newFilename = $this->generateFilename($newName . '.' . $file['extension'], true);
        $newPath = dirname($oldPath) . '/' . $newFilename;

        if (!is_file($oldPath)) return false;

        if (!rename($oldPath, $newPath)) {
            return false;
        }

        // Update DB
        $this->db->update('media', [
            'name'     => $newName,
            'filename' => $newFilename,
            'path'     => dirname($file['path']) . '/' . $newFilename,
        ], ['id' => $id]);

        return true;
    }

    /**
     * Move file to a different folder
     */
    public function moveFile(int $id, int $newFolderId): bool
    {
        $file = $this->getFile($id);
        if (!$file) return false;

        $newFolder = $this->getFolder($newFolderId);
        if (!$newFolder && $newFolderId !== 0) return false;

        // Update physical location
        $oldPath = $this->basePath . $file['path'];
        $newPath = $this->getFolderPath($newFolderId) . $file['filename'];

        if (!is_file($oldPath)) return false;

        if (!rename($oldPath, $newPath)) {
            return false;
        }

        // Update DB
        $this->db->update('media', [
            'folder_id' => $newFolderId,
            'path'      => $this->getRelativePath($newFolderId) . $file['filename'],
        ], ['id' => $id]);

        return true;
    }

    /**
     * Generate a thumbnail (for images)
     */
    protected function generateThumbnail(string $filePath, string $extension): void
    {
        $thumbPath = $this->getThumbnailPath($filePath);
        $size = 150; // thumbnail size

        // Use GD
        $image = null;
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                $image = imagecreatefromjpeg($filePath);
                break;
            case 'png':
                $image = imagecreatefrompng($filePath);
                break;
            case 'gif':
                $image = imagecreatefromgif($filePath);
                break;
            case 'webp':
                $image = imagecreatefromwebp($filePath);
                break;
            default:
                return;
        }

        if (!$image) return;

        $width = imagesx($image);
        $height = imagesy($image);

        // Calculate thumbnail dimensions (maintain aspect ratio)
        if ($width > $height) {
            $newWidth = $size;
            $newHeight = (int) ($height * ($size / $width));
        } else {
            $newHeight = $size;
            $newWidth = (int) ($width * ($size / $height));
        }

        $thumb = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG/GIF
        if ($extension === 'png' || $extension === 'gif') {
            imagecolortransparent($thumb, imagecolorallocatealpha($thumb, 0, 0, 0, 127));
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save thumbnail
        $thumbDir = dirname($thumbPath);
        if (!is_dir($thumbDir)) {
            mkdir($thumbDir, 0755, true);
        }

        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumb, $thumbPath, 80);
                break;
            case 'png':
                imagepng($thumb, $thumbPath, 8);
                break;
            case 'gif':
                imagegif($thumb, $thumbPath);
                break;
            case 'webp':
                imagewebp($thumb, $thumbPath, 80);
                break;
        }

        imagedestroy($image);
        imagedestroy($thumb);
    }

    protected function getThumbnailPath(string $filePath): string
    {
        $dir = dirname($filePath);
        $filename = basename($filePath);
        return $dir . '/.thumbnails/' . $filename;
    }

    protected function getFolderPath(int $folderId): string
    {
        $path = $this->basePath;
        if ($folderId === 0) return $path;

        $folders = [];
        $current = $this->getFolder($folderId);
        while ($current) {
            $folders[] = $current['name'];
            $current = $this->getFolder($current['parent_id'] ?? 0);
        }
        $folders = array_reverse($folders);
        return $path . implode('/', $folders) . '/';
    }

    protected function getRelativePath(int $folderId): string
    {
        $path = '';
        if ($folderId === 0) return $path;

        $folders = [];
        $current = $this->getFolder($folderId);
        while ($current) {
            $folders[] = $current['name'];
            $current = $this->getFolder($current['parent_id'] ?? 0);
        }
        $folders = array_reverse($folders);
        return implode('/', $folders) . '/';
    }

    protected function sanitizeName(string $name): string
    {
        // Remove any directory traversal and trim
        $name = basename(trim($name));
        $name = preg_replace('/[^a-zA-Z0-9\-_ ]/', '', $name);
        return trim($name);
    }

    protected function generateFilename(string $originalName, bool $keepName = false): string
    {
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $name = $keepName ? pathinfo($originalName, PATHINFO_FILENAME) : uniqid();
        // Ensure filename is safe
        $name = $this->sanitizeName($name);
        $name = $name ?: 'file';
        return $name . '.' . strtolower($extension);
    }

    protected function isAllowedExtension(string $ext): bool
    {
        foreach ($this->allowedExtensions as $group) {
            if (in_array($ext, $group)) return true;
        }
        return false;
    }

    /**
     * Get file URL (for front-end)
     */
    public function getFileUrl(array $file): string
    {
        return $this->baseUrl . $file['path'];
    }

    /**
     * Get thumbnail URL (for images)
     */
    public function getThumbnailUrl(array $file): string
    {
        if (!in_array($file['extension'], $this->allowedExtensions['image'])) {
            return '';
        }
        $thumbPath = dirname($this->basePath . $file['path']) . '/.thumbnails/' . $file['filename'];
        if (is_file($thumbPath)) {
            return $this->baseUrl . dirname($file['path']) . '/.thumbnails/' . $file['filename'];
        }
        return $this->getFileUrl($file);
    }
}