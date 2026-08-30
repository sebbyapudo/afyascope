---
paths:
  - 'resources/**'
---

# Resources

## Use AfyaScope frontend tokens and shared primitives
Define approved visual values in resources/css/app.css via Tailwind 4 @theme and consume semantic token utilities instead of scattering brand hex values. Reuse the focused primitives in resources/js/components/ui for authenticated page structure, surfaces, actions, status, empty states, and form presentation. Do not treat PageContainer as the authenticated application shell; permanent navigation remains separate.

## Drive authenticated navigation from shared capabilities
Authenticated pages use the persistent AuthenticatedLayout. Sidebar visibility reads auth.capabilities booleans from sanitized Inertia props, never staff role slugs or display names. Use Wayfinder destinations and Inertia page.url for active sections; backend policies remain authoritative.
