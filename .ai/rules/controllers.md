---
paths:
  - app/Http/Controllers/PatientController.php
---

# Controllers

## Keep the Patient profile an administrative aggregate
The Receptionist Patient profile may aggregate only existing Patient demographics, Visit facts, and Appointment facts. Scope every history query to the bound Patient: Visits newest-first, future scheduled appointments soonest-first, and cancelled/no_show appointments newest-first, with independent pagination. Do not expose audit internals, billing, clinical, procedure, or nursing data, and do not duplicate source records to build the profile.

## Keep the Patient profile an administrative aggregate
The Receptionist Patient profile may aggregate only existing Patient demographics, Visit facts, and Appointment facts. Scope every history query to the bound Patient: Visits newest-first; future scheduled appointments soonest-first; past unresolved scheduled appointments newest-first without automatic status changes; and cancelled/no_show appointments newest-first. Give every section an independent paginator and unique page query name. Do not expose audit internals, billing, clinical, procedure, or nursing data, and do not duplicate source records to build the profile.
