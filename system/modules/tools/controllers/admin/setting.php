<?php

use System\Engine\Controller;
use System\Library\Notify;

class ToolsAdminSetting extends Controller
{
    public function indexAction(): void
    {
        $model = $this->load->model('tools/setting');

        $page    = (int) ($this->request->get('page', 'int', 1));
        $search  = $this->request->get('search', 'string', null);
        $group   = $this->request->get('group', 'string', null);

        $filters = [];
        if ($search) $filters['search'] = $search;
        if ($group)  $filters['group']  = $group;

        // ✅ Get pagination result
        $result = $model->getAll($filters, $page, 20);
        $rows   = $result['data'] ?? [];  // Actual rows
        $total  = $result['total'] ?? 0;
        $lastPage = $result['last_page'] ?? 1;

        $groups = $model->getGroups();

        echo $this->view->inline(function () use ($rows, $total, $lastPage, $groups, $filters) {

            echo Notify::read();

            echo "<div class='container'>";
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h3>System Settings</h3>";
            echo "<a class='btn btn-primary' href='" . $this->url->to('tools/admin/setting/create') . "'>Add Setting</a>";
            echo "</div>";

            // Filters form...
            echo "<form method='get' class='row g-3 mb-4 bg-light p-3 rounded'>";
            echo "<div class='col-md-5'>";
            echo "<input type='text' name='search' class='form-control' placeholder='Search name or key...' value='" . escape($filters['search'] ?? '') . "'>";
            echo "</div>";
            echo "<div class='col-md-4'>";
            echo "<select name='group' class='form-select'>";
            echo "<option value=''>All Groups</option>";
            foreach ($groups as $g) {
                $selected = ($filters['group'] ?? '') === $g ? 'selected' : '';
                echo "<option value='" . escape($g) . "' $selected>" . escape(ucwords(str_replace('_', ' ', $g))) . "</option>";
            }
            echo "</select>";
            echo "</div>";
            echo "<div class='col-md-3'>";
            echo "<button type='submit' class='btn btn-secondary w-100'>Filter</button>";
            echo "</div>";
            echo "</form>";

            // Settings Table
            echo "<div class='table-responsive'>";
            echo "<table class='table table-striped table-hover'>";
            echo "<thead class='table-dark'>";
            echo "<tr><th>Key</th><th>Value</th><th>Group</th><th>Type</th><th style='width:140px'>Actions</th></tr>";
            echo "</thead><tbody>";

            if (empty($rows)) {
                echo "<tr><td colspan='5' class='text-center text-muted py-4'>No settings found.</td></tr>";
            } else {
                foreach ($rows as $s) {
                    $idVal    = (int) ($s['id'] ?? 0);
                    $keyVal   = $s['key'] ?? '';
                    $valVal   = $s['value'] ?? '';
                    $groupVal = $s['group'] ?? '-';
                    $typeVal  = $s['type'] ?? 'text';

                    $editUrl = $idVal ? $this->url->to('tools/admin/setting/edit', ['id' => $idVal]) : '';
                    $delUrl  = $idVal ? $this->url->to('tools/admin/setting/delete', ['id' => $idVal]) : '';

                    echo "<tr>";
                    echo "<td><code>" . escape($keyVal) . "</code></td>";
                    echo "<td class='text-truncate' style='max-width:250px'>" . escape(mb_substr($valVal, 0, 60)) . (mb_strlen($valVal) > 60 ? '...' : '') . "</td>";
                    echo "<td>" . escape($groupVal) . "</td>";
                    echo "<td><span class='badge bg-secondary'>" . escape($typeVal) . "</span></td>";
                    echo "<td>";
                    if ($idVal) {
                        echo "<a href='$editUrl' class='btn btn-sm btn-outline-primary'>Edit</a> ";
                        echo "<a href='$delUrl' class='btn btn-sm btn-outline-danger' onclick=\"return confirm('Delete this setting?')\">Del</a>";
                    }
                    echo "</td>";
                    echo "</tr>";
                }
            }

            echo "</tbody></table></div>";

            // Pagination summary
            echo "<div class='d-flex justify-content-between align-items-center mt-3'>";
            echo "<span class='text-muted'>Total: $total settings</span>";
            // Add pagination links here if needed
            echo "</div>";

            echo "</div>";
        }, 'admin');
    }

    public function createAction(): void
    {
        $model = $this->load->model('tools/setting');
        $form  = $this->form;

        if ($this->request->isPost()) {
            $data['key'] = $this->request->post('key');
            $data['value'] = $this->request->post('value');
            $data['group'] = $this->request->post('group');
            $data['type'] = $this->request->post('type');
            $data['description'] = $this->request->post('description');
            
            if ($model->save($data)) {
                Notify::success('Setting created.');
                redirect($this->url->to('tools/admin/setting'));
                return;
            }
            Notify::error('Failed to save.');
        }

        echo $this->view->inline(function () use ($form) {
            echo "<div class='container'><h4>Add Setting</h4><hr>";
            echo $form->open(['method' => 'POST']);

            echo "<fieldset class='border p-3 mb-3 rounded'>";
            echo "<legend class='fw-bold mb-2'>Setting Details</legend>";

            echo $form->input('key', ['label' => 'Config Key', 'required' => true]);
            echo $form->textarea('value', ['label' => 'Value', 'rows' => 4]);
            echo $form->input('group', ['label' => 'Group', 'placeholder' => 'e.g. general']);
            echo $form->select('type', ['text' => 'Text', 'number' => 'Number', 'bool' => 'Yes/No'], ['label' => 'Type']);
            echo $form->textarea('description', ['label' => 'Description', 'rows' => 2]);

            echo "</fieldset>";

            echo "<button type='submit' class='btn btn-primary'>Save</button> ";
            echo "<a href='" . $this->url->to('tools/admin/setting') . "' class='btn btn-secondary'>Cancel</a>";
            echo $form->close();
            echo "</div>";
        }, 'admin');
    }

    public function editAction(): void
    {
        $model = $this->load->model('tools/setting');
        $form  = $this->form;
        $id    = (int) $this->request->get('id', 'int', 0);
        $item  = $model->getById($id);

        if (!$item) {
            Notify::error('Setting not found.');
            redirect($this->url->to('tools/admin/setting'));
            return;
        }

        if ($this->request->isPost()) {
            $data = $this->request->post(['key','value','group','type','description']);
            if ($model->save($data, $id)) {
                Notify::success('Setting updated.');
                redirect($this->url->to('tools/admin/setting'));
                return;
            }
            Notify::error('Failed to update.');
        }

        $form->fill($item);

        echo $this->view->inline(function () use ($form, $item) {
            echo "<div class='container'><h4>Edit Setting: " . escape($item['key']) . "</h4><hr>";
            echo $form->open(['method' => 'POST']);

            echo $form->fieldset('Setting Details', [
                $form->input('key', ['label' => 'Config Key', 'required' => true]),
                $form->textarea('value', ['label' => 'Value', 'rows' => 5]),
                $form->input('group', ['label' => 'Group']),
                $form->select('type', ['text' => 'Text', 'number' => 'Number', 'bool' => 'Yes/No'], ['label' => 'Type']),
                $form->textarea('description', ['label' => 'Description', 'rows' => 2]),
            ]);

            echo "<button type='submit' class='btn btn-primary'>Update</button> ";
            echo "<a href='" . $this->url->to('tools/admin/setting') . "' class='btn btn-secondary'>Cancel</a>";
            echo $form->close();
            echo "</div>";
        }, 'admin');
    }

    public function deleteAction(): void
    {
        $model = $this->load->model('tools/setting');
        $id    = (int) $this->request->get('id', 'int', 0);

        if ($model->delete($id)) {
            Notify::success('Setting deleted.');
        } else {
            Notify::error('Delete failed.');
        }

        redirect($this->url->to('tools/admin/setting'));
    }
}