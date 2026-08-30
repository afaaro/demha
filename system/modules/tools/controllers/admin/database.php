<?php
use System\Engine\Controller;
use System\Library\Notify;

class ToolsAdminDatabase extends Controller
{
    public function indexAction(): void
    {
        echo $this->view->inline(function ($view) {
            echo '<div class="card">';
            echo '  <div class="card-header">';
            echo '    <h4>Database Tools</h4>';
            echo '  </div>';
            echo '  <div class="card-body">';
            echo '    <a class="btn btn-primary me-2" href="' . $view->url->to('tools/admin/database/export') . '">Export Database</a>';
            echo '    <a class="btn btn-warning me-2" href="' . $view->url->to('tools/admin/database/optimize') . '">Optimize Tables</a>';
            echo '    <a class="btn btn-info" href="' . $view->url->to('tools/admin/database/tables') . '">View Tables</a>';
            echo '  </div>';
            echo '</div>';
        }, 'admin');
    }

    public function tablesAction(): void
    {
        $tables = $this->db->query("SHOW TABLE STATUS")->rows;

        echo $this->view->inline(function () use ($tables) {
            echo '<table class="table table-striped">';
            echo '  <thead>';
            echo '    <tr>';
            echo '      <th>Table</th>';
            echo '      <th>Rows</th>';
            echo '      <th>Size</th>';
            echo '    </tr>';
            echo '  </thead>';
            echo '  <tbody>';

            foreach ($tables as $table) {
                echo '<tr>';
                echo '  <td>' . escape($table['Name']) . '</td>';
                echo '  <td>' . (int)$table['Rows'] . '</td>';
                echo '  <td>' . $this->view->formatBytes($table['Data_length'] + $table['Index_length']) . '</td>';
                echo '</tr>';
            }

            echo '  </tbody>';
            echo '</table>';
        }, 'admin');
    }

    public function optimizeAction(): void
    {
        $tables = $this->db->query("SHOW TABLES")->rows;

        foreach ($tables as $table) {
            $name = array_values($table)[0];
            $quoted = $this->db->quoteIdentifier($name);
            $this->db->query("OPTIMIZE TABLE {$quoted}");
        }

        $this->session->success('Database optimized.');
        redirect($this->url->to('tools/admin/database'));
    }

    public function exportAction(): void
    {
        $filename = 'database-' . date('Y-m-d-H-i-s') . '.sql';
        $path = BASEDIR . 'storage/backup/' . $filename;

        if (!is_dir(BASEDIR . 'storage/backup')) {
            mkdir(BASEDIR . 'storage/backup', 0755, true);
        }

        $sql = "-- Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        $tables = $this->db->query("SHOW TABLES")->rows;

        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            $quoted = $this->db->quoteIdentifier($tableName);

            // Drop & Create
            $create = $this->db->query("SHOW CREATE TABLE {$quoted}")->row;
            $sql .= "DROP TABLE IF EXISTS {$quoted};\n";
            $sql .= $create['Create Table'] . ";\n\n";

            // Data
            $rows = $this->db->query("SELECT * FROM {$quoted}")->rows;
            if (!empty($rows)) {
                $columns = array_keys($rows[0]);
                $escapedColumns = array_map([$this->db, 'quoteIdentifier'], $columns);
                $columnList = implode(', ', $escapedColumns);

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($row as $value) {
                        $values[] = $value === null ? 'NULL' : $this->db->quote($value);
                    }
                    $sql .= "INSERT INTO {$quoted} ({$columnList}) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

        $bytes = file_put_contents($path, $sql);
        if ($bytes === false) {
            Notify::error('Failed to export database. Please check permissions and disk space.');
        } else {
            Notify::success('Database exported. Size: ' . $this->view->formatBytes($bytes));
        }

        redirect($this->url->to('tools/admin/database'));
    }

    public function downloadAction(): void
    {
        $file = $this->request->get('file', 'string', '');
        if (empty($file) || str_contains($file, '..') || !preg_match('/^[a-zA-Z0-9_\-\.]+$/', $file)) {
            Notify::error('Invalid file.');
            redirect($this->url->to('tools/admin/database'));
            return;
        }

        $path = BASEDIR . 'storage/backup/' . $file;
        if (!is_file($path)) {
            Notify::error('File not found.');
            redirect($this->url->to('tools/admin/database'));
            return;
        }

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
}