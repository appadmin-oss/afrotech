# Afrotech Academy

The Afrostrength youth technology academy — a full-stack LMS and Summer School
platform. Cybersecurity, Google Workspace, AI & Automations, Coding, Graphics
Design, and Digital Marketing for the next generation of African builders (age 7+).

> _Building brands, strengthening legacies._

Built as a dependency-light **PHP MVC** application (no framework, no Composer
required to boot) so it deploys cleanly onto shared cPanel hosting — the same
stack and conventions as the Afrostrength site.

---

## What's inside

| Area | Routes | Notes |
|------|--------|-------|
| **Landing page** | `/` | Hero with the flier's animated **binary "10101"** backdrop + starburst fee badge, six-track list, featured courses, two Lagos campuses, register CTA. |
| **Summer School** | `/summer`, `/summer/track/{slug}` | Registration page + endpoint, rendered from the **live form definition** (see below). Mints a reference code, emails the family + the academy inbox, soft-fails to `storage/summer.log` if the DB/SMTP aren't configured. |
| **Form builder** | `/admin/forms` | Visual, logic-based editor for the public registration form. Versioned, previewed against the real renderer, published atomically. |
| **LMS catalog** | `/academy`, `/academy/{slug}` | Course listing + detail with syllabus, learner enrolment. |
| **Learner area** | `/login`, `/dashboard` | Student sign-in and "My Learning" with per-course progress. |
| **RBAC admin** | `/admin/**` | Full operator console with role-based access control. |
| **Registration API** | `POST /api/summer/register` | JSON or form; CSRF-guarded; returns the reference code. |
| **Receipt** | `/summer/receipt/{reference}` | Stable, printable payment receipt — itemised, re-visitable, emailed to the family. |

### The six tracks (from the flier)
Cybersecurity · Google Workspace · AI & Automations · Coding · Graphics Design · Digital Marketing.

---

## Admin RBAC

Five roles, each mapped to a set of dotted permissions in
[`src/core/Rbac.php`](src/core/Rbac.php):

| Role | Can do |
|------|--------|
| **Super Admin** | Everything, including managing operators and settings. |
| **Administrator** | Registrations, students, courses, content, and publishing the public registration form. Not operators. |
| **Registrar** | Own the summer intake — review, confirm, export. Clears bank transfers. Reads the form builder. |
| **Instructor** | Manage courses, view enrolled students. |
| **Viewer** | Read-only across the admin. |

Guards are enforced two ways: controllers call `Rbac::require('some.permission')`
(hard 403), and views hide affordances with `Rbac::can(...)`. Operators can only
manage or assign roles **junior to their own**, can't suspend/delete themselves,
and the **last super admin can't be demoted or removed** (no lock-out). The live
permission matrix is rendered at `/admin/users`.

### ID minting
Every learner, registrant, operator, and certificate gets a printable house ID
from [`src/core/Ids.php`](src/core/Ids.php), using an unambiguous alphabet
(no `0/O/1/I/L`) so codes survive being read aloud or typed from a screenshot:

```
Student ......  AFT-STU-2026-7H3QK
Summer intake   AFT-SS26-4F9RD
Operator .....  AFT-STAFF-9K2P
Certificate ..  AFT-CERT-2026-J4M8-QP7X
```

---

## Local setup

```bash
# 1. Serve (PHP 8.1+). Runs even without a database — pages fall back to
#    seeded content and form submissions log to storage/.
AFT_DEBUG=1 php -S 127.0.0.1:8000 index.php

# 2. (Optional) database — MySQL 5.7+ / MariaDB 10.3+
mysql -u root -p -e "CREATE DATABASE afrotech CHARACTER SET utf8mb4"
mysql -u root -p afrotech < database/schema.sql
mysql -u root -p afrotech < database/seed.sql   # tracks, courses, first super admin

# 2b. Existing installs only — schema.sql already contains these. Each is
#     additive and re-runnable, so a second import is a no-op.
mysql -u root -p afrotech < database/migrations/2026-07-form-builder.sql
mysql -u root -p afrotech < database/migrations/2026-07-payment-confirmation.sql

# 3. Create your own operator (recommended over the seeded default)
php scripts/create-admin.php owner you@example.com 'a-strong-password' super_admin
```

The seed creates a first super admin — **username `owner`, password
`ChangeMe!2026`**. Change it immediately after first login (or skip the seed
row and use `create-admin.php`).

### Configuration (environment variables)

| Var | Purpose |
|-----|---------|
| `DB_HOST` `DB_PORT` `DB_NAME` `DB_USER` `DB_PASS` | MySQL connection |
| `MAIL_ENABLED` `MAIL_HOST` `MAIL_PORT` `MAIL_USERNAME` `MAIL_PASSWORD` `MAIL_FROM` `MAIL_INBOX` | SMTP (via PHPMailer, vendored) |
| `PAYMENT_PROVIDER` | `paystack` (default) |
| `PAYSTACK_SECRET_KEY` `PAYSTACK_PUBLIC_KEY` `PAYSTACK_WEBHOOK_SECRET` | Paystack keys — when absent, checkout falls back to bank transfer |
| `AFT_URL` | Canonical base URL (auto-detected if unset) |
| `AFT_DEBUG` | `1` shows full errors while developing |

**Email** uses **PHPMailer** (committed under `vendor/`, so no build step on
cPanel); it soft-fails to `storage/mail.log` when SMTP creds are absent. The SMTP
approach mirrors MAM Academy's working mailer.

**Payments** use **Paystack** (server-side initialize → hosted checkout →
callback verify + HMAC webhook, adapted from MAM Academy). Set the keys above to
go live; without them the checkout shows bank-transfer instructions so the flow
still works end-to-end. Point your Paystack webhook at `/webhooks/paystack`.

### Nothing is hard-coded
Fee, currency, age, registration deadline, cohort dates, seats, campuses, and the
payment toggle live in the **`settings`** table and are edited at `/admin/settings`
(with built-in defaults as a fallback). **Promotions** (the announcement ribbon +
live countdown) and **discount codes** (percent or fixed, with usage limits and
date windows) are managed at `/admin/promotions` and `/admin/discounts` and applied
at checkout.

---

## The form builder

`/summer` does not have a hard-coded form. It renders whatever the **live form
definition** says, and operators edit that definition at **`/admin/forms`** —
no deploy, no developer.

**Blocks.** 21 types: short text, paragraph, email, phone, URL, number, date,
time, choice (radio or dropdown), multi-select, yes/no, consent, 1–10 scale,
country, file, hidden/captured — plus page breaks, section headings, info text
and dividers for structure.

**Logic.** Any block can carry two independent rule groups, each matching
**all** or **any** of up to 12 rules across 18 operators (`is`, `is not`,
contains, starts/ends with, `>`, `≥`, `<`, `≤`, between, one of, none of,
includes, blank, has any answer, ticked, unticked):

- **Show / hide** — a field appears only when the rules pass.
- **Require conditionally** — a field only demands an answer when they do.

Rules may only reference fields **above** them, which is enforced on save: a
rule about a later answer could never be true, so it is a mistake rather than a
feature. Chains work — B depends on A, C depends on B — because visibility is
resolved to a **fixed point**, not in a single pass.

**Validation.** Per field: min/max value, min/max length, min/max selections,
and an operator-authored regex with its own error message. A pattern that
doesn't compile is refused at save time rather than breaking the public form.

**Pricing.** A field or an individual option can carry a naira amount. Selected
add-ons move the running total on the page and the amount actually charged at
checkout — the fee is stored on the registration, not recomputed from settings.

**Steps.** Page breaks split the form into steps with a progress rail; each step
validates before it advances.

**The server is the authority.** `assets/js/form-logic.js` mirrors
[`src/core/FormEngine.php`](src/core/FormEngine.php) so the form reacts
instantly, but on submit the server re-resolves visibility, re-validates every
answer and recomputes the price from what it decided was askable. A crafted POST
cannot answer a hidden question, skip a required one, or buy an add-on the logic
never offered. With JavaScript off the form still works — every field is present
and posts normally.

**Versioning.** Editing writes a draft; publishing archives the current live
version and inserts a new one in a single transaction, so there is never a
window with two live definitions or none. Every submission records the version
that collected it, so the ops console and CSV export replay answers against the
questions that were actually asked. Any past version can be rolled forward, and
the definition exports as JSON.

Run the engine's test suite (no database needed):

```bash
php scripts/test-form-engine.php
```

Field keys can be mapped onto the registration record's own columns
(`student_name`, `email`, `track_slug`, …); the six the intake pipeline needs are
locked and cannot be removed. Everything else is stored as an answer. A field
marked **sensitive** stays inside the console — never exported, never emailed.

---

## Payment confirmation

A payment can be confirmed from three directions, and any two can arrive at
once: the **gateway callback** the parent's browser follows, the
**server-to-server webhook**, and an **operator** clearing a bank transfer in
the console. All three run one path.

**Confirmed once, never twice.** The guard is the write itself —
`UPDATE … WHERE reference = ? AND status <> 'succeeded'` — so the row is the
lock and only the caller that actually changed it runs the side effects.
Without that, a callback and a webhook landing together send the family two
receipts and burn two uses of a limited discount code on one sale. The receipt
email is claimed separately (`receipt_sent_at`), so a later re-verification
can't email again.

**A charge has to actually settle the invoice.** `chargeAcceptable()` checks
status, amount and currency. Paystack reports **minor units**, so ₦55,000 is
`5500000` — comparing that against naira is the classic way to accept a 100×
shortfall. Over-payment is fine; under-payment, a mismatched currency, or a
webhook whose signature is valid but whose amount belongs to another invoice
are all refused.

**Pending is its own outcome.** If the gateway can't be reached to verify, the
payment stays pending and the parent is told we're still checking — not that
their payment failed, and explicitly *don't pay twice*. The webhook or an
operator can still confirm it.

**Bank transfers are first-class.** Marking a registration paid used to update
one column: the ledger stayed pending and the family never got a receipt.
Confirming a transfer (`/admin/payments`, or from the registration record) now
runs the same path a card payment does — same ledger state, same receipt, same
audit trail — under `payments.confirm`.

**The receipt is a document, not a flash message.** `/summer/receipt/{reference}`
is stable, re-visitable and printable, itemising the programme fee, each add-on
the form priced, and any discount. Parents forward it and produce it when a
school or sponsor asks for proof. The callback redirects there, so a refresh
re-reads a record instead of re-verifying a charge. The academy inbox gets its
own notification, because staffing and campus lists run off *paid* places.

```bash
php scripts/test-payment-confirmation.php
```

### Binary "10101" effect
The flier's digital backdrop is a self-contained, CSP-safe engine
(`assets/js/binary-matrix.js`): DPR-aware canvas, parallax depth, glowing heads,
pointer-reactive brightening, theme-aware colour, periodic **word-reveals** that
spell the brand and track names inside the stream, and a motion-free static frame
under `prefers-reduced-motion`. Drop it on any element with
`data-binary data-words="…"`.

---

## Design system

Lifted straight from the summer-school flier — **warm bone paper, near-black
ink, vivid academy red (`#E4022B`), bold geometric display type** (Space
Grotesk), and the animated **binary "10101"** field behind the hero (canvas rain
with a motion-free CSS fallback; respects `prefers-reduced-motion`). Tokens live
in [`assets/css/tokens.css`](assets/css/tokens.css); the operator console runs
on the dark variant.

## Project layout

```
index.php              front controller + hardened session
config/                app, database, mail, routes
src/core/              Router, Controller, View, Auth, Rbac, StudentAuth,
                       Ids, Mailer, Validator, Csrf, Security, Database, Helpers,
                       FormEngine (logic + validation), FormRenderer
src/models/            AdminUser, Track, Course, Student, Enrollment,
                       SummerRegistration, ContentBlock, FormDef
src/controllers/       public controllers + Admin/ (namespaced) console
src/views/             layouts, pages, admin, partials, emails
assets/                css (tokens, app, admin, form, builder),
                       js (app.js, form-logic.js, form-builder.js), images
database/              schema.sql, seed.sql, migrations/
scripts/               create-admin.php, test-form-engine.php,
                       test-payment-confirmation.php
```

Security baseline: hardened session cookie (HttpOnly/SameSite/Secure/strict),
CSRF on every mutating route, PDO prepared statements throughout, bcrypt
password hashing, a strict Content-Security-Policy, and a separate auth realm
for operators vs. learners.
