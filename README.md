# Hotel Reservation System Template

This project was reorganized to be easier to maintain and present as a sample template.

## Current Architecture

```
HotelReservationSystemPintonSalve/
├── index.php                  # Default entry point
├── home.php                   # Route wrapper -> app/pages/home.php
├── profile.php                # Route wrapper -> app/pages/profile.php
├── contacts.php               # Route wrapper -> app/pages/contacts.php
├── reservation.php            # Route wrapper -> app/pages/reservation.php
├── admin.php                  # Route wrapper -> app/pages/admin.php
├── db.php                     # Compatibility wrapper -> app/config/db.php
├── header.php                 # Compatibility wrapper -> app/includes/header.php
├── footer.php                 # Compatibility wrapper -> app/includes/footer.php
├── app/
│   ├── config/
│   │   └── db.php             # Database connection and reservation data access
│   ├── includes/
│   │   ├── header.php         # Shared top layout and navigation
│   │   └── footer.php         # Shared footer and scripts output
│   └── pages/
│       ├── home.php
│       ├── profile.php
│       ├── contacts.php
│       ├── reservation.php
│       └── admin.php
├── assets/
│   ├── css/
│   │   └── style.css          # Global stylesheet
│   └── img/                   # Static images
└── legacy/
    ├── billing.html
    ├── contacts.html
    ├── home.html
    ├── profile.html
    └── reservation.html
```

## Why This Structure Is Better

- Separation of concerns: page views, shared includes, DB logic, and assets are now separated.
- Safer includes: app pages now use absolute include paths based on `__DIR__`.
- Backward compatibility: old root page URLs (`home.php`, `admin.php`, etc.) still work.
- Presentation-ready: folders clearly communicate architecture to reviewers and panelists.

## Run Locally in XAMPP

1. Put the folder under `htdocs`.
2. Start Apache and MySQL.
3. Open `http://localhost/Elective2/elective2/HotelReservationSystemPintonSalve/`.

## Portfolio Demo Deployment (Free Hosting)

Use the step-by-step guide in `DEPLOY_PORTFOLIO.md`.

Database config now supports environment values:

- `HOTEL_DB_HOST`
- `HOTEL_DB_PORT`
- `HOTEL_DB_NAME`
- `HOTEL_DB_USER`
- `HOTEL_DB_PASS`
- `HOTEL_DB_CHARSET` (optional)
- `HOTEL_DB_AUTO_CREATE_DATABASE` (`0` on shared/free hosting, optional `1` for local root-based setup)
- `HOTEL_STORAGE_MODE` (`mysql` default, use `session` for no-persistence demo mode)
- `HOTEL_DEMO_SESSION_ONLY` (optional boolean toggle for session mode)

## Suggested Next Improvements

- Add environment-based DB config (`.env`) and remove hard-coded credentials.
- Create `app/services/ReservationService.php` to move business logic out of page files.
- Add `public/` web root and move route wrappers there when deploying to production hosting.
- Add basic automated tests for billing and reservation validation logic.

## Deployment

### Railway (Live)
1. Push this repo to GitHub (in a different github account)
2. Go to railway.app -> New Project -> Deploy from GitHub repo
3. Select this repository
4. Railway auto-detects the Dockerfile and builds
5. After deploy: go to Settings -> Networking -> Generate Domain
6. Visit your live URL

### Local (XAMPP)
1. Copy project to C:/xampp/htdocs/hotel/
2. Start Apache in XAMPP Control Panel
3. Visit http://localhost/hotel/home.php

### Session Note
This project uses PHP sessions (no database required).
Sessions reset when the browser tab is closed - this is by design for demo purposes.
