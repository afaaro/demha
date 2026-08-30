<?php

namespace System\Library;

class Pagination
{
    protected int $total;
    protected int $page;
    protected int $limit;
    protected string $url;
    protected int $radius;

    public function __construct(
        int $total,
        int $page = 1,
        int $limit = 20,
        string $url = '?page={page}',
        int $radius = 2
    ) {
        $this->total = max(0, $total);
        $this->page = max(1, $page);
        $this->limit = max(1, $limit);
        $this->url = $url;
        $this->radius = max(1, $radius);
    }

    public function pages(): int
    {
        return (int) ceil($this->total / $this->limit);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function render(): string
    {
        $pages = $this->pages();

        if ($pages <= 1) {
            return '';
        }

        $start = max(1, $this->page - $this->radius);
        $end   = min($pages, $this->page + $this->radius);

        $html = '<nav>';
        $html .= '<ul class="pagination">';

        // Previous
        $html .= $this->item(
            '&laquo;',
            max(1, $this->page - 1),
            $this->page == 1
        );

        // First page
        $html .= $this->item(
            '1',
            1,
            false,
            $this->page == 1
        );

        // Left dots
        if ($start > 2) {
            $html .= $this->dots();
        }

        // Middle pages
        for ($i = $start; $i <= $end; $i++) {

            if ($i == 1 || $i == $pages) {
                continue;
            }

            $html .= $this->item(
                (string) $i,
                $i,
                false,
                $i == $this->page
            );
        }

        // Right dots
        if ($end < $pages - 1) {
            $html .= $this->dots();
        }

        // Last page
        if ($pages > 1) {
            $html .= $this->item(
                (string) $pages,
                $pages,
                false,
                $this->page == $pages
            );
        }

        // Next
        $html .= $this->item(
            '&raquo;',
            min($pages, $this->page + 1),
            $this->page == $pages
        );

        $html .= '</ul>';
        $html .= '</nav>';

        return $html;
    }

    protected function item(
        string $label,
        int $page,
        bool $disabled = false,
        bool $active = false
    ): string {

        $class = 'page-item';

        if ($disabled) {
            $class .= ' disabled';
        }

        if ($active) {
            $class .= ' active';
        }

        return sprintf(
            '<li class="%s"><a class="page-link" href="%s">%s</a></li>',
            htmlspecialchars($class),
            $disabled ? '#' : htmlspecialchars($this->link($page)),
            $label
        );
    }

    protected function dots(): string
    {
        return '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    protected function link(int $page): string
    {
        return str_replace(
            '{page}',
            (string) $page,
            $this->url
        );
    }

    public function info(): string
    {
        if ($this->total === 0) {
            return 'No records found.';
        }

        $from = $this->offset() + 1;
        $to = min($this->offset() + $this->limit, $this->total);

        return "Showing {$from} to {$to} of {$this->total} records";
    }
}