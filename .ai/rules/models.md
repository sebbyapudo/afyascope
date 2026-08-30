---
paths:
  - 'app/Models/{Patient,Visit}.php'
---

# Models

## Keep Patient and Visit references immutable
Patient and Visit references use server-generated `PAT-` / `VIS-` prefixes plus a 26-character ULID, backed by unique database indexes. Creation input must not set them and persisted references must not be edited. New Visits begin only in `created`; add later states only with their owning workflow checkpoint.

## Keep Patient and Visit references immutable
Patient and Visit references use `PAT-` / `VIS-` plus the model's MySQL auto-increment ID padded to at least six digits. Generate the final reference inside model creation after the ID exists, keep the unique database indexes, reject reference input in creation actions, and prevent later edits. New Visits begin only in `created`; add later states only with their owning workflow checkpoint.

## Sequential references supersede the earlier ULID rule
The earlier ULID reference rule in this file is obsolete following the accepted Checkpoint 1 amendment. Use only the auto-increment-derived `PAT-000001` / `VIS-000001` strategy described below; ULIDs may be transaction-local placeholders but must never be the persisted operational reference.
