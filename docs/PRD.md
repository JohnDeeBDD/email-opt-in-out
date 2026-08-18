# Product Requirements Document: WordPress Email Opt-In / Opt-Out Management Plugin

## 1. Overview

The Email Opt-In / Opt-Out Management plugin provides a WordPress-based system for managing the relationship between a large external email list and WordPress user accounts.

The system begins with a potentially large collection of email addresses whose owners are permitted to receive email but who, in many cases, do not currently have WordPress user accounts.

The fundamental business rule is:

> **Receiving an email or failing to respond to an email does not create a WordPress user. Explicitly opting in or explicitly opting out does create a WordPress user.**

Once created, the WordPress user becomes the persistent record of that person's explicit action.

The plugin maintains hidden `opt-in` and `opt-out` user metadata containing information about these actions.

---

# 2. Core Business Model

The system recognizes three fundamentally different situations.

### No Action

An email recipient has neither opted in nor opted out.

```text
Email sent
    ↓
No response
    ↓
NO WORDPRESS USER CREATED
```

Silence MUST NOT be interpreted as either an opt-in or an opt-out.

The recipient may remain on the external email list according to policies managed outside this plugin.

### Explicit Opt-In

The recipient explicitly chooses to opt in.

```text
Email
  ↓
Opt In
  ↓
Find or create WordPress user
  ↓
Record opt-in metadata
```

If the email address does not already correspond to a WordPress user, the plugin SHALL create one.

### Explicit Opt-Out

The recipient explicitly chooses to unsubscribe.

```text
Email
  ↓
Opt Out
  ↓
Find or create WordPress user
  ↓
Record opt-out metadata
```

If the email address does not already correspond to a WordPress user, the plugin SHALL create one.

Creating a WordPress user as the result of an opt-out is an intentional business requirement.

---

# 3. Goals

The plugin SHALL:

* Support recipients who do not currently have WordPress accounts.
* Generate individualized email action codes for external email addresses.
* Associate generated codes with a campaign.
* Allow recipients to explicitly opt in.
* Allow recipients to explicitly opt out.
* Create WordPress users when recipients explicitly opt in.
* Create WordPress users when recipients explicitly opt out.
* Never create WordPress users merely because an email was sent.
* Never create WordPress users because a recipient failed to respond.
* Maintain detailed hidden metadata describing explicit opt-in and opt-out actions.
* Preserve the provenance of those actions.
* Provide an authoritative WordPress record of explicit recipient decisions.

---

# 4. Non-Goals

Version 1 does not need to provide:

* Email transmission.
* Campaign creation.
* Email template management.
* Bulk mailing-list storage.
* Mailing-list import.
* Bounce processing.
* Open tracking.
* Click analytics.
* Campaign analytics.
* Automated list cleanup.
* Automatic removal of inactive recipients.
* Complex mailing preferences.
* Multiple newsletter subscriptions.
* A full CRM.
* A full mailing-list management system.

The external mailing system remains responsible for maintaining and processing recipients who have taken no action.

---

# 5. External Email Addresses

The plugin MUST NOT require an email recipient to already exist in `wp_users`.

The administrative API SHALL therefore be capable of generating action codes for email addresses that do not correspond to existing WordPress users.

For example:

```text
john@example.com
    ↓
No WordPress user exists
    ↓
Generate campaign code
    ↓
Send email
```

Generating the code MUST NOT create a WordPress user.

Sending the email MUST NOT create a WordPress user.

Only an explicit recipient action creates the user.

---

# 6. WordPress User Creation Rule

The central account-creation rule SHALL be:

| Recipient action                         | Create WordPress user? |
| ---------------------------------------- | ---------------------: |
| Email included in external list          |                     No |
| Action code generated                    |                     No |
| Email sent                               |                     No |
| Email delivered                          |                     No |
| Email opened                             |                     No |
| Link viewed without completing an action |                     No |
| No response                              |                     No |
| Explicit opt-in                          |                **Yes** |
| Explicit opt-out                         |                **Yes** |

If the email address already corresponds to an existing WordPress user, the existing user SHALL be used rather than creating a duplicate account.

---

# 7. User Identity

Email address SHALL be the primary mechanism for resolving an email recipient to an existing WordPress user.

When an explicit action occurs:

```text
Find user by email
       ↓
+------+------+
|             |
Exists     Does not exist
|             |
Use user    Create user
|             |
+------+------+
       ↓
Record action
```

The plugin MUST NOT create duplicate WordPress users for the same email address.

User creation SHALL follow normal WordPress requirements and constraints.

---

# 8. Hidden User Metadata

The plugin SHALL maintain two hidden user metadata concepts:

* `opt-in`
* `opt-out`

These values SHALL NOT appear as ordinary user-editable profile fields.

They represent historical explicit actions rather than merely UI preferences.

---

# 9. Opt-In Metadata

When a recipient explicitly opts in, the plugin SHALL record `opt-in` metadata.

The metadata SHOULD contain sufficient information to establish:

* That an explicit opt-in occurred.
* Timestamp.
* Reason.
* Source.
* Campaign ID.
* Relevant contextual information about the action.

Conceptually:

```json
{
  "timestamp": "2026-08-17T17:00:00Z",
  "reason": "Recipient explicitly opted in",
  "source": "email_campaign",
  "campaign_id": "campaign-123",
  "details": {}
}
```

The exact serialization and database representation are implementation decisions.

---

# 10. Opt-Out Metadata

When a recipient explicitly opts out, the plugin SHALL record `opt-out` metadata.

The metadata SHOULD contain:

* Timestamp.
* Reason.
* Source.
* Campaign ID.
* Relevant contextual information.

Conceptually:

```json
{
  "timestamp": "2026-08-17T17:30:00Z",
  "reason": "Recipient explicitly unsubscribed",
  "source": "email_campaign",
  "campaign_id": "campaign-123",
  "details": {}
}
```

An opt-out MUST NOT delete the WordPress user.

---

# 11. Historical Information

Opt-in and opt-out information represents events in the user's relationship with the mailing system.

The plugin SHOULD preserve sufficient information to understand how the user's current state was reached.

For example:

```text
2026-08-17
User created because recipient explicitly opted in.
Campaign: campaign-123

2026-10-02
Recipient explicitly opted out.
Campaign: campaign-218
```

An opt-out MUST NOT automatically erase historical opt-in information.

Likewise, a later opt-in SHOULD NOT destroy the historical fact that an earlier opt-out occurred.

The storage implementation MAY evolve into an event/history model if multiple actions must be retained.

---

# 12. Current State

The system SHALL be capable of determining a WordPress user's current mailing state from their opt-in and opt-out information.

At minimum, the following states must be distinguishable:

```text
OPTED_IN
OPTED_OUT
```

For addresses that have never explicitly acted and therefore have no WordPress user, the plugin does not need to assign a WordPress subscription state.

Those recipients remain the responsibility of the external mailing-list system.

---

# 13. Action Code Generation API

The plugin SHALL provide an administrator-only WordPress API for generating recipient-specific action codes.

The API SHALL accept at minimum:

```text
email
campaign_id
```

The supplied email MAY correspond to:

* An existing WordPress user; or
* An email address for which no WordPress user currently exists.

Both cases SHALL be valid.

The API MUST NOT create a WordPress user merely because a code was requested.

---

# 14. API Authorization

The code-generation API MUST require authenticated administrator-level authorization.

Unauthenticated requests SHALL be rejected.

Authenticated users without the required capability SHALL be rejected.

The exact WordPress capability used is an implementation decision, but it SHOULD normally be an administrator-level capability such as `manage_options` or a dedicated capability assigned to administrators.

---

# 15. Action Codes

Codes SHALL be:

* Recipient-specific.
* Associated with an email address.
* Associated with a campaign ID.
* Cryptographically unpredictable.
* Suitable for inclusion in an email URL.

A code MAY internally resolve to information such as:

```text
email
campaign_id
purpose
created_at
```

Sensitive recipient information MUST NOT need to appear directly in the public URL.

Prefer:

```text
https://example.com/email-action?t=8f3a7d...
```

over:

```text
https://example.com/unsubscribe?email=john@example.com
```

---

# 16. Action Scope

Codes SHOULD have an explicit purpose.

For example:

```text
purpose = opt_in
```

or:

```text
purpose = opt_out
```

An opt-out code MUST NOT authorize unrelated account actions.

An opt-in code MUST NOT implicitly authorize arbitrary changes to a WordPress account.

Possession of an action code authorizes only the specific email-related action for which that code was issued.

---

# 17. Campaign Association

Every generated action code SHALL be associated with a campaign ID.

Examples:

```text
beta-test-001
august-2026
plugin-launch-001
```

The campaign ID is supplied by the external mailing system.

The plugin does not need to maintain campaign definitions.

Campaign association provides provenance for subsequent opt-in or opt-out actions.

---

# 18. Opt-Out Workflow

The unsubscribe workflow SHALL be:

```text
External email address
        ↓
Administrator generates action code
        ↓
Email containing unsubscribe URL is sent
        ↓
Recipient follows URL
        ↓
Public unsubscribe page
        ↓
Recipient explicitly chooses Unsubscribe
        ↓
Validate code
        ↓
Resolve email address
        ↓
Find existing WordPress user
        ↓
If none exists, CREATE USER
        ↓
Record opt-out metadata
        ↓
Display confirmation
```

The recipient MUST NOT be required to log into WordPress.

---

# 19. Unsubscribe Page

A valid unsubscribe code SHALL bring the recipient to a public page explaining the unsubscribe action.

The recipient SHALL be provided with a clear action such as:

**Unsubscribe**

The recipient MUST NOT be required to:

* Log in.
* Supply a WordPress username.
* Supply a WordPress password.
* Enter their email address again.
* Explain why they are unsubscribing.

Once the recipient explicitly performs the action, the opt-out SHALL be recorded.

---

# 20. Opt-In Workflow

The plugin SHALL support the corresponding explicit opt-in operation.

Conceptually:

```text
External email address
        ↓
Recipient receives opt-in opportunity
        ↓
Recipient explicitly opts in
        ↓
Validate action
        ↓
Resolve email address
        ↓
Find existing WordPress user
        ↓
If none exists, CREATE USER
        ↓
Record opt-in metadata
        ↓
Display confirmation
```

The exact opt-in UI MAY differ from the unsubscribe UI.

The essential requirement is that **the WordPress user is not created until the explicit opt-in occurs.**

---

# 21. No-Response Workflow

No response is explicitly different from both opt-in and opt-out.

```text
Email sent
    ↓
Recipient takes no action
    ↓
No WordPress user created
    ↓
No opt-in metadata created
    ↓
No opt-out metadata created
```

The plugin MUST NOT interpret silence as consent.

The plugin MUST NOT interpret silence as an unsubscribe.

The external mailing system MAY later implement policies that remove non-responsive recipients from the external list.

Such removal SHALL be considered an inactivity/list-management operation rather than an explicit user opt-out.

---

# 22. Future Inactivity Handling

The larger mailing system is expected eventually to distinguish between:

```text
Explicit opt-in
Explicit opt-out
No response / inactive
```

These concepts MUST remain semantically distinct.

If a recipient is eventually removed from an external mailing list because they never responded, the system SHOULD NOT falsely record:

```text
opt-out = true
```

because the recipient never actually opted out.

This distinction preserves the historical accuracy of the data.

---

# 23. Provenance

The plugin SHALL preserve provenance for explicit actions.

At minimum, opt-in and opt-out records SHOULD identify:

```text
action
timestamp
campaign_id
source
reason
```

Where useful, additional information MAY be retained, such as:

```text
action_code_id
original_list/source
action mechanism
```

The objective is to make questions such as the following answerable:

> When did this person opt in?

> How did this person opt in?

> Which campaign caused this person to opt out?

> Why does this WordPress user exist?

That final question is particularly important because an account may have been created as the direct consequence of either an opt-in or an opt-out.

---

# 24. Account Creation Provenance

When the plugin creates a WordPress user because of an email action, it SHOULD record why the account was created.

Conceptually:

```text
account_created_by = email_action
account_created_reason = opt_out
campaign_id = campaign-123
```

or:

```text
account_created_by = email_action
account_created_reason = opt_in
campaign_id = campaign-123
```

This information MAY be incorporated into the opt-in/opt-out metadata rather than stored separately, provided the origin of the account remains determinable.

---

# 25. Idempotency

Recipient actions SHALL be idempotent where practical.

If a recipient performs the same opt-out operation repeatedly:

```text
Opt out
Opt out
Opt out
```

the result SHALL remain:

```text
OPTED_OUT
```

Repeated use MUST NOT:

* Create duplicate WordPress users.
* Resubscribe the user.
* Produce inconsistent metadata.
* Cause an application-level failure merely because the action was previously completed.

Likewise, repeated processing of the same opt-in action MUST NOT create duplicate users.

---

# 26. Existing WordPress Users

If the email address associated with an action already belongs to a WordPress user:

```text
Action
   ↓
Email lookup
   ↓
Existing user found
   ↓
DO NOT create user
   ↓
Modify appropriate metadata
```

The plugin SHALL operate on that existing account.

Unrelated user data MUST NOT be modified.

---

# 27. User Creation

When an explicit action requires creation of a new WordPress user, the plugin SHALL create a valid WordPress user using the recipient's email address.

The implementation SHALL determine appropriate values for required WordPress fields such as username and password.

The newly created user MUST NOT receive elevated privileges.

The account SHALL receive the site's normal subscriber-level or otherwise explicitly configured non-privileged role.

Account creation MUST NOT inadvertently grant administrative, editorial, or other privileged access.

---

# 28. Email Sending Integration

The plugin does not itself need to send campaign email.

The external email system will conceptually perform:

```text
Recipient email
      ↓
Campaign ID
      ↓
Request action code(s)
      ↓
Construct individualized email
      ↓
Send
```

The email may contain recipient-specific links generated using the plugin.

The external sender SHOULD consult WordPress opt-out information before sending future marketing email to an address represented by a WordPress user.

An explicit opt-out SHALL be authoritative for suppression.

---

# 29. Separation of Account and Mailing State

A WordPress account MUST NOT automatically imply permission to send marketing email.

The following are distinct concepts:

```text
WordPress account exists
        ≠
Recipient opted in
```

A WordPress user can therefore legitimately exist with:

```text
OPTED_IN
```

or:

```text
OPTED_OUT
```

The reason the account exists may itself be an explicit opt-out.

---

# 30. Security Requirements

Public action codes MUST:

* Be difficult to guess.
* Not expose passwords.
* Not provide WordPress authentication.
* Not expose administrative credentials.
* Not authorize unrelated operations.
* Not permit modification of another recipient's state through simple parameter manipulation.

Administrative APIs MUST use WordPress authentication and capability checks.

Public action endpoints MUST validate their action codes before modifying data.

---

# 31. Invalid Codes

An invalid, unknown, or malformed action code MUST NOT:

* Create a WordPress user.
* Modify an existing WordPress user.
* Create opt-in metadata.
* Create opt-out metadata.

The public response SHOULD avoid unnecessarily revealing whether a particular email address exists in WordPress.

---

# 32. Data Integrity

User creation and metadata modification SHOULD behave as a single logical operation.

For example, an opt-out SHOULD NOT leave the system in this state:

```text
WordPress user created
        ↓
opt-out write failed
        ↓
User appears to have no known state
```

The implementation SHOULD make failures recoverable and SHOULD minimize partially completed operations.

---

# 33. Administrative Visibility

Administrators SHOULD be able to determine for a relevant WordPress user:

* Email address.
* Current explicit mailing state.
* Opt-in information.
* Opt-out information.
* Relevant campaign IDs.
* Relevant timestamps.
* Source/reason for actions.
* Whether an email action caused the creation of the account.

A sophisticated administrative dashboard is not required for Version 1.

---

# 34. Acceptance Criteria

The MVP SHALL satisfy the following:

1. The API accepts an email address that does not currently belong to a WordPress user.
2. Generating an action code does not create a WordPress user.
3. Sending an email does not create a WordPress user.
4. A recipient taking no action does not create a WordPress user.
5. Silence creates neither opt-in nor opt-out metadata.
6. An explicit opt-in creates a WordPress user when none exists.
7. An explicit opt-out creates a WordPress user when none exists.
8. Existing users are reused instead of duplicated.
9. An explicit opt-in records hidden opt-in metadata.
10. An explicit opt-out records hidden opt-out metadata.
11. Action metadata records the relevant campaign ID.
12. Action metadata records an appropriate timestamp.
13. Action metadata identifies the nature/source of the action.
14. The system can determine why an account created through this plugin was created.
15. The code-generation API is restricted to administrators.
16. Public action codes are recipient-specific and unpredictable.
17. Codes can be associated with an explicit purpose.
18. Invalid codes cannot create WordPress users.
19. Invalid codes cannot modify user metadata.
20. Recipients can unsubscribe without logging into WordPress.
21. Recipients can explicitly opt in without already having a WordPress account.
22. Repeated opt-out operations do not create duplicate accounts.
23. Opt-out does not delete the WordPress account.
24. Historical opt-in information is not silently destroyed by an opt-out.
25. No-response/inactivity remains distinct from explicit opt-out.
26. WordPress account existence alone is never treated as proof of opt-in.

---

# 35. Core Business Invariants

The implementation SHALL preserve the following invariants.

### Invariant 1 — Silence Creates Nothing

> **Merely appearing on the external email list, receiving an email, or failing to respond MUST NOT create a WordPress user.**

### Invariant 2 — Explicit Action Creates Identity

> **An explicit opt-in or explicit opt-out SHALL result in a WordPress user existing for that email address.**

If the user does not already exist, the plugin creates the user.

### Invariant 3 — Explicit Action Is Recorded

> **Every completed opt-in or opt-out SHALL be represented in hidden WordPress user metadata with sufficient provenance to establish what happened and why.**

### Invariant 4 — No Duplicate Identity

> **If the email address already belongs to a WordPress user, the plugin SHALL modify that existing user rather than creating another account.**

### Invariant 5 — Silence Is Not Opt-Out

> **Removal of an inactive or non-responsive address from the external mailing list is not equivalent to an explicit opt-out and MUST NOT be recorded as one.**

### Invariant 6 — Opt-Out Is Authoritative

> **Once a user has explicitly opted out, that explicit decision SHALL remain authoritative until superseded by a subsequent explicit opt-in.**

These rules allow the external email list to begin much larger than the WordPress user database while allowing the WordPress database to gradually become a durable record of the recipients who have actually made an explicit decision.
