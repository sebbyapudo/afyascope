---
paths:
  - 'app/{Actions/Nursing,Models,Policies,Http/Controllers,Http/Requests}/**/*.php'
---

# Nursing Models Policies Http Controllers Http Requests

## Finalize discharge only from durable Nursing readiness
One immutable RecoveryDischarge finalizes an uncomplicated RecoveryEpisode. Only its responsible active Nurse may discharge after a current criteria-met readiness assessment and with no open escalation; Doctor resolution never discharges. Finalization atomically creates the record, moves RecoveryEpisode ready_for_discharge to completed/Discharged, and records one safe audit event. It must not complete the Visit or create a Patient Timeline lifecycle handoff.
