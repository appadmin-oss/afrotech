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
| **Summer School** | `/summer`, `/summer/track/{slug}` | Registration page + endpoint. Mints a reference code, emails the family + the academy inbox, soft-fails to `storage/summer.log` if the DB/SMTP aren't configured. |
| **LMS catalog** | `/academy`, `/academy/{slug}` | Course listing + detail with syllabus, learner enrolment. |
| **Learner area** | `/login`, `/dashboard` | Student sign-in and "My Learning" with per-course progress. |
| **RBAC admin** | `/admin/**` | Full operator console with role-based access control. |
| **Registration API** | `POST /api/summer/register` | JSON or form; CSRF-guarded; returns the reference code. |

### The six tracks (from the flier)
Cybersecurity · Google Workspace · AI & Automations · Coding · Graphics Design · Digital Marketing.

---

## Admin RBAC

Five roles, each mapped to a set of dotted permissions in
[`src/core/Rbac.php`](src/core/Rbac.php):

| Role | Can do |
|------|--------|
| **Super Admin** | Everything, including managing operators and settings. |
| **Administrator** | Registrations, students, courses, content. Not operators. |
| **Registrar** | Own the summer intake — review, confirm, export. |
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
| `MAIL_ENABLED` `MAIL_HOST` `MAIL_PORT` `MAIL_USERNAME` `MAIL_PASSWORD` `MAIL_FROM` `MAIL_INBOX` | SMTP (via PHPMailer in `vendor/`) |
| `AFT_URL` | Canonical base URL (auto-detected if unset) |
| `AFT_DEBUG` | `1` shows full errors while developing |

Email uses PHPMailer if present at `vendor/phpmailer/`; otherwise it soft-fails to
`storage/mail.log` so nothing breaks during setup.

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
                       Ids, Mailer, Validator, Csrf, Security, Database, Helpers
src/models/            AdminUser, Track, Course, Student, Enrollment,
                       SummerRegistration, ContentBlock
src/controllers/       public controllers + Admin/ (namespaced) console
src/views/             layouts, pages, admin, partials, emails
assets/                css (tokens, app, admin), js (app.js), images
database/              schema.sql, seed.sql
scripts/               create-admin.php
```

Security baseline: hardened session cookie (HttpOnly/SameSite/Secure/strict),
CSRF on every mutating route, PDO prepared statements throughout, bcrypt
password hashing, a strict Content-Security-Policy, and a separate auth realm
for operators vs. learners.
