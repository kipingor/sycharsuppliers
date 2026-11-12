# Sychar Water Billing System

A modern web application for managing water usage, billing, payments, and customer statements for residential estates and water providers.

## ✨ Features

- 🔢 **Meter Management** – Register, edit, and monitor individual meters
- 👥 **Resident Management** – Link meters to residents, add new residents dynamically
- 💧 **Billing Engine** – Generate bills based on readings and price per unit
- 💰 **Payment Tracking** – Record, list, and reconcile payments
- 🧾 **PDF Statement Generation** – Send PDF statements via email (with carry-forward balances)
- 📨 **Email Notifications** – Bill reminders, payment receipts, overdue alerts
- 🛡 **Role-Based Access Control** – Admin, Accountant, Field Officer roles (Spatie Laravel Permission)
- 📊 **Dashboard & Reporting** – Compare billed vs extracted water, download billing reports
- ⚙️ **Offline Meter Reading API** – Support for mobile apps
- 📤 **Send Statements with Attachments** – Automatically email detailed PDF statements
- ⚙️ **System Settings** – Admin-managed email, bank, M-Pesa, company, and billing configuration
- 💬 **Staff Communications** – Persistent notifications, acknowledged priority messages, announcements, and internal chat

### System settings and staff communications

Admins (or users with the Spatie `manage-settings` permission) can configure system-wide
email delivery, multiple bank accounts, M-Pesa credentials, and company/billing defaults
under **Settings → System**. Secrets are encrypted in the database, are never included in
the settings page response, and are preserved when their password fields are left blank.
M-Pesa settings also configure the existing STK Push flow; the connection test checks the
Safaricom OAuth endpoint. The email test sends a message to the supplied recipient.

The authenticated communication dock polls for unread database notifications, priority
messages, announcements, and operational counts every 30 seconds. Chat refreshes every
5 seconds. Critical and high-priority messages remain overlaid until the recipient
acknowledges them. No WebSocket service is required. The compact floating icon dock stays
clear of the sidebar's account/navigation controls. Chat opens as a Meet-style floating
panel with blue outgoing bubbles and neutral incoming bubbles; on mobile it resizes to
the viewport instead of covering the full-width footer.

### Historical consumption rate review

To export billing details and compare each recorded unit rate against candidate rates
of KES 200, 250, and 300 without modifying bills or payments, run:

```bash
php artisan billing:historical-rate-review
```

The CSV is saved under `storage/app/reports/`. A custom filename can be supplied with
`--output=review.csv`. Candidate differences are usage-only estimates; taxes and fixed
charges are not recalculated. All non-voided records remain marked as unverified until
the applicable rate dates are supported by evidence and approved. Do not post the
candidate differences as adjustments directly; preserve existing bills and payment
allocations and record any approved correction through the accounting adjustment
process. The report includes bills without detail rows for investigation. Since each row
represents a billing detail, bill-level totals (including allocated payments and applied
credit notes) repeat when a bill has multiple details. Payment totals include completed,
non-deleted allocated payments and completed direct bill payments without counting the
same payment twice; pending, failed, and deleted payments are excluded.

After confirming the approved historical rates in the CSV, preview their effect with:

```bash
php artisan billing:apply-historical-rates /path/to/approved-rates.csv
```

Only CSV rows with a KES 200 or KES 250 `recorded_unit_rate` are eligible. Review the
preview, then explicitly apply with a documented reason:

```bash
php artisan billing:apply-historical-rates /path/to/approved-rates.csv \
  --apply \
  --reason="Approved historical rate correction from reviewed schedule"
```

The command asks for confirmation, skips voided/written-off bills and zero or negative
consumption, and updates the billing detail rate/amount, bill total/open balance, and
settlement status in one transaction. The open balance is adjusted by the change in bill
total for unsettled bills, preserving the existing recorded balance. Bills already
marked paid remain paid and their balances are not recalculated from potentially
incomplete historical payment links. Payment-history discrepancies are not reconciled
by this command. It preserves payments and allocations, and writes the corrections and
reason to the audit trail. Re-run the review export afterward.
Bill details retain their rate as a snapshot; statements use the stored detail rate and
bill total, so future tariff updates apply to newly generated bills rather than rewriting
historical bills. Previously issued statement PDFs are not rewritten; regenerate them to
show corrected values.

## 🚀 Tech Stack

- **Backend**: Laravel 12
- **Frontend**: Inertia.js + React
- **Styling**: Tailwind CSS 4
- **PDF Generation**: Laravel DomPDF
- **Authentication**: Laravel Breeze (Inertia stack)
- **Role Management**: Spatie Laravel Permission
- **Email**: Laravel Mailables + Notifications
- **Database**: MariaDB

## 🛠 Installation

### Requirements

- PHP 8.2+
- Composer
- Node.js & npm
- MySQL or MariaDB
- Redis (recommended)
