-- Barangay Case Routing System
-- Links online-filed cases to Barangay for initial processing
-- Routes cases: Complainant Online Filing → Barangay → Police Blotter → Court

-- Note: barangay_id already added in 003_add_barangay_module.sql, so skip it here
-- Just add barangay_record_id if it doesn't exist
ALTER TABLE cases ADD COLUMN IF NOT EXISTS barangay_record_id INT NULL AFTER barangay_id;

-- Add indexes (use CREATE INDEX IF NOT EXISTS for new syntax support)
ALTER TABLE cases ADD INDEX IF NOT EXISTS idx_barangay_record_id (barangay_record_id);

-- Add reference to online-filed case in barangay_records
ALTER TABLE barangay_records ADD COLUMN IF NOT EXISTS online_case_id INT NULL AFTER barangay_id;
ALTER TABLE barangay_records ADD INDEX IF NOT EXISTS idx_online_case_id (online_case_id);

-- Update status field to accommodate online case states
ALTER TABLE barangay_records MODIFY COLUMN status ENUM('ACTIVE', 'MEDIATION_IN_PROGRESS', 'SETTLED', 'ESCALATED', 'DISMISSED', 'CLOSED', 'WAITING_FOR_BARANGAY') DEFAULT 'ACTIVE';

-- Create indexes for faster lookups
CREATE INDEX IF NOT EXISTS idx_barangay_records_status_date ON barangay_records(barangay_id, status, created_at);
CREATE INDEX IF NOT EXISTS idx_cases_barangay_stage ON cases(barangay_id, stage, status);
