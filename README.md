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
7. Visit `http://127.0.0.1:8000/` for the storefront, `/courses`, `/websites`, `/software`, `/books`, or `/devices` for the five sections, and `/product/business-website-starter` for a product page. The API is available at `/api/v1/products`, `/api/v1/products/{slug}`, and `/api/v1/categories`.

On Windows, run `php --ini` to find the active `php.ini`. If `pdo_mysql` is not listed by `php -m`, enable `extension=pdo_mysql` in that configuration, ensure `extension_dir` points to PHP's `ext` directory, restart the terminal, and verify `php -m` lists `pdo_mysql`.

## Notes

- Base security rules are enforced in the roadmap and should be applied in later phases.
- Browser values must never be trusted for pricing, quantity, discount, or payment confirmation.
- Private digital assets must remain behind backend authorization checks.
- The homepage and five catalog pages now share a responsive storefront layout and load products from the database-backed API. Run migrations and seeders explicitly with the commands above; they do not run automatically on web requests.
- Catalog API filters: `GET /api/v1/products?category=books&type=book&q=operations&page=1&per_page=20`. Search covers product name, descriptions, and category; query values and filters are validated, SQL wildcard characters are escaped, and page size is capped at 50. Product detail is at `GET /api/v1/products/{slug}` and returns active product details, images, and active variants. If MySQL is unavailable, the API returns HTTP 503 rather than sample or success-shaped fallback data.
- `.env` supports one `NAME=value` entry per line, with optional matching single or double quotes around values. Do not commit real secrets.
- Migrations are tracked in `schema_migrations`; applied migration files must not be edited. Add a new numbered SQL migration for schema changes.
- MySQL DDL statements are not fully transactional. If a migration fails partway, inspect the database before retrying; current schema statements use `CREATE TABLE IF NOT EXISTS` for safe reruns.

## Related source

The implementation follows the Helpyard.store Professional Developer Implementation Plan prepared for the project.
