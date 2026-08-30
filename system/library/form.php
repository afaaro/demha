<?php

namespace System\Library;

use System\Engine\Registry;

class Form {
    protected Registry $registry;
    protected Session $session;
    protected Database $db;
    protected Document $doc;

    // Core data
    protected array $data = [];
    protected array $errors = [];
    protected array $flashedErrors = [];
    protected array $values = [];

    // Validation
    protected array $rules = [];
    protected array $messages = [];
    protected array $fieldLabels = [];
    protected array $validated = [];
    protected array $submittedData = [];

    // CSRF
    protected ?string $csrfToken = null;
    protected string $csrfSessionKey = 'form_csrf_token';
    protected string $formId = 'form-default';

    // Form state
    protected string $method = 'POST';
    protected string $action = '';
    protected bool $submitted = false;

    /**
     * Default validation messages
     */
    public array $defaultMessages = [
        'required'   => 'The :label field is required.',
        'email'      => 'The :label must be a valid email address.',
        'min'        => 'The :label must be at least :param characters.',
        'max'        => 'The :label may not be greater than :param characters.',
        'numeric'    => 'The :label must be a number.',
        'url'        => 'The :label is not a valid URL.',
        'match'      => 'The :label does not match the :param field.',
        'unique'     => 'The :label has already been taken.',
        'exists'     => 'The :label does not exist.',
        'alpha'      => 'The :label may only contain letters.',
        'alnum'      => 'The :label may only contain letters and numbers.',
        'alpha_dash' => 'The :label may only contain letters, numbers, and dashes.',
        'digits'     => 'The :label must be exactly :param digits.',
        'min_num'    => 'The :label must be at least :param.',
        'max_num'    => 'The :label may not be greater than :param.',
        'date'       => 'The :label is not a valid date format.',
        'before'     => 'The :label must be before :param.',
        'after'      => 'The :label must be after :param.',
        'in'         => 'The selected :label is invalid.',
        'not_in'     => 'The selected :label is invalid.',
        'min_file'   => 'The :label must be at least :param KB.',
        'max_file'   => 'The :label may not be larger than :param KB.',
        'ext'        => 'The :label must be a file of type: :param.',
        'image'      => 'The :label must be a valid image file.',
        'boolean'    => 'The :label must be true or false.',
        'array'      => 'The :label must be an array.',
        'regex'      => 'The :label format is invalid.',
        'nullable'   => '',
        'accepted'   => 'The :label must be accepted.',
        'checked'    => 'The :label must be checked.',
    ];

    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->session  = $registry->get('session');
        $this->db       = $registry->get('db');
        $this->doc      = $registry->get('doc');

        // Load flashed errors from session
        $this->flashedErrors = (array) $this->session->get('_form_errors', []);
    }

    // ============================================
    // CONFIGURATION
    // ============================================

    public function setRules(array $rules, array $messages = []): self
    {
        $this->rules = $rules;
        $this->messages = array_merge($this->defaultMessages, $messages);
        return $this;
    }

    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }

    public function fill(array|object $data): self
    {
        $this->values = (array)$data;
        return $this;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function setMethod(string $method): self
    {
        $this->method = strtoupper($method);
        return $this;
    }

    // ============================================
    // CSRF
    // ============================================

    public function csrfField(?string $formId = null): string
    {
        $id = $formId ?? $this->formId;
        $token = bin2hex(random_bytes(32));
        $bucket = '_csrf.' . $id;

        $tokens = (array)$this->session->get($bucket, []);
        $tokens = array_filter($tokens, fn($expiry) => $expiry >= time());
        $tokens[$token] = time() + 3600;
        $this->session->set($bucket, $tokens);

        return '<input type="hidden" name="_token" value="' . escape($token) . '">';
    }

    public function checkToken(?string $token = null, ?string $formId = null): bool
    {
        $id = $formId ?? $this->formId;
        $token = $token ?? (string)$this->request()?->post('_token', 'raw', '');

        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }

        $bucket = '_csrf.' . $id;
        $tokens = (array)$this->session->get($bucket, []);
        $expiry = (int)($tokens[$token] ?? 0);

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

    // ============================================
    // VALIDATION
    // ============================================

    public function isValid(): bool
    {
        if (!$this->isSubmitted()) {
            return false;
        }

        $this->errors = [];
        $this->validated = [];
        $this->submittedData = [];

        // CSRF check
        if ($this->method !== 'GET' && !$this->checkToken()) {
            $this->errors['_token'] = 'Your session has expired. Please try again.';
            $this->flashErrors($this->errors);
            return false;
        }

        // Get all POST data
        $this->submittedData = $this->getAllPostData();

        // Validate against rules
        $errors = $this->validateData($this->submittedData);
        $this->errors = $errors;

        if (!empty($errors)) {
            $this->flashInput($this->submittedData);
            $this->flashErrors($errors);
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

    public function errors(): array
    {
        return array_merge($this->errors, $this->flashedErrors);
    }

    public function error(string $field): ?string
    {
        if (isset($this->errors[$field])) {
            return (string)$this->errors[$field];
        }
        if (isset($this->flashedErrors[$field])) {
            return (string)$this->flashedErrors[$field];
        }
        return null;
    }

    public function hasError(string $field): bool
    {
        return isset($this->errors[$field]) || isset($this->flashedErrors[$field]);
    }

    public function isSubmitted(): bool
    {
        if (!$this->request()) {
            return false;
        }
        return match ($this->method) {
            'POST' => $this->request()->isPost(),
            'GET'  => $this->request()->isGet(),
            default => false,
        };
    }

    // ============================================
    // SESSION HELPERS
    // ============================================

    public function flashInput(array $input): void
    {
        $this->session->set('_old_input', $input);
    }

    public function flashErrors(array $errors): void
    {
        $this->session->set('_form_errors', $errors);
        $this->flashedErrors = $errors;
    }

    public function clearOldInput(): void
    {
        $this->session->delete('_old_input');
    }

    public function clearFlashedErrors(): void
    {
        $this->session->delete('_form_errors');
        $this->flashedErrors = [];
    }

    public function oldInput(string $key, mixed $default = ''): mixed
    {
        $old = (array)$this->session->get('_old_input', []);
        if (array_key_exists($key, $old)) {
            return $old[$key];
        }
        return $this->request()?->post($key, 'raw', $default) ?? $default;
    }

    // ============================================
    // RENDERING
    // ============================================

    public function start(array $attrs = []): string
    {
        $this->formId = (string)($attrs['id'] ?? $this->formId);
        $this->method = strtoupper((string)($attrs['method'] ?? $this->method));
        $this->action = (string)($attrs['action'] ?? $this->action);

        $defaults = [
            'id'         => $this->formId,
            'method'     => $this->method,
            'action'     => $this->action,
            'class'      => 'needs-validation',
            'novalidate' => 'novalidate',
        ];

        if (!empty($this->hasFileInput)) {
            $attrs['enctype'] = 'multipart/form-data';
        }

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

    /**
     * Render form element — rules accept array OR pipe-separated string
     */
    public function element(string $type, array $attributes = []): string
    {
        $name      = $attributes['name'] ?? '';
        $label     = $attributes['label'] ?? null;
        $prefix    = $attributes['prefix'] ?? null;
        $suffix    = $attributes['suffix'] ?? null;
        $floating  = $attributes['floating'] ?? false;
        $help      = $attributes['help'] ?? null;
        $rules     = $attributes['rules'] ?? null;
        $messages  = $attributes['messages'] ?? [];

        // Register validation rules from the element
        if ($name && $rules) {
            // Store as-is — validateData normalizes array/string automatically
            $this->rules[$name] = $rules;

            if ($messages) {
                foreach ($messages as $rule => $msg) {
                    $this->messages[$name . '.' . $rule] = $msg;
                }
            }
            if ($label) {
                $this->fieldLabels[$name] = $label;
            }
        }

        // Resolve value
        $value = $this->resolveValue($name, $attributes['value'] ?? null);

        // Remove special attributes
        unset(
            $attributes['label'], $attributes['prefix'], $attributes['suffix'],
            $attributes['floating'], $attributes['rules'], $attributes['messages'],
            $attributes['help'], $attributes['col'], $attributes['value']
        );

        // Ensure ID exists
        if (!isset($attributes['id'])) {
            $attributes['id'] = $name
                ? str_replace(['[', ']', ' '], ['_', '', '_'], $name)
                : 'form-field-' . uniqid();
        }

        // Error handling
        $error = $this->error($name);
        $isInvalid = $error ? ' is-invalid' : '';
        $attributes['class'] = trim(
            ($attributes['class'] ?? '') .
            (!in_array($type, ['checkbox', 'radio', 'button', 'submit']) ? $isInvalid : '')
        );

        // Add required attribute if rule exists
        if ($name && isset($this->rules[$name])) {
            $ruleSet = is_array($this->rules[$name])
                ? $this->rules[$name]
                : explode('|', (string)$this->rules[$name]);
            if (in_array('required', $ruleSet)) {
                $attributes['required'] = true;
            }
        }

        $wrapFloating = $floating && in_array($type, ['text', 'email', 'password', 'number', 'textarea', 'datetime']);
        $html = !$wrapFloating && !in_array($type, ['submit', 'button', 'checkbox', 'radio', 'file'])
            ? '<div class="mb-3">' : '';

        $input = '';

        switch ($type) {
            case 'textarea':
                $attributes['class'] = trim(($attributes['class'] ?? '') . ' form-control');

                $isEditor = !empty($attributes['editor']) && $attributes['editor'] === true;
                $content = is_scalar($value) ? (string)$value : '';
                if (!$isEditor) {
                    $content = escape($content);
                }
                unset($attributes['editor']);

                $input = '<textarea ' . $this->buildAttributes($attributes) . '>' . $content . '</textarea>';
                break;

            case 'select':
                $options = $attributes['options'] ?? [];
                $multiple = !empty($attributes['multiple']);
                $attributes['class'] = trim(($attributes['class'] ?? '') . ' form-select');
                $selectAttrs = $attributes;
                unset($selectAttrs['options']);
                if ($multiple && substr($selectAttrs['name'] ?? '', -2) !== '[]') {
                    $selectAttrs['name'] .= '[]';
                }
                $input = '<select ' . $this->buildAttributes($selectAttrs) . '>';
                if (!empty($attributes['placeholder']) && !$multiple) {
                    $input .= '<option value="">' . escape($attributes['placeholder']) . '</option>';
                }
                foreach ($options as $optValue => $optLabel) {
                    $selected = ($multiple && is_array($value) && in_array($optValue, $value))
                        || (!$multiple && (string)$optValue === (string)$value) ? ' selected' : '';
                    $input .= '<option value="' . escape($optValue) . '"' . $selected . '>' . escape($optLabel) . '</option>';
                }
                $input .= '</select>';
                break;

            case 'file':
                $attributes['class'] = trim(($attributes['class'] ?? '') . ' form-control');
                $input = '<input type="file" ' . $this->buildAttributes($attributes) . '>';
                if ($error) $input .= '<div class="invalid-feedback d-block">' . escape($error) . '</div>';
                if ($help) $input .= '<div class="form-text small">' . escape($help) . '</div>';
                break;

            case 'checkbox':
            case 'radio':
                $attributes['class'] = trim(($attributes['class'] ?? '') . ' form-check-input');
                $checked = $attributes['checked'] ?? false;
                $checkboxValue = (string)($attributes['value'] ?? '1');

                // ✅ Properly handle ALL truthy values from fill() / DB
                if ($value !== null && $value !== '') {
                    $valueStr = (string)$value;
                    // Match accepted values SAME as your validation rules
                    $isTruthy = in_array($valueStr, ['1', 'true', 'on', 'yes'], true) || $value === true;
                    
                    // Either: truthy matches default value, OR exact match
                    $checked = $isTruthy || $valueStr === $checkboxValue;
                }

                if ($checked) {
                    $attributes['checked'] = true;
                } else {
                    unset($attributes['checked']);
                }
                
                if ($type === 'checkbox' && empty($attributes['no_hidden'])) {
                    $input = '<input type="hidden" name="' . escape($name) . '" value="0">';
                }
                $input .= '<div class="form-check">';
                $input .= '<input type="' . $type . '" ' . $this->buildAttributes($attributes) . '>';
                if ($label) $input .= '<label class="form-check-label" for="' . $attributes['id'] . '">' . escape($label) . '</label>';
                if ($error) $input .= '<div class="invalid-feedback d-block">' . escape($error) . '</div>';
                if ($help) $input .= '<div class="form-text small">' . escape($help) . '</div>';
                $input .= '</div>';
                break;

            case 'submit':
            case 'button':
                $attributes['class'] = trim(($attributes['class'] ?? '') . ' btn btn-primary');
                $buttonText = $value ?: ucfirst($type);
                $input = '<button type="' . $type . '" ' . $this->buildAttributes($attributes) . '>' . escape($buttonText) . '</button>';
                if ($help) $input .= '<div class="form-text small">' . escape($help) . '</div>';
                break;

            default:
                $attributes['class'] = trim(($attributes['class'] ?? '') . ' form-control');
                $input = '<input type="' . escape($type) . '" ' . $this->buildAttributes($attributes) . ' value="' . escape((string)$value) . '">';
                break;
        }

        // Prefix/Suffix
        if ($prefix || $suffix) {
            $input = '<div class="input-group">'
                . ($prefix ? '<span class="input-group-text">' . escape($prefix) . '</span>' : '')
                . $input
                . ($suffix ? '<span class="input-group-text">' . escape($suffix) . '</span>' : '')
                . '</div>';
        }

        // Wrap floating label
        if ($wrapFloating && $label) {
            $html .= '<div class="form-floating">' . $input
                . '<label for="' . $attributes['id'] . '">' . escape($label) . '</label>';
            if ($error) $html .= '<div class="invalid-feedback">' . escape($error) . '</div>';
            if ($help)  $html .= '<div class="form-text small">' . escape($help) . '</div>';
            $html .= '</div>';
        } elseif (!in_array($type, ['submit', 'button', 'checkbox', 'radio', 'file'])) {
            if ($label) $html .= '<label class="form-label" for="' . $attributes['id'] . '">' . escape($label) . '</label>';
            $html .= $input;
            if ($error) $html .= '<div class="invalid-feedback d-block">' . escape($error) . '</div>';
            if ($help)  $html .= '<div class="form-text small">' . escape($help) . '</div>';
            $html .= '</div>';
        } else {
            $html .= $input;
        }

        return $html;
    }

    // ============================================
    // CONVENIENCE METHODS
    // ============================================

    public function input(string $name, array $options = []): string
    {
        $type = $options['type'] ?? 'text';
        unset($options['type']);
        return $this->element($type, array_merge($options, ['name' => $name]));
    }

    public function textarea(string $name, array $options = []): string
    {
        $editorEnabled = !empty($options['editor']) && $options['editor'] === true;
        if ($editorEnabled) {
            $this->registerTinyMce();
            $options['class'] = isset($options['class']) ? $options['class'] . ' js-tinymce-editor' : 'js-tinymce-editor';
        }
        return $this->element('textarea', array_merge($options, ['name' => $name]));
    }

    /**
     * Register global TinyMCE assets once per request
     */
    private function registerTinyMce(): void
    {
        static $registered = false;
        if ($registered) return;
        $registered = true;

        $pickerUrl = $this->registry->get('url')->to('tools/admin/media/picker');
        $this->doc->addJs('https://cdn.jsdelivr.net/npm/tinymce@6/tinymce.min.js');

        $this->doc->addInlineJs(<<<JS
            document.addEventListener("DOMContentLoaded", function() {
                if (typeof tinymce === "undefined") return;
                tinymce.init({
                    selector: 'textarea.js-tinymce-editor',
                    height: 350,
                    menubar: false,
                    plugins: 'link lists code table',
                    toolbar: 'undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | code',
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
                                    body: { type: 'panel', items: [
                                        { type: 'htmlpanel', html: '<iframe src="{$pickerUrl}" style="width:100%;height:100%;border:0;"></iframe>' }
                                    ]},
                                    buttons: [ { type: 'cancel', text: 'Close' } ]
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
        if (isset($options['options']) && is_array($options['options'])) {
            $items = $options['options'];
        }
        $options['options'] = (array)$items;
        if ($selected !== null) {
            $options['value'] = $selected;
        }
        return $this->element('select', array_merge($options, ['name' => $name]));
    }

    public function checkbox(string $name, array $options = []): string
    {
        return $this->element('checkbox', array_merge($options, ['name' => $name]));
    }

    public function radio(string $name, array $options = []): string
    {
        return $this->element('radio', array_merge($options, ['name' => $name]));
    }

    public function hidden(string $name, mixed $value = '', array $options = []): string
    {
        return $this->element('hidden', array_merge($options, ['name' => $name, 'value' => $value]));
    }

    public function file(string $name, array $options = []): string
    {
        return $this->element('file', array_merge($options, ['name' => $name]));
    }

    public function submit(string $text = 'Submit', array $options = []): string
    {
        return $this->element('submit', array_merge($options, ['value' => $text]));
    }

    // ============================================
    // INTERNAL HELPERS
    // ============================================

    protected function request()
    {
        return $this->registry->get('request');
    }

    protected function getAllPostData(): array
    {
        $postData = $this->request()?->post() ?? [];
        unset($postData['_token']);
        return $postData;
    }

    /**
     * Validate data — rules accept BOTH array AND pipe-separated string
     */
    protected function validateData(array $data): array
    {
        $errors = [];

        foreach ($this->rules as $field => $ruleSet) {
            // ✅ Normalize: array OR string → array
            $rules = is_array($ruleSet) ? $ruleSet : explode('|', (string)$ruleSet);

            $value = $data[$field] ?? null;
            $label = $this->fieldLabels[$field] ?? $this->humanize($field);

            foreach ($rules as $rule) {
                $param = null;
                $exceptId = null;

                if (str_contains($rule, ':')) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    if ($rule === 'unique' && str_contains($paramStr, ',')) {
                        [$paramStr, $exceptId] = explode(',', $paramStr, 2);
                    }
                    $param = $paramStr;
                }

                // Skip validation if field is nullable and empty
                if ($rule === 'nullable' && ($value === null || $value === '')) {
                    continue 2;
                }

                $pass = true;
                $messageKey = $rule;
                $labelParam = $label;
                $paramDisplay = $param;

                switch ($rule) {
                    case 'required':
                        $pass = (is_array($value) ? !empty($value) : trim((string)$value) !== '');
                        break;

                    case 'email':
                        $pass = $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
                        break;

                    case 'min':
                        $pass = $value === null || $value === '' || mb_strlen((string)$value) >= (int)$param;
                        break;

                    case 'max':
                        $pass = $value === null || $value === '' || mb_strlen((string)$value) <= (int)$param;
                        break;

                    case 'numeric':
                        $pass = $value === null || $value === '' || is_numeric($value);
                        break;

                    case 'url':
                        $pass = $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_URL) !== false;
                        break;

                    case 'match':
                        $compareValue = $data[$param] ?? '';
                        $pass = $value === null || $value === '' || ((string)$value === (string)$compareValue);
                        break;

                    case 'unique':
                        $pass = true;
                        if ($this->db && $param && $value !== null && $value !== '') {
                            $parts = explode(',', $param);
                            $table = $parts[0] ?? null;
                            $column = $parts[1] ?? 'id';
                            $except = $parts[2] ?? null;
                            $idColumn = $parts[3] ?? 'id';

                            if (!$table) break;

                            $table = str_starts_with($table, '#__') ? $table : '#__' . $table;
                            $sql = "SELECT COUNT(*) AS total FROM `{$table}` WHERE `{$column}` = ?";
                            $bind = [(string)$value];

                            if ($except !== null && $except !== '' && $except !== 'NULL') {
                                $sql .= " AND `{$idColumn}` != ?";
                                $bind[] = $except;
                            }

                            $row = $this->db->query($sql, $bind)->row;
                            if ($row && $row['total'] > 0) $pass = false;
                        }
                        break;

                    case 'exists':
                        $pass = true;
                        if ($this->db && $param && $value !== null && $value !== '') {
                            [$table, $column] = explode('.', $param, 2) + [1 => 'id'];
                            $table = str_starts_with($table, '#__') ? $table : '#__' . $table;
                            $sql = "SELECT COUNT(*) AS total FROM `{$table}` WHERE `{$column}` = ?";
                            $row = $this->db->query($sql, [(string)$value])->row;
                            if ($row && $row['total'] == 0) $pass = false;
                        }
                        break;

                    case 'alpha':
                        $pass = $value === null || $value === '' || preg_match('/^[\p{L}]+$/u', (string)$value);
                        break;

                    case 'alnum':
                        $pass = $value === null || $value === '' || preg_match('/^[\p{L}0-9]+$/u', (string)$value);
                        break;

                    case 'alpha_dash':
                        $pass = $value === null || $value === '' || preg_match('/^[\p{L}0-9_-]+$/u', (string)$value);
                        break;

                    case 'digits':
                        $pass = $value === null || $value === '' || preg_match('/^[0-9]{' . (int)$param . '}$/', (string)$value);
                        break;

                    case 'min_num':
                        $pass = $value === null || $value === '' || (is_numeric($value) && (float)$value >= (float)$param);
                        break;

                    case 'max_num':
                        $pass = $value === null || $value === '' || (is_numeric($value) && (float)$value <= (float)$param);
                        break;

                    case 'date':
                        $pass = $value === null || $value === '' || (bool)strtotime($value);
                        break;

                    case 'before':
                        if ($value !== null && $value !== '') {
                            $tsValue = strtotime($value);
                            $tsCompare = strtotime($param === 'today' ? date('Y-m-d') : $param);
                            $pass = $tsValue && $tsCompare && $tsValue < $tsCompare;
                        }
                        break;

                    case 'after':
                        if ($value !== null && $value !== '') {
                            $tsValue = strtotime($value);
                            $tsCompare = strtotime($param === 'today' ? date('Y-m-d') : $param);
                            $pass = $tsValue && $tsCompare && $tsValue > $tsCompare;
                        }
                        break;

                    case 'in':
                        if ($value !== null && $value !== '') {
                            $allowed = explode(',', $param);
                            $pass = in_array((string)$value, $allowed);
                        }
                        break;

                    case 'not_in':
                        if ($value !== null && $value !== '') {
                            $forbidden = explode(',', $param);
                            $pass = !in_array((string)$value, $forbidden);
                        }
                        break;

                    case 'boolean':
                        $pass = $value === null || $value === '' || filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) !== null;
                        break;

                    case 'array':
                        $pass = $value === null || is_array($value);
                        break;

                    case 'regex':
                        if ($value !== null && $value !== '') {
                            $pass = preg_match($param, (string)$value) === 1;
                        }
                        break;

                    case 'accepted':
                    case 'checked':
                        $pass = in_array((string)$value, ['1', 'true', 'on', 'yes'], true);
                        break;

                    case 'nullable':
                        break;
                }

                if (!$pass && !isset($errors[$field])) {
                    $messageKey = $rule;
                    $msg = $this->messages[$field . '.' . $rule] ?? $this->defaultMessages[$rule] ?? 'Invalid value.';
                    $msg = str_replace([':label', ':param'], [$labelParam, $paramDisplay], $msg);
                    $errors[$field] = $msg;
                    break;
                }
            }
        }

        return $errors;
    }

    protected function resolveValue(string $name, mixed $fallback): mixed
    {
        $request = $this->request();

        // ✅ STEP 1: Submitted POST data — ONLY after submission
        if ($request && $request->isPost()) {
            $posted = $request->post($name, 'raw', null);
            if ($posted !== null) {
                return $posted;
            }
        }

        // ✅ STEP 2: Old input — ONLY after a FAILED submission
        // Check if THIS form was actually submitted before trusting old input
        if ($this->isSubmitted()) {
            $old = (array) $this->session->get('_old_input', []);
            if (array_key_exists($name, $old)) {
                return $old[$name];
            }
        }

        // ✅ STEP 3: fill() values — PRIMARY source on FIRST load
        if (array_key_exists($name, $this->values)) {
            $val = $this->values[$name];
            // Return it even if null — unless you prefer fallback
            return $val ?? $fallback;
        }

        // ✅ STEP 4: Explicit value attribute or default
        return $fallback;
    }

    protected function buildAttributes(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            if ($value === true) {
                $parts[] = $key;
            } elseif ($value !== false && $value !== null) {
                if (is_array($value)) {
                    if ($key === 'class') {
                        $value = implode(' ', $value);
                    } else {
                        $value = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                }
                $parts[] = $key . '="' . escape((string)$value) . '"';
            }
        }
        return implode(' ', $parts);
    }

    protected function humanize(string $field): string
    {
        $field = str_replace(['.', '_', '-'], ' ', $field);
        return ucwords(trim($field));
    }
}