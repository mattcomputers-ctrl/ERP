<?php

namespace App\Services;

class ReservationService
{
    private \PDO $db;
    private FIFOService $fifo;

    public function __construct(\PDO $db, FIFOService $fifo)
    {
        $this->db = $db;
        $this->fifo = $fifo;
    }

    /**
     * Create a reservation. Returns warning if insufficient available inventory.
     * Does NOT hard-block — caller decides based on permissions.
     */
    public function reserve(
        int $itemId,
        int $facilityId,
        float $quantity,
        string $reservationType,
        int $referenceId
    ): array {
        $available = $this->fifo->getAvailable($itemId, $facilityId);
        $warning = false;
        $message = '';
        $shortfall = 0;

        if ($available < $quantity) {
            $warning = true;
            $shortfall = $quantity - $available;
            $stmt = $this->db->prepare('SELECT item_code, description FROM items WHERE id = ?');
            $stmt->execute([$itemId]);
            $item = $stmt->fetch(\PDO::FETCH_ASSOC);
            $message = "Insufficient stock for {$item['item_code']} — {$item['description']}. "
                . "Available: {$available}, Requested: {$quantity}, Shortfall: {$shortfall}.";

            $allFacilities = $this->fifo->getAvailableAllFacilities($itemId);
            $suggestions = [];
            foreach ($allFacilities as $fId => $fData) {
                if ($fId != $facilityId && $fData['available'] > 0) {
                    $suggestions[] = "{$fData['available']} at {$fData['facility_name']}";
                }
            }
            if (!empty($suggestions)) {
                $message .= ' Transfer available: ' . implode(', ', $suggestions) . '.';
            }
        }

        $stmt = $this->db->prepare('
            INSERT INTO inventory_reservations (item_id, facility_id, quantity, reservation_type, reference_id)
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([$itemId, $facilityId, $quantity, $reservationType, $referenceId]);

        return [
            'warning' => $warning,
            'message' => $message,
            'shortfall' => $shortfall,
            'reservation_id' => (int)$this->db->lastInsertId(),
        ];
    }

    public function release(int $reservationId): void
    {
        $this->db->prepare('DELETE FROM inventory_reservations WHERE id = ?')->execute([$reservationId]);
    }

    public function releaseAllForReference(string $referenceType, int $referenceId): void
    {
        $this->db->prepare('DELETE FROM inventory_reservations WHERE reservation_type = ? AND reference_id = ?')
            ->execute([$referenceType, $referenceId]);
    }

    public function getReservedQuantity(int $itemId, int $facilityId): float
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(quantity), 0) FROM inventory_reservations WHERE item_id = ? AND facility_id = ?');
        $stmt->execute([$itemId, $facilityId]);
        return (float)$stmt->fetchColumn();
    }

    public function getBatchReserved(int $itemId, int $facilityId): float
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(quantity), 0) FROM inventory_reservations WHERE item_id = ? AND facility_id = ? AND reservation_type = "BATCH"');
        $stmt->execute([$itemId, $facilityId]);
        return (float)$stmt->fetchColumn();
    }

    public function getQuoteReserved(int $itemId, int $facilityId): float
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(quantity), 0) FROM inventory_reservations WHERE item_id = ? AND facility_id = ? AND reservation_type = "QUOTE"');
        $stmt->execute([$itemId, $facilityId]);
        return (float)$stmt->fetchColumn();
    }
}
