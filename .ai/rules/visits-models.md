---
paths:
  - 'app/{Actions/Consultations,Actions/Nursing,Actions/Visits,Models}/**/*.php'
---

# Visits Models

## Complete Visits only from terminal clinical handoffs
A Visit completes automatically and atomically only from one of two authoritative durable handoffs: an immutable Doctor no-procedure decision, or a finalized Nursing RecoveryDischarge after completed recovery. This supersedes the temporary Phase 5.5 boundary that discharge did not complete the Visit. Finalize the Consultation, set Visit status/completed_at, and record visit.completed in the same transaction; never add a generic/manual completion endpoint or permission. Downstream completion trusts the durable handoff and must not revalidate unrelated upstream module internals.
