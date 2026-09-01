---
paths:
  - 'app/{Models,Actions,Policies}/**/*.php'
---

# Models Actions Policies

## Keep financial gates Visit-scoped and exact
Bills belong to a Visit; do not duplicate patient_id because Patient is reached through Visit. Consultation and procedure remain distinct Bill types, with at most one Bill per Visit/type. Store prices as positive integer minor units, and snapshot the catalog description and amount onto immutable Bill items. Payment, financial clearance, and check-in remain separate later-stage concepts.

## Create consultation Bills through the guarded Visit action
Create a consultation Bill only through CreateConsultationBill: authorize billing.create, lock the Visit and selected catalog service in one transaction, require an active consultation-category service, create exactly one Bill item snapshot, and record bill.created before commit. The Visit remains created; derive its handoff as Awaiting consultation payment when the consultation Bill exists. Do not add payment, clearance, or check-in state to Visit.

## Record consultation payment and Receipt atomically
Consultation payment accepts only a locally recorded method; derive the exact positive Bill total server-side. In one retryable transaction, lock/revalidate the open consultation Bill, create one Payment and one Receipt, mark the Bill paid, and record payment.recorded plus receipt.issued. Unique bill_id/payment_id constraints are concurrency backstops. Payment does not grant financial clearance or check in the Visit.
