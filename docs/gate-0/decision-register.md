# Gate 0 decision register

**Status:** Draft for product-owner and technical-owner review  
**Basis:** Helpyard.store New Implementation Plan v2.0, 05 October 2026  
**Rule:** Suggested defaults below are working assumptions, not approved business, legal, or production policy. Do not enable the affected live workflow until the accountable owner records approval.

**Build direction (06 October 2026):** The product owner asked for professional implementation decisions and continued step-by-step delivery. The technical defaults may now be implemented as working behavior. This direction does not record product-owner, legal, merchant, or production approval; those sign-offs remain open where required.

## Product and customer policy

| Decision | Proposed starting point | Owner | Status |
|---|---|---|---|
| Product sections and type rules | One platform with Courses, Ready Websites, Official Software, Books, and Cyber-Security Devices. Keep one shared product model and implement type-specific fulfillment only where needed. | Product owner | Pending approval |
| Prices, currency, offers, and variants | BDT as the base currency; optional comparison/sale price; variants only for products that need selectable options. The server remains authoritative for price and stock. | Product owner | Pending approval |
| Download access | Keep paid files private; use customer-session-bound random access tokens that expire after 24 hours, a five-transfer cap per purchased file, and administrator revocation. | Product owner | Working default implemented; owner/legal review pending |
| Refund and entitlement revocation | Approve a written refund policy for each product type; revoke digital/course/license access when a refund is completed, subject to the final policy and legal review. | Product owner + legal adviser | Pending approval |
| Shipping zones and rates | Start with inside-Dhaka and outside-Dhaka zones with fixed rates; confirm actual prices, exclusions, carrier, and delivery estimates before checkout is enabled. | Product owner | Pending approval |
| Payment methods | SSLCOMMERZ first; complete sandbox acceptance before production credentials or live payment. Consider other gateways only after launch needs are known. | Product owner + technical owner | Pending approval |
| Course access | Lifetime access for v1, with lesson completion tracking; certificates deferred. Confirm whether refunds revoke access and whether course content is text, hosted video, or both. | Product owner | Pending approval |
| Software and website fulfillment | Use a pre-loaded license pool and one auditable assignment per eligible order item; define activation, reassignment, revocation, and website handoff rules before implementation. Never expose reusable secrets. | Product owner | Pending approval |

## Operations and technical policy

| Decision | Proposed starting point | Owner | Status |
|---|---|---|---|
| Email provider and sending domain | Select a transactional provider with bounce visibility; configure SPF, DKIM, and DMARC before customer lifecycle email. | Technical owner | Pending selection |
| Hosting, TLS, and backups | Staging plus production on a supported PHP 8.3+/MySQL or MariaDB host; HTTPS everywhere; daily backups and a documented monthly restore test. Confirm hosting, region, retention, and recovery targets. | Technical owner | Pending approval |
| Roles and administrator lifecycle | Customer, support, staff, and administrator roles; trusted CLI/operations-only administrator provisioning; no public administrator registration. Define what staff and support may access. | Technical owner | Pending approval |
| Support process | Published support email plus a ticket log, named responder, and an owner-approved response-time target. | Product owner + technical owner | Pending approval |
| Integration and release gate | Apply all migrations to a clean MySQL database in CI; run database-backed catalog, checkout/stock, payment, entitlement, ownership, and fulfillment tests before treating related workflows as launch-ready. | Technical owner | In progress |
| Design approval | Approve sitemap, principal user flows, low-fidelity desktop/mobile wireframes, design tokens, and clickable prototype before broad visual-system implementation. | Product owner | Pending approval |
| Customer API authentication | Customer-only opaque bearer tokens, hashed at rest, 30-day expiry, a maximum of ten active tokens per account, and customer-managed revocation. Merge guest carts into the account cart at sign-in so web and API use the same account cart. | Technical owner | Working default implemented; owner review pending |

## Required decisions before production

- [ ] Product types, pricing, currency, variants, shipping and tax treatment
- [ ] Download, refund, revocation, course, license and website-handoff policy
- [ ] SSLCOMMERZ sandbox evidence and an operational reconciliation/refund process
- [ ] Email provider, domain authentication, support ownership and response target
- [ ] Role permissions, hosting/TLS, secrets, backup retention and recovery targets
- [ ] Requirements, sitemap, user-flow, wireframe and design approval
- [ ] Clean MySQL migration and integration-test evidence in CI

## Approval record

This register intentionally records no approval on behalf of an owner.

| Approval | Name | Date | Decision / notes |
|---|---|---|---|
| Product owner |  |  |  |
| Technical owner |  |  |  |
| Legal review where applicable |  |  |  |
