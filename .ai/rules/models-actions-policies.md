---
paths:
  - 'app/{Models,Actions,Policies}/**/*.php'
---

# Models Actions Policies

## Keep financial gates Visit-scoped and exact
Bills belong to a Visit; do not duplicate patient_id because Patient is reached through Visit. Consultation and procedure remain distinct Bill types, with at most one Bill per Visit/type. Store prices as positive integer minor units, and snapshot the catalog description and amount onto immutable Bill items. Payment, financial clearance, and check-in remain separate later-stage concepts.
