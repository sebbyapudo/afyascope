---
paths:
  - 'app/**'
---

# App

## Record audit events through the transactional recorder
Meaningful successful state changes must call App\Actions\Audit\RecordAuditLog from the same database transaction as the business write. Do not create audit rows directly in controllers. Pass only meaningful before/after values; the recorder centrally removes password, token, secret, credential, session, CSRF, and remember-key data.
