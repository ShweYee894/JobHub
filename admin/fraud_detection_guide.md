# Fraud Detection System — Testing & Usage Guide

## Table of Contents
1. [System Overview](#system-overview)
2. [File Structure](#file-structure)
3. [Prerequisites](#prerequisites)
4. [Step-by-Step Testing](#step-by-step-testing)
5. [API Reference](#api-reference)
6. [Score Calculation Rules](#score-calculation-rules)
7. [Admin Actions Reference](#admin-actions-reference)
8. [Troubleshooting](#troubleshooting)

---

## System Overview

The Fraud Detection module monitors user behavior on the JobHub platform, calculates risk scores based on suspicious activity patterns, and provides admin tools to flag, unflag, or suspend users.

### How It Works

1. **Behavior Logging** — User actions (login attempts, proposals, spam, etc.) are logged to the `user_behavior_logs` table via the API.
2. **Score Calculation** — The API analyzes logged behavior and computes a fraud score (0–100) based on weighted rules.
3. **Auto-Flagging** — Users with a score ≥ 70 are automatically flagged.
4. **Admin Dashboard** — Admins review suspicious users, view activity logs, and take manual action (flag, unflag, suspend).
5. **Auto-Refresh** — The dashboard refreshes suspicious user data every 30 seconds via AJAX.

---

## File Structure

| File | Purpose |
|------|---------|
| `admin/fraud_detection.php` | Main dashboard page — overview cards, suspicious users table, activity logs |
| `admin/fraud_action.php` | POST-only endpoint for flag/unflag/suspend actions |
| `api/fraud_api.php` | REST API for logging actions, calculating scores, listing suspicious users |
| `assets/js/fraud.js` | Frontend — AJAX score refresh, user activity modal, auto-refresh, toast notifications |

---

## Prerequisites

1. Logged in as **admin** (role = `admin`)
2. Database tables exist:
   - `users` (must have `fraud_score` and `status` columns)
   - `user_behavior_logs` (columns: `id`, `user_id`, `action_type`, `ip_address`, `payload`, `created_at`)
3. PHP session is active

---

## Step-by-Step Testing

### Step 1: Log In as Admin

Navigate to:
```
http://localhost/finalproject/auth/login.php
```

Log in with an admin account. The fraud dashboard requires `require_role('admin')`.

---

### Step 2: Insert Test Behavior Logs

Open **phpMyAdmin** or a MySQL client. Replace `USER_ID` with an actual user ID from your `users` table.

#### 2a. Simulate Rapid Actions (10+ actions in 1 minute)
```sql
INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
VALUES
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW()),
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW()),
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW()),
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW()),
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW()),
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW()),
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW()),
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW()),
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW()),
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW()),
(USER_ID, 'proposal_submit', '192.168.1.1', '{}', NOW());
```
**Expected score impact:** +20 points

#### 2b. Simulate Multiple IP Addresses
```sql
INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
VALUES
(USER_ID, 'login_failed', '10.0.0.5', '{}', NOW()),
(USER_ID, 'spam', '10.0.0.99', '{}', NOW());
```
**Expected score impact:** +15 points (3 unique IPs in 24h)

#### 2c. Simulate Flagged Actions
```sql
INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
VALUES
(USER_ID, 'spam', '192.168.1.1', '{"content":"buy cheap stuff"}', NOW()),
(USER_ID, 'phishing', '192.168.1.1', '{"target":"login page"}', NOW()),
(USER_ID, 'fake_review', '192.168.1.1', '{"rating":5}', NOW());
```
**Expected score impact:** +30 points (10 per flagged action type)

#### 2d. Simulate Failed Login Attempts
```sql
INSERT INTO user_behavior_logs (user_id, action_type, ip_address, payload, created_at)
VALUES
(USER_ID, 'login_failed', '192.168.1.1', '{}', NOW()),
(USER_ID, 'login_failed', '192.168.1.1', '{}', NOW()),
(USER_ID, 'login_failed', '192.168.1.1', '{}', NOW());
```
**Expected score impact:** +30 points (10 per failed login, max 30)

---

### Step 3: Calculate Fraud Score via API

Open in browser:
```
http://localhost/finalproject/api/fraud_api.php?action=calculate_score&user_id=USER_ID
```

**Expected JSON response:**
```json
{
  "success": true,
  "user_id": 5,
  "score": 95,
  "reasons": [
    "Rapid actions: 11 actions in the last minute (+20)",
    "Multiple IPs: 3 unique IPs in 24h (+15)",
    "Failed logins: 3 in 24h (+30)",
    "Flagged action 'spam': 1 occurrences (+10)",
    "Flagged action 'phishing': 1 occurrences (+10)",
    "Flagged action 'fake_review': 1 occurrences (+10)"
  ]
}
```

If score ≥ 70, the user is **auto-flagged** (`status` → `flagged`).

---

### Step 4: View the Fraud Dashboard

Navigate to:
```
http://localhost/finalproject/admin/fraud_detection.php
```

You will see:
- **Overview Cards** — Flagged users, High Risk (70+), Medium Risk (40–69), Suspicious Actions (24h)
- **Suspicious Users Table** — Users with `fraud_score ≥ 50` or `status = flagged`
- **Activity Log** — All behavior log entries with filtering and pagination

---

### Step 5: Test Admin Actions

#### 5a. Flag a User
1. Find a user with status **active** in the suspicious users table
2. Click the **yellow flag** icon
3. Confirm the dialog
4. User status changes to `flagged`
5. Success toast appears

#### 5b. Unflag a User
1. Find a user with status **flagged**
2. Click the **green check-circle** icon
3. Confirm the dialog
4. Status resets to `active`, fraud score resets to `0`

#### 5c. Suspend a User
1. Find a user with status **active** or **flagged**
2. Click the **red ban** icon
3. Confirm the dialog
4. User status changes to `suspended`
5. Suspended users cannot log in

#### 5d. View User Activity Modal
1. Click the **blue eye** icon on any user
2. Modal opens showing that user's full activity log
3. Displays timestamps, action types, IP addresses, and payload details
4. Click outside the modal or press **Escape** to close

---

### Step 6: Test Recalculate Scores (AJAX Refresh)

1. Click the **"Recalculate All Scores"** button at the top of the dashboard
2. The button shows a spinning icon during the request
3. This calls `?action=recalculate_all` which recalculates fraud scores for **all users** with behavior logs
4. Then it fetches the updated suspicious users list
5. The table updates and a toast shows: "Recalculated X users. Y auto-flagged."

---

### Step 7: Test Activity Log Filters

1. Use the **Action Type** dropdown to filter by a specific action (e.g., `spam`, `login_failed`)
2. Use the **Date** picker to filter by date
3. Click **Filter** to apply
4. Click **Clear** to reset all filters
5. Pagination updates based on filtered results

---

### Step 8: Test Auto-Refresh

The dashboard auto-refreshes the suspicious users table every **30 seconds**.

To verify:
1. Open browser **DevTools** (F12)
2. Go to the **Network** tab
3. Wait 30 seconds
4. You should see a request to `fraud_api.php?action=suspicious`
5. The table updates automatically if new suspicious users appear

---

### Step 9: Test Log API via URL

Simulate logging a new action directly:
```
http://localhost/finalproject/api/fraud_api.php?action=log&action_type=spam&payload={"test":"data"}
```

**Expected response:**
```json
{"success": true, "message": "Action logged.", "log_id": 42}
```

Then recalculate the score (Step 3) to see the impact.

---

## API Reference

### `GET /api/fraud_api.php?action=log`

Log a user behavior action.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `user_id` | int | No* | User ID (defaults to logged-in user) |
| `action_type` | string | Yes | Action type (see list below) |
| `payload` | JSON string | No | Additional context data |

**Action types:** `login_failed`, `spam`, `phishing`, `fake_review`, `payment_fraud`, `account_takeover`, `suspicious_download`, `proposal_submit`

**Example:**
```
?action=log&action_type=spam&payload={"content":"suspicious link"}
```

---

### `GET /api/fraud_api.php?action=recalculate_all`

Batch recalculate fraud scores for all users who have behavior logs. Auto-flags users with score ≥ 70.

**Response:**
```json
{
  "success": true,
  "message": "Recalculated 15 users. 2 auto-flagged.",
  "updated": 15,
  "flagged": 2
}
```

---

### `GET /api/fraud_api.php?action=calculate_score&user_id=X`

Calculate and update fraud score for a specific user. Returns score breakdown.

**Response:**
```json
{
  "success": true,
  "user_id": 5,
  "score": 75,
  "reasons": ["Rapid actions: 11 actions in the last minute (+20)", "..."]
}
```

---

### `GET /api/fraud_api.php?action=suspicious`

List all suspicious users (score ≥ 50 or status = flagged). Used by dashboard AJAX refresh.

**Response:**
```json
{
  "success": true,
  "users": [
    {
      "id": 5,
      "name": "John Doe",
      "email": "john@example.com",
      "role": "freelancer",
      "status": "flagged",
      "fraud_score": 75,
      "unique_ips_24h": 3,
      "last_action": "spam",
      "last_action_time": "2026-07-12 10:30:00"
    }
  ]
}
```

---

### `GET /api/fraud_api.php?action=activity_log&user_id=X`

Get paginated activity logs for a specific user.

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `user_id` | int | No | Filter by user ID |
| `action_type` | string | No | Filter by action type |
| `date_from` | date | No | Start date (YYYY-MM-DD) |
| `date_to` | date | No | End date (YYYY-MM-DD) |
| `page` | int | No | Page number (default: 1) |

---

### `POST /admin/fraud_action.php`

Admin action endpoint (requires CSRF token).

| Field | Values | Description |
|-------|--------|-------------|
| `action` | `flag_user` | Set user status to `flagged` |
| `action` | `unflag_user` | Reset status to `active`, score to `0` |
| `action` | `suspend_user` | Set user status to `suspended` |
| `user_id` | int | Target user ID |
| `csrf_token` | string | CSRF token (auto-included in forms) |

---

## Score Calculation Rules

| Condition | Points | Details |
|-----------|--------|---------|
| Rapid actions | +20 | More than 10 actions in the last 1 minute |
| Multiple IPs | +15 | More than 1 unique IP address in 24 hours |
| Rapid proposals | +25 | More than 5 `proposal_submit` actions in 1 hour |
| Failed logins | +10 each (max 30) | `login_failed` actions in 24 hours |
| Flagged actions | +10 each | Each occurrence of: `spam`, `phishing`, `fake_review`, `payment_fraud`, `account_takeover`, `suspicious_download` in 7 days |
| **Score cap** | **100** | Maximum possible score |
| **Auto-flag** | — | Score ≥ 70 automatically sets status to `flagged` |

---

## Risk Levels

| Score Range | Risk Level | Visual Indicator |
|-------------|-----------|------------------|
| 0–30 | Low | Green bar and text |
| 31–60 | Medium | Yellow bar and text |
| 61–100 | High | Red bar and text |

---

## Admin Actions Reference

| Action | Button | Effect | Reversible? |
|--------|--------|--------|-------------|
| **Flag User** | Yellow flag icon | Status → `flagged` | Yes (unflag) |
| **Unflag User** | Green check-circle icon | Status → `active`, score → `0` | Yes (flag again) |
| **Suspend User** | Red ban icon | Status → `suspended` (user cannot log in) | No (manual DB edit needed) |
| **View Activity** | Blue eye icon | Opens modal with full activity log | — |
| **Recalculate Scores** | Top-right button | AJAX refresh of all suspicious user data | — |

---

## Troubleshooting

### Dashboard shows "No suspicious users found"
- No users have `fraud_score ≥ 50` or `status = flagged`
- Insert test data (Step 2) and calculate scores (Step 3)

### Score calculation returns 0
- No behavior logs exist for the user within the time windows
- Check `user_behavior_logs` table has entries for the target `user_id`

### Activity modal shows "No activity logs found"
- The user has no entries in `user_behavior_logs`
- Log some actions via the API (Step 9)

### Auto-refresh not working
- Check browser console for JS errors
- Verify `assets/js/fraud_api.php` is accessible
- Ensure the admin session is still active

### Flag/Unflag/Suspend buttons not working
- Verify CSRF token is present in the page
- Check that `admin/fraud_action.php` exists and is writable
- Look for flash message errors after action

### Score not auto-flagging at ≥ 70
- The auto-flag only works for users with `status = 'active'`
- Already flagged or suspended users are not affected
