<?php
// ============================================================
//  includes/db.php  –  Database connection
//  Edit HOST, USER, PASS, NAME to match your phpMyAdmin setup
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');          // Change to your MySQL username
define('DB_PASS', '');              // Change to your MySQL password
define('DB_NAME', 'cbt_system');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}

// Helper: compute grade from percentage
function computeGrade(float $pct): string {
    if ($pct >= 70) return 'A';
    if ($pct >= 60) return 'B';
    if ($pct >= 50) return 'C';
    if ($pct >= 45) return 'D';
    if ($pct >= 40) return 'E';
    return 'F';
}

// Helper: send JSON and exit
function jsonOut(array $data): void {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Helper: write an audit log entry
function auditLog(PDO $db, string $actorName, string $action, string $target = '', string $detail = '', string $actorType = 'admin'): void {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    $db->prepare('INSERT INTO audit_logs (actor_type,actor_name,action,target,detail,ip) VALUES (?,?,?,?,?,?)')
       ->execute([$actorType, $actorName, $action, $target, $detail, $ip]);
}
