# HoldThisProduct Rename Checklist (Completed)

This codebase has been updated to use the **HoldThisProduct / Hold This Product** naming consistently across code, assets, and documentation.

## Status

- [x] All rename steps completed

## Final Identifiers

- Plugin display name: `Hold This Product`
- Main plugin file: `HoldThisProduct.php`
- Main plugin class: `HoldThisProduct`
- Prefix: `HTP` / `htp`
- Constants: `HTP_PLUGIN_URL`, `HTP_PLUGIN_PATH`, `HTP_VERSION`
- Text domain: `hold-this-product`
- Option name: `holdthisproduct_options`
- Settings group: `holdthisproduct_options_group`
- Admin page slugs: `holdthisproduct-settings`, `holdthisproduct-manage-reservations`, `holdthisproduct-analytics`
- Frontend assets:
  - Script: `assets/js/holdthisproduct.js`
  - Style: `assets/css/style.css`
  - Localized JS object: `holdthisproduct_ajax`
  - AJAX action: `holdthisproduct_reserve`
  - Nonce: `holdthisproduct_nonce`
- Reservations model:
  - CPT: `htp_reservation`
  - Meta keys: `_htp_*`
  - My Account endpoint: `htp-reservations`
- Admin AJAX:
  - Actions: `htp_cancel_admin_reservation`, `htp_delete_admin_reservation`, `htp_approve_reservation`, `htp_deny_reservation`
  - Nonces: `htp_admin_cancel`, `htp_admin_delete`, `htp_admin_approve`, `htp_admin_deny`
- Branding assets:
  - `assets/images/HTP-menu-icon.png`
  - `assets/images/HTP Logo.png`

## Verification Commands

```bash
# PHP lint
php -l HoldThisProduct.php
php -l includes/class-htp-reservations.php
php -l includes/class-htp-email-manager.php
php -l includes/class-htp-shortcodes.php
php -l includes/frontend/class-htp-frontend.php
php -l includes/admin/class-htp-admin.php
php -l includes/admin/class-htp-analytics.php

# JS syntax
node -c assets/js/holdthisproduct.js
node -c assets/js/htp-res-toggle.js
```

