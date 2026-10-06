# Helpyard.store

This repository contains a working storefront, customer account flows, a session-backed cart, and the first authenticated checkout/order flow for the Helpyard.store commerce platform.

## Current implementation status

The engineering foundation and initial commerce flows are implemented. Checkout, hosted SSLCOMMERZ callbacks, a reservation cleanup worker, customer order history, physical-order fulfillment, expiring download links with a five-transfer cap, authenticated customer APIs, the first course-learning flow, audited text-lesson authoring, administrator-managed private files, audited product/category management, and audited administrator order review notes are in place. Merchant credentials, a fresh hosted CI run for current changes, payment-review resolution, hosted video delivery, software licensing, and production approval are still required.

The ordered build plan is in [`docs/next-build-implementation-plan.md`](docs/next-build-implementation-plan.md). The public catalog and initial authenticated customer API are described by [`docs/openapi.yaml`](docs/openapi.yaml), also served at `/api/v1/openapi.yaml`.

### Included
- MVC-style app structure aligned to the roadmap
- Route definitions for homepage, catalog, customer account, cart, order, course, and API endpoints
- Configuration for app and database values through environment variables
- SQL migration and seed files for core commerce tables
- Responsive storefront, database-backed public catalog API, authenticated customer API, server-rendered product pages, and catalog discovery metadata
- Customer authentication, profile, and saved delivery addresses
- Cart, authenticated checkout, price/stock revalidation, and order history details
- Guest cart merging into the shared customer account cart used by the web and API
- Staff-only physical/book order processing, shipment tracking, and customer-visible delivery status
- Verified-payment course enrollment, customer-only course access, and lesson progress
- CLI-only admin provisioning and audited private-file upload/revocation
- Audited product/category management and simple/variant inventory controls
- Administrator product image uploads, alt text updates, and image removal
- Audited internal review notes on administrator order details
- Administrator course workspaces, ordered sections, draft/published text lessons, and audit events
- Scheduled reservation cleanup command and customer recent-order history

### Next roadmap gate
Gate 0 is the current priority: approve the proposed product and operating decisions and review the responsive sitemap/wireframes before expanding the feature set. Clean MySQL migrations and integration tests passed in hosted CI on base commit `49e7e3f`; rerun CI after pushing the current commits. The decision register and clickable draft are in `docs/gate-0/`. The draft is not owner approval.

GitHub Actions (`.github/workflows/ci.yml`) provisions MySQL 8.4 and PHP 8.3 with `pdo_mysql` and `fileinfo`, applies migrations to a fresh database, seeds the catalog/course data, checks that migrations are repeatable, and runs `tests/mysql_integration.php` plus the local policy suite. The database integration suite exercises competing checkouts against one available unit, server-side order snapshots, cart clearing, expired-reservation restoration, risky/late/repeated payment validation, and rejected failed/amount/currency callback data. The expanded suite passed locally against a disposable WSL MariaDB 11.8 instance and in [hosted MySQL 8.4 CI on commit `49e7e3f`](https://github.com/ofriday0420-code/helpyard.store/actions/runs/37333898590); rerun the hosted workflow after pushing the current commits. Passing the local policy suite alone does not prove subsequent changes.

The database integration test creates fixture users, orders, payments, entitlements, lessons, and shipments. Run it only after migrations and seeders against an isolated database whose name ends in `_test`; it refuses other database names.

After Gate 0 is signed off, continue in the v2 roadmap order: verify/stabilize payment, checkout, stock, and download flows; close admin order/payment-review work; then progress through design-system, commerce, fulfillment, growth/quality, hardening, and deployment gates. PWA and native work remain deferred until API v1 is stable and documented.

## Quick start

1. Install and start MySQL 8 or MariaDB, and enable PHP's `pdo_mysql` extension.
2. Create the development database:
   ```sql
   CREATE DATABASE helpyard_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Copy `.env.example` to `.env` and set the local database host, port, name, user, and password.
4. From the project root, run `php database/migrate.php`, then `php database/seed.php`.
5. Run the foundation checks with `php tests/run.php`.
6. Start the server with `php -S 127.0.0.1:8000 -t public public/router.php`.
7. Visit `http://127.0.0.1:8000/` for the storefront, `/courses`, `/websites`, `/software`, `/books`, or `/devices` for the five sections, `/product/business-website-starter` for a product page, `/cart` to manage the current browser session's cart, and `/checkout` after signing in and saving a delivery address. Orders are available at `/orders/{id}`. The API contract is served at `/api/v1/openapi.yaml`; it includes public catalog routes and authenticated customer profile, token, cart, order, and course routes.
8. Customer accounts are available at `/register`, `/login`, `/account`, `/account/addresses`, `/account/downloads`, and `/account/courses`.
9. After applying migrations and creating a customer account, provision an administrator from a trusted terminal with `php database/provision_admin.php admin@example.com`. The account must already exist as a customer; the action is CLI-only and recorded in the admin audit log. Sign in to reach `/admin/orders`; inspect orders and payments there, and use `/admin/fulfillment`, `/admin/catalog`, `/admin/files`, and `/admin/courses` for their respective tasks.

On Windows, run `php --ini` to find the active `php.ini`. If `pdo_mysql` or `fileinfo` is not listed by `php -m`, enable the corresponding `extension=pdo_mysql` or `extension=fileinfo` in that configuration, ensure `extension_dir` points to PHP's `ext` directory, restart the terminal, and verify both extensions are listed. `fileinfo` is required to validate private uploads against their actual content.

## Notes

- Base security rules are enforced in the roadmap and should be applied in later phases.
- Browser values must never be trusted for pricing, quantity, discount, or payment confirmation.
- Private digital assets must remain behind backend authorization checks.
- The homepage and five catalog pages share a responsive storefront layout and load product lists from the database-backed API. Product detail pages render product data on the server and are enhanced with the same API data when JavaScript is available. Canonical metadata, Open Graph tags, Product structured data, `/sitemap.xml`, and `/robots.txt` support catalog discovery. Run migrations and seeders explicitly with the commands above; they do not run automatically on web requests.
- Catalog API filters: `GET /api/v1/products?category=books&type=book&q=operations&page=1&per_page=20`. Search covers product name, descriptions, and category; query values and filters are validated, SQL wildcard characters are escaped, and page size is capped at 50. Product detail is at `GET /api/v1/products/{slug}` and returns active product details, images, and active variants. If MySQL is unavailable, the API returns HTTP 503 rather than sample or success-shaped fallback data.
- Customer API: exchange customer email/password at `POST /api/v1/auth/token` for a random bearer token that is shown once, stored as a hash, and expires after 30 days. Use `/api/v1/auth/tokens` to inspect or revoke active tokens. Authenticated routes expose the customer's own profile, latest orders, enrolled courses and lessons, lesson progress, and account cart. Signing in merges the browser guest cart into that same account cart. Checkout and payment remain web-only pending shipping and payment policy decisions. See `docs/openapi.yaml` for request/response contracts.
- `.env` supports one `NAME=value` entry per line, with optional matching single or double quotes around values. Do not commit real secrets.
- Migrations are tracked in `schema_migrations`; applied migration files must not be edited. Add a new numbered SQL migration for schema changes. Migration 012 adds course, section, lesson, paid enrollment, and lesson progress tables; migration 013 adds the administrator audit log. Course access is shown only to customers with a verified, non-revoked enrollment, and lesson content is served from the application rather than public media URLs.
- The first authentication slice provides customer registration, login, logout, CSRF-protected forms, session rotation, login throttling, and a protected account page. Email verification and password-reset delivery are not yet enabled because no email provider is configured.
- The cart is database-backed and associated with a random key held in the server-side session. Product and variant prices and stock are read from the database; add/update/remove forms require CSRF tokens, and requested quantities are capped at 99 and checked against availability. Guest-cart/account-cart merging is not yet implemented.
- Checkout requires a signed-in customer and an address belonging to that customer. It snapshots the delivery address and product details, recalculates totals from current database prices, reserves stock transactionally, and creates an order in `payment_pending` for 30 minutes. Expired reservations are released when a checkout, order-status, verified payment request, or the cleanup worker runs.
- Schedule `php database/release_expired_reservations.php` to run every few minutes (for example, Windows Task Scheduler or cron) so expired reservations are released even when no customers visit checkout. The worker processes bounded batches, restores inventory, cancels pending payment attempts, and exits non-zero when cleanup fails. The customer account lists the latest 50 orders.
- Physical fulfillment is documented in `docs/next-step-implementation-plan.md`. Admin fulfillment is available at `/admin/fulfillment` to authenticated accounts with the `admin` role; admin roles must be assigned through the CLI provisioning script or another trusted operator-controlled process (there is no public admin registration). Only paid orders whose snapshotted lines are all `physical` or `book` can move through processing, shipped, and delivered. Carrier and tracking information is shown on that order's customer-owned detail page.
- Order administration at `/admin/orders` prioritizes payment-review orders and exposes customer/order snapshots, payment-attempt and validation references, shipment records, and audited internal review notes. It intentionally has no payment settlement, refund, or order-status mutation controls until the reconciliation policy is approved.
- Catalog management at `/admin/catalog` supports audited product/category creation and updates, storefront visibility, product prices, and inventory; products are deactivated rather than hard-deleted, and product types cannot change after creation. Category slugs remain unique (including hidden categories), and category deactivation is blocked while it contains active products. Product option inventory is updated separately from parent-product inventory. Product images can be uploaded, described with alt text, and removed in the per-product image manager; uploads are limited to 12 JPEG, PNG, or WebP images of at most 5 MB each.
- Private digital files are stored outside `public/` in `storage/private` by default; set `PRIVATE_STORAGE_PATH` to another private directory when deploying. Admins can manage file uploads at `/admin/files`; the handler validates PDF, ZIP, EPUB, or MP4 content up to 50 MB, assigns an unpredictable storage name, grants existing eligible paid orders access, and audits uploads. Revocation disables the file and revokes all associated entitlements in one transaction. Customers use `/account/downloads`; each link has a random token stored only as a hash, is bound to the signed-in owner, and expires after 24 hours. The application enforces five transfers per purchased file in a transaction before streaming and shows the remaining count. Files are served as attachments with `application/octet-stream` and are never linked by a public URL. Configure PHP `upload_max_filesize` and `post_max_size` to at least `50M` to use the full application limit. Customers use `/account/courses`; admins author ordered text sections and lessons at `/admin/courses`. Hosted video delivery and the final course access/refund policy remain future work.
- Hosted payment uses SSLCOMMERZ in BDT. It remains disabled unless `SSLCOMMERZ_STORE_ID` and `SSLCOMMERZ_STORE_PASSWORD` are configured; sandbox mode defaults on. Before live use, set real merchant credentials, set `SSLCOMMERZ_SANDBOX=false`, set `APP_URL` to the public HTTPS site, configure the SSLCOMMERZ IPN URL as `/payments/ipn`, run migrations, and complete sandbox and production verification. Both browser returns and IPNs are verified against the provider's server-side validation API before an order is marked paid. Risky, late, or duplicate verified payments are placed into `payment_review` for manual reconciliation. See the [SSLCOMMERZ Hosted Payment API documentation](https://developer.sslcommerz.com/doc/v4/).
- Customer account holders can update their display name and manage their own saved addresses. Address writes require CSRF tokens; ownership is enforced in every address query, and setting a default address replaces the previous default for that customer.
- MySQL DDL statements are not fully transactional. If a migration fails partway, inspect the database before retrying; current schema statements use `CREATE TABLE IF NOT EXISTS` for safe reruns. Migration 010 snapshots product types and adds shipment/event records; migration 011 adds private file metadata and paid-order entitlements; migration 012 adds courses, lessons, paid enrollments, and progress; migration 013 adds administrator audit records; migration 014 adds category storefront visibility; migration 016 adds expiring download tokens and per-file usage limits; migration 017 adds hashed customer API tokens. Verify all migrations and order, download, API, catalog administration, file administration, and course flows against MySQL/MariaDB before production use.
- Gate 0 review materials: `docs/gate-0/decision-register.md` records proposed defaults that require owner approval, and `docs/gate-0/sitemap-and-wireframes.html` provides a responsive, clickable low-fidelity draft. These are review artefacts, not signed requirements or an approved design.

## Related source

The implementation follows the Helpyard.store New Implementation Plan v2 prepared for the project.
