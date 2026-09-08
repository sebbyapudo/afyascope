---
paths:
  - 'app/{Actions/Nursing,Models,Policies,Http/Controllers}/**/*.php'
---

# Nursing Models Policies Http Controllers

## Start recovery only from the completed procedure handoff
A RecoveryEpisode is started only by the authoritative Nursing action after a completed ProcedureRecord. The completed ProcedureRecord is the durable handoff; do not reconstruct consultation, billing, payment, receipt, financial-clearance, handoff, or preparation internals. Lock/revalidate the ProcedureRecord, checked-in Visit, absence of any RecoveryEpisode, and active Nurse; preserve all upstream state.
