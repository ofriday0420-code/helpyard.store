# Next-step implementation plan

The active milestone in the Helpyard.store New Implementation Plan (version
2.0, 05 October 2026) is Gate 0. Do not skip its requirements/design review and
database confidence checks to add more feature surface. The Gate 0 decision
register and sitemap/wireframe draft are in `docs/gate-0/`; owner approval is
still pending.

## Gate 0 acceptance and current evidence

- Proposed product, fulfillment, email, support, hosting, and role decisions
  are recorded in `docs/gate-0/decision-register.md`. They are draft proposals,
  not approved business or legal policy.
- The sitemap and clickable low-fidelity desktop/mobile wireframe draft is
  `docs/gate-0/sitemap-and-wireframes.html`; visual and product-owner approval
  remain outstanding.
- `.github/workflows/ci.yml` provisions MySQL 8.4 and PHP 8.3 with
  `pdo_mysql`/`fileinfo`/`pcntl`, lints application PHP, runs the existing tests,
  applies migrations to a clean database, seeds it, reruns migrations, and
  invokes `tests/mysql_integration.php`.
- The MySQL integration test checks migration idempotency, the expected
  migration set and seeded catalog reads; hidden-category filtering; actual
  cart checkout snapshots and clearing; simultaneous checkouts contending for
  one unit; exact-once expired-reservation restoration; risky, late, repeated,
  failed, wrong-amount, and wrong-currency payment validation; private-file
  entitlement, customer isolation, and revocation; paid course enrollment,
  access isolation, and lesson-progress idempotency; and the physical shipment
  lifecycle.
- `tests/mysql_integration.php` creates fixture rows and is intentionally
  restricted to database names ending in `_test`; run it only against an
  isolated test database.
- Previously, local `php tests/run.php`, PHP lint, `git diff --check`, and the
  original integration suite passed against a disposable WSL MariaDB 11.8.8
  instance after all 14 migrations and seeders were applied. The expanded
  checkout/payment integration cases are now under validation. The Windows
  PHP CLI has no PDO drivers and Docker is unavailable; the MySQL 8.4 GitHub
  Actions workflow still needs a hosted run after these changes are pushed.
  SSLCOMMERZ sandbox callback behavior, upload content inspection, and
  restore/load behavior remain unverified.
- Gate 0 remains open until product-owner decisions and the draft flows are
  approved, and hosted CI demonstrates clean migrations and integration tests.

## Next work after Gate 0

1. Run the expanded database-backed checkout/stock and payment callback cases
   locally and in hosted MySQL 8.4 CI; then fix any defects they expose.
2. Run the SSLCOMMERZ sandbox success, cancel, fail, repeat, late, and risk
   matrix. Repository-level verified-validation tests do not replace a real
   provider sandbox run.
3. Continue in the v2 order: complete admin order/payment-review operations,
   design-system and commerce scope, fulfillment, SEO/accessibility, hardening,
   then staging and production acceptance.
4. Defer PWA/native work until API v1 is stable and documented.

Do not claim a release gate complete based only on a percentage estimate. Each
gate requires its stated evidence and an accountable owner’s approval where
applicable.

## Admin catalog-management acceptance

- Require the existing administrator role and CSRF validation on all catalog
  writes; never expose public administrator registration.
- Manage category visibility and product create/update, price, stock, and
  storefront visibility with server-side validation and transaction-scoped
  audit events.
- Never hard-delete products referenced by historical orders; keep product
  types immutable after creation so order snapshots remain valid.
- Edit option-level inventory separately and prevent stock adjustments from
  overflowing when active checkout reservations are later restored.
- Ensure products in hidden categories disappear from the public catalog and
  cannot be added to or checked out from customer carts.

## Remaining fulfillment sequence

1. **Administrator foundation and private files (first slices implemented)**
   - Provision admins through a trusted CLI operation and record audit events.
   - Upload/revoke protected product files, updating paid-order entitlements.
   - Manage categories, products, pricing, and product/option inventory.
   - Remaining: full course authoring, image management, and order/payment-review CRUD.

2. **Physical-order fulfillment and shipment tracking (implemented)**
   - Snapshot each order line's product type at checkout.
   - Add a staff-only fulfillment queue and guarded order transitions:
     `paid → processing → shipped → delivered`.
   - Record carrier/tracking details and show them only to the owning customer
     on the order detail page.
   - Initially permit shipment fulfillment only when every order line is a
     physical product or book. Other product types need their own delivery path.
   - Acceptance: only an authenticated admin can advance eligible paid orders;
     invalid transitions, non-physical orders, missing tracking data, and
     customer access to the admin queue are rejected; customers see shipment
     data only for their own order.

3. **Protected digital delivery (first slice implemented)**
   - Define private file metadata/storage and paid-order entitlements.
   - Add an authorization-checked download endpoint with expiring/revocable
     access; never expose private files through `public/`.
   - Acceptance: unpaid, expired, and cross-customer downloads are denied.
   - Implemented: private file metadata, paid-order entitlement creation,
     customer-owned download list, private-root path validation, protected
     admin upload/revocation, and attachment streaming. Download expiry/usage
     limits remain follow-up work.

4. **Course fulfillment (first flow implemented)**
   - Add enrollment and lesson-access records tied to verified paid orders.
   - Add the first course-learning flow and customer course view.
   - Acceptance: only entitled customers can access enrolled course content.

5. **Software licensing and ready-website delivery**
   - Define the product-specific delivery/licensing requirements before
     implementing assignment or handoff workflows.
   - Acceptance: an eligible paid order receives one auditable entitlement or
     assignment, without exposing reusable secrets publicly.

6. **Fulfillment notifications**
   - Select/configure an email provider, then notify customers of relevant
     fulfillment transitions.
   - Acceptance: delivery failures are observable and do not roll back a
     committed order transition.

## Dependencies and release gates

- Admin accounts must be provisioned through a trusted operator-controlled
  process; there is no public admin registration.
- A MySQL/MariaDB integration environment is required to validate migrations,
  transaction locking, ownership isolation, and shipment updates. The current
  local PHP runtime has PDO but has no available database drivers (including
  `pdo_mysql`), so this remains an explicit external verification gate.
- Payment sandbox validation remains a separate prerequisite before any
  production launch. Fulfillment must only begin from a verified paid order.
- Do not begin PWA/native mobile work until the web platform and REST API are
  stable, as required by the source plan.

## Current build slice

The current delivery implements the first course schema and customer learning
flow, CLI-only administrator provisioning, protected product-file upload
and revocation, and audited product/category and inventory management. Admin
order/payment-review operations, course authoring, email verification/reset,
licensing, and production acceptance remain incomplete. A MySQL/MariaDB
integration environment with PHP `pdo_mysql` is still required to validate
migrations, audit transactions, file entitlements, inventory reservations, and customer ownership
isolation.
