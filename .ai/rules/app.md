---
paths:
  - 'app/**'
  - 'app/**/Patient*.php'
---

# App

## Record audit events through the transactional recorder
Meaningful successful state changes must call App\Actions\Audit\RecordAuditLog from the same database transaction as the business write. Do not create audit rows directly in controllers. Pass only meaningful before/after values; the recorder centrally removes password, token, secret, credential, session, CSRF, and remember-key data.

## Patient registry authorization and duplicate assistance
Phase 2 Patient registry access is Receptionist-only through patients.create, patients.view, and patients.update; Administrator is not a bypass. Duplicate matching is deterministic and advisory (normalized phone, email, or exact first name + last name + DOB), never blocks registration, and never merges records. Patient deletion remains unavailable.
