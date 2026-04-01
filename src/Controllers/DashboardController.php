<?php

namespace PrecisionInk\Controllers;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $user = $this->currentUser();
        $userId = $user['id'] ?? 0;
        $groupId = $user['group_id'] ?? null;

        $announcements = $this->getActiveAnnouncements((int) $userId, $groupId ? (int) $groupId : null);

        $this->renderView('dashboard', [
            'title'         => 'Dashboard',
            'announcements' => $announcements,
        ]);
    }

    private function getActiveAnnouncements(int $userId, ?int $groupId): array
    {
        $stmt = $this->db()->prepare("
            SELECT a.*
            FROM announcements a
            WHERE a.active = 1
              AND a.start_date <= CURDATE()
              AND (a.end_date IS NULL OR a.end_date >= CURDATE())
              AND a.id NOT IN (
                  SELECT announcement_id FROM announcement_dismissals WHERE user_id = ?
              )
              AND (
                  a.target_all = 1
                  OR EXISTS (
                      SELECT 1 FROM announcement_target_groups atg
                      WHERE atg.announcement_id = a.id AND atg.group_id = ?
                  )
              )
            ORDER BY FIELD(a.priority, 'URGENT', 'WARNING', 'INFO'), a.start_date DESC
        ");
        $stmt->execute([$userId, $groupId ?? 0]);
        return $stmt->fetchAll();
    }
}
