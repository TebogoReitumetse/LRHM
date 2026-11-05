# LivityICT HRMS

Web-based Human Resource Management System for LivityICT, built with Laravel 13, Tailwind CSS 4, Alpine.js and MySQL/MariaDB.

- **Phase 1: Digital Leave Management.** Digital leave form, Employee → Manager → Director workflow, leave register, management dashboard, reports (PDF / Excel CSV / print), absence records, notifications and audit trail.
- **Phase 2: Contract Management.** Contract register, signed-document storage, automatic duration and remaining-time calculation, 90/60/30-day expiry reminders, renewal and extension history.

## Setup

```bash
composer install
npm install && npm run build
cp .env.example .env          # set DB_* and mail settings
php artisan key:generate
php artisan migrate --seed    # reference data + demo data (demo data is skipped in production)
php artisan serve
```

Create the database first, e.g. `CREATE DATABASE livity_hrms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`

Set `HRMS_MYSQLDUMP` and `HRMS_MYSQL` in `.env` to the MySQL client tools (on XAMPP: `C:/xampp/mysql/bin/mysqldump.exe` and `C:/xampp/mysql/bin/mysql.exe`).

### Scheduler (required for reminders and backups)

Run `php artisan schedule:run` every minute (cron on Linux, Task Scheduler on Windows). It runs:

| Task | When | Command |
|---|---|---|
| Contract status update and expiry reminders | Daily 07:00 | `php artisan contracts:monitor` |
| Database backup (gzipped, last 30 kept) | Daily 01:00 | `php artisan hrms:backup` |

Restore: `php artisan hrms:restore hrms-YYYYMMDD-HHMMSS.sql.gz`. Uploaded documents live in `storage/app/private` and must be included in file-level backups.

### Email

`MAIL_MAILER=log` writes emails to `storage/logs/laravel.log`. Configure SMTP in `.env` for real delivery. In-system notifications always work.

## Demo accounts (non-production only)

| Role | Username | Password |
|---|---|---|
| System Administrator | `admin` | `Admin@12345` |
| Director | `director` | `Password@123` |
| Manager | `manager` | `Password@123` |
| Employees | `lerato`, `kabelo`, `ayesha`, `johan`, `nomsa` | `Password@123` |
| Contractors | `tumelo`, `zanele` | `Password@123` |

In production only the `admin` account is created, and its password must be changed at first sign-in.

## Roles

| Role | Access |
|---|---|
| Employee / Contractor | Own leave applications, documents and history; own contract (if enabled on the account). |
| Manager | Reviews leave (comment, recommend, return for information, forward to Director); register, reports, absences; full Contract Management; staff accounts. |
| Director | Final approve/decline decision; register, reports, absences. No contract module access in Phase 2. |
| System Administrator | Users, leave categories, public holidays, notification schedule, backups, system audit. No access to confidential leave or contract records. |

Nobody can act on their own leave application. If the applicant is the only Manager, the application goes straight to the Director.

## Business rules

- Working days exclude weekends and public holidays (maintained under *Public holidays*; SA holidays for 2026 and 2027 are seeded).
- No leave balances or entitlements are calculated until LivityICT approves the rules and opening balances.
- Absence records never trigger warnings, salary deductions or disciplinary action. Only management records an outcome after review.
- Contract reminders are administrative only. Contracts are never renewed, ended or changed automatically. Renewals create a new version and keep the old one in the history.

## Items for management to confirm (FRS §14)

The seeded leave categories, document/reason requirements and declaration wording are provisional, based on the BCEA. Confirm them against the existing paper form and HR policy, then adjust under *Leave categories*. Also confirm contract types (`App\Models\Contract::TYPES`), the reminder schedule, hosting and SMTP details.

## Tests

```bash
php artisan test
```

Tests run against the `livity_hrms_test` MySQL database (see `phpunit.xml`).
