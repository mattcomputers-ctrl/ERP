<?php

namespace App\Services;

class CreditService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function getExposure(int $customerId): array
    {
        $stmt = $this->db->prepare('SELECT credit_limit, ar_balance FROM customers WHERE id = ?');
        $stmt->execute([$customerId]);
        $customer = $stmt->fetch(\PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare('
            SELECT COALESCE(SUM((sol.ordered_quantity - sol.shipped_quantity) * sol.unit_price), 0) as open_so_value
            FROM sales_order_lines sol
            JOIN sales_orders so ON sol.so_id = so.id
            WHERE so.customer_id = ? AND so.status NOT IN ("SHIPPED","CANCELLED")
        ');
        $stmt->execute([$customerId]);
        $openSo = $stmt->fetchColumn();

        $arBalance = (float)($customer['ar_balance'] ?? 0);
        $creditLimit = (float)($customer['credit_limit'] ?? 0);
        $totalExposure = $arBalance + (float)$openSo;
        $availableCredit = $creditLimit > 0 ? $creditLimit - $totalExposure : null;

        return [
            'credit_limit' => $creditLimit,
            'ar_balance' => $arBalance,
            'open_so_value' => (float)$openSo,
            'total_exposure' => $totalExposure,
            'available_credit' => $availableCredit,
            'is_over_limit' => $creditLimit > 0 && $totalExposure > $creditLimit,
            'utilization_pct' => $creditLimit > 0 ? round($totalExposure / $creditLimit * 100, 1) : null,
        ];
    }

    public function checkAtOrderEntry(int $customerId): array
    {
        $exposure = $this->getExposure($customerId);
        return array_merge($exposure, ['show_warning' => $exposure['is_over_limit']]);
    }

    public function checkAtShipment(int $customerId, float $shipmentValue = 0): array
    {
        $exposure = $this->getExposure($customerId);
        $wouldExceed = $exposure['credit_limit'] > 0
            && ($exposure['total_exposure'] + $shipmentValue) > $exposure['credit_limit'];
        return array_merge($exposure, ['would_exceed' => $wouldExceed]);
    }
}
