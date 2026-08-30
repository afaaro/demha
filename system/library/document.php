<?php
namespace System\Library;

class Document {
    protected array $css = [];
    protected array $js = [];
    protected array $inlineCss = [];
    protected array $inlineJs = [];

    protected string $pageHeadTags = "";
    protected string $pageFooterTags = "";
    protected string $title = "";
    protected array $meta = [];
    protected array $breadcrumbs = [];
    protected array $handlers = [];

    /* ---------------------------------
     * Assets & Tags
     * --------------------------------- */

    public function addCss(string $href): self
    {
        if (!in_array($href, $this->css)) {
            $this->css[] = $href;
        }
        return $this;
    }

    public function addJs(string $src, bool $footer = true): self
    {
        $this->js[] = ['src' => $src, 'footer' => $footer];
        return $this;
    }

    public function addInlineCss(string $css): self
    {
        $this->inlineCss[] = $css;
        return $this;
    }

    public function addInlineJs(string $js): self
    {
        $this->inlineJs[] = $js;
        return $this;
    }

    public function addToHead(string $tag): self
    {
        if (!str_contains($this->pageHeadTags, $tag)) {
            $this->pageHeadTags .= $tag . PHP_EOL;
        }
        return $this;
    }

    public function addToFooter(string $tag): self
    {
        if (!str_contains($this->pageFooterTags, $tag)) {
            $this->pageFooterTags .= $tag . PHP_EOL;
        }
        return $this;
    }

    /* ---------------------------------
     * Meta & Breadcrumbs (Restored)
     * --------------------------------- */

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function addMeta(string $name, string $content): self
    {
        $this->meta[$name] = $content;
        return $this;
    }

    public function addBreadcrumb(string $label, string $url): self
    {
        $this->breadcrumbs[] = ['label' => $label, 'url' => $url];
        return $this;
    }

    /* ---------------------------------
     * Rendering & Pipeline
     * --------------------------------- */

    public function renderMeta(): string
    {
        $out = '<title>' . $this->escape($this->title) . '</title>' . PHP_EOL;
        foreach ($this->meta as $name => $content) {
            $out .= '<meta name="' . $this->escape($name) . '" content="' . $this->escape($content) . '">' . PHP_EOL;
        }
        return $out;
    }

    public function renderCss(): string
    {
        $out = '';
        foreach ($this->css as $href) {
            $out .= '<link rel="stylesheet" href="' . $this->escape($href) . '">' . PHP_EOL;
        }
        if ($this->inlineCss) {
            $out .= "<style>\n" . implode("\n", $this->inlineCss) . "\n</style>\n";
        }
        return $out;
    }

    public function renderJs(bool $footer = true): string
    {
        $out = '';
        foreach ($this->js as $script) {
            if ($script['footer'] === $footer) {
                $out .= '<script src="' . $this->escape($script['src']) . '"></script>' . PHP_EOL;
            }
        }

        // Inline JS is injected once in handleOutput().
        return $out;
    }

    public function addHandler(callable $callback): self
    {
        $this->handlers[] = $callback;
        return $this;
    }

    public function handleOutput(string $output): string
    {
        $headAssets = $this->renderMeta() . $this->renderCss() . $this->pageHeadTags;
        $output = preg_replace("#</head>#i", $headAssets . "</head>", $output, 1);

        $footerAssets = $this->renderJs(true) . $this->pageFooterTags;
        if ($this->inlineJs) {
            $footerAssets .= "<script>\n" . implode("\n", $this->inlineJs) . "\n</script>\n";
        }
        $output = preg_replace("#</body>#i", $footerAssets . "</body>", $output, 1);

        foreach ($this->handlers as $handler) {
            $output = $handler($output);
        }

        return $output;
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// $doc->addCss('/css/app.css', [
//     'media' => 'all'
// ]);
// $doc->addJs('/js/app.js', ['defer' => true]);
// $doc->addJs('/js/module.js', ['type' => 'module'], false); // head
// $doc->addInlineJs('console.log("ready");');
// $output = $doc->handleOutput($html);