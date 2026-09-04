---
paths:
  - 'app/{Actions/Billing,Models,Http/Controllers}/**'
---

# Billing Models Http Controllers

## Keep procedure financial gate distinct
Procedure billing begins only from an authoritative procedure_required ProcedureDecision and its matching ProcedureBillingHandoff. Procedure Bill, exact Payment/Receipt, and procedure FinancialClearance are separate locked transitions; procedure clearance projects “Ready for Nursing preparation” without creating nursing/readiness records. Never let procedure financial records satisfy the consultation gate or vice versa.
