<?php

namespace PrecisionInk\Controllers;

abstract class BaseController
{
    /**
     * Verify the current user has permission for the given module/action.
     */
    protected function checkPermission(string $module, string $action): bool
    {
        // TODO: implement role-based permission check
        return false;
    }

    /**
     * Return the facility the current session is scoped to.
     */
    protected function getActiveFacility(): ?int
    {
        // TODO: read from session / user profile
        return null;
    }

    /**
     * Write an entry to the audit-log table.
     */
    protected function auditLog(
        string $action,
        string $module,
        int    $recordId,
        mixed  $old = null,
        mixed  $new = null,
    ): void {
        // TODO: insert into audit_log table
    }

    /**
     * Send a JSON response and exit.
     */
    protected function jsonResponse(mixed $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Render a view template with the supplied data.
     */
    protected function renderView(string $template, array $data = []): void
    {
        extract($data);
        require __DIR__ . '/../Views/' . $template . '.php';
    }

    /**
     * Return the currently authenticated user record.
     */
    protected function currentUser(): ?array
    {
        // TODO: return user from session
        return $_SESSION['user'] ?? null;
    }
}
