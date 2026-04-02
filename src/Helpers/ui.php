<?php

/**
 * Render a consistent status badge HTML string.
 */
function statusBadge(string $status): string
{
    $colors = [
        'OPEN' => 'green', 'ACTIVE' => 'green', 'CONFIRMED' => 'green', 'AVAILABLE' => 'green',
        'PASSED' => 'green', 'RECEIVED' => 'green', 'CLOSED' => 'green', 'PAID' => 'green',
        'ACCEPTED' => 'green', 'COMPLETE' => 'green', 'POSTED' => 'green', 'CONVERTED' => 'green',
        'DRAFT' => 'gray', 'PENDING' => 'gray', 'PENDING_INSPECTION' => 'gray', 'INACTIVE' => 'gray',
        'IN_PROGRESS' => 'blue', 'SENT' => 'blue', 'PARTIAL' => 'blue', 'SHIPPED' => 'blue',
        'SUBMITTED' => 'blue', 'IN_REVIEW' => 'blue', 'RESPONSE_RECEIVED' => 'blue',
        'CANCELLED' => 'red', 'FAILED' => 'red', 'VOID' => 'red', 'QUARANTINED' => 'red',
        'REJECTED' => 'red', 'DECLINED' => 'red', 'EXPIRED' => 'red',
        'ON_HOLD' => 'yellow', 'WARNING' => 'yellow', 'CONDITIONAL' => 'yellow', 'APPROVED' => 'yellow',
        'RUSH' => 'orange', 'URGENT' => 'orange',
    ];

    $color = $colors[strtoupper($status)] ?? 'gray';

    $classes = [
        'green' => 'badge-status-green',
        'gray' => 'badge-status-gray',
        'blue' => 'badge-status-blue',
        'red' => 'badge-status-red',
        'yellow' => 'badge-status-yellow',
        'orange' => 'badge-status-orange',
    ];

    $cls = $classes[$color] ?? $classes['gray'];
    $label = ucwords(strtolower(str_replace('_', ' ', $status)));

    return '<span class="badge ' . $cls . '" style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:9999px;font-size:11px;font-weight:500;">' . htmlspecialchars($label) . '</span>';
}
