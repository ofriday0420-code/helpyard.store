# Next-step implementation plan

The next unfinished milestone in the Helpyard.store New Implementation Plan
(version 2.0, 05 October 2026) is the remaining P1 operator tooling. The first
customer course-learning flow and private-file management are implemented;
this slice completes product/category maintenance and inventory controls with
audited staff-only operations.

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
  local PHP runtime has no PDO database driver, so this remains an explicit
  external verification gate.
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
