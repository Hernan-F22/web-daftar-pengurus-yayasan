<?php

/**
 * Konfigurasi Database TiDB Cloud Serverless & Helper Lingkungan
 * Sistem Pendaftaran Calon Pengurus Yayasan Mardiah
 */

declare(strict_types=1);

// Mulai output buffering untuk mencegah peringatan server merusak JSON
if (!ob_get_level()) {
    ob_start();
}

// Cegah caching header pada respon API
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

/**
 * Muat file .env jika ada (Sangat berguna untuk pengujian lokal di XAMPP/PHP CLI)
 */
function loadLocalEnv(string $envFilePath): void
{
    if (!file_exists($envFilePath) || !is_readable($envFilePath)) {
        return;
    }

    $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        // Abaikan komentar
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);

            // Hapus tanda kutip jika ada
            $val = trim($val, "\"'");

            if (!isset($_SERVER[$key]) && !isset($_ENV[$key])) {
                putenv("{$key}={$val}");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Cari file .env di root proyek
loadLocalEnv(dirname(__DIR__) . '/.env');

/**
 * Mengambil nilai environment variable dengan fallback
 */
function env(string $key, ?string $default = null): ?string
{
    $value = getenv($key);
    if ($value !== false && $value !== '') {
        return $value;
    }

    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }

    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }

    return $default;
}

/**
 * Mendapatkan lokasi sertifikat SSL CA yang tersedia pada sistem host
 */
function resolveSslCaPath(?string $customCaPath): ?string
{
    if (!empty($customCaPath) && file_exists($customCaPath)) {
        return $customCaPath;
    }

    // Daftar lokasi umum root CA bundle di Linux (Vercel Lambda / Debian / RHEL) & Windows (XAMPP)
    $systemCertPaths = [
        '/etc/pki/tls/certs/ca-bundle.crt',                  // Amazon Linux 2 / Vercel Lambda
        '/etc/ssl/certs/ca-certificates.crt',              // Debian / Ubuntu
        '/etc/ssl/cert.pem',                               // Alpine / macOS
        'C:/xampp/perl/vendor/lib/Mozilla/CA/cacert.pem',  // Windows XAMPP
        ini_get('openssl.cafile') ?: '',
        ini_get('curl.cainfo') ?: ''
    ];

    foreach ($systemCertPaths as $path) {
        if (!empty($path) && file_exists($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Membuka koneksi PDO terenkripsi ke TiDB Cloud Serverless atau MySQL Lokal
 *
 * @throws PDOException
 * @return PDO
 */
function getDbConnection(): PDO
{
    static $pdoInstance = null;

    if ($pdoInstance instanceof PDO) {
        return $pdoInstance;
    }

    // Baca konfigurasi ENV dengan fallback default XAMPP lokal
    $host     = env('TIDB_HOST') ?: env('DB_HOST') ?: '127.0.0.1';
    $isLocal  = in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true);
    $defaultPort = $isLocal ? '3306' : '4000';
    $port     = (int) (env('TIDB_PORT') ?: env('DB_PORT') ?: $defaultPort);
    $db       = env('TIDB_DATABASE') ?: env('DB_NAME') ?: 'yayasan_mardiah';
    $user     = env('TIDB_USER') ?: env('DB_USER') ?: 'root';
    $pass     = env('TIDB_PASSWORD') ?? env('DB_PASSWORD') ?? '';
    $caCustom = env('TIDB_SSL_CA');

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
        $host,
        $port,
        $db
    );

    $pdoOptions = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        PDO::ATTR_TIMEOUT            => 5,
    ];

    // Aktifkan enkripsi SSL hanya jika host bukan localhost (misal ke TiDB Cloud Serverless)
    if (!$isLocal) {
        $caPath = resolveSslCaPath($caCustom);
        if ($caPath !== null) {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_CA] = $caPath;
            $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
        } else {
            $pdoOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
        }
    }

    try {
        $pdoInstance = new PDO($dsn, $user, $pass, $pdoOptions);
        return $pdoInstance;
    } catch (PDOException $e) {
        error_log("[Database Connection Error]: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Mengirimkan respon JSON terstruktur dengan HTTP status code
 */
function sendJsonResponse(int $statusCode, array $data): void
{
    if (ob_get_length()) {
        ob_clean();
    }
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Ekstraksi Bearer Token dari request header atau query parameter
 */
function getBearerToken(): ?string
{
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? $_SERVER['HTTP_X_AUTH_TOKEN']
        ?? '';

    if (empty($header) && function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        $header = $headers['Authorization']
            ?? $headers['authorization']
            ?? $headers['X-Auth-Token']
            ?? $headers['x-auth-token']
            ?? '';
    }

    if (!empty($header)) {
        if (preg_match('/Bearer\s+([A-Za-z0-9-_=]+)/i', $header, $matches)) {
            return $matches[1];
        }
        if (preg_match('/^[A-Za-z0-9-_=]{32,64}$/', trim($header))) {
            return trim($header);
        }
    }

    return $_GET['token'] ?? $_POST['token'] ?? null;
}

/**
 * Memvalidasi token admin aktif dan mengembalikan data akun admin jika sah
 */
function getAuthenticatedAdmin(PDO $pdo): ?array
{
    $token = getBearerToken();
    if (!$token) {
        return null;
    }

    $stmt = $pdo->prepare("
        SELECT id, username, nama, created_at 
        FROM `admins` 
        WHERE `auth_token` = :token 
          AND (`token_expiry` IS NULL OR `token_expiry` > NOW())
        LIMIT 1
    ");
    $stmt->execute([':token' => $token]);
    $admin = $stmt->fetch();

    return $admin ?: null;
}
