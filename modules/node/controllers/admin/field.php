<?php

use System\Engine\Controller;
use System\Engine\Registry;
use System\Library\Notify;

class NodeAdminField extends Controller
{
    private $bundleModel;
    private $fieldModel;
    private $taxonomyModel;

    public function __construct()
    {
        parent::__construct(Registry::getInstance());
        $this->bundleModel = $this->load->model('node/node_bundle');
        $this->fieldModel = $this->load->model('node/node_field');
        $this->taxonomyModel = $this->load->model('node/taxonomy');
    }

    /**
     * List all fields for a bundle.
     */
    public function indexAction(): void
    {
        $bundle = $this->request->get('bundle', 'raw');
        if (!$bundle) {
            Notify::error('Content type is required.');
            redirect_to('node/admin/bundle');
        }

        $bundleInfo = $this->bundleModel->getBundle($bundle);
        if (!$bundleInfo) {
            Notify::error('Content type not found.');
            redirect_to('node/admin/bundle');
        }

        $fields = $this->fieldModel->getFieldsForBundle($bundle);

        echo $this->view->inline(function ($view) use ($fields, $bundle, $bundleInfo) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Fields — " . htmlspecialchars($bundleInfo['name']) . "</h1>";
            echo "<div>";
            echo "<a class='btn btn-secondary me-1' href='" . $view->url->to('node/admin/bundle') . "'><i class='bi bi-arrow-left me-1'></i>Back</a>";
            echo "<a class='btn btn-primary' href='" . $view->url->to('node/admin/field/create', ['bundle' => $bundle]) . "'><i class='bi bi-plus-lg me-1'></i>Add Field</a>";
            echo "</div></div>";

            if (empty($fields)) {
                echo "<div class='alert alert-info'>No fields defined yet. Add your first field!</div>";
                return;
            }

            echo "<div class='table-responsive'><table class='table table-striped table-hover'>";
            echo "<thead><tr>
                <th>Label</th><th>Machine Name</th><th>Type</th>
                <th>Required</th><th>Settings</th><th>Weight</th><th>Actions</th>
            </tr></thead><tbody>";

            foreach ($fields as $field) {
                $settings = json_decode($field['settings'] ?? '{}', true) ?: [];
                $summary = $this->getSettingsSummary($field['field_type'], $settings);

                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($field['label']) . "</strong></td>";
                echo "<td><code>" . htmlspecialchars($field['field_name']) . "</code></td>";
                echo "<td><span class='badge bg-secondary'>" . htmlspecialchars($field['field_type']) . "</span></td>";
                echo "<td>" . ($field['required'] ? '<span class="text-success">✓</span>' : '<span class="text-muted">—</span>') . "</td>";
                echo "<td><small class='text-muted'>" . htmlspecialchars($summary) . "</small></td>";
                echo "<td>" . (int) $field['weight'] . "</td>";
                echo "<td>
                    <a class='btn btn-sm btn-outline-primary me-1' href='" . $view->url->to('node/admin/field/edit', ['id' => $field['id']]) . "' title='Edit'><i class='bi bi-pencil'></i></a>
                    <a class='btn btn-sm btn-outline-danger' onclick=\"return confirm('Delete this field and ALL its stored data? This cannot be undone!')\" href='" . $view->url->to('node/admin/field/delete', ['id' => $field['id']]) . "' title='Delete'><i class='bi bi-trash'></i></a>
                </td>";
                echo "</tr>";
            }
            echo "</tbody></table></div>";
        }, 'admin');
    }

    /**
     * Create a new field.
     */
    public function createAction(): void
    {
        $bundle = $this->request->get('bundle', 'raw');
        if (!$bundle || !$this->bundleModel->getBundle($bundle)) {
            Notify::error('Invalid content type.');
            redirect_to('node/admin/bundle');
        }

        $this->form->setRules([
            'field_name' => 'required|min:2|max:50|alpha_dash',
            'field_type' => 'required|in:text,textarea,richtext,number,date,image,file,select,checkbox,taxonomy,entity_reference',
            'label'      => 'required|min:2|max:100',
            'weight'     => 'integer',
            'required'   => 'in:0,1',
        ]);

        // ✅ VALIDATE FIRST
        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $data['bundle'] = $bundle;
            $data['required'] = empty($data['required']) ? 0 : 1;

            // Prevent duplicate field name in same bundle
            if ($this->fieldModel->fieldExists($bundle, $data['field_name'])) {
                $this->form->setErrors(['field_name' => 'This field name already exists for this content type.']);
            } else {
                $settings = $this->processSettings($data['field_type'], $_POST);
                $data['settings'] = !empty($settings) ? json_encode($settings) : null;

                $this->fieldModel->saveField($data);
                Notify::success('Field added successfully.');
                redirect_to('node/admin/field', ['bundle' => $bundle]);
            }
        }

        // ✅ RENDER AFTER
        echo $this->view->inline(function ($view) use ($bundle) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Add New Field</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('node/admin/field', ['bundle' => $bundle]) . "'><i class='bi bi-arrow-left me-1'></i>Back</a>";
            echo "</div>";

            echo $this->form->start();
            echo "<input type='hidden' name='bundle' value='" . htmlspecialchars($bundle) . "'>";
            echo $this->form->input('field_name', [
                'label' => 'Machine Name',
                'help'  => 'Lowercase letters, numbers, underscores only. Cannot be changed later.'
            ]);
            echo $this->form->select('field_type', [
                'text'              => 'Text',
                'textarea'          => 'Textarea',
                'richtext'          => 'Rich Text Editor',
                'number'            => 'Number',
                'date'              => 'Date',
                'image'             => 'Image',
                'file'              => 'File',
                'select'            => 'Dropdown Select',
                'checkbox'          => 'Checkbox',
                'taxonomy'          => 'Taxonomy Reference',
                'entity_reference'  => 'Content Reference',
            ], '', ['label' => 'Field Type', 'required' => true, 'id' => 'field_type']);
            echo $this->form->input('label', ['label' => 'Display Label']);
            echo $this->form->checkbox('required', ['label' => 'Required field']);
            echo $this->form->input('weight', ['type' => 'number', 'label' => 'Weight (display order)', 'value' => 0]);

            echo "<div id='settings-container' class='border p-3 rounded mt-3' style='display:none;'>";
            echo "<h5 class='mb-3'>Field Settings</h5>";
            echo $this->renderSettingsFields(null);
            echo "</div>";

            echo $this->form->submit('Create Field', ['class' => 'btn btn-primary']);
            echo $this->form->close();
            echo $this->getSettingsToggleJS();
        }, 'admin');
    }

    /**
     * Edit an existing field.
     */
    public function editAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        $field = $this->fieldModel->getFieldById($id);

        if (!$field) {
            Notify::error('Field not found.');
            redirect_to('node/admin/bundle');
        }

        $settings = json_decode($field['settings'] ?? '{}', true) ?: [];

        $this->form->setRules([
            'label'    => 'required|min:2|max:100',
            'weight'   => 'integer',
            'required' => 'in:0,1',
        ]);

        // ✅ VALIDATE FIRST
        if ($this->form->isValid()) {
            $data = $this->form->validated();
            $data['required'] = empty($data['required']) ? 0 : 1;

            $newSettings = $this->processSettings($field['field_type'], $_POST);
            $data['settings'] = empty($newSettings) ? null : json_encode($newSettings);

            $this->fieldModel->updateField($id, $data);
            Notify::success('Field updated.');
            redirect_to('node/admin/field', ['bundle' => $field['bundle']]);
        }

        // ✅ FILL AND RENDER AFTER
        $this->form->fill($field);

        echo $this->view->inline(function ($view) use ($field, $settings) {
            echo "<div class='d-flex justify-content-between align-items-center mb-3'>";
            echo "<h1>Edit Field</h1>";
            echo "<a class='btn btn-secondary' href='" . $view->url->to('node/admin/field', ['bundle' => $field['bundle']]) . "'><i class='bi bi-arrow-left me-1'></i>Back</a>";
            echo "</div>";

            echo $this->form->start();
            echo "<div class='alert alert-info mb-3'>
                <strong>Machine Name:</strong> <code>" . htmlspecialchars($field['field_name']) . "</code> |
                <strong>Type:</strong> <span class='badge bg-secondary'>" . htmlspecialchars($field['field_type']) . "</span>
                <small class='text-muted'>(fixed)</small>
            </div>";

            echo $this->form->input('label', ['label' => 'Display Label']);
            echo $this->form->checkbox('required', ['label' => 'Required field']);
            echo $this->form->input('weight', ['type' => 'number', 'label' => 'Weight (display order)']);

            echo "<div id='settings-container' class='border p-3 rounded mt-3'>";
            echo "<h5 class='mb-3'>Field Settings</h5>";
            echo $this->renderSettingsFields($field['field_type'], $settings);
            echo "</div>";

            echo $this->form->submit('Update Field', ['class' => 'btn btn-primary']);
            echo $this->form->close();
            echo $this->getSettingsToggleJS();
        }, 'admin');
    }

    /**
     * Delete a field.
     */
    public function deleteAction(): void
    {
        $id = (int) $this->request->get('id', 'int');
        $field = $this->fieldModel->getFieldById($id);

        if (!$field) {
            Notify::error('Field not found.');
            redirect_to('node/admin/bundle');
        }

        $this->fieldModel->deleteField($id, $field['field_name']);
        Notify::success('Field deleted. All associated values have been removed.');
        redirect_to('node/admin/field', ['bundle' => $field['bundle']]);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function renderSettingsFields(?string $fieldType = null, array $settings = []): string
    {
        $html = '';

        // Common settings
        $html .= "<div class='row mb-3'>";
        $html .= "<div class='col-md-6'>" . $this->form->input('settings[default_value]', [
            'label' => 'Default Value',
            'value' => $settings['default_value'] ?? '',
            'help'  => 'Optional default value'
        ]) . "</div>";
        $html .= "<div class='col-md-6'>" . $this->form->input('settings[placeholder]', [
            'label' => 'Placeholder Text',
            'value' => $settings['placeholder'] ?? '',
            'help'  => 'Input placeholder hint'
        ]) . "</div>";
        $html .= "</div>";

        // Select
        $html .= "<div class='type-settings' data-type='select'>
            <div class='mb-3'>
                <label class='form-label'>Options</label>
                <textarea name='settings[options_text]' class='form-control' rows='5' placeholder='key: Label text'>";
        if (!empty($settings['options']) && is_array($settings['options'])) {
            $html .= htmlspecialchars(implode("\n", array_map(fn($k,$v) => "$k: $v", array_keys($settings['options']), $settings['options'])));
        }
        $html .= "</textarea>
                <div class='form-text'>One per line: <code>key: Label</code></div>
            </div>
        </div>";

        // Taxonomy
        $vocabs = $this->db->query("SELECT * FROM #__node_taxonomy_vocabularies ORDER BY name")->rows;
        $opts = array_column($vocabs, 'name', 'machine_name');
        $html .= "<div class='type-settings' data-type='taxonomy'>" .
            $this->form->select('settings[vocabulary]', $opts, $settings['vocabulary'] ?? '', ['label' => 'Vocabulary']) .
        "</div>";

        // Entity Reference
        $bundles = $this->bundleModel->getBundles();
        $opts = array_column($bundles, 'name', 'machine_name');
        $html .= "<div class='type-settings' data-type='entity_reference'>" .
            $this->form->select('settings[target_bundle]', $opts, $settings['target_bundle'] ?? '', ['label' => 'Target Content Type']) .
        "</div>";

        // Text
        $html .= "<div class='type-settings' data-type='text'>" . $this->form->input('settings[max_length]', [
            'type' => 'number', 'label' => 'Max Length', 'value' => $settings['max_length'] ?? ''
        ]) . "</div>";

        // Number
        $html .= "<div class='type-settings' data-type='number'><div class='row'>
            <div class='col-md-4'>" . $this->form->input('settings[min]', ['type' => 'number', 'label' => 'Min', 'value' => $settings['min'] ?? '']) . "</div>
            <div class='col-md-4'>" . $this->form->input('settings[max]', ['type' => 'number', 'label' => 'Max', 'value' => $settings['max'] ?? '']) . "</div>
            <div class='col-md-4'>" . $this->form->input('settings[step]', ['type' => 'number', 'label' => 'Step', 'value' => $settings['step'] ?? 1]) . "</div>
        </div></div>";

        // Image
        $html .= "<div class='type-settings' data-type='image'><div class='row'>
            <div class='col-md-6'>" . $this->form->input('settings[file_extensions]', ['label' => 'Allowed Types', 'value' => $settings['file_extensions'] ?? 'jpg,jpeg,png,gif,svg,webp']) . "</div>
            <div class='col-md-6'>" . $this->form->input('settings[max_file_size]', ['type' => 'number', 'label' => 'Max Size (KB)', 'value' => $settings['max_file_size'] ?? 2048]) . "</div>
        </div></div>";

        // File
        $html .= "<div class='type-settings' data-type='file'><div class='row'>
            <div class='col-md-6'>" . $this->form->input('settings[file_extensions]', ['label' => 'Allowed Extensions', 'value' => $settings['file_extensions'] ?? 'pdf,doc,docx,xls,xlsx,zip']) . "</div>
            <div class='col-md-6'>" . $this->form->input('settings[max_file_size]', ['type' => 'number', 'label' => 'Max Size (KB)', 'value' => $settings['max_file_size'] ?? 5120]) . "</div>
        </div></div>";

        // Date
        $html .= "<div class='type-settings' data-type='date'>" . $this->form->input('settings[format]', [
            'label' => 'Date Format', 'value' => $settings['format'] ?? 'Y-m-d'
        ]) . "</div>";

        return $html;
    }

    private function processSettings(string $fieldType, array $post): array
    {
        $s = [];
        if (!empty($post['settings']['default_value'])) $s['default_value'] = $post['settings']['default_value'];
        if (!empty($post['settings']['placeholder'])) $s['placeholder'] = $post['settings']['placeholder'];

        switch ($fieldType) {
            case 'select':
                if (!empty($post['settings']['options_text'])) {
                    foreach (explode("\n", trim($post['settings']['options_text'])) as $line) {
                        $parts = strpos($line, ':') !== false ? explode(':', $line, 2) : [trim($line), trim($line)];
                        if (trim($parts[0]) !== '') $s['options'][trim($parts[0])] = trim($parts[1] ?? $parts[0]);
                    }
                }
                break;
            case 'taxonomy':
                if (!empty($post['settings']['vocabulary'])) $s['vocabulary'] = $post['settings']['vocabulary'];
                break;
            case 'entity_reference':
                if (!empty($post['settings']['target_bundle'])) $s['target_bundle'] = $post['settings']['target_bundle'];
                break;
            case 'text':
                if ($post['settings']['max_length'] ?? '') $s['max_length'] = (int) $post['settings']['max_length'];
                break;
            case 'number':
                if (($v = $post['settings']['min'] ?? '') !== '') $s['min'] = (float) $v;
                if (($v = $post['settings']['max'] ?? '') !== '') $s['max'] = (float) $v;
                if (($v = $post['settings']['step'] ?? '') !== '') $s['step'] = (float) $v;
                break;
            case 'image':
            case 'file':
                if (!empty($post['settings']['file_extensions'])) $s['file_extensions'] = preg_replace('/[\s,]+/', ',', trim($post['settings']['file_extensions']));
                if (!empty($post['settings']['max_file_size'])) $s['max_file_size'] = (int) $post['settings']['max_file_size'];
                break;
            case 'date':
                if (!empty($post['settings']['format'])) $s['format'] = $post['settings']['format'];
                break;
        }
        return $s;
    }

    private function getSettingsSummary(string $type, array $s): string
    {
        return match ($type) {
            'select' => !empty($s['options']) ? count($s['options']) . ' options' : 'No options',
            'taxonomy' => $s['vocabulary'] ?? '—',
            'entity_reference' => $s['target_bundle'] ?? '—',
            'text' => isset($s['max_length']) ? "Max {$s['max_length']} chars" : 'No limit',
            'number' => implode(', ', array_filter([
                isset($s['min']) ? "Min {$s['min']}" : '',
                isset($s['max']) ? "Max {$s['max']}" : '',
                isset($s['step']) ? "Step {$s['step']}" : ''
            ])) ?: 'No limits',
            'image', 'file' => implode(', ', array_filter([
                $s['file_extensions'] ?? '',
                isset($s['max_file_size']) ? "{$s['max_file_size']}KB" : ''
            ])) ?: 'Defaults',
            'date' => $s['format'] ?? 'Y-m-d',
            default => ''
        };
    }

    private function getSettingsToggleJS(): string
    {
        return <<<'JS'
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const sel=document.getElementById('field_type'), container=document.getElementById('settings-container');
    if(!sel||!container) return;
    const types=['select','taxonomy','entity_reference','text','number','image','file','date'];
    const panels=document.querySelectorAll('.type-settings');
    function toggle(){
        const t=sel.value;
        container.style.display=types.includes(t)?'block':'none';
        panels.forEach(p=>p.style.display=p.dataset.type===t?'block':'none');
    }
    sel.addEventListener('change',toggle);
    toggle();
});
</script>
JS;
    }
}