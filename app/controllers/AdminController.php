<?php
require_once __DIR__ . "/../middleware/require_role.php";
require_once __DIR__ . "/../helpers/csrf.php";
require_once __DIR__ . "/../helpers/http.php";
require_once __DIR__ . "/../models/Division.php";
require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../models/Folder.php";
require_once __DIR__ . "/../models/Document.php";
require_once __DIR__ . "/../models/Version.php";
require_once __DIR__ . "/../models/Permission.php";
require_once __DIR__ . "/../models/Notification.php";
require_once __DIR__ . "/../models/AuditLog.php";
require_once __DIR__ . "/../services/DocumentService.php";

function build_user_workspace_groups(PDO $pdo, array $users): array {
  $groups = [];

  foreach ($users as $user) {
    $folders = Folder::listForUser($pdo, (int)$user['id']);
    $documents = Document::listInventoryForOwner($pdo, (int)$user['id']);

    $foldersById = [];
    foreach ($folders as $folder) {
      $folder['documents'] = [];
      $foldersById[(int)$folder['id']] = $folder;
    }

    $rootDocuments = [];
    $activeDocuments = 0;
    $trashedDocuments = 0;
    $privateDocuments = 0;
    $officialDocuments = 0;
    $incomingDocuments = 0;
    $outgoingDocuments = 0;
    $sharedDocuments = 0;
    $trackingDocuments = 0;
    $versionCount = 0;
    $latestActivityAt = null;

    foreach ($documents as $document) {
      if ($document['deleted_at'] === null) {
        $activeDocuments++;
      } else {
        $trashedDocuments++;
      }

      $storageArea = strtoupper((string)($document['storage_area'] ?? 'PRIVATE'));
      if ($storageArea === 'OFFICIAL') {
        $officialDocuments++;
      } else {
        $privateDocuments++;
      }

      $documentType = strtoupper((string)($document['document_type'] ?? 'INCOMING'));
      if ($documentType === 'OUTGOING') {
        $outgoingDocuments++;
      } else {
        $incomingDocuments++;
      }

      if ((int)($document['shared_count'] ?? 0) > 0) {
        $sharedDocuments++;
      }

      $routingStatus = strtoupper((string)($document['routing_status'] ?? ''));
      if ($routingStatus !== '' && $routingStatus !== 'NOT_ROUTED' && $routingStatus !== 'AVAILABLE') {
        $trackingDocuments++;
      }

      $versionCount += max(0, (int)($document['version_count'] ?? 0));
      $activityAt = trim((string)($document['last_activity_at'] ?? ''));
      if ($activityAt !== '' && ($latestActivityAt === null || strtotime($activityAt) > strtotime($latestActivityAt))) {
        $latestActivityAt = $activityAt;
      }

      $folderId = (int)($document['folder_id'] ?? 0);
      if ($folderId > 0 && isset($foldersById[$folderId])) {
        $foldersById[$folderId]['documents'][] = $document;
        continue;
      }

      $rootDocuments[] = $document;
    }

    $groups[] = [
      'user' => $user,
      'folders' => array_values($foldersById),
      'rootDocuments' => $rootDocuments,
      'allDocuments' => $documents,
      'summary' => [
        'folder_count' => count($folders),
        'document_count' => count($documents),
        'active_count' => $activeDocuments,
        'trashed_count' => $trashedDocuments,
        'private_count' => $privateDocuments,
        'official_count' => $officialDocuments,
        'incoming_count' => $incomingDocuments,
        'outgoing_count' => $outgoingDocuments,
        'shared_docs_count' => $sharedDocuments,
        'tracking_count' => $trackingDocuments,
        'version_count' => $versionCount,
        'latest_activity_at' => $latestActivityAt,
      ],
    ];
  }

  return $groups;
}

function build_admin_user_panels(PDO $pdo, array $users, array $workspaceGroups): array {
  $logsByUserId = [];
  foreach ($users as $user) {
    $logsByUserId[(int)$user['id']] = AuditLog::recentForUser($pdo, (int)$user['id'], 8);
  }

  $panels = [];
  foreach ($workspaceGroups as $group) {
    $userId = (int)$group['user']['id'];
    $documents = $group['allDocuments'];
    $logs = $logsByUserId[$userId] ?? [];

    usort($documents, static function (array $a, array $b): int {
      return strcmp((string)($b['last_activity_at'] ?? ''), (string)($a['last_activity_at'] ?? ''));
    });

    $lastSeenAt = null;
    foreach ($logs as $log) {
      $action = strtolower((string)($log['action'] ?? ''));
      if (str_contains($action, 'logged in') || str_contains($action, 'logged out')) {
        $lastSeenAt = (string)($log['created_at'] ?? '');
        break;
      }
    }

    $panels[$userId] = [
      'recent_documents' => array_slice($documents, 0, 6),
      'recent_logs' => $logs,
      'activity_summary' => [
        'total' => count($logs),
        'uploads' => count(array_filter($logs, static fn(array $log): bool => str_contains(strtolower((string)($log['action'] ?? '')), 'upload'))),
        'reviews' => count(array_filter($logs, static fn(array $log): bool => str_contains(strtolower((string)($log['action'] ?? '')), 'review'))),
        'shares' => count(array_filter($logs, static fn(array $log): bool => str_contains(strtolower((string)($log['action'] ?? '')), 'share'))),
        'last_seen_at' => $lastSeenAt,
      ],
    ];
  }

  return $panels;
}

function build_audit_log_groups(PDO $pdo, array $users): array {
  $logs = AuditLog::allWithUsers($pdo);
  $indexed = [];

  foreach ($users as $user) {
    $indexed[(int)$user['id']] = [
      'user' => $user,
      'logs' => [],
      'summary' => [
        'total' => 0,
        'document_events' => 0,
        'sharing_events' => 0,
      ],
      'days' => [],
    ];
  }

  foreach ($logs as $log) {
    $userId = (int)$log['user_id'];
    if (!isset($indexed[$userId])) {
      continue;
    }

    $indexed[$userId]['logs'][] = $log;
    $indexed[$userId]['summary']['total']++;

    $action = strtolower((string)($log['action'] ?? ''));
    if (str_contains($action, 'document') || str_contains($action, 'version')) {
      $indexed[$userId]['summary']['document_events']++;
    }
    if (str_contains($action, 'share')) {
      $indexed[$userId]['summary']['sharing_events']++;
    }

    $dayKey = (string)date('Y-m-d', strtotime((string)$log['created_at']));
    if (!isset($indexed[$userId]['days'][$dayKey])) {
      $indexed[$userId]['days'][$dayKey] = [];
    }
    $indexed[$userId]['days'][$dayKey][] = $log;
  }

  $groups = array_values($indexed);
  foreach ($groups as &$group) {
    krsort($group['days']);
  }
  unset($group);

  return $groups;
}

function logs_group_by_day(array $logs): array {
  $days = [];
  foreach ($logs as $log) {
    $dt = admin_datetime_pht((string)($log['created_at'] ?? ''));
    $dayKey = $dt ? $dt->format('Y-m-d') : (string)date('Y-m-d');
    if (!isset($days[$dayKey])) {
      $days[$dayKey] = [];
    }
    $days[$dayKey][] = $log;
  }
  krsort($days);
  return $days;
}

function admin_datetime_pht(string $dateTime): ?DateTimeImmutable {
  $raw = trim($dateTime);
  if ($raw === '') {
    return null;
  }
  try {
    $dt = new DateTimeImmutable($raw);
  } catch (Throwable $_e) {
    return null;
  }
  return $dt->setTimezone(new DateTimeZone('Asia/Manila'));
}

function admin_month_bounds(string $monthInput): array {
  $month = trim($monthInput);
  if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
  }
  $start = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', new DateTimeZone('Asia/Manila'));
  if (!$start) {
    $month = date('Y-m');
    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $month . '-01', new DateTimeZone('Asia/Manila'));
  }
  $end = $start->modify('last day of this month');
  return [
    'month' => $month,
    'from' => $start->format('Y-m-d'),
    'to' => $end->format('Y-m-d'),
  ];
}

function xlsx_escape(string $value): string {
  $clean = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);
  if ($clean === null) {
    $clean = '';
  }
  return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsx_column_name(int $index): string {
  $name = '';
  $n = $index;
  while ($n > 0) {
    $mod = ($n - 1) % 26;
    $name = chr(65 + $mod) . $name;
    $n = intdiv($n - 1, 26);
  }
  return $name;
}

function build_xlsx_sheet_xml(array $rows, int $headerRowIndex, int $columnCount): string {
  $xmlRows = [];
  $rowNum = 1;

  foreach ($rows as $row) {
    if (!is_array($row)) {
      $row = [(string)$row];
    }

    $cells = [];
    $col = 1;
    foreach ($row as $value) {
      $cellRef = xlsx_column_name($col) . $rowNum;
      $text = xlsx_escape((string)$value);
      $cells[] = '<c r="' . $cellRef . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
      $col++;
    }
    $xmlRows[] = '<row r="' . $rowNum . '">' . implode('', $cells) . '</row>';
    $rowNum++;
  }

  return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
    . '<sheetFormatPr defaultRowHeight="15"/>'
    . '<sheetData>' . implode('', $xmlRows) . '</sheetData>'
    . '<pageMargins left="0.3" right="0.3" top="0.4" bottom="0.4" header="0.3" footer="0.3"/>'
    . '<pageSetup paperSize="5" orientation="landscape"/>'
    . '</worksheet>';
}

function stream_xlsx_download(string $filename, array $header, array $rows, array $reportMeta = []): void {
  if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZipArchive extension is required for XLSX export.');
  }

  $sheetRows = [];
  foreach ($reportMeta as $metaRow) {
    if (is_array($metaRow)) {
      $sheetRows[] = $metaRow;
    }
  }
  if (!empty($reportMeta)) {
    $sheetRows[] = [];
  }
  $sheetRows[] = $header;
  foreach ($rows as $row) {
    $sheetRows[] = is_array($row) ? $row : [(string)$row];
  }

  $tmp = tempnam(sys_get_temp_dir(), 'wdms_xlsx_');
  if ($tmp === false) {
    http_response_code(500);
    exit('Failed to prepare XLSX export.');
  }

  $zip = new ZipArchive();
  if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    http_response_code(500);
    exit('Failed to create XLSX export.');
  }

  $columnCount = count($header);
  $headerRowIndex = count($reportMeta) + (empty($reportMeta) ? 1 : 2);
  $sheetXml = build_xlsx_sheet_xml($sheetRows, $headerRowIndex, $columnCount);
  $now = gmdate('Y-m-d\TH:i:s\Z');

  $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
    . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
    . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
    . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
    . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
    . '</Types>');

  $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
    . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
    . '</Relationships>');

  $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
    . '<dc:title>WDMS Export</dc:title><dc:creator>WDMS</dc:creator><cp:lastModifiedBy>WDMS</cp:lastModifiedBy>'
    . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
    . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
    . '</cp:coreProperties>');

  $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
    . '<Application>WDMS</Application></Properties>');

  $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
    . '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');

  $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
    . '</Relationships>');

  $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
    . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
    . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
    . '<borders count="1"><border/></borders>'
    . '<cellStyleXfs count="1"><xf/></cellStyleXfs>'
    . '<cellXfs count="1"><xf xfId="0"/></cellXfs>'
    . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
    . '</styleSheet>');

  $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
  $zip->close();

  $downloadName = str_ends_with(strtolower($filename), '.xlsx') ? $filename : ($filename . '.xlsx');
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment; filename="' . $downloadName . '"');
  header('Content-Length: ' . filesize($tmp));
  readfile($tmp);
  @unlink($tmp);
  exit;
}

function docx_escape(string $value): string {
  $clean = preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value);
  if ($clean === null) {
    $clean = '';
  }
  return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function docx_cell(string $text, bool $isHeader = false): string {
  $shading = $isHeader ? '<w:shd w:val="clear" w:color="auto" w:fill="1F4E78"/>' : '';
  $color = $isHeader ? '<w:color w:val="FFFFFF"/>' : '';
  $bold = $isHeader ? '<w:b/>' : '';
  return '<w:tc>'
    . '<w:tcPr>'
    . $shading
    . '<w:tcBorders>'
    . '<w:top w:val="single" w:sz="4" w:space="0" w:color="D9E2F3"/>'
    . '<w:left w:val="single" w:sz="4" w:space="0" w:color="D9E2F3"/>'
    . '<w:bottom w:val="single" w:sz="4" w:space="0" w:color="D9E2F3"/>'
    . '<w:right w:val="single" w:sz="4" w:space="0" w:color="D9E2F3"/>'
    . '</w:tcBorders>'
    . '<w:tcMar><w:top w:w="100" w:type="dxa"/><w:left w:w="120" w:type="dxa"/><w:bottom w:w="100" w:type="dxa"/><w:right w:w="120" w:type="dxa"/></w:tcMar>'
    . '</w:tcPr>'
    . '<w:p><w:r><w:rPr>' . $bold . $color . '</w:rPr><w:t xml:space="preserve">' . docx_escape($text) . '</w:t></w:r></w:p>'
    . '</w:tc>';
}

function docx_table(array $headers, array $rows): string {
  $headerCells = '';
  foreach ($headers as $h) {
    $headerCells .= docx_cell((string)$h, true);
  }
  $xmlRows = '<w:tr>' . $headerCells . '</w:tr>';

  foreach ($rows as $row) {
    $cells = '';
    foreach ($headers as $index => $_h) {
      $cells .= docx_cell((string)($row[$index] ?? ''), false);
    }
    $xmlRows .= '<w:tr>' . $cells . '</w:tr>';
  }

  return '<w:tbl>'
    . '<w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblLayout w:type="autofit"/></w:tblPr>'
    . '<w:tblGrid/>'
    . $xmlRows
    . '</w:tbl>';
}

function stream_docx_report_download(string $filename, string $reportTitle, array $metaRows, array $header, array $rows): void {
  if (!class_exists('ZipArchive')) {
    http_response_code(500);
    exit('ZipArchive extension is required for DOCX export.');
  }

  $tmp = tempnam(sys_get_temp_dir(), 'wdms_docx_');
  if ($tmp === false) {
    http_response_code(500);
    exit('Failed to prepare DOCX export.');
  }

  $zip = new ZipArchive();
  if ($zip->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($tmp);
    http_response_code(500);
    exit('Failed to create DOCX export.');
  }

  $now = gmdate('Y-m-d\TH:i:s\Z');
  $metaTableRows = [];
  foreach ($metaRows as $meta) {
    if (!is_array($meta) || count($meta) === 0) {
      continue;
    }
    $left = (string)($meta[0] ?? '');
    $right = (string)($meta[1] ?? '');
    $metaTableRows[] = [$left, $right];
  }

  $metaTable = docx_table(['Field', 'Value'], $metaTableRows);
  $dataTable = docx_table($header, $rows);

  $documentXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<w:document xmlns:wpc="http://schemas.microsoft.com/office/word/2010/wordprocessingCanvas" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml" xmlns:wp14="http://schemas.microsoft.com/office/word/2010/wordprocessingDrawing" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:w10="urn:schemas-microsoft-com:office:word" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" mc:Ignorable="w14 wp14">'
    . '<w:body>'
    . '<w:p><w:r><w:rPr><w:b/><w:sz w:val="36"/><w:color w:val="1F4E78"/></w:rPr><w:t xml:space="preserve">' . docx_escape($reportTitle) . '</w:t></w:r></w:p>'
    . '<w:p><w:r><w:rPr><w:sz w:val="20"/><w:color w:val="5E7F89"/></w:rPr><w:t xml:space="preserve">WDMS Generated Report</w:t></w:r></w:p>'
    . '<w:p/>'
    . $metaTable
    . '<w:p/>'
    . $dataTable
    . '<w:sectPr>'
    . '<w:pgSz w:w="18720" w:h="12240" w:orient="landscape"/>'
    . '<w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720" w:header="708" w:footer="708" w:gutter="0"/>'
    . '</w:sectPr>'
    . '</w:body>'
    . '</w:document>';

  $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
    . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
    . '<Default Extension="xml" ContentType="application/xml"/>'
    . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
    . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
    . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
    . '</Types>');

  $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
    . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
    . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
    . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
    . '</Relationships>');

  $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>');

  $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
    . '<dc:title>' . docx_escape($reportTitle) . '</dc:title><dc:creator>WDMS</dc:creator><cp:lastModifiedBy>WDMS</cp:lastModifiedBy>'
    . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:created>'
    . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $now . '</dcterms:modified>'
    . '</cp:coreProperties>');

  $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
    . '<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
    . '<Application>WDMS</Application></Properties>');

  $zip->addFromString('word/document.xml', $documentXml);
  $zip->close();

  $downloadName = str_ends_with(strtolower($filename), '.docx') ? $filename : ($filename . '.docx');
  header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
  header('Content-Disposition: attachment; filename="' . $downloadName . '"');
  header('Content-Length: ' . filesize($tmp));
  readfile($tmp);
  @unlink($tmp);
  exit;
}

function pdf_text_escape(string $text): string {
  $enc = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
  if ($enc === false) {
    $enc = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
  }
  return str_replace(['\\', '(', ')'], ['\\\\', '\(', '\)'], $enc);
}

function pdf_cell_fit(string $text, int $maxChars): string {
  $clean = trim(preg_replace('/\s+/', ' ', $text) ?? '');
  if (strlen($clean) <= $maxChars) {
    return $clean;
  }
  return rtrim(substr($clean, 0, max(0, $maxChars - 1))) . "\xE2\x80\xA6";
}

function filename_segment(string $value): string {
  $normalized = preg_replace('/[^a-z0-9]+/i', '-', trim($value)) ?? '';
  $normalized = trim($normalized, '-');
  if ($normalized === '') {
    return 'Unknown';
  }
  return $normalized;
}

function pdf_logo_jpeg_binary(string $logoPath): ?array {
  if (!is_file($logoPath) || !function_exists('imagecreatefrompng')) {
    return null;
  }

  $src = @imagecreatefrompng($logoPath);
  if (!$src) {
    return null;
  }

  $w = imagesx($src);
  $h = imagesy($src);
  if ($w <= 0 || $h <= 0) {
    imagedestroy($src);
    return null;
  }

  $canvas = imagecreatetruecolor($w, $h);
  $white = imagecolorallocate($canvas, 255, 255, 255);
  imagefill($canvas, 0, 0, $white);
  imagecopy($canvas, $src, 0, 0, 0, 0, $w, $h);

  ob_start();
  imagejpeg($canvas, null, 90);
  $binary = (string)ob_get_clean();
  imagedestroy($canvas);
  imagedestroy($src);

  if ($binary === '') {
    return null;
  }

  return ['data' => $binary, 'width' => $w, 'height' => $h];
}

function stream_pdf_logs_download(string $filename, array $user, array $logs, string $monthLabel): void {
  $pageWidth = 842.0;
  $pageHeight = 595.0;
  $margin = 36.0;
  $lineHeight = 13.5;
  $contentTop = $pageHeight - $margin;
  $left = $margin;
  $usableWidth = $pageWidth - ($margin * 2);
  $logo = pdf_logo_jpeg_binary(__DIR__ . '/../../public/assets/images/logo.png');
  $generatedAtPht = (new DateTimeImmutable('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d h:i A');

  $columns = [
    ['key' => 'date', 'title' => 'Date', 'width' => 82.0, 'max' => 10],
    ['key' => 'time', 'title' => 'Time', 'width' => 62.0, 'max' => 8],
    ['key' => 'action', 'title' => 'Action', 'width' => 210.0, 'max' => 44],
    ['key' => 'doc', 'title' => 'Doc', 'width' => 58.0, 'max' => 8],
    ['key' => 'details', 'title' => 'Details', 'width' => $usableWidth - (82.0 + 62.0 + 210.0 + 58.0), 'max' => 74],
  ];

  $formatText = static function (float $x, float $y, string $font, float $size, string $text): string {
    return "BT /{$font} {$size} Tf 1 0 0 1 {$x} {$y} Tm (" . pdf_text_escape($text) . ") Tj ET\n";
  };
  $line = static function (float $x1, float $y1, float $x2, float $y2): string {
    return "{$x1} {$y1} m {$x2} {$y2} l S\n";
  };
  $rectFill = static function (float $x, float $y, float $w, float $h, float $r, float $g, float $b): string {
    return "{$r} {$g} {$b} rg {$x} {$y} {$w} {$h} re f 0 0 0 rg\n";
  };

  $pages = [];
  $stream = '';
  $y = $contentTop;
  $pageNo = 0;
  $drawHeader = function () use (&$stream, &$y, &$pageNo, $contentTop, $left, $formatText, $line, $user, $logo, $generatedAtPht, $monthLabel): void {
    $pageNo++;
    $y = $contentTop;
    if ($logo !== null) {
      $drawW = 44.0;
      $drawH = 44.0;
      $imgX = $left;
      $imgY = $y - $drawH;
      $stream .= "q {$drawW} 0 0 {$drawH} {$imgX} {$imgY} cm /Im1 Do Q\n";
    }

    $titleX = $left + ($logo !== null ? 56.0 : 0.0);
    $stream .= $formatText($titleX, $y - 10.0, 'F2', 19.0, 'WDMS');
    $stream .= $formatText($titleX, $y - 28.0, 'F1', 10.0, 'Document Workflow and Management System');
    $stream .= $formatText($titleX, $y - 43.0, 'F2', 12.0, 'Activity Log Report');
    $stream .= $formatText($left + 520.0, $y - 10.0, 'F1', 9.0, 'Generated: ' . $generatedAtPht);
    $stream .= $formatText($left + 520.0, $y - 24.0, 'F1', 9.0, 'User: ' . ((string)($user['name'] ?? '')));
    $stream .= $formatText($left + 520.0, $y - 38.0, 'F1', 9.0, 'Month: ' . $monthLabel . ' (PHT)');
    $stream .= "0.82 0.88 0.95 RG 1 w\n";
    $stream .= $line($left, $y - 54.0, $left + 770.0, $y - 54.0);
    $stream .= "0 0 0 RG\n";
    $y -= 70.0;
  };
  $drawTableHeader = function () use (&$stream, &$y, $columns, $left, $formatText, $rectFill): void {
    $headerHeight = 20.0;
    $stream .= $rectFill($left, $y - $headerHeight + 4.0, 770.0, $headerHeight, 0.90, 0.94, 0.99);
    $x = $left + 4.0;
    foreach ($columns as $column) {
      $stream .= $formatText($x, $y - 10.0, 'F2', 9.0, $column['title']);
      $x += $column['width'];
    }
    $y -= 20.0;
  };

  $drawHeader();
  $drawTableHeader();
  $rowLimitY = $margin + 26.0;
  foreach ($logs as $log) {
    if ($y <= $rowLimitY) {
      $stream .= $formatText($left + 700.0, $margin - 2.0, 'F1', 8.0, 'Page ' . (string)$pageNo);
      $pages[] = $stream;
      $stream = '';
      $drawHeader();
      $drawTableHeader();
    }

    $dt = admin_datetime_pht((string)($log['created_at'] ?? ''));
    $rowData = [
      'date' => $dt ? $dt->format('Y-m-d') : '',
      'time' => $dt ? $dt->format('h:i A') : '',
      'action' => (string)($log['action'] ?? ''),
      'doc' => (string)($log['document_id'] ?? '-'),
      'details' => (string)($log['meta'] ?? ''),
    ];

    $x = $left + 4.0;
    foreach ($columns as $column) {
      $value = pdf_cell_fit((string)($rowData[$column['key']] ?? ''), (int)$column['max']);
      $stream .= $formatText($x, $y - 10.0, 'F1', 8.5, $value);
      $x += $column['width'];
    }
    $stream .= "0.92 0.92 0.92 RG 0.5 w\n";
    $stream .= $line($left, $y - 14.0, $left + 770.0, $y - 14.0);
    $stream .= "0 0 0 RG 1 w\n";
    $y -= $lineHeight;
  }

  $stream .= $formatText($left + 700.0, $margin - 2.0, 'F1', 8.0, 'Page ' . (string)$pageNo);
  $pages[] = $stream;

  $objects = [];
  $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
  $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
  $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

  $nextId = 5;
  $imageObjId = null;
  if ($logo !== null) {
    $imageObjId = $nextId++;
    $imgData = (string)$logo['data'];
    $objects[$imageObjId] = '<< /Type /XObject /Subtype /Image /Width ' . (int)$logo['width']
      . ' /Height ' . (int)$logo['height']
      . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($imgData)
      . " >>\nstream\n" . $imgData . "\nendstream";
  }

  $kids = [];
  foreach ($pages as $pageStream) {
    $contentId = $nextId++;
    $pageId = $nextId++;
    $objects[$contentId] = '<< /Length ' . strlen($pageStream) . " >>\nstream\n" . $pageStream . "endstream";

    $resources = '<< /Font << /F1 3 0 R /F2 4 0 R >>';
    if ($imageObjId !== null) {
      $resources .= ' /XObject << /Im1 ' . $imageObjId . ' 0 R >>';
    }
    $resources .= ' >>';

    $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageWidth . ' ' . $pageHeight
      . '] /Resources ' . $resources . ' /Contents ' . $contentId . ' 0 R >>';
    $kids[] = $pageId . ' 0 R';
  }

  $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';

  ksort($objects);
  $maxId = (int)max(array_keys($objects));
  $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
  $offsets = [];
  for ($i = 1; $i <= $maxId; $i++) {
    if (!isset($objects[$i])) {
      continue;
    }
    $offsets[$i] = strlen($pdf);
    $pdf .= $i . " 0 obj\n" . $objects[$i] . "\nendobj\n";
  }

  $xrefOffset = strlen($pdf);
  $pdf .= "xref\n0 " . ($maxId + 1) . "\n";
  $pdf .= "0000000000 65535 f \n";
  for ($i = 1; $i <= $maxId; $i++) {
    if (!isset($offsets[$i])) {
      $pdf .= "0000000000 65535 f \n";
      continue;
    }
    $pdf .= sprintf('%010d 00000 n ', $offsets[$i]) . "\n";
  }

  $pdf .= "trailer\n<< /Size " . ($maxId + 1) . " /Root 1 0 R >>\n";
  $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

  $downloadName = str_ends_with(strtolower($filename), '.pdf') ? $filename : ($filename . '.pdf');
  header('Content-Type: application/pdf');
  header('Content-Disposition: attachment; filename="' . $downloadName . '"');
  header('Content-Length: ' . strlen($pdf));
  echo $pdf;
  exit;
}

function admin_reauth_ok(PDO $pdo, string $password): bool {
  if ($password === '') {
    return false;
  }

  $adminId = (int)($_SESSION['user']['id'] ?? 0);
  if ($adminId <= 0) {
    return false;
  }

  $admin = User::findById($pdo, $adminId);
  if (!$admin) {
    return false;
  }

  return password_verify($password, (string)($admin['password'] ?? ''));
}

function delete_directory_recursive(string $dir): void {
  if (!is_dir($dir)) {
    return;
  }

  $items = scandir($dir);
  if ($items === false) {
    return;
  }

  foreach ($items as $item) {
    if ($item === '.' || $item === '..') {
      continue;
    }
    $path = $dir . DIRECTORY_SEPARATOR . $item;
    if (is_dir($path)) {
      delete_directory_recursive($path);
      continue;
    }
    @unlink($path);
  }

  @rmdir($dir);
}

function admin_users(): void {
  global $pdo;
  require_role('ADMIN');
  $users = User::allNonAdmins($pdo);
  $workspaceGroups = build_user_workspace_groups($pdo, $users);
  view('admin/users', [
    'users' => $users,
    'divisions' => Division::all($pdo),
    'divisionChiefs' => User::allDivisionChiefs($pdo),
    'workspaceGroups' => $workspaceGroups,
    'userPanels' => build_admin_user_panels($pdo, $users, $workspaceGroups),
  ]);
}

function admin_export_users(): void {
  global $pdo;
  require_role('ADMIN');

  $users = User::allNonAdmins($pdo);
  $groups = build_user_workspace_groups($pdo, $users);
  $rows = [];

  foreach ($groups as $group) {
    $user = $group['user'];
    $documents = $group['allDocuments'];

    if (empty($documents)) {
      $rows[] = [
        $user['id'],
        $user['name'],
        $user['email'],
        $user['role'],
        $user['status'],
        '',
        '',
        '',
        '',
        '',
        '',
        '',
        '',
      ];
      continue;
    }

    foreach ($documents as $document) {
      $rows[] = [
        $user['id'],
        $user['name'],
        $user['email'],
        $user['role'],
        $user['status'],
        $document['folder_id'] ?: '',
        folder_location_label($document['folder_name'] ?? null),
        $document['id'],
        $document['name'],
        $document['latest_version'],
        $document['version_count'],
        $document['shared_count'],
        $document['deleted_at'] === null ? 'ACTIVE' : 'TRASHED',
      ];
    }
  }

  stream_xlsx_download(
    'wdms_user_workspace_inventory.xlsx',
    [
      'user_id',
      'user_name',
      'email',
      'role',
      'status',
      'folder_id',
      'folder_name',
      'document_id',
      'document_name',
      'latest_version',
      'version_count',
      'shared_count',
      'document_state',
    ],
    $rows,
    [
      ['WDMS USER WORKSPACE INVENTORY REPORT'],
      ['Generated At', date('Y-m-d H:i:s')],
      ['Generated By', (string)($_SESSION['user']['name'] ?? 'Admin')],
      ['Total Rows', (string)count($rows)],
    ]
  );
}

function admin_toggle_user(): void {
  global $pdo;
  require_role('ADMIN');
  csrf_verify();

  $id = req_int('id', 0);
  $status = req_str('status', 'ACTIVE');
  if (!in_array($status, ['ACTIVE','DISABLED'], true)) $status='ACTIVE';
  if (!admin_reauth_ok($pdo, req_str('confirm_password', ''))) {
    redirect('/admin/users?err=reauth_failed');
  }

  if ($id === (int)$_SESSION['user']['id']) {
    redirect('/admin/users?err=self');
  }

  $target = User::findById($pdo, $id);
  if (!$target) {
    redirect('/admin/users?err=user_not_found');
  }
  if (($target['role'] ?? '') === 'ADMIN' && $status === 'DISABLED' && User::countActiveAdmins($pdo) <= 1) {
    redirect('/admin/users?err=last_admin');
  }

  User::setStatus($pdo, $id, $status);
  AuditLog::add($pdo, (int)$_SESSION['user']['id'], "Changed user status", null, "user_id=$id,status=$status");

  redirect('/admin/users?msg=updated');
}

function admin_create_division(): void {
  global $pdo;
  require_role('ADMIN');
  csrf_verify();

  if (!admin_reauth_ok($pdo, req_str('confirm_password', ''))) {
    redirect('/admin/users?err=reauth_failed');
  }

  $name = trim(req_str('name', ''));
  $chiefUserId = req_int('chief_user_id', 0);
  if ($name === '') {
    redirect('/admin/users?err=missing_fields');
  }

  try {
    $divisionId = Division::create($pdo, $name, $chiefUserId > 0 ? $chiefUserId : null);
    if ($chiefUserId > 0) {
      User::setDivision($pdo, $chiefUserId, $divisionId);
      User::setRole($pdo, $chiefUserId, 'DIVISION_CHIEF');
    }
  } catch (Throwable $e) {
    redirect('/admin/users?err=division_create_failed');
  }

  AuditLog::add($pdo, (int)$_SESSION['user']['id'], "Created division", null, "division_id=".$divisionId.",name=".$name);
  redirect('/admin/users?msg=updated');
}

function admin_change_role(): void {
  global $pdo;
  require_role('ADMIN');
  csrf_verify();

  $id = req_int('id', 0);
  $role = req_str('role', 'EMPLOYEE');
  if (!in_array($role, ['ADMIN', 'DIVISION_CHIEF', 'EMPLOYEE'], true)) {
    $role = 'EMPLOYEE';
  }
  $divisionId = req_int('division_id', 0);
  if (!admin_reauth_ok($pdo, req_str('confirm_password', ''))) {
    redirect('/admin/users?err=reauth_failed');
  }

  if ($id === (int)$_SESSION['user']['id']) {
    redirect('/admin/users?err=self_role');
  }

  $target = User::findById($pdo, $id);
  if (!$target) {
    redirect('/admin/users?err=user_not_found');
  }
  if (($target['role'] ?? '') === 'ADMIN' && $role !== 'ADMIN' && User::countAdmins($pdo) <= 1) {
    redirect('/admin/users?err=last_admin');
  }

  User::setRole($pdo, $id, $role);
  User::setDivision($pdo, $id, $divisionId > 0 ? $divisionId : null);
  if ($role === 'DIVISION_CHIEF' && $divisionId > 0) {
    Division::updateChief($pdo, $divisionId, $id);
  }
  AuditLog::add($pdo, (int)$_SESSION['user']['id'], "Changed user role", null, "user_id=$id,role=$role");

  redirect('/admin/users?msg=role_updated');
}

function admin_create_user(): void {
  global $pdo;
  require_role('ADMIN');
  csrf_verify();

  if (!admin_reauth_ok($pdo, req_str('confirm_password', ''))) {
    redirect('/admin/users?err=reauth_failed');
  }

  $name = trim(req_str('name', ''));
  $email = strtolower(trim(req_str('email', '')));
  $role = req_str('role', 'EMPLOYEE');
  if (!in_array($role, ['ADMIN', 'DIVISION_CHIEF', 'EMPLOYEE'], true)) {
    $role = 'EMPLOYEE';
  }
  $divisionId = req_int('division_id', 0);

  if ($name === '' || $email === '') {
    redirect('/admin/users?err=missing_fields');
  }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('/admin/users?err=invalid_email');
  }
  if (User::emailExists($pdo, $email)) {
    redirect('/admin/users?err=email_exists');
  }

  $defaultPassword = 'password';
  $newId = User::create(
    $pdo,
    $name,
    $email,
    $role,
    'ACTIVE',
    password_hash($defaultPassword, PASSWORD_DEFAULT),
    $divisionId > 0 ? $divisionId : null
  );
  if ($role === 'DIVISION_CHIEF' && $divisionId > 0) {
    Division::updateChief($pdo, $divisionId, $newId);
  }
  AuditLog::add(
    $pdo,
    (int)$_SESSION['user']['id'],
    "Created user account",
    null,
    "user_id=".$newId.",email=".$email.",role=".$role
  );

  redirect('/admin/users?msg=user_created');
}

function admin_change_user_password(): void {
  global $pdo;
  require_role('ADMIN');
  csrf_verify();

  if (!admin_reauth_ok($pdo, req_str('confirm_password', ''))) {
    redirect('/admin/users?err=reauth_failed');
  }

  $id = req_int('id', 0);
  $next = req_str('new_password', '');
  $confirm = req_str('new_password_confirm', '');

  if ($id <= 0) {
    redirect('/admin/users?err=user_not_found');
  }

  $target = User::findById($pdo, $id);
  if (!$target) {
    redirect('/admin/users?err=user_not_found');
  }

  if (strlen($next) < 8) {
    redirect('/admin/users?user_id=' . $id . '&err=password_too_short');
  }

  if ($next !== $confirm) {
    redirect('/admin/users?user_id=' . $id . '&err=password_mismatch');
  }

  User::updatePassword($pdo, $id, password_hash($next, PASSWORD_DEFAULT));
  AuditLog::add(
    $pdo,
    (int)($_SESSION['user']['id'] ?? 0),
    "Changed user password",
    null,
    "user_id=" . $id . ",email=" . ((string)($target['email'] ?? ''))
  );

  redirect('/admin/users?user_id=' . $id . '&msg=password_updated');
}

function admin_delete_user(): void {
  global $pdo;
  require_role('ADMIN');
  csrf_verify();

  if (!admin_reauth_ok($pdo, req_str('confirm_password', ''))) {
    redirect('/admin/users?err=reauth_failed');
  }

  $id = req_int('id', 0);
  $adminId = (int)($_SESSION['user']['id'] ?? 0);
  if ($id <= 0) {
    redirect('/admin/users?err=user_not_found');
  }
  if ($id === $adminId) {
    redirect('/admin/users?err=self_delete');
  }

  $target = User::findById($pdo, $id);
  if (!$target) {
    redirect('/admin/users?err=user_not_found');
  }
  if (($target['role'] ?? '') === 'ADMIN' && User::countAdmins($pdo) <= 1) {
    redirect('/admin/users?err=last_admin');
  }

  $documents = Document::listInventoryForOwner($pdo, $id);
  $docIds = array_values(array_unique(array_map(static fn(array $row): int => (int)$row['id'], $documents)));
  $filePaths = Version::filePathsByDocumentIds($pdo, $docIds);

  $pdo->beginTransaction();
  try {
    if (!empty($docIds)) {
      Permission::deleteByDocumentIds($pdo, $docIds);
      Version::deleteByDocumentIds($pdo, $docIds);
      Document::hardDeleteByIds($pdo, $docIds);
    }

    $pdo->prepare("DELETE FROM permissions WHERE user_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM folders WHERE owner_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM notifications WHERE user_id=?")->execute([$id]);
    $pdo->prepare("DELETE FROM audit_logs WHERE user_id=?")->execute([$id]);
    User::deleteById($pdo, $id);

    $pdo->commit();
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) {
      $pdo->rollBack();
    }
    redirect('/admin/users?err=delete_failed');
  }

  foreach ($filePaths as $path) {
    $abs = DocumentService::absolutePathFromVersion($path);
    if (is_file($abs)) {
      @unlink($abs);
    }
  }

  delete_directory_recursive(rtrim(STORAGE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'private' . DIRECTORY_SEPARATOR . $id);
  delete_directory_recursive(rtrim(STORAGE_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'official' . DIRECTORY_SEPARATOR . $id);

  AuditLog::add(
    $pdo,
    $adminId,
    "Deleted user account",
    null,
    "deleted_user_id=".$id.",deleted_email=".((string)($target['email'] ?? ''))
  );

  redirect('/admin/users?msg=user_deleted');
}

function admin_logs(): void {
  global $pdo;
  require_role('ADMIN');
  $users = User::all($pdo);
  $selectedUserId = req_int('user_id', !empty($users) ? (int)$users[0]['id'] : 0);
  $selectedUser = User::findById($pdo, $selectedUserId);
  if (!$selectedUser && !empty($users)) {
    $selectedUserId = (int)$users[0]['id'];
    $selectedUser = $users[0];
  }

  $monthBounds = admin_month_bounds(req_str('month', date('Y-m')));
  $selectedMonth = (string)$monthBounds['month'];
  $dateFrom = (string)$monthBounds['from'];
  $dateTo = (string)$monthBounds['to'];

  $logs = $selectedUser ? AuditLog::allForUserWithUser($pdo, (int)$selectedUserId) : [];
  $logs = array_values(array_filter($logs, static function (array $row) use ($dateFrom, $dateTo): bool {
    $dt = admin_datetime_pht((string)($row['created_at'] ?? ''));
    if (!$dt) {
      return false;
    }
    $day = $dt->format('Y-m-d');
    return !($day < $dateFrom || $day > $dateTo);
  }));

  $documentEvents = 0;
  $sharingEvents = 0;
  foreach ($logs as $log) {
    $action = strtolower((string)($log['action'] ?? ''));
    if (str_contains($action, 'document') || str_contains($action, 'version')) {
      $documentEvents++;
    }
    if (str_contains($action, 'share')) {
      $sharingEvents++;
    }
  }

  view('admin/logs', [
    'users' => $users,
    'selectedUser' => $selectedUser,
    'selectedUserId' => (int)$selectedUserId,
    'logs' => $logs,
    'days' => logs_group_by_day($logs),
    'summary' => [
      'total' => count($logs),
      'document_events' => $documentEvents,
      'sharing_events' => $sharingEvents,
    ],
    'selectedMonth' => $selectedMonth,
    'selectedMonthLabel' => DateTimeImmutable::createFromFormat('!Y-m', $selectedMonth, new DateTimeZone('Asia/Manila'))->format('F Y'),
  ]);
}

function admin_export_logs(): void {
  global $pdo;
  require_role('ADMIN');
  $userId = req_int('user_id', 0);
  $user = User::findById($pdo, $userId);
  if (!$user) {
    redirect('/admin/logs?err=user_not_found');
  }
  $monthBounds = admin_month_bounds(req_str('month', date('Y-m')));
  $selectedMonth = (string)$monthBounds['month'];
  $dateFrom = (string)$monthBounds['from'];
  $dateTo = (string)$monthBounds['to'];

  $logs = AuditLog::allForUserWithUser($pdo, $userId);
  $logs = array_values(array_filter($logs, static function (array $row) use ($dateFrom, $dateTo): bool {
    $dt = admin_datetime_pht((string)($row['created_at'] ?? ''));
    if (!$dt) {
      return false;
    }
    $day = $dt->format('Y-m-d');
    return !($day < $dateFrom || $day > $dateTo);
  }));

  stream_pdf_logs_download(
    'Activity-Report-' . filename_segment($selectedMonth) . '-' . filename_segment((string)($user['name'] ?? 'User')) . '.pdf',
    $user,
    $logs,
    DateTimeImmutable::createFromFormat('!Y-m', $selectedMonth, new DateTimeZone('Asia/Manila'))->format('F Y')
  );
}
