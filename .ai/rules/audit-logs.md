---
paths:
  - 'app/Actions/Audit/**,app/Http/Controllers/AuditLogController.php,resources/js/pages/audit-logs/**'
---

# Audit Logs

## Audit review uses an explicit safe projection
Audit administration is strictly read-only and must project only event-aware allowlisted structural fields. Never render raw before/after/metadata JSON or clinical narratives, recovery observations/vitals, discharge instructions, credentials, tokens, or secrets. Prefer event-time snapshot references; because actor roles are not snapshotted, never present a current role as the historical role.
