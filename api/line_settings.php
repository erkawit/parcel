<?php
/**
 * Line Messaging API Settings API
 * Handles get / save / test for Line OA notification configuration
 */
require_once __DIR__ . '/../includes/auth.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

/* ── Ensure table exists ───────────────────────────────────────── */
function ensureLineSettingsTable(): void
{
    global $pdo;
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS line_settings (
            id              INT PRIMARY KEY DEFAULT 1,
            channel_access_token  TEXT    DEFAULT NULL,
            group_id        VARCHAR(255)    DEFAULT NULL,
            notify_enabled  TINYINT(1)      DEFAULT 0,
            updated_at      TIMESTAMP       DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $cnt = $pdo->query("SELECT COUNT(*) FROM line_settings WHERE id = 1")->fetchColumn();
    if ($cnt == 0) {
        $pdo->exec("INSERT INTO line_settings (id) VALUES (1)");
    }
}

/* ── Helper: send a message via Line Messaging API ────────────── */
function sendLineGroupMessage(string $token, string $groupId, string $message): array
{
    $payload = json_encode([
        'to'       => $groupId,
        'messages' => [['type' => 'text', 'text' => $message]],
    ]);

    $ch = curl_init('https://api.line.me/v2/bot/message/push');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ],
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $body   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($err) return ['ok' => false, 'message' => 'cURL error: ' . $err];
    if ($status === 200) return ['ok' => true];

    $decoded = json_decode($body, true);
    $msg = $decoded['message'] ?? ('HTTP ' . $status . ': ' . $body);
    return ['ok' => false, 'message' => $msg];
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'get';

/* ── GET ──────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    ensureLineSettingsTable();
    $row = $pdo->query("SELECT * FROM line_settings WHERE id = 1")->fetch();
    // Mask token in response for security — send only first/last 6 chars
    $masked = '';
    if (!empty($row['channel_access_token'])) {
        $t = $row['channel_access_token'];
        $len = strlen($t);
        $masked = $len > 12
            ? substr($t, 0, 6) . str_repeat('*', $len - 12) . substr($t, -6)
            : str_repeat('*', $len);
    }
    echo json_encode([
        'success'         => true,
        'settings'        => [
            'channel_access_token_masked' => $masked,
            'has_token'                   => !empty($row['channel_access_token']),
            'group_id'                    => $row['group_id']   ?? '',
            'notify_enabled'              => (bool)($row['notify_enabled'] ?? false),
        ],
    ]);
    exit;
}

/* ── POST ─────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireRole(['admin']);
    ensureLineSettingsTable();

    /* ─ action=test ─ */
    if ($action === 'test') {
        $row = $pdo->query("SELECT * FROM line_settings WHERE id = 1")->fetch();
        $token   = $row['channel_access_token'] ?? '';
        $groupId = $row['group_id'] ?? '';

        if (empty($token) || empty($groupId)) {
            echo json_encode(['success' => false, 'message' => 'กรุณาบันทึก Channel Access Token และ Group ID ก่อน']);
            exit;
        }

        $result = sendLineGroupMessage($token, $groupId, "🔔 ทดสอบการแจ้งเตือนจากระบบ\n✅ การตั้งค่า Line OA เชื่อมต่อสำเร็จ!\n🕐 " . date('d/m/Y H:i:s'));
        echo json_encode(['success' => $result['ok'], 'message' => $result['message'] ?? 'ส่งข้อความทดสอบสำเร็จ']);
        exit;
    }

    /* ─ action=save ─ */
    $token          = trim($_POST['channel_access_token'] ?? '');
    $groupId        = trim($_POST['group_id'] ?? '');
    $notifyEnabled  = isset($_POST['notify_enabled']) ? 1 : 0;

    // If token contains asterisks (masked, unchanged), keep existing
    if (!empty($token) && (preg_match('/^\*+$/', $token) || strpos($token, '***') !== false)) {
        $row   = $pdo->query("SELECT channel_access_token FROM line_settings WHERE id = 1")->fetch();
        $token = $row['channel_access_token'] ?? '';
    }

    try {
        $pdo->prepare("
            UPDATE line_settings
            SET channel_access_token = ?,
                group_id = ?,
                notify_enabled = ?
            WHERE id = 1
        ")->execute([$token ?: null, $groupId ?: null, $notifyEnabled]);

        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Method not allowed']);
