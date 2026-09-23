-- Holiday Feature Migration
-- Run this SQL on the u946810828_Next database (via phpMyAdmin or CLI)
-- 
-- Adds 'Holiday' as a valid attendance status alongside Present and Absent.
-- This is a non-destructive change — no existing data is modified.

ALTER TABLE `student_attendance` 
MODIFY `status` ENUM('Present','Absent','Holiday') NOT NULL;
