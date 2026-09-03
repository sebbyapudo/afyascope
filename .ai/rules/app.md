---
paths:
  - 'app/**'
  - 'app/**/Patient*.php'
  - 'app/**/Visit*.php'
  - 'app/**/*Appointment*.php'
  - 'app/**/*{Appointment,Visit}*.php'
  - 'app/**/*Consultation*.php'
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

## Keep the Reception Appointment-to-Visit handoff minimal
A scheduled Appointment may produce at most one Visit through the Reception handoff. The Visit reuses the Appointment Patient, remains `created`, and exposes `Awaiting consultation billing`; the Appointment stays preserved with its existing status. Enforce one Visit per Appointment with nullable unique `visits.appointment_id`, lock the Appointment during handoff, and do not add billing, clearance, check-in, clinical, or automatic Appointment transitions.

## Reception check-in owns the Doctor-ready transition
A Visit moves from `created` to `checked_in` only through `CheckInVisit`, after an exact paid consultation Bill, Receipt, and consultation financial clearance are transactionally revalidated. One immutable `VisitCheckIn` per Visit records Receptionist/time/reference and one `visit.checked_in` audit; check-in creates no consultation record.

## Consultation owns clinical progression after check-in
A checked-in Visit with no Consultation is ready for Doctor consultation; absence represents not started. MVP allows one Consultation per Visit, assigned to an active Doctor, with only in_progress then finalized; finalized records are immutable. Consultation creation/finalization never changes Visit.status or creates ProcedureBillingHandoff; only the future authoritative Doctor procedure-decision action may create that handoff.

## Begin consultation only through the Doctor action
BeginConsultation requires an active Doctor, a checked_in Visit, a persisted VisitCheckIn, and no existing Consultation. VisitCheckIn is the authoritative financial-to-clinical handoff, so BeginConsultation must not reload or revalidate Bill, Payment, Receipt, or FinancialClearance. It locks its own prerequisites, creates one in_progress Consultation plus consultation.started audit, leaves Visit.status checked_in, and never creates ProcedureBillingHandoff.
