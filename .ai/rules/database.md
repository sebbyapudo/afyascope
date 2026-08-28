---
paths:
  - 'database/**'
---

# Database

## Scope standalone test database commands explicitly
PHPUnit uses afyascope_test via phpunit.xml, but standalone `php artisan --env=testing` does not load that value when `.env.testing` is absent. For direct test-only Artisan database commands, set `DB_DATABASE=afyascope_test` in the command process and verify the selected database first. Never run destructive migration commands against afyascope.
