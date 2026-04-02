<?php

namespace PrecisionInk\Controllers;

class AuthController extends BaseController
{
    public function loginForm(): void
    {
        // Already logged in? Go to dashboard
        if (!empty($_SESSION['user'])) {
            $this->redirect('/');
            return;
        }

        $error = $_SESSION['login_error'] ?? null;
        unset($_SESSION['login_error']);

        echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Precision Ink ERP</title>
    <link rel="stylesheet" href="/assets/css/settings.css">
    <style>
        .login-wrap { display:flex; align-items:center; justify-content:center; min-height:100vh; background:#f0f2f5; }
        .login-box { background:#fff; border-radius:12px; padding:40px; width:100%; max-width:400px; box-shadow:0 4px 24px rgba(0,0,0,0.08); }
        .login-box h1 { text-align:center; margin-bottom:8px; font-size:22px; color:#1a1a2e; }
        .login-box .subtitle { text-align:center; color:#6b7280; font-size:13px; margin-bottom:24px; }
        .login-box label { display:block; font-size:13px; font-weight:500; margin-bottom:4px; color:#374151; }
        .login-box input { width:100%; padding:10px 12px; border:1px solid #d1d5db; border-radius:6px; font-size:14px; margin-bottom:16px; }
        .login-box input:focus { outline:none; border-color:#2563eb; box-shadow:0 0 0 3px rgba(37,99,235,0.1); }
        .login-box button { width:100%; padding:10px; background:#1d4ed8; color:#fff; border:none; border-radius:6px; font-size:14px; font-weight:600; cursor:pointer; }
        .login-box button:hover { background:#1e40af; }
        .login-error { background:#fef2f2; color:#991b1b; padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:16px; border:1px solid #fecaca; }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="login-box">
            <h1>Precision Ink ERP</h1>
            <p class="subtitle">Sign in to your account</p>' .
            ($error ? '<div class="login-error">' . htmlspecialchars($error) . '</div>' : '') .
            '<form method="POST" action="/auth/login">
                <label>Username</label>
                <input type="text" name="username" required autofocus autocomplete="username">
                <label>Password</label>
                <input type="password" name="password" required autocomplete="current-password">
                <button type="submit">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>';
        exit;
    }

    public function login(): void
    {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (!$username || !$password) {
            $_SESSION['login_error'] = 'Username and password are required.';
            $this->redirect('/auth/login');
            return;
        }

        $stmt = $this->db()->prepare('
            SELECT u.*, g.name as group_name, g.is_system_admin as group_is_admin
            FROM users u
            LEFT JOIN `groups` g ON u.group_id = g.id
            WHERE u.username = ? AND u.active = 1 AND u.deleted_at IS NULL
        ');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Track failed attempts
            if ($user) {
                $this->db()->prepare('UPDATE users SET failed_attempts = failed_attempts + 1 WHERE id = ?')->execute([$user['id']]);
            }
            $_SESSION['login_error'] = 'Invalid username or password.';
            $this->redirect('/auth/login');
            return;
        }

        if ($user['locked']) {
            $_SESSION['login_error'] = 'Account is locked. Contact an administrator.';
            $this->redirect('/auth/login');
            return;
        }

        // Successful login — set session
        $_SESSION['user'] = [
            'id' => (int)$user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'group_id' => $user['group_id'] ? (int)$user['group_id'] : null,
            'group_name' => $user['group_name'] ?? null,
            'is_system_admin' => (bool)($user['group_is_admin'] ?? false),
            'theme' => $user['theme'] ?? 'light',
        ];

        // Reset failed attempts, update last login
        $this->db()->prepare('UPDATE users SET failed_attempts = 0, last_login = NOW() WHERE id = ?')->execute([$user['id']]);

        // Audit log
        $this->auditLog('LOGIN', 'users', (int)$user['id'], [], ['username' => $user['username']]);

        $this->redirect('/');
    }

    public function logout(): void
    {
        $userId = $_SESSION['user']['id'] ?? 0;
        if ($userId) {
            $this->auditLog('LOGOUT', 'users', $userId, [], []);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        session_start(); // Restart for redirect

        $this->redirect('/auth/login');
    }
}
