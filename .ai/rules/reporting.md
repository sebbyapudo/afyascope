---
paths:
  - 'app/Actions/Reporting/**'
---

# Reporting

## Reporting reads authoritative operational records
Reporting is read-only and aggregates existing operational tables server-side; do not create mirror tables or mutate operational records. Date semantics are explicit per measure: Visit occurred_at/completed_at, Bill created_at, Payment recorded_at, ProcedureRecord completed_at, and RecoveryDischarge discharged_at. Historical billed/outstanding amounts use immutable BillItem snapshots, never current catalog prices. Aggregate contracts exclude patient identifiers, clinical narratives, and raw audit metadata, and must authorize reports.management.view at the query boundary (Administrator and Management only).
