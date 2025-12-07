-- Migration: Add locations table for barangay location management
-- This migration creates a locations table to manage areas/zones within barangays

DROP TABLE IF EXISTS `locations`;

CREATE TABLE `locations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barangay_id` INT NOT NULL,
  `location_name` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`barangay_id`) REFERENCES `barangay_info`(`id`) ON DELETE CASCADE,
  INDEX `idx_barangay_id` (`barangay_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
