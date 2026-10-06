# Helpyard.store next-build implementation plan

**Prepared:** 06 October 2026  
**Baseline:** repository source, README, Gate 0 decision register, and the v2 implementation roadmap.

This plan starts with the open release gates and then finishes the existing web commerce platform before beginning deferred PWA or native work. The documented phase scores total 605/1,500 points, or about **40% across all 15 roadmap phases** and **47% across phases 0–12**. These are equal-weight planning estimates, not release evidence.

## Build order

| Step | Work | Completion evidence | Dependency / state |
| --- | --- | --- | --- |
| 0. Gate 0 | Obtain product and technical owner decisions; approve sitemap, customer flows, and responsive wireframes. Keep unresolved legal and refund decisions explicitly assigned. | Approval record completed in `docs/gate-0/decision-register.md`; approved requirements and flows. | Owner input required; draft materials already exist. |
| 1. Foundation confidence | Run clean migrations, seed, repeat migrations, and the full integration workflow on hosted MySQL 8.4. Fix failures. | Successful hosted CI run attached to the branch/release record. | The latest hosted run passed on base commit `49e7e3f`; rerun after the current uncommitted changes are pushed. |
| 2. API v1 contract | Document existing public endpoints and their real request/response behavior; then design and implement authenticated account, cart, and order APIs against an approved auth model. | OpenAPI contract matches implementation; contract validation and API acceptance evidence. | Existing public catalog endpoints documented in this build. Authenticated API design follows. |
| 3. Commerce confidence | Exercise SSLCOMMERZ sandbox success, cancel, fail, duplicate, late, and risk paths; finish reconciliation, refund, reservation, cart merge, coupons, and shipping rules as approved. | Sandbox evidence, approved reconciliation/refund procedure, database-backed acceptance evidence. | Gateway credentials and business decisions required. |
| 4. Operable platform | Complete administrator payment-review actions, course-authoring enhancements and media, product images/content, software license and website handoff flows, and operational notifications. | Admin can operate approved workflows without direct database edits; auditable entitlements and delivery. | Refund, licensing, role, content, and email policies/providers required where applicable. |
| 5. Customer experience | Complete the approved responsive design system, localization, accessibility, product details, and customer lifecycle flows (email verification/reset, wishlist/notifications where in approved scope). | Approved design; reviewed keyboard/mobile/accessibility and customer-flow evidence. | Gate 0 design approval and selected email provider. |
| 6. Discovery and security | Finish search/SEO (metadata, canonical URLs, sitemap, robots, structured data), rate limits, logging/monitoring, upload review, independent security assessment, backups and restore checks. | Search and security checklist completed; no unresolved critical/high findings; restore evidence. | Named operations owner and hosting decisions. |
| 7. Release | Prepare staging and production, HTTPS, secrets, cron/worker scheduling, migration/rollback procedures, monitoring, smoke checks, and production acceptance. | Staging acceptance, successful restore/rollback rehearsal, production owner sign-off. | Hosting, domain, support and operational ownership. |
| 8. Deferred clients | Publish and stabilize API v1 before adding installable PWA behavior; consider native mobile only after PWA/API outcomes. | Separate approved scope and acceptance criteria. | Explicitly post-launch/deferred. |

## Immediate implementation slice

Independent slices completed so far:

- Documented the currently shipped public catalog API in `docs/openapi.yaml` and served that same document at `/api/v1/openapi.yaml`. The contract covers only routes that exist today; it does not claim that authentication, cart, or order APIs are implemented.
- Updated cart and checkout messaging to reflect whether SSLCOMMERZ is configured and to make clear that the displayed subtotal excludes shipping charges.
- Added initial course authoring at `/admin/courses`: create workspaces for newly added course products, append ordered sections and lessons, edit lesson text, and publish/unpublish lessons. Writes require the admin role and a CSRF token and are audited in the same transaction. This does not add video delivery or choose course refund/access-duration policy.

These slices do not close Gate 0 or authorize unresolved product/payment policies.

## Release blockers that need owners

- Product, pricing, tax, shipping, refund, download, course, software-license, support, and role policies in the Gate 0 decision register.
- Product/technical owner approval of the sitemap and wireframes.
- SSLCOMMERZ sandbox access/acceptance; hosted MySQL 8.4 CI passed on base commit `49e7e3f` and must rerun for the current changes.
- A selected email provider, hosting/domain, support responder, and operations owner for production.

## Working rules

- Keep the one shared commerce platform and keep price, stock, entitlement, and payment decisions server-side.
- Keep private paid files outside the public web root and enforce ownership on every access.
- Preserve migration history; make schema changes with new numbered migrations.
- Do not mark a roadmap phase or release gate complete from a percentage estimate alone.
- Record the behavior implemented and remaining work in this plan and the README as each slice lands.
