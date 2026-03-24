<?php
class DocumentReview {
  public static function add(PDO $pdo, int $documentId, int $reviewerId, string $decision, ?string $note): void {
    $pdo->prepare("
      INSERT INTO document_reviews(document_id, reviewer_id, decision, note)
      VALUES(?,?,?,?)
    ")->execute([$documentId, $reviewerId, strtoupper($decision), $note]);
  }

  public static function listForDocument(PDO $pdo, int $documentId): array {
    $s = $pdo->prepare("
      SELECT dr.*, u.name reviewer_name, u.email reviewer_email
      FROM document_reviews dr
      JOIN users u ON u.id = dr.reviewer_id
      WHERE dr.document_id=?
      ORDER BY dr.created_at DESC, dr.id DESC
    ");
    $s->execute([$documentId]);
    return $s->fetchAll();
  }
}
