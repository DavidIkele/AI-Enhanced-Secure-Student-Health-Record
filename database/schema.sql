-- ============================================================================
-- AI-ENHANCED SECURE WEB-BASED STUDENT HEALTH RECORD MANAGEMENT SYSTEM
-- DATABASE SCHEMA
--
-- Target: MySQL 8+ / MariaDB 10.4+ (XAMPP). Engine: InnoDB, utf8mb4.
--
-- This file is a DEV install script. The DROP statements at the top wipe the
-- tables in dependency order so the schema can be re-created cleanly during
-- development. NEVER point this at a production database.
--
-- Design notes:
--   * All monetary/free-text sizes are explicit; indexes cover all FKs.
--   * status/severity/type columns use ENUM for compact, validated values.
--   * Health data uses soft deletes (deleted_at) for retention/audit.
--   * Audit logs are append-only  - no UPDATE/DELETE path.
--   * JSON columns hold structured metadata; raw patient PII is minimised.
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- DROP (reverse dependency order) - dev re-install only
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS health_insights;
DROP TABLE IF EXISTS outbreak_analytics;
DROP TABLE IF EXISTS health_alerts;
DROP TABLE IF EXISTS ai_predictions;
DROP TABLE IF EXISTS appointments;
DROP TABLE IF EXISTS vital_signs;
DROP TABLE IF EXISTS medications;
DROP TABLE IF EXISTS treatments;
DROP TABLE IF EXISTS diagnoses;
DROP TABLE IF EXISTS clinic_visits;
DROP TABLE IF EXISTS medical_histories;
DROP TABLE IF EXISTS health_records;
DROP TABLE IF EXISTS healthcare_staff;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS login_attempts;
DROP TABLE IF EXISTS user_preferences;
DROP TABLE IF EXISTS user_permission;
DROP TABLE IF EXISTS role_permission;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------------
-- ACCESS CONTROL
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS roles (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(50)      NOT NULL,
    name          VARCHAR(100)     NOT NULL,
    description   VARCHAR(255)     NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_roles_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id            INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    slug          VARCHAR(80)      NOT NULL,
    name          VARCHAR(120)     NOT NULL,
    description   VARCHAR(255)     NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_permissions_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS role_permission (
    role_id       INT UNSIGNED     NOT NULL,
    permission_id INT UNSIGNED     NOT NULL,
    PRIMARY KEY (role_id, permission_id),
    CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS users (
    id                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    username              VARCHAR(50)   NOT NULL,
    email                 VARCHAR(190)  NOT NULL,
    password_hash         VARCHAR(255)  NOT NULL,
    role_id               INT UNSIGNED  NOT NULL,
    is_active             TINYINT(1)    NOT NULL DEFAULT 1,
    must_change_password  TINYINT(1)    NOT NULL DEFAULT 0,
    last_login_at         DATETIME      NULL,
    failed_login_attempts INT UNSIGNED  NOT NULL DEFAULT 0,
    locked_until          DATETIME      NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at            DATETIME      NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    KEY idx_users_active (is_active),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles (id)
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional per-user permission grants beyond role permissions.
CREATE TABLE IF NOT EXISTS user_permission (
    user_id       INT UNSIGNED  NOT NULL,
    permission_id INT UNSIGNED  NOT NULL,
    granted_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, permission_id),
    CONSTRAINT fk_up_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_up_permission FOREIGN KEY (permission_id) REFERENCES permissions (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login attempt tracking for rate limiting / lockout .
CREATE TABLE IF NOT EXISTS login_attempts (
    id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id      INT UNSIGNED     NULL,
    identifier   VARCHAR(190)     NOT NULL,   -- email or username as submitted
    ip_address   VARCHAR(45)      NOT NULL,
    succeeded    TINYINT(1)       NOT NULL DEFAULT 0,
    attempted_at DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_la_identifier (identifier, attempted_at),
    KEY idx_la_ip (ip_address, attempted_at),
    KEY idx_la_user (user_id),
    CONSTRAINT fk_la_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- PEOPLE
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS students (
    id                        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id                   INT UNSIGNED  NOT NULL,
    reg_number                VARCHAR(30)   NOT NULL,
    first_name                VARCHAR(80)   NOT NULL,
    last_name                 VARCHAR(80)   NOT NULL,
    other_names               VARCHAR(120)  NULL,
    date_of_birth             DATE          NULL,
    gender                    ENUM('male','female','other') NULL,
    email                     VARCHAR(190)  NULL,
    phone                     VARCHAR(30)   NULL,
    address                   VARCHAR(255)  NULL,
    department                VARCHAR(120)  NULL,
    faculty                   VARCHAR(120)  NULL,
    level_of_study            VARCHAR(30)   NULL,
    emergency_contact_name    VARCHAR(120)  NULL,
    emergency_contact_phone   VARCHAR(30)   NULL,
    created_at                DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at                DATETIME      NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_students_user (user_id),
    UNIQUE KEY uq_students_reg (reg_number),
    KEY idx_students_name (last_name, first_name),
    CONSTRAINT fk_students_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS healthcare_staff (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id         INT UNSIGNED  NOT NULL,
    staff_id        VARCHAR(30)   NOT NULL,
    title           VARCHAR(40)   NULL,
    first_name      VARCHAR(80)   NOT NULL,
    last_name       VARCHAR(80)   NOT NULL,
    other_names     VARCHAR(120)  NULL,
    role_name       VARCHAR(80)   NULL,   -- e.g. Nurse, Doctor, Pharmacist
    specialization  VARCHAR(120)  NULL,
    department      VARCHAR(120)  NULL,
    phone           VARCHAR(30)   NULL,
    email           VARCHAR(190)  NULL,
    is_active       TINYINT(1)    NOT NULL DEFAULT 1,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at      DATETIME      NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_staff_user (user_id),
    UNIQUE KEY uq_staff_staffid (staff_id),
    CONSTRAINT fk_staff_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- HEALTH DATA
-- ---------------------------------------------------------------------------

-- Core health profile. One active profile per student (soft-replaceable).
CREATE TABLE IF NOT EXISTS health_records (
    id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id          INT UNSIGNED  NOT NULL,
    blood_group         ENUM('A+','A-','B+','B-','AB+','AB-','O+','O-','Unknown') NOT NULL DEFAULT 'Unknown',
    genotype            ENUM('AA','AS','SS','AC','SC','Unknown') NOT NULL DEFAULT 'Unknown',
    height_cm           DECIMAL(5,2)  NULL,
    weight_kg           DECIMAL(5,2)  NULL,
    allergies           TEXT          NULL,   -- comma-separated free text
    chronic_conditions  TEXT          NULL,
    disabilities        TEXT          NULL,
    family_history      TEXT          NULL,
    notes               TEXT          NULL,
    created_by          INT UNSIGNED  NULL,
    updated_by          INT UNSIGNED  NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_healthrecords_student (student_id),
    CONSTRAINT fk_hr_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_hr_createdby FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_hr_updatedby FOREIGN KEY (updated_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medical_histories (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED  NOT NULL,
    condition_name  VARCHAR(150)  NOT NULL,
    description     TEXT          NULL,
    onset_date      DATE          NULL,
    is_recurring    TINYINT(1)    NOT NULL DEFAULT 0,
    severity        ENUM('mild','moderate','severe','critical') NOT NULL DEFAULT 'mild',
    status          ENUM('active','resolved') NOT NULL DEFAULT 'active',
    recorded_by     INT UNSIGNED  NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_mh_student (student_id),
    KEY idx_mh_condition (condition_name),
    KEY idx_mh_recurring (is_recurring, created_at),
    CONSTRAINT fk_mh_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mh_recordedby FOREIGN KEY (recorded_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinic_visits (
    id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id          INT UNSIGNED  NOT NULL,
    healthcare_staff_id INT UNSIGNED  NULL,
    visited_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    visit_type          ENUM('initial','follow_up','emergency','routine','referral') NOT NULL DEFAULT 'routine',
    reason              VARCHAR(255)  NOT NULL,
    chief_complaint     TEXT          NULL,
    assessment_notes    TEXT          NULL,
    outcome             ENUM('treated','referred','admitted','observation','discharged') NULL,
    status              ENUM('open','closed') NOT NULL DEFAULT 'open',
    created_by          INT UNSIGNED  NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cv_student (student_id),
    KEY idx_cv_visited (visited_at),
    KEY idx_cv_staff (healthcare_staff_id),
    KEY idx_cv_status (status),
    CONSTRAINT fk_cv_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cv_staff FOREIGN KEY (healthcare_staff_id) REFERENCES healthcare_staff (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_cv_createdby FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS diagnoses (
    id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    clinic_visit_id     INT UNSIGNED  NOT NULL,
    icd_code            VARCHAR(20)   NULL,
    name                VARCHAR(150)  NOT NULL,
    description         TEXT          NULL,
    severity            ENUM('mild','moderate','severe','critical') NOT NULL DEFAULT 'mild',
    is_primary          TINYINT(1)    NOT NULL DEFAULT 0,
    diagnosed_by        INT UNSIGNED  NULL,
    diagnosed_at        DATE          NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_dx_visit (clinic_visit_id),
    KEY idx_dx_name (name),
    KEY idx_dx_icd (icd_code),
    CONSTRAINT fk_dx_visit FOREIGN KEY (clinic_visit_id) REFERENCES clinic_visits (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_dx_diagnosedby FOREIGN KEY (diagnosed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS treatments (
    id                  INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    clinic_visit_id     INT UNSIGNED  NOT NULL,
    diagnosis_id        INT UNSIGNED  NULL,
    name                VARCHAR(150)  NOT NULL,
    description         TEXT          NULL,
    treatment_type      ENUM('medication','procedure','therapy','counseling','referral','other') NOT NULL DEFAULT 'other',
    started_at          DATE          NULL,
    ended_at            DATE          NULL,
    status              ENUM('planned','in_progress','completed','discontinued') NOT NULL DEFAULT 'planned',
    prescribed_by       INT UNSIGNED  NULL,
    created_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tr_visit (clinic_visit_id),
    KEY idx_tr_diagnosis (diagnosis_id),
    CONSTRAINT fk_tr_visit FOREIGN KEY (clinic_visit_id) REFERENCES clinic_visits (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tr_diagnosis FOREIGN KEY (diagnosis_id) REFERENCES diagnoses (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_tr_prescribedby FOREIGN KEY (prescribed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS medications (
    id              INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    treatment_id    INT UNSIGNED  NULL,
    clinic_visit_id INT UNSIGNED  NULL,
    name            VARCHAR(150)  NOT NULL,
    dosage          VARCHAR(100)  NOT NULL,
    frequency       VARCHAR(100)  NULL,
    route           VARCHAR(50)   NULL,
    quantity        VARCHAR(50)   NULL,
    duration_days   SMALLINT UNSIGNED NULL,
    instructions    TEXT          NULL,
    status          ENUM('active','completed','discontinued','stopped') NOT NULL DEFAULT 'active',
    prescribed_by   INT UNSIGNED  NULL,
    prescribed_at   DATE          NULL,
    created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_med_treatment (treatment_id),
    KEY idx_med_visit (clinic_visit_id),
    KEY idx_med_name (name),
    CONSTRAINT fk_med_treatment FOREIGN KEY (treatment_id) REFERENCES treatments (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_med_visit FOREIGN KEY (clinic_visit_id) REFERENCES clinic_visits (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_med_prescribedby FOREIGN KEY (prescribed_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vital_signs (
    id                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    clinic_visit_id       INT UNSIGNED  NULL,
    student_id            INT UNSIGNED  NOT NULL,
    temperature_c         DECIMAL(4,1)  NULL,
    blood_pressure_sys    SMALLINT UNSIGNED NULL,
    blood_pressure_dia    SMALLINT UNSIGNED NULL,
    heart_rate            SMALLINT UNSIGNED NULL,
    respiratory_rate      SMALLINT UNSIGNED NULL,
    oxygen_saturation     SMALLINT UNSIGNED NULL,
    weight_kg             DECIMAL(5,2)  NULL,
    height_cm             DECIMAL(5,2)  NULL,
    bmi                   DECIMAL(4,1)  NULL,
    measured_at           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    recorded_by           INT UNSIGNED  NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_vs_visit (clinic_visit_id),
    KEY idx_vs_student (student_id),
    KEY idx_vs_measured (measured_at),
    CONSTRAINT fk_vs_visit FOREIGN KEY (clinic_visit_id) REFERENCES clinic_visits (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_vs_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_vs_recordedby FOREIGN KEY (recorded_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- APPOINTMENTS
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS appointments (
    id                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id            INT UNSIGNED  NOT NULL,
    healthcare_staff_id   INT UNSIGNED  NOT NULL,
    scheduled_at          DATETIME      NOT NULL,
    duration_minutes      SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    reason                VARCHAR(255)  NOT NULL,
    status                ENUM('pending','approved','rejected','cancelled','completed','no_show') NOT NULL DEFAULT 'pending',
    cancellation_reason   VARCHAR(255)  NULL,
    admin_notes           TEXT          NULL,
    requested_by          INT UNSIGNED  NULL,
    handled_by            INT UNSIGNED  NULL,
    created_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_app_student (student_id),
    KEY idx_app_staff (healthcare_staff_id),
    KEY idx_app_scheduled (scheduled_at),
    KEY idx_app_status (status),
    -- DB-level backstop against exact double booking for the same staff slot.
    -- Application-level overlap detection  handles overlapping
    -- windows; this guards identical timestamps.
    -- The unique index only applies to occupying statuses (pending/approved):
    -- cancelled/rejected/completed/no_show rows yield NULL (freed slot) so the
    -- same timestamp can be re-booked.
    occupies_at DATETIME GENERATED ALWAYS AS (
        CASE WHEN status IN ('pending','approved') THEN scheduled_at ELSE NULL END
    ) STORED,
    UNIQUE KEY uq_app_staff_slot (healthcare_staff_id, occupies_at),
    CONSTRAINT fk_app_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_app_staff FOREIGN KEY (healthcare_staff_id) REFERENCES healthcare_staff (id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_app_requestedby FOREIGN KEY (requested_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_app_handledby FOREIGN KEY (handled_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- AI / INSIGHTS / ALERTS
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ai_predictions (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED     NULL,   -- nullable: supports anonymised batches
    prediction_type VARCHAR(80)      NOT NULL,
    risk_level      ENUM('low','moderate','high') NULL,
    risk_score      DECIMAL(5,4)     NULL,
    confidence      DECIMAL(5,4)     NULL,
    model_version   VARCHAR(50)      NOT NULL,
    features_snapshot JSON           NULL,   -- de-identified input summary only
    explanation     TEXT             NULL,
    status          ENUM('delivered','pending','failed') NOT NULL DEFAULT 'pending',
    requested_by    INT UNSIGNED     NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ap_student (student_id),
    KEY idx_ap_type (prediction_type),
    KEY idx_ap_created (created_at),
    CONSTRAINT fk_ap_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ap_requestedby FOREIGN KEY (requested_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Symptom assessments: staff enter the symptoms a student reported and the
-- decision-support assistant suggests possible conditions. The clinical text is
-- staff-authored record data (like chief_complaint); the stored result is the
-- validated service output (ranked conditions, never a diagnosis).
CREATE TABLE IF NOT EXISTS symptom_assessments (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED     NOT NULL,
    symptoms_text   TEXT             NOT NULL,
    matched_symptoms JSON            NULL,
    result          JSON             NOT NULL,
    model_version   VARCHAR(50)      NOT NULL,
    status          ENUM('delivered','failed') NOT NULL DEFAULT 'delivered',
    explanation     TEXT             NULL,
    created_by      INT UNSIGNED     NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_sa_student (student_id),
    KEY idx_sa_created (created_at),
    CONSTRAINT fk_sa_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_sa_createdby FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS health_alerts (
    id           BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id   INT UNSIGNED     NULL,
    alert_type   ENUM('personal','outbreak','system') NOT NULL DEFAULT 'system',
    severity     ENUM('info','warning','critical') NOT NULL DEFAULT 'info',
    title        VARCHAR(150)     NOT NULL,
    message      TEXT             NOT NULL,
    metadata     JSON             NULL,
    is_resolved  TINYINT(1)       NOT NULL DEFAULT 0,
    resolved_by  INT UNSIGNED     NULL,
    resolved_at  DATETIME         NULL,
    created_at   DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_ha_student (student_id),
    KEY idx_ha_type (alert_type),
    KEY idx_ha_severity (severity),
    KEY idx_ha_created (created_at),
    CONSTRAINT fk_ha_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ha_resolvedby FOREIGN KEY (resolved_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Aggregated illness-pattern / outbreak detection results .
-- Stores category-level aggregates, never individual identities.
CREATE TABLE IF NOT EXISTS outbreak_analytics (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    illness_category VARCHAR(120)     NOT NULL,
    period_start    DATE              NOT NULL,
    period_end      DATE              NOT NULL,
    baseline_count  INT UNSIGNED      NOT NULL DEFAULT 0,
    observed_count  INT UNSIGNED      NOT NULL DEFAULT 0,
    z_score         DECIMAL(8,3)      NULL,
    alert_level     ENUM('none','watch','warning','elevated') NOT NULL DEFAULT 'none',
    is_flagged      TINYINT(1)        NOT NULL DEFAULT 0,
    created_by      INT UNSIGNED      NULL,
    created_at      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_oa_category_period (illness_category, period_start, period_end),
    KEY idx_oa_flagged (is_flagged),
    KEY idx_oa_period (period_start, period_end),
    CONSTRAINT fk_oa_createdby FOREIGN KEY (created_by) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS health_insights (
    id              BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    student_id      INT UNSIGNED     NOT NULL,
    insight_type    VARCHAR(80)      NOT NULL,
    title           VARCHAR(150)     NOT NULL,
    content         TEXT             NOT NULL,
    data_version    VARCHAR(50)      NULL,
    status          ENUM('active','dismissed','expired') NOT NULL DEFAULT 'active',
    is_read         TINYINT(1)       NOT NULL DEFAULT 0,
    read_at         DATETIME         NULL,
    created_at      DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_hi_student (student_id),
    KEY idx_hi_student_read (student_id, is_read),
    KEY idx_hi_type (insight_type),
    KEY idx_hi_created (created_at),
    CONSTRAINT fk_hi_student FOREIGN KEY (student_id) REFERENCES students (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- NOTIFICATIONS
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS notifications (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED     NOT NULL,
    type          VARCHAR(50)      NOT NULL,
    title         VARCHAR(150)     NOT NULL,
    body          TEXT             NULL,
    reference_type VARCHAR(80)     NULL,   -- e.g. appointment, insight, alert
    reference_id  BIGINT UNSIGNED  NULL,
    is_read       TINYINT(1)       NOT NULL DEFAULT 0,
    read_at       DATETIME         NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notif_user (user_id, is_read),
    KEY idx_notif_type (type),
    KEY idx_notif_ref (reference_type, reference_id),
    -- Prevent exact duplicate notifications for the same target/event.
    UNIQUE KEY uq_notif_dedup (user_id, type, reference_type, reference_id),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- AUDIT LOG (append-only)
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS audit_logs (
    id            BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    user_id       INT UNSIGNED     NULL,
    action        VARCHAR(80)      NOT NULL,
    entity_type   VARCHAR(80)      NOT NULL,
    entity_id     VARCHAR(64)      NULL,
    old_values    JSON             NULL,
    new_values    JSON             NULL,
    ip_address    VARCHAR(45)      NULL,
    user_agent    VARCHAR(255)     NULL,
    request_method VARCHAR(10)     NULL,
    request_path  VARCHAR(255)     NULL,
    created_at    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_audit_user (user_id),
    KEY idx_audit_action (action),
    KEY idx_audit_entity (entity_type, entity_id),
    KEY idx_audit_created (created_at),
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- USER PREFERENCES (per-account notification/UI opt-ins)
-- ---------------------------------------------------------------------------
-- One row per user. Missing rows are treated as "default on" by the
-- application (see UserPreferencesRepository::get). Soft-deleted users
-- cascade their preference row.
CREATE TABLE IF NOT EXISTS user_preferences (
    user_id                    INT UNSIGNED  NOT NULL,
    -- In-app + email notifications for appointment status changes.
    notify_appointment_changes TINYINT(1)    NOT NULL DEFAULT 1,
    -- Personalised health insights  generated by staff.
    notify_health_insights     TINYINT(1)    NOT NULL DEFAULT 1,
    -- Personal health alerts  from authorised clinic staff.
    notify_health_alerts       TINYINT(1)    NOT NULL DEFAULT 1,
    -- System-wide announcements from administrators.
    notify_system_announcements TINYINT(1)   NOT NULL DEFAULT 1,
    -- Reminder notifications ahead of a confirmed clinic appointment.
    appointment_reminder_opt_in TINYINT(1)   NOT NULL DEFAULT 1,
    created_at                 DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at                 DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_upref_user FOREIGN KEY (user_id) REFERENCES users (id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
