<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class DocumentRenderer
{
    private \PDO $db;
    private array $cache = [];

    public function __construct(\PDO $db)
    {
        $this->db = $db;
    }

    public function render(string $templateKey, array $data, ?string $pageSize = null, ?string $orientation = null): string
    {
        $template = $this->getTemplate($templateKey);

        // Fallback: if no template in DB, return empty PDF
        $htmlContent = $template ? $template['html_content'] : '<p>Template "' . htmlspecialchars($templateKey) . '" not found.</p>';
        $footerHtml = $template['footer_html'] ?? '';

        $html = $this->merge($htmlContent, $data);
        $footer = $footerHtml ? $this->merge($footerHtml, $data) : '';

        // Wrap in full document if needed
        if (stripos($html, '<html') === false) {
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>
                body { font-family: Arial, sans-serif; font-size: 11pt; color: #222; margin: 0; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 5px 7px; }
                @page { margin: 0.75in; }
            </style></head><body>' . $html . '</body></html>';
        }

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper(
            $pageSize ?? ($template['page_size'] ?? 'letter'),
            $orientation ?? ($template['page_orientation'] ?? 'portrait')
        );
        $dompdf->render();

        return $dompdf->output();
    }

    public function merge(string $template, array $data): string
    {
        // Simple {{variable}} replacement
        $result = preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($data) {
            return htmlspecialchars((string)($data[$m[1]] ?? ''));
        }, $template);

        // {{#if variable}}...{{/if}}
        $result = preg_replace_callback('/\{\{#if (\w+)\}\}(.*?)\{\{\/if\}\}/s', function ($m) use ($data) {
            return !empty($data[$m[1]]) ? $m[2] : '';
        }, $result);

        // {{#unless variable}}...{{/unless}}
        $result = preg_replace_callback('/\{\{#unless (\w+)\}\}(.*?)\{\{\/unless\}\}/s', function ($m) use ($data) {
            return empty($data[$m[1]]) ? $m[2] : '';
        }, $result);

        // {{#each arrayVar}}...{{/each}}
        $result = preg_replace_callback('/\{\{#each (\w+)\}\}(.*?)\{\{\/each\}\}/s', function ($m) use ($data) {
            $items = $data[$m[1]] ?? [];
            if (!is_array($items)) return '';
            $out = '';
            foreach ($items as $item) {
                $itemData = is_array($item) ? $item : ['value' => $item];
                $out .= preg_replace_callback('/\{\{(\w+)\}\}/', function ($im) use ($itemData) {
                    return htmlspecialchars((string)($itemData[$im[1]] ?? ''));
                }, $m[2]);
            }
            return $out;
        }, $result);

        return $result;
    }

    public function getTemplate(string $key): ?array
    {
        if (isset($this->cache[$key])) return $this->cache[$key];
        $stmt = $this->db->prepare('SELECT * FROM document_templates WHERE template_key = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$key]);
        $t = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        if ($t) $this->cache[$key] = $t;
        return $t;
    }

    public function getAllTemplates(): array
    {
        return $this->db->query('SELECT id, template_key, template_name, page_size, page_orientation, is_active, updated_at FROM document_templates ORDER BY template_name')->fetchAll(\PDO::FETCH_ASSOC);
    }
}
