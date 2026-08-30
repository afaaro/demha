<?php
define('DS', str_replace('\\', '/', DIRECTORY_SEPARATOR));
if (!defined('BASEDIR')) define("BASEDIR", strpos(getRootDirectory(), DS) === FALSE ? '' : dirname(getRootDirectory()).DS);
define('BASEURL', get_base_url());

require_once BASEDIR . 'system/engine/autoloader.php';
$loader = new System\Engine\Autoloader();
$loader->register('System\\Modules\\', BASEDIR . 'system/modules/');
$loader->register('System\\Engine\\', BASEDIR . 'system/engine/');
$loader->register('System\\Library\\', BASEDIR . 'system/library/');
$loader->register('System\\Security\\', BASEDIR . 'system/security/');
$loader->register('Modules\\', BASEDIR . 'modules/');
$loader->aliases([
    'Controller'    => 'System\\Engine\\Controller',
    'Model'         => 'System\\Engine\\Model',
    'Registry'      => 'System\\Engine\\Registry',

    'Json'          => 'System\\Library\\Json',
    'Request'       => 'System\\Library\\Request',
    'Response'      => 'System\\Library\\Response',
    'Session'       => 'System\\Library\\Session',
    'Config'        => 'System\\Library\\Config',
    'Cache'         => 'System\\Library\\Cache',
    'Database'      => 'System\\Library\\Database',
    'Form'          => 'System\\Library\\Form',
    'Url'           => 'System\\Library\\Url',
    'Document'      => 'System\\Library\\Document',
    'View'          => 'System\\Library\\View',
    'Block'         => 'System\\Library\\Block',
    'Pagination'    => 'System\\Library\\Pagination',
]);

$errorHandler = new \System\Library\ErrorHandler();
$errorHandler::register();

$registry = System\Engine\Registry::getInstance();
$registry->set('request', new System\Library\Request());

$registry->set('load', new System\Engine\Loader());

$config = new System\Library\Config();
$registry->set('config', $config);

date_default_timezone_set($config->get('app.timezone', 'UTC'));

$registry->set('logger', new System\Library\Logger());

$db = new System\Library\Database($config->get('database', []));
$registry->set('db', $db);

$registry->set('response', new System\Library\Response());

$doc = new System\Library\Document();
$registry->set('doc', $doc);

$session = System\Library\Session::getInstance();
$session->setOptions($config->get('session'));
$session->start();
$registry->set('session', $session);

$url = new System\Library\Url($registry);
$registry->set('url', $url);

$view = new System\Library\View($registry);
$registry->set('view', $view);

$form = new System\Library\Form($registry);
$registry->set('form', $form);

$block = new System\Library\Block($registry);
$registry->set('block', $block);

$menuManager = new System\Engine\MenuManager($registry);
$registry->set('menuManager', $menuManager);

$mediaClass = 'System\\Engine\\Media';
if (class_exists($mediaClass)) {
    $registry->set('media', new $mediaClass($registry));
}

$action = new System\Engine\Action($registry);
$registry->set('action', $action);

$auth = new System\Security\Auth($db, $session, $config->get('auth', []));
$registry->set('auth', $auth);
ob_start();

// functions
function debug(string|array|object $data, bool $debug = true) {
    list($called) = debug_backtrace();
    echo "<pre class='printp' style='white-space:pre-wrap !important;'>";
    echo htmlspecialchars(print_r($data, TRUE), ENT_QUOTES, 'utf-8');
    if ($debug) echo "&nbsp;&nbsp;(<small class='small d-block'>".$called['file']." @line: ".$called['line']."</small>)";
    echo "</pre>\n";
}

function registry(string $name, mixed $value = false) {
    $registry = System\Engine\Registry::getInstance();
    if (is_null($name)) {
        throw new Exception("You must provide service.");
    }

    if (!$registry->has($name)) {
        //trigger_error("The {$name} is not supported");
        throw new Exception("no such class $name.");
    }

    if (empty($value) && $registry->has($name)) {
        return $registry->get($name);
    } else {
        if (!empty($value)) {
            return $registry->set($name, $value);
        }
    }
}

function getRootDirectory(int $maxLevels = 7): string {
    static $config_path = '';

    $config_file = 'storage/config.php';

    if ($config_path === '') {
        $basedir = "";
        $i = 0;
        while ($i <= $maxLevels and !file_exists($basedir.$config_file)) {
            $basedir .= "../";
            $i++;
        }
        $config_path = file_exists($basedir.$config_file) ? $basedir.$config_file : '';
    }
    return $config_path;
}

function get_modules(string|null $module = null): array {
    $paths = [
        BASEDIR . "system/modules",
        BASEDIR . "modules"
    ];

    $folders = [];

    foreach ($paths as $path) {
        if (!is_dir($path)) continue;

        foreach (scandir($path) as $folder) {
            if ($folder === '.' || $folder === '..') continue;
            if ($module !== null && $folder !== $module) continue;

            $fullPath = $path . DS . $folder;
            if (is_dir($fullPath)) {
                $folders[$folder] = $fullPath;
            }
        }
    }

    return $folders;
}

function get_module_path(string $module): ?string {
    $modules = get_modules($module);
    return $modules[$module] ?? null;
}

function get_enabled_module(string $module): bool {
    $result = registry('db')->query("SELECT `module` FROM `#__module` WHERE `installed` = 1")->rows;
    $enabledModules = array_column($result, 'module');
    return in_array($module, $enabledModules, true);
}

function random_string($length = 10, $extra = "-_&$%^"): string {
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $all = $letters . '0123456789' . $extra;

    if ($length < 2) {
        // If length is too short, return just a letter
        return $letters[random_int(0, strlen($letters) - 1)];
    }

    // First and last characters must be letters
    $first = $letters[random_int(0, strlen($letters) - 1)];
    $last = $letters[random_int(0, strlen($letters) - 1)];

    // Middle part with any characters
    $middleLength = $length - 2;
    $middle = '';
    $pool = str_repeat($all, ceil($middleLength / strlen($all)));
    $shuffled = str_shuffle($pool);
    $middle = mb_substr($shuffled, 0, $middleLength);

    return $first . $middle . $last;
}

function route_url(string $route, array $params = [], bool $absolute = false, bool $seo = false): string {
    $url = registry('url')->to($route, $params, $absolute, $seo);
    return $url;
}

function redirect_to(string $route = '', array $params = []): never {
    header('Location: ' . route_url($route, $params));
    exit;
}

function get_base_url(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443)
        ? "https://" : "http://";

    $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';
    $basePath = $scriptName !== '' ? rtrim(dirname($scriptName), '/\\') : '';

    if ($basePath === '.' || $basePath === '/' || $basePath === '\\') {
        $basePath = '';
    }

    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] !== 80 && (int) $_SERVER['SERVER_PORT'] !== 443 && !str_contains($host, ':')) {
        $host .= ':' . (int) $_SERVER['SERVER_PORT'];
    }

    return $protocol . $host . ($basePath !== '' ? $basePath : '');
}

/**
 * Redirects the user to a new location with security hardening.
 */
function redirect(string $location, $delay = false, $script = false, int $code = 302): void {
    // 1. Sanitize the URL: Strip control characters and normalize entities
    $location = trim(str_replace(['&amp;', "\r", "\n"], ['&', '', ''], htmlspecialchars_decode($location, ENT_QUOTES)));

    // 2. Open Redirect Prevention:
    // Allow only relative URLs or same-host absolute HTTP(S) URLs.
    if (str_starts_with($location, '//')) {
        $location = '/';
    } else {
        $parsed = parse_url($location);

        if ($parsed !== false && isset($parsed['scheme'])) {
            $scheme = strtolower((string) $parsed['scheme']);

            // Block non-http(s) schemes (e.g. javascript:, data:)
            if (!in_array($scheme, ['http', 'https'], true)) {
                $location = '/';
            } else {
                $targetHost = strtolower((string) ($parsed['host'] ?? ''));
                $currentHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));

                if ($targetHost === '' || $targetHost !== $currentHost) {
                    $location = '/';
                }
            }
        }
    }

    if (is_numeric($delay)) {
        // HTML meta refresh
        echo "<meta http-equiv='refresh' content='{$delay}; url={$location}' />";
        if ($script) {
            echo "<script>setTimeout(function(){window.location.href='{$location}';}, " . ($delay * 1000) . ");</script>";
        }
    } else {
        // Standard Header Redirect
        if (!$script && !headers_sent()) {
            set_status_header($code);
            header("Location: $location", true, $code);
        } else {
            // JS/Meta fallback if headers already sent or forced by script option
            echo "<script>window.location.href='{$location}';</script>\n";
            echo "<noscript><meta http-equiv='refresh' content='0; url={$location}' /></noscript>";
        }
    }
    exit; // Crucial: Stop execution
}

/**
 * Sets the HTTP status header with a clean lookup table.
 */
function set_status_header(int $code): void
{
    $status_codes = [
        200 => 'OK',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        500 => 'Internal Server Error'
    ];

    $text = $status_codes[$code] ?? 'Internal Server Error';
    $protocol = $_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1';

    // Native header support handles the reason phrase automatically
    header("{$protocol} {$code} {$text}", true, $code);
}

function csrf_field(?string $formId = null): string {
    return registry('form')->csrfField($formId);
}

function old_input(string $key, mixed $default = ''): mixed {
    return registry('form')->oldInput($key, $default);
}

/**
 * Recursively escapes a value for safe HTML output.
 */
function escape(mixed $value): mixed {
    if (is_array($value)) {
        return array_map('escape', $value);
    }

    if (is_object($value)) {
        // Objects can be cast to arrays or handled via method calls
        return method_exists($value, '__toString') ? htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8') : '[Object]';
    }

    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function truncate(string $text, int $length = 100, string $suffix = '...'): string {
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
}

#####################################################
# Site Output
#####################################################
function opentable($title = '') {
    $html = '<div class="card mb-3">';
    if (!empty($title)) {
        $html .= '<div class="card-header"><h5 class="mb-0">' . htmlspecialchars($title) . '</h5></div>';
    }
    $html .= '<div class="card-body">';
    return $html;
}

function closetable($footer = '') {
    $html = '</div>'; // close card-body
    if (!empty($footer)) {
        $html .= '<div class="card-footer text-muted">' . $footer . '</div>';
    }
    $html .= '</div>'; // close card
    return $html;
}

function openslide($title = '', $state = "on", $id = null)
{
    $id = $id ?: 'slide_' . uniqid();
    $showClass = ($state === 'on') ? 'show' : '';
    $expanded = ($state === 'on') ? 'true' : 'false';

    $html = '<div class="card mb-3">';
    if (!empty($title)) {
        $html .= '<div class="card-header" id="heading_' . $id . '">';
        $html .= '<h5 class="mb-0">';
        $html .= '<button class="btn btn-link text-decoration-none p-0" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_' . $id . '" aria-expanded="' . $expanded . '">';
        $html .= htmlspecialchars($title);
        $html .= '</button>';
        $html .= '</h5>';
        $html .= '</div>';
    }
    $html .= '<div id="collapse_' . $id . '" class="collapse ' . $showClass . '">';
    $html .= '<div class="card-body">';
    return $html;
}

function closeslide($footer = '')
{
    $html = '</div>'; // close card-body
    if (!empty($footer)) {
        $html .= '<div class="card-footer text-muted">' . $footer . '</div>';
    }
    $html .= '</div>'; // close collapse div
    $html .= '</div>'; // close card
    return $html;
}

#####################################################
# Slug
#####################################################
function slug($string = null, $separator = '-', $length = 60)
{
    if (!is_string($string)) return '';

    // Normalize entities and strip special HTML characters
    $string = html_entity_decode($string, ENT_QUOTES, 'UTF-8');
    $string = preg_replace('/&#0*39;|\'/', '', $string); // Remove single quotes

    // Lowercase and trim
    $string = mb_strtolower(trim($string), 'UTF-8');

    // Convert special characters like ñ, ö, etc.
    $string = htmlentities($string, ENT_QUOTES, 'UTF-8');
    $string = preg_replace('~&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);~i', '$1', $string);
    $string = preg_replace('/&.+?;/', '', $string); // Remove remaining entities

    // Replace non-letter/digit characters with separator
    $string = preg_replace('~[^\pL\d]+~u', $separator, $string);

    // Remove duplicate separators
    $string = preg_replace("/[$separator]{2,}/u", $separator, $string);

    // Clean specific symbols
    $string = str_replace(['"', ',', ';', '<', '>', '/', '”', '“'], '', $string);

    // Truncate length if needed
    if ($length > -1 && mb_strlen($string, 'UTF-8') > $length) {
        $string = mb_substr($string, 0, $length, 'UTF-8');
    }

    // Trim separator from both ends
    return trim($string, $separator);
}