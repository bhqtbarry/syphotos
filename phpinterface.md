# SY Photos PHP Interface Spec

## 1. Scope

This document defines the PHP backend interface requirements for the SY Photos Android app v1.

Platform assumptions:

- Backend domain: `https://www.syphotos.cn`
- Web server: Apache
- PHP: `8.3.29`
- Database: `MariaDB 10.11.15`
- Existing website account system is reused
- App authentication must use `access token + refresh token`
- Existing website `session/cookie` logic is not reused directly by the app

## 2. Product Scope

Bottom navigation for v1:

1. All Photos + search/filter
2. Map mode
3. Upload
4. Category mode
5. My

Not in scope for v1:

- Comments
- Favorites
- Follow
- Private messages
- Video
- Admin review features inside app

## 3. Existing Data Basis

Existing core tables already available:

- `photos`
- `photo_likes`
- `users`
- `announcements`
- `airplane`

Important existing behavior to preserve:

- `index.php` featured logic and homepage thumbnail logic
- `photolist.php` search, filter and list behavior
- `upload.php` upload fields, EXIF extraction, watermark and aircraft registration related behavior
- `photo_detail.php` detail information and web share target
- `map.php` map-style page behavior as the reference for app map mode

## 4. Authentication Model

App authentication rules:

- Login supports `username` or `email`
- Email verification is required before login
- Registration follows the existing website rules
- App uses token-based authentication
- Recommended model:
  - short-lived `access_token`
  - long-lived `refresh_token`
  - refresh supported after access token expiry
  - logout current device supported
  - logout all other devices supported
  - revoke a specified device supported

Required device/session management:

- Show all logged-in devices
- Show device name
- Show login time
- Show IP
- Show system version
- Kick one device manually
- Kick all devices except current device

## 5. App Page Requirements

### 5.1 All Photos

This page is the first tab.

Behavior:

- Main photo list uses the same service logic as `photolist.php`
- Search and filter are built into this page
- Thumbnail grid style follows `photolist.php`
- Opening a thumbnail enters full-screen original image mode
- Returning from full-screen returns to the current list position

Search and filter fields confirmed from current `photolist.php` service:

- `keyword`
- `userid`
- `airline`
- `aircraft_model`
- `cam`
- `lens`
- `registration_number`
- `iatacode`

Current `photolist.php` matching rules come from `src/photo_feed_service.php`:

- keyword matches:
  - `photos.title`
  - `users.username`
  - `photos.category`
  - `photos.aircraft_model`
  - `photos.registration_number`
  - `photos.Cam`
  - `photos.Lens`
  - `photos.拍摄地点`
- only approved photos in public list
- default sorting:
  - `score DESC`
  - `created_at DESC`

Suggestion capability already exists conceptually:

- author
- airline
- aircraft model
- camera
- lens
- registration number
- shooting location / IATA code

### 5.2 Map Mode

This page is the second tab.

Behavior:

- Similar to `map.php`
- Aggregation levels:
  - country
  - province/state
  - city
- Markers split when zooming in
- Tapping a marker jumps to the All Photos list page with the same filter context
- Map page has the same filter panel as All Photos
- Default location:
  - request precise device location permission first
  - if denied, choose default country by current language

### 5.3 Upload

This page is the third tab.

Behavior:

- Single image upload only
- Max original upload size: `40 MB`
- Allowed ratio:
  - widest `2:1`
  - narrowest `1:2`
- Upload fields and workflow follow `upload.php`
- Keep current EXIF extraction logic
- Keep current watermark logic
- Keep current aircraft registration related recognition behavior
- Upload requires login
- Upload shows progress
- Upload supports retry on failure
- Uploaded photos enter review queue first

Status rules:

- Pending:
  - editable
  - deletable
- Rejected:
  - cannot be resubmitted from same record
  - must re-upload a new image
  - original rejected record may be deleted
- Approved:
  - deletable
  - not editable

Deletion rules:

- All statuses can be deleted
- Deletion is irreversible
- Require second confirmation by typing the photo title
- Deleted photo share links become invalid
- Invalid share link should show "link expired" style response and redirect to homepage

### 5.4 Category Mode

This page is the fourth tab.

Behavior for v1:

- Top tabs only
  - airline tab
  - aircraft model tab
- This is a list page, not a photo list page
- Show text rows only
- Show count for each item
- No click-through in v1
- No search in category page
- No filter in category page

Grouping source for v1:

- airline tab groups by `airplane.operator`
- aircraft model tab groups by `airplane.modes`

### 5.5 My

This page is the fifth tab.

Content:

- change password only
- my works
- my likes
- my pending
- my rejected
- approved / pending / rejected filters on my works
- rejected items must show rejection reason and admin comment
- view current logged-in devices
- kick one device
- kick all devices except current

Not allowed:

- profile editing beyond password
- admin review operations

## 6. Full-Screen Photo Viewer

Entry:

- user taps a thumbnail from list/grid pages

Loading and cache:

- show original image
- preload next `5` images in current queue
- original image local cache limit: `200 MB`
- thumbnail local cache limit: `100 MB`

Viewer interactions:

- single tap toggles chrome/info visibility
- double tap zooms in
- double tap again zooms in further
- double tap again returns to fit-to-screen
- when zoomed in, dragging inside current image is allowed
- while zoomed in, vertical gesture stays inside image
- next/previous image swipe allowed only after returning to full image view
- left swipe opens author homepage
- like is a dedicated button, not double tap

Displayed information:

- same information scope as `photo_detail.php`
- share button
- like button
- author entry

## 7. Guest Permissions

Guest users can:

- browse approved photos
- browse map mode
- open shared web detail links

Guest users cannot:

- upload
- like
- enter My features that require account state

When guest triggers a restricted action:

- app should require login first

## 8. Share and Deep Link Strategy

Share strategy:

- outward share target is HTTPS web detail URL
- web detail page remains the fallback
- Android uses App Links
- future iPhone version uses Universal Links
- if app is installed, open target photo inside app
- if app is not installed, open website detail page

## 9. Password Reset Rules

Forgot password flow:

- send email verification code
- user enters code
- user enters new password twice

Security rules:

- code valid for `5 minutes`
- max `10` sends per hour
- rate limit by email and IP
- after `10` sends, block for `1 hour`
- after `5` wrong code attempts, lock for `1 hour`

## 10. Notifications

Notification scope for v1:

- review approved notification
- review rejected notification

Form:

- system push notification
- tapping the notification switches to My page

## 11. Required PHP API Areas

The Android app requires PHP endpoints in these areas.

Implemented in this repository so far:

- `api/app/v1/auth/login.php`
- `api/app/v1/auth/register.php`
- `api/app/v1/auth/refresh.php`
- `api/app/v1/auth/logout.php`
- `api/app/v1/auth/logout_others.php`
- `api/app/v1/auth/devices.php`
- `api/app/v1/auth/revoke_device.php`
- `api/app/v1/auth/change_password.php`
- `api/app/v1/auth/forgot_password_request.php`
- `api/app/v1/auth/forgot_password_reset.php`
- `api/app/v1/photos/feed.php`
- `api/app/v1/photos/detail.php`
- `api/app/v1/photos/toggle_like.php`
- `api/app/v1/photos/my.php`
- `api/app/v1/photos/likes.php`
- `api/app/v1/photos/delete.php`
- `api/app/v1/photos/update_pending.php`
- `api/app/v1/search/suggestions.php`
- `api/app/v1/map/clusters.php`
- `api/app/v1/categories/counts.php`
- `api/app/v1/notifications/index.php`
- `api/app/v1/me/summary.php`

### 11.1 Auth

- login by username/email + password
- register
- refresh token
- logout current device
- logout other devices
- list active devices
- revoke one device
- forgot password request code
- forgot password reset password
- change password

### 11.2 Photo Feed

- all photos list
- photo detail
- signed image access
- like / unlike
- user own works list
- user pending list
- user rejected list
- user likes list

### 11.3 Search and Filter

- keyword search
- filter suggestions
- list by author
- list by airline
- list by aircraft model
- list by camera
- list by lens
- list by registration number
- list by location

### 11.4 Map

- clustered map points by country / province / city
- map filters consistent with All Photos
- location-based default country resolution

### 11.5 Upload

- upload image
- extract EXIF
- recognize/validate related aircraft registration data
- edit pending upload metadata
- delete upload

### 11.6 Category

- airline grouped counts from `airplane.operator`
- aircraft model grouped counts from `airplane.modes`

### 11.7 Push / Notice

- unread notice list or notification center
- review result push trigger support

## 12. Response Principles

General backend principles:

- API returns JSON only
- app endpoints are versioned, recommended prefix: `/api/app/v1/...`
- image files are not directly exposed
- image access uses signed temporary URLs or signed file gateway
- all timestamps returned in explicit format
- all paginated endpoints return:
  - `page`
  - `per_page`
  - `total`
  - `has_more`
  - `items`

Recommended status fields for app:

- `pending`
- `approved`
- `rejected`

## 13. Data Additions Likely Needed

Likely extra backend persistence required for app:

- app access tokens / refresh tokens table
- device sessions table
- password reset code table
- push token table for Android devices
- internal notification table

Schema file added in repository:

- `sql/app_api_schema.sql`

## 14. Open Items

These are intentionally deferred, not blockers:

- category page click-through in later versions
- exact Android push vendor choice
- exact map geo source implementation details
