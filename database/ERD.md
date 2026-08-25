# Entity-Relationship Diagram — Student Health Record System

Database: `student_health` (InnoDB, utf8mb4). 22 tables, 39 foreign keys.

## Relationship map

```
roles (1) ────< role_permission >──── (1) permissions
   │
   │ (1:N)
   │
users (1) ────< user_permission >──── (1) permissions
   │
   ├── (1:1) students
   │          │
   │          ├── (1:N) health_records  (unique: one profile per student)
   │          ├── (1:N) medical_histories
   │          ├── (1:N) clinic_visits
   │          │          ├── (1:N) diagnoses
   │          │          │        └── (1:N) treatments
   │          │          │                └── (1:N) medications
   │          │          └── (1:N) vital_signs
   │          ├── (1:N) appointments (N:1 healthcare_staff)
   │          ├── (1:N) ai_predictions
   │          ├── (1:N) health_alerts
   │          └── (1:N) health_insights
   │
   └── (1:1) healthcare_staff
            └── (1:N) appointments
   │
   └── (1:N) notifications
   └── (1:N) audit_logs
   └── (1:N) login_attempts

healthcare_staff (1) ────< clinic_visits (N)   (seen_by / attendee)
healthcare_staff (1) ────< appointments (N)
outbreak_analytics — aggregate table, no direct identity FK to students
```

## Table inventory

| Table | Purpose | Key uniqueness | Notable indexes |
|---|---|---|---|
| roles | Role catalog | slug | — |
| permissions | Permission catalog | slug | — |
| role_permission | M:N role→permission | (role_id, permission_id) | — |
| users | Accounts | username, email | role_id, is_active |
| user_permission | Per-user extra grants | (user_id, permission_id) | — |
| login_attempts | Brute-force/rate-limit tracking | — | (identifier, attempted_at), (ip_address, attempted_at) |
| students | Student demographics | user_id, reg_number | (last_name, first_name) |
| healthcare_staff | Clinic staff | user_id, staff_id | — |
| health_records | Core health profile | student_id (1 profile) | — |
| medical_histories | Past/current conditions | — | student_id, condition_name |
| clinic_visits | Visit encounters | — | student_id, visited_at, staff, status |
| diagnoses | Diagnoses per visit | — | clinic_visit_id, name, icd_code |
| treatments | Treatment plans | — | clinic_visit_id, diagnosis_id |
| medications | Prescribed drugs | — | treatment_id, clinic_visit_id, name |
| vital_signs | Objective measurements | — | clinic_visit_id, student_id, measured_at |
| appointments | Booking requests | (staff_id, scheduled_at) backstop | student_id, staff, scheduled_at, status |
| ai_predictions | AI decision-support outputs | — | student_id, prediction_type, created_at |
| health_alerts | Personal/outbreak/system alerts | — | student_id, type, severity |
| outbreak_analytics | Category-level outbreak aggregates | (category, period_start, period_end) | is_flagged, period |
| health_insights | Personalized non-diagnostic insights | — | (student_id, is_read), type |
| notifications | User notifications | (user_id, type, ref_type, ref_id) dedup | (user_id, is_read), type, ref |
| audit_logs | Append-only audit trail | — | user, action, (entity_type, entity_id), created_at |

## Design decisions

1. **Normalization** — clinical data is split into visits → diagnoses → treatments → medications
   and vital_signs so each fact is stored once and queried by relationship, not by duplication.
2. **Referential integrity** — every child table enforces FKs (`ON DELETE RESTRICT` for clinical
   parents, `CASCADE` for pure compositions, `SET NULL` for audit/actor references).
3. **Duplicate prevention** — unique keys on usernames/emails, reg numbers, staff ids, one health
   profile per student, per-category outbreak period, notification dedup, and an appointment
   staff-slot backstop (application-level overlap detection handles the rest).
4. **Soft deletes** — `deleted_at` on users/students/staff for retention and auditability;
   clinical rows are never hard-deleted through the application.
5. **Privacy** — `outbreak_analytics` holds only category aggregates (no identities);
   `ai_predictions.features_snapshot` stores de-identified feature summaries.
6. **Append-only audit** — `audit_logs` has no update/delete path at application level.
7. **Enums** — validated value sets stored as ENUM (severity, status, type) to prevent junk input.
