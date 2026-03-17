-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Mar 17, 2026 at 07:24 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `amanah_digital`
--

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` char(36) NOT NULL,
  `content` text NOT NULL,
  `tag_id` int(10) UNSIGNED DEFAULT NULL,
  `sub_tag_id` int(10) UNSIGNED DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `content`, `tag_id`, `sub_tag_id`, `ip_address`, `user_agent`, `created_at`) VALUES
('1c081303-2414-48fa-93df-fd7f34a859d7', 'Tambah AC', 7, 26, '::1', 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/143.0.0.0 Safari/537.36', '2026-03-17 04:22:31'),
('ee1a567a-be72-407d-b08f-b7232dbab5fc', 'Terima kasih seru tapi jangan lupa tambahkan pasilitas TV', 8, 32, '192.168.1.19', 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/128.0.6613.98 Mobile/15E148 Safari/604.1', '2026-03-17 04:30:21');

-- --------------------------------------------------------

--
-- Table structure for table `sub_tags`
--

CREATE TABLE `sub_tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `tag_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `usage_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sub_tags`
--

INSERT INTO `sub_tags` (`id`, `tag_id`, `name`, `usage_count`) VALUES
(12, 6, 'Pembelajaran Umum', 0),
(13, 6, 'Pembelajaran Al Qur\'an', 0),
(14, 6, 'Fasilitas', 0),
(15, 6, 'Tenaga Pendidik & Kependidikan', 0),
(16, 6, 'Transportasi', 0),
(17, 6, 'Humas', 0),
(18, 9, 'Layanan', 0),
(19, 9, 'Fasilitas', 0),
(20, 9, 'Keuangan', 0),
(21, 10, 'Layanan', 0),
(22, 10, 'Kelengkapan', 0),
(23, 6, 'Boarding', 0),
(24, 7, 'Pembelajaran Umum', 0),
(25, 7, 'Pembelajaran Al Qur\'an', 0),
(26, 7, 'Fasilitas', 1),
(27, 7, 'Tenaga Pendidik & Kependidikan', 0),
(28, 7, 'Transportasi', 0),
(29, 7, 'Humas', 0),
(30, 8, 'Pembelajaran Umum', 0),
(31, 8, 'Pembelajaran Al Qur\'an', 0),
(32, 8, 'Fasilitas', 1),
(33, 8, 'Tenaga Pendidik & Kependidikan', 0),
(34, 8, 'Humas', 0);

-- --------------------------------------------------------

--
-- Table structure for table `tags`
--

CREATE TABLE `tags` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `usage_count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tags`
--

INSERT INTO `tags` (`id`, `name`, `usage_count`) VALUES
(6, 'SMPIT', 0),
(7, 'SDIT', 1),
(8, 'PAUD IT', 1),
(9, 'Kantor Pelayanan Terpadu', 0),
(10, 'Ihsanul Amal Mart', 0);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_feedback_tag` (`tag_id`),
  ADD KEY `fk_feedback_subtag` (`sub_tag_id`),
  ADD KEY `idx_ip` (`ip_address`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `sub_tags`
--
ALTER TABLE `sub_tags`
-- =====================================================
-- AMANAH Digital — Database Schema
-- MySQL / MariaDB
-- =====================================================

CREATE DATABASE IF NOT EXISTS `amanah_digital`
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `amanah_digital`;

-- -----------------------------------
-- Table: tags (Kategori / Topik)
-- -----------------------------------
CREATE TABLE `tags` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(255) NOT NULL,
  `usage_count` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tag_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------
-- Table: sub_tags (Sub-Kategori)
-- -----------------------------------
CREATE TABLE `sub_tags` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tag_id`      INT UNSIGNED NOT NULL,
  `name`        VARCHAR(255) NOT NULL,
  `usage_count` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_subtag_tag`
    FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------
-- Table: feedback
-- -----------------------------------
CREATE TABLE `feedback` (
  `id`          CHAR(36)     NOT NULL,
  `content`     TEXT         NOT NULL,
  `tag_id`      INT UNSIGNED DEFAULT NULL,
  `sub_tag_id`  INT UNSIGNED DEFAULT NULL,
  `ip_address`  VARCHAR(45)  DEFAULT NULL,
  `user_agent`  VARCHAR(500) DEFAULT NULL,
  `created_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_feedback_tag`
    FOREIGN KEY (`tag_id`)     REFERENCES `tags` (`id`)     ON DELETE SET NULL,
  CONSTRAINT `fk_feedback_subtag`
    FOREIGN KEY (`sub_tag_id`) REFERENCES `sub_tags` (`id`) ON DELETE SET NULL,
  INDEX `idx_ip`         (`ip_address`),
  INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------
-- Seed Data
-- -----------------------------------
INSERT INTO `tags` (`name`) VALUES
  ('Fasilitas'),
  ('Kurikulum'),
  ('Pelayanan'),
  ('Kebersihan');

INSERT INTO `sub_tags` (`tag_id`, `name`) VALUES
  (1, 'AC / Pendingin Ruangan'),
  (1, 'Kamar Mandi'),
  (1, 'Area Parkir'),
  (2, 'Metode Pengajaran'),
  (2, 'Materi Pelajaran'),
  (2, 'Buku Paket'),
  (3, 'Respon Admin'),
  (3, 'Keramahan Staff'),
  (4, 'Ruang Kelas'),
  (4, 'Halaman');

  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_subtag_tag` (`tag_id`);

--
-- Indexes for table `tags`
--
ALTER TABLE `tags`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_tag_name` (`name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `sub_tags`
--
ALTER TABLE `sub_tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `tags`
--
ALTER TABLE `tags`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `fk_feedback_subtag` FOREIGN KEY (`sub_tag_id`) REFERENCES `sub_tags` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_feedback_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sub_tags`
--
ALTER TABLE `sub_tags`
  ADD CONSTRAINT `fk_subtag_tag` FOREIGN KEY (`tag_id`) REFERENCES `tags` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
