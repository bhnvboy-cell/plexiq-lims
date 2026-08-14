<?php
if (!empty($paginator) && (int)($paginator['lastPage'] ?? 0) > 1):
    $qs = $_GET;
    $page = max(1, (int)($paginator['currentPage'] ?? 1));
    $last = (int)($paginator['lastPage'] ?? 1);
    $total = (int)($paginator['total'] ?? 0);
    $build = function (int $p) use ($qs) {
        $qs['page'] = $p;
        return '?' . http_build_query($qs);
    };
?>
<nav aria-label="Pagination" class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($build($page - 1)) ?>">&laquo; Prev</a>
        </li>
        <li class="page-item disabled">
            <span class="page-link">Page <?= $page ?> of <?= $last ?> &mdash; <?= number_format($total) ?> records</span>
        </li>
        <li class="page-item <?= $page >= $last ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= e($build($page + 1)) ?>">Next &raquo;</a>
        </li>
    </ul>
</nav>
<?php endif; ?>
