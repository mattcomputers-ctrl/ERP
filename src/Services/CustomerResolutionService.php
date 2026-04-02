<?php

namespace App\Services;

class CustomerResolutionService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function resolve(int $shipToId, string $field): array
    {
        $allowed = ['sales_rep_id', 'default_ship_via_id', 'payment_terms_id', 'credit_limit'];
        if (!in_array($field, $allowed)) return ['value' => null, 'inherited' => false];

        $stmt = $this->db->prepare(
            "SELECT st.{$field} as ship_to_value, c.{$field} as customer_value
             FROM ship_to_locations st
             JOIN customers c ON st.customer_id = c.id
             WHERE st.id = ?"
        );
        $stmt->execute([$shipToId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return ['value' => null, 'inherited' => false];

        if ($row['ship_to_value'] !== null) {
            return ['value' => $row['ship_to_value'], 'inherited' => false];
        }
        return ['value' => $row['customer_value'], 'inherited' => true];
    }

    public function getEffectiveInternalNotes(int $customerId, int $shipToId): string
    {
        $stmt = $this->db->prepare(
            'SELECT c.default_internal_notes as customer_notes, st.default_internal_notes as ship_to_notes
             FROM customers c
             JOIN ship_to_locations st ON st.id = ? AND st.customer_id = c.id
             WHERE c.id = ?'
        );
        $stmt->execute([$shipToId, $customerId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return '';

        $parts = array_filter([
            trim($row['customer_notes'] ?? ''),
            trim($row['ship_to_notes'] ?? '')
        ]);
        return implode("\n\n", $parts);
    }

    public function resolveAll(int $shipToId): array
    {
        $stmt = $this->db->prepare(
            'SELECT st.*, c.sales_rep_id as c_sales_rep_id,
             c.default_ship_via_id as c_ship_via_id,
             c.payment_terms_id as c_payment_terms_id,
             c.credit_limit as c_credit_limit,
             c.default_internal_notes as c_internal_notes
             FROM ship_to_locations st
             JOIN customers c ON st.customer_id = c.id
             WHERE st.id = ?'
        );
        $stmt->execute([$shipToId]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$row) return [];

        $resolved = [];
        foreach (['sales_rep_id' => 'c_sales_rep_id',
                  'default_ship_via_id' => 'c_ship_via_id',
                  'payment_terms_id' => 'c_payment_terms_id',
                  'credit_limit' => 'c_credit_limit'] as $field => $fallback) {
            $resolved[$field] = [
                'value' => $row[$field] ?? $row[$fallback],
                'inherited' => ($row[$field] === null)
            ];
        }
        return $resolved;
    }
}
