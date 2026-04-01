<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private \PDO $db;
    private ?int $currentUserId;

    public function __construct(\PDO $db, ?int $currentUserId = null)
    {
        $this->db = $db;
        $this->currentUserId = $currentUserId;
    }

    /**
     * Send an email. This is the ONLY method in the entire codebase that sends email.
     * All modules call this method — never PHPMailer directly.
     *
     * @param string $templateType  e.g. 'invoice', 'quote', 'coa', 'purchase_order'
     * @param array $toAddresses  ['email@example.com', ...] or [['email' => '...', 'name' => '...']]
     * @param array $mergeData  ['customer_name' => 'Acme Corp', 'invoice_number' => 'SHP-0001', ...]
     * @param string|null $attachmentPath  Absolute path to file to attach (or null)
     * @param string|null $attachmentName  Display filename for attachment (or null)
     * @param string $referenceType  e.g. 'invoice', 'batch_ticket', 'purchase_order'
     * @param int $referenceId  Primary key of the reference record
     * @return bool  true on success, false on failure
     */
    public function send(
        string $templateType,
        array $toAddresses,
        array $mergeData = [],
        ?string $attachmentPath = null,
        ?string $attachmentName = null,
        string $referenceType = '',
        int $referenceId = 0
    ): bool {
        // 1. Load template
        [$subject, $body] = $this->loadTemplate($templateType, $mergeData);

        // 2. Append signature
        $signature = $this->getSetting('email_signature', '');
        if ($signature) {
            $body .= "\n\n" . $signature;
        }

        // 3. Build recipient list for logging
        $recipientList = $this->normalizeAddresses($toAddresses);
        $recipientString = implode(', ', array_column($recipientList, 'email'));

        // 4. Send via PHPMailer
        $success = false;
        $failureReason = null;

        try {
            $mail = $this->buildMailer();
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->isHTML(true);
            $mail->AltBody = strip_tags($body);

            foreach ($recipientList as $recipient) {
                $mail->addAddress($recipient['email'], $recipient['name'] ?? '');
            }

            if ($attachmentPath && file_exists($attachmentPath)) {
                $mail->addAttachment($attachmentPath, $attachmentName ?? basename($attachmentPath));
            }

            $mail->send();
            $success = true;

        } catch (Exception $e) {
            $failureReason = $e->getMessage();
            $success = false;
        } catch (\Exception $e) {
            $failureReason = $e->getMessage();
            $success = false;
        }

        // 5. Log the attempt (always — success or failure)
        $this->logEmail(
            $templateType,
            $referenceType,
            $referenceId,
            $recipientString,
            $subject,
            $success ? 'SENT' : 'FAILED',
            $failureReason,
            $attachmentName ?? ($attachmentPath ? basename($attachmentPath) : null)
        );

        return $success;
    }

    private function buildMailer(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = $this->getSetting('smtp_host', 'localhost');
        $mail->Port = (int)$this->getSetting('smtp_port', '587');
        $mail->SMTPAuth = true;
        $mail->Username = $this->getSetting('smtp_username', '');
        $mail->Password = $this->decryptPassword($this->getSetting('smtp_password', ''));
        $mail->FromName = $this->getSetting('smtp_from_name', 'Precision Ink ERP');
        $mail->From = $this->getSetting('smtp_from_address', '');

        $encryption = $this->getSetting('smtp_encryption', 'tls');
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } else {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }

        return $mail;
    }

    private function loadTemplate(string $templateType, array $mergeData): array
    {
        // Try database template first
        $stmt = $this->db->prepare(
            'SELECT subject, body FROM email_templates WHERE template_type = ? AND active = 1 LIMIT 1'
        );
        $stmt->execute([$templateType]);
        $template = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($template) {
            $subject = $this->substituteMergeFields($template['subject'], $mergeData);
            $body = $this->substituteMergeFields($template['body'], $mergeData);
            return [$subject, $body];
        }

        // Fallback templates when none configured
        return $this->getFallbackTemplate($templateType, $mergeData);
    }

    private function getFallbackTemplate(string $templateType, array $mergeData): array
    {
        $fallbacks = [
            'invoice' => [
                'Invoice {invoice_number} from Precision Ink Corporation',
                'Dear {customer_name},<br><br>Please find your invoice attached.<br><br>Amount Due: {amount_due}<br>Due Date: {due_date}'
            ],
            'order_acknowledgment' => [
                'Order Confirmation {order_number} — Precision Ink Corporation',
                'Dear {customer_name},<br><br>Thank you for your order. Please find your order confirmation attached.<br><br>Estimated Ship Date: {promised_ship_date}'
            ],
            'quote' => [
                'Quote {quote_number} from Precision Ink Corporation',
                'Dear {customer_name},<br><br>Please find your quote attached. This quote is valid until {expiration_date}.'
            ],
            'coa' => [
                'Certificate of Analysis — {item_description} Lot {batch_number}',
                'Dear {customer_name},<br><br>Please find your Certificate of Analysis attached.'
            ],
            'purchase_order' => [
                'Purchase Order {po_number} — Precision Ink Corporation',
                'Dear {supplier_name},<br><br>Please find our purchase order attached. Expected delivery: {expected_delivery}.'
            ],
            'scar' => [
                'Supplier Corrective Action Request {scar_number}',
                'Dear {supplier_name},<br><br>Please find the attached SCAR for your review and response. Due date: {due_date}.'
            ],
            'credit_memo' => [
                'Credit Memo {rma_number} — Precision Ink Corporation',
                'Dear {customer_name},<br><br>Please find your credit memo attached. Credit amount: {credit_amount}.'
            ],
            'notification' => [
                '{subject}',
                '{body}'
            ],
        ];

        $template = $fallbacks[$templateType] ?? [
            'Message from Precision Ink ERP',
            'Please see the attached document.'
        ];

        return [
            $this->substituteMergeFields($template[0], $mergeData),
            $this->substituteMergeFields($template[1], $mergeData),
        ];
    }

    private function substituteMergeFields(string $text, array $mergeData): string
    {
        foreach ($mergeData as $key => $value) {
            $text = str_replace('{' . $key . '}', (string)($value ?? ''), $text);
        }
        return $text;
    }

    private function normalizeAddresses(array $addresses): array
    {
        $result = [];
        foreach ($addresses as $address) {
            if (is_string($address)) {
                $result[] = ['email' => $address, 'name' => ''];
            } elseif (is_array($address) && isset($address['email'])) {
                $result[] = ['email' => $address['email'], 'name' => $address['name'] ?? ''];
            }
        }
        return $result;
    }

    private function logEmail(
        string $documentType,
        string $referenceType,
        int $referenceId,
        string $recipients,
        string $subject,
        string $status,
        ?string $failureReason,
        ?string $attachmentFilename
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO outbound_email_log
             (sent_by, document_type, reference_type, reference_id, recipients, subject, status, failure_reason, attachment_filename, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $this->currentUserId,
            $documentType,
            $referenceType ?: null,
            $referenceId ?: null,
            $recipients,
            $subject,
            $status,
            $failureReason,
            $attachmentFilename,
        ]);
    }

    private function getSetting(string $key, string $default = ''): string
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ? (string)$row['setting_value'] : $default;
    }

    private function decryptPassword(string $encrypted): string
    {
        if (empty($encrypted)) {
            return '';
        }
        // Match the encryption format used in SettingsController:
        // base64_encode(iv . '::' . openssl_encrypt(plain, aes-256-cbc, key, 0, iv))
        $config = require __DIR__ . '/../../config/config.php';
        $key = hash('sha256', $config['APP_KEY'] ?? 'default-key', true);
        $decoded = base64_decode($encrypted);
        $parts = explode('::', $decoded, 2);
        if (count($parts) !== 2) {
            return $encrypted; // Not encrypted, return as-is
        }
        [$iv, $cipherText] = $parts;
        $decrypted = openssl_decrypt($cipherText, 'aes-256-cbc', $key, 0, $iv);
        return $decrypted !== false ? $decrypted : $encrypted;
    }
}
