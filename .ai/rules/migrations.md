---
paths:
  - 'database/migrations/*_create_visits_table.php'
---

# Migrations

## Preserve Visit history when deleting Patients
Visits use a required patient foreign key with restricted deletion. Do not cascade Patient deletion into Visit history, and do not add Patient or Visit deletion workflows without an approved lifecycle rule.
