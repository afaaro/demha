<?php

namespace System\Library;

use System\Engine\Registry;

class Tabs
{
    private string $id;
    private ?Registry $container;
    private array $tabs = [];
    private string $queryKey = 'section';
    private string $style = 'tabs'; // tabs, pills, underline
    private bool $vertical = false;
    private array $cleanup = [];
    private bool|string $linkMode = true;

    public function __construct(string $id, ?Registry $container = null)
    {
        $this->id = $id;
        $this->container = $container;
    }

    public static function make(string $id, ?Registry $container = null): self
    {
        return new self($id, $container);
    }

    public function query(string $key): self
    {
        $safe = preg_replace('/[^a-zA-Z0-9_]/', '', $key);
        $this->queryKey = $safe !== '' ? $safe : 'section';
        return $this;
    }

    public function style(string $style): self
    {
        $this->style = $style;
        return $this;
    }

    public function vertical(bool $vertical = true): self
    {
        $this->vertical = $vertical;
        return $this;
    }

    public function link(bool|string $value = true, array $cleanup = []): self
    {
        $this->linkMode = $value;
        $this->cleanup = $cleanup;
        return $this;
    }

    public function add(string $id, string $title, string $icon = '', string $content = '', array $options = []): self
    {
        $safeId = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
        if ($safeId === '') {
            $safeId = 'tab_' . (count($this->tabs) + 1);
        }

        $this->tabs[] = [
            'id'       => $safeId,
            'title'    => $title,
            'icon'     => $icon,
            'content'  => $content,
            'disabled' => $options['disabled'] ?? false,
            'badge'    => $options['badge'] ?? null,
        ];
        return $this;
    }

    public function render(): string
    {
        if (empty($this->tabs)) {
            return '';
        }

        $ids = array_column($this->tabs, 'id');
        $defaultId = $ids[0] ?? '';

        // Determine active tab from URL query parameter
        $activeId = $defaultId;
        if (isset($_GET[$this->queryKey]) && in_array($_GET[$this->queryKey], $ids, true)) {
            $activeId = $_GET[$this->queryKey];
        } elseif ($this->container) {
            $request = $this->container->get('request');
            if ($request && $request->has($this->queryKey)) {
                $val = $request->get($this->queryKey);
                if (in_array($val, $ids, true)) {
                    $activeId = $val;
                }
            }
        }

        $navClass = match ($this->style) {
            'pills'     => 'nav nav-pills',
            'underline' => 'nav nav-underline',
            default     => 'nav nav-tabs',
        };

        if ($this->vertical) {
            $navClass .= ' flex-column me-3';
            $wrapperClass = 'd-flex align-items-start';
        } else {
            $wrapperClass = 'nav-wrapper';
        }

        $html = '<div class="' . $wrapperClass . '">';
        
        // Render Navigation Headers
        $html .= '<ul id="' . htmlspecialchars($this->id, ENT_QUOTES, 'UTF-8') . '" class="' . $navClass . '" role="tablist">';
        
        foreach ($this->tabs as $tab) {
            $isActive = ($activeId === $tab['id']);
            $activeClass = $isActive ? ' active' : '';
            $ariaSelected = $isActive ? 'true' : 'false';
            $disabledClass = $tab['disabled'] ? ' disabled' : '';

            // Generate clean URL using container service
            $tabUrl = $this->buildLinkUrl($tab['id']);

            $iconHtml = $tab['icon'] ? '<i class="' . htmlspecialchars($tab['icon'], ENT_QUOTES, 'UTF-8') . '"></i> ' : '';
            $badgeHtml = $tab['badge'] !== null ? ' <span class="badge bg-primary">' . htmlspecialchars($tab['badge'], ENT_QUOTES, 'UTF-8') . '</span>' : '';

            $html .= '<li class="nav-item" role="presentation">';
            $html .= '<a class="nav-link' . $activeClass . $disabledClass . '" href="' . htmlspecialchars($tabUrl, ENT_QUOTES, 'UTF-8') . '" role="tab" aria-selected="' . $ariaSelected . '">';
            $html .= $iconHtml . htmlspecialchars($tab['title'], ENT_QUOTES, 'UTF-8') . $badgeHtml;
            $html .= '</a>';
            $html .= '</li>';
        }
        
        $html .= '</ul>';

        // Render Tab Contents Container
        $html .= '<div class="tab-content py-3">';
        foreach ($this->tabs as $tab) {
            $isActive = ($activeId === $tab['id']);
            $showActive = $isActive ? ' show active' : '';

            $html .= '<div class="tab-pane fade' . $showActive . '" id="' . htmlspecialchars($tab['id'], ENT_QUOTES, 'UTF-8') . '" role="tabpanel">';
            $html .= $tab['content'];
            $html .= '</div>';
        }
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }

    private function buildLinkUrl(string $tabId): string
    {
        if ($this->container) {
            $urlService = $this->container->get('url'); // Assumes your URL router service is bound here
            if ($urlService && method_exists($urlService, 'cleanRequest')) {
                return $urlService->cleanRequest(
                    "{$this->queryKey}={$tabId}",
                    $this->cleanup,
                    in_array('*', $this->cleanup, true)
                );
            }
        }

        // Fallback standard build
        $query = $_GET;
        $query[$this->queryKey] = $tabId;
        unset($query['route']);
        return '?' . http_build_query($query);
    }
}