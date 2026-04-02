<?php

namespace PrecisionInk\Controllers;

class QbSyncController extends BaseController
{
    // ── Dashboard ───────────────────────────────────────────────────

    public function dashboard(): void
    {
        if (!$this->checkPermission('settings', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $qbDesktop = new \App\Services\QbDesktopService($this->db());
        $counts = $qbDesktop->getUnsyncedCounts();

        $qbOnline = new \App\Services\QbOnlineService($this->db());
        $isConnected = $qbOnline->isConnected();
        $companyInfo = $isConnected ? $qbOnline->getCompanyInfo() : null;

        $mode = $this->getQbSetting('qb_mode', 'DESKTOP');

        $this->renderView('qb_sync/dashboard', [
            'counts' => $counts, 'mode' => $mode,
            'isConnected' => $isConnected, 'companyInfo' => $companyInfo,
        ]);
    }

    // ── IIF Exports ─────────────────────────────────────────────────

    public function exportInvoices(): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $includeAll = !empty($_POST['include_all']);
        $qb = new \App\Services\QbDesktopService($this->db());
        $iif = $qb->exportInvoices($includeAll);
        $this->downloadIif('invoices_' . date('Y-m-d'), $iif);
    }

    public function exportBills(): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $includeAll = !empty($_POST['include_all']);
        $qb = new \App\Services\QbDesktopService($this->db());
        $iif = $qb->exportBills($includeAll);
        $this->downloadIif('bills_' . date('Y-m-d'), $iif);
    }

    public function exportCustomers(): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $qb = new \App\Services\QbDesktopService($this->db());
        $iif = $qb->exportCustomers();
        $this->downloadIif('customers_' . date('Y-m-d'), $iif);
    }

    public function exportVendors(): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $qb = new \App\Services\QbDesktopService($this->db());
        $iif = $qb->exportVendors();
        $this->downloadIif('vendors_' . date('Y-m-d'), $iif);
    }

    public function exportItems(): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $qb = new \App\Services\QbDesktopService($this->db());
        $iif = $qb->exportItems();
        $this->downloadIif('items_' . date('Y-m-d'), $iif);
    }

    public function exportInventory(): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $qb = new \App\Services\QbDesktopService($this->db());
        $iif = $qb->exportInventoryValuation();
        $this->downloadIif('inventory_valuation_' . date('Y-m-d'), $iif);
    }

    // ── OAuth ───────────────────────────────────────────────────────

    public function oauthConnect(): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $qb = new \App\Services\QbOnlineService($this->db());
        $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'] . '/qb-sync/oauth/callback';
        $authUrl = $qb->getAuthUrl($redirectUri);
        header('Location: ' . $authUrl);
        exit;
    }

    public function oauthCallback(): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $code = $_GET['code'] ?? '';
        $realmId = $_GET['realmId'] ?? '';

        if (!$code || !$realmId) {
            $this->toast('OAuth failed — missing code or realm ID.', 'error');
            $this->redirect('/qb-sync');
            return;
        }

        $qb = new \App\Services\QbOnlineService($this->db());
        $redirectUri = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . $_SERVER['HTTP_HOST'] . '/qb-sync/oauth/callback';

        if ($qb->exchangeCode($code, $redirectUri, $realmId)) {
            $this->toast('Connected to QuickBooks Online.', 'success');
        } else {
            $this->toast('Failed to connect to QuickBooks Online.', 'error');
        }
        $this->redirect('/qb-sync');
    }

    public function oauthDisconnect(): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }
        $qb = new \App\Services\QbOnlineService($this->db());
        $qb->disconnect();
        $this->toast('Disconnected from QuickBooks Online.', 'success');
        $this->redirect('/qb-sync');
    }

    // ── Sync Log ────────────────────────────────────────────────────

    public function syncLog(): void
    {
        if (!$this->checkPermission('settings', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $filterStatus = $_GET['status'] ?? '';
        $filterType = $_GET['type'] ?? '';

        $where = '1=1';
        $params = [];
        if ($filterStatus) { $where .= ' AND status = ?'; $params[] = $filterStatus; }
        if ($filterType) { $where .= ' AND sync_type = ?'; $params[] = $filterType; }

        $stmt = $this->db()->prepare("
            SELECT * FROM qb_sync_log WHERE {$where} ORDER BY created_at DESC LIMIT 200
        ");
        $stmt->execute($params);

        $this->renderView('qb_sync/log', [
            'logs' => $stmt->fetchAll(), 'filterStatus' => $filterStatus, 'filterType' => $filterType,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────

    private function downloadIif(string $filename, string $content): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '.iif"');
        header('Content-Length: ' . strlen($content));
        echo $content;
        exit;
    }

    private function getQbSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db()->prepare('SELECT setting_value FROM qb_settings WHERE setting_key = ?');
        $stmt->execute([$key]);
        return (string)($stmt->fetchColumn() ?: $default);
    }
}
