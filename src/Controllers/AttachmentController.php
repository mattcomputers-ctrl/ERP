<?php

namespace PrecisionInk\Controllers;

class AttachmentController extends BaseController
{
    public function upload(): void
    {
        $recordType = $_POST['record_type'] ?? '';
        $recordId = (int)($_POST['record_id'] ?? 0);
        $notes = trim($_POST['notes'] ?? '') ?: null;
        $userId = $this->currentUserId();

        if (!$recordType || !$recordId || !$userId) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid request.'], 400);
        }

        if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] === UPLOAD_ERR_NO_FILE) {
            $this->jsonResponse(['success' => false, 'error' => 'No file selected.'], 400);
        }

        try {
            $attachment = $this->attachmentService->upload($recordType, $recordId, $_FILES['attachment'], $notes, $userId);
            $this->jsonResponse(['success' => true, 'attachment' => $attachment]);
        } catch (\RuntimeException $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }

    public function download(string $id): void
    {
        $this->attachmentService->download((int)$id);
    }

    public function delete(string $id): void
    {
        $userId = $this->currentUserId();
        if (!$userId) {
            $this->jsonResponse(['success' => false, 'error' => 'Not authenticated.'], 401);
        }

        $result = $this->attachmentService->delete((int)$id, $userId);
        $this->jsonResponse(['success' => $result]);
    }
}
