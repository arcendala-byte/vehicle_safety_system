<?php
/**
 * ============================================================
 * PAGINATION HELPERS
 * ============================================================
 */

if (!defined('VS_APP')) {
    define('VS_APP', true);
}

/**
 * Compute pagination metadata.
 *   $meta = paginate($totalRows, $currentPage, $perPage);
 *   Returns: ['page','per_page','total','total_pages','offset','has_prev','has_next']
 */
function paginate(int $total, int $page, int $perPage = 10): array
{
    $perPage    = max(1, $perPage);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;

    return [
        'page'        => $page,
        'per_page'    => $perPage,
        'total'       => $total,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $totalPages,
    ];
}

/**
 * Render a professional pagination control.
 *   render_pagination($meta, 'admin/drivers/index.php', ['q' => 'john', 'status' => 'active']);
 */
function render_pagination(array $meta, string $basePath, array $query = []): string
{
    if ($meta['total_pages'] <= 1) return '';

    $build = function (int $p) use ($basePath, $query): string {
        $query['page'] = $p;
        return url($basePath) . '?' . http_build_query($query);
    };

    $out = '<nav class="pagination" aria-label="Pagination">';

    // Prev
    if ($meta['has_prev']) {
        $out .= '<a class="pagination__link" href="' . h($build($meta['page'] - 1)) . '">'
              . '<i data-lucide="chevron-left"></i> Prev</a>';
    } else {
        $out .= '<span class="pagination__link pagination__link--disabled">'
              . '<i data-lucide="chevron-left"></i> Prev</span>';
    }

    // Page numbers (window of 5)
    $start = max(1, $meta['page'] - 2);
    $end   = min($meta['total_pages'], $start + 4);
    $start = max(1, $end - 4);

    if ($start > 1) {
        $out .= '<a class="pagination__num" href="' . h($build(1)) . '">1</a>';
        if ($start > 2) $out .= '<span class="pagination__dots">…</span>';
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $meta['page'] ? ' pagination__num--active' : '';
        $out .= '<a class="pagination__num' . $active . '" href="' . h($build($i)) . '">' . $i . '</a>';
    }

    if ($end < $meta['total_pages']) {
        if ($end < $meta['total_pages'] - 1) $out .= '<span class="pagination__dots">…</span>';
        $out .= '<a class="pagination__num" href="' . h($build($meta['total_pages'])) . '">'
              . $meta['total_pages'] . '</a>';
    }

    // Next
    if ($meta['has_next']) {
        $out .= '<a class="pagination__link" href="' . h($build($meta['page'] + 1)) . '">'
              . 'Next <i data-lucide="chevron-right"></i></a>';
    } else {
        $out .= '<span class="pagination__link pagination__link--disabled">'
              . 'Next <i data-lucide="chevron-right"></i></span>';
    }

    $out .= '</nav>';

    // Summary
    $from = ($meta['page'] - 1) * $meta['per_page'] + 1;
    $to   = min($meta['page'] * $meta['per_page'], $meta['total']);
    $out .= '<div class="pagination__summary">Showing '
          . (int)$from . '–' . (int)$to . ' of ' . (int)$meta['total'] . '</div>';

    return $out;
}