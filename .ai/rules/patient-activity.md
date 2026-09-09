---
paths:
  - 'app/Actions/PatientActivity/**'
---

# Patient Activity

## Patient activity is a derived personal projection
Patient tracking is read-only and derives actor attribution from existing audited business events mapped back to the authoritative Visit. Current stage must use Visit::workflowMessage(); never persist parallel workflow/history state, widen underlying record access, or put completed work back into operational queues. Only Receptionist, Accountant, Doctor, and Nurse receive patient-activity.view.
