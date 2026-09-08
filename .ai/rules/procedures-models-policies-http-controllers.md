---
paths:
  - 'app/{Actions/Procedures,Models,Policies,Http/Controllers}/**'
---

# Procedures Models Policies Http Controllers

## Doctor procedure starts only from completed Nurse readiness
A completed PreProcedureReadiness is the authoritative Nursing-to-Doctor handoff. Only the responsible active Doctor may start, document, and complete the single ProcedureRecord for the Visit/decision; the selected procedure and ownership remain immutable. Completion projects Ready for Nursing recovery and must not create recovery or discharge records or revalidate financial internals.
