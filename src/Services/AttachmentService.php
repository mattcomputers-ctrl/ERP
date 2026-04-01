<?php

namespace App\Services;

/**
 * Manages file attachments linked to system entities.
 */
class AttachmentService
{
    private \PDO $db;
    private string $storagePath;

    private array $allowedTypes = [
        'application/pdf',
        'image/jpeg', 'image/jpg', 'image/png', 'image/tiff',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/msword',
        'application/vnd.ms-excel',
    ];

    private array $allowedExtensions = ['pdf','jpg','jpeg','png','tiff','tif','docx','xlsx','doc','xls'];

    public function __construct(\PDO $db, string $storagePath)
    {
        $this->db = $db;
        $this->storagePath = rtrim($storagePath, '/');
    }

    /**
     * Upload a file and attach it to a record.
     * $file = one entry from $_FILES
     */
    public function upload(string $recordType, int $recordId, array $file, ?string $notes, int $uploadedBy): array
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload error: ' . $file['error']);
        }

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->allowedExtensions)) {
            throw new \RuntimeException('File type not allowed: .' . $ext);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $this->allowedTypes)) {
            throw new \RuntimeException('File MIME type not allowed: ' . $mimeType);
        }

        $dir = $this->storagePath . '/' . $recordType . '/' . $recordId;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = uniqid('', true) . '.' . $ext;
        $fullPath = $dir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            throw new \RuntimeException('Failed to move uploaded file.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO attachments (record_type, record_id, filename, original_filename, file_size, mime_type, storage_path, notes, uploaded_by, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $recordType, $recordId, $filename, $file['name'],
            $file['size'], $mimeType, $fullPath, $notes, $uploadedBy
        ]);

        return $this->getById((int)$this->db->lastInsertId());
    }

    public function getForRecord(string $recordType, int $recordId): array
    {
        $stmt = $this->db->prepare(
            'SELECT a.*, u.username as uploaded_by_name
             FROM attachments a
             LEFT JOIN users u ON a.uploaded_by = u.id
             WHERE a.record_type = ? AND a.record_id = ?
             ORDER BY a.created_at DESC'
        );
        $stmt->execute([$recordType, $recordId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM attachments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    public function delete(int $id, int $userId): bool
    {
        $attachment = $this->getById($id);
        if (!$attachment) return false;

        if (file_exists($attachment['storage_path'])) {
            unlink($attachment['storage_path']);
        }

        $stmt = $this->db->prepare('DELETE FROM attachments WHERE id = ?');
        $stmt->execute([$id]);
        return true;
    }

    public function download(int $id): void
    {
        $attachment = $this->getById($id);
        if (!$attachment || !file_exists($attachment['storage_path'])) {
            http_response_code(404);
            exit('Attachment not found.');
        }

        header('Content-Type: ' . $attachment['mime_type']);
        header('Content-Disposition: attachment; filename="' . addslashes($attachment['original_filename']) . '"');
        header('Content-Length: ' . filesize($attachment['storage_path']));
        readfile($attachment['storage_path']);
        exit;
    }
}
