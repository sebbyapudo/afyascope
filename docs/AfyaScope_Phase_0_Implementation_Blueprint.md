# AfyaScope HMS — Phase 0 Implementation Blueprint

**Status:** Validated / Frozen for MVP development  
**Purpose:** Canonical requirements, workflow, actor, module, RBAC, and cross-cutting-services reference for human developers and coding agents.  
**Implementation stack:** Laravel backend + React/Inertia frontend.  

---

## 1. Document Purpose

This document is the Phase 0 source of truth for the AfyaScope Hospital Management System (HMS), initially focused on an endoscopy-clinic workflow.

It is intended to:

- define the MVP system scope before implementation;
- give developers and coding agents a consistent understanding of the application;
- define the normal end-to-end patient journey;
- define actor responsibilities and handoffs;
- establish module boundaries;
- establish high-level authorization boundaries;
- define shared/cross-cutting capabilities;
- prevent unnecessary workflow complexity from being introduced during implementation.

This specification intentionally focuses on **general operational workflows**. Edge cases, exceptional clinical scenarios, complicated escalation paths, multi-facility logic, advanced workflow engines, and speculative requirements are outside the Phase 0 MVP unless explicitly added later.

---

## 2. Product Definition

AfyaScope is an HMS supporting the normal operational journey of a patient through an endoscopy clinic.

At the highest level, AfyaScope coordinates:

1. patient identification and registration;
2. visit creation;
3. consultation billing and payment;
4. patient check-in;
5. doctor consultation;
6. selection/order of a procedure where required;
7. procedure billing and payment;
8. procedure preparation;
9. procedure documentation;
10. recovery;
11. discharge;
12. administration, reporting, auditing, notifications, documents, and longitudinal patient history.

---

## 3. Core Design Principles

### 3.1 Keep the MVP straightforward

Implementation must prioritize the validated normal workflow. Do not introduce additional workflow states, exception paths, approval chains, or clinical rules unless required by this specification or subsequently approved.

### 3.2 Patient and Visit are different concepts

A **Patient** is the long-lived person record.

A **Visit** represents a particular attendance/episode at the clinic.

A patient can therefore have multiple visits over time.

```text
Patient
 ├── Visit 001
 ├── Visit 002
 └── Visit 003
```

Clinical and operational records created during an attendance should normally be associated with the relevant Visit.

### 3.3 The Visit is the operational thread

For a normal attendance, the Visit connects the major workflow records:

```text
Patient
   │
   └── Visit
        ├── Consultation
        ├── Billing / Payments
        ├── Procedure
        ├── Nursing / Preparation
        ├── Recovery
        └── Discharge
```

### 3.4 Module ownership must remain clear

Each module owns its business records and actions. Shared capabilities may read or aggregate information but should not unnecessarily duplicate source records.

### 3.5 Backend authorization is authoritative

React may hide or disable actions a user cannot perform, but Laravel must enforce authorization for every protected server-side action.

---

# 4. Canonical End-to-End Patient Journey

This workflow is the authoritative normal MVP patient journey.

```text
PATIENT ARRIVES / APPOINTMENT
        │
        ▼
RECEPTIONIST
Search for existing patient
        │
        ├── Found → Open patient
        │
        └── Not found → Register patient
        │
        ▼
Create Visit
        │
        ▼
ACCOUNTANT
Consultation Billing
        │
        ▼
Record Consultation Payment
        │
        ▼
CONSULTATION FINANCIALLY CLEARED
        │
        ▼
RECEPTIONIST
Check Patient In
        │
        ▼
DOCTOR
Open Visit / Consultation
        │
        ▼
Review Patient Information
        │
        ▼
Record Consultation
        │
        ▼
Assessment / Clinical Decision
        │
        ▼
Procedure Required?
      /     \
    NO       YES
    │         │
    │         ▼
    │      Doctor determines/orders procedure
    │         │
    │         ▼
    │      Procedure/service charge determined
    │         │
    │         ▼
    │      ACCOUNTANT
    │      Procedure Billing
    │         │
    │         ▼
    │      Record Procedure Payment
    │         │
    │         ▼
    │      PROCEDURE FINANCIALLY CLEARED
    │         │
    │         ▼
    │      NURSE
    │      Procedure Preparation
    │         │
    │         ▼
    │      DOCTOR
    │      Perform / Document Procedure
    │         │
    │         ▼
    │      Procedure Completed
    │         │
    │         ▼
    │      NURSE
    │      Recovery
    │         │
    │         ▼
    │      Discharge
    │         │
    └─────────┴──────────► VISIT COMPLETED
```

---

# 5. Mandatory Two-Stage Billing Model

This rule is fundamental and must not be simplified into a single billing stage.

AfyaScope has **two distinct financial-clearance gates** in the normal procedure journey.

## 5.1 Stage 1 — Consultation billing/payment

Occurs after Visit creation and before consultation/check-in proceeds into the doctor workflow.

```text
Create Visit
    ↓
Consultation Charge
    ↓
Record Consultation Payment
    ↓
Consultation Financial Clearance
    ↓
Check-in
    ↓
Doctor Consultation
```

The purpose is to settle the known consultation charge before consultation.

## 5.2 Stage 2 — Procedure billing/payment

Occurs after consultation because the required procedure is determined during consultation.

```text
Doctor Consultation
    ↓
Procedure Determined / Ordered
    ↓
Procedure Charge Determined
    ↓
Record Procedure Payment
    ↓
Procedure Financial Clearance
    ↓
Procedure Preparation
    ↓
Procedure
```

This reflects the validated clinic workflow: the exact procedure and therefore its charge are established after clinical consultation.

## 5.3 No routine third billing stage

There is **no routine third billing/payment stage after the procedure** in the canonical MVP workflow.

Do not introduce post-procedure final billing as a normal required stage unless requirements are explicitly changed later.

---

# 6. Primary Actors

The validated operational actors are:

1. Receptionist
2. Accountant
3. Doctor
4. Nurse
5. Administrator
6. Management

These are business roles. Authentication users are assigned appropriate roles/permissions.

---

# 7. Actor Journeys

## 7.1 Receptionist Journey

### Responsibility

Own front-desk patient and visit operations.

### Normal journey

```text
Login
  ↓
Reception Dashboard
  ↓
Search Patient
  ↓
Existing? ── Yes → Open Patient
  │
  No
  ↓
Register Patient
  ↓
Create Visit
  ↓
Consultation billing/payment handled by Accountant
  ↓
Consultation financially cleared
  ↓
Check Patient In
  ↓
Patient proceeds to Doctor
```

### Major capabilities

- search patients;
- register patients;
- view patient basic information;
- update demographic information;
- create visits;
- view visits;
- manage appointments;
- check patients in;
- view basic payment/procedure status where needed to coordinate patient flow.

### Boundaries

Receptionists do not own clinical documentation, payment recording, procedure documentation, or system administration.

---

## 7.2 Accountant Journey

### Responsibility

Own financial transactions and financial clearance.

### Consultation stage

```text
Visit Created
  ↓
Open Consultation Charge
  ↓
Record Payment
  ↓
Issue Receipt
  ↓
Consultation Cleared
```

### Procedure stage

```text
Doctor Determines Procedure
  ↓
Procedure Charge Available
  ↓
Open Procedure Bill
  ↓
Record Payment
  ↓
Issue Receipt
  ↓
Procedure Cleared
```

### Major capabilities

- search patient;
- view patient identification information;
- view relevant visit information;
- view bill/charges;
- view service/procedure charges;
- record payments;
- issue receipts;
- view transactions;
- request payment reversal;
- view reversal status.

### Boundaries

The Accountant does not decide which procedure is clinically required. The Doctor makes the clinical decision; billing processes the resulting charge.

---

## 7.3 Doctor Journey

### Responsibility

Own consultation, clinical decision-making, and procedure documentation.

### Consultation journey

```text
Login
  ↓
Doctor Dashboard / Worklist
  ↓
Open Patient Visit
  ↓
Review Patient Information / Timeline
  ↓
Record Consultation
  ↓
Record Assessment
  ↓
Determine Whether Procedure Is Required
  ↓
If required: Select / Order Procedure
  ↓
Complete Consultation
```

The selected procedure creates the basis for the second billing/payment stage.

### Procedure journey

```text
Procedure Financially Cleared
  ↓
Patient Prepared
  ↓
Doctor Opens Procedure
  ↓
Perform Procedure
  ↓
Record Procedure Details
  ↓
Record Findings
  ↓
Complete Procedure
  ↓
Patient Proceeds to Recovery
```

### Major capabilities

- search patient;
- view patient information;
- view patient timeline;
- view current visit;
- open consultation;
- record clinical information;
- record assessment;
- determine/select procedure/service;
- complete consultation;
- view ready procedures;
- open procedure;
- record procedure details/findings;
- complete procedure;
- view and attach relevant clinical documents;
- view relevant payment/financial-clearance status.

### Boundaries

Doctors do not record financial transactions or perform general system administration.

---

## 7.4 Nurse Journey

### Responsibility

Own nursing workflow, procedure preparation/support, recovery, and operational discharge.

### Normal journey

```text
Login
  ↓
Nursing Worklist
  ↓
Open Patient Visit
  ↓
View Relevant Clinical / Procedure Information
  ↓
Procedure Financially Cleared
  ↓
Prepare Patient / Record Nursing Information
  ↓
Procedure
  ↓
Receive Patient in Recovery
  ↓
Record Recovery Information
  ↓
Complete Recovery
  ↓
Complete Nursing Discharge Workflow
```

### Major capabilities

- search patient;
- view patient information;
- view current visit;
- view patient timeline;
- record nursing information;
- prepare/support procedure workflow;
- view procedure status;
- record recovery information;
- complete recovery;
- view discharge information;
- record nursing discharge information;
- complete operational discharge workflow;
- view relevant clinical/procedure information;
- view relevant financial-clearance status.

### Boundaries

The Nurse participates in clinical care but does not inherit the Doctor's procedure-selection or primary clinical decision permissions.

---

## 7.5 Administrator Journey

### Responsibility

Maintain users and operational system configuration.

### Major capabilities

- user management;
- role management;
- service/procedure configuration;
- price configuration;
- general system configuration;
- audit-log access;
- operational administration.

### Boundary

Administrator does not automatically own normal clinical, reception, accounting, nursing, or management business actions merely because the role administers the system.

---

## 7.6 Management Journey

### Responsibility

Oversight, reporting, financial/operational summaries, and defined approvals.

### Major capabilities

- management dashboard;
- reports;
- operational summaries;
- financial summaries;
- approval actions;
- management oversight.

### Example approval workflow

```text
Accountant
  ↓
Request Payment Reversal
  ↓
Management
  ↓
Approve / Reject
  ↓
System Records Outcome
  ↓
Audit Record
```

Management does not normally participate in the routine patient journey.

---

# 8. Validated Functional Modules

The MVP is organized around five primary functional areas.

## Module 01 — Patient & Visit Management

### Purpose

Manage patient identity, appointments, visits, and front-desk flow.

### Core capabilities

- patient search;
- patient registration;
- patient profile;
- demographic updates;
- appointment management;
- visit creation;
- visit viewing;
- patient check-in;
- patient visit history.

### Core records

- Patient
- Appointment
- Visit

### Primary actor

Receptionist.

Other authorized clinical/financial users may view relevant patient/visit information required for their workflows.

---

## Module 02 — Consultation & Clinical

### Purpose

Support the doctor's consultation and clinical decision that determines subsequent care.

### Core capabilities

- consultation worklist/access;
- open patient visit;
- review patient information/history;
- consultation documentation;
- assessment documentation;
- procedure/service selection or ordering;
- consultation completion.

### Core records

- Consultation / Encounter clinical record
- Assessment
- Procedure/Service decision/order

### Primary actor

Doctor.

### Critical output

If a procedure is required, the consultation produces the procedure decision that triggers the second billing stage.

---

## Module 03 — Billing & Payment

### Purpose

Manage charges, payments, receipts, transaction history, financial clearance, and controlled reversal requests.

### Core capabilities

- consultation billing;
- consultation payment;
- consultation financial clearance;
- procedure billing;
- procedure payment;
- procedure financial clearance;
- receipts;
- transaction history;
- payment reversal request;
- reversal status;
- management approval/rejection of defined reversal requests.

### Core records

- Bill / Charge
- Payment / Transaction
- Receipt
- Payment Reversal Request

### Primary actor

Accountant.

### Secondary actor

Management for required approvals.

### Critical implementation requirement

Billing must represent **both financial gates** separately. Do not implement one generic single-payment assumption that loses the distinction between consultation payment and procedure payment.

---

## Module 04 — Procedure, Nursing, Recovery & Discharge

This functional area contains sequential clinical operations after the procedure has been selected and financially cleared.

### Procedure capabilities

- procedure worklist/status;
- open procedure;
- procedure documentation;
- findings;
- procedure completion.

Primary actor: Doctor.

### Nursing/preparation capabilities

- view patient/procedure information;
- record relevant nursing information;
- procedure preparation/support.

Primary actor: Nurse.

### Recovery capabilities

- receive patient into recovery;
- record recovery information;
- complete recovery.

Primary actor: Nurse.

### Discharge capabilities

- view discharge information;
- record required nursing discharge information;
- complete operational discharge.

Primary actor: Nurse.

### Core records

- Procedure
- Procedure Findings / Details
- Nursing Record
- Recovery Record
- Discharge Record

---

## Module 05 — Administration & Reporting

### Administration

Core capabilities:

- users;
- roles;
- services/procedures;
- pricing;
- system configuration;
- audit-log viewing.

Primary actor: Administrator.

### Reporting / Management

Core capabilities:

- dashboards;
- operational reports;
- financial reports/summaries;
- defined approval actions;
- management oversight.

Primary actor: Management.

---

# 9. Cross-Cutting Services

Cross-cutting services support several modules and should not be treated as independent patient workflows.

## 9.1 Authentication

Answers: **Who is using AfyaScope?**

MVP capabilities:

- login;
- logout;
- password reset;
- email verification;
- password change;
- authenticated session management.

Normal flow:

```text
User
 ↓
Login
 ↓
Credentials Validated
 ↓
Authenticated Session
 ↓
Role-appropriate application access
```

---

## 9.2 Authorization / RBAC

Answers: **What may the authenticated user do?**

```text
User
 ↓
Role
 ↓
Permissions
 ↓
Allowed Routes / Actions / UI
```

Authorization must be enforced server-side in Laravel. Frontend visibility is supplementary.

---

## 9.3 Audit

Important system actions are recorded automatically.

Examples include:

- patient created;
- payment recorded;
- consultation completed;
- procedure completed;
- payment reversal requested;
- payment reversal approved/rejected;
- user account changed;
- configured service/price changed.

A simple audit record should identify, as appropriate:

- actor;
- action;
- affected record/entity;
- date/time.

The Audit Log UI is an administrative view over audit data, not a separate workflow engine.

---

## 9.4 Notifications

Notifications are lightweight and event-driven.

Example:

```text
Accountant Requests Reversal
  ↓
System Creates Notification
  ↓
Management Sees Pending Approval
```

MVP notification information can include:

- message;
- related record/action;
- read/unread status;
- date/time.

Do not turn notifications into an internal chat platform.

---

## 9.5 Patient Timeline

The Patient Timeline provides a chronological view of existing patient-related activity.

Example:

```text
08:15  Visit created
08:20  Consultation payment recorded
08:30  Patient checked in
08:45  Consultation completed
09:00  Procedure selected
09:15  Procedure payment recorded
10:00  Procedure completed
10:35  Recovery completed
10:50  Patient discharged
```

The timeline **aggregates/references existing records**. It must not create duplicate clinical or financial source records merely to populate the timeline.

---

## 9.6 Document Management

Provide a shared, simple document/attachment capability.

A document may include:

- file;
- document type;
- related patient;
- related visit or domain record;
- uploader;
- upload date/time.

Do not add advanced document versioning, elaborate electronic-signature infrastructure, or a full document workflow engine during MVP unless separately approved.

---

## 9.7 Search and Filtering

Search is a shared application capability rather than a heavyweight standalone domain service.

Relevant search/filtering includes:

- patient search;
- visit search;
- user search;
- transaction/reference search;
- list/worklist filters.

Patient search is especially important because many workflows begin by locating the patient.

---

# 10. RBAC — Validated Business Boundaries

The following is a business-level permission model. Exact permission identifiers can be finalized during implementation.

| Capability | Receptionist | Accountant | Doctor | Nurse | Administrator | Management |
|---|---|---|---|---|---|---|
| Search/view relevant patients | Yes | Yes | Yes | Yes | As needed | As needed |
| Register patient | Yes | No | No | No | No | No |
| Update demographics | Yes | No | No | No | No | No |
| Create/manage visits | Yes | View relevant | View | View | No | View/report |
| Manage appointments | Yes | No | No | No | No | View/report |
| Check in patient | Yes | No | No | No | No | No |
| Record consultation | No | No | Yes | No | No | No |
| Determine/order procedure | No | No | Yes | No | No | No |
| View billing status | Limited | Yes | Relevant | Relevant | As needed | Yes |
| Record payment | No | Yes | No | No | No | No |
| Issue receipt | No | Yes | No | No | No | No |
| Request payment reversal | No | Yes | No | No | No | No |
| Approve/reject reversal | No | No | No | No | No | Yes |
| Record procedure | No | No | Yes | No | No | No |
| Record nursing/preparation | No | No | No | Yes | No | No |
| Record recovery | No | No | No | Yes | No | No |
| Complete nursing discharge | No | No | No | Yes | No | No |
| Manage users/roles | No | No | No | No | Yes | No |
| Configure services/prices | No | No | No | No | Yes | No |
| View audit log | No | No | No | No | Yes | As approved |
| View management reports | No | Relevant only | Relevant only | Relevant only | As needed | Yes |

The table defines the intended boundaries, not necessarily the final database permission schema.

Potential implementation permission names may resemble:

```text
patients.view
patients.create
patients.update

visits.view
visits.create
visits.check-in

appointments.view
appointments.manage

consultations.view
consultations.manage

procedures.view
procedures.manage

billing.view
payments.record
payment-reversals.request
payment-reversals.approve

nursing.manage
recovery.manage
discharge.manage

users.manage
roles.manage
system-settings.manage
services.manage
pricing.manage
audit.view

reports.view
```

Do not create permissions for every individual visual button unless a real authorization boundary requires it.

---

# 11. Actor Handoff Model

The canonical procedure-patient handoff is:

```text
Receptionist
    ↓
Accountant
[Consultation payment]
    ↓
Receptionist
[Check-in]
    ↓
Doctor
[Consultation / Procedure decision]
    ↓
Accountant
[Procedure payment]
    ↓
Nurse
[Preparation]
    ↓
Doctor
[Procedure]
    ↓
Nurse
[Recovery / Discharge]
```

This is preferable to treating all actors as interchangeable operators.

---

# 12. Suggested High-Level Domain Relationships

This is conceptual guidance rather than a frozen physical database schema.

```text
Patient
  │
  ├── has many Appointments
  │
  └── has many Visits
           │
           ├── Consultation
           │      └── Procedure decision/order
           │
           ├── Charges / Bills
           │      └── Payments / Transactions
           │
           ├── Procedure
           │      └── Procedure details/findings
           │
           ├── Nursing records
           │
           ├── Recovery record
           │
           ├── Discharge record
           │
           └── Documents
```

Other shared entities may include:

```text
User
Role
Permission
Notification
Audit Event
Service / Procedure Catalog Item
Price / Charge Configuration
Payment Reversal Request
```

Do not treat this conceptual diagram as authorization to generate all tables immediately without validating the implementation slice being built.

---

# 13. Dashboard / Worklist Principle

Role dashboards should prioritize the work relevant to that actor.

Examples:

### Receptionist

- patient search;
- today's appointments/visits;
- registration/check-in actions.

### Accountant

- pending consultation payments;
- pending procedure payments;
- recent transactions;
- reversal requests/status.

### Doctor

- patients ready for consultation;
- consultations;
- procedures ready for the doctor.

### Nurse

- patients requiring preparation;
- recovery patients;
- discharge work.

### Administrator

- users;
- configuration;
- audit access.

### Management

- operational/financial summaries;
- reports;
- pending defined approvals.

Dashboards should support workflows rather than becoming independent data domains.

---

# 14. Workflow State Guidance

Use only states required to make the validated normal workflow understandable and enforceable.

At minimum, the system needs to be capable of distinguishing meaningful workflow milestones such as:

```text
Visit Created
Consultation Payment Cleared
Checked In
Consultation Completed
Procedure Selected/Ordered
Procedure Payment Cleared
Procedure Prepared/Ready
Procedure Completed
Recovery Completed
Discharged / Visit Completed
```

The exact implementation may use explicit status columns, timestamps, derived states, or a carefully chosen combination. Avoid creating an elaborate state machine unless implementation demonstrates that it is necessary.

---

# 15. Important Business Invariants

Coding agents and developers should preserve these rules.

## Invariant 1 — Consultation is the first financial gate

A normal visit requiring consultation must pass consultation billing/payment before proceeding into the consultation workflow.

## Invariant 2 — Procedure choice comes from consultation

The procedure is clinically determined by the Doctor during/after consultation, not by the Accountant.

## Invariant 3 — Procedure payment is the second financial gate

A normal procedure workflow proceeds to preparation/procedure after its billing/payment stage has been cleared.

## Invariant 4 — Patient history spans visits

The Patient is persistent across attendances. A new normal attendance creates/uses a Visit rather than creating a duplicate Patient.

## Invariant 5 — Timeline does not duplicate source records

The Patient Timeline presents existing domain activity chronologically.

## Invariant 6 — UI restrictions are not security

Laravel authorization must protect server actions even when React already hides inaccessible controls.

## Invariant 7 — Audit is automatic

Important auditable actions should produce audit records without requiring the operator to manually create them.

## Invariant 8 — Administrator is not a universal business actor

System administration privileges do not inherently mean the Administrator performs clinical, reception, accounting, or nursing workflows.

---

# 16. Explicit MVP Non-Goals / Complexity Boundary

Phase 0 intentionally does **not** require speculative handling of questions such as:

- complex triage prerequisites and exceptions;
- advanced allergy/prescription conflict engines;
- complex completed-encounter amendment policies;
- doctor takeover/handover workflows;
- elaborate unsigned-note workflows;
- asynchronous diagnostic-result orchestration;
- emergency-specific workflow branches;
- multi-facility practitioner assignment;
- complex cross-facility patient visibility rules;
- sophisticated internal messaging/chat;
- enterprise document versioning/workflows;
- unnecessary microservices;
- exhaustive button-level permissions;
- elaborate workflow/state-machine infrastructure.

These are not declared impossible future features. They are simply **not part of the validated MVP unless subsequently approved**.

When an implementation decision is ambiguous, prefer the simplest solution that satisfies the validated normal workflow.

---

# 17. Implementation Guidance for Coding Agents

## 17.1 Treat this document as the requirements baseline

Before implementing a feature:

1. identify its owning module;
2. identify the primary actor;
3. identify the relevant Visit/Patient relationship;
4. identify authorization requirements;
5. identify whether it affects either financial gate;
6. identify relevant audit events;
7. identify whether it should appear on the Patient Timeline;
8. identify whether a notification is required by an already-defined workflow;
9. implement only the normal validated workflow unless instructed otherwise.

## 17.2 Do not silently invent business rules

If implementation requires a business decision not contained in this specification, surface the ambiguity rather than inventing a complex rule.

## 17.3 Preserve module boundaries

Examples:

- Consultation determines the procedure.
- Billing records the financial transaction.
- Procedure records the procedure itself.
- Patient Timeline displays resulting history.
- Audit records the action.

Do not merge these responsibilities merely because doing so is convenient in one controller/component.

## 17.4 Build vertically where practical

A useful development slice should include the necessary backend, authorization, frontend, validation, and tests for a coherent workflow capability rather than creating every database table first and wiring behavior much later.

## 17.5 Maintain tests

Each completed slice should preserve:

- passing Laravel tests;
- successful frontend production build;
- valid route registration;
- no obsolete references to removed starter-kit functionality.

---

# 18. Current Technical Foundation / Development Checkpoint

The project uses Laravel with React/Inertia and has already undergone starter-kit cleanup.

The previously inherited Team-oriented functionality was removed because AfyaScope's business actors and RBAC model should not be implemented as starter-kit Teams.

At the established checkpoint:

- Team-related application dependencies had been removed;
- dashboard routing was simplified to `/dashboard`;
- profile routes were corrected;
- obsolete team UI references were removed;
- team invitation remnants were removed;
- team-dependent authentication redirects were removed;
- account deletion was intentionally removed from the intended settings experience;
- production frontend build passed;
- Laravel tests passed;
- `php artisan route:list` worked.

Development should preserve this cleaned foundation and introduce AfyaScope domain concepts deliberately rather than reusing removed Team semantics.

---

# 19. Recommended Development Direction After Phase 0

Phase 0 is complete. Development should now proceed systematically from foundation into the patient journey.

A sensible implementation sequence is:

```text
Foundation / RBAC
      ↓
Patient & Visit Management
      ↓
Consultation Billing Gate
      ↓
Check-in / Consultation
      ↓
Procedure Decision
      ↓
Procedure Billing Gate
      ↓
Procedure / Nursing
      ↓
Recovery / Discharge
      ↓
Administration / Reporting
      ↓
Cross-cutting refinement
```

Cross-cutting concerns such as authorization and audit should be introduced as the relevant vertical slices are built rather than postponed until the entire application is complete.

---

# 20. Phase 0 Validation Record

The rapid validation process covered ten rounds and is now complete.

The validated areas include:

- overall scope and workflow simplicity;
- Patient & Visit workflow;
- consultation workflow;
- two-stage billing/payment architecture;
- procedure workflow;
- nursing/recovery/discharge workflow;
- administration/reporting boundaries;
- cross-cutting services;
- RBAC/actor permissions;
- complete end-to-end system journey.

All final validation decisions were accepted as **Keep**.

The final billing clarification is binding across the specification:

```text
FINANCIAL GATE 1
Visit Created
  ↓
Consultation Billing + Payment
  ↓
Consultation Cleared
  ↓
Check-in / Consultation

FINANCIAL GATE 2
Consultation
  ↓
Procedure Determined
  ↓
Procedure Billing + Payment
  ↓
Procedure Cleared
  ↓
Preparation / Procedure
```

---

# 21. Definition of Phase 0 Complete

Phase 0 is considered complete because the project now has:

- a defined MVP boundary;
- validated actors;
- validated actor journeys;
- validated module responsibilities;
- a canonical end-to-end patient workflow;
- an explicit two-stage billing/payment model;
- a clear Patient-versus-Visit distinction;
- validated cross-cutting services;
- validated high-level RBAC boundaries;
- explicit complexity/non-goal boundaries;
- implementation guidance suitable for developers and coding agents.

This document should remain the **canonical Phase 0 reference** until a requirement is deliberately changed and the specification is versioned accordingly.

---

## Canonical One-Screen Summary

```text
                         AFYASCOPE HMS

PATIENT
  │
  ▼
RECEPTIONIST
Search/Register → Create Visit
  │
  ▼
ACCOUNTANT
Consultation Bill → Payment → CONSULTATION CLEARED
  │
  ▼
RECEPTIONIST
Check-in
  │
  ▼
DOCTOR
Consultation → Assessment → Procedure Decision
  │
  ├── No Procedure ──────────────────────────────┐
  │                                               │
  └── Procedure Required                         │
          │                                       │
          ▼                                       │
      ACCOUNTANT                                  │
      Procedure Bill → Payment                    │
          │                                       │
          ▼                                       │
      PROCEDURE CLEARED                           │
          │                                       │
          ▼                                       │
        NURSE                                     │
      Preparation                                 │
          │                                       │
          ▼                                       │
        DOCTOR                                    │
      Procedure                                   │
          │                                       │
          ▼                                       │
        NURSE                                     │
      Recovery → Discharge                        │
          │                                       │
          └──────────────────┬────────────────────┘
                             ▼
                       VISIT COMPLETED

SHARED ACROSS THE SYSTEM
Authentication • RBAC • Audit • Notifications
Patient Timeline • Documents • Search/Filtering

ADMINISTRATOR
Users • Roles • Services/Procedures • Pricing • Configuration • Audit

MANAGEMENT
Dashboards • Reports • Summaries • Defined Approvals
```

---

**End of AfyaScope Phase 0 Implementation Blueprint**
