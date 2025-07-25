<?php
declare(strict_types=1);
namespace App\Domain\User\Services;

use App\Domain\User\ValueObjects\UserId;

/**
 * Image Upload Service - Profilbild-Handling mit Resizing
 */
class ImageUploadService
{
    private const array ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    private const int MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    private const int PROFILE_SIZE = 150; // 150x150 px
    private const int THUMBNAIL_SIZE = 50; // 50x50 px

    public function __construct(
        private readonly string $uploadPath = 'storage/uploads/profiles'
    )
    {
    }

    /**
     * Verarbeitet Profilbild-Upload
     */
    public function processProfileImage(array $uploadedFile, UserId $userId): string
    {
        $this->validateUpload($uploadedFile);

        // Eindeutigen Dateinamen generieren
        $extension = $this->getFileExtension($uploadedFile['type']);
        $filename = $this->generateFilename($userId, $extension);

        // Pfade erstellen
        $fullPath = $this->uploadPath . '/' . $filename;
        $thumbnailPath = $this->uploadPath . '/thumbs/' . $filename;

        // Verzeichnisse erstellen falls sie nicht existieren
        $this->ensureDirectoriesExist();

        // Bild verarbeiten und speichern
        $this->processAndSaveImage($uploadedFile['tmp_name'], $fullPath, self::PROFILE_SIZE);
        $this->processAndSaveImage($uploadedFile['tmp_name'], $thumbnailPath, self::THUMBNAIL_SIZE);

        return $filename;
    }

    /**
     * Löscht Profilbild
     */
    public function deleteProfileImage(string $filename): bool
    {
        $fullPath = $this->uploadPath . '/' . $filename;
        $thumbnailPath = $this->uploadPath . '/thumbs/' . $filename;

        $success = true;

        if (file_exists($fullPath)) {
            $success = unlink($fullPath) && $success;
        }

        if (file_exists($thumbnailPath)) {
            $success = unlink($thumbnailPath) && $success;
        }

        return $success;
    }

    /**
     * Validiert Upload
     */
    private function validateUpload(array $uploadedFile): void
    {
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('File upload failed');
        }

        if ($uploadedFile['size'] > self::MAX_FILE_SIZE) {
            throw new \InvalidArgumentException('File too large (max 5MB)');
        }

        if (!in_array($uploadedFile['type'], self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('Invalid file type. Allowed: JPEG, PNG, GIF, WebP');
        }

        // Zusätzliche Sicherheitsprüfung
        $imageInfo = getimagesize($uploadedFile['tmp_name']);
        if ($imageInfo === false) {
            throw new \InvalidArgumentException('Invalid image file');
        }
    }

    /**
     * Verarbeitet und speichert Bild mit Resizing
     */
    private function processAndSaveImage(string $sourcePath, string $targetPath, int $size): void
    {
        $imageInfo = getimagesize($sourcePath);
        $sourceImage = match ($imageInfo['mime']) {
            'image/jpeg' => imagecreatefromjpeg($sourcePath),
            'image/png' => imagecreatefrompng($sourcePath),
            'image/gif' => imagecreatefromgif($sourcePath),
            'image/webp' => imagecreatefromwebp($sourcePath),
            default => throw new \InvalidArgumentException('Unsupported image type')
        };

        if (!$sourceImage) {
            throw new \RuntimeException('Failed to create image resource');
        }

        // Resize-Berechnung (quadratisch mit Crop)
        $sourceWidth = imagesx($sourceImage);
        $sourceHeight = imagesy($sourceImage);

        $cropSize = min($sourceWidth, $sourceHeight);
        $cropX = ($sourceWidth - $cropSize) / 2;
        $cropY = ($sourceHeight - $cropSize) / 2;

        // Ziel-Bild erstellen
        $targetImage = imagecreatetruecolor($size, $size);

        // Transparenz für PNG/GIF erhalten
        if ($imageInfo['mime'] === 'image/png' || $imageInfo['mime'] === 'image/gif') {
            imagealphablending($targetImage, false);
            imagesavealpha($targetImage, true);
            $transparent = imagecolorallocatealpha($targetImage, 255, 255, 255, 127);
            imagefill($targetImage, 0, 0, $transparent);
        }

        // Resize und Crop
        imagecopyresampled(
            $targetImage, $sourceImage,
            0, 0, (int)$cropX, (int)$cropY,
            $size, $size, $cropSize, $cropSize
        );

        // Speichern (immer als JPEG für Konsistenz)
        imagejpeg($targetImage, $targetPath, 90);

        // Aufräumen
        imagedestroy($sourceImage);
        imagedestroy($targetImage);
    }

    /**
     * Generiert eindeutigen Dateinamen
     */
    private function generateFilename(UserId $userId, string $extension): string
    {
        return sprintf(
            'user_%d_%s.%s',
            $userId->toInt(),
            bin2hex(random_bytes(8)),
            $extension
        );
    }

    /**
     * Holt Dateierweiterung aus MIME-Type
     */
    private function getFileExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'jpg'
        };
    }

    /**
     * Erstellt Upload-Verzeichnisse
     */
    private function ensureDirectoriesExist(): void
    {
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }

        if (!is_dir($this->uploadPath . '/thumbs')) {
            mkdir($this->uploadPath . '/thumbs', 0755, true);
        }
    }

    /**
     * Holt URL für Profilbild
     */
    public function getProfileImageUrl(string $filename): string
    {
        return '/uploads/profiles/' . $filename;
    }

    /**
     * Holt URL für Thumbnail
     */
    public function getThumbnailUrl(string $filename): string
    {
        return '/uploads/profiles/thumbs/' . $filename;
    }
}