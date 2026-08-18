# Product Requirements Document: WordPress Email Opt-In / Opt-Out Management Plugin

## 1. Overview

The Email Opt-In / Opt-Out Management plugin provides a WordPress-based system for managing the relationship between a large external email list and WordPress user accounts.

The system begins with a large collection of email addresses whose owners are permitted to receive email but who have **no existing relationship with this website**. In the general case these recipients:

* Have never visited the website.
* Have never created a WordPress account.
* Exist only on an external list maintained outside WordPress.

Campaign email is sent from an ordinary Gmail account (typically via mail merge), not from WordPress. WordPress therefore has no knowledge that a message was sent, to whom, or when. The **only** signal WordPress ever receives is a recipient clicking a link.

The fundamental business rule is:

> **Receiving an email or failing to respond to an email does not create a WordPress user. Explicitly opting in or explicitly opting out does create a WordPress user.**

Because WordPress has no record of the recipient, the link itself must carry the recipient's identity. Each email contains an **action code** that encodes the recipient's email address together with a campaign code. When the recipient clicks either the call-to-action (CTA) link or the unsubscribe link, that code delivers the email address to the website, which then finds or creates the WordPress user and applies the appropriate metadata.

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

The recipient explicitly chooses to opt in by following the CTA link.

```text
Email
  ↓
Click CTA link (carries action code)
  ↓
Decode email address from action code
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
Click unsubscribe link (carries action code)
  ↓
Decode email address from action code
  ↓
Find or create WordPress user
  ↓
Record opt-out metadata
```

If the email address does not already correspond to a WordPress user, the plugin SHALL create one.

Creating a WordPress user as the result of an opt-out is an intentional business requirement. It is how the site retains an authoritative, durable suppression record for an address that otherwise exists only on an external list.

---

# 3. Goals

The plugin SHALL:

* Support recipients who do not currently have WordPress accounts and have never visited the site.
* Generate individualized email action codes for arbitrary external email addresses.
* Encode the recipient's email address inside the action code so no prior WordPress record is required.
* Associate every action code with a pre-generated campaign code.
* Validate the campaign code before allowing any change to user data.
* Allow recipients to explicitly opt in.
* Allow recipients to explicitly opt out.
* Create WordPress users when recipients explicitly opt in.
* Create WordPress users when recipients explicitly opt out.
* Never create WordPress users merely because an email was sent.
* Never create WordPress users because a recipient failed to respond.
* Never log the recipient in as part of an opt-in or opt-out action.
* Maintain detailed hidden metadata describing explicit opt-in and opt-out actions.
* Preserve the provenance of those actions.
* Provide an authoritative WordPress record of explicit recipient decisions.
* Support bulk generation of action codes/URLs suitable for Gmail mail merge.

---

# 4. Non-Goals

Version 1 does not need to provide:

* Email transmission (email is sent from Gmail).
* Email template management.
* Mail merge tooling.
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
* Recipient authentication or login.
* A full CRM.
* A full mailing-list management system.

The plugin DOES maintain a minimal campaign record — campaign code, label, and enabled/disabled status — because the campaign code is the authorization secret and MUST be validated. It does not manage campaign content, scheduling, or sending.

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
Generate action code (campaign code + encoded address)
    ↓
Send email from Gmail
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

The email address is obtained by decoding the action code. No other identifier is required.

When an explicit action occurs:

```text
Decode email from action code
       ↓
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
* Campaign code.
* Relevant contextual information about the action.

Conceptually:

```json
{
  "timestamp": "2026-08-17T17:00:00Z",
  "reason": "Recipient explicitly opted in",
  "source": "email_campaign",
  "campaign_code": "A7K2Q",
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
* Campaign code.
* Relevant contextual information.

Conceptually:

```json
{
  "timestamp": "2026-08-17T17:30:00Z",
  "reason": "Recipient explicitly unsubscribed",
  "source": "email_campaign",
  "campaign_code": "A7K2Q",
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
Campaign: A7K2Q

2026-10-02
Recipient explicitly opted out.
Campaign: M3XP9
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

# 13. Campaign Codes

A **campaign code** is a short shared secret created in advance of a mailing. It is the authorization element of every action code: without a valid campaign code, no user data may be created or modified.

Campaign codes SHALL be:

* Exactly **five characters** long.
* Drawn from a fixed, unambiguous, URL-safe alphabet (for example uppercase letters and digits, excluding visually confusable characters such as `O`, `0`, `I`, and `1`).
* Randomly generated by the plugin using a cryptographically secure source.
* Created and stored in advance of the campaign.
* Unique across campaigns.

Example campaign codes:

```text
A7K2Q
M3XP9
R8TF4
```

The plugin SHALL store, for each campaign, at minimum:

```text
campaign_code   (5 characters, unique)
label           (human-readable, e.g. "august-2026 launch")
created_at
status          (active | disabled)
```

Administrators SHALL be able to create a campaign code and to **disable** one. A disabled campaign code MUST be rejected by the public endpoints. Disabling is the mechanism for shutting down a campaign code that has been leaked, scraped, or abused.

Action codes referencing an unknown or disabled campaign code MUST be rejected without creating or modifying any user data.

---

# 14. Action Code Format

An action code is a single opaque-looking string composed of two parts:

```text
[ 5-character campaign code ][ encoded email address ]
```

For example:

```text
A7K2QNBSWY3DPEBLW64TMMQ
└───┘└─────────────────┘
  │           └── encoded form of john@example.com
  └── campaign code
```

Requirements:

* The campaign code SHALL occupy the first five characters of the action code.
* The remainder of the action code SHALL be the encoded email address.
* The action code SHALL be URL-safe and require no percent-encoding.
* The action code SHALL be decodable **without any database lookup of the recipient**, because no recipient record exists.
* A separator character between the two parts is OPTIONAL; the fixed five-character prefix length makes one unnecessary.

The action code appears in the email URL as a single query parameter or path segment. For example:

```text
https://example.com/email-action/?c=A7K2QNBSWY3DPEBLW64TMMQ
```

The recipient's plaintext email address MUST NOT appear directly in the URL:

```text
https://example.com/unsubscribe?email=john@example.com     ← NOT acceptable
```

---

# 15. Email Address Encoding

The encoded portion of the action code SHALL be produced by a **deterministic, reversible transformation** of the recipient's email address. This is a simple text substitution, not authenticated encryption. It is an obfuscation measure — deliberately chosen, with the trade-offs recorded in Section 18.

The encoding SHALL:

* Canonicalize the address before encoding (trim whitespace, lowercase).
* Be fully reversible, producing the exact canonical address on decode.
* Produce only characters from a fixed URL-safe alphabet.
* Avoid characters whose case may be altered by email clients or link rewriters; a single-case alphabet (for example a Base32-style alphabet) is RECOMMENDED.
* Avoid producing a string that visually resembles the original address.
* Require no per-recipient state, no database row, and no prior WordPress record.

A representative implementation is: canonicalize the address, encode it with a Base32-style byte encoding, then map the result through a site-specific shuffled alphabet stored in plugin configuration. Decoding reverses both steps. The exact transformation is an implementation decision, provided the properties above hold.

The plugin SHALL reject an action code whose encoded portion does not decode to a syntactically valid email address, without creating or modifying any data.

**RECOMMENDED hardening (optional):** append a short check value — for example two to four characters derived from a keyed hash (HMAC) of the canonical email address plus the campaign code, using a site secret — to the end of the action code. This costs a few characters of URL length and provides two benefits: truncated or mangled links are detected and rejected rather than silently creating a junk user, and an attacker who has seen a valid code cannot forge codes for arbitrary other addresses under the same campaign. If this check value is omitted, the limitation described in Section 18 applies in full.

---

# 16. Action Code Generation API

The plugin SHALL provide an administrator-only WordPress API for generating recipient-specific action codes.

The API SHALL accept at minimum:

```text
email
campaign_code
```

The API SHALL also support **bulk generation**: given a campaign code and a list of email addresses, it returns an action code and full action URL for each address, in a form suitable for import into a Gmail mail merge (for example CSV with `email` and `action_url` columns).

The supplied email MAY correspond to:

* An existing WordPress user; or
* An email address for which no WordPress user currently exists.

Both cases SHALL be valid.

The API MUST reject an unknown or disabled campaign code.

The API MUST NOT create a WordPress user merely because a code was requested.

The API MUST NOT store the recipient's email address as a side effect of generating a code. Code generation is a pure transformation.

---

# 17. API Authorization

The code-generation API MUST require authenticated administrator-level authorization.

Unauthenticated requests SHALL be rejected.

Authenticated users without the required capability SHALL be rejected.

The exact WordPress capability used is an implementation decision, but it SHOULD normally be an administrator-level capability such as `manage_options` or a dedicated capability assigned to administrators.

Campaign code creation and disabling SHALL be subject to the same authorization requirement.

---

# 18. Security Model and Accepted Risks

The action code is **not** a cryptographic capability token. Its design is a deliberate trade-off in favor of stateless generation from a Gmail mail merge against an external list of addresses that have no WordPress records.

The security model is:

| Element | Purpose |
| ------- | ------- |
| Encoded email address | Delivers recipient identity without a prior WordPress record; obscures the address in the URL |
| Campaign code | Authorization — proves the request derives from a real campaign |
| Campaign disable switch | Revocation — shuts down a leaked or abused campaign code |
| Explicit click on the landing page | Confirms the recipient intends the action |

The following limitations are **known and accepted**:

1. **The email encoding is obscurity, not encryption.** Anyone who obtains a code and determines the substitution scheme can recover the recipient's email address. Action-code URLs are therefore to be treated as containing personal data and MUST NOT be published, logged to third parties, or exposed in analytics.
2. **Without the optional check value of Section 15, a leaked code compromises its whole campaign.** A recipient who has one valid code holds a valid campaign code and, once the substitution is understood, can construct codes for arbitrary addresses within that campaign. The maximum consequence is the creation of a subscriber-level WordPress user carrying opt-in or opt-out metadata for an address the attacker chose. Implementing the keyed check value removes this exposure and is RECOMMENDED.
3. **Referrer and link-scanner leakage.** Corporate mail scanners and link previewers may fetch action URLs. Because a mere page view performs no action (Section 24), such fetches MUST NOT change any state.

Given these limits, the blast radius of the public endpoint is intentionally bounded to a single capability: setting opt-in or opt-out metadata on the user identified by the code, creating that user if necessary. Nothing else is reachable.

The plugin SHOULD rate-limit the public endpoints by IP address and SHOULD log rejected codes so abuse of a campaign code is detectable.

---

# 19. Action Scope

Possession of an action code authorizes only the email-related action performed on the public landing page for the address encoded in that code.

The action taken — opt-in or opt-out — is determined by:

```text
the endpoint the recipient reached (CTA link vs. unsubscribe link)
                    +
the explicit action the recipient confirms on that page
```

The same action code MAY therefore appear in both the CTA link and the unsubscribe link of a single email; the two links differ by endpoint, not by code.

An action code MUST NOT authorize:

* Logging in.
* Changing a password.
* Changing an email address.
* Changing a role or capability.
* Reading or modifying any other user's data.
* Any account operation other than recording opt-in or opt-out metadata.

---

# 20. Campaign Association

Every generated action code SHALL be associated with a campaign code.

Campaign codes are created inside the plugin in advance of the mailing (Section 13) and supplied to the external mailing process. A campaign MAY additionally carry a human-readable label supplied by the operator:

```text
A7K2Q  →  "beta-test-001"
M3XP9  →  "august-2026"
R8TF4  →  "plugin-launch-001"
```

Campaign association provides both authorization for the public endpoint and provenance for subsequent opt-in or opt-out actions.

---

# 21. Opt-Out Workflow

The unsubscribe workflow SHALL be:

```text
External email address
        ↓
Administrator creates campaign code
        ↓
Administrator generates action codes in bulk
        ↓
Gmail mail merge sends email containing unsubscribe URL
        ↓
Recipient follows URL
        ↓
Validate campaign code portion
        ↓
Decode email address portion
        ↓
Public unsubscribe page (no state change yet)
        ↓
Recipient explicitly chooses Unsubscribe
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

The recipient MUST NOT be logged into WordPress as a result of this workflow.

---

# 22. Unsubscribe Page

A valid unsubscribe code SHALL bring the recipient to a public page explaining the unsubscribe action.

The recipient SHALL be provided with a clear action such as:

**Unsubscribe**

The recipient MUST NOT be required to:

* Log in.
* Supply a WordPress username.
* Supply a WordPress password.
* Enter their email address again.
* Explain why they are unsubscribing.

The page MAY display the decoded email address so the recipient can confirm which address is being unsubscribed.

Once the recipient explicitly performs the action, the opt-out SHALL be recorded.

---

# 23. Opt-In Workflow

The plugin SHALL support the corresponding explicit opt-in operation, reached through the CTA link in the campaign email.

Conceptually:

```text
External email address
        ↓
Recipient clicks CTA link
        ↓
Validate campaign code portion
        ↓
Decode email address portion
        ↓
Recipient explicitly opts in
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

# 24. Landing Page Scope Limitation

The public landing pages reached by an action code operate under an unauthenticated code, not a WordPress session. Their capability is deliberately minimal.

On these pages the ONLY permitted effect is:

```text
For the single email address encoded in the action code:
  find or create the WordPress user
  set opt-in or opt-out metadata
```

The plugin MUST NOT, on these pages:

* Establish a WordPress login session or authentication cookie for the recipient.
* Issue a password, password-reset link, or magic login link.
* Expose or modify any field of the user record other than the opt-in/opt-out metadata and the values required to create the account.
* Expose or modify any other user's data.
* Accept an email address supplied in the request in place of the one encoded in the code.

Merely loading an action URL MUST NOT change any state. State changes SHALL require the recipient's explicit action on the page (for example a form submission), so that mail scanners, link previewers, and prefetchers cannot record opt-ins or opt-outs on the recipient's behalf.

---

# 25. No-Response Workflow

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

# 26. Future Inactivity Handling

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

# 27. Provenance

The plugin SHALL preserve provenance for explicit actions.

At minimum, opt-in and opt-out records SHOULD identify:

```text
action
timestamp
campaign_code
source
reason
```

Where useful, additional information MAY be retained, such as:

```text
original_list/source
action mechanism
```

The objective is to make questions such as the following answerable:

> When did this person opt in?

> How did this person opt in?

> Which campaign caused this person to opt out?

> Why does this WordPress user exist?

That final question is particularly important because an account may have been created as the direct consequence of either an opt-in or an opt-out, by someone who had never visited the site.

---

# 28. Account Creation Provenance

When the plugin creates a WordPress user because of an email action, it SHOULD record why the account was created.

Conceptually:

```text
account_created_by = email_action
account_created_reason = opt_out
campaign_code = A7K2Q
```

or:

```text
account_created_by = email_action
account_created_reason = opt_in
campaign_code = A7K2Q
```

This information MAY be incorporated into the opt-in/opt-out metadata rather than stored separately, provided the origin of the account remains determinable.

---

# 29. Idempotency

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

Because the action code is stateless and reusable, the same code MAY be followed any number of times, including after the campaign has ended, unless its campaign code has been disabled.

---

# 30. Existing WordPress Users

If the email address decoded from an action code already belongs to a WordPress user:

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

Unrelated user data MUST NOT be modified. In particular, an action code MUST NOT be usable to alter an existing account's role, password, email address, or any profile field.

---

# 31. User Creation

When an explicit action requires creation of a new WordPress user, the plugin SHALL create a valid WordPress user using the recipient's decoded email address.

The implementation SHALL determine appropriate values for required WordPress fields such as username and password.

The generated password SHALL be a strong random value that is never displayed to the recipient and never sent by email.

The newly created user MUST NOT receive elevated privileges.

The account SHALL receive the site's normal subscriber-level or otherwise explicitly configured non-privileged role.

Account creation MUST NOT inadvertently grant administrative, editorial, or other privileged access.

Account creation MUST NOT send a WordPress "new user" notification to the recipient unless the site explicitly configures it, since the recipient did not register for an account.

---

# 32. Email Sending Integration

The plugin does not itself send campaign email. Campaign email is sent from a Gmail account by mail merge.

The external process conceptually performs:

```text
Administrator creates campaign code in WordPress
      ↓
Administrator submits recipient list to the code-generation API
      ↓
Plugin returns email + action URL for each recipient
      ↓
Export to CSV / spreadsheet
      ↓
Gmail mail merge builds individualized email
      ↓
Send
```

Each email contains recipient-specific CTA and unsubscribe URLs generated by the plugin.

The external sender SHOULD consult WordPress opt-out information before sending future marketing email to an address represented by a WordPress user.

An explicit opt-out SHALL be authoritative for suppression.

---

# 33. Separation of Account and Mailing State

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

# 34. Security Requirements

Public action codes MUST:

* Contain a valid, active campaign code before any data is written.
* Not expose passwords.
* Not provide WordPress authentication or establish a session.
* Not expose administrative credentials.
* Not authorize unrelated operations.
* Not permit modification of a user other than the one encoded in the code.
* Not accept a recipient-supplied email parameter that overrides the encoded address.

Administrative APIs MUST use WordPress authentication and capability checks.

Public action endpoints MUST validate the campaign code and successfully decode the email address before modifying data.

Public action endpoints SHOULD be rate-limited and SHOULD log rejected codes.

Action URLs are personal data by construction (Section 18) and MUST NOT be forwarded to third-party analytics or exposed in publicly readable logs.

The accepted risks in Section 18 are the authoritative statement of what this design does not defend against.

---

# 35. Invalid Codes

An action code is invalid if it is malformed, too short, references an unknown or disabled campaign code, fails its check value (where implemented), or does not decode to a syntactically valid email address.

An invalid, unknown, or malformed action code MUST NOT:

* Create a WordPress user.
* Modify an existing WordPress user.
* Create opt-in metadata.
* Create opt-out metadata.

The public response SHOULD present a single generic failure message. It SHOULD NOT reveal:

* Whether a particular email address exists in WordPress.
* Whether the campaign code portion was valid.
* Which portion of the code failed validation.

---

# 36. Data Integrity

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

# 37. Administrative Visibility

Administrators SHOULD be able to determine for a relevant WordPress user:

* Email address.
* Current explicit mailing state.
* Opt-in information.
* Opt-out information.
* Relevant campaign codes.
* Relevant timestamps.
* Source/reason for actions.
* Whether an email action caused the creation of the account.

Administrators SHOULD also be able to view the list of campaign codes with their labels, creation dates, and status.

A sophisticated administrative dashboard is not required for Version 1.

---

# 38. Acceptance Criteria

The MVP SHALL satisfy the following:

1. The API accepts an email address that does not currently belong to a WordPress user.
2. Generating an action code does not create a WordPress user.
3. Generating an action code does not store the recipient's email address.
4. Sending an email does not create a WordPress user.
5. A recipient taking no action does not create a WordPress user.
6. Silence creates neither opt-in nor opt-out metadata.
7. An administrator can create a five-character campaign code in advance of a mailing.
8. An administrator can disable a campaign code, after which action codes bearing it are rejected.
9. An action code consists of a five-character campaign code followed by the encoded email address.
10. An action code decodes to the original email address with no database lookup.
11. An action code is URL-safe and contains no plaintext email address.
12. Bulk generation returns an action URL per recipient in a form usable by Gmail mail merge.
13. An explicit opt-in creates a WordPress user when none exists.
14. An explicit opt-out creates a WordPress user when none exists.
15. Existing users are reused instead of duplicated.
16. An explicit opt-in records hidden opt-in metadata.
17. An explicit opt-out records hidden opt-out metadata.
18. Action metadata records the relevant campaign code.
19. Action metadata records an appropriate timestamp.
20. Action metadata identifies the nature/source of the action.
21. The system can determine why an account created through this plugin was created.
22. The code-generation API and campaign management are restricted to administrators.
23. An action code with an unknown or disabled campaign code cannot create or modify a user.
24. An action code whose encoded portion does not decode to a valid email address cannot create or modify a user.
25. Invalid codes cannot create WordPress users.
26. Invalid codes cannot modify user metadata.
27. Failure responses do not reveal which portion of the code was invalid or whether an address exists.
28. Loading an action URL without confirming the action changes no state.
29. Recipients can unsubscribe without logging into WordPress.
30. Recipients can explicitly opt in without already having a WordPress account.
31. No action performed through an action code logs the recipient in.
32. An action code cannot change a user's role, password, email address, or any field other than opt-in/opt-out metadata.
33. Users created through an action code receive a non-privileged role.
34. Repeated opt-out operations do not create duplicate accounts.
35. Opt-out does not delete the WordPress account.
36. Historical opt-in information is not silently destroyed by an opt-out.
37. No-response/inactivity remains distinct from explicit opt-out.
38. WordPress account existence alone is never treated as proof of opt-in.

---

# 39. Core Business Invariants

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

### Invariant 7 — The Code Carries the Identity

> **The recipient's identity comes from the action code and from nowhere else. The plugin MUST NOT accept a recipient-supplied email address, and MUST NOT act on any address other than the one decoded from the code.**

### Invariant 8 — The Code Is Not a Login

> **An action code SHALL NOT authenticate the recipient. Its entire authority is to set opt-in or opt-out metadata on one email address, creating that user if necessary. No other account operation is reachable through it.**

These rules allow the external email list to begin much larger than the WordPress user database while allowing the WordPress database to gradually become a durable record of the recipients who have actually made an explicit decision.
