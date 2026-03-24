<?php
class ChatMessage {
  public static function send(PDO $pdo, int $senderId, int $recipientId, string $message, int $documentId = 0, array $attachment = []): int {
    $attachmentPath = (string)($attachment['path'] ?? '');
    $attachmentName = (string)($attachment['name'] ?? '');
    $attachmentMime = (string)($attachment['mime'] ?? '');
    $pdo->prepare("
      INSERT INTO document_messages(document_id, sender_id, recipient_id, message, attachment_path, attachment_name, attachment_mime, is_read)
      VALUES(?,?,?,?,?,?,?,0)
    ")->execute([$documentId, $senderId, $recipientId, $message, $attachmentPath ?: null, $attachmentName ?: null, $attachmentMime ?: null]);
    return (int)$pdo->lastInsertId();
  }

  public static function unreadTotal(PDO $pdo, int $userId): int {
    $s = $pdo->prepare("
      SELECT COUNT(*)
      FROM document_messages
      WHERE recipient_id=? AND is_read=0 AND deleted_by_recipient_at IS NULL
    ");
    $s->execute([$userId]);
    return (int)$s->fetchColumn();
  }

  public static function conversations(PDO $pdo, int $userId): array {
    $s = $pdo->prepare("
      SELECT
        x.peer_id,
        u.name AS peer_name,
        u.email AS peer_email,
        u.avatar_photo AS peer_avatar_photo,
        u.avatar_preset AS peer_avatar_preset,
        x.last_message,
        x.last_created_at,
        COALESCE((
          SELECT COUNT(*)
          FROM document_messages um
          WHERE um.sender_id = x.peer_id
            AND um.recipient_id = ?
            AND um.is_read = 0
            AND um.deleted_by_recipient_at IS NULL
        ), 0) AS unread_count
      FROM (
        SELECT
          CASE WHEN m.sender_id = ? THEN m.recipient_id ELSE m.sender_id END AS peer_id,
          SUBSTRING_INDEX(
            GROUP_CONCAT(
              COALESCE(NULLIF(m.message, ''), CONCAT('[Attachment] ', COALESCE(m.attachment_name, 'File')))
              ORDER BY m.id DESC SEPARATOR '\n'
            ),
            '\n',
            1
          ) AS last_message,
          MAX(m.created_at) AS last_created_at
        FROM document_messages m
        WHERE
          (m.sender_id = ? AND m.deleted_by_sender_at IS NULL)
          OR
          (m.recipient_id = ? AND m.deleted_by_recipient_at IS NULL)
        GROUP BY CASE WHEN m.sender_id = ? THEN m.recipient_id ELSE m.sender_id END
      ) x
      JOIN users u ON u.id = x.peer_id
      ORDER BY x.last_created_at DESC
      LIMIT 50
    ");
    $s->execute([$userId, $userId, $userId, $userId, $userId]);
    return $s->fetchAll();
  }

  public static function thread(PDO $pdo, int $userId, int $peerId, int $limit = 80): array {
    $limit = max(10, min(200, $limit));
    $s = $pdo->prepare("
      SELECT *
      FROM document_messages
      WHERE
        (sender_id = ? AND recipient_id = ? AND deleted_by_sender_at IS NULL)
        OR
        (sender_id = ? AND recipient_id = ? AND deleted_by_recipient_at IS NULL)
      ORDER BY id DESC
      LIMIT {$limit}
    ");
    $s->execute([$userId, $peerId, $peerId, $userId]);
    $rows = $s->fetchAll();
    return array_reverse($rows);
  }

  public static function markThreadRead(PDO $pdo, int $userId, int $peerId): int {
    $s = $pdo->prepare("
      UPDATE document_messages
      SET is_read = 1
      WHERE sender_id = ? AND recipient_id = ? AND is_read = 0 AND deleted_by_recipient_at IS NULL
    ");
    $s->execute([$peerId, $userId]);
    return $s->rowCount();
  }

  public static function deleteThreadForUser(PDO $pdo, int $userId, int $peerId): int {
    $s = $pdo->prepare("
      UPDATE document_messages
      SET
        deleted_by_sender_at = CASE
          WHEN sender_id = ? AND recipient_id = ? AND deleted_by_sender_at IS NULL THEN NOW()
          ELSE deleted_by_sender_at
        END,
        deleted_by_recipient_at = CASE
          WHEN sender_id = ? AND recipient_id = ? AND deleted_by_recipient_at IS NULL THEN NOW()
          ELSE deleted_by_recipient_at
        END
      WHERE
        (sender_id = ? AND recipient_id = ? AND deleted_by_sender_at IS NULL)
        OR
        (sender_id = ? AND recipient_id = ? AND deleted_by_recipient_at IS NULL)
    ");
    $s->execute([$userId, $peerId, $peerId, $userId, $userId, $peerId, $peerId, $userId]);
    return $s->rowCount();
  }
}
