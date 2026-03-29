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

## Demo Volatile Mode

Set the app to no-database demo mode with the values from .env.example:

- HOTEL_STORAGE_MODE=volatile
- HOTEL_DEMO_VOLATILE=1
- HOTEL_DEMO_COOKIE_MIRROR=0

Behavior in this mode:

- Reservation submit stores data in session only.
- A short-lived signed handoff cookie lets the first admin listing show the new reservation.
- After the first admin listing request, data is consumed and disappears on refresh.

Optional handoff tuning:

- HOTEL_HANDOFF_TTL_SECONDS (default 45)
- HOTEL_HANDOFF_SECRET
- HOTEL_HANDOFF_COOKIE_NAME

