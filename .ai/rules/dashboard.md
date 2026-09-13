---
paths:
  - 'app/Actions/Dashboard/**'
---

# Dashboard

## Dashboard projects authoritative role workload
The single /dashboard projection is count-only and role-specific. Derive workload directly from authoritative workflow records/scopes; never persist dashboard state, load narratives or identifiers, or recreate report semantics. Management headline values must reuse BuildManagementSummary.
