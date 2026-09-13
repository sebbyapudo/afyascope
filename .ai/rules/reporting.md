---
paths:
  - 'app/Actions/Reporting/**'
---

# Reporting

## Reporting reads authoritative operational records
Reporting is read-only and aggregates existing operational tables server-side; do not create mirror tables or mutate operational records. Date semantics are explicit per measure: Visit occurred_at/completed_at, Bill created_at, Payment recorded_at, ProcedureRecord completed_at, and RecoveryDischarge discharged_at. Historical billed/outstanding amounts use immutable BillItem snapshots, never current catalog prices. Aggregate contracts exclude patient identifiers, clinical narratives, and raw audit metadata, and must authorize reports.management.view at the query boundary (Administrator and Management only).

## Scope authorization and derive operational stages by report
Every report builder authorizes its own report permission at the query boundary. BuildManagementSummary remains reports.management.view (Administrator/Management), while BuildOperationalReport uses reports.operational.view (Receptionist/Administrator/Management). Operational stage distribution is a read-only current-state projection over the selected Visit occurrence cohort using authoritative durable workflow records; never persist a parallel stage field or expose patient identifiers, narratives, financial values, or audit internals.
