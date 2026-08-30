<?php

namespace System\Library;

class Logger
{
    // Log levels (RFC 5424)
    public const EMERGENCY = 'emergency';
    public const ALERT     = 'alert';
    public const CRITICAL  = 'critical';
    public const ERROR     = 'error';
    public const WARNING   = 'warning';
    public const NOTICE    = 'notice';
    public const INFO      = 'info';
    public const DEBUG     = 'debug';

    /**
     * @var array<string, int> Level → integer priority (higher = more severe)
     */
    private const LEVEL_MAP = [
        self::DEBUG     => 100,
        self::INFO      => 200,
        self::NOTICE    => 250,
        self::WARNING   => 300,
        self::ERROR     => 400,
        self::CRITICAL  => 500,
        self::ALERT     => 550,
        self::EMERGENCY => 600,
    ];

    /**
     * @var array Configuration
     */
    protected array $config;

    /**
     * @var int Minimum log level (numeric)
     */
    protected int $minLevel;

    /**
     * @var array<string, callable> Handlers
     */
    protected array $handlers = [];

    /**
     * Constructor.
     *
     * @param array $config {
     *     @var string $path        Log file path (default: BASEDIR . 'storage/logs/app.log')
     *     @var string $level       Minimum log level (default: 'debug')
     *     @var string $format      'json' or 'line' (default: 'line' – easier to read)
     *     @var int    $max_size    Max file size in MB (0 = no rotation)
     *     @var int    $max_backups Number of rotated files to keep (default: 5)
     * }
     */
    public function __construct(array $config = [])
    {
        $defaultPath = defined('BASEDIR')
            ? BASEDIR . 'storage' . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'app.log'
            : __DIR__ . '/../storage/logs/app.log';

        $this->config = array_merge([
            'path'        => $defaultPath,
            'level'       => self::DEBUG,
            'format'      => 'line',
            'max_size'    => 0,
            'max_backups' => 5,
        ], $config);

        $this->minLevel = self::LEVEL_MAP[$this->config['level']] ?? 100;

        // Ensure log directory exists
        $dir = dirname($this->config['path']);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $this->registerFileHandler();
    }

    // -------------------------------------------------------------------------
    // Log Methods
    // -------------------------------------------------------------------------

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }

    /**
     * Generic log method.
     */
    public function log(string $level, string|\Stringable $message, array $context = []): void
    {
        $level = strtolower($level);
        $levelValue = self::LEVEL_MAP[$level] ?? 0;

        if ($levelValue < $this->minLevel) {
            return;
        }

        $entry = $this->buildLogEntry($level, (string) $message, $context);

        foreach ($this->handlers as $handler) {
            $handler($entry, $level, $message, $context);
        }
    }

    // -------------------------------------------------------------------------
    // Handlers
    // -------------------------------------------------------------------------

    public function addHandler(string $name, callable $handler): self
    {
        $this->handlers[$name] = $handler;
        return $this;
    }

    public function removeHandler(string $name): self
    {
        unset($this->handlers[$name]);
        return $this;
    }

    protected function registerFileHandler(): void
    {
        $this->handlers['file'] = function ($entry) {
            $this->writeToFile($entry);
        };
    }

    // -------------------------------------------------------------------------
    // File Handling
    // -------------------------------------------------------------------------

    protected function writeToFile(string $entry): void
    {
        $file = $this->config['path'];
        $dir = dirname($file);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            error_log("Logger: Cannot create log directory '$dir'");
            return;
        }

        // Rotate if needed
        $maxSize = $this->config['max_size'] ?? 0;
        if ($maxSize > 0 && file_exists($file) && filesize($file) >= $maxSize * 1024 * 1024) {
            $this->rotateFile($file);
        }

        file_put_contents($file, $entry . "\n", FILE_APPEND | LOCK_EX)
            or error_log("Logger: Unable to write to '$file'");
    }

    /**
     * Rotate log files (e.g., app.log → app.log.1 → app.log.2, etc.)
     */
    protected function rotateFile(string $file): void
    {
        $maxBackups = $this->config['max_backups'] ?? 5;

        // Shift existing backups: .5 → .6, .4 → .5, etc.
        for ($i = $maxBackups - 1; $i >= 1; $i--) {
            $old = $file . '.' . $i;
            $new = $file . '.' . ($i + 1);
            if (file_exists($old)) {
                rename($old, $new);
            }
        }

        // Move current log to .1
        if (file_exists($file)) {
            rename($file, $file . '.1');
        }
    }

    // -------------------------------------------------------------------------
    // Log Entry Builder
    // -------------------------------------------------------------------------

    protected function buildLogEntry(string $level, string $message, array $context): string
    {
        $entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level'     => strtoupper($level),
            'message'   => $this->interpolate($message, $context),
        ];

        if (!empty($context)) {
            $entry['context'] = $context;
        }

        if ($this->config['format'] === 'json') {
            return json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        // Default: line format
        $line = '[' . $entry['timestamp'] . '] ' . $entry['level'] . ': ' . $entry['message'];
        if (isset($entry['context']) && !empty($entry['context'])) {
            $line .= ' ' . json_encode($entry['context']);
        }
        return $line;
    }

    protected function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $val;
            }
        }
        return strtr($message, $replace);
    }

    // -------------------------------------------------------------------------
    // Utilities
    // -------------------------------------------------------------------------

    public function setLevel(string $level): self
    {
        $this->minLevel = self::LEVEL_MAP[$level] ?? 100;
        return $this;
    }

    public function getLevel(): string
    {
        $map = array_flip(self::LEVEL_MAP);
        return $map[$this->minLevel] ?? self::DEBUG;
    }

    /**
     * Get the log directory path (with trailing slash).
     */
    public function getBaseDir(): string
    {
        $path = $this->config['path'];
        return rtrim(dirname($path), '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Get a list of all log files in the directory (excluding rotated backups if desired).
     */
    public function getLogFiles(bool $includeBackups = false): array
    {
        $dir = $this->getBaseDir();
        $pattern = $includeBackups ? '*.log*' : '*.log';
        return glob($dir . $pattern) ?: [];
    }

    /**
     * Get the full path of the current log file.
     */
    public function getCurrentLogPath(): string
    {
        return $this->config['path'];
    }

    /**
     * Clear a specific log file by path.
     */
    public function clearLog(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }
        return file_put_contents($path, '') !== false;
    }
}