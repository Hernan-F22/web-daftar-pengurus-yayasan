<?php

/**
 * Endpoint Manajemen CRUD Pendaftar (Khusus Admin) - Yayasan Mardiah
 * Endpoint: /api/admin-crud.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Auth-Token');

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

$action = $_GET['action'] ?? ($inputData['action'] ?? 'list');

try {
    $pdo = getDbConnection();

    // Verifikasi Hak Akses Admin
    $currentAdmin = getAuthenticatedAdmin($pdo);
    if (!$currentAdmin) {
        sendJsonResponse(401, [
            'status'  => 'error',
            'message' => 'Akses ditolak. Sesi admin tidak valid atau telah kedaluwarsa.'
        ]);
    }

    // 1. ACTION: STATISTIK DASHBOARD
    if ($action === 'stats') {
        $totalPendaftar = (int)$pdo->query("SELECT COUNT(*) FROM `pendaftar`")->fetchColumn();

        $statusStmt = $pdo->query("
            SELECT `status_seleksi`, COUNT(*) AS total 
            FROM `pendaftar` 
            GROUP BY `status_seleksi`
        ");
        $statusCounts = [
            'Menunggu Review' => 0,
            'Lolos Berkas'    => 0,
            'Wawancara'       => 0,
            'Diterima'        => 0,
            'Ditolak'         => 0
        ];
        while ($row = $statusStmt->fetch()) {
            $statusCounts[$row['status_seleksi']] = (int)$row['total'];
        }

        $divisiStmt = $pdo->query("
            SELECT `posisi_diminati`, COUNT(*) AS total 
            FROM `pendaftar` 
            GROUP BY `posisi_diminati` 
            ORDER BY total DESC
        ");
        $divisiCounts = $divisiStmt->fetchAll();

        sendJsonResponse(200, [
            'status' => 'success',
            'data'   => [
                'total_pendaftar' => $totalPendaftar,
                'status_counts'   => $statusCounts,
                'divisi_counts'   => $divisiCounts
            ]
        ]);
    }

    // 2. ACTION: LIST PENDAFTAR (Paginasi, Filter, & Search)
    if ($action === 'list') {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = min(100, max(5, (int)($_GET['limit'] ?? 10)));
        $offset = ($page - 1) * $limit;

        $search = trim((string)($_GET['q'] ?? ''));
        $divisi = trim((string)($_GET['divisi'] ?? ''));
        $status = trim((string)($_GET['status'] ?? ''));

        $whereClauses = [];
        $params = [];

        if ($search !== '') {
            $whereClauses[] = "(`nama_lengkap` LIKE :q OR `nik` LIKE :q OR `email` LIKE :q OR `whatsapp` LIKE :q)";
            $params[':q'] = "%{$search}%";
        }

        if ($divisi !== '') {
            $whereClauses[] = "`posisi_diminati` = :divisi";
            $params[':divisi'] = $divisi;
        }

        if ($status !== '') {
            $whereClauses[] = "`status_seleksi` = :status";
            $params[':status'] = $status;
        }

        $whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

        // Hitung total data
        $countSql = "SELECT COUNT(*) FROM `pendaftar` {$whereSql}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $totalItems = (int)$countStmt->fetchColumn();
        $totalPages = (int)ceil($totalItems / $limit);

        // Ambil baris data
        $dataSql = "
            SELECT 
                `id`, `uuid`, `nama_lengkap`, `nik`, `jenis_kelamin`, 
                `whatsapp`, `email`, `pendidikan_terakhir`, `posisi_diminati`, 
                `status_seleksi`, `created_at`
            FROM `pendaftar` 
            {$whereSql} 
            ORDER BY `id` DESC 
            LIMIT {$limit} OFFSET {$offset}
        ";
        $dataStmt = $pdo->prepare($dataSql);
        $dataStmt->execute($params);
        $items = $dataStmt->fetchAll();

        sendJsonResponse(200, [
            'status' => 'success',
            'data'   => [
                'items'        => $items,
                'total_items'  => $totalItems,
                'current_page' => $page,
                'limit'        => $limit,
                'total_pages'  => max(1, $totalPages)
            ]
        ]);
    }

    // 3. ACTION: DETAIL PENDAFTAR LENGKAP
    if ($action === 'get') {
        $id = (int)($_GET['id'] ?? ($inputData['id'] ?? 0));
        if ($id <= 0) {
            sendJsonResponse(400, ['status' => 'error', 'message' => 'ID pendaftar tidak valid.']);
        }

        $stmt = $pdo->prepare("SELECT * FROM `pendaftar` WHERE `id` = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $item = $stmt->fetch();

        if (!$item) {
            sendJsonResponse(404, ['status' => 'error', 'message' => 'Data pendaftar tidak ditemukan.']);
        }

        sendJsonResponse(200, [
            'status' => 'success',
            'data'   => $item
        ]);
    }

    // 4. ACTION: CREATE MANUAL (Admin menambah calon pengurus)
    if ($action === 'create') {
        if ($method !== 'POST') {
            sendJsonResponse(405, ['status' => 'error', 'message' => 'Gunakan metode POST.']);
        }

        $nama        = trim((string)($inputData['nama_lengkap'] ?? ''));
        $nik         = trim((string)($inputData['nik'] ?? ''));
        $gender      = trim((string)($inputData['jenis_kelamin'] ?? ''));
        $tempat      = trim((string)($inputData['tempat_lahir'] ?? ''));
        $tgl         = trim((string)($inputData['tanggal_lahir'] ?? ''));
        $wa          = trim((string)($inputData['whatsapp'] ?? ''));
        $email       = trim((string)($inputData['email'] ?? ''));
        $alamat      = trim((string)($inputData['alamat'] ?? ''));
        $pendidikan  = trim((string)($inputData['pendidikan_terakhir'] ?? ''));
        $posisi      = trim((string)($inputData['posisi_diminati'] ?? ''));
        $motivasi    = trim((string)($inputData['motivasi'] ?? ''));
        $organisasi  = trim((string)($inputData['riwayat_organisasi'] ?? ''));
        $status      = trim((string)($inputData['status_seleksi'] ?? 'Menunggu Review'));

        if ($nama === '' || $nik === '' || $email === '' || $posisi === '') {
            sendJsonResponse(422, ['status' => 'error', 'message' => 'Nama lengkap, NIK, Email, dan Divisi wajib diisi.']);
        }

        // Cek duplikasi
        $dupStmt = $pdo->prepare("SELECT id FROM `pendaftar` WHERE `nik` = :nik OR `email` = :email LIMIT 1");
        $dupStmt->execute([':nik' => $nik, ':email' => strtolower($email)]);
        if ($dupStmt->fetch()) {
            sendJsonResponse(409, ['status' => 'error', 'message' => 'NIK atau Email sudah terdaftar sebelumnya.']);
        }

        // Generate UUID
        $dataBytes = random_bytes(16);
        $dataBytes[6] = chr((ord($dataBytes[6]) & 0x0f) | 0x40);
        $dataBytes[8] = chr((ord($dataBytes[8]) & 0x3f) | 0x80);
        $uuid = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($dataBytes), 4));

        $sql = "
            INSERT INTO `pendaftar` (
                `uuid`, `nama_lengkap`, `nik`, `jenis_kelamin`, `tempat_lahir`, 
                `tanggal_lahir`, `whatsapp`, `email`, `alamat`, `pendidikan_terakhir`, 
                `posisi_diminati`, `motivasi`, `riwayat_organisasi`, `status_seleksi`,
                `ip_address`, `user_agent`
            ) VALUES (
                :uuid, :nama, :nik, :jk, :tempat, 
                :tgl, :wa, :email, :alamat, :pend, 
                :posisi, :motivasi, :org, :status,
                '127.0.0.1 (Admin Input)', 'Admin Manual Entry'
            )
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':uuid'    => $uuid,
            ':nama'    => $nama,
            ':nik'     => $nik,
            ':jk'      => $gender ?: 'Laki-laki',
            ':tempat'  => $tempat ?: '-',
            ':tgl'     => $tgl ?: date('Y-m-d'),
            ':wa'      => $wa ?: '-',
            ':email'   => strtolower($email),
            ':alamat'  => $alamat ?: null,
            ':pend'    => $pendidikan ?: 'S1',
            ':posisi'  => $posisi,
            ':motivasi' => $motivasi ?: 'Ditambahkan manual oleh admin yayasan.',
            ':org'     => $organisasi ?: null,
            ':status'  => $status ?: 'Menunggu Review'
        ]);

        sendJsonResponse(201, [
            'status'  => 'success',
            'message' => 'Data calon pengurus berhasil ditambahkan manual.'
        ]);
    }

    // 5. ACTION: UPDATE PENDAFTAR
    if ($action === 'update') {
        $id = (int)($_GET['id'] ?? ($inputData['id'] ?? 0));
        if ($id <= 0) {
            sendJsonResponse(400, ['status' => 'error', 'message' => 'ID pendaftar tidak valid.']);
        }

        $nama        = trim((string)($inputData['nama_lengkap'] ?? ''));
        $nik         = trim((string)($inputData['nik'] ?? ''));
        $gender      = trim((string)($inputData['jenis_kelamin'] ?? ''));
        $tempat      = trim((string)($inputData['tempat_lahir'] ?? ''));
        $tgl         = trim((string)($inputData['tanggal_lahir'] ?? ''));
        $wa          = trim((string)($inputData['whatsapp'] ?? ''));
        $email       = trim((string)($inputData['email'] ?? ''));
        $alamat      = trim((string)($inputData['alamat'] ?? ''));
        $pendidikan  = trim((string)($inputData['pendidikan_terakhir'] ?? ''));
        $posisi      = trim((string)($inputData['posisi_diminati'] ?? ''));
        $motivasi    = trim((string)($inputData['motivasi'] ?? ''));
        $organisasi  = trim((string)($inputData['riwayat_organisasi'] ?? ''));
        $status      = trim((string)($inputData['status_seleksi'] ?? ''));

        if ($nama === '' || $nik === '' || $email === '') {
            sendJsonResponse(422, ['status' => 'error', 'message' => 'Nama lengkap, NIK, dan Email wajib diisi.']);
        }

        // Cek duplikasi NIK/Email selain record saat ini
        $dupStmt = $pdo->prepare("SELECT id FROM `pendaftar` WHERE (`nik` = :nik OR `email` = :email) AND id != :id LIMIT 1");
        $dupStmt->execute([':nik' => $nik, ':email' => strtolower($email), ':id' => $id]);
        if ($dupStmt->fetch()) {
            sendJsonResponse(409, ['status' => 'error', 'message' => 'NIK atau Email sudah digunakan oleh pendaftar lain.']);
        }

        $sql = "
            UPDATE `pendaftar` SET 
                `nama_lengkap` = :nama,
                `nik` = :nik,
                `jenis_kelamin` = :jk,
                `tempat_lahir` = :tempat,
                `tanggal_lahir` = :tgl,
                `whatsapp` = :wa,
                `email` = :email,
                `alamat` = :alamat,
                `pendidikan_terakhir` = :pend,
                `posisi_diminati` = :posisi,
                `motivasi` = :motivasi,
                `riwayat_organisasi` = :org,
                `status_seleksi` = :status
            WHERE `id` = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nama'    => $nama,
            ':nik'     => $nik,
            ':jk'      => $gender,
            ':tempat'  => $tempat,
            ':tgl'     => $tgl,
            ':wa'      => $wa,
            ':email'   => strtolower($email),
            ':alamat'  => $alamat !== '' ? $alamat : null,
            ':pend'    => $pendidikan,
            ':posisi'  => $posisi,
            ':motivasi' => $motivasi,
            ':org'     => $organisasi !== '' ? $organisasi : null,
            ':status'  => $status,
            ':id'      => $id
        ]);

        sendJsonResponse(200, [
            'status'  => 'success',
            'message' => 'Data pendaftar berhasil diperbarui.'
        ]);
    }

    // 6. ACTION: QUICK UPDATE STATUS SELEKSI
    if ($action === 'update-status') {
        $id     = (int)($_GET['id'] ?? ($inputData['id'] ?? 0));
        $status = trim((string)($inputData['status_seleksi'] ?? ''));

        $validStatus = ['Menunggu Review', 'Lolos Berkas', 'Wawancara', 'Diterima', 'Ditolak'];
        if (!in_array($status, $validStatus, true)) {
            sendJsonResponse(422, ['status' => 'error', 'message' => 'Status seleksi tidak valid.']);
        }

        $stmt = $pdo->prepare("UPDATE `pendaftar` SET `status_seleksi` = :st WHERE `id` = :id");
        $stmt->execute([':st' => $status, ':id' => $id]);

        sendJsonResponse(200, [
            'status'  => 'success',
            'message' => "Status seleksi berhasil diubah menjadi: {$status}"
        ]);
    }

    // 7. ACTION: DELETE PENDAFTAR
    if ($action === 'delete') {
        $id = (int)($_GET['id'] ?? ($inputData['id'] ?? 0));
        if ($id <= 0) {
            sendJsonResponse(400, ['status' => 'error', 'message' => 'ID pendaftar tidak valid.']);
        }

        $stmt = $pdo->prepare("DELETE FROM `pendaftar` WHERE `id` = :id");
        $stmt->execute([':id' => $id]);

        if ($stmt->rowCount() === 0) {
            sendJsonResponse(404, ['status' => 'error', 'message' => 'Data pendaftar tidak ditemukan atau sudah dihapus.']);
        }

        sendJsonResponse(200, [
            'status'  => 'success',
            'message' => 'Data pendaftar berhasil dihapus.'
        ]);
    }

    // 8. ACTION: EXPORT CSV
    if ($action === 'export') {
        $stmt = $pdo->query("
            SELECT 
                `id`, `uuid`, `nama_lengkap`, `nik`, `jenis_kelamin`, 
                `tempat_lahir`, `tanggal_lahir`, `whatsapp`, `email`, 
                `alamat`, `pendidikan_terakhir`, `posisi_diminati`, 
                `status_seleksi`, `created_at` 
            FROM `pendaftar` 
            ORDER BY `id` ASC
        ");
        $rows = $stmt->fetchAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="data_pendaftar_yayasan_mardiah_' . date('Ymd_His') . '.csv"');

        $output = fopen('php://output', 'w');
        // UTF-8 BOM untuk Microsoft Excel
        fputs($output, "\xEF\xBB\xBF");

        // Header CSV
        fputcsv($output, [
            'No',
            'ID Registrasi',
            'Nama Lengkap',
            'NIK',
            'Jenis Kelamin',
            'Tempat Lahir',
            'Tanggal Lahir',
            'WhatsApp',
            'Email',
            'Alamat',
            'Pendidikan Terakhir',
            'Divisi Diminati',
            'Status Seleksi',
            'Waktu Pendaftaran'
        ]);

        $no = 1;
        foreach ($rows as $r) {
            fputcsv($output, [
                $no++,
                $r['uuid'],
                $r['nama_lengkap'],
                "'" . $r['nik'], // Format string NIK agar tidak scientific notation di Excel
                $r['jenis_kelamin'],
                $r['tempat_lahir'],
                $r['tanggal_lahir'],
                "'" . $r['whatsapp'],
                $r['email'],
                $r['alamat'],
                $r['pendidikan_terakhir'],
                $r['posisi_diminati'],
                $r['status_seleksi'],
                $r['created_at']
            ]);
        }
        fclose($output);
        exit;
    }

    sendJsonResponse(400, [
        'status'  => 'error',
        'message' => 'Aksi CRUD tidak dikenali.'
    ]);
} catch (Throwable $e) {
    error_log("[Admin CRUD Error]: " . $e->getMessage());
    sendJsonResponse(500, [
        'status'  => 'error',
        'message' => 'Terjadi kesalahan sistem pada database admin.',
        'detail'  => env('APP_ENV') === 'development' ? $e->getMessage() : null
    ]);
}
