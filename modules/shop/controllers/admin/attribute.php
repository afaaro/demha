<?php

use System\Engine\Controller;
use System\Library\Notify;

class ShopAdminAttribute extends Controller
{
    public function indexAction(): void
    {
        $groups = $this->db->query("
            SELECT g.*, (SELECT COUNT(id) FROM #__shop_option_value WHERE group_id = g.id) AS total_values 
            FROM #__shop_option_group g 
            ORDER BY g.name ASC
        ")->rows;

        echo $this->view->inline(function ($view) use ($groups) {
            echo Notify::read();
            echo "<div class='d-flex justify-content-between mb-3'>";
            echo "<h3>Product Attributes</h3>";
            echo "<a class='btn btn-primary' href='" . $view->url->to('shop/admin/attribute/add') . "'>Add Attribute Group</a>";
            echo "</div>";

            if (empty($groups)) {
                echo "<div class='alert alert-info'>No attribute groups defined.</div>";
            } else {
                echo "<div class='table-responsive'>";
                echo "<table class='table table-striped'>";
                echo "<thead><tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Values</th>
                    <th>Actions</th>
                </tr></thead>";
                echo "<tbody>";
                foreach ($groups as $g) {
                    echo "<tr>";
                    echo "<td>" . (int)$g['id'] . "</td>";
                    echo "<td><strong>" . htmlspecialchars($g['name']) . "</strong></td>";
                    echo "<td><span class='badge bg-secondary'>" . strtoupper($g['type'] ?? 'SELECT') . "</span></td>";
                    echo "<td>" . (int)($g['total_values'] ?? 0) . " options</td>";
                    echo "<td>";
                    echo "<a class='btn btn-sm btn-outline-primary' href='" . $view->url->to('shop/admin/attribute/edit', ['id' => $g['id']]) . "'><i class='bi bi-pencil'></i></a> ";
                    echo "<a class='btn btn-sm btn-outline-danger' onclick=\"return confirm('Deleting this will remove this attribute from ALL products. Continue?')\" href='" . $view->url->to('shop/admin/attribute/delete', ['id' => $g['id']]) . "'><i class='bi bi-trash'></i></a>";
                    echo "</td>";
                    echo "</tr>";
                }
                echo "</tbody></table>";
                echo "</div>";
            }
        }, 'admin');
    }

    public function addAction(): void
    {
        $this->renderForm();

        if ($this->form->isSubmitted() && $this->form->isValid()) {
            $data = $this->form->validated();

            // Insert new group — consistent table prefix
            $this->db->insert('#__shop_option_group', [
                'name'       => $data['name'],
                'type'       => $data['type'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $id = (int)$this->db->getLastId();

            // Get and process values
            $values = $this->request->post('opt_values', 'array', []);
            $values = array_filter($values, fn($v) => trim((string)$v) !== '');
            $values = array_values($values);

            foreach ($values as $index => $val) {
                $this->db->insert('#__shop_option_value', [
                    'group_id'   => $id,
                    'value'      => trim((string)$val),
                    'sort_order' => $index,
                ]);
            }

            Notify::success('Attribute group added successfully.');
            redirect($this->url->to('shop/admin/attribute'));
        }
    }

    public function editAction(): void
    {
        $id = (int) $this->request->get('id', 'int', 0);
        $group = $this->db->findOne('shop_option_group', $id);

        if (!$group) {
            Notify::error('Attribute group not found.');
            redirect($this->url->to('shop/admin/attribute'));
            return;
        }

        // ✅ FIX: Ensure we ALWAYS get an array, never null
        $values = $this->db->query("
            SELECT * FROM #__shop_option_value
            WHERE group_id = ?
            ORDER BY sort_order ASC
        ", [$id])->rows;
        $values = is_array($values) ? $values : []; // <-- CRITICAL FIX

        if ($this->form->isSubmitted() && $this->form->isValid()) {
            $data = $this->form->validated();

            // Update group
            $this->db->update('shop_option_group', [
                'name'       => $data['name'],
                'type'       => $data['type'],
                'updated_at' => date('Y-m-d H:i:s'),
            ], ['id' => $id]);

            // Get submitted values
            $values = $this->request->post('opt_values', 'array', []);
            $values = array_filter($values, fn($v) => trim((string)$v) !== '');
            $values = array_values($values);

            // Delete old values and insert fresh
            $this->db->delete('shop_option_value', ['group_id' => $id]);
            foreach ($values as $index => $val) {
                $this->db->insert('shop_option_value', [
                    'group_id'   => $id,
                    'value'      => trim((string)$val),
                    'sort_order' => $index,
                ]);
            }

            Notify::success('Attribute group updated successfully.');
            redirect($this->url->to('shop/admin/attribute'));
        }

        $this->renderForm($group, $values);
    }

    public function deleteAction(): void
    {
        $id = (int) $this->request->get('id');
        if (!$id) {
            Notify::error('Invalid ID.');
            redirect($this->url->to('shop/admin/attribute'));
            return;
        }

        // ✅ FIX: Consistent table name with prefix
        $group = $this->db->findOne('shop_option_group', $id);
        if (!$group) {
            Notify::error('Attribute group not found.');
            redirect($this->url->to('shop/admin/attribute'));
            return;
        }

        // Cascade delete (values removed via FK)
        $this->db->delete('shop_option_group', ['id' => $id]);
        Notify::success('Attribute group deleted.');
        redirect($this->url->to('shop/admin/attribute'));
    }

    // ==============================
    // Form Rendering
    // ==============================
    private function renderForm(array $group = [], array $values = []): void
    {
        $this->form->fill($group);
        $isEdit = !empty($group['id']);

        $typeOptions = [
            'select' => 'Dropdown',
            'radio'  => 'Radio Buttons',
            'color'  => 'Color Swatch',
            'text'   => 'Text Input',
        ];

        $valueHtml = $this->renderValueEditor($values);

        echo $this->view->inline(function ($view) use ($isEdit, $group, $typeOptions, $valueHtml) {
            echo Notify::read();
            echo "<div class='d-flex justify-content-between mb-3'>";
            echo "<h3>" . ($isEdit ? 'Edit Attribute Group' : 'Add Attribute Group') . "</h3>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('shop/admin/attribute') . "'>Back to Attributes</a>";
            echo "</div>";

            echo "<div class='card'><div class='card-body'>";
            echo $view->form->start(['method' => 'POST']);

            echo $view->form->input('name', [
                'label'       => 'Attribute Name (e.g., Color)',
                'rules'       => 'required',
                'placeholder' => 'Enter attribute name...',
            ]);

            echo $view->form->select('type', $typeOptions, '', [
                'label' => 'UI Type',
                'rules' => 'required',
            ]);

            echo "<div class='mb-3'>";
            echo "<label class='form-label'>Option Values</label>";
            echo $valueHtml;
            echo "</div>";

            echo "<div class='mt-3'>";
            echo $view->form->submit($isEdit ? 'Update' : 'Add');
            echo "</div>";

            echo $view->form->end();
            echo "</div></div>";

            $view->doc->addInlineJs("
                function addValueField() {
                    const container = document.getElementById('values-list');
                    if (!container) return;
                    const div = document.createElement('div');
                    div.className = 'input-group mb-2';
                    div.innerHTML = `
                        <input type=\"text\" name=\"opt_values[]\" class=\"form-control\" placeholder=\"New Value\">
                        <button type=\"button\" class=\"btn btn-outline-danger\" onclick=\"this.parentElement.remove()\">&times;</button>
                    `;
                    container.appendChild(div);
                }
            ");
        }, 'admin');
    }

    // ==============================
    // Helpers
    // ==============================
    private function renderValueEditor(array $existingValues): string
    {
        $html = '<div id="values-list">';

        if (empty($existingValues)) {
            $html .= $this->valueRow('');
        } else {
            foreach ($existingValues as $v) {
                $val = is_array($v) ? ($v['value'] ?? '') : '';
                $html .= $this->valueRow($val);
            }
        }

        $html .= '</div>';
        $html .= '<button type="button" class="btn btn-sm btn-secondary mt-2" onclick="addValueField()">+ Add Value</button>';
        return $html;
    }

    private function valueRow(string $value = ''): string
    {
        return '<div class="input-group mb-2">
            <input type="text" name="opt_values[]" class="form-control" placeholder="e.g., Small or Red" value="' . htmlspecialchars($value) . '">
            <button type="button" class="btn btn-outline-danger" onclick="this.parentElement.remove()">&times;</button>
        </div>';
    }
}