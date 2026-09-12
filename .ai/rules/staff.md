---
paths:
  - 'app/Actions/Staff/**'
---

# Staff

## Protect administrator coverage and active workflow ownership
Staff lifecycle writes must never leave zero active Administrators; serialize removal of active Administrator access inside the transaction. Deactivation or role changes must also reject changes that would strand an in-progress responsible Doctor consultation or Nurse-owned preparation/recovery. Historical actor links remain unchanged and users are not deleted.
