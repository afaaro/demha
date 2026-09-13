<?php

namespace System\Library;

class Pagination
{
    protected int $total;
    protected int $page;
    protected int $limit;
    /** @var callable|null Generates URL for a given page number: fn(int $page): string */
    protected $urlGenerator;
    protected int $radius;

    /**
     * @param int $total Total number of records
     * @param int $page Current page number
     * @param int $limit Records per page
     * @param callable|null $urlGenerator fn(int $page): string — generates page URLs
     * @param int $radius How many pages to show around current page
     */
    public function __construct(
        int $total,
        int $page = 1,
        int $limit = 20,
        ?callable $urlGenerator = null,
        int $radius = 2
    ) {
        $this->total = max(0, $total);
        $this->page = max(1, $page);
        $this->limit = max(1, $limit);
        $this->urlGenerator = $urlGenerator;
        $this->radius = max(1, $radius);
    }

    /** Total number of pages */
    public function pages(): int
    {
        return (int) ceil($this->total / $this->limit) ?: 1;
    }

    /** SQL OFFSET value */
    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    /** Records per page limit */
    public function limit(): int
    {
        return $this->limit;
    }

    /** Current page number */
    public function currentPage(): int
    {
        return $this->page;
    }

    /** Total record count */
    public function total(): int
    {
        return $this->total;
    }

    /** Render Bootstrap pagination HTML */
    public function render(): string
    {
        $pages = $this->pages();
        if ($pages <= 1) {
            return '';
        }

        $start = max(1, $this->page - $this->radius);
        $end   = min($pages, $this->page + $this->radius);

        $html = '<nav><ul class="pagination justify-content-center">';

        // Previous Page
        $html .= $this->item(
            '&laquo;',
            $this->page - 1,
            $this->page === 1
        );

        // First Page
        if ($start > 1) {
            $html .= $this->item('1', 1, false, $this->page === 1);
            if ($start > 2) {
                $html .= $this->dots();
            }
        }

        // Nearby Pages
        for ($i = $start; $i <= $end; $i++) {
            $html .= $this->item((string)$i, $i, false, $i === $this->page);
        }

        // Last Page + End Dots
        if ($end < $pages) {
            if ($end < $pages - 1) {
                $html .= $this->dots();
            }
            $html .= $this->item((string)$pages, $pages, false, $this->page === $pages);
        }

        // Next Page
        $html .= $this->item(
            '&raquo;',
            $this->page + 1,
            $this->page === $pages
        );

        $html .= '</ul></nav>';
        return $html;
    }

    /** Render "Showing X-Y of Z" info text */
    public function info(): string
    {
        if ($this->total === 0) {
            return 'No records found.';
        }
        $from = $this->offset() + 1;
        $to   = min($this->offset() + $this->limit, $this->total);
        return sprintf('Showing %d to %d of %d', $from, $to, $this->total);
    }

    /** Render a single pagination item */
    protected function item(string $label, int $page, bool $disabled = false, bool $active = false): string
    {
        $classes = ['page-item'];
        if ($disabled) $classes[] = 'disabled';
        if ($active)   $classes[] = 'active';
        $class = implode(' ', $classes);

        $url = $disabled ? '#' : $this->link($page);

        return sprintf(
            '<li class="%s"><a class="page-link" href="%s">%s</a></li>',
            htmlspecialchars($class),
            htmlspecialchars($url),
            $label
        );
    }

    /** Render ellipsis dots */
    protected function dots(): string
    {
        return '<li class="page-item disabled"><span class="page-link">&hellip;</span></li>';
    }

    /** Generate URL for a specific page */
    protected function link(int $page): string
    {
        // Use callable generator if provided, fallback to ?page=N
        if ($this->urlGenerator !== null) {
            return call_user_func($this->urlGenerator, $page);
        }
        return '?page=' . $page;
    }
}