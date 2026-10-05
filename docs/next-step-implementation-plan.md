# Next-step implementation plan

The next unfinished milestone in the Helpyard.store New Implementation Plan
(version 2.0, 05 October 2026) is the first course-learning flow. Physical
fulfillment and the first protected-download slice already exist; the course
flow adds customer enrollment, access control, and progress without exposing
course content through public URLs.

## Course first-flow acceptance

- Add course, section, lesson, enrollment, and lesson-progress records.
- Create an enrollment only when a verified payment moves the order to `paid`;
  retries must not duplicate an order's enrollment.
- Require an active enrollment backed by a paid order for both course viewing
  and lesson progress updates.
- Show enrolled courses and completion counts in the customer account.
- Serve lesson content only through the authorized customer course page; do
  not add public media URLs.

## Remaining fulfillment sequence

1. **Physical-order fulfillment and shipment tracking (implemented)**
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

2. **Protected digital delivery (first slice implemented)**
   - Define private file metadata/storage and paid-order entitlements.
   - Add an authorization-checked download endpoint with expiring/revocable
     access; never expose private files through `public/`.
   - Acceptance: unpaid, expired, and cross-customer downloads are denied.
   - First slice now implemented: private file metadata, paid-order entitlement
     creation, customer-owned download list, private-root path validation, and
     attachment streaming. Operator-managed file upload/admin UI and download
     expiry/usage limits remain follow-up work.

3. **Course fulfillment (current build)**
   - Add enrollment and lesson-access records tied to verified paid orders.
   - Add the first course-learning flow and customer course view.
   - Acceptance: only entitled customers can access enrolled course content.

4. **Software licensing and ready-website delivery**
   - Define the product-specific delivery/licensing requirements before
     implementing assignment or handoff workflows.
   - Acceptance: an eligible paid order receives one auditable entitlement or
     assignment, without exposing reusable secrets publicly.

5. **Fulfillment notifications**
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

The current delivery implements course schema, verified-paid enrollment,
customer-only access, and lesson completion tracking. Course authoring/admin
management, video/media hosting, licensing, email delivery, production
deployment, and production acceptance remain incomplete. A MySQL/MariaDB
integration environment with PHP `pdo_mysql` is still required to validate
migrations, payment callback idempotency, and customer ownership isolation.
