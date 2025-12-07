-- Migration: Add barangay_id to users table
-- Allows assigning staff users to specific barangays

ALTER TABLE users ADD COLUMN barangay_id INT NULL AFTER role;
ALTER TABLE users ADD INDEX idx_barangay_id (barangay_id);
