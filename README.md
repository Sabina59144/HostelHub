# HostelHub

A hostel management system for managing students, rooms, fees, and maintenance requests. Built with PHP and MySQL, designed to run on XAMPP.

## Features

- **Admin / Staff portal** — manage students, rooms, fee records, and maintenance tickets
- **Student portal** — view room info, fee status, and submit maintenance requests
- **Role-based access** — admin, staff, and student roles with separate dashboards
- **Maintenance module** — submit, assign, and track maintenance tickets with status updates
- **Fee module** — record payments, track dues, apply fines

---

## Prerequisites

- [XAMPP](https://www.apachefriends.org/) (PHP 8.0+ and MySQL/MariaDB)
- Git

---

## Setup

### 1. Clone the repository

Place the project inside XAMPP's `htdocs` directory:

```bash
cd C:/xampp/htdocs
git clone <repo-url> HostelHub
```

### 2. Start XAMPP services

Open the XAMPP Control Panel and start:
- **Apache**
- **MySQL**

### 3. Import the database

**Option A — phpMyAdmin (recommended)**

1. Open your browser and go to `http://localhost/phpmyadmin`
2. Click **Import** in the top navigation
3. Click **Choose File** and select `database/hostelhub.sql`
4. Click **Go**

This creates the `hostelhub` database with all tables and a seeded admin account.

**Option B — MySQL CLI**

```bash
mysql -u root -p < C:/xampp/htdocs/HostelHub/database/hostelhub.sql
```

### 4. Verify database config

The default credentials in `includes/db.php` match a standard XAMPP install:

```
Host:     localhost
Database: hostelhub
Username: root
Password: (empty)
```

If your MySQL setup uses a different username or password, edit `includes/db.php` accordingly.

### 5. Open the app

Visit `http://localhost/HostelHub` in your browser.

---

## Default Login Credentials

| Role  | Username | Password |
|-------|----------|----------|
| Admin | `admin`  | `password` |

> After logging in as admin, you can create staff accounts and add students from the dashboard.

**Student login** uses the student number (set when adding a student) and the password assigned during registration. The student portal is at `http://localhost/HostelHub/student_user/`.

---

## Project Structure

```
HostelHub/
├── api/                  # REST-style API endpoints (auth, maintenance)
├── css/                  # Stylesheets
├── database/             # SQL schema and seed files
│   ├── hostelhub.sql     # Full database dump (use this to import)
│   └── schema.sql        # Schema-only (no seed data)
├── Fee module/           # Fee management pages
├── html/                 # Module HTML views (maintenance, etc.)
├── includes/             # Shared PHP helpers (db.php, session.php, navbar)
├── js/                   # JavaScript files
├── Room module/          # Room management pages
├── Student module/       # Student management pages
├── student_user/         # Student-facing portal
├── User module/          # Staff/admin user management
├── dashboard.php         # Admin/staff dashboard
├── login.php             # Admin/staff login
└── student_dashboard.php # Student dashboard
```

---

## Troubleshooting

**Blank page or "Database connection failed"**
- Confirm Apache and MySQL are running in XAMPP
- Check that the `hostelhub` database was imported successfully
- Verify credentials in `includes/db.php`

**404 on any page**
- Ensure the folder is named exactly `HostelHub` inside `htdocs`
- URL should be `http://localhost/HostelHub/`

**Login not working after fresh import**
- All seeded accounts use the password `password`
- Make sure you imported `database/hostelhub.sql`, not just `schema.sql`
