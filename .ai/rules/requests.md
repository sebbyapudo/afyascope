---
paths:
  - 'app/{Actions/Nursing,Models,Http/Controllers,Http/Requests}/**/*.php'
---

# Requests

## Recovery observations are owner-Nurse append-only
Post-procedure monitoring is recorded as append-only RecoveryObservation children of an in-progress RecoveryEpisode. Only the episode's responsible active Nurse may append; actor and recorded_at are server-controlled, audit metadata is structural only, and adding an observation never changes the episode, Visit, ProcedureRecord, or discharge/completion state.
