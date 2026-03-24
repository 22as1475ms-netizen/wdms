<?php
class AuditLog {
  public static function add(PDO $pdo, int $userId, string $action, ?int $docId=null, ?string $meta=null): void {
    $pdo->prepare("INSERT INTO audit_logs(user_id,action,document_id,meta) VALUES(?,?,?,?)")
        ->execute([$userId, $action, $docId, $meta]);
  }

  public static function recent(PDO $pdo, int $limit=30): array {
    $s = $pdo->prepare("
      SELECT a.*, u.name
      FROM audit_logs a
      JOIN users u ON u.id=a.user_id
      ORDER BY a.id DESC
      LIMIT ?
    ");
    $s->bindValue(1, $limit, PDO::PARAM_INT);
    $s->execute();
    return $s->fetchAll();
  }

  public static function recentForUser(PDO $pdo, int $userId, int $limit=30): array {
    $s = $pdo->prepare("
      SELECT a.*, u.name
      FROM audit_logs a
      JOIN users u ON u.id=a.user_id
      WHERE a.user_id=?
      ORDER BY a.id DESC
      LIMIT ?
    ");
    $s->bindValue(1, $userId, PDO::PARAM_INT);
    $s->bindValue(2, $limit, PDO::PARAM_INT);
    $s->execute();
    return $s->fetchAll();
  }

  public static function allWithUsers(PDO $pdo): array {
    return $pdo->query("
      SELECT a.*, u.name, u.email, u.role, u.status
      FROM audit_logs a
      JOIN users u ON u.id = a.user_id
      ORDER BY u.name ASC, a.created_at DESC, a.id DESC
    ")->fetchAll();
  }

  public static function allForUserWithUser(PDO $pdo, int $userId): array {
    $s = $pdo->prepare("
      SELECT a.*, u.name, u.email, u.role, u.status
      FROM audit_logs a
      JOIN users u ON u.id = a.user_id
      WHERE a.user_id=?
      ORDER BY a.created_at DESC, a.id DESC
    ");
    $s->execute([$userId]);
    return $s->fetchAll();
  }

  public static function summaryLastDays(PDO $pdo, int $days = 7): array {
    $s = $pdo->prepare("
      SELECT
        DATE(created_at) AS day,
        SUM(CASE WHEN LOWER(action) LIKE '%upload%' THEN 1 ELSE 0 END) AS uploads,
        SUM(CASE WHEN LOWER(action) LIKE '%restore%' THEN 1 ELSE 0 END) AS restores,
        SUM(CASE WHEN LOWER(action) LIKE '%delete%' THEN 1 ELSE 0 END) AS deletes
      FROM audit_logs
      WHERE created_at >= (NOW() - INTERVAL ? DAY)
      GROUP BY DATE(created_at)
      ORDER BY day DESC
    ");
    $s->execute([$days]);
    return $s->fetchAll();
  }

  public static function attendanceDaily(PDO $pdo, ?string $dateFrom = null, ?string $dateTo = null, ?int $userId = null): array {
    $where = [];
    $params = [];

    $where[] = "(a.action='Logged in' OR a.action='Logged out')";
    if ($dateFrom !== null && $dateFrom !== '') {
      $where[] = "DATE(a.created_at) >= ?";
      $params[] = $dateFrom;
    }
    if ($dateTo !== null && $dateTo !== '') {
      $where[] = "DATE(a.created_at) <= ?";
      $params[] = $dateTo;
    }
    if ($userId !== null && $userId > 0) {
      $where[] = "a.user_id = ?";
      $params[] = $userId;
    }

    $sql = "
      SELECT
        u.id AS user_id,
        u.name,
        u.email,
        DATE(a.created_at) AS work_date,
        MIN(CASE WHEN a.action='Logged in' THEN a.created_at END) AS first_in,
        MAX(CASE WHEN a.action='Logged out' THEN a.created_at END) AS last_out,
        SUM(CASE WHEN a.action='Logged in' THEN 1 ELSE 0 END) AS login_count,
        SUM(CASE WHEN a.action='Logged out' THEN 1 ELSE 0 END) AS logout_count
      FROM audit_logs a
      JOIN users u ON u.id=a.user_id
      WHERE " . implode(" AND ", $where) . "
      GROUP BY u.id, u.name, u.email, DATE(a.created_at)
      ORDER BY work_date DESC, u.name ASC
    ";
    $s = $pdo->prepare($sql);
    $s->execute($params);
    return $s->fetchAll();
  }
}
