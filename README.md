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

1. Copy `.env.example` to `.env` and adjust the local database values.
2. Serve the application from the `public` directory using your PHP web server.
3. Visit `/` for the storefront shell and `/api/v1/products` for JSON output.

## Notes

- Base security rules are enforced in the roadmap and should be applied in later phases.
- Browser values must never be trusted for pricing, quantity, discount, or payment confirmation.
- Private digital assets must remain behind backend authorization checks.

## Related source

The implementation follows the Helpyard.store Professional Developer Implementation Plan prepared for the project.
