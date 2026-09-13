---
paths:
  - 'app/Actions/Reporting/**'
---

# Reporting

## Reporting reads authoritative operational records
Reporting is read-only and aggregates existing operational tables server-side; do not create mirror tables or mutate operational records. Date semantics are explicit per measure: Visit occurred_at/completed_at, Bill created_at, Payment recorded_at, ProcedureRecord completed_at, and RecoveryDischarge discharged_at. Historical billed/outstanding amounts use immutable BillItem snapshots, never current catalog prices. Aggregate contracts exclude patient identifiers, clinical narratives, and raw audit metadata, and must authorize reports.management.view at the query boundary (Administrator and Management only).

## Scope authorization and derive operational stages by report
Every report builder authorizes its own report permission at the query boundary. BuildManagementSummary remains reports.management.view (Administrator/Management), while BuildOperationalReport uses reports.operational.view (Receptionist/Administrator/Management). Operational stage distribution is a read-only current-state projection over the selected Visit occurrence cohort using authoritative durable workflow records; never persist a parallel stage field or expose patient identifiers, narratives, financial values, or audit internals.

## Financial report event cohorts and immutable amounts
Financial reporting is authorized by reports.financial.view for active Accountant, Administrator, and Management users only. Compute billed Bill cohorts by bills.created_at using immutable bill_items.amount_minor snapshots; payments by payments.recorded_at, receipts by receipts.issued_at, and clearances by financial_clearances.granted_at. Outstanding is the current balance of Bills created in the selected period, net of their persisted authoritative Payment regardless of payment date, so billed minus in-period paid activity is not expected to equal outstanding.

## Clinical report event cohorts and catalog labels
Clinical/procedure reports count each lifecycle event by its own authoritative event timestamp and expose aggregate-only data. Procedure distribution is grouped by durable service_catalog_item_id but displays the current catalog name: renames relabel historical aggregates, while repricing or deactivation must not change counts. Do not infer completion percentages across independently date-bounded started/completed cohorts, and never expose narratives, Patient/Visit identifiers, financial amounts, or raw audit data.
