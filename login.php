<?php
/**
 * Login Page
 */
if (session_status() === PHP_SESSION_NONE) {
    $savePath = session_save_path();
    if (empty($savePath) || !@is_writable($savePath)) {
        session_save_path(sys_get_temp_dir());
    }
    @session_start();
}
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

// Fetch System Settings
$settings = null;
try {
    $settings = $pdo->query("SELECT * FROM system_settings WHERE id = 1")->fetch();
} catch (PDOException $e) { /* Ignore */
}

// Defaults
$defaults = [
    'org_name' => 'ระบบบริหารจัดการครุภัณฑ์',
    'org_name_en' => 'Asset Management System',
    'theme_color' => 'blue',
    'logo_path' => 'img/default_logo.png'
];

if (!$settings) {
    $settings = $defaults;
} else {
    // Merge defaults for any empty values
    foreach ($defaults as $key => $val) {
        if (empty($settings[$key])) {
            $settings[$key] = $val;
        }
    }
}

$error = '';
// Handle login request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

    // Check if it's a JSON request (Fetch API)
    $input = json_decode(file_get_contents('php://input'), true);
    if ($input) {
        $_POST = $input;
        $isAjax = true;
    }

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = !isset($_POST['remember']) || !empty($_POST['remember']);
    $response = ['success' => false, 'message' => ''];

    if (empty($username) || empty($password)) {
        $response['message'] = 'กรุณากรอกชื่อผู้ใช้และรหัสผ่าน';
    } else if (loginUser($username, $password, $remember)) {
        $response['success'] = true;
        // Get user for welcome message
        $user = getCurrentUser();
        $response['message'] = "ยินดีต้อนรับกลับ, " . ($user['full_name'] ?? $username);
    } else {
        $response['message'] = 'ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
    }

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // Fallback for non-JS
    if ($response['success']) {
        header('Location: index.php');
        exit;
    } else {
        $error = $response['message'];
    }
}
?>
<?php
$faviconPath = (!empty($settings['logo_path']) && file_exists(__DIR__ . '/' . $settings['logo_path']))
    ? $settings['logo_path']
    : 'img/default_logo.png';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ | GPAMS</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($faviconPath) ?>">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($faviconPath) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($faviconPath) ?>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Sarabun:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        * {
            font-family: 'Sarabun', sans-serif;
        }

        .login-bg {
            background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 30%, #172554 70%, #0f172a 100%);
            position: relative;
            overflow: hidden;
        }

        .login-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                radial-gradient(circle at 20% 50%, rgba(202, 138, 4, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(59, 130, 246, 0.1) 0%, transparent 40%),
                radial-gradient(circle at 60% 80%, rgba(202, 138, 4, 0.06) 0%, transparent 40%);
        }

        .login-bg::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
        }

        .login-card {
            animation: slideUp 0.6s ease forwards;
            backdrop-filter: blur(20px);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            50% {
                transform: translateY(-10px) rotate(2deg);
            }
        }

        .float-icon {
            animation: float 4s ease-in-out infinite;
        }

        .input-group input:focus+.input-icon {
            color: #1e3a8a;
        }

        .input-group input:focus {
            border-color: #1e3a8a;
            box-shadow: 0 0 0 3px rgba(30, 58, 138, 0.1);
        }

        @keyframes shimmer {
            0% {
                background-position: -200% center;
            }

            100% {
                background-position: 200% center;
            }
        }

        .shimmer-btn {
            background: linear-gradient(110deg, #1e3a8a 0%, #1e3a8a 40%, #2563eb 50%, #1e3a8a 60%, #1e3a8a 100%);
            background-size: 200% 100%;
        }

        .shimmer-btn:hover {
            animation: shimmer 2s linear infinite;
        }
    </style>
    <script>
        <?php if (isset($_GET['auth_error'])): ?>
            // Session was missing/invalid - clear all client storage
            localStorage.removeItem('isLoggedIn');
            localStorage.removeItem('auth_user');
            sessionStorage.removeItem('login_redirect_loop');
        <?php endif; ?>

        // Check for existing login
        if (localStorage.getItem('isLoggedIn') === 'true') {
            // Validate via API to ensure session is valid, if not, clear storage
            // But since this page is loaded when session is GONE (usually), we might need to rely on the fact 
            // that if we are here, PHP session is likely empty.
            // However, user said "if found login status... enter system".
            // If we just redirect, index.php -> requireLogin -> login.php loop.
            // So we can only auto-login if we have a TOKEN.
            // But without token logic, maybe just redirect once?
            // "Active Session" vs "Remember Me".
            // Given the requirement "Adjust system to store login data... and auto login", 
            // I will implement a check. if index.php redirects back here, we should clear storage.
            // But how to know?
            // Let's just try to redirect. If it fails, index.php will send us back.
            // To prevent loop, check referrer? Or use a session cookie check?
            // Simplified:
            // window.location.href = 'index.php'; 
            // Loop prevention: 
            if (!sessionStorage.getItem('login_redirect_loop')) {
                sessionStorage.setItem('login_redirect_loop', '1');
                window.location.href = 'index.php';
            } else {
                // We were redirected back, so session is invalid.
                localStorage.removeItem('isLoggedIn');
                sessionStorage.removeItem('login_redirect_loop');
            }
        }
    </script>
</head>

<body class="login-bg min-h-screen flex items-center justify-center p-4">

    <div class="relative z-10 w-full max-w-md">

        <!-- Floating decorative elements -->
        <div class="absolute -top-16 -right-10 w-24 h-24 bg-yellow-500/10 rounded-2xl rotate-12 float-icon"></div>
        <div class="absolute -bottom-10 -left-8 w-20 h-20 bg-blue-400/10 rounded-full float-icon"
            style="animation-delay: 1s;"></div>

        <!-- Login Card -->
        <div class="login-card bg-white rounded-2xl shadow-2xl overflow-hidden">

            <!-- Top Accent Bar -->
            <div class="h-2 bg-gradient-to-r from-yellow-500 via-yellow-600 to-yellow-700"></div>

            <!-- Header -->
            <div class="text-center pt-8 pb-4 px-8">
                <div
                    class="inline-flex items-center justify-center w-24 h-24 bg-white rounded-2xl shadow-lg mb-4 float-icon p-2 overflow-hidden">
                    <img src="<?= htmlspecialchars($settings['logo_path']) ?>" alt="Logo"
                        class="w-full h-full object-contain">
                </div>
                <h1 class="text-xl font-extrabold text-gray-800 mb-1 leading-tight">
                    <?= htmlspecialchars($settings['org_name']) ?>
                </h1>
                <p class="text-gray-500 text-xs mt-1"><?= htmlspecialchars($settings['org_name_en']) ?></p>
            </div>

            <!-- Form -->
            <form id="loginForm" method="POST" class="px-8 pb-8" onsubmit="handleLogin(event)">

                <div id="alertBox"
                    class="hidden bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl mb-4 text-sm flex items-center gap-2">
                    <i class="fas fa-exclamation-circle text-red-500"></i>
                    <span id="alertMessage"></span>
                </div>

                <!-- Username -->
                <div class="input-group mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-user text-blue-900 mr-1"></i> ชื่อผู้ใช้งาน
                    </label>
                    <div class="relative">
                        <input type="text" name="username" id="username"
                            class="w-full px-4 py-3 pl-11 border-2 border-gray-200 rounded-xl text-gray-800 text-sm focus:outline-none transition-all duration-200"
                            placeholder="กรอกชื่อผู้ใช้งาน" value="" required>
                        <span
                            class="input-icon absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 transition-colors">
                            <i class="fas fa-user"></i>
                        </span>
                    </div>
                </div>

                <!-- Password -->
                <div class="input-group mb-4">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        <i class="fas fa-lock text-blue-900 mr-1"></i> รหัสผ่าน
                    </label>
                    <div class="relative">
                        <input type="password" name="password" id="password"
                            class="w-full px-4 py-3 pl-11 pr-11 border-2 border-gray-200 rounded-xl text-gray-800 text-sm focus:outline-none transition-all duration-200"
                            placeholder="กรอกรหัสผ่าน" required>
                        <span
                            class="input-icon absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 transition-colors">
                            <i class="fas fa-lock"></i>
                        </span>
                        <button type="button" onclick="togglePassword()"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-blue-900 transition-colors">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <!-- Remember Me -->
                <div class="flex items-center justify-between mb-6">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="remember" id="remember" value="1" checked
                            class="w-4 h-4 rounded border-gray-300 text-blue-900 focus:ring-blue-900">
                        <span class="text-sm text-gray-600 font-medium">จดจำการเข้าสู่ระบบในเครื่องนี้ (30 วัน)</span>
                    </label>
                </div>

                <!-- Login Button -->
                <button type="submit" id="loginBtn" class="shimmer-btn w-full text-white font-bold py-3.5 rounded-xl transition-all duration-200 
                               border-b-4 border-yellow-700 hover:border-yellow-800 active:border-b-2 active:mt-0.5
                               flex items-center justify-center gap-2 text-lg shadow-lg hover:shadow-xl">
                    <i class="fas fa-sign-in-alt"></i>
                    เข้าสู่ระบบ
                </button>
            </form>
        </div>

        <!-- Footer -->
        <p class="text-center text-blue-300/60 text-xs mt-6">
            ©
            <?= date('Y') + 543 ?> ระบบบริหารจัดการจัดซื้อจัดจ้างและตรวจสอบครุภัณฑ์สำนักงาน GPAMS v1.0
        </p>
    </div>

    <script>
        function togglePassword() {
            const pw = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (pw.type === 'password') {
                pw.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                pw.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }

        function handleLogin(e) {
            e.preventDefault();

            const btn = document.getElementById('loginBtn');
            const originalContent = btn.innerHTML;
            const alertBox = document.getElementById('alertBox');

            // Loading state
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> กำลังตรวจสอบ...';
            btn.disabled = true;
            btn.classList.add('opacity-80', 'cursor-not-allowed');
            alertBox.classList.add('hidden');

            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            fetch('login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(data)
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'เข้าสู่ระบบสำเร็จ!',
                            text: data.message,
                            showConfirmButton: false,
                            timer: 1500,
                            timerProgressBar: true
                        }).then(() => {
                            localStorage.setItem('isLoggedIn', 'true');
                            sessionStorage.removeItem('login_redirect_loop'); // Clear loop guard
                            window.location.href = 'index.php';
                        });
                    } else {
                        // Show error alert
                        Swal.fire({
                            icon: 'error',
                            title: 'เข้าสู่ระบบไม่สำเร็จ',
                            text: data.message,
                            confirmButtonColor: '#1e3a8a',
                            confirmButtonText: 'ตกลง'
                        });

                        // Reset button
                        btn.innerHTML = originalContent;
                        btn.disabled = false;
                        btn.classList.remove('opacity-80', 'cursor-not-allowed');
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'เกิดข้อผิดพลาด',
                        text: 'ไม่สามารถเชื่อมต่อกับเซิร์ฟเวอร์ได้',
                        confirmButtonColor: '#1e3a8a'
                    });
                    btn.innerHTML = originalContent;
                    btn.disabled = false;
                    btn.classList.remove('opacity-80', 'cursor-not-allowed');
                });
        }

        // Auto-focus username
        document.getElementById('username').focus();

        <?php if (isset($_GET['logout'])): ?>
            Swal.fire({
                icon: 'success',
                title: 'ออกจากระบบแล้ว',
                text: 'คุณได้ออกจากระบบเรียบร้อยแล้ว',
                timer: 2000,
                showConfirmButton: false,
                confirmButtonColor: '#1e3a8a'
            });
        <?php endif; ?>
    </script>
</body>

</html>