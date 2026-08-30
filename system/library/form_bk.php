<?php
namespace System\Library;

use RuntimeException;
use System\Engine\Registry;

class Form
{
    private Registry $registry;
    private Request $request;
    private Session $session;
    private ?Database $db = null;
    private Document $doc;

    private array $rules = [];
    private array $messages = [];
    private array $errors = [];
    private array $values = [];

    private array $validated = [];
    private array $submittedData = [];

    private string $method = 'POST';
    private string $action = '';
    private string $formId = 'form-default';
    
    private array $flashedErrors = [];

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->request = $registry->get('request');
        $this->session = $registry->get('session');
        $this->doc = $registry->get('doc');
        
        // Load flashed errors on construction
        $this->flashedErrors = (array) $this->session->get('_form_errors', []);

        if ($registry->has('db')) {
            $this->db = $registry->get('db');
        }
    }

    public function start(array $attrs = []): string
    {
        $this->formId = (string) ($attrs['id'] ?? $this->formId);
        $this->method = strtoupper((string) ($attrs['method'] ?? $this->method));
        $this->action = (string) ($attrs['action'] ?? $this->action);

        $defaults = [
            'id' => $this->formId,
            'method' => $this->method,
            'action' => $this->action,
            'class' => 'needs-validation',
            'novalidate' => 'novalidate',
        ];

        $attributes = array_merge($defaults, $attrs);

        $html = '<form ' . $this->buildAttributes($attributes) . '>';
        if (strtoupper($this->method) !== 'GET') {
            $html .= $this->csrfField($this->formId);
        }

        return $html;
    }

    public function open(array $attrs = []): string
    {
        return $this->start($attrs);
    }

    public function end(): string
    {
        return '</form>';
    }

    public function close(): string
    {
        return $this->end();
    }

    public function setRules(array $rules, array $messages = []): self
    {
        $this->rules = $rules;
        $this->messages = $messages;
        return $this;
    }

    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    public function errors(): array
    {
        return array_merge($this->errors, $this->flashedErrors);
    }

    public function error(string $field): ?string
    {
        // Check current errors first
        if (isset($this->errors[$field])) {
            return (string) $this->errors[$field];
        }
        
        // Then check flashed errors
        if (isset($this->flashedErrors[$field])) {
            return (string) $this->flashedErrors[$field];
        }
        
        return null;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) || isset($this->flashedErrors[$field]);
    }

    public function fill(array|object $values): self
    {
        $this->values = (array) $values;
        return $this;
    }

    public function bind(array|object $values): self
    {
        return $this->fill($values);
    }

    public function oldInput(string $key, mixed $default = ''): mixed
    {
        $old = (array) $this->session->get('_old_input', []);
        if (array_key_exists($key, $old)) {
            return $old[$key];
        }

        return $this->request->post($key, 'raw', $default);
    }

    public function flashInput(array $input): void
    {
        $this->session->set('_old_input', $input);
    }

    public function clearOldInput(): void
    {
        $this->session->delete('_old_input');
    }

    public function flashErrors(array $errors): void
    {
        $this->session->set('_form_errors', $errors);
        $this->flashedErrors = $errors;
    }

    public function pullErrors(): array
    {
        return (array) $this->session->pull('_form_errors', []);
    }
    
    public function getFlashedErrors(): array
    {
        return $this->flashedErrors;
    }
    
    public function clearFlashedErrors(): void
    {
        $this->session->delete('_form_errors');
        $this->flashedErrors = [];
    }

    /**
     * Generate a CSRF hidden input field.
     */
    public function csrfField(?string $formId = null): string
    {
        $id = $formId !== null && $formId !== '' ? $formId : $this->formId;
        $token = bin2hex(random_bytes(32));
        $bucket = '_csrf.' . $id;

        $tokens = (array) $this->session->get($bucket, []);
        $tokens = array_filter($tokens, fn($expiry) => $expiry >= time());

        // store new token
        $tokens[$token] = time() + 3600;

        $this->session->set($bucket, $tokens);

        return '<input type="hidden" name="_token" value="' . $this->e($token) . '">';
    }

    /**
     * Get the current CSRF token without generating a hidden field.
     * Creates a new token if one doesn't exist.
     */
    public function getToken(?string $formId = null): string
    {
        $id = $formId !== null && $formId !== '' ? $formId : $this->formId;
        $bucket = '_csrf.' . $id;
        $tokens = (array) $this->session->get($bucket, []);
        
        // Filter out expired tokens first
        $tokens = array_filter($tokens, fn($expiry) => $expiry >= time());
        
        if (empty($tokens)) {
            $token = bin2hex(random_bytes(32));
            $tokens[$token] = time() + 3600;
            $this->session->set($bucket, $tokens);
            return $token;
        }
        
        $this->session->set($bucket, $tokens);
        $keys = array_keys($tokens);
        return $keys[0];
    }

    /**
     * Validate a CSRF token.
     */
    public function checkToken(?string $token = null, ?string $formId = null): bool
    {
        $id = $formId !== null && $formId !== '' ? $formId : $this->formId;
        $token = $token ?? (string) $this->request->post('_token', 'raw', '');

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }

        $bucket = '_csrf.' . $id;
        $tokens = (array) $this->session->get($bucket, []);
        $expiry = (int) ($tokens[$token] ?? 0);

        if ($expiry < time()) {
            unset($tokens[$token]);
            $this->session->set($bucket, $tokens);
            return false;
        }

        // One-time token
        unset($tokens[$token]);
        $this->session->set($bucket, $tokens);

        return true;
    }

    public function data(): array
    {
        $data = [];

        foreach (array_keys($this->rules) as $field) {
            $value = $this->getRequestValue($field, null);

            if ($value !== null) {
                $data[$field] = $value;
            }
        }

        return $data;
    }

    private function getRequestValue(string $field, mixed $default = null): mixed
    {
        return $this->request->post($field, 'raw', $default);
    }

    public function isSubmitted(): bool
    {
        return match ($this->method) {
            'POST' => $this->request->isPost(),
            'GET'  => $this->request->isGet(),
            default => false,
        };
    }

    public function isValid(): bool
    {
        if (!$this->isSubmitted()) {
            return false;
        }

        $this->errors = [];
        $this->validated = [];
        $this->submittedData = [];

        if ($this->method !== 'GET' && !$this->checkToken()) {
            $this->errors = [
                '_token' => 'Your session has expired. Please try again.',
            ];

            $this->flashErrors($this->errors);

            return false;
        }

        $this->submittedData = $this->data();

        $this->validate($this->submittedData);

        if (!empty($this->errors)) {
            return false;
        }

        $this->validated = $this->submittedData;

        return true;
    }

    public function validated(?string $field = null, mixed $default = null): mixed
    {
        if ($field === null) {
            return $this->validated;
        }

        return $this->validated[$field] ?? $default;
    }

    public function submitted(?string $field = null, mixed $default = null): mixed
    {
        if ($field === null) {
            return $this->submittedData;
        }

        return $this->submittedData[$field] ?? $default;
    }

    public function validate(array $data, ?array $rules = null, ?array $messages = null): array
    {
        if ($rules !== null) {
            $this->rules = $rules;
        }

        if ($messages !== null) {
            $this->messages = $messages;
        }

        $errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            $fieldRules = is_array($ruleSet) ? $ruleSet : explode('|', (string) $ruleSet);
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                $ruleName = (string) $rule;
                $params = [];

                if (str_contains($ruleName, ':')) {
                    [$ruleName, $paramString] = explode(':', $ruleName, 2);
                    $params = array_map('trim', explode(',', $paramString));
                }

                $ruleName = strtolower(trim($ruleName));
                $messageKey = $field . '.' . $ruleName;
                $customMessage = $this->messages[$messageKey] ?? null;

                if ($ruleName === 'nullable' && ($value === null || $value === '')) {
                    break;
                }

                $failed = $this->failsRule($ruleName, $value, $params, $data, $field);
                if ($failed) {
                    $errors[$field] = $customMessage ?? $this->defaultMessage($field, $ruleName, $params);
                    break;
                }
            }
        }

        $this->errors = $errors;

        if (!empty($errors)) {
            $this->flashInput($data);
            $this->flashErrors($errors);
        } else {
            $this->clearOldInput();
            $this->session->delete('_form_errors');
            $this->flashedErrors = [];
        }

        return $errors;
    }

    public function input(string $name, array $options = []): string
    {
        $this->registerFieldRules($name, $options);
        $type = (string) ($options['type'] ?? 'text');
        $label = (string) ($options['label'] ?? $this->humanize($name));
        $placeholder = (string) ($options['placeholder'] ?? '');
        $readonly = !empty($options['readonly']);
        $disabled = !empty($options['disabled']);
        
        // Build class with error handling
        $classes = ['form-control'];
        if (!empty($options['class'])) {
            $classes[] = (string) $options['class'];
        }
        if ($this->hasError($name)) {
            $classes[] = 'is-invalid';
        }
        $class = implode(' ', array_unique($classes));

        $value = $this->resolveValue($name, $options['value'] ?? null);
        
        $control = '<input type="' . $this->e($type) . '" name="' . $this->e($name) . '" id="' . $this->e((string) ($options['id'] ?? $name)) . '"'
            . ' value="' . $this->e(is_scalar($value) ? (string) $value : '') . '"'
            . ' class="' . $this->e($class) . '"'
            . ($placeholder !== '' ? ' placeholder="' . $this->e($placeholder) . '"' : '')
            . ($readonly ? ' readonly' : '')
            . ($disabled ? ' disabled' : '')
            . $this->extraAttributes($options)
            . '>';
            
        // Add inline error message if field has error
        $error = $this->error($name);
        if ($error !== null) {
            $control .= '<div class="invalid-feedback d-block">' . $this->e($error) . '</div>';
        }

        return $this->renderField($name, $label, $control, $options);
    }

    public function textarea(string $name, array $options = []): string
    {
        $this->registerFieldRules($name, $options);
        $label = (string) ($options['label'] ?? $this->humanize($name));
        $rows = (int) ($options['rows'] ?? 4);
        $readonly = !empty($options['readonly']);
        $disabled = !empty($options['disabled']);
        $editorEnabled = !empty($options['editor']) && $options['editor'] === true;

        // Build control classes
        $classes = ['form-control'];
        if (!empty($options['class'])) {
            $classes[] = (string) $options['class'];
        }
        if ($this->hasError($name)) {
            $classes[] = 'is-invalid';
        }
        if ($editorEnabled) {
            $classes[] = 'js-tinymce-editor';
            $this->registerTinyMce();
        }

        $class = implode(' ', array_unique($classes));
        $value = $this->resolveValue($name, $options['value'] ?? null);

        $control = '<textarea name="' . $this->e($name) . '" id="' . $this->e((string) ($options['id'] ?? $name)) . '" rows="' . $rows . '"'
            . ' class="' . $this->e($class) . '"'
            . ($readonly ? ' readonly' : '')
            . ($disabled ? ' disabled' : '')
            . $this->extraAttributes($options)
            . '>' . $this->e(is_scalar($value) ? (string) $value : '') . '</textarea>';
        
        // Add inline error message if field has error
        $error = $this->error($name);
        if ($error !== null) {
            $control .= '<div class="invalid-feedback d-block">' . $this->e($error) . '</div>';
        }

        return $this->renderField($name, $label, $control, $options);
    }

    /**
     * Register global TinyMCE assets once per request.
     */
    private function registerTinyMce(): void
    {
        static $registered = false;

        if ($registered) {
            return;
        }

        $registered = true;
        $pickerUrl = $this->registry->get('url')->to('tools/admin/media/picker');

        $this->doc->addJs('https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js');

        $this->doc->addInlineJs(<<<JS
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof tinymce === "undefined") {
                    return;
                }

                tinymce.init({
                    selector: 'textarea.js-tinymce-editor',
                    height: 350,
                    menubar: false,
                    plugins: 'link lists code table',
                    toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | code | media',
                    skin: 'oxide',
                    content_css: 'default',
                    setup: function(editor) {
                        editor.ui.registry.addButton('media', {
                            text: 'Media',
                            icon: 'image',
                            onAction: function() {
                                editor.windowManager.open({
                                    title: 'Media Picker',
                                    width: 800,
                                    height: 600,
                                    body: {
                                        type: 'panel',
                                        items: [
                                            {
                                                type: 'htmlpanel',
                                                html: '<iframe src="{$pickerUrl}" style="width:100%;height:100%;border:0;"></iframe>'
                                            }
                                        ]
                                    },
                                    buttons: [
                                        {
                                            type: 'cancel',
                                            text: 'Close'
                                        }
                                    ]
                                });
                            }
                        });
                    }
                });
            });
        JS);
    }

    public function select(string $name, array|object $items = [], mixed $selected = null, array $options = []): string
    {
        $this->registerFieldRules($name, $options);
        if (isset($options['options']) && is_array($options['options'])) {
            $items = $options['options'];
        }

        $items = (array) $items;
        $label = (string) ($options['label'] ?? $this->humanize($name));
        $multiple = !empty($options['multiple']);
        $disabled = !empty($options['disabled']);
        
        // Get blank/placeholder text
        $blank = $options['blank'] ?? $options['placeholder'] ?? null;
        unset($options['blank'], $options['placeholder']);

        $selectedValue = $selected;
        if ($selectedValue === null) {
            $selectedValue = $this->resolveValue($name, $options['value'] ?? null);
        }

        $selectedList = is_array($selectedValue) ? $selectedValue : [(string) $selectedValue];

        // Build class with error handling
        $classes = ['form-select'];
        if (!empty($options['class'])) {
            $classes[] = (string) $options['class'];
        }
        if ($this->hasError($name)) {
            $classes[] = 'is-invalid';
        }
        $class = implode(' ', array_unique($classes));
        
        $fieldName = $multiple ? $name . '[]' : $name;
        if (!$multiple) {
            $html = '<input type="hidden" name="' . $this->e($name) . '" value="">';
        } else {
            $html = '';
        }

        $html .= '<select name="' . $this->e($fieldName) . '" id="' . $this->e((string) ($options['id'] ?? $name)) . '"'
            . ' class="' . $this->e($class) . '"'
            . ($multiple ? ' multiple' : '')
            . ($disabled ? ' disabled' : '')
            . $this->extraAttributes($options)
            . '>';

        // Add blank option if specified
        if ($blank !== null) {
            $blankValue = isset($options['blank_value']) ? (string) $options['blank_value'] : '';
            $isSelected = in_array($blankValue, array_map('strval', $selectedList), true);
            $html .= '<option value="' . $this->e($blankValue) . '"' . ($isSelected ? ' selected' : '') . '>'
                . $this->e((string) $blank)
                . '</option>';
        }

        foreach ($items as $key => $text) {
            if (is_array($text)) {
                $html .= '<optgroup label="' . $this->e((string) $key) . '">';
                foreach ($text as $subKey => $subText) {
                    $isSelected = in_array((string) $subKey, array_map('strval', $selectedList), true);
                    $html .= '<option value="' . $this->e((string) $subKey) . '"' . ($isSelected ? ' selected' : '') . '>'
                        . $this->e((string) $subText) . '</option>';
                }
                $html .= '</optgroup>';
                continue;
            }

            $isSelected = in_array((string) $key, array_map('strval', $selectedList), true);
            $html .= '<option value="' . $this->e((string) $key) . '"' . ($isSelected ? ' selected' : '') . '>'
                . $this->e((string) $text) . '</option>';
        }

        $html .= '</select>';
        
        // Add inline error message if field has error
        $error = $this->error($name);
        if ($error !== null) {
            $html .= '<div class="invalid-feedback d-block">' . $this->e($error) . '</div>';
        }

        return $this->renderField($name, $label, $html, $options);
    }

    public function checkbox(string $name, array $options = []): string
    {
        $this->registerFieldRules($name, $options);
        $value = (string) ($options['value'] ?? '1');
        $label = (string) ($options['label'] ?? $this->humanize($name));
        $checkedByDefault = !empty($options['checked']);
        $actual = $this->resolveValue($name, $checkedByDefault ? $value : null);

        $checked = false;
        if (is_array($actual)) {
            $checked = in_array($value, array_map('strval', $actual), true);
        } elseif ($actual !== null) {
            $checked = (string) $actual === $value;
        }

        // Build class with error handling
        $classes = ['form-check-input'];
        if (!empty($options['class'])) {
            $classes[] = (string) $options['class'];
        }
        if ($this->hasError($name)) {
            $classes[] = 'is-invalid';
        }
        $class = implode(' ', array_unique($classes));
        
        $id = (string) ($options['id'] ?? ($name . '_' . $value));

        $html = '<div class="form-check">';
        if (empty($options['no_hidden'])) {
            $html .= '<input type="hidden" name="' . $this->e($name) . '" value="0">';
        }
        $html .= '<input type="checkbox" class="' . $this->e($class) . '" id="' . $this->e($id) . '"'
            . ' name="' . $this->e($name) . '" value="' . $this->e($value) . '"'
            . ($checked ? ' checked' : '')
            . (!empty($options['disabled']) ? ' disabled' : '')
            . $this->extraAttributes($options)
            . '>';
        $html .= '<label class="form-check-label" for="' . $this->e($id) . '">' . $this->e($label) . '</label>';
        
        // Add inline error message if field has error
        $error = $this->error($name);
        if ($error !== null) {
            $html .= '<div class="invalid-feedback d-block">' . $this->e($error) . '</div>';
        }
        
        $html .= '</div>';

        return $this->renderField($name, '', $html, $options);
    }

    public function hidden(
        string $name,
        mixed $value = '',
        array $options = []
    ): string {
        $this->registerFieldRules($name, $options);

        return '<input type="hidden"'
            . ' name="' . $this->e($name) . '"'
            . ' value="' . $this->e((string) $value) . '"'
            . $this->extraAttributes($options)
            . '>';
    }

    public function submit(string $text = 'Submit', array $options = []): string
    {
        $class = trim((string) ($options['class'] ?? 'btn btn-primary'));
        unset($options['class']);

        return '<button type="submit" class="' . $this->e($class) . '" ' . $this->buildAttributes($options) . '>'
            . $this->e($text)
            . '</button>';
    }

    /**
     * Render field wrapper with label and help text
     */
    private function renderField(string $name, string $label, string $control, array $options): string
    {
        $wrapperClass = (string) ($options['wrapper_class'] ?? 'mb-3');
        $help = (string) ($options['help'] ?? '');
        
        // Add error class to wrapper
        if ($this->hasError($name)) {
            $wrapperClass .= ' has-error';
        }

        $withInputGroup = isset($options['prefix']) || isset($options['suffix']);
        if ($withInputGroup) {
            $control = $this->wrapInputGroup(
                $control,
                isset($options['prefix']) ? (string) $options['prefix'] : '',
                isset($options['suffix']) ? (string) $options['suffix'] : ''
            );
        }

        $html = '<div class="' . $this->e($wrapperClass) . '">';

        if ($label !== '') {
            $html .= '<label class="form-label" for="' . $this->e((string) ($options['id'] ?? $name)) . '">' . $this->e($label) . '</label>';
        }

        $html .= $control;

        // Help text (not error) - only show if no error
        if ($help !== '' && !$this->hasError($name)) {
            $html .= '<div class="form-text">' . $this->e($help) . '</div>';
        }

        $html .= '</div>';

        return $html;
    }

    private function wrapInputGroup(string $control, string $prefix, string $suffix): string
    {
        $html = '<div class="input-group">';
        if ($prefix !== '') {
            $html .= '<span class="input-group-text">' . $this->e($prefix) . '</span>';
        }

        $html .= $control;

        if ($suffix !== '') {
            $html .= '<span class="input-group-text">' . $this->e($suffix) . '</span>';
        }

        $html .= '</div>';
        return $html;
    }

    private function resolveValue(string $name, mixed $fallback): mixed
    {
        if ($this->request->isPost()) {
            $posted = $this->request->post($name, 'raw', null);
            if ($posted !== null) {
                return $posted;
            }
        }

        $old = (array) $this->session->get('_old_input', []);
        if (array_key_exists($name, $old)) {
            return $old[$name];
        }

        if (array_key_exists($name, $this->values)) {
            return $this->values[$name];
        }

        return $fallback;
    }

    private function failsRule(string $rule, mixed $value, array $params, array $allData, string $field): bool
    {
        return match ($rule) {
            'required' => $value === null || $value === '' || (is_array($value) && empty($value)),
            'email' => $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL),
            'url' => $value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL),
            'alpha' => $value !== null && $value !== '' && !preg_match('/^[a-zA-Z]+$/', (string) $value),
            'alnum' => $value !== null && $value !== '' && !preg_match('/^[a-zA-Z0-9]+$/', (string) $value),
            'alpha_dash' => $value !== null && $value !== '' && !preg_match('/^[a-zA-Z0-9_-]+$/', (string) $value),
            'numeric' => $value !== null && $value !== '' && !is_numeric($value),
            'integer' => $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false,
            'boolean' => $value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) === null,
            'min' => $this->length($value) < (int) ($params[0] ?? 0),
            'max' => $this->length($value) > (int) ($params[0] ?? PHP_INT_MAX),
            'in' => $value !== null && $value !== '' && !in_array((string) $value, array_map('strval', $params), true),
            'match' => (string) $value !== (string) ($allData[$params[0] ?? ''] ?? ''),
            'different' => (string) $value === (string) ($allData[$params[0] ?? ''] ?? ''),
            'regex' => $value !== null && $value !== '' && !preg_match((string) ($params[0] ?? '//'), (string) $value),
            'array' => $value !== null && !is_array($value),
            'exists' => !$this->validateExists($value, $params),
            'unique' => !$this->validateUnique($value, $params),
            'accepted' => !in_array((string) $value, ['1', 'true', 'on', 'yes'], true),
            'checked' => !in_array((string) $value, ['1', 'true', 'on', 'yes'], true),
            default => false,
        };
    }

    private function validateExists(mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if ($this->db === null) {
            throw new RuntimeException("Validation rule 'exists' requires database service.");
        }

        $table = $this->sanitizeIdentifier((string) ($params[0] ?? ''), true);
        $column = $this->sanitizeIdentifier((string) ($params[1] ?? 'id'));
        if ($table === '') {
            return false;
        }

        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $this->db->table($table) . ' WHERE `' . $column . '` = ?';
        $count = (int) ($this->db->query($sql, [$value])->row['cnt'] ?? 0);

        // Returns TRUE if the record exists in the database
        return $count > 0;
    }

    private function validateUnique(mixed $value, array $params): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        if ($this->db === null) {
            throw new RuntimeException("Validation rule 'unique' requires database service.");
        }

        $table = $this->sanitizeIdentifier((string) ($params[0] ?? ''), true);
        $column = $this->sanitizeIdentifier((string) ($params[1] ?? 'id'));
        $except = $params[2] ?? null;
        $exceptColumn = $this->sanitizeIdentifier((string) ($params[3] ?? 'id'));

        if ($table === '') {
            return false;
        }

        $sql = 'SELECT COUNT(*) AS cnt FROM ' . $this->db->table($table) . ' WHERE `' . $column . '` = ?';
        $bind = [$value];

        if ($except !== null && $except !== '') {
            $sql .= ' AND `' . $exceptColumn . '` <> ?';
            $bind[] = $except;
        }

        $count = (int) ($this->db->query($sql, $bind)->row['cnt'] ?? 0);

        // Returns TRUE if the value is unique (count is zero)
        return $count === 0;
    }

    private function defaultMessage(string $field, string $rule, array $params): string
    {
        $name = $this->humanize($field);

        return match ($rule) {
            'required' => $name . ' is required.',
            'email' => $name . ' must be a valid email address.',
            'url' => $name . ' must be a valid URL.',
            'alpha' => $name . ' may contain letters only.',
            'alnum' => $name . ' may contain letters and numbers only.',
            'alpha_dash' => $name . ' may contain letters, numbers, dashes, and underscores only.',
            'numeric' => $name . ' must be numeric.',
            'integer' => $name . ' must be an integer.',
            'boolean' => $name . ' must be true or false.',
            'min' => $name . ' must be at least ' . (int) ($params[0] ?? 0) . ' characters.',
            'max' => $name . ' must be at most ' . (int) ($params[0] ?? 0) . ' characters.',
            'in' => $name . ' has an invalid value.',
            'match' => $name . ' must match ' . $this->humanize((string) ($params[0] ?? '')).'.',
            'different' => $name . ' must be different from ' . $this->humanize((string) ($params[0] ?? '')).'.',
            'regex' => $name . ' format is invalid.',
            'array' => $name . ' must be an array.',
            'exists' => $name . ' does not exist.',
            'unique' => $name . ' has already been taken.',
            'accepted' => $name . ' must be accepted.',
            'checked' => $name . ' must be checked.',
            default => $name . ' is invalid.',
        };
    }

    private function length(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        if (is_array($value)) {
            return count($value);
        }

        return mb_strlen((string) $value);
    }

    private function extraAttributes(array $options): string
    {
        $reserved = [
            'label',
            'value',
            'placeholder',
            'help',
            'type',
            'rows',
            'prefix',
            'suffix',
            'options',
            'readonly',
            'disabled',
            'wrapper_class',
            'class',
            'id',
            'multiple',
            'checked',
            'no_hidden',
            'attrs',

            // Validation
            'rules',
            'messages',
        ];

        $attrs = (array) ($options['attrs'] ?? []);

        foreach ($options as $key => $value) {
            if (in_array($key, $reserved, true)) {
                continue;
            }

            $attrs[$key] = $value;
        }

        return $attrs === []
            ? ''
            : ' ' . $this->buildAttributes($attrs);
    }

    private function buildAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false || $value === '') {
                continue;
            }

            if ($value === true) {
                $parts[] = $this->e((string) $name);
                continue;
            }

            $parts[] = $this->e((string) $name) . '="' . $this->e((string) $value) . '"';
        }

        return implode(' ', $parts);
    }

    private function sanitizeIdentifier(string $name, bool $allowPrefixMarker = false): string
    {
        if ($allowPrefixMarker) {
            return preg_replace('/[^A-Za-z0-9_#]/', '', str_replace('`', '', $name)) ?? '';
        }

        return preg_replace('/[^A-Za-z0-9_]/', '', str_replace('`', '', $name)) ?? '';
    }

    private function humanize(string $field): string
    {
        $field = str_replace(['.', '_', '-'], ' ', $field);
        return ucwords(trim($field));
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    public function rule(
        string $field,
        string|array $rules,
        array $messages = []
    ): self {
        $rules = $this->normalizeRules($rules);

        $existing = $this->rules[$field] ?? [];

        if (is_string($existing)) {
            $existing = $this->normalizeRules($existing);
        }

        $this->rules[$field] = array_values(
            array_unique([
                ...$existing,
                ...$rules,
            ])
        );

        foreach ($messages as $rule => $message) {
            $this->messages[$field . '.' . $rule] = (string) $message;
        }

        return $this;
    }

    private function registerFieldRules(string $name, array $options): void
    {
        if (!isset($options['rules'])) {
            return;
        }

        $rules = $this->normalizeRules($options['rules']);

        $existing = $this->rules[$name] ?? [];

        if (is_string($existing)) {
            $existing = $this->normalizeRules($existing);
        }

        $this->rules[$name] = array_values(
            array_unique([
                ...$existing,
                ...$rules,
            ])
        );

        if (isset($options['messages']) && is_array($options['messages'])) {
            foreach ($options['messages'] as $rule => $message) {
                $this->messages[$name . '.' . $rule] = (string) $message;
            }
        }
    }

    private function normalizeRules(string|array $rules): array
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        return array_values(
            array_filter(
                array_map(
                    static fn ($rule) => trim((string) $rule),
                    $rules
                ),
                static fn ($rule) => $rule !== ''
            )
        );
    }
    
    /**
     * Show general form errors (for CSRF, non-field errors)
     */
    public function showGeneralErrors(): string
    {
        $html = '';
        
        // Check for token error
        $tokenError = $this->error('_token');
        if ($tokenError !== null) {
            $html .= '<div class="alert alert-danger">' . $this->e($tokenError) . '</div>';
        }
        
        // Check for any other general errors (fields not in rules)
        foreach ($this->flashedErrors as $field => $message) {
            if ($field === '_token' || !isset($this->rules[$field])) {
                $html .= '<div class="alert alert-danger">' . $this->e($message) . '</div>';
            }
        }
        
        return $html;
    }
    
    /**
     * Debug method to inspect errors
     */
    public function debugErrors(): array
    {
        return [
            'current_errors' => $this->errors,
            'flashed_errors' => $this->flashedErrors,
            'session_errors' => $this->session->get('_form_errors', []),
            'fields_with_errors' => array_keys(array_merge(
                $this->errors,
                $this->flashedErrors
            ))
        ];
    }
}