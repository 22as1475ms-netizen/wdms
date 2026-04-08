<?php
require_once __DIR__ . "/../models/Document.php";
require_once __DIR__ . "/../models/Version.php";
require_once __DIR__ . "/../models/AuditLog.php";
require_once __DIR__ . "/StorageService.php";

class DocumentService {
  private const EDITABLE_EXTENSIONS = ['docx', 'xlsx', 'xls'];
  private const ALLOWED_UPLOAD_MIME_TYPES = [
    'csv' => ['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel'],
    'doc' => ['application/msword', 'application/octet-stream'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/octet-stream'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'pdf' => ['application/pdf'],
    'png' => ['image/png'],
    'ppt' => ['application/vnd.ms-powerpoint', 'application/octet-stream'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/octet-stream'],
    'txt' => ['text/plain'],
    'xls' => ['application/vnd.ms-excel', 'application/octet-stream'],
    'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
  ];

  public static function upload(
    PDO $pdo,
    array $file,
    int $ownerId,
    ?int $folderId,
    ?int $actorId = null,
    string $storageArea = 'PRIVATE',
    ?int $divisionId = null,
    array $metadata = []
  ): int {
    if (!isset($file['tmp_name']) || !self::isAcceptedUploadSource((string)$file['tmp_name'])) {
      throw new RuntimeException("Invalid upload");
    }

    $original = self::sanitizeFilename((string)($file['name'] ?? 'file'));
    self::assertUploadConstraints($file, $actorId ?? $ownerId);

    $actorId = $actorId ?? $ownerId;
    $storageArea = Document::normalizeStorageArea($storageArea);
    $docId = Document::create($pdo, $ownerId, $folderId, $original, $storageArea, $divisionId, $metadata);
    self::storeUploadedVersion($pdo, $docId, $file, $actorId);
    AuditLog::add($pdo, $actorId, "Uploaded document", $docId, $original);

    return $docId;
  }

  public static function uploadNewVersion(PDO $pdo, int $docId, array $file, int $userId): int {
    $doc = Document::get($pdo, $docId);
    if (!$doc) {
      throw new RuntimeException("Document not found");
    }

    $version = self::storeUploadedVersion($pdo, $docId, $file, $userId);
    AuditLog::add($pdo, $userId, "Uploaded new version", $docId, "version=".$version);

    return $version;
  }

  public static function restoreVersion(PDO $pdo, int $docId, int $versionId, int $userId): int {
    $doc = Document::get($pdo, $docId);
    $source = Version::get($pdo, $versionId);
    if (!$doc || !$source || (int)$source['document_id'] !== $docId) {
      throw new RuntimeException("Version not found");
    }

    $sourcePath = (string)$source['file_path'];
    if (!StorageService::exists($pdo, $sourcePath)) {
      throw new RuntimeException("Source file missing");
    }

    $next = Version::nextNumber($pdo, $docId);
    $safeName = self::buildStoredFilename($docId, $next, $doc['name']);
    $storageArea = (string)($doc['storage_area'] ?? 'PRIVATE');
    $targetPath = self::relativePath((int)$doc['owner_id'], $safeName, $storageArea);
    self::assertWithinQuota((int)$doc['owner_id'], StorageService::size($pdo, $sourcePath), $storageArea);

    if (!StorageService::copy($pdo, $sourcePath, $targetPath, [
      'kind' => 'document_version',
      'visibility' => 'private',
      'original_name' => (string)$doc['name'],
      'created_by' => $userId,
    ])) {
      throw new RuntimeException("Failed to restore version");
    }

    Version::add($pdo, $docId, $userId, $targetPath, $next);
    self::archiveNonLatestVersions($pdo, $docId, (int)$doc['owner_id'], $next, $storageArea);
    AuditLog::add($pdo, $userId, "Restored version", $docId, "from=".$source['version_number'].",to=".$next);

    return $next;
  }

  public static function storageStats(PDO $pdo): array {
    $totalBytes = 0;
    $files = 0;

    $usage = StorageService::storageUsage($pdo, ['../storage/documents/']);
    $files = (int)$usage['files'];
    $totalBytes = (int)$usage['bytes'];

    return [
      'documents' => Document::countActive($pdo),
      'documents_total' => Document::countAll($pdo),
      'documents_trashed' => Document::countTrashed($pdo),
      'versions' => Version::countAll($pdo),
      'files' => $files,
      'bytes' => $totalBytes,
    ];
  }

  public static function ownerStorageSummary(int $ownerId, string $storageArea = 'ALL'): array {
    $storageArea = strtoupper(trim($storageArea));
    $used = self::ownerStorageBytes($ownerId, $storageArea);
    $limit = match ($storageArea) {
      'PRIVATE' => PRIVATE_STORAGE_LIMIT_BYTES,
      'OFFICIAL' => OFFICIAL_STORAGE_LIMIT_BYTES,
      default => PRIVATE_STORAGE_LIMIT_BYTES + OFFICIAL_STORAGE_LIMIT_BYTES,
    };
    return [
      'used' => $used,
      'limit' => $limit,
      'remaining' => max(0, $limit - $used),
      'percent' => $limit > 0 ? min(100, ($used / $limit) * 100) : 0,
    ];
  }

  public static function ownerStorageBreakdown(int $ownerId): array {
    return [
      'private' => self::ownerStorageSummary($ownerId, 'PRIVATE'),
      'official' => self::ownerStorageSummary($ownerId, 'OFFICIAL'),
      'all' => self::ownerStorageSummary($ownerId, 'ALL'),
    ];
  }

  public static function absolutePathFromVersion(string $filePath): string {
    return StorageService::absoluteDocumentPath($filePath);
  }

  public static function signedDocumentToken(int $docId): string {
    return hash_hmac('sha256', (string)$docId, APP_SECRET);
  }

  public static function verifyDocumentToken(int $docId, string $token): bool {
    return hash_equals(self::signedDocumentToken($docId), $token);
  }

  private static function storeUploadedVersion(PDO $pdo, int $docId, array $file, int $userId): int {
    if (!isset($file['tmp_name']) || !self::isAcceptedUploadSource((string)$file['tmp_name'])) {
      throw new RuntimeException("Invalid upload");
    }

    $doc = Document::get($pdo, $docId);
    if (!$doc) {
      throw new RuntimeException("Document not found");
    }

    $ver = Version::nextNumber($pdo, $docId);
    $sourceName = self::sanitizeFilename((string)($file['name'] ?? $doc['name']));
    $ext = strtolower((string)pathinfo($sourceName, PATHINFO_EXTENSION));
    $docExt = strtolower((string)pathinfo((string)$doc['name'], PATHINFO_EXTENSION));
    self::assertUploadConstraints($file, $userId);

    if ($docExt !== $ext) {
      throw new RuntimeException("Uploaded version must match the document format");
    }
    $storageArea = (string)($doc['storage_area'] ?? 'PRIVATE');
    self::assertWithinQuota((int)$doc['owner_id'], (int)($file['size'] ?? 0), $storageArea);

    $safeName = self::buildStoredFilename($docId, $ver, $sourceName);
    $relativePath = self::relativePath((int)$doc['owner_id'], $safeName, $storageArea);
    if (!StorageService::storeUploadedFile($pdo, $file, $relativePath, [
      'kind' => 'document_version',
      'visibility' => 'private',
      'original_name' => $sourceName,
      'mime_type' => self::detectMimeType((string)$file['tmp_name']),
      'created_by' => $userId,
    ])) {
      throw new RuntimeException("Failed to store file");
    }

    Version::add($pdo, $docId, $userId, $relativePath, $ver);
    self::archiveNonLatestVersions($pdo, $docId, (int)$doc['owner_id'], $ver, $storageArea);
    return $ver;
  }

  private static function archiveNonLatestVersions(PDO $pdo, int $docId, int $ownerId, int $latestVersionNumber, string $storageArea = 'PRIVATE'): void {
    if ($latestVersionNumber <= 1) {
      return;
    }

    $s = $pdo->prepare("\n      SELECT id, file_path, version_number\n      FROM document_versions\n      WHERE document_id = ? AND version_number < ?\n      ORDER BY version_number ASC\n    ");
    $s->execute([$docId, $latestVersionNumber]);
    $rows = $s->fetchAll();

    foreach ($rows as $row) {
      $currentPath = (string)$row['file_path'];
      if (!StorageService::exists($pdo, $currentPath)) {
        continue;
      }

      $base = basename($currentPath);
      $targetPath = self::relativePathForArchivedVersion($ownerId, $docId, $base, $storageArea);
      if (strtolower($currentPath) === strtolower($targetPath)) {
        continue;
      }

      $targetPath = self::resolveArchiveCollision($pdo, $targetPath);
      if (!StorageService::move($pdo, $currentPath, $targetPath)) {
        if (!StorageService::copy($pdo, $currentPath, $targetPath) ) {
          continue;
        }
        StorageService::delete($pdo, $currentPath);
      }

      $u = $pdo->prepare("UPDATE document_versions SET file_path=? WHERE id=?");
      $u->execute([$targetPath, (int)$row['id']]);
    }
  }

  private static function relativePathForArchivedVersion(int $ownerId, int $docId, string $basename, string $storageArea = 'PRIVATE'): string {
    $segment = strtolower(Document::normalizeStorageArea($storageArea));
    return "../storage/documents/" . $segment . "/" . $ownerId . "/previous_versions/" . $docId . "/" . $basename;
  }

  private static function resolveArchiveCollision(PDO $pdo, string $targetPath): string {
    if (!StorageService::exists($pdo, $targetPath)) {
      return $targetPath;
    }

    $dir = dirname(str_replace('\\', '/', $targetPath));
    $name = pathinfo($targetPath, PATHINFO_FILENAME);
    $ext = pathinfo($targetPath, PATHINFO_EXTENSION);
    $suffix = '_' . time();
    return rtrim(str_replace('\\', '/', $dir), '/') . '/' . $name . $suffix . ($ext !== '' ? '.' . $ext : '');
  }

  private static function normalizeEditableExtension(string $extension): ?string {
    $ext = strtolower(trim($extension));
    return in_array($ext, self::EDITABLE_EXTENSIONS, true) ? $ext : null;
  }

  private static function assertUploadConstraints(array $file, int $actorId): void {
    $error = (int)($file['error'] ?? UPLOAD_ERR_OK);
    if ($error !== UPLOAD_ERR_OK) {
      throw new RuntimeException("Upload failed");
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    if ($tmpName === '' || !self::isAcceptedUploadSource($tmpName)) {
      throw new RuntimeException("Invalid upload");
    }

    $size = (int)($file['size'] ?? 0);
    $userRole = strtoupper((string)($_SESSION['user']['role'] ?? 'USER'));
    $max = $userRole === 'ADMIN' ? MAX_UPLOAD_BYTES_ADMIN : MAX_UPLOAD_BYTES_USER;

    if ($size <= 0 || $size > $max) {
      throw new RuntimeException("File exceeds upload size policy");
    }

    $extension = strtolower((string)pathinfo((string)($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!isset(self::ALLOWED_UPLOAD_MIME_TYPES[$extension])) {
      throw new RuntimeException("Unsupported file type");
    }

    $mime = self::detectMimeType($tmpName);
    if ($mime === null) {
      return;
    }

    if (!in_array($mime, self::ALLOWED_UPLOAD_MIME_TYPES[$extension], true)) {
      throw new RuntimeException("Uploaded file content does not match its extension");
    }
  }

  private static function ownerStorageBytes(int $ownerId, string $storageArea = 'ALL'): int {
    $storageArea = strtoupper(trim($storageArea));
    $prefixes = [];
    if ($storageArea === 'ALL' || $storageArea === 'PRIVATE') {
      $prefixes[] = "../storage/documents/private/" . $ownerId . "/";
    }
    if ($storageArea === 'ALL' || $storageArea === 'OFFICIAL') {
      $prefixes[] = "../storage/documents/official/" . $ownerId . "/";
    }

    return (int)StorageService::storageUsage($GLOBALS['pdo'], $prefixes)['bytes'];
  }

  private static function assertWithinQuota(int $ownerId, int $incomingBytes, string $storageArea = 'PRIVATE'): void {
    if ($incomingBytes <= 0) {
      return;
    }

    $summary = self::ownerStorageSummary($ownerId, $storageArea);
    if (($summary['used'] + $incomingBytes) > $summary['limit']) {
      throw new RuntimeException("User storage limit exceeded");
    }
  }

  private static function sanitizeFilename(string $name): string {
    $clean = preg_replace('/[^\w\-. ]+/', '_', $name);
    return trim((string)$clean) ?: 'file';
  }

  private static function detectMimeType(string $path): ?string {
    if (!is_file($path)) {
      return null;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
      return null;
    }

    $mime = finfo_file($finfo, $path);
    finfo_close($finfo);
    if ($mime === false) {
      return null;
    }

    return strtolower(trim((string)$mime));
  }

  private static function isAcceptedUploadSource(string $tmpName): bool {
    if ($tmpName === '') {
      return false;
    }

    if (is_uploaded_file($tmpName)) {
      return true;
    }

    return defined('WDMS_TEST_MODE') && WDMS_TEST_MODE && is_file($tmpName);
  }

  private static function buildStoredFilename(int $docId, int $version, string $originalName): string {
    $ext = pathinfo($originalName, PATHINFO_EXTENSION);
    return "doc{$docId}_v{$version}_" . time() . ($ext ? ".{$ext}" : "");
  }

  private static function relativePath(int $ownerId, string $basename, string $storageArea = 'PRIVATE'): string {
    $segment = strtolower(Document::normalizeStorageArea($storageArea));
    return "../storage/documents/" . $segment . "/" . $ownerId . "/" . $basename;
  }
}
