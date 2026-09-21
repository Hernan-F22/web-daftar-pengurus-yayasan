-- ============================================================================
-- Skema Database: Sistem Pendaftaran Calon Pengurus Yayasan Mardiah
-- Kompatibilitas: TiDB Cloud Serverless & MySQL 8.0+
-- ============================================================================

-- Buat Database (Jika belum ada di lingkungan lokal / server mandiri)
-- Catatan: Di TiDB Cloud Serverless, database biasanya dibuat via TiDB Cloud Console.
CREATE DATABASE IF NOT EXISTS `yayasan_mardiah` 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE `yayasan_mardiah`;

-- ============================================================================
-- Tabel: pendaftar
-- Menyimpan seluruh data pendaftaran calon pengurus Yayasan Mardiah
-- ============================================================================
CREATE TABLE IF NOT EXISTS `pendaftar` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT 'Primary Key Auto-Increment',
    `uuid` CHAR(36) NOT NULL UNIQUE COMMENT 'ID Unik Pendaftar (UUID v4) untuk referensi publik',
    `nama_lengkap` VARCHAR(150) NOT NULL COMMENT 'Nama lengkap sesuai KTP',
    `nik` VARCHAR(16) NOT NULL UNIQUE COMMENT 'Nomor Induk Kependudukan (16 digit angka)',
    `jenis_kelamin` ENUM('Laki-laki', 'Perempuan') NOT NULL COMMENT 'Jenis Kelamin',
    `tempat_lahir` VARCHAR(100) NOT NULL COMMENT 'Kota / Kabupaten tempat lahir',
    `tanggal_lahir` DATE NOT NULL COMMENT 'Tanggal lahir calon pengurus',
    `whatsapp` VARCHAR(25) NOT NULL COMMENT 'Nomor WhatsApp aktif untuk komunikasi tim',
    `email` VARCHAR(150) NOT NULL UNIQUE COMMENT 'Alamat surel aktif calon pengurus',
    `alamat` TEXT NULL COMMENT 'Alamat tempat tinggal / domisili saat ini',
    `file_ktp` VARCHAR(255) NULL COMMENT 'Path berkas foto/scan KTP pendaftar',
    `pendidikan_terakhir` VARCHAR(50) NOT NULL COMMENT 'Jenjang pendidikan terakhir (SMA/SMK, D3, S1, S2, Lainnya)',
    `posisi_diminati` VARCHAR(100) NOT NULL COMMENT 'Divisi kepengurusan yang dipilih',
    `motivasi` TEXT NOT NULL COMMENT 'Uraian motivasi bergabung dengan kepengurusan',
    `riwayat_organisasi` TEXT NULL COMMENT 'Pengalaman organisasi kemasyarakatan/dakwah/kampus terdahulu',
    `status_seleksi` ENUM('Menunggu Review', 'Lolos Berkas', 'Wawancara', 'Diterima', 'Ditolak') 
        NOT NULL DEFAULT 'Menunggu Review' COMMENT 'Status tahapan seleksi',
    `ip_address` VARCHAR(45) NULL COMMENT 'Alamat IP pendaftar saat mengirimkan data',
    `user_agent` TEXT NULL COMMENT 'Informasi browser / device pendaftar',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Waktu pengiriman formulir',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Waktu perubahan data terakhir',
    
    -- Index untuk optimasi pencarian data dan validasi duplikasi
    INDEX `idx_pendaftar_nik` (`nik`),
    INDEX `idx_pendaftar_email` (`email`),
    INDEX `idx_pendaftar_posisi` (`posisi_diminati`),
    INDEX `idx_pendaftar_status` (`status_seleksi`),
    INDEX `idx_pendaftar_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel Data Calon Pengurus Yayasan Mardiah';

-- ============================================================================
-- Tabel: admins
-- Menyimpan akun pengelola dan token autentikasi admin panel
-- ============================================================================
CREATE TABLE IF NOT EXISTS `admins` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL COMMENT 'BCRYPT hash password',
    `nama` VARCHAR(100) NOT NULL,
    `auth_token` VARCHAR(64) NULL COMMENT 'Bearer token sesi aktif',
    `token_expiry` DATETIME NULL COMMENT 'Batas waktu berlaku token',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_admins_token` (`auth_token`),
    INDEX `idx_admins_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tabel Akun Admin Yayasan Mardiah';

-- Seed Akun Admin Default (Username: admin, Password: admin123)
INSERT INTO `admins` (`id`, `username`, `password`, `nama`) 
VALUES (1, 'admin', '$2y$10$0Doity6CkGi/bbBaGnwHTOkpei3GPoPSWkS8ihig8dP97cS0NaRd6', 'Administrator Yayasan')
ON DUPLICATE KEY UPDATE `username` = VALUES(`username`);
