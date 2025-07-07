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
