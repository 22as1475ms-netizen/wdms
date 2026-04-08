<?php

class DatabaseSessionHandler implements SessionHandlerInterface {
  private PDO $pdo;
  private int $lifetime;

  public function __construct(PDO $pdo, int $lifetime) {
    $this->pdo = $pdo;
    $this->lifetime = max(60, $lifetime);
  }

  public function open(string $path, string $name): bool {
    return true;
  }

  public function close(): bool {
    return true;
  }

  public function read(string $id): string {
    $sql = "
      SELECT payload
      FROM sessions
      WHERE id = ?
        AND expires_at > NOW()
      LIMIT 1
    ";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([$id]);
    $payload = $stmt->fetchColumn();
    return is_string($payload) ? $payload : '';
  }

  public function write(string $id, string $data): bool {
    $sql = "
      INSERT INTO sessions(id, payload, last_activity_at, expires_at)
      VALUES(?, ?, NOW(), DATE_ADD(NOW(), INTERVAL ? SECOND))
      ON DUPLICATE KEY UPDATE
        payload = VALUES(payload),
        last_activity_at = VALUES(last_activity_at),
        expires_at = VALUES(expires_at)
    ";
    $stmt = $this->pdo->prepare($sql);
    return $stmt->execute([$id, $data, $this->lifetime]);
  }

  public function destroy(string $id): bool {
    $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE id = ?");
    return $stmt->execute([$id]);
  }

  public function gc(int $max_lifetime): int|false {
    $stmt = $this->pdo->prepare("DELETE FROM sessions WHERE expires_at <= NOW()");
    $stmt->execute();
    return $stmt->rowCount();
  }
}
