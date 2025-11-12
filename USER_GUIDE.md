# HoldMyProduct - User Guide

Welcome to HoldMyProduct! This comprehensive guide will help you set up and use the plugin to enable product reservations in your WooCommerce store.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Initial Setup](#initial-setup)
3. [Configuring Settings](#configuring-settings)
4. [Enabling Product Reservations](#enabling-product-reservations)
5. [Customer Experience](#customer-experience)
6. [Managing Reservations](#managing-reservations)
7. [Email Notifications](#email-notifications)
8. [Admin Approval Workflow](#admin-approval-workflow)
9. [Troubleshooting](#troubleshooting)
10. [Best Practices](#best-practices)

---

## Getting Started

### Prerequisites

Before installing HoldMyProduct, ensure you have:

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- Active WooCommerce store with products

### Installation

1. **Via WordPress Admin:**
   - Navigate to **Plugins > Add New**
   - Search for "HoldMyProduct"
   - Click **Install Now**, then **Activate**

2. **Manual Installation:**
   - Download the plugin ZIP file
   - Go to **Plugins > Add New > Upload Plugin**
   - Upload the ZIP file and click **Install Now**
   - Click **Activate Plugin**

---

## Initial Setup

After activation, follow these steps:

### 1. Access Plugin Settings

Navigate to **HoldMyProduct > Settings** in your WordPress admin dashboard.

### 2. Enable Reservations Globally

Toggle **Enable Reservation** to ON. This activates the reservation system site-wide.

### 3. Configure Basic Settings

Set the following initial parameters:

- **Max Reservations Per User:** Default is 1 (recommended to start)
- **Reservation Duration:** Default is 24 hours
- **Email Notifications:** Enable if you want automated emails

---

## Configuring Settings

### General Settings Tab

#### Enable Reservation
- **Purpose:** Master switch for the entire reservation system
- **When OFF:** No products can be reserved, even if individually enabled
- **When ON:** Products with reservations enabled will show the Reserve button

#### Max Reservations Per User
- **Range:** 1 or more
- **Purpose:** Limit how many products a user can reserve simultaneously
- **Use Case:** Set to 1 for exclusive items, higher for flexible shopping
- **Example:** Setting to 3 allows users to reserve up to 3 different products at once

#### Reservation Duration (hours)
- **Range:** 1-168 hours (1 hour to 1 week)
- **Purpose:** How long products remain reserved before automatic expiration
- **Recommended:**
  - 24 hours: Standard products
  - 48-72 hours: Custom or consultation-required items
  - 1 week: Pre-orders or special orders

#### Enable Email Notifications
- **Purpose:** Send automated emails for reservation events
- **Events Covered:**
  - Reservation created
  - Reservation expiring soon
  - Reservation expired
  - Reservation approved/denied (if approval required)

#### Require Admin Approval for Reservations
- **Purpose:** Manually approve each reservation before it becomes active
- **Use Cases:**
  - High-value items
  - Limited stock products
  - Custom order verification
  - Fraud prevention
- **Workflow:** Reservations stay in "Pending Approval" until you approve/deny them

### Pop-up Customization Tab

Customize the reservation modal appearance:

- **Modal Title:** Default: "Reserve this Product"
- **Button Text:** Default: "Confirm Reservation"
- **Colors:** Customize to match your brand
- **Border Radius:** Adjust corner roundness
- **Font Settings:** Typography customization

---

## Enabling Product Reservations

Reservations are controlled per product:

### For Individual Products

1. Go to **Products** and edit any product
2. Scroll to **Product Data > Inventory** tab
3. Enable **Stock Management** (required)
4. Set stock quantity
5. Check **Enable reservations** checkbox
6. Click **Update**

### Requirements

- ✅ Product must have **stock management enabled**
- ✅ Product must have **stock quantity** set
- ✅ Global reservations must be **enabled in plugin settings**

### Product Types Supported

- **Simple Products:** ✅ Fully supported
- **Variable Products:** ⚠️ Coming in future update
- **Grouped Products:** ❌ Not supported
- **External Products:** ❌ Not supported

---

## Customer Experience

### Reservation Flow

1. **Product Page:**
   - Customer views enabled product
   - Sees "Reserve Product" button next to Add to Cart
   - Button only visible to logged-in users

2. **Clicking Reserve:**
   - Modal popup appears
   - Shows reservation terms and duration
   - Customer clicks "Confirm Reservation"

3. **Confirmation:**
   - Success message displayed
   - Stock quantity decreases by 1
   - Email confirmation sent (if enabled)
   - Reservation added to My Account

4. **My Account Page:**
   - Navigate to **My Account > Reserved products**
   - View all active reservations
   - See expiration countdown
   - Quick "Add to Cart" button
   - Option to cancel reservation

5. **Completing Purchase:**
   - Customer adds reserved product to cart
   - Completes checkout normally
   - Reservation marked as fulfilled
   - Stock remains adjusted

6. **Expiration:**
   - If time runs out, stock automatically restores
   - Customer receives expiration notification
   - Reservation removed from My Account

---

## Managing Reservations

### Admin Dashboard

Navigate to **HoldMyProduct > Reservations** for comprehensive management.

#### View Reservations

**Columns Displayed:**
- Product Name (linked to edit page)
- Customer (name and email)
- Status (Active, Expired, Cancelled, Fulfilled, Pending Approval, Denied)
- Reserved Date
- Expires Date
- Time Left (color-coded urgency)
- Actions (Cancel, Delete, Approve, Deny)

#### Filter Reservations

Use the filter dropdown to show:
- **All:** Every reservation regardless of status
- **Active:** Currently valid reservations
- **Expired:** Time ran out, stock restored
- **Cancelled:** User or admin cancelled
- **Fulfilled:** Purchase completed
- **Pending Approval:** Awaiting admin decision
- **Denied:** Admin rejected

#### Search Functionality

Search by:
- Customer email
- Customer name
- Product name

#### Reservation Actions

**Cancel:**
- Immediately expires the reservation
- Restores product stock
- Sends cancellation notification to customer
- Use when: Customer requests cancellation or stock needed urgently

**Delete:**
- Permanently removes reservation record
- Only available for non-active reservations
- Use when: Cleaning up old expired/cancelled records

**Approve:**
- Only for "Pending Approval" reservations
- Activates the reservation
- Starts expiration countdown
- Sends approval email

**Deny:**
- Only for "Pending Approval" reservations
- Rejects the reservation request
- Restores stock immediately
- Sends denial email with optional reason

### Product-Level Reservations

View reservations for specific products:

1. Edit any product
2. Go to **Product Data > Inventory** tab
3. Scroll to **Active Reservations** section
4. See all current reservations for this product
5. Cancel individual reservations if needed

---

## Email Notifications

### Email Types

1. **Reservation Created**
   - Sent when: User successfully creates reservation
   - Includes: Product details, expiration time, next steps

2. **Reservation Expiring Soon**
   - Sent when: 2 hours before expiration
   - Includes: Urgent reminder, quick purchase link

3. **Reservation Expired**
   - Sent when: Reservation time runs out
   - Includes: Apology, invitation to reserve again

4. **Reservation Approved**
   - Sent when: Admin approves pending reservation
   - Includes: Confirmation, expiration time, purchase link

5. **Reservation Denied**
   - Sent when: Admin denies reservation
   - Includes: Denial reason (if provided), alternative suggestions

### Customizing Emails

Email templates are located in:
```
wp-content/plugins/HoldMyProduct/templates/emails/
```

To customize:
1. Copy template to your theme:
   ```
   your-theme/holdmyproduct/emails/[template-name].php
   ```
2. Edit as needed
3. Changes will be preserved during plugin updates

---

## Admin Approval Workflow

When "Require Admin Approval" is enabled:

### Customer Perspective

1. Customer creates reservation
2. Receives "pending approval" notification
3. Stock is reserved but reservation not active yet
4. Waits for admin decision
5. Receives approval/denial email

### Admin Process

1. Go to **HoldMyProduct > Reservations**
2. Filter by **Pending Approval**
3. Review each request:
   - Check customer history
   - Verify stock availability
   - Assess request legitimacy

4. Take action:
   - **Approve:** Click Approve button
   - **Deny:** Click Deny, optionally add reason

### Use Cases

- High-value luxury items
- Limited edition products
- Custom manufacturing orders
- Fraud prevention for new customers
- Managing overwhelming demand

---

## Troubleshooting

### Reserve Button Not Showing

**Possible Causes:**
1. Global reservations disabled in settings
2. Product reservations not enabled
3. Product stock management disabled
4. No stock available
5. User not logged in
6. Theme compatibility issue

**Solutions:**
- Verify all settings enabled
- Check product inventory settings
- Clear cache (if using caching plugin)
- Test with default WordPress theme

### Reservations Not Expiring

**Possible Causes:**
1. WordPress cron not running
2. Server cron disabled

**Solutions:**
- Install WP Crontrol plugin to verify cron
- Contact hosting provider about cron jobs
- Check for cron-blocking plugins

### Email Not Sending

**Possible Causes:**
1. Email notifications disabled in settings
2. WordPress mail function issues
3. Server email restrictions

**Solutions:**
- Verify email settings enabled
- Install WP Mail SMTP plugin
- Check spam folders
- Test with default WordPress email

### Stock Not Restoring

**Possible Causes:**
1. Manual stock adjustments during reservation
2. Database sync issues
3. Plugin conflict

**Solutions:**
- Avoid manual stock changes for reserved products
- Deactivate other inventory plugins temporarily
- Check error logs for conflicts

---

## Best Practices

### Setting Duration

- **Quick Turnaround Products:** 12-24 hours
- **Standard Items:** 24-48 hours
- **Custom Orders:** 48-72 hours
- **Pre-orders:** 1 week

### Managing Limits

- **High Demand:** Set limit to 1 reservation per user
- **Normal Stock:** 2-3 reservations okay
- **Bulk Items:** Higher limits acceptable

### Customer Communication

- Set clear expiration times in product descriptions
- Explain reservation benefits
- Add urgency messaging: "Reserved items expire in [X] hours"
- Send reminder emails before expiration

### Stock Planning

- Keep extra stock buffer for walk-in customers
- Don't enable reservations for last few units
- Monitor reservation patterns to adjust stock

### Admin Approval

- Respond to pending reservations within 2-4 hours
- Set auto-approval for trusted customers
- Create approval criteria checklist
- Communicate denial reasons clearly

### Performance

- Regularly clean up old expired/cancelled reservations
- Monitor reservation patterns
- Adjust duration based on conversion rates

---

## Support

Need help? Here's how to get support:

1. **Documentation:** Re-read this guide
2. **FAQ:** Check readme.txt file
3. **WordPress Forum:** [Plugin Support Forum](https://wordpress.org/support/plugin/hold-my-product/)
4. **GitHub Issues:** [Report bugs](https://github.com/Flavius-Ciortan/HoldMyProduct/issues)

---

## Feature Requests

Have an idea? We'd love to hear it!

- Submit on [GitHub](https://github.com/Flavius-Ciortan/HoldMyProduct/issues)
- Include detailed use case
- Explain expected behavior
- Note any similar plugins/features

---

## Version Information

Current Version: 1.0.0
Last Updated: November 12, 2025
WordPress Compatibility: 5.8+
WooCommerce Compatibility: 5.0+
PHP Compatibility: 7.4+

---

Thank you for using HoldMyProduct! We're committed to helping you provide the best reservation experience for your customers.
