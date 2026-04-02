<?php

namespace PrecisionInk\Controllers;

use App\Services\ImportService;
use App\Services\ExportService;

class ImportExportController extends BaseController
{
    private function getImportService(): ImportService
    {
        return new ImportService($this->db());
    }

    private function getExportService(): ExportService
    {
        return new ExportService($this->db());
    }

    private function getSupportedImports(): array
    {
        return [
            'items' => 'Items',
            'customers' => 'Customers',
            'suppliers' => 'Suppliers',
            'customer-pricing' => 'Customer Prices',
            'opening-inventory' => 'Inventory Adjustments',
            'approved-vendors' => 'Approved Vendor List',
        ];
    }

    private function getSupportedExports(): array
    {
        return [
            'items' => 'Items',
            'customers' => 'Customers',
            'suppliers' => 'Suppliers',
            'inventory' => 'Inventory (FIFO Lots)',
            'open-sales' => 'Open Sales Orders',
            'open-purchases' => 'Open Purchase Orders',
        ];
    }

    // ── Import Landing ──────────────────────────────────────────────

    public function importLanding(): void
    {
        if (!$this->checkPermission('settings', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('import_export/import_landing', ['modules' => $this->getSupportedImports()]);
    }

    public function importModule(string $module): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $modules = $this->getSupportedImports();
        if (!isset($modules[$module])) { $this->toast('Unknown module.', 'error'); $this->redirect('/import'); return; }

        $this->renderView('import_export/import_module', [
            'module' => $module, 'moduleName' => $modules[$module],
            'preview' => null, 'results' => null,
        ]);
    }

    public function processImport(string $module): void
    {
        if (!$this->checkPermission('settings', 'edit')) { http_response_code(403); echo 'Access Denied'; exit; }

        $modules = $this->getSupportedImports();
        if (!isset($modules[$module])) { $this->toast('Unknown module.', 'error'); $this->redirect('/import'); return; }

        $action = $_POST['action'] ?? 'preview';
        $file = $_FILES['file'] ?? null;

        $svc = $this->getImportService();

        if ($action === 'preview' && $file && $file['error'] === UPLOAD_ERR_OK) {
            $parsed = ImportService::parseCsvUpload($file);
            if (!empty($parsed['error'])) {
                $this->toast($parsed['error'], 'error');
                $this->redirect("/import/{$module}");
                return;
            }

            // Store file for commit step
            $tmpPath = sys_get_temp_dir() . '/import_' . uniqid() . '.csv';
            move_uploaded_file($file['tmp_name'], $tmpPath);
            $_SESSION['import_tmp_file'] = $tmpPath;
            $_SESSION['import_module'] = $module;

            // Dry run
            $dryRunResults = $svc->dryRun($module, $parsed['rows']);
            $errorCount = count(array_filter($dryRunResults, fn($r) => $r['status'] === 'error'));

            $this->renderView('import_export/import_module', [
                'module' => $module, 'moduleName' => $modules[$module],
                'preview' => ['rows' => array_slice($parsed['rows'], 0, 10), 'headers' => $parsed['headers'], 'total' => count($parsed['rows'])],
                'dryRun' => $dryRunResults, 'errorCount' => $errorCount,
                'results' => null,
            ]);
            return;
        }

        if ($action === 'commit') {
            $tmpPath = $_SESSION['import_tmp_file'] ?? '';
            if (!$tmpPath || !file_exists($tmpPath) || ($_SESSION['import_module'] ?? '') !== $module) {
                $this->toast('Upload session expired. Please re-upload.', 'error');
                $this->redirect("/import/{$module}");
                return;
            }

            $parsed = ImportService::parseCsvUpload(['tmp_name' => $tmpPath, 'error' => 0, 'name' => 'import.csv', 'type' => 'text/csv', 'size' => filesize($tmpPath)]);
            $results = $svc->commit($module, $parsed['rows']);

            @unlink($tmpPath);
            unset($_SESSION['import_tmp_file'], $_SESSION['import_module']);

            $this->auditLog('CREATE', 'import', 0, [], ['module' => $module, 'imported' => $results['imported'] ?? 0, 'skipped' => $results['skipped'] ?? 0]);

            $this->renderView('import_export/import_module', [
                'module' => $module, 'moduleName' => $modules[$module],
                'preview' => null, 'dryRun' => null,
                'results' => $results,
            ]);
            return;
        }

        $this->redirect("/import/{$module}");
    }

    public function downloadTemplate(string $module): void
    {
        $svc = $this->getImportService();
        $template = $svc->getTemplate($module);
        if (!$template) { $this->toast('No template for this module.', 'error'); $this->redirect('/import'); return; }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $module . '_template.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $template['headers']);
        if (!empty($template['example'])) fputcsv($out, $template['example']);
        fclose($out);
        exit;
    }

    public function downloadSample(string $module): void
    {
        $svc = $this->getImportService();
        $template = $svc->getTemplate($module);
        if (!$template) { $this->toast('No sample for this module.', 'error'); $this->redirect('/import'); return; }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $module . '_sample.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $template['headers']);
        if (!empty($template['example'])) {
            fputcsv($out, $template['example']);
            // Add 2 more variations
            $ex2 = $template['example'];
            if (isset($ex2[0])) $ex2[0] .= '-002';
            fputcsv($out, $ex2);
            $ex3 = $template['example'];
            if (isset($ex3[0])) $ex3[0] .= '-003';
            fputcsv($out, $ex3);
        }
        fclose($out);
        exit;
    }

    // ── Export Landing ───────────────────────────────────────────────

    public function exportLanding(): void
    {
        if (!$this->checkPermission('settings', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }
        $this->renderView('import_export/export_landing', ['modules' => $this->getSupportedExports()]);
    }

    public function exportModule(string $module): void
    {
        if (!$this->checkPermission('settings', 'view')) { http_response_code(403); echo 'Access Denied'; exit; }

        $svc = $this->getExportService();
        $csv = $svc->export($module);
        $filename = $svc->getFilename($module);

        if (!$csv) { $this->toast('No data or unknown module.', 'error'); $this->redirect('/export'); return; }

        $this->auditLog('CREATE', 'export', 0, [], ['module' => $module]);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $csv;
        exit;
    }
}
