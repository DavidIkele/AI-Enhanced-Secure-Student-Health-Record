-- Outbreak smoke fixture: temporary coded Dengue cases used by
-- tests/outbreak_detection_smoke.php (transactional, rolled back).
-- Tokens replaced at runtime: {student_id}, {date}
INSERT INTO clinic_visits
    (student_id, healthcare_staff_id, visited_at, visit_type, reason,
     chief_complaint, assessment_notes, outcome, status, created_by)
VALUES
    ({student_id}, NULL, '{date} 09:00:00', 'routine', 'smoke fixture', 'x', 'x', 'treated', 'closed', NULL);

INSERT INTO diagnoses
    (clinic_visit_id, icd_code, name, description, severity, is_primary, diagnosed_by, diagnosed_at)
VALUES
    (LAST_INSERT_ID(), 'A90', 'Dengue fever', 'smoke fixture', 'moderate', 1, NULL, '{date}');
