# STC Product Enquiry

A production-ready WordPress plugin for WooCommerce that replaces every **Add to Cart** / **Add to Quote** button with an **Enquire Now** button. Clicking it opens an AJAX popup where customers submit their Name and Mobile Number. Enquiries are emailed to the site admin and stored in a custom database table with an admin dashboard (search, filter, delete, CSV export).

## Features

- Replaces Add to Cart / Add to Quote with a single **Enquire Now** button across:
  - WooCommerce loops, archives, category and search pages, single product pages
  - Elementor (free), Essential Addons for Elementor, EA Woo Product Gallery
  - Custom WooCommerce loops (via an output-buffer catch-all layer)
- AJAX enquiry popup (no page reload) with hidden Product ID / Name / SKU / URL fields
- Automatic product detection from the WooCommerce product object, builder widgets, or the closest product container in the DOM
- HTML email notification to the configured admin address
- Custom table `wp_stc_product_enquiries`
- Admin dashboard: ID, Product Name, SKU, Customer Name, Mobile, Date — with search, date filtering, single/bulk delete and CSV export
- Settings page to manage the notification email and button label
- Security: nonce validation, sanitization, capability checks, honeypot anti-spam, prepared SQL statements
- HPOS (High-Performance Order Storage) compatible

## Requirements

- WordPress 6.8+
- WooCommerce 10.x (tested up to 10.8)
- PHP 8.1+

## Installation

1. Download this repository as a ZIP (or use a release ZIP).
2. In WordPress, go to **Plugins → Add New → Upload Plugin** and upload the ZIP.
3. Click **Install Now**, then **Activate**. WooCommerce must be active.
4. On activation, the `wp_stc_product_enquiries` table is created and a default notification email is set.

## Usage

- **Product Enquiries** (admin menu): view, search, filter, delete and export enquiries to CSV.
- **Product Enquiries → Settings**: change the notification email address and the button label.

## Developer Filters

| Filter | Purpose |
|--------|---------|
| `stc_pe_button_label` | Override the Enquire Now button text |
| `stc_pe_notification_email` | Override the notification recipient |
| `stc_pe_email_subject` | Override the email subject |
| `stc_pe_email_headers` | Modify the email headers |
| `stc_pe_enable_buffer` | Toggle the HTML output-buffer button-rewrite layer |
| `stc_pe_popup_heading` / `stc_pe_popup_subtext` / `stc_pe_popup_submit_label` | Customize popup copy |

## Actions

| Action | Purpose |
|--------|---------|
| `stc_pe_enquiry_saved` | Fires after an enquiry is stored (`$enquiry_id`, `$record`) |

## File Structure

```
stc-product-enquiry/
├── stc-product-enquiry.php   # Bootstrap, activation/uninstall, HPOS compat
├── uninstall.php             # Removes table + options (multisite aware)
├── assets/
│   ├── css/frontend.css
│   └── js/frontend.js
└── includes/
    ├── class-database.php
    ├── class-frontend.php
    ├── class-popup.php
    ├── class-email.php
    ├── class-ajax.php
    └── class-admin.php
```

## License

GPL-2.0-or-later
