<?php

/**
 * Endpoint Pemrosesan Form Pendaftaran Calon Pengurus Yayasan Mardiah
 * Endpoint: POST /api/submit.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Pastikan hanya menerima metode POST atau OPTIONS (CORS preflight)
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Handle CORS Headers jika dipanggil lintas origin
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($method === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($method !== 'POST') {
    sendJsonResponse(405, [
        'status'  => 'error',
        'message' => 'Metode request tidak diizinkan. Gunakan metode POST.'
    ]);
}

/**
 * Generate UUID v4 (RFC 4122)
 */
function generateUuidV4(): string
{
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // versi 4
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // varian RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Mendapatkan IP Asli Klien (mendukung reverse proxy Vercel / Cloudflare)
 */
function getClientIp(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'REMOTE_ADDR'
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ipList = explode(',', $_SERVER[$header]);
            $ip = trim($ipList[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

// 1. Tangkap Payload Request (JSON atau Form Data)
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

// 2. Ekstraksi dan Sanitasi Data
$namaLengkap       = trim((string)($inputData['nama_lengkap'] ?? ''));
$nik               = trim((string)($inputData['nik'] ?? ''));
$jenisKelamin      = trim((string)($inputData['jenis_kelamin'] ?? ''));
$tempatLahir       = trim((string)($inputData['tempat_lahir'] ?? ''));
$tanggalLahir      = trim((string)($inputData['tanggal_lahir'] ?? ''));
$whatsapp          = trim((string)($inputData['whatsapp'] ?? ''));
$email             = trim((string)($inputData['email'] ?? ''));
$alamat            = trim((string)($inputData['alamat'] ?? ''));
$pendidikanTerakhir = trim((string)($inputData['pendidikan_terakhir'] ?? ''));
$posisiDiminati    = trim((string)($inputData['posisi_diminati'] ?? ''));
$motivasi          = trim((string)($inputData['motivasi'] ?? ''));
$riwayatOrganisasi = trim((string)($inputData['riwayat_organisasi'] ?? ''));

// 3. Validasi Data Server-Side
$errors = [];

// Nama Lengkap
if ($namaLengkap === '') {
    $errors['nama_lengkap'] = 'Nama lengkap wajib diisi.';
} elseif (mb_strlen($namaLengkap) < 3) {
    $errors['nama_lengkap'] = 'Nama lengkap minimal 3 karakter.';
} elseif (mb_strlen($namaLengkap) > 150) {
    $errors['nama_lengkap'] = 'Nama lengkap maksimal 150 karakter.';
}

// NIK (16 digit angka KTP)
if ($nik === '') {
    $errors['nik'] = 'NIK (Nomor KTP) wajib diisi.';
} elseif (!preg_match('/^[0-9]{16}$/', $nik)) {
    $errors['nik'] = 'NIK harus terdiri dari tepat 16 digit angka.';
}

// Jenis Kelamin
$validGenders = ['Laki-laki', 'Perempuan'];
if (!in_array($jenisKelamin, $validGenders, true)) {
    $errors['jenis_kelamin'] = 'Silakan pilih jenis kelamin yang valid.';
}

// Tempat Lahir
if ($tempatLahir === '') {
    $errors['tempat_lahir'] = 'Tempat lahir wajib diisi.';
}

// Tanggal Lahir
if ($tanggalLahir === '') {
    $errors['tanggal_lahir'] = 'Tanggal lahir wajib diisi.';
} else {
    $d = DateTime::createFromFormat('Y-m-d', $tanggalLahir);
    if (!$d || $d->format('Y-m-d') !== $tanggalLahir) {
        $errors['tanggal_lahir'] = 'Format tanggal lahir tidak valid (gunakan YYYY-MM-DD).';
    } else {
        $today = new DateTime('today');
        $birthDate = new DateTime($tanggalLahir);
        $age = $today->diff($birthDate)->y;

        if ($birthDate > $today) {
            $errors['tanggal_lahir'] = 'Tanggal lahir tidak boleh di masa depan.';
        } elseif ($age < 15) {
            $errors['tanggal_lahir'] = 'Usia minimal calon pengurus adalah 15 tahun.';
        } elseif ($age > 85) {
            $errors['tanggal_lahir'] = 'Mohon masukkan tanggal lahir yang valid.';
        }
    }
}

// Nomor WhatsApp
if ($whatsapp === '') {
    $errors['whatsapp'] = 'Nomor WhatsApp wajib diisi.';
} else {
    // Bersihkan karakter selain angka dan tanda tambah
    $cleanWa = preg_replace('/[^0-9+]/', '', $whatsapp);
    if (!preg_match('/^(\+?62|0)[8][0-9]{7,12}$/', $cleanWa)) {
        $errors['whatsapp'] = 'Nomor WhatsApp tidak valid. Format: 08xxxxxxxxxx atau +628xxxxxxxxxx.';
    }
}

// Email
if ($email === '') {
    $errors['email'] = 'Alamat email wajib diisi.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Format alamat email tidak valid.';
} elseif (strlen($email) > 150) {
    $errors['email'] = 'Alamat email terlalu panjang (maksimal 150 karakter).';
}

// Pendidikan Terakhir
$validEducation = ['SMA/SMK', 'D3', 'S1', 'S2', 'Lainnya'];
if (!in_array($pendidikanTerakhir, $validEducation, true)) {
    $errors['pendidikan_terakhir'] = 'Pilih jenjang pendidikan terakhir yang valid.';
}

// Posisi / Divisi
$validDivisions = [
    'Keagamaan & Dakwah',
    'Sosial & Kemanusiaan',
    'Pendidikan & Pembinaan',
    'Media, Humas & Syiar Digital',
    'Umum, Logistik & Perlengkapan'
];
if (!in_array($posisiDiminati, $validDivisions, true)) {
    $errors['posisi_diminati'] = 'Pilih posisi/divisi yang diminati dari daftar yang tersedia.';
}

// Motivasi
if ($motivasi === '') {
    $errors['motivasi'] = 'Motivasi menjadi pengurus wajib diisi.';
} elseif (mb_strlen($motivasi) < 20) {
    $errors['motivasi'] = 'Motivasi minimal berisi 20 karakter agar panitia dapat memahami niat Anda.';
}

// Upload File KTP
$ktpRelativePath = null;
if (isset($_FILES['file_ktp']) && $_FILES['file_ktp']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['file_ktp'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors['file_ktp'] = 'Terjadi kendala saat mengunggah berkas KTP (Error code: ' . $file['error'] . ').';
    } elseif ($file['size'] > 2 * 1024 * 1024) {
        $errors['file_ktp'] = 'Ukuran berkas KTP melebihi batas maksimal 2 MB.';
    } else {
        $origName = (string)($file['name'] ?? '');
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'pdf'];

        // Cek MIME type dengan finfo jika ekstensi valid
        if (!in_array($ext, $allowedExts, true)) {
            $errors['file_ktp'] = 'Format file tidak diizinkan. Gunakan format JPG, JPEG, PNG, atau PDF.';
        } else {
            $mimeType = '';
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = (string)finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
            } elseif (function_exists('mime_content_type')) {
                $mimeType = (string)mime_content_type($file['tmp_name']);
            }

            $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf', 'application/x-pdf', 'application/octet-stream'];
            if (!empty($mimeType) && !in_array(strtolower($mimeType), $allowedMimes, true)) {
                $errors['file_ktp'] = 'Tipe konten file tidak valid (' . htmlspecialchars($mimeType) . '). Harap unggah foto KTP asli atau dokumen PDF.';
            } else {
                $uploadDir = dirname(__DIR__) . '/public/uploads/ktp';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }

                $cleanNik = preg_replace('/[^0-9]/', '', $nik);
                $uniqueHash = bin2hex(random_bytes(6));
                $safeFilename = sprintf('ktp_%s_%s.%s', $cleanNik ?: 'doc', $uniqueHash, $ext);
                $destination = $uploadDir . '/' . $safeFilename;

                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $ktpRelativePath = 'uploads/ktp/' . $safeFilename;
                } else {
                    $errors['file_ktp'] = 'Gagal menyimpan berkas KTP di server. Pastikan izin folder uploads aktif.';
                }
            }
        }
    }
} else {
    $errors['file_ktp'] = 'Foto atau scan KTP wajib diunggah.';
}

// Jika ada error validasi
if (!empty($errors)) {
    sendJsonResponse(422, [
        'status'  => 'error',
        'message' => 'Terdapat kesalahan pada isian formulir Anda. Silakan periksa kembali field yang ditandai.',
        'errors'  => $errors
    ]);
}

// 4. Proses Simpan ke TiDB Cloud Serverless
try {
    $pdo = getDbConnection();

    // Periksa apakah NIK atau Email sudah terdaftar sebelumnya
    $checkSql = "SELECT id, nik, email FROM `pendaftar` WHERE `nik` = :nik OR `email` = :email LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':nik'   => $nik,
        ':email' => strtolower($email)
    ]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        if ($existing['nik'] === $nik) {
            sendJsonResponse(409, [
                'status'  => 'error',
                'message' => 'NIK (Nomor KTP) ini sudah pernah didaftarkan. Satu NIK hanya dapat mendaftar satu kali.',
                'field'   => 'nik'
            ]);
        }

        if (strtolower($existing['email']) === strtolower($email)) {
            sendJsonResponse(409, [
                'status'  => 'error',
                'message' => 'Alamat email ini sudah pernah digunakan untuk pendaftaran. Silakan gunakan email lain.',
                'field'   => 'email'
            ]);
        }
    }

    // Buat data pendaftaran baru
    $uuid = generateUuidV4();
    $ipAddress = getClientIp();
    $userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 500);

    $insertSql = "
        INSERT INTO `pendaftar` (
            `uuid`,
            `nama_lengkap`,
            `nik`,
            `jenis_kelamin`,
            `tempat_lahir`,
            `tanggal_lahir`,
            `whatsapp`,
            `email`,
            `alamat`,
            `file_ktp`,
            `pendidikan_terakhir`,
            `posisi_diminati`,
            `motivasi`,
            `riwayat_organisasi`,
            `ip_address`,
            `user_agent`
        ) VALUES (
            :uuid,
            :nama_lengkap,
            :nik,
            :jenis_kelamin,
            :tempat_lahir,
            :tanggal_lahir,
            :whatsapp,
            :email,
            :alamat,
            :file_ktp,
            :pendidikan_terakhir,
            :posisi_diminati,
            :motivasi,
            :riwayat_organisasi,
            :ip_address,
            :user_agent
        )
    ";

    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute([
        ':uuid'               => $uuid,
        ':nama_lengkap'       => $namaLengkap,
        ':nik'                => $nik,
        ':jenis_kelamin'      => $jenisKelamin,
        ':tempat_lahir'       => $tempatLahir,
        ':tanggal_lahir'      => $tanggalLahir,
        ':whatsapp'           => $whatsapp,
        ':email'              => strtolower($email),
        ':alamat'             => $alamat !== '' ? $alamat : null,
        ':file_ktp'           => $ktpRelativePath,
        ':pendidikan_terakhir' => $pendidikanTerakhir,
        ':posisi_diminati'    => $posisiDiminati,
        ':motivasi'           => $motivasi,
        ':riwayat_organisasi' => $riwayatOrganisasi !== '' ? $riwayatOrganisasi : null,
        ':ip_address'         => $ipAddress,
        ':user_agent'         => $userAgent,
    ]);

    // Respon Berhasil
    sendJsonResponse(201, [
        'status'  => 'success',
        'message' => 'Alhamdulillah, berkas pendaftaran Anda telah berhasil terkirim ke panitia Yayasan Mardiah!',
        'data'    => [
            'registration_id'   => $uuid,
            'nama_lengkap'      => $namaLengkap,
            'posisi_diminati'   => $posisiDiminati,
            'waktu_pendaftaran' => date('d F Y H:i:s T')
        ]
    ]);
} catch (PDOException $e) {
    error_log("[Database Error /api/submit.php]: " . $e->getMessage());

    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $isDev   = env('APP_ENV') === 'development' || $isLocal;

    sendJsonResponse(500, [
        'status'  => 'error',
        'message' => 'Terjadi kesalahan sistem saat menyimpan berkas pendaftaran. Silakan coba beberapa saat lagi.',
        'detail'  => $isDev ? $e->getMessage() : null
    ]);
} catch (Throwable $t) {
    error_log("[Server Error /api/submit.php]: " . $t->getMessage());

    $isLocal = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $isDev   = env('APP_ENV') === 'development' || $isLocal;

    sendJsonResponse(500, [
        'status'  => 'error',
        'message' => 'Terjadi kendala pada server. Mohon hubungi panitia jika masalah berlanjut.',
        'detail'  => $isDev ? $t->getMessage() : null
    ]);
}
