# AfyaScope HMS — Brand & Product UI Guidelines

## Purpose
This document defines the practical visual system for the AfyaScope HMS web application. It is intended for developers and coding agents working in the Laravel + React + Inertia + Tailwind codebase.

The application should feel clinical, calm, modern, precise, trustworthy, and efficient.

## Brand source
The current AfyaScope logo establishes the core direction: deep navy blue, medium blue/cyan, teal/aqua, and white/light neutral backgrounds.

The supplied UI inspiration establishes the preferred application feel: light surfaces, generous whitespace, rounded panels/cards, soft borders, restrained shadows, calm blue/teal accents, clean typography, and dashboard-first information hierarchy.

**Important:** the inspiration image is visual direction only. Do not copy its sitemap, features, terminology, or modules.

## Core design principles
1. Clinical clarity over decoration.
2. Calm, trustworthy, professional tone.
3. High readability for operational workflows.
4. Consistency across Reception, Billing, Clinical, Nursing, Admin, and Management.
5. Dense data is acceptable when hierarchy is clear.
6. Avoid visual clutter, heavy gradients, oversized illustrations, and excessive animation.
7. Use color primarily for hierarchy, actions, and status.
8. Reuse shared forms, tables, queues, cards, badges, and dialogs.

## Color system
Use these as first implementation tokens. They may be fine-tuned later, but should remain within the same brand family.

### Brand
- Primary Navy: `#0F4C75`
- Deep Navy: `#123B5D`
- Teal: `#2D9C9F`
- Aqua: `#62C3BE`
- Soft Aqua: `#DFF4F3`

### Neutrals
- Page Background: `#F6F9FB`
- Surface / Card: `#FFFFFF`
- Secondary Surface: `#EEF4F7`
- Border: `#D9E3E8`
- Primary Text: `#17252E`
- Secondary Text: `#65757F`
- Muted Text: `#8A9AA4`

### Semantic colors
Use restrained semantic colors for Success, Warning, Error, and Info. Do not use semantic colors decoratively.

## Typography
Use `Instrument Sans` as the default application UI font unless a later approved brand typeface replaces it.

Suggested hierarchy:
- Page title: 28–32px, semibold
- Section title: 20–24px, semibold
- Card title: 16–18px, medium/semibold
- Body: 14–16px, regular
- Table / metadata: 13–14px
- Helper text: 12–13px

Use sentence case for interface labels.

## Layout
- Persistent left sidebar on desktop.
- Top bar for page title, contextual actions, notifications, search, and user profile.
- Main content area with a light neutral background.
- Responsive behavior for smaller screens.
- Operational pages may use wide layouts; do not force data-heavy pages into narrow marketing widths.
- Prefer a spacing rhythm of 4 / 8 / 12 / 16 / 24 / 32px.

## Surfaces and cards
- White cards on a light gray-blue background.
- Recommended radius: 12–16px for major cards.
- Use subtle borders and minimal shadows.
- Avoid stacked/heavy shadows.

## Buttons
- Primary: one dominant brand treatment using navy or teal.
- Secondary: white/light neutral with border.
- Destructive: semantic red only.
- Prefer verb labels such as `Save`, `Create Visit`, `Record Payment`, `Complete Procedure`.

## Forms
- Labels above fields.
- Clear required-field indication.
- Inline validation beside relevant fields.
- Group related fields into clear sections.
- Use consistent input heights and spacing.

## Tables and queues
Operational queues are central to AfyaScope.

Prefer columns such as:
`Patient | Visit | Time | Status | Primary Action`

Avoid excessive columns. Use consistent status badges and filtering where relevant.

## Status badges
Use the same labels and treatment everywhere.

Examples:
- Waiting
- Financially Cleared
- Checked In
- In Consultation
- Awaiting Procedure Payment
- Procedure Cleared
- Ready for Procedure
- In Recovery
- Discharged

Do not invent workflow statuses outside the Phase 0 blueprint.

## Dashboards
Prioritize role-specific queues, today's workload, key counts, and pending actions. Do not turn dashboards into decorative analytics pages.

## Navigation
Navigation must be role-aware. Backend authorization remains authoritative even when UI elements are hidden.

## Icons
Use a single icon family, preferably `lucide-react`.

## Light / dark mode
Prioritize a polished light theme for MVP. Dark mode may remain if inexpensive, but must not slow implementation.

## Accessibility
Maintain sufficient contrast, visible focus states, accessible labels, keyboard-usable dialogs/tables, and do not communicate status using color alone.

## Agent implementation rules
Coding agents must:
1. Reuse shared components before creating new ones.
2. Use approved tokens rather than arbitrary colors.
3. Preserve one visual language across all modules.
4. Treat the inspiration image as style direction only.
5. Never infer business features from the inspiration image.
6. Read the Phase 0 implementation blueprint before implementing screens.
7. Avoid inventing roles or workflow states.
8. Prefer production-oriented UI over showcase-style mockups.
