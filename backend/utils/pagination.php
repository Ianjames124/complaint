<?php

/**
 * Simple pagination helper.
 *
 * Usage:
 *   [$limit, $offset, $page, $perPage] = getPaginationParams();
 */

function getPaginationParams(): array
{
    $page    = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
    $perPage = isset($_GET['per_page']) ? max(1, min(100, (int) $_GET['per_page'])) : 20;

    $offset = ($page - 1) * $perPage;
    $limit  = $perPage;

    return [$limit, $offset, $page, $perPage];
}


