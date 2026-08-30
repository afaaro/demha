<?php

use System\Engine\Controller;
use System\Library\Notify;

class ToolsAdminLogs extends Controller
{
    private const FORM_ID = 'tools_logs_clear_form';
    private const TAIL_BYTES = 150000;

    public function indexAction(): void
    {
        $path = $this->logger->getCurrentLogPath();
        $exists = is_file($path);
        $size = $exists ? (int) filesize($path) : 0;
        $content = $exists ? $this->tailFile($path, self::TAIL_BYTES) : '';
        $truncated = $exists && $size > self::TAIL_BYTES;

        echo $this->view->inline(function ($view) use ($path, $exists, $size, $content, $truncated): void {
            echo '<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-4">';
            echo '<div><h1 class="h3 mb-1">Application Logs</h1>';
            echo '<p class="text-muted mb-0"><code>' . $view->e($path) . '</code>';
            if ($exists) {
                echo ' · ' . $view->e($this->formatBytes($size));
            }
            echo '</p></div>';
            echo '<div class="d-flex gap-2">';
            echo '<a class="btn btn-outline-secondary btn-sm" href="' . route_url('tools/admin/logs') . '">Refresh</a>';
            if ($exists && $size > 0) {
                echo '<form method="post" action="' . route_url('tools/admin/logs/clear') . '" class="d-inline-block">';
                echo csrf_field(self::FORM_ID);
                echo '<button type="submit" class="btn btn-outline-danger btn-sm" onclick="return confirm(\'Clear the application log file?\')">Clear log</button>';
                echo '</form>';
            }
            echo '</div></div>';

            if (!$exists) {
                echo '<div class="alert alert-info mb-0">No log file has been created yet.</div>';
                return;
            }

            if ($truncated) {
                echo '<div class="alert alert-warning">Showing the last ' . $view->e($this->formatBytes(self::TAIL_BYTES)) . ' of this file.</div>';
            }

            if ($content === '') {
                echo '<div class="alert alert-success mb-0">The log file is empty.</div>';
                return;
            }

            echo '<div class="card border-0 shadow-sm"><div class="card-body p-0">';
            echo '<pre class="mb-0 p-3 small" style="max-height:70vh;overflow:auto;white-space:pre-wrap;word-break:break-word;">' . $view->e($content) . '</pre>';
            echo '</div></div>';
        }, 'admin');
    }

    public function clearAction(): void
    {
        if (!$this->request->isPost()) {
            redirect_to('tools/admin/logs');
        }

        if (!$this->form->checkToken(null, self::FORM_ID)) {
            Notify::error('Invalid CSRF token.');
            redirect_to('tools/admin/logs');
        }

        $path = $this->logger->getCurrentLogPath();
        if ($this->logger->clearLog($path)) {
            Notify::success('Log file cleared.');
        } else {
            Notify::error('Could not clear the log file.');
        }

        redirect_to('tools/admin/logs');
    }

    private function tailFile(string $path, int $maxBytes): string
    {
        $size = filesize($path);
        if ($size === false || $size === 0) {
            return '';
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return '';
        }

        $start = max(0, $size - $maxBytes);
        if ($start > 0) {
            fseek($handle, $start);
        }

        $data = (string) stream_get_contents($handle);
        fclose($handle);

        if ($start > 0) {
            $newline = strpos($data, "\n");
            if ($newline !== false) {
                $data = substr($data, $newline + 1);
            }
        }

        return $data;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }
        if ($bytes < 1048576) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return number_format($bytes / 1048576, 1) . ' MB';
    }
}
