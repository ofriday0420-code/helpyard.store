# Helpyard.store

This repository contains a working storefront, customer account flows, a session-backed cart, and the first authenticated checkout/order flow for the Helpyard.store commerce platform.

## Current implementation status

The engineering foundation and initial commerce flows are implemented. Checkout, hosted SSLCOMMERZ callbacks, a reservation cleanup worker, customer order history, physical-order fulfillment, protected downloads, and the first customer course-learning flow are in place; merchant credentials, database integration testing, course authoring, software licensing, and production approval are still required.

### Included
- MVC-style app structure aligned to the roadmap
- Route definitions for homepage and API endpoints
- Configuration for app and database values through environment variables
- SQL migration and seed files for core commerce tables
- Responsive storefront, database-backed catalog API, and product pages
- Customer authentication, profile, and saved delivery addresses
- Cart, authenticated checkout, price/stock revalidation, and order history details
- Staff-only physical/book order processing, shipment tracking, and customer-visible delivery status
- Verified-payment course enrollment, customer-only course access, and lesson progress
- Scheduled reservation cleanup command and customer recent-order history

### Planned next phases
1. Admin product/course/file management, inventory, and payment-review operations
2. Software licensing, fulfillment notifications, and account recovery
3. Audit logging, integration coverage, deployment, and production hardening

## Quick start

1. Install and start MySQL 8 or MariaDB, and enable PHP's `pdo_mysql` extension.
2. Create the development database:
   ```sql
   CREATE DATABASE helpyard_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Copy `.env.example` to `.env` and set the local database host, port, name, user, and password.
4. From the project root, run `php database/migrate.php`, then `php database/seed.php`.
5. Run the foundation checks with `php tests/run.php`.
6. Start the server with `php -S 127.0.0.1:8000 -t public`.
7. Visit `http://127.0.0.1:8000/` for the storefront, `/courses`, `/websites`, `/software`, `/books`, or `/devices` for the five sections, `/product/business-website-starter` for a product page, `/cart` to manage the current browser session's cart, and `/checkout` after signing in and saving a delivery address. Orders are available at `/orders/{id}`. The API is available at `/api/v1/products`, `/api/v1/products/{slug}`, and `/api/v1/categories`.
8. Customer accounts are available at `/register`, `/login`, `/account`, `/account/addresses`, `/account/downloads`, and `/account/courses`.

On Windows, run `php --ini` to find the active `php.ini`. If `pdo_mysql` is not listed by `php -m`, enable `extension=pdo_mysql` in that configuration, ensure `extension_dir` points to PHP's `ext` directory, restart the terminal, and verify `php -m` lists `pdo_mysql`.

## Notes

- Base security rules are enforced in the roadmap and should be applied in later phases.
- Browser values must never be trusted for pricing, quantity, discount, or payment confirmation.
- Private digital assets must remain behind backend authorization checks.
- The homepage and five catalog pages now share a responsive storefront layout and load products from the database-backed API. Run migrations and seeders explicitly with the commands above; they do not run automatically on web requests.
- Catalog API filters: `GET /api/v1/products?category=books&type=book&q=operations&page=1&per_page=20`. Search covers product name, descriptions, and category; query values and filters are validated, SQL wildcard characters are escaped, and page size is capped at 50. Product detail is at `GET /api/v1/products/{slug}` and returns active product details, images, and active variants. If MySQL is unavailable, the API returns HTTP 503 rather than sample or success-shaped fallback data.
- `.env` supports one `NAME=value` entry per line, with optional matching single or double quotes around values. Do not commit real secrets.
- Migrations are tracked in `schema_migrations`; applied migration files must not be edited. Add a new numbered SQL migration for schema changes. Migration 012 adds course, section, lesson, paid enrollment, and lesson progress tables; course access is shown only to customers with a verified, non-revoked enrollment, and lesson content is served from the application rather than public media URLs.
- The first authentication slice provides customer registration, login, logout, CSRF-protected forms, session rotation, login throttling, and a protected account page. Email verification and password-reset delivery are not yet enabled because no email provider is configured.
- The cart is database-backed and associated with a random key held in the server-side session. Product and variant prices and stock are read from the database; add/update/remove forms require CSRF tokens, and requested quantities are capped at 99 and checked against availability. Guest-cart/account-cart merging is not yet implemented.
- Checkout requires a signed-in customer and an address belonging to that customer. It snapshots the delivery address and product details, recalculates totals from current database prices, reserves stock transactionally, and creates an order in `payment_pending` for 30 minutes. Expired reservations are released when a checkout, order-status, verified payment request, or the cleanup worker runs.
- Schedule `php database/release_expired_reservations.php` to run every few minutes (for example, Windows Task Scheduler or cron) so expired reservations are released even when no customers visit checkout. The worker processes bounded batches, restores inventory, cancels pending payment attempts, and exits non-zero when cleanup fails. The customer account lists the latest 50 orders.
- Physical fulfillment is documented in `docs/next-step-implementation-plan.md`. Admin fulfillment is available at `/admin/fulfillment` to authenticated accounts with the `admin` role; admin roles must be assigned through a trusted operator-controlled process (there is no public admin registration). Only paid orders whose snapshotted lines are all `physical` or `book` can move through processing, shipped, and delivered. Carrier and tracking information is shown on that order's customer-owned detail page.
- Private digital files are stored outside `public/` in `storage/private` by default; set `PRIVATE_STORAGE_PATH` to another private directory when deploying. Add each downloadable asset's relative `storage_key` and customer-facing `download_name` to `product_files` only after placing the file under that private root. Verified successful payment creates order-line entitlements for configured files; payment-review, unpaid, refunded, and cancelled orders do not receive download access. Customers use `/account/downloads`; the handler checks order ownership, eligible paid order state, entitlement revocation, active file metadata, and the resolved path before streaming the file. Files are served as attachments with `application/octet-stream` and are never linked by a public URL. The course first flow is available at `/account/courses`; course authoring, protected video/media delivery, and admin file-management/upload tooling remain future work.
- Hosted payment uses SSLCOMMERZ in BDT. It remains disabled unless `SSLCOMMERZ_STORE_ID` and `SSLCOMMERZ_STORE_PASSWORD` are configured; sandbox mode defaults on. Before live use, set real merchant credentials, set `SSLCOMMERZ_SANDBOX=false`, set `APP_URL` to the public HTTPS site, configure the SSLCOMMERZ IPN URL as `/payments/ipn`, run migrations, and complete sandbox and production verification. Both browser returns and IPNs are verified against the provider's server-side validation API before an order is marked paid. Risky, late, or duplicate verified payments are placed into `payment_review` for manual reconciliation. See the [SSLCOMMERZ Hosted Payment API documentation](https://developer.sslcommerz.com/doc/v4/).
- Customer account holders can update their display name and manage their own saved addresses. Address writes require CSRF tokens; ownership is enforced in every address query, and setting a default address replaces the previous default for that customer.
- MySQL DDL statements are not fully transactional. If a migration fails partway, inspect the database before retrying; current schema statements use `CREATE TABLE IF NOT EXISTS` for safe reruns. Migration 010 snapshots product types and adds shipment/event records; migration 011 adds private file metadata and paid-order entitlements; migration 012 adds courses, lessons, paid enrollments, and progress. Verify all migrations and order, download, and course flows against MySQL/MariaDB before production use.

## Related source

The implementation follows the Helpyard.store New Implementation Plan v2 prepared for the project.
