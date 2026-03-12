# SY Photos Android App Handoff

## 1. Purpose

This document is for the separate Android repository.

Target repo planned by user:

- `https://github.com/bhqtbarry/andriod`

The goal is that an Android implementation can be built against the PHP backend without guessing product behavior.

## 2. Product Definition

App type:

- native Android
- future iPhone and HarmonyOS versions may follow later
- image-only app, no video
- uses existing SY Photos account system

Bottom navigation:

1. All Photos
2. Map
3. Upload
4. Category
5. My

## 3. Tab-by-Tab Requirements

### 3.1 All Photos

This is the primary entry page.

Requirements:

- same overall list behavior as current website `photolist.php`
- built-in search and filter
- thumbnail grid
- tap thumbnail to enter full-screen original viewer
- maintain list scroll position when returning

Search/filter dimensions:

- keyword
- author
- airline
- aircraft model
- camera
- lens
- registration number
- location / IATA code

Sorting:

- same as current photo feed service
- `score DESC`, then `created_at DESC`

### 3.2 Map

Requirements:

- visual reference: website `map.php`
- cluster hierarchy:
  - country
  - province/state
  - city
- split markers on zoom
- tap marker -> jump to All Photos with same filter context
- same filter panel as All Photos
- initial country:
  - request precise location permission
  - if denied, infer country from current language

### 3.3 Upload

Requirements:

- single image upload
- max file size `40 MB`
- ratio constraint from `1:2` to `2:1`
- keep website `upload.php` field model
- keep EXIF auto extraction
- keep watermark behavior
- keep aircraft registration recognition logic
- show upload progress
- support retry
- upload enters review flow

Editing rules:

- pending: editable, deletable
- rejected: not editable for resubmission, must upload again, deletable
- approved: not editable, deletable

Deletion rules:

- irreversible
- second confirmation required
- user must type photo title to confirm

### 3.4 Category

Version 1 scope:

- top tab switcher only
- two tabs:
  - airline
  - aircraft model
- airline groups from `airplane.operator`
- aircraft model groups from `airplane.modes`
- text-only list
- show counts
- no click action in v1
- no search
- no filter

### 3.5 My

Requirements:

- change password only
- my works
- my likes
- my pending
- my rejected
- filter my works by approved / pending / rejected
- rejected items must show rejection reason and admin comment
- device management list
- revoke one device
- revoke all devices except current

## 4. Full-Screen Viewer

Entry:

- from tapping a thumbnail in list pages

Image source:

- display original image
- signed backend URL expected

Preload/cache:

- preload next `5` originals in queue
- original cache cap `200 MB`
- thumbnail cache cap `100 MB`

Gestures:

- single tap: hide/show UI
- double tap: zoom in
- double tap again: zoom in more
- double tap again: fit screen
- pan allowed while zoomed
- vertical gesture while zoomed stays inside current image
- page switching only after returning to fit-screen state
- left swipe: open author homepage

Actions:

- like button
- share button
- author button

Displayed info:

- same information scope as current website `photo_detail.php`

## 5. Authentication and Security

App auth model:

- use `access_token + refresh_token`
- do not rely on website session/cookie
- login supports username or email
- email verification required before login

Forgot password:

- email code
- code valid `5 minutes`
- max `10` sends per hour
- email + IP rate limiting
- after `10` sends, block `1 hour`
- after `5` wrong code attempts, lock `1 hour`

Session management:

- show all login devices
- show device name, login time, IP, system version
- revoke one device
- revoke all other devices

## 6. Guest Behavior

Guests can:

- browse approved photos
- browse map mode
- open shared photo links

Guests cannot:

- upload
- like
- use My account functions

Restricted actions must redirect to login flow.

## 7. Share and Link Handling

Share output:

- HTTPS web detail link

Open strategy:

- Android App Links
- website as fallback
- if app installed, open target photo inside app
- if not installed, open website photo detail page

Deleted or invalid shared link behavior:

- show expired/invalid link message
- return user to app home or website home fallback

## 8. Notifications

Version 1 notifications:

- review approved
- review rejected

Behavior:

- use system push notifications
- tap notification -> open My page

## 9. Backend Contract Summary

Android app expects backend support for:

- auth
- token refresh
- password reset
- device session management
- all photos feed
- search/filter suggestions
- map clusters
- photo detail
- signed image URLs
- like/unlike
- upload
- edit pending upload metadata
- delete photo
- my works
- my likes
- my pending
- my rejected
- category counts
- notification center or equivalent unread state

Recommended backend versioning:

- `/api/app/v1/...`

## 10. Implementation Notes For Android Repo

Recommended Android architecture decisions:

- keep tab state independent
- preserve list scroll state across navigation
- use paged loading for feeds
- separate thumbnail loading and original loading strategies
- deep link route should support direct open by photo id
- token refresh should be transparent to the user
- push open action should route deterministically to My page

## 11. Known Deferred Items

Deferred after v1:

- category item click-through
- iPhone Universal Links
- HarmonyOS adaptation
- richer notification center UX

