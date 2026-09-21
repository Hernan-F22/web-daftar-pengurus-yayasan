<?php

/**
 * Entry Point & Health Check API Yayasan Mardiah
 * Endpoint: GET /api/index.php atau /api
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');

$checkDb = isset($_GET['check_db']);
$dbStatus = 'untested';
$dbMessage = 'Gunakan parameter ?check_db=1 untuk menguji koneksi TiDB.';

if ($checkDb) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT VERSION() AS tidb_version, CURRENT_TIMESTAMP() AS server_time");
        $res = $stmt->fetch();
        $dbStatus = 'connected';
        $dbMessage = 'Koneksi ke TiDB Cloud Serverless berhasil via SSL/TLS.';
    } catch (Throwable $e) {
        $dbStatus = 'error';
        $dbMessage = $e->getMessage();
    }
}

sendJsonResponse(200, [
    'status'     => 'online',
    'app'        => 'API Sistem Pendaftaran Calon Pengurus Yayasan Mardiah',
    'version'    => '1.0.0',
    'php_version' => PHP_VERSION,
    'timestamp'  => date('c'),
    'database'   => [
        'status'  => $dbStatus,
        'message' => $dbMessage
    ]
]);
