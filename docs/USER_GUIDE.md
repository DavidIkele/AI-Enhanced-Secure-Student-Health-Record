# User Guide

This guide is written for students and clinic staff using the Student Health
Record System.

> Important: the AI features are **decision support only**. They give an
> estimate of risk and are **never a medical diagnosis**. Always follow the
> guidance of qualified clinic staff.

## Getting started

Open the system in your browser at the URL provided by your institution (the
`public/` folder of the installation). Log in with the username and password
given to you.

If you enter a wrong password 5 times within a short window, your account is
temporarily locked for security. Wait for the lock to clear or contact an
administrator.

## As a student

After logging in you land on your dashboard.

### View your profile and records
- **Profile** shows your personal and health details (blood group, allergies,
  chronic conditions, etc.).
- **Records** shows your clinic visits, diagnoses, treatments, medications and
  vital signs.

### Request an appointment
1. Go to **Appointments**.
2. Choose **Request new appointment**.
3. Pick a staff member and a free time slot from the availability list.
4. Submit. Your request appears as **pending** until a staff member approves or
   rejects it (approvals/status changes come to you as notifications).

You can cancel a pending appointment from the appointments list.

### Health insights
On your profile you may see **personalized health insights** (e.g. visit
patterns, asthma management tips). Read them as helpful information —
discuss anything concerning with clinic staff. Use the marks to set an
insight as read or dismiss it.

### Notifications
**Notifications** shows appointment updates and any health advisories sent to
you. Mark items read to clear the badge.

## As clinic staff (nurse/doctor)

You have additional tools for the students you serve.

### Manage records
Open a student’s records from **Records**. Staff with the right permission can:
- Edit a student’s health profile (allergies, conditions, notes).
- Add a medical history entry.
- Record a clinic visit (reasons, complaints, assessment).

### Manage appointments
- See all appointment requests in **Appointments**.
- **Approve** or **reject** pending requests.
- **Reschedule** appointments; the form shows free slots.

### Analytics & outbreaks
- **Visit analytics** shows visit patterns for monitoring.
- **Outbreak analytics** lets you run illness-pattern detection over a period
  and review flagged patterns (aggregate data only, no identities).

### AI decision support
For a student, from the records screen you can request a prediction such as
`malaria_risk`, `asthma_exacerbation` or `typhoid_risk`. The result is a
**risk score** and **risk level** (low/moderate/high) with an explanation.
Treat it purely as a supporting decision aid — clinical judgment always takes
precedence.

### Health alerts
You can send a targeted health alert to a specific student (appears in their
notifications), and generate personalized insights from their records.

## Data privacy expectations

- You see only your own records as a student; staff see the records of the
  students they support, and access is logged.
- All changes are written to an append-only audit log (who, what, when).
- Sessions time out automatically after inactivity; log out when leaving a
  shared computer.