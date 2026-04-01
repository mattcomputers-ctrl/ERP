<?php

namespace App\Services;

/**
 * Manages facility records and multi-facility operations.
 */
class FacilityService
{
    private \PDO $db;

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Get the current user's active facility.
     * Reads active_facility_id from session.
     * Falls back to the system default facility.
     */
    public function getActiveFacility(int $userId): array
    {
        $facilityId = $_SESSION['active_facility_id'] ?? null;

        if ($facilityId) {
            // Verify user still has access
            if ($this->canAccessFacility($userId, $facilityId)) {
                $stmt = $this->db->prepare('SELECT * FROM facilities WHERE id = ? AND active = 1');
                $stmt->execute([$facilityId]);
                $facility = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($facility) return $facility;
            }
        }

        // Fall back to default
        return $this->getDefaultFacility();
    }

    /**
     * Set the active facility for a user session.
     * Validates the user has access first.
     */
    public function setActiveFacility(int $userId, int $facilityId): bool
    {
        if (!$this->canAccessFacility($userId, $facilityId)) {
            return false;
        }
        $_SESSION['active_facility_id'] = $facilityId;
        return true;
    }

    /**
     * Get all facilities a user can access.
     * Empty restriction list = all active facilities.
     */
    public function getUserFacilities(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT facility_id FROM user_facility_restrictions WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        $restrictions = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($restrictions)) {
            // No restrictions — return all active facilities
            return $this->getAllActiveFacilities();
        }

        $placeholders = implode(',', array_fill(0, count($restrictions), '?'));
        $stmt = $this->db->prepare(
            "SELECT * FROM facilities WHERE id IN ($placeholders) AND active = 1 ORDER BY name"
        );
        $stmt->execute($restrictions);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function canAccessFacility(int $userId, int $facilityId): bool
    {
        $facilities = $this->getUserFacilities($userId);
        foreach ($facilities as $f) {
            if ((int)$f['id'] === $facilityId) return true;
        }
        return false;
    }

    public function getAllActiveFacilities(): array
    {
        $stmt = $this->db->query('SELECT * FROM facilities WHERE active = 1 ORDER BY is_default DESC, name ASC');
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getDefaultFacility(): array
    {
        $stmt = $this->db->query('SELECT * FROM facilities WHERE is_default = 1 AND active = 1 LIMIT 1');
        $facility = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($facility) return $facility;

        // Absolute fallback — first active facility
        $stmt = $this->db->query('SELECT * FROM facilities WHERE active = 1 ORDER BY id ASC LIMIT 1');
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: ['id' => 1, 'code' => 'DEFAULT', 'name' => 'Main Facility'];
    }

    public function getFacilityById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM facilities WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get the storage location for an item at a facility.
     */
    public function getItemLocation(int $itemId, int $facilityId): string
    {
        $stmt = $this->db->prepare(
            'SELECT location FROM item_facility_locations WHERE item_id = ? AND facility_id = ?'
        );
        $stmt->execute([$itemId, $facilityId]);
        return (string)($stmt->fetchColumn() ?: '');
    }

    /**
     * Get all item locations for a facility.
     * Returns ['item_id' => 'location', ...] map.
     */
    public function getFacilityLocations(int $facilityId): array
    {
        $stmt = $this->db->prepare(
            'SELECT item_id, location FROM item_facility_locations WHERE facility_id = ?'
        );
        $stmt->execute([$facilityId]);
        return $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
    }

    public function getActiveFacilityCount(): int
    {
        return (int)$this->db->query('SELECT COUNT(*) FROM facilities WHERE active = 1')->fetchColumn();
    }
}
