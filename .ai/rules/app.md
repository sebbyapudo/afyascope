---
paths:
  - 'app/**'
  - 'app/**/Patient*.php'
  - 'app/**/Visit*.php'
  - 'app/**/*Appointment*.php'
---

# App

## Record audit events through the transactional recorder
Meaningful successful state changes must call App\Actions\Audit\RecordAuditLog from the same database transaction as the business write. Do not create audit rows directly in controllers. Pass only meaningful before/after values; the recorder centrally removes password, token, secret, credential, session, CSRF, and remember-key data.

## Patient registry authorization and duplicate assistance
Phase 2 Patient registry access is Receptionist-only through patients.create, patients.view, and patients.update; Administrator is not a bypass. Duplicate matching is deterministic and advisory (normalized phone, email, or exact first name + last name + DOB), never blocks registration, and never merges records. Patient deletion remains unavailable.

## Keep Checkpoint 3 Visit management administrative
Visit registry, detail, and creation are Receptionist-only through visits.view and visits.create. HTTP creation always targets an existing Patient and keeps occurred_at, visit_number, patient_id, and status server-controlled. Until the owning later checkpoints are approved, created is the only Visit status and may be presented as awaiting consultation billing; do not add billing, clearance, check-in, clinical, procedure, nursing, update, or deletion behavior.

## Appointment workflow is scheduling-only
Appointments remain separate from Visits. Their only statuses are scheduled, cancelled, and no_show; cancelled/no_show are terminal. Create and reschedule require a future scheduled_at. Do not add attended/completed, administrative notes, deletion, billing, check-in, clinical, or Visit-creation behavior without explicit approval. Repeated/no-op transitions must not create audit events.
