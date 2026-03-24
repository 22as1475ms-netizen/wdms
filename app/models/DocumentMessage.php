<?php
class DocumentMessage {
  public static function send(PDO $pdo, int $docId, int $senderId, int $recipientId, string $message): int {
    $pdo->prepare("
      INSERT INTO document_messages(document_id, sender_id, recipient_id, message)
      VALUES(?,?,?,?)
    ")->execute([$docId, $senderId, $recipientId, $message]);
    return (int)$pdo->lastInsertId();
  }

  public static function conversationForUser(PDO $pdo, int $docId, int $userId, bool $isAdmin): array {
    if ($isAdmin) {
      $s = $pdo->prepare("
        SELECT m.*, s.name AS sender_name, s.email AS sender_email, r.name AS recipient_name, r.email AS recipient_email
        FROM document_messages m
        JOIN users s ON s.id = m.sender_id
        JOIN users r ON r.id = m.recipient_id
        WHERE m.document_id = ?
        ORDER BY m.id DESC
        LIMIT 60
      ");
      $s->execute([$docId]);
      return $s->fetchAll();
    }

    $s = $pdo->prepare("
      SELECT m.*, s.name AS sender_name, s.email AS sender_email, r.name AS recipient_name, r.email AS recipient_email
      FROM document_messages m
      JOIN users s ON s.id = m.sender_id
      JOIN users r ON r.id = m.recipient_id
      WHERE m.document_id = ?
        AND (m.sender_id = ? OR m.recipient_id = ?)
      ORDER BY m.id DESC
      LIMIT 60
    ");
    $s->execute([$docId, $userId, $userId]);
    return $s->fetchAll();
  }
}
