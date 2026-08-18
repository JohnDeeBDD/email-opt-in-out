# Feature Breakdown: WordPress Email Opt-In / Opt-Out Management Plugin

**Source Document:** [`docs/PRD.md`](docs/PRD.md)

**Generated:** 2026-08-18

---

## Feature List

### 1. Campaign Management CRUD Screen

**Type:** Explicit

**Description:** A complete administrative interface under the WordPress **Tools** menu for creating, listing, updating, and deleting email campaigns.

**Details:**
- Create campaigns by supplying a human-readable name
- List all campaigns showing name, campaign code, created date, and status
- Rename existing campaigns
- Change campaign status between `active` and `disabled`
- Delete campaigns with explicit confirmation
- Display per-campaign metadata key (`email_campaign_{CAMPAIGN_CODE}`)
- Show counts of opt-ins and opt-outs per campaign
- Provide convenient copy mechanism for campaign codes
- Campaign codes are plugin-generated and immutable after creation
- Deletion removes only the campaign record, not user metadata
- Warn administrators that disabling is reversible, deletion is not

**WordPress Concepts:** Admin settings page under Tools menu

**Source References:** Sections 13, 14, 39 (criteria 7-11)

---

### 2. Campaign Code Generation

**Type:** Explicit

**Description:** Automatic generation of unique, secure, five-character campaign codes when administrators create campaigns.

**Details:**
- Exactly five characters long
- Drawn from unambiguous, URL-safe alphabet (uppercase letters and digits, excluding O, 0, I, 1)
- Randomly generated using cryptographically secure source
- Assigned automatically on campaign creation
- Unique across all campaigns
- Immutable for campaign lifetime
- Never reissued, even after campaign deletion
- Retry on collision to ensure uniqueness

**Source References:** Sections 13, 14, 39 (criterion 7)

---

### 3. Campaign Record Storage

**Type:** Inferred

**Description:** Persistent storage of campaign records with required metadata.

**Why Inferred:** The PRD explicitly requires campaign CRUD operations and campaign code validation, which necessitate persistent storage of campaign data.

**Details:**
- Store campaign_code (5 characters, unique, plugin-generated, immutable)
- Store name (human-readable, administrator-supplied)
- Store created_at timestamp
- Store status (active | disabled)

**Source References:** Section 13

---

### 4. Action Code Generation API

**Type:** Explicit

**Description:** Administrator-only WordPress API endpoint that generates tracking codes (action codes) for email recipients.

**Details:**
- Accept email address and campaign_code as input
- Return tracking_code, opt_in_url, and opt_out_url
- Support email addresses with or without existing WordPress users
- Reject unknown or disabled campaign codes
- Must NOT create WordPress users
- Must NOT store recipient email addresses
- Pure transformation with no side effects

**WordPress Concepts:** REST API endpoint or custom admin-ajax handler

**Source References:** Sections 17, 39 (criteria 1-3, 15)

---

### 5. Action Code Format and Encoding

**Type:** Explicit

**Description:** Structured action code format combining campaign code and encoded email address.

**Details:**
- First five characters: campaign code
- Remaining characters: encoded email address
- URL-safe, requires no percent-encoding
- Decodable without database lookup
- Deterministic, reversible email address encoding
- Canonicalize email (trim whitespace, lowercase) before encoding
- Use Base32-style or similar URL-safe alphabet
- Avoid visual resemblance to original address
- Validate decoded result is syntactically valid email

**Source References:** Sections 15, 16, 39 (criteria 12-14)

---

### 6. Optional Action Code Check Value

**Type:** Explicit

**Description:** Optional security enhancement appending a keyed hash check value to action codes.

**Details:**
- Append 2-4 characters derived from HMAC of canonical email + campaign code
- Use site secret for keying
- Detect truncated or mangled links
- Prevent forgery of codes for arbitrary addresses under same campaign
- Reject codes with invalid check values

**Source References:** Section 16

---

### 7. Campaign Management API

**Type:** Explicit

**Description:** Programmatic API for campaign CRUD operations, mirroring the admin screen functionality.

**Details:**
- List campaigns
- Create campaign (name in, campaign code out)
- Update campaign (name, status)
- Delete campaign
- Campaign code assigned by plugin on create
- Campaign code rejected as input to update
- Deletion removes campaign record only, preserves user metadata

**WordPress Concepts:** REST API endpoints or custom admin-ajax handlers

**Source References:** Section 17

---

### 8. Recorded State Query API

**Type:** Explicit (SHOULD requirement)

**Description:** API endpoints to query per-campaign opt-in/opt-out state for users.

**Details:**
- Query which users opted in to specific campaign
- Query which users opted out of specific campaign
- Query per-campaign state of specific user
- Responses restricted to administrators
- Responses contain personal data

**WordPress Concepts:** REST API endpoints

**Source References:** Section 17

---

### 9. Public Opt-In Landing Page

**Type:** Explicit

**Description:** Public-facing page where recipients explicitly opt in via CTA link from campaign email.

**Details:**
- Accessible via action code in URL
- Validate campaign code portion
- Decode email address portion
- Display opt-in confirmation interface
- Require explicit user action (form submission) before state change
- Find or create WordPress user on confirmation
- Record opt-in metadata under `email_campaign_{CAMPAIGN_CODE}`
- Display confirmation message
- Must NOT log user in
- Must NOT require WordPress authentication

**Source References:** Sections 24, 25, 39 (criteria 16, 19, 37)

---

### 10. Public Opt-Out Landing Page (Unsubscribe)

**Type:** Explicit

**Description:** Public-facing unsubscribe page where recipients explicitly opt out.

**Details:**
- Accessible via action code in URL
- Validate campaign code portion
- Decode email address portion
- Display unsubscribe explanation
- Provide clear "Unsubscribe" action
- Require explicit user action before state change
- Find or create WordPress user on confirmation
- Record opt-out metadata under `email_campaign_{CAMPAIGN_CODE}`
- Display confirmation message
- Must NOT require login, username, password, or re-entering email
- Must NOT require explanation for unsubscribing
- May display decoded email address for confirmation
- Should indicate which campaign is being unsubscribed
- May display campaign name
- Must NOT log user in
- Must NOT delete WordPress user

**Source References:** Sections 22, 23, 25, 39 (criteria 17, 20, 36)

---

### 11. WordPress User Creation on Opt-In

**Type:** Explicit

**Description:** Automatic creation of WordPress user account when recipient with no existing account explicitly opts in.

**Details:**
- Create user only on explicit opt-in action
- Use decoded email address from action code
- Generate strong random password (never displayed or emailed)
- Assign subscriber-level or configured non-privileged role
- Must NOT grant elevated privileges
- Must NOT send WordPress "new user" notification unless explicitly configured
- Reuse existing user if email already exists
- Must NOT create duplicate accounts

**Source References:** Sections 6, 24, 32, 39 (criteria 16, 18, 39, 40)

---

### 12. WordPress User Creation on Opt-Out

**Type:** Explicit

**Description:** Automatic creation of WordPress user account when recipient with no existing account explicitly opts out, to maintain durable suppression record.

**Details:**
- Create user on explicit opt-out action
- Use decoded email address from action code
- Generate strong random password (never displayed or emailed)
- Assign subscriber-level or configured non-privileged role
- Must NOT grant elevated privileges
- Must NOT send WordPress "new user" notification unless explicitly configured
- Reuse existing user if email already exists
- Must NOT create duplicate accounts
- Must NOT delete user after opt-out is recorded

**Source References:** Sections 2, 6, 22, 32, 39 (criteria 17, 18, 39-41)

---

### 13. Per-Campaign Opt-In Metadata Storage

**Type:** Explicit

**Description:** Hidden user metadata recording explicit opt-in actions, stored per campaign.

**Details:**
- Metadata key: `email_campaign_{CAMPAIGN_CODE}`
- Record campaign_code
- Record state (OPTED_IN)
- Record opt_in timestamp
- Record reason
- Record source (e.g., "email_campaign")
- Record relevant contextual details
- Hidden from ordinary user profile fields
- May use protected-meta convention (leading underscore)
- Affect only the campaign identified by action code
- Must NOT alter metadata of other campaigns

**WordPress Concepts:** User meta

**Source References:** Sections 8, 9, 11, 39 (criteria 19, 24-26)

---

### 14. Per-Campaign Opt-Out Metadata Storage

**Type:** Explicit

**Description:** Hidden user metadata recording explicit opt-out actions, stored per campaign.

**Details:**
- Metadata key: `email_campaign_{CAMPAIGN_CODE}`
- Record campaign_code
- Record state (OPTED_OUT)
- Record opt_out timestamp
- Record reason
- Record source (e.g., "email_campaign")
- Record relevant contextual details
- Hidden from ordinary user profile fields
- May use protected-meta convention (leading underscore)
- Affect only the campaign identified by action code
- Must NOT alter metadata of other campaigns
- Must NOT delete historical opt-in information for same campaign

**WordPress Concepts:** User meta

**Source References:** Sections 8, 10, 11, 39 (criteria 20, 24-26, 42)

---

### 15. Account Creation Provenance Metadata

**Type:** Explicit (SHOULD requirement)

**Description:** Metadata recording why and how a WordPress account was created through the plugin.

**Details:**
- Record account_created_by (e.g., "email_action")
- Record account_created_reason (opt_in or opt_out)
- Record campaign_code that triggered creation
- May be incorporated into opt-in/opt-out metadata
- Must remain determinable for administrative visibility

**WordPress Concepts:** User meta

**Source References:** Sections 28, 29, 39 (criterion 27)

---

### 16. Per-Campaign State Determination

**Type:** Explicit

**Description:** System capability to determine current opt-in/opt-out state for a user and specific campaign.

**Details:**
- Distinguish between OPTED_IN, OPTED_OUT, and NO_RECORD states
- NO_RECORD means no metadata entry exists for that campaign
- NO_RECORD must NOT be treated as opt-in or opt-out
- States are independent across campaigns
- Single user may have different states for different campaigns

**Source References:** Sections 12, 39 (criteria 22, 23)

---

### 17. Cross-Campaign Summary Report

**Type:** Explicit

**Description:** Derived summary showing all campaigns a user has opted in to or opted out of.

**Details:**
- Report campaigns user has opted in to
- Report campaigns user has opted out of
- Summary is derived from per-campaign metadata
- Per-campaign entries remain authoritative record

**Source References:** Section 12

---

### 18. Campaign Code Validation

**Type:** Explicit

**Description:** Validation of campaign codes in action codes before allowing any data modification.

**Details:**
- Validate campaign code exists
- Validate campaign is not disabled
- Validate campaign is not deleted
- Reject unknown, disabled, or deleted campaign codes
- Must NOT create or modify user data with invalid campaign code

**Source References:** Sections 13, 14, 35, 36, 39 (criteria 11, 30)

---

### 19. Email Address Decoding Validation

**Type:** Explicit

**Description:** Validation that encoded portion of action code decodes to syntactically valid email address.

**Details:**
- Decode email address from action code
- Validate result is syntactically valid email
- Reject codes that don't decode to valid email
- Must NOT create or modify data with invalid email

**Source References:** Sections 16, 35, 36, 39 (criterion 31)

---

### 20. Invalid Code Rejection

**Type:** Explicit

**Description:** Comprehensive rejection of malformed, invalid, or unauthorized action codes.

**Details:**
- Reject malformed codes
- Reject codes that are too short
- Reject codes with unknown campaign codes
- Reject codes with disabled campaign codes
- Reject codes with deleted campaign codes
- Reject codes with invalid check values (if implemented)
- Reject codes that don't decode to valid email
- Must NOT create WordPress users
- Must NOT modify existing users
- Must NOT create opt-in or opt-out metadata
- Present generic failure message
- Must NOT reveal which portion failed
- Must NOT reveal if email exists in WordPress
- Must NOT reveal if campaign code was valid

**Source References:** Sections 35, 36, 39 (criteria 30-34)

---

### 21. Administrator-Only Authorization

**Type:** Explicit

**Description:** Capability-based access control restricting administrative features to administrators.

**Details:**
- Tracking code API requires administrator capability
- Campaign management screen requires administrator capability
- Campaign CRUD operations require administrator capability
- Recorded state query API requires administrator capability
- Reject unauthenticated requests
- Reject authenticated users without required capability
- Use `manage_options` or dedicated administrator capability

**WordPress Concepts:** Capability checks

**Source References:** Sections 14, 18, 39 (criterion 28)

---

### 22. Nonce Protection for Admin Operations

**Type:** Explicit

**Description:** WordPress nonce protection for all administrative write operations.

**Details:**
- Protect campaign create operations
- Protect campaign update operations
- Protect campaign delete operations
- Protect campaign status change operations
- All POST operations from admin screen must be nonce-protected

**WordPress Concepts:** WordPress nonces

**Source References:** Sections 14, 18, 35, 39 (criterion 29)

---

### 23. Input Sanitization and Output Escaping

**Type:** Explicit

**Description:** Security hardening for administrator-supplied input and output display.

**Details:**
- Sanitize campaign name on save
- Escape campaign name on output
- Sanitize all administrator-supplied input

**Source References:** Section 14

---

### 24. Rate Limiting for Public Endpoints

**Type:** Explicit (SHOULD requirement)

**Description:** Rate limiting by IP address for public opt-in and opt-out endpoints.

**Details:**
- Rate-limit by IP address
- Prevent abuse of public endpoints

**Source References:** Sections 19, 35

---

### 25. Rejected Code Logging

**Type:** Explicit (SHOULD requirement)

**Description:** Logging of rejected action codes for abuse detection.

**Details:**
- Log rejected codes
- Enable detection of campaign code abuse
- Enable detection of leaked campaign codes

**Source References:** Sections 19, 35

---

### 26. No Auto-Login on Action

**Type:** Explicit

**Description:** Prohibition against establishing WordPress authentication session during opt-in or opt-out actions.

**Details:**
- Must NOT log recipient in during opt-in
- Must NOT log recipient in during opt-out
- Must NOT establish WordPress session
- Must NOT issue authentication cookie
- Must NOT issue password
- Must NOT issue password-reset link
- Must NOT issue magic login link

**Source References:** Sections 3, 20, 22, 25, 39 (criterion 38)

---

### 27. Action Scope Limitation

**Type:** Explicit

**Description:** Strict limitation of what action codes can authorize.

**Details:**
- Action code authorizes ONLY opt-in or opt-out metadata change
- Action code applies ONLY to email address encoded in code
- Action code applies ONLY to campaign identified in code
- Must NOT authorize login
- Must NOT authorize password change
- Must NOT authorize email address change
- Must NOT authorize role or capability change
- Must NOT authorize reading other user data
- Must NOT authorize modifying other user data
- Must NOT accept recipient-supplied email parameter
- Must NOT modify any field except opt-in/opt-out metadata

**Source References:** Sections 20, 25, 35, 39 (criterion 40), Invariant 7, Invariant 8

---

### 28. Existing User Reuse

**Type:** Explicit

**Description:** Reuse of existing WordPress user accounts instead of creating duplicates.

**Details:**
- Look up user by decoded email address
- If user exists, use existing account
- If user does not exist, create new account
- Must NOT create duplicate users for same email
- Must NOT modify unrelated user data
- Must NOT modify role, password, email, or profile fields

**Source References:** Sections 6, 7, 31, 39 (criterion 18), Invariant 4

---

### 29. Idempotent Action Processing

**Type:** Explicit

**Description:** Idempotent handling of repeated opt-in or opt-out actions.

**Details:**
- Repeated opt-out results in OPTED_OUT state
- Repeated opt-in results in OPTED_IN state
- Must NOT create duplicate users
- Must NOT resubscribe on repeated opt-out
- Must NOT produce inconsistent metadata
- Must NOT affect other campaigns
- Must NOT fail merely because action was previously completed
- Same action code may be followed multiple times

**Source References:** Section 30, 39 (criterion 40)

---

### 30. No State Change on Page Load

**Type:** Explicit

**Description:** Requirement that merely loading an action URL changes no state.

**Details:**
- Page load must NOT record opt-in
- Page load must NOT record opt-out
- Page load must NOT create user
- State changes require explicit user action (form submission)
- Prevents mail scanners from triggering actions
- Prevents link previewers from triggering actions
- Prevents prefetchers from triggering actions

**Source References:** Sections 19, 25, 39 (criterion 35)

---

### 31. Campaign Independence

**Type:** Explicit

**Description:** Strict independence of opt-in/opt-out state across campaigns.

**Details:**
- Action on one campaign must NOT create metadata for other campaigns
- Action on one campaign must NOT modify metadata for other campaigns
- Action on one campaign must NOT clear metadata for other campaigns
- Single user may be opted in to one campaign and opted out of another
- Absence of metadata entry means NO_RECORD, not opt-out

**Source References:** Sections 8, 9, 10, 11, 39 (criteria 21-23), Invariant 9

---

### 32. Historical Information Preservation

**Type:** Explicit (SHOULD requirement)

**Description:** Preservation of historical opt-in and opt-out information per campaign.

**Details:**
- Preserve sufficient information to understand how current state was reached
- Opt-out must NOT erase historical opt-in information for same campaign
- Later opt-in should NOT destroy historical opt-out for same campaign
- Action on one campaign must NOT erase record of other campaigns
- May evolve into event/history model per campaign

**Source References:** Section 11, 39 (criterion 42)

---

### 33. Campaign Deletion Without Metadata Deletion

**Type:** Explicit

**Description:** Campaign deletion removes only campaign record, preserving all user metadata.

**Details:**
- Deletion removes campaign record only
- Must NOT delete user metadata
- Must NOT alter user metadata
- Must NOT anonymize user metadata
- User metadata entries survive campaign deletion
- Deleted campaign code must NOT be reissued
- Action codes with deleted campaign code must be rejected
- Require explicit confirmation for deletion
- Warn that disabling is reversible, deletion is not

**Source References:** Sections 14, 17, 39 (criterion 10), Invariant 10

---

### 34. Administrative Visibility of User State

**Type:** Explicit (SHOULD requirement)

**Description:** Administrative interface or capability to view per-campaign opt-in/opt-out state for users.

**Details:**
- View user email address
- View campaigns user has acted on
- View current per-campaign state
- View opt-in information per campaign
- View opt-out information per campaign
- View campaign codes and names
- View timestamps
- View source/reason for actions
- View whether email action caused account creation
- View which campaign caused account creation
- View per-campaign opt-in and opt-out counts

**Source References:** Sections 14, 38

---

### 35. Data Integrity for User Creation and Metadata

**Type:** Explicit (SHOULD requirement)

**Description:** Atomic or recoverable handling of user creation and metadata modification.

**Details:**
- User creation and metadata write should behave as single logical operation
- Minimize partially completed operations
- Make failures recoverable
- Avoid state where user exists but metadata write failed

**Source References:** Section 37

---

### 36. No Duplicate User Creation

**Type:** Explicit

**Description:** Prevention of duplicate WordPress user accounts for same email address.

**Details:**
- Check for existing user by email before creating
- Reuse existing user if found
- Create new user only if none exists
- Enforce at user creation time

**WordPress Concepts:** `get_user_by()`, `email_exists()`

**Source References:** Sections 7, 30, 39 (criteria 18, 40), Invariant 4

---

### 37. Non-Privileged Role Assignment

**Type:** Explicit

**Description:** Assignment of subscriber-level or configured non-privileged role to created users.

**Details:**
- Assign site's normal subscriber-level role
- Or assign explicitly configured non-privileged role
- Must NOT grant administrative privileges
- Must NOT grant editorial privileges
- Must NOT grant elevated privileges

**WordPress Concepts:** User roles and capabilities

**Source References:** Section 32, 39 (criterion 39)

---

### 38. Optional New User Notification Suppression

**Type:** Explicit

**Description:** Suppression of WordPress "new user" notification unless explicitly configured.

**Details:**
- Must NOT send new user notification by default
- Recipient did not register for account
- May send if site explicitly configures it

**WordPress Concepts:** `wp_new_user_notification()` control

**Source References:** Section 32

---

### 39. Silence Creates Nothing Enforcement

**Type:** Explicit

**Description:** Enforcement that no WordPress user is created merely from email sending or non-response.

**Details:**
- Email included in external list: no user created
- Action code generated: no user created
- Email sent: no user created
- Email delivered: no user created
- Email opened: no user created
- Link viewed without action: no user created
- No response: no user created
- Only explicit opt-in or opt-out creates user

**Source References:** Sections 2, 3, 5, 6, 26, 39 (criteria 2-6), Invariant 1

---

### 40. No Automatic Opt-In from Account Existence

**Type:** Explicit

**Description:** Separation of WordPress account existence from marketing email permission.

**Details:**
- WordPress account existence does NOT imply opt-in
- WordPress account existence does NOT imply permission to send marketing email
- User may exist with OPTED_OUT state
- User may exist with NO_RECORD state
- Account may have been created by opt-out action

**Source References:** Section 34, 39 (criterion 44)

---

### 41. Distinction Between Opt-Out and Inactivity

**Type:** Explicit

**Description:** Semantic distinction between explicit opt-out and inactivity/no-response.

**Details:**
- Explicit opt-out is recorded as opt-out
- Inactivity/no-response is NOT recorded as opt-out
- Removal from external list due to inactivity is NOT opt-out
- Must NOT falsely record opt-out for inactive recipients
- Preserve historical accuracy

**Source References:** Sections 26, 27, 39 (criterion 43), Invariant 5

---

### 42. Provenance Recording

**Type:** Explicit (SHOULD requirement)

**Description:** Recording of provenance information for opt-in and opt-out actions.

**Details:**
- Record action type
- Record timestamp
- Record campaign_code
- Record source
- Record reason
- May record original_list/source
- May record action mechanism
- Enable answering: when, how, which campaign, why

**Source References:** Section 28

---

### 43. Personal Data Protection

**Type:** Explicit

**Description:** Treatment of action URLs and responses as personal data.

**Details:**
- Action URLs contain personal data (encoded email)
- Must NOT publish action URLs
- Must NOT log to third parties
- Must NOT expose in analytics
- Must NOT forward to third-party analytics
- Must NOT expose in publicly readable logs
- API responses with email addresses restricted to administrators

**Source References:** Sections 17, 19, 35

---

### 44. Campaign Disable Switch

**Type:** Explicit

**Description:** Ability to disable campaign codes to revoke authorization.

**Details:**
- Administrator can disable campaign
- Disabled campaign code rejected by public endpoints
- Mechanism for shutting down leaked campaign code
- Mechanism for shutting down scraped campaign code
- Mechanism for shutting down abused campaign code
- Administrator can re-enable disabled campaign

**Source References:** Sections 13, 14, 19, 39 (criterion 8)

---

### 45. Username Generation for Created Users

**Type:** Inferred

**Description:** Generation of WordPress usernames for users created from email addresses.

**Why Inferred:** The PRD explicitly requires creating WordPress users, and WordPress requires a username. The PRD states "The implementation SHALL determine appropriate values for required WordPress fields such as username."

**Details:**
- Generate valid WordPress username
- Derive from email address or generate unique value
- Handle username conflicts
- Follow WordPress username requirements

**WordPress Concepts:** `wp_insert_user()`, username validation

**Source References:** Section 32

---

### 46. Email Address Canonicalization

**Type:** Explicit

**Description:** Canonicalization of email addresses before encoding and user lookup.

**Details:**
- Trim whitespace
- Convert to lowercase
- Apply before encoding
- Apply before user lookup
- Ensure consistent matching

**Source References:** Section 16

---

### 47. URL-Safe Alphabet for Encoding

**Type:** Explicit

**Description:** Use of URL-safe, unambiguous alphabet for email encoding and campaign codes.

**Details:**
- Use URL-safe characters
- Avoid case-sensitive characters (single-case alphabet recommended)
- Avoid visually confusable characters (O, 0, I, 1)
- Base32-style alphabet recommended
- Prevent email client or link rewriter case alteration

**Source References:** Sections 13, 16

---

### 48. Site-Specific Encoding Configuration

**Type:** Inferred

**Description:** Site-specific configuration for email address encoding transformation.

**Why Inferred:** The PRD describes encoding using "a site-specific shuffled alphabet stored in plugin configuration," which requires configuration storage.

**Details:**
- Store site-specific shuffled alphabet
- Store in plugin configuration
- Use for encoding transformation
- Use for decoding transformation

**Source References:** Section 16

---

### 49. Optional Site Secret for Check Values

**Type:** Inferred

**Description:** Site secret storage for generating keyed hash check values in action codes.

**Why Inferred:** The PRD describes the optional check value as "derived from a keyed hash (HMAC) of the canonical email address plus the campaign code, using a site secret," which requires a site secret.

**Details:**
- Store site secret
- Use for HMAC generation
- Use for check value validation
- Required only if check value feature is implemented

**Source References:** Section 16

---

## Summary

**Total Features:** 49

**Explicit Features:** 45

**Inferred Features:** 4

### Inferred Features Summary

1. **Campaign Record Storage** (Feature 3) - Required for campaign CRUD operations and validation
2. **Username Generation for Created Users** (Feature 45) - Required for WordPress user creation
3. **Site-Specific Encoding Configuration** (Feature 48) - Required for email encoding transformation
4. **Optional Site Secret for Check Values** (Feature 49) - Required for optional HMAC check value feature

### Core Feature Categories

1. **Campaign Management** (Features 1-3, 7, 18, 33, 44)
2. **Action Code Generation & Encoding** (Features 4-6, 19, 46-49)
3. **Public Landing Pages** (Features 9-10, 30)
4. **User Creation** (Features 11-12, 28, 36-38, 45)
5. **Metadata Storage** (Features 13-17, 31-32, 42)
6. **Security & Authorization** (Features 20-27, 43)
7. **State Management** (Features 16-17, 29, 39-41)
8. **Administrative Visibility** (Features 8, 34)
9. **Data Integrity** (Features 35, 46)

### WordPress-Specific Concepts Used

- Admin settings page (Tools menu)
- User meta (hidden, per-campaign)
- REST API endpoints or admin-ajax handlers
- Capability checks (`manage_options`)
- WordPress nonces
- User roles and capabilities
- WordPress user creation functions
- Email validation and canonicalization

