---
paths:
  - 'app/Actions/Administration/**'
---

# Administration

## Catalog prices affect future Bills only
ServiceCatalogItem.unit_price_minor is the authoritative current positive KES price. Change it only through the focused Administrator price-update action, with locking, stale-write protection, and service.price_updated audit. Existing BillItem amount/description snapshots and downstream payment, receipt, clearance, and completed-workflow records are immutable and must never be repriced.
