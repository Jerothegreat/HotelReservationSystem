# Portfolio Deployment Guide (Free Hosting)

This guide is for a live portfolio demo (not full production hardening).

## Goal

Show this complete flow publicly:

1. Visitor submits reservation.
2. Reservation appears in Admin list.
3. Admin can edit and delete records.

## 1) Choose Deployment Mode

For this template, you can run in two modes:

- Temporary demo mode (no MySQL required)
- Persistent mode (MySQL required)

Use temporary mode for portfolio demos when you do not need data persistence.

## 2) Temporary Demo Mode (No Database)

Set these environment variables:

- HOTEL_STORAGE_MODE=volatile
- HOTEL_DEMO_VOLATILE=1
- HOTEL_DEMO_COOKIE_MIRROR=0

Expected behavior:

- Reservation and Admin work without MySQL
- Records are temporary by design
- Data can disappear on refresh/redeploy/session reset

Verify using `/healthcheck.php`:

- storage_mode=volatile
- session_storage=1
- database_required=0

## 3) Persistent Mode (MySQL)

Choose a host that supports PHP + MySQL.

Use any free host that provides:

- PHP 8+
- MySQL database
- File upload (File Manager or FTP)
- phpMyAdmin or similar DB panel

## 4) Create Database and User in Hosting Panel

Create one database and one user, then keep these values:

- DB host
- DB port (usually 3306)
- DB name
- DB user
- DB password

## 5) Upload Project Files

Upload the project preserving the current structure.

Public entry points should include:

- index.php
- home.php
- reservation.php
- admin.php

## 6) Configure Database Environment Values

Set these environment variables in your hosting panel (if available):

- HOTEL_DB_HOST
- HOTEL_DB_PORT
- HOTEL_DB_NAME
- HOTEL_DB_USER
- HOTEL_DB_PASS
- HOTEL_DB_CHARSET (optional, default utf8mb4)
- HOTEL_DB_AUTO_CREATE_DATABASE (set to 0 on free hosting)

## Session-Only Mode (No Database Saved)

If you want a pure portfolio demo without persistent database storage, set:

- HOTEL_STORAGE_MODE=session

Optional equivalent flag:

- HOTEL_DEMO_SESSION_ONLY=1

What this does:

- Skips MySQL completely
- Stores reservations in PHP session only
- Data is cleared when session expires or browser session changes

Session-only caveat:

- Admin and Reservation must be tested in the same browser session to see the same records.

Important:

- On shared/free hosting, keep HOTEL_DB_AUTO_CREATE_DATABASE=0
- The database should be created manually in the panel

## 7) First-Run Data Bootstrap

The app will still create required tables and seed room rates via initializeDatabase() inside the selected database.

## 8) End-to-End Demo Test

1. Open reservation page and submit a booking.
2. Open admin page and confirm the record appears.
3. Edit booking data and confirm totals update.
4. Delete the record and confirm it disappears.

## 9) Portfolio Presentation Tips

Add these to your project card:

- Live demo URL
- Short note: "PHP + MySQL reservation flow with Admin CRUD"
- Simple test instruction: "Create a reservation, then open Admin to verify live update"

## Optional Reset Before Presentation

Use phpMyAdmin and run:

```sql
DELETE FROM reservations;
ALTER TABLE reservations AUTO_INCREMENT = 1;
```

This keeps your demo data clean before evaluator reviews.
