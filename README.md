# Helpyard.store

This repository now contains the foundation for the Helpyard.store commerce platform described in the professional implementation plan.

## Current implementation status

This is the first delivery milestone in the plan: engineering foundation and a working PHP MVC-style shell.

### Included
- MVC-style app structure aligned to the roadmap
- Route definitions for homepage and API endpoints
- Configuration for app and database values through environment variables
- SQL migration and seed files for core commerce tables
- A small starter home page and API response structure

### Planned next phases
1. Product type and catalog architecture
2. Authentication and secure customer dashboard
3. Cart, checkout, and server-side pricing validation
4. Payment gateway integration and fulfillment rules
5. Admin controls, audit logging, and production hardening

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
8. Customer accounts are available at `/register`, `/login`, `/account`, and `/account/addresses`.

On Windows, run `php --ini` to find the active `php.ini`. If `pdo_mysql` is not listed by `php -m`, enable `extension=pdo_mysql` in that configuration, ensure `extension_dir` points to PHP's `ext` directory, restart the terminal, and verify `php -m` lists `pdo_mysql`.

## Notes

- Base security rules are enforced in the roadmap and should be applied in later phases.
- Browser values must never be trusted for pricing, quantity, discount, or payment confirmation.
- Private digital assets must remain behind backend authorization checks.
- The homepage and five catalog pages now share a responsive storefront layout and load products from the database-backed API. Run migrations and seeders explicitly with the commands above; they do not run automatically on web requests.
- Catalog API filters: `GET /api/v1/products?category=books&type=book&q=operations&page=1&per_page=20`. Search covers product name, descriptions, and category; query values and filters are validated, SQL wildcard characters are escaped, and page size is capped at 50. Product detail is at `GET /api/v1/products/{slug}` and returns active product details, images, and active variants. If MySQL is unavailable, the API returns HTTP 503 rather than sample or success-shaped fallback data.
- `.env` supports one `NAME=value` entry per line, with optional matching single or double quotes around values. Do not commit real secrets.
- Migrations are tracked in `schema_migrations`; applied migration files must not be edited. Add a new numbered SQL migration for schema changes.
- The first authentication slice provides customer registration, login, logout, CSRF-protected forms, session rotation, login throttling, and a protected account page. Email verification and password-reset delivery are not yet enabled because no email provider is configured.
- The cart is database-backed and associated with a random key held in the server-side session. Product and variant prices and stock are read from the database; add/update/remove forms require CSRF tokens, and requested quantities are capped at 99 and checked against availability. Guest-cart/account-cart merging is not yet implemented.
- Checkout requires a signed-in customer and an address belonging to that customer. It snapshots the delivery address and product details, recalculates totals from current database prices, reserves stock transactionally, and creates an order in `payment_pending` for 30 minutes. Expired reservations are released when a checkout or order-status request is made; a scheduled cleanup task and payment provider integration are still needed before production use. No payment is captured or implied.
- Customer account holders can update their display name and manage their own saved addresses. Address writes require CSRF tokens; ownership is enforced in every address query, and setting a default address replaces the previous default for that customer.
- MySQL DDL statements are not fully transactional. If a migration fails partway, inspect the database before retrying; current schema statements use `CREATE TABLE IF NOT EXISTS` for safe reruns.

## Related source

The implementation follows the Helpyard.store Professional Developer Implementation Plan prepared for the project.
