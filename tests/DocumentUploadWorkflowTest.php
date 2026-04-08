<?php
require_once __DIR__ . '/TestCase.php';
require_once __DIR__ . '/../app/services/DocumentService.php';

class DocumentUploadWorkflowTest extends TestCase {
  public function testUploadCreatesDocumentVersionAndStoredFile(): void {
    $owner = $this->actingAs(3);
    $tmpFile = $this->makeTempUpload('memo.txt', "hello upload\n");

    $docId = DocumentService::upload(
      $this->pdo,
      $tmpFile,
      (int)$owner['id'],
      null,
      (int)$owner['id'],
      'OFFICIAL',
      (int)$owner['division_id'],
      [
        'title' => 'Upload Test',
        'current_location' => 'Owner Desk',
        'routing_status' => 'AVAILABLE',
        'status' => 'Draft',
      ]
    );

    $doc = Document::get($this->pdo, $docId);
    $latestVersion = Version::latest($this->pdo, $docId);
    $auditLogs = AuditLog::recentForUser($this->pdo, (int)$owner['id'], 5);

    $this->assertSame('memo.txt', (string)$doc['name']);
    $this->assertNotNull($latestVersion);
    $this->assertTrue(is_file(DocumentService::absolutePathFromVersion((string)$latestVersion['file_path'])), 'Expected uploaded file to exist in storage.');
    $this->assertSame('Uploaded document', (string)$auditLogs[0]['action']);
  }

  public function testUploadNewVersionArchivesPreviousVersionAndIncrementsVersionNumber(): void {
    $owner = $this->actingAs(3);
    $firstUpload = $this->makeTempUpload('memo.txt', "version one\n");
    $docId = DocumentService::upload(
      $this->pdo,
      $firstUpload,
      (int)$owner['id'],
      null,
      (int)$owner['id'],
      'OFFICIAL',
      (int)$owner['division_id'],
      [
        'title' => 'Upload Test',
        'current_location' => 'Owner Desk',
        'routing_status' => 'AVAILABLE',
        'status' => 'Draft',
      ]
    );

    $secondUpload = $this->makeTempUpload('memo.txt', "version two\n");
    $versionNumber = DocumentService::uploadNewVersion($this->pdo, $docId, $secondUpload, (int)$owner['id']);

    $versions = Version::list($this->pdo, $docId);
    $latestVersion = Version::latest($this->pdo, $docId);
    $archivedPath = DocumentService::absolutePathFromVersion((string)$versions[1]['file_path']);

    $this->assertSame(2, $versionNumber);
    $this->assertCount(2, $versions);
    $this->assertSame(2, (int)$latestVersion['version_number']);
    $this->assertTrue(str_contains((string)$versions[1]['file_path'], 'previous_versions'), 'Expected previous version to be archived.');
    $this->assertTrue(is_file($archivedPath), 'Expected archived version file to exist.');
  }

  public function testUploadRejectsUnsupportedFileType(): void {
    $owner = $this->actingAs(3);
    $tmpFile = $this->makeTempUpload('script.php', "<?php echo 'bad';");

    $this->expectExceptionMessage('Unsupported file type', function () use ($tmpFile, $owner): void {
      DocumentService::upload(
        $this->pdo,
        $tmpFile,
        (int)$owner['id'],
        null,
        (int)$owner['id'],
        'OFFICIAL',
        (int)$owner['division_id'],
        [
          'title' => 'Upload Test',
          'current_location' => 'Owner Desk',
          'routing_status' => 'AVAILABLE',
          'status' => 'Draft',
        ]
      );
    });
  }

  private function makeTempUpload(string $name, string $contents): array {
    $tmpPath = tempnam(sys_get_temp_dir(), 'wdms_upload_');
    if ($tmpPath === false) {
      throw new RuntimeException('Failed to create temp file.');
    }

    file_put_contents($tmpPath, $contents);

    return [
      'name' => $name,
      'type' => 'application/octet-stream',
      'tmp_name' => $tmpPath,
      'error' => UPLOAD_ERR_OK,
      'size' => filesize($tmpPath) ?: strlen($contents),
    ];
  }
}
