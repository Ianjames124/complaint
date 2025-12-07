<?php

require_once __DIR__ . '/auth.php';

/**
 * Convenience role helpers for endpoints.
 */

function requireAdmin(): array
{
    return requireRole(['admin']);
}

function requireStaff(): array
{
    return requireRole(['staff']);
}

function requireCitizen(): array
{
    return requireRole(['citizen']);
}


