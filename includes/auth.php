<?php
/**
 * Authentication & Authorization Helper
 */

if (session_status() === PHP_SESSION_NONE) {
    $savePath = session_save_path();
    if (empty($savePath) || !@is_writable($savePath)) {
        session_save_path(sys_get_temp_dir());
    }
    @session_start();
}

require_once __DIR__ . '/db.php';

/**
 * Secret key for signing persistent login tokens (Remember Me)
 */
function getAuthTokenSecret(): string
{
    $neon = getNeonServicesConfig();
    $secret = envVal('APP_SECRET')
        ?? $neon['auth']['secret_key']
        ?? $neon['realtime']['secret']
        ?? $neon['postgres']['database_url']
        ?? 'gpams-persistent-auth-secret-key-2026';
    return hash('sha256', $secret);
}

/**
 * Set persistent login cookie (30 days default) so mobile/PWA users stay logged in
 */
function setPersistentAuthCookie(array $user, int $days = 30): void
{
    $expires = time() + ($days * 86400);
    $payload = json_encode([
        'uid' => (int) $user['id'],
        'role' => (string) $user['role'],
        'exp' => $expires,
        'ph' => substr(hash('sha256', (string) ($user['password'] ?? '')), 0, 16),
    ]);
    $b64 = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $b64, getAuthTokenSecret());
    $token = $b64 . '.' . $sig;

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    if (!headers_sent()) {
        setcookie('gpams_remember_token', $token, [
            'expires' => $expires,
            'path' => '/',
            'secure' => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    $_COOKIE['gpams_remember_token'] = $token;
}

/**
 * Clear persistent login cookie on logout
 */
function clearPersistentAuthCookie(): void
{
    if (!headers_sent()) {
        setcookie('gpams_remember_token', '', [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    unset($_COOKIE['gpams_remember_token']);
}

/**
 * Restore PHP session from signed persistent cookie if session expired
 */
function restoreSessionFromCookie(): bool
{
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    $token = $_COOKIE['gpams_remember_token'] ?? '';
    if ($token === '' || !str_contains($token, '.')) {
        return false;
    }
    [$b64, $sig] = explode('.', $token, 2);
    $expectedSig = hash_hmac('sha256', $b64, getAuthTokenSecret());
    if (!hash_equals($expectedSig, $sig)) {
        clearPersistentAuthCookie();
        return false;
    }
    $json = base64_decode(strtr($b64, '-_', '+/'), true);
    $data = $json ? json_decode($json, true) : null;
    if (!is_array($data) || empty($data['uid']) || empty($data['exp']) || $data['exp'] < time()) {
        clearPersistentAuthCookie();
        return false;
    }

    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 1");
        $stmt->execute([(int) $data['uid']]);
        $user = $stmt->fetch();
        if (!$user) {
            clearPersistentAuthCookie();
            return false;
        }
        $ph = substr(hash('sha256', (string) ($user['password'] ?? '')), 0, 16);
        if (!hash_equals($ph, (string) ($data['ph'] ?? ''))) {
            clearPersistentAuthCookie();
            return false;
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_avatar'] = $user['avatar'];
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Neon Auth service status & JWKS/Endpoint helper
 */
function getNeonAuthStatus(): array
{
    $neon = getNeonServicesConfig();
    return [
        'enabled' => !empty($neon['auth']['url']) || !empty($neon['auth']['jwks_url']),
        'url' => $neon['auth']['url'],
        'jwks_url' => $neon['auth']['jwks_url'],
    ];
}

/**
 * Neon Realtime broadcast helper for notifications & asset updates
 */
function publishNeonRealtimeEvent(string $channel, array $payload): bool
{
    $neon = getNeonServicesConfig();
    $realtimeUrl = $neon['realtime']['url'];
    if (empty($realtimeUrl)) {
        return false;
    }
    // Neon Realtime works over PostgreSQL pg_notify or WebSocket endpoint
    global $pdo;
    if (function_exists('isPostgresDb') && isPostgresDb() && $pdo) {
        try {
            $stmt = $pdo->prepare("SELECT pg_notify(?, ?)");
            $stmt->execute([$channel, json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    return false;
}

/**
 * Check if user is logged in (checks active session first, then persistent cookie)
 */
function isLoggedIn()
{
    if (isset($_SESSION['user_id'])) {
        return true;
    }
    return restoreSessionFromCookie();
}

/**
 * Require login - redirect to login page if not authenticated
 */
function requireLogin()
{
    $currentDir = basename(dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $loginUrl = in_array($currentDir, ['tracking', 'api']) ? '../login.php' : 'login.php';

    if (!isLoggedIn()) {
        // Force clear all session data
        session_unset();
        session_destroy();

        header('Location: ' . $loginUrl);
        exit;
    }

    // Validate that the user still exists in database
    if (!getCurrentUser()) {
        session_unset();
        session_destroy();

        header('Location: ' . $loginUrl);
        exit;
    }
}

/**
 * Get current user data
 */
function getCurrentUser()
{
    global $pdo;
    if (!isLoggedIn())
        return null;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 1");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Check user role
 */
function hasRole($roles)
{
    if (!isLoggedIn())
        return false;
    if (!is_array($roles))
        $roles = [$roles];
    return in_array($_SESSION['user_role'], $roles);
}

/**
 * Require specific role(s)
 */
function requireRole($roles)
{
    requireLogin();
    if (!hasRole($roles)) {
        header('HTTP/1.1 403 Forbidden');
        echo '<h1>403 - ไม่มีสิทธิ์เข้าถึง</h1>';
        exit;
    }
}

/**
 * Login user
 */
function loginUser($username, $password, $remember = true)
{
    global $pdo;

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND status = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['user_name'] = $user['full_name'];
        $_SESSION['user_avatar'] = $user['avatar'];

        if ($remember) {
            setPersistentAuthCookie($user, 30);
        }

        // Log activity
        logActivity($user['id'], 'login', 'เข้าสู่ระบบ');

        return true;
    }
    return false;
}

/**
 * Logout user
 */
function logoutUser()
{
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], 'logout', 'ออกจากระบบ');
    }
    clearPersistentAuthCookie();
    session_unset();
    session_destroy();
    // Instead of immediate redirect, show a beautiful success message

    // Check if JSON request (AJAX)
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Logged out', 'redirect' => 'login.php']);
        exit;
    }
    $faviconPath = 'img/default_logo.png';
    try {
        global $pdo;
        if ($pdo) {
            $stLogo = $pdo->query("SELECT logo_path FROM system_settings WHERE id = 1")->fetchColumn();
            if (!empty($stLogo) && file_exists(__DIR__ . '/../' . $stLogo)) {
                $faviconPath = $stLogo;
            }
        }
    } catch (Exception $e) {
    }
    ?>
    <!DOCTYPE html>
    <html lang="th">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>กำลังออกจากระบบ...</title>
        <link rel="icon" type="image/png" href="<?= htmlspecialchars($faviconPath) ?>">
        <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($faviconPath) ?>">
        <script src="https://cdn.tailwindcss.com"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700&display=swap"
            rel="stylesheet">
        <style>
            * {
                font-family: 'Sarabun', sans-serif;
            }

            body {
                background-color: #f3f4f6;
            }
        </style>
    </head>

    <body class="flex items-center justify-center min-h-screen">
        <script>
            Swal.fire({
                html: `
                    <div class="mt-4">
                        <div class="mx-auto flex items-center justify-center h-24 w-24 rounded-full bg-blue-50 mb-6 border-4 border-blue-100 shadow-inner">
                            <i class="fas fa-sign-out-alt text-4xl text-blue-900"></i>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-800 mb-2">ออกจากระบบสำเร็จ</h3>
                        <p class="text-gray-500">ขอบคุณที่ใช้งานระบบจัดการครุภัณฑ์</p>
                    </div>
                `,
                timer: 2000,
                timerProgressBar: true,
                showConfirmButton: false,
                allowOutsideClick: false,
                backdrop: `rgba(30, 58, 138, 0.4)`, // Blue tint backdrop
                customClass: {
                    popup: 'rounded-3xl p-8 border border-gray-100 shadow-2xl'
                },
                didOpen: () => {
                    Swal.showLoading();
                }
            }).then(() => {
                localStorage.removeItem('isLoggedIn');
                localStorage.removeItem('auth_user');
                window.location.href = 'login.php';
            });
        </script>
    </body>

    </html>
    <?php
    exit;
}

/**
 * Log activity
 */
function logActivity($userId, $action, $description)
{
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $action, $description, $ip]);
}

/**
 * Send notification to user
 */
function sendNotification($userId, $title, $message, $link = null, $type = 'info')
{
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, link, type) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $title, $message, $link, $type]);
    publishNeonRealtimeEvent('notifications', [
        'user_id' => (int) $userId,
        'title' => $title,
        'message' => $message,
        'link' => $link,
        'type' => $type,
    ]);
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($userId)
{
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

/**
 * Get role label in Thai
 */
function getRoleLabel($role)
{
    $labels = [
        'admin' => 'ผู้ดูแลระบบ',
        'supply_officer' => 'เจ้าหน้าที่พัสดุ',
        'department_head' => 'หัวหน้าส่วน',
        'director' => 'ผู้อำนวยการฯ',
        'user' => 'ผู้ใช้งานทั่วไป'
    ];
    return $labels[$role] ?? $role;
}

/**
 * Check if user can access/manage asset
 * @param int $assetId - Asset ID to check
 * @param int $userId - User ID (default: current user)
 * @return bool
 */
function canAccessAsset($assetId, $userId = null)
{
    global $pdo;

    if ($userId === null) {
        $userId = $_SESSION['user_id'] ?? 0;
    }

    // Get user info
    $stmt = $pdo->prepare("SELECT role, department FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user)
        return false;

    // Admin, supply_officer, and director can access all assets
    if (in_array($user['role'], ['admin', 'supply_officer', 'director'])) {
        return true;
    }

    // Department head can access assets in their department
    if ($user['role'] === 'department_head') {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM assets a
            LEFT JOIN users u ON a.holder_id = u.id
            WHERE a.id = ? AND (u.department = ? OR a.location LIKE ?)
        ");
        $stmt->execute([$assetId, $user['department'], '%' . $user['department'] . '%']);
        return $stmt->fetchColumn() > 0;
    }

    // Regular users can only access their own assets
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assets WHERE id = ? AND holder_id = ?");
    $stmt->execute([$assetId, $userId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Get asset filter SQL based on user role
 * @return array [whereClause, params]
 */
function getAssetFilterByRole()
{
    global $pdo;

    if (!isLoggedIn()) {
        return ['1=0', []]; // No access
    }

    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT role, department FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        return ['1=0', []];
    }

    // Admin, supply_officer, and director see all assets
    if (in_array($user['role'], ['admin', 'supply_officer', 'director'])) {
        return ['1=1', []];
    }

    // Department head sees assets in their department
    if ($user['role'] === 'department_head' && $user['department']) {
        return [
            '(u.department = ? OR a.location LIKE ?)',
            [$user['department'], '%' . $user['department'] . '%']
        ];
    }

    // Regular users see assets they hold OR assets in their department location
    if ($user['department']) {
        return [
            '(a.holder_id = ? OR a.location LIKE ?)',
            [$userId, '%' . $user['department'] . '%']
        ];
    }

    return ['a.holder_id = ?', [$userId]];
}

/**
 * Check if user has management privileges
 * @return bool
 */
function hasManagementPrivileges()
{
    return hasRole(['admin', 'supply_officer', 'director', 'department_head']);
}

/**
 * Format date to Thai
 */
function formatThaiDate($date)
{
    if (!$date)
        return '-';
    $months = ['', 'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.', 'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'];
    $ts = strtotime($date);
    $d = date('j', $ts);
    $m = (int) date('n', $ts);
    $y = date('Y', $ts) + 543;
    return "$d {$months[$m]} $y";
}

/**
 * Format currency
 */
function formatCurrency($amount)
{
    return number_format($amount, 2) . ' บาท';
}
