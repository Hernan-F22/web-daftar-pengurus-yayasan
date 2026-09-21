<?php

/**
 * Endpoint Autentikasi Admin - Yayasan Mardiah
 * Endpoint: /api/admin-auth.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$rawInput = file_get_contents('php://input');
$inputData = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false && !empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $inputData = $decoded;
    }
} else {
    $inputData = $_POST;
}

$action = $_GET['action'] ?? ($inputData['action'] ?? '');

try {
    $pdo = getDbConnection();

    // 1. ACTION: LOGIN
    if ($action === 'login') {
        if ($method !== 'POST') {
            sendJsonResponse(405, ['status' => 'error', 'message' => 'Gunakan metode POST untuk login.']);
        }

        $username = trim((string)($inputData['username'] ?? ''));
        $password = (string)($inputData['password'] ?? '');

        if ($username === '' || $password === '') {
            sendJsonResponse(422, [
                'status'  => 'error',
                'message' => 'Username dan password wajib diisi.'
            ]);
        }

        $stmt = $pdo->prepare("SELECT * FROM `admins` WHERE `username` = :username LIMIT 1");
        $stmt->execute([':username' => $username]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($password, $admin['password'])) {
            sendJsonResponse(401, [
                'status'  => 'error',
                'message' => 'Username atau password yang Anda masukkan salah.'
            ]);
        }

        // Buat token 64 karakter acak yang aman
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+7 days'));

        $updateStmt = $pdo->prepare("
            UPDATE `admins` 
            SET `auth_token` = :token, `token_expiry` = :expiry 
            WHERE `id` = :id
        ");
        $updateStmt->execute([
            ':token'  => $token,
            ':expiry' => $expiry,
            ':id'     => $admin['id']
        ]);

        sendJsonResponse(200, [
            'status'  => 'success',
            'message' => 'Login berhasil. Selamat datang, ' . htmlspecialchars($admin['nama']) . '!',
            'data'    => [
                'token' => $token,
                'admin' => [
                    'id'       => $admin['id'],
                    'username' => $admin['username'],
                    'nama'     => $admin['nama']
                ]
            ]
        ]);
    }

    // 2. ACTION: LOGOUT
    if ($action === 'logout') {
        $token = getBearerToken();
        if ($token) {
            $stmt = $pdo->prepare("UPDATE `admins` SET `auth_token` = NULL, `token_expiry` = NULL WHERE `auth_token` = :token");
            $stmt->execute([':token' => $token]);
        }

        sendJsonResponse(200, [
            'status'  => 'success',
            'message' => 'Anda telah berhasil keluar (logout).'
        ]);
    }

    // 3. ACTION: ME (Cek status sesi admin)
    if ($action === 'me') {
        $admin = getAuthenticatedAdmin($pdo);
        if (!$admin) {
            sendJsonResponse(401, [
                'status'  => 'error',
                'message' => 'Sesi tidak valid atau telah kedaluwarsa. Silakan login kembali.'
            ]);
        }

        sendJsonResponse(200, [
            'status' => 'success',
            'data'   => [
                'admin' => $admin
            ]
        ]);
    }

    // 4. ACTION: GANTI PASSWORD
    if ($action === 'change-password') {
        if ($method !== 'POST') {
            sendJsonResponse(405, ['status' => 'error', 'message' => 'Gunakan metode POST.']);
        }

        $admin = getAuthenticatedAdmin($pdo);
        if (!$admin) {
            sendJsonResponse(401, ['status' => 'error', 'message' => 'Sesi tidak sah.']);
        }

        $oldPassword = (string)($inputData['old_password'] ?? '');
        $newPassword = (string)($inputData['new_password'] ?? '');

        if (strlen($newPassword) < 6) {
            sendJsonResponse(422, [
                'status'  => 'error',
                'message' => 'Password baru minimal 6 karakter.'
            ]);
        }

        $stmt = $pdo->prepare("SELECT password FROM `admins` WHERE id = :id");
        $stmt->execute([':id' => $admin['id']]);
        $row = $stmt->fetch();

        if (!password_verify($oldPassword, $row['password'])) {
            sendJsonResponse(422, [
                'status'  => 'error',
                'message' => 'Password lama Anda tidak cocok.'
            ]);
        }

        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
        $updateStmt = $pdo->prepare("UPDATE `admins` SET `password` = :pass WHERE id = :id");
        $updateStmt->execute([':pass' => $newHash, ':id' => $admin['id']]);

        sendJsonResponse(200, [
            'status'  => 'success',
            'message' => 'Password berhasil diperbarui.'
        ]);
    }

    sendJsonResponse(400, [
        'status'  => 'error',
        'message' => 'Parameter aksi tidak dikenali. Pilihan: login, logout, me, change-password.'
    ]);
} catch (Throwable $e) {
    error_log("[Admin Auth Error]: " . $e->getMessage());
    sendJsonResponse(500, [
        'status'  => 'error',
        'message' => 'Terjadi kesalahan sistem saat memproses autentikasi.',
        'detail'  => env('APP_ENV') === 'development' ? $e->getMessage() : null
    ]);
}
