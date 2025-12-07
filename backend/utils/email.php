<?php

/**
 * Placeholder email utility.
 *
 * In production, integrate with a real mailer (SMTP, SendGrid, etc.).
 */

function sendStaffInviteEmail(string $toEmail, string $fullName, string $password): bool
{
    // TODO: Implement actual email sending.
    // For now, this is just a stub that always returns true.
    // Example content:
    // "Hello {$fullName}, your staff account has been created. Email: {$toEmail}, Password: {$password}"
    return true;
}


