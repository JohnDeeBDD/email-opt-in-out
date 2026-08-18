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

Opt-in and opt-out are recorded **per campaign**. The plugin maintains one hidden user metadata entry per campaign the user has acted on, keyed by that campaign's code (`email_campaign_{CAMPAIGN_CODE}`), containing information about the action. A person may therefore be opted in to one campaign and opted out of another at the same time.

Campaigns themselves are managed by administrators from a CRUD screen under the WordPress **Tools** menu, and the tracking codes used in the emails are produced by an administrator-only API.

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
Record opt-in metadata for that campaign
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
Record opt-out metadata for that campaign
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
* Provide an administrator-only CRUD screen, under the WordPress **Tools** menu, for creating, listing, renaming, disabling, and deleting campaigns.
* Assign each campaign a plugin-generated campaign code at creation time.
* Expose the tracking (action) code through an administrator-only API.
* Allow recipients to explicitly opt in.
* Allow recipients to explicitly opt out.
* Create WordPress users when recipients explicitly opt in.
* Create WordPress users when recipients explicitly opt out.
* Never create WordPress users merely because an email was sent.
* Never create WordPress users because a recipient failed to respond.
* Never log the recipient in as part of an opt-in or opt-out action.
* Maintain detailed hidden metadata describing explicit opt-in and opt-out actions, recorded **per campaign** under a campaign-specific metadata key.
* Allow a single user to hold independent opt-in / opt-out states for different campaigns.
* Preserve the provenance of those actions.
* Provide an authoritative WordPress record of explicit recipient decisions.

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

The plugin DOES maintain a campaign record — name, campaign code, creation date, and enabled/disabled status — with a full administrative CRUD interface (Section 14), because the campaign code is the authorization secret, MUST be validated, and identifies the per-campaign opt-in/opt-out metadata. It does not manage campaign content, scheduling, or sending.

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

# 8. Hidden Per-Campaign User Metadata

Opt-in and opt-out are **per campaign**, not global. A recipient who unsubscribes from one campaign has said nothing about any other campaign.

The plugin SHALL therefore store one hidden user metadata entry **per campaign that the user has explicitly acted on**. The metadata key SHALL be:

```text
email_campaign_{CAMPAIGN_CODE}
```

For example:

```text
email_campaign_A7K2Q
email_campaign_M3XP9
```

Requirements:

* The key prefix SHALL be the literal string `email_campaign_`.
* The suffix SHALL be the campaign code exactly as stored on the campaign record (Section 13), in its canonical case.
* The key SHALL make the metadata identifiable to a single campaign by inspection, with no join or lookup.
* A user MAY hold any number of these entries — one per campaign they have acted on.
* The absence of an entry for a campaign means the user has never explicitly acted on that campaign. It is neither an opt-in nor an opt-out (Section 27).

Each entry SHALL record the two explicit action concepts for that campaign:

* `opt-in`
* `opt-out`

Conceptually:

```json
{
  "campaign_code": "A7K2Q",
  "state": "OPTED_IN",
  "opt_in":  { "timestamp": "2026-08-17T17:00:00Z", "reason": "...", "source": "email_campaign", "details": {} },
  "opt_out": null
}
```

Whether the two actions are held in one metadata entry per campaign or in separate per-campaign keys is an implementation decision, provided that:

* Every stored value is attributable to exactly one campaign by its key.
* Both the opt-in and the opt-out history for a campaign remain recoverable (Section 11).

These values SHALL NOT appear as ordinary user-editable profile fields.

They represent historical explicit actions rather than merely UI preferences.

An implementation MAY additionally apply WordPress's protected-meta convention (a leading underscore, `_email_campaign_{CAMPAIGN_CODE}`) to keep the values out of the default custom-fields UI, provided the `email_campaign_` + campaign code structure is preserved.

---

# 9. Opt-In Metadata

When a recipient explicitly opts in, the plugin SHALL record `opt-in` metadata **under the metadata key for the campaign whose code appeared in the action code**.

The metadata SHOULD contain sufficient information to establish:

* That an explicit opt-in occurred.
* Which campaign it applies to.
* Timestamp.
* Reason.
* Source.
* Campaign code.
* Relevant contextual information about the action.

Conceptually:

```text
meta_key: email_campaign_A7K2Q
```

```json
{
  "campaign_code": "A7K2Q",
  "state": "OPTED_IN",
  "opt_in": {
    "timestamp": "2026-08-17T17:00:00Z",
    "reason": "Recipient explicitly opted in",
    "source": "email_campaign",
    "details": {}
  }
}
```

An opt-in SHALL affect only the campaign identified by the action code. It MUST NOT alter, create, or clear the metadata of any other campaign.

The exact serialization and database representation are implementation decisions.

---

# 10. Opt-Out Metadata

When a recipient explicitly opts out, the plugin SHALL record `opt-out` metadata **under the metadata key for the campaign whose code appeared in the action code**.

The metadata SHOULD contain:

* Which campaign it applies to.
* Timestamp.
* Reason.
* Source.
* Campaign code.
* Relevant contextual information.

Conceptually:

```text
meta_key: email_campaign_M3XP9
```

```json
{
  "campaign_code": "M3XP9",
  "state": "OPTED_OUT",
  "opt_out": {
    "timestamp": "2026-08-17T17:30:00Z",
    "reason": "Recipient explicitly unsubscribed",
    "source": "email_campaign",
    "details": {}
  }
}
```

An opt-out SHALL affect only the campaign identified by the action code. It MUST NOT alter, create, or clear the metadata of any other campaign.

An opt-out MUST NOT delete the WordPress user.

---

# 11. Historical Information

Opt-in and opt-out information represents events in the user's relationship with the mailing system. Because the metadata is per campaign, the history is kept per campaign.

The plugin SHOULD preserve sufficient information to understand how the user's current state for each campaign was reached.

For example:

```text
email_campaign_A7K2Q
  2026-08-17  User created because recipient explicitly opted in.
  2026-11-04  Recipient explicitly opted out of this campaign.

email_campaign_M3XP9
  2026-10-02  Recipient explicitly opted in.
```

Within a campaign, an opt-out MUST NOT automatically erase historical opt-in information.

Likewise, a later opt-in SHOULD NOT destroy the historical fact that an earlier opt-out occurred for that campaign.

Across campaigns, an action on one campaign MUST NOT modify or erase the record of any other campaign.

The storage implementation MAY evolve into an event/history model per campaign if multiple actions must be retained.

---

# 12. Current State

The system SHALL be capable of determining a WordPress user's current mailing state **for a given campaign** from that campaign's metadata entry.

At minimum, the following per-campaign states must be distinguishable:

```text
OPTED_IN        an explicit opt-in is the latest action for this campaign
OPTED_OUT       an explicit opt-out is the latest action for this campaign
NO_RECORD       no metadata entry exists for this campaign
```

`NO_RECORD` MUST NOT be treated as either an opt-in or an opt-out.

States are independent across campaigns. The following is a valid and expected condition for a single user:

```text
email_campaign_A7K2Q  →  OPTED_IN
email_campaign_M3XP9  →  OPTED_OUT
(no entry for R8TF4)  →  NO_RECORD
```

The plugin SHALL also be able to report a derived, cross-campaign summary for a user — at minimum, the campaigns they have opted in to and the campaigns they have opted out of. This summary is **derived**; the per-campaign entries remain the authoritative record.

Whether an opt-out on one campaign should suppress sending for other campaigns is a policy decision belonging to the external mailing process, not to this plugin. The plugin's obligation is to report the per-campaign facts accurately.

For addresses that have never explicitly acted and therefore have no WordPress user, the plugin does not need to assign a WordPress subscription state.

Those recipients remain the responsibility of the external mailing-list system.

---

# 13. Campaign Codes

A **campaign code** is a short shared secret created in advance of a mailing. It serves two purposes:

1. It is the authorization element of every action code: without a valid campaign code, no user data may be created or modified.
2. It identifies the campaign that an opt-in or opt-out belongs to, and forms the suffix of the per-campaign user metadata key (Section 8).

Campaign codes SHALL be:

* Exactly **five characters** long.
* Drawn from a fixed, unambiguous, URL-safe alphabet (for example uppercase letters and digits, excluding visually confusable characters such as `O`, `0`, `I`, and `1`).
* Randomly generated by the plugin using a cryptographically secure source.
* Assigned automatically when the administrator creates the campaign; the administrator names the campaign, the plugin issues the code.
* Created and stored in advance of the campaign.
* Unique across campaigns.
* Immutable for the life of the campaign record, because existing user metadata keys embed it.
* Never reissued to a different campaign, even after the original campaign is deleted, because historical user metadata may still reference it.

Example campaign codes:

```text
A7K2Q
M3XP9
R8TF4
```

The plugin SHALL store, for each campaign, at minimum:

```text
campaign_code   (5 characters, unique, plugin-generated, immutable)
name            (human-readable, supplied by the administrator, e.g. "August 2026 launch")
created_at
status          (active | disabled)
```

The metadata key for a campaign is derived directly from its code:

```text
campaign_code = A7K2Q   →   meta_key = email_campaign_A7K2Q
```

Administrators SHALL be able to create a campaign and to **disable** one. A disabled campaign code MUST be rejected by the public endpoints. Disabling is the mechanism for shutting down a campaign code that has been leaked, scraped, or abused.

Action codes referencing an unknown or disabled campaign code MUST be rejected without creating or modifying any user data.

Full campaign management — create, list, rename, disable, re-enable, and delete — is described in Section 14.

---

# 14. Campaign Management Screen (Admin)

The plugin SHALL provide a campaign management screen in the WordPress admin, registered under the **Tools** menu.

The screen SHALL provide a complete CRUD interface over campaign records.

### Create

The administrator supplies a **name** only:

```text
Name:  "August 2026 launch"
        ↓
Plugin generates campaign code   →  A7K2Q
Plugin sets created_at           →  2026-08-18
Plugin sets status               →  active
        ↓
Campaign record saved
```

Requirements:

* The name SHALL be required and SHALL be human-readable free text.
* The campaign code SHALL be generated by the plugin per Section 13. The administrator MUST NOT be able to choose or type it.
* Code generation SHALL retry on collision so that the stored code is unique.
* The newly created campaign SHALL be `active` by default.

### Read / List

The screen SHALL list all campaigns showing at minimum:

```text
name | campaign_code | created_at | status
```

It SHOULD additionally show, per campaign:

* The per-campaign metadata key (`email_campaign_{CAMPAIGN_CODE}`), so an administrator can locate the user metadata.
* Counts of recorded opt-ins and opt-outs for that campaign.
* A convenient way to copy the campaign code.

### Update

The administrator SHALL be able to:

* Rename a campaign.
* Change its status between `active` and `disabled`.

The campaign code MUST NOT be editable. Changing a code would orphan every `email_campaign_{CAMPAIGN_CODE}` metadata entry already recorded against it.

### Delete

The administrator SHALL be able to delete a campaign record.

Deletion rules:

* Deletion SHALL require an explicit confirmation step.
* Deletion SHALL remove only the campaign record. It MUST NOT delete, alter, or anonymize any `email_campaign_{CAMPAIGN_CODE}` user metadata. Those entries are the historical record of explicit recipient decisions and survive the campaign.
* After deletion, action codes bearing that campaign code MUST be rejected as unknown (Section 36).
* A deleted campaign's code MUST NOT be reissued to a new campaign.
* The screen SHOULD warn the administrator that **disabling** is the reversible option and that deletion is preferred only for campaigns that were never mailed.

### Access control and hardening

* The screen SHALL be visible only to administrators (Section 18).
* Every create, update, delete, and status change SHALL be a POST protected by a WordPress nonce and a capability check.
* All administrator-supplied input, in particular the campaign name, SHALL be sanitized on save and escaped on output.

---

# 15. Action Code Format

An action code — also referred to as the **tracking code** — is a single opaque-looking string composed of two parts:

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
* The campaign code recovered from the first five characters SHALL determine both the campaign authorization check and the `email_campaign_{CAMPAIGN_CODE}` metadata key written by the action (Section 8).

The action code appears in the email URL as a single query parameter or path segment. The CTA link may carry it on any URL of the site; the unsubscribe link carries it on the unsubscribe endpoint. For example:

```text
https://example.com/summer-launch/?c=A7K2QNBSWY3DPEBLW64TMMQ      ← CTA, opts in silently
https://example.com/email-unsubscribe/?c=A7K2QNBSWY3DPEBLW64TMMQ  ← unsubscribe, asks first
```

The recipient's plaintext email address MUST NOT appear directly in the URL:

```text
https://example.com/unsubscribe?email=john@example.com     ← NOT acceptable
```

---

# 16. Email Address Encoding

The encoded portion of the action code SHALL be produced by a **deterministic, reversible transformation** of the recipient's email address. This is a simple text substitution, not authenticated encryption. It is an obfuscation measure — deliberately chosen, with the trade-offs recorded in Section 19.

The encoding SHALL:

* Canonicalize the address before encoding (trim whitespace, lowercase).
* Be fully reversible, producing the exact canonical address on decode.
* Produce only characters from a fixed URL-safe alphabet.
* Avoid characters whose case may be altered by email clients or link rewriters; a single-case alphabet (for example a Base32-style alphabet) is RECOMMENDED.
* Avoid producing a string that visually resembles the original address.
* Require no per-recipient state, no database row, and no prior WordPress record.

A representative implementation is: canonicalize the address, encode it with a Base32-style byte encoding, then map the result through a site-specific shuffled alphabet stored in plugin configuration. Decoding reverses both steps. The exact transformation is an implementation decision, provided the properties above hold.

The plugin SHALL reject an action code whose encoded portion does not decode to a syntactically valid email address, without creating or modifying any data.

**RECOMMENDED hardening (optional):** append a short check value — for example two to four characters derived from a keyed hash (HMAC) of the canonical email address plus the campaign code, using a site secret — to the end of the action code. This costs a few characters of URL length and provides two benefits: truncated or mangled links are detected and rejected rather than silently creating a junk user, and an attacker who has seen a valid code cannot forge codes for arbitrary other addresses under the same campaign. If this check value is omitted, the limitation described in Section 19 applies in full.

---

# 17. Tracking Code API

The plugin SHALL provide an administrator-only WordPress API that exposes the tracking (action) code and manages campaigns. It is the programmatic counterpart of the admin screen in Section 14.

### Tracking code generation

The generation endpoint SHALL accept at minimum:

```text
email
campaign_code
```

and SHALL return at minimum:

```text
tracking_code   (campaign code + encoded email address)
opt_in_url
opt_out_url
```

The supplied email MAY correspond to:

* An existing WordPress user; or
* An email address for which no WordPress user currently exists.

Both cases SHALL be valid.

The API MUST reject an unknown or disabled campaign code.

The API MUST NOT create a WordPress user merely because a code was requested.

The API MUST NOT store the recipient's email address as a side effect of generating a code. Code generation is a pure transformation.

### Campaign management

The API SHALL expose the same CRUD operations as the Tools screen, so campaigns can be managed programmatically:

```text
list campaigns
create campaign        (name in, campaign code out)
update campaign        (name, status)
delete campaign
```

The campaign code SHALL be assigned by the plugin on create and SHALL be rejected as an input to update (Section 14).

Deleting a campaign through the API is subject to the same rule as the admin screen: the campaign record is removed, and per-campaign user metadata is left untouched.

### Reading recorded state

The API SHOULD expose read access to recorded per-campaign state, so an operator can answer:

* Which users have opted in to campaign `A7K2Q`?
* Which users have opted out of campaign `A7K2Q`?
* What is the per-campaign state of a given user?

Responses containing recipient email addresses or action URLs are personal data (Section 19) and are restricted to administrators.

---

# 18. API Authorization

The tracking code API MUST require authenticated administrator-level authorization.

Unauthenticated requests SHALL be rejected.

Authenticated users without the required capability SHALL be rejected.

The exact WordPress capability used is an implementation decision, but it SHOULD normally be an administrator-level capability such as `manage_options` or a dedicated capability assigned to administrators.

The campaign management screen (Section 14) and every campaign create, update, disable, and delete operation SHALL be subject to the same authorization requirement, whether performed through the admin screen or the API.

Administrative write operations performed from the admin screen SHALL additionally be protected by a WordPress nonce.

---

# 19. Security Model and Accepted Risks

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
2. **Without the optional check value of Section 16, a leaked code compromises its whole campaign.** A recipient who has one valid code holds a valid campaign code and, once the substitution is understood, can construct codes for arbitrary addresses within that campaign. The maximum consequence is the creation of a subscriber-level WordPress user carrying opt-in or opt-out metadata for an address the attacker chose. Implementing the keyed check value removes this exposure and is RECOMMENDED.
3. **Referrer and link-scanner leakage.** Corporate mail scanners and link previewers may fetch action URLs. For unsubscribe links this changes nothing, because the unsubscribe requires a confirming submission (Section 25). For CTA links it does: opt-in is recorded by the visit itself (Section 24), so a scanner that follows a CTA link records that recipient's opt-in. This is accepted deliberately, in exchange for a CTA link that costs the recipient no extra click. Opt-in is the reversible direction — the recipient's own unsubscribe overrides it at any time — and the effect is bounded to a subscriber-level account carrying opt-in metadata for one campaign.

Given these limits, the blast radius of the public endpoint is intentionally bounded to a single capability: setting opt-in or opt-out metadata on the user identified by the code, creating that user if necessary. Nothing else is reachable.

The plugin SHOULD rate-limit the public endpoints by IP address and SHOULD log rejected codes so abuse of a campaign code is detectable.

---

# 20. Action Scope

Possession of an action code authorizes only the email-related action taken for the address encoded in that code.

The action taken — opt-in or opt-out — is determined by:

```text
opt-in   the recipient reached any URL of the site carrying the code
opt-out  the recipient reached the unsubscribe endpoint AND confirmed there
```

The same action code MAY therefore appear in both the CTA link and the unsubscribe link of a single email; the two links differ by destination, not by code. The unsubscribe endpoint and the page hosting it SHALL never record an opt-in, so a mangled unsubscribe link cannot invert the recipient's intent.

An action code MUST NOT authorize:

* Logging in.
* Changing a password.
* Changing an email address.
* Changing a role or capability.
* Reading or modifying any other user's data.
* Any account operation other than recording opt-in or opt-out metadata.

---

# 21. Campaign Association

Every generated action code SHALL be associated with a campaign code.

Campaigns are created inside the plugin in advance of the mailing (Sections 13 and 14) and their codes supplied to the external mailing process. Each campaign carries a human-readable name supplied by the operator:

```text
A7K2Q  →  "beta-test-001"
M3XP9  →  "august-2026"
R8TF4  →  "plugin-launch-001"
```

Campaign association provides three things:

* Authorization for the public endpoint.
* Provenance for subsequent opt-in or opt-out actions.
* The metadata key under which the action is recorded (`email_campaign_{CAMPAIGN_CODE}`), so that opt-in and opt-out are scoped to the campaign that produced them.

---

# 22. Opt-Out Workflow

The unsubscribe workflow SHALL be:

```text
External email address
        ↓
Administrator creates a campaign (Tools screen) and the plugin assigns its code
        ↓
Administrator generates tracking codes in bulk
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
Record opt-out metadata under email_campaign_{CAMPAIGN_CODE}
        ↓
Display confirmation
```

The recipient MUST NOT be required to log into WordPress.

The recipient MUST NOT be logged into WordPress as a result of this workflow.

---

# 23. Unsubscribe Page

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

The page SHOULD make clear that the unsubscribe applies to the campaign the link came from. It MAY display the campaign name.

Once the recipient explicitly performs the action, the opt-out SHALL be recorded.

---

# 24. Opt-In Workflow

The plugin SHALL record an explicit opt-in for any front-end request that carries a valid action code, whatever URL that request was for. Opt-in has no page, no form, no confirmation step and no visible response.

Conceptually:

```text
External email address
        ↓
Recipient follows the CTA link — any URL of the site, carrying the code
        ↓
Validate campaign code portion
        ↓
Decode email address portion
        ↓
Find existing WordPress user
        ↓
If none exists, CREATE USER
        ↓
Record opt-in metadata under email_campaign_{CAMPAIGN_CODE}
        ↓
Serve the requested page, unchanged and unannotated
```

Requirements:

* The opt-in SHALL be recorded silently: the response body, status and template SHALL be exactly what the request would have produced without the code.
* The plugin SHALL NOT require the recipient to be logged in, to confirm, or to interact with the page in any way.
* The action code MAY be appended to any URL of the site. No page setting exists for opt-in, and none is required.
* A code already holding `OPTED_IN` for its campaign SHALL be a no-op, so reloads and repeat clicks do not inflate the recorded counts. A code whose recipient has since opted out SHALL record a fresh opt-in.
* An invalid, unknown or disabled code SHALL be logged and otherwise ignored; the request SHALL still be served normally, disclosing nothing.

The essential requirement is that **the WordPress user is not created until the recipient follows their own personalized link.**

---

# 25. Action Code Scope Limitation

A request carrying an action code operates under that code alone, not a WordPress session. Its capability is deliberately minimal.

The ONLY permitted effect of an action code is:

```text
For the single email address encoded in the action code:
  find or create the WordPress user
  set opt-in or opt-out metadata for the single campaign
  identified by the code's campaign code
```

The plugin MUST NOT, on the strength of an action code:

* Establish a WordPress login session or authentication cookie for the recipient.
* Issue a password, password-reset link, or magic login link.
* Expose or modify any field of the user record other than the opt-in/opt-out metadata for the campaign named in the code, and the values required to create the account.
* Read, modify, or clear the opt-in/opt-out metadata of any other campaign.
* Expose or modify any other user's data.
* Accept an email address supplied in the request in place of the one encoded in the code.

Merely loading the unsubscribe URL MUST NOT change any state: an opt-out SHALL require the recipient's explicit submission on that page, so that mail scanners, link previewers and prefetchers cannot unsubscribe anyone on their behalf. Opt-in is deliberately the exception (Sections 19 and 24): following the personalized CTA link is itself the opt-in, and it is recorded without a confirmation step.

---

# 26. No-Response Workflow

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

# 27. Future Inactivity Handling

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

# 28. Provenance

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

> Which campaigns is this person currently opted in to?

> Why does this WordPress user exist?

That final question is particularly important because an account may have been created as the direct consequence of either an opt-in or an opt-out, by someone who had never visited the site.

---

# 29. Account Creation Provenance

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

# 30. Idempotency

Recipient actions SHALL be idempotent where practical.

If a recipient performs the same opt-out operation repeatedly:

```text
Opt out
Opt out
Opt out
```

the result SHALL remain, for that campaign:

```text
OPTED_OUT
```

Repeated use MUST NOT:

* Create duplicate WordPress users.
* Resubscribe the user.
* Produce inconsistent metadata.
* Affect the state of any other campaign.
* Cause an application-level failure merely because the action was previously completed.

Likewise, repeated processing of the same opt-in action MUST NOT create duplicate users. Because a CTA link records its opt-in on sight, repeat visits SHALL be treated as no-ops while the campaign already stands at `OPTED_IN` for that address, so reloads and link scanners do not inflate the recorded action counts.

Because the action code is stateless and reusable, the same code MAY be followed any number of times, including after the campaign has ended, unless its campaign code has been disabled.

---

# 31. Existing WordPress Users

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
Modify the metadata of the campaign named in the code
```

The plugin SHALL operate on that existing account.

Unrelated user data MUST NOT be modified. In particular, an action code MUST NOT be usable to alter an existing account's role, password, email address, or any profile field.

---

# 32. User Creation

When an explicit action requires creation of a new WordPress user, the plugin SHALL create a valid WordPress user using the recipient's decoded email address.

The implementation SHALL determine appropriate values for required WordPress fields such as username and password.

The generated password SHALL be a strong random value that is never displayed to the recipient and never sent by email.

The newly created user MUST NOT receive elevated privileges.

The account SHALL receive the site's normal subscriber-level or otherwise explicitly configured non-privileged role.

Account creation MUST NOT inadvertently grant administrative, editorial, or other privileged access.

Account creation MUST NOT send a WordPress "new user" notification to the recipient unless the site explicitly configures it, since the recipient did not register for an account.

---

# 33. Email Sending Integration

The plugin does not itself send campaign email. Campaign email is sent from a Gmail account by mail merge.

The external process conceptually performs:

```text
Administrator creates a campaign in WordPress (Tools screen) and receives its code
      ↓
Administrator calls the tracking code API for each recipient email
      ↓
Plugin returns tracking code and action URLs for that recipient
      ↓
Collect results into CSV / spreadsheet
      ↓
Gmail mail merge builds individualized email
      ↓
Send
```

Each email contains recipient-specific CTA and unsubscribe URLs generated by the plugin.

The external sender SHOULD consult WordPress opt-out information before sending future marketing email to an address represented by a WordPress user.

Because opt-out is recorded per campaign, the sender SHOULD consult the state for the campaign it is about to send, and MAY apply a broader suppression policy of its own using the cross-campaign summary of Section 12.

An explicit opt-out SHALL be authoritative for suppression of the campaign it was recorded against.

---

# 34. Separation of Account and Mailing State

A WordPress account MUST NOT automatically imply permission to send marketing email.

The following are distinct concepts:

```text
WordPress account exists
        ≠
Recipient opted in
```

A WordPress user can therefore legitimately exist with, for each campaign independently:

```text
OPTED_IN
```

or:

```text
OPTED_OUT
```

or with no record at all for a campaign they were never mailed or never acted on.

The reason the account exists may itself be an explicit opt-out.

---

# 35. Security Requirements

Public action codes MUST:

* Contain a valid, active campaign code before any data is written.
* Not expose passwords.
* Not provide WordPress authentication or establish a session.
* Not expose administrative credentials.
* Not authorize unrelated operations.
* Not permit modification of a user other than the one encoded in the code.
* Not permit modification of any campaign's metadata other than the campaign named in the code.
* Not accept a recipient-supplied email parameter that overrides the encoded address.

Administrative APIs and the campaign management screen MUST use WordPress authentication and capability checks, and administrative write operations MUST be nonce-protected.

Public action endpoints MUST validate the campaign code and successfully decode the email address before modifying data.

Public action endpoints SHOULD be rate-limited and SHOULD log rejected codes.

Action URLs are personal data by construction (Section 19) and MUST NOT be forwarded to third-party analytics or exposed in publicly readable logs.

The accepted risks in Section 19 are the authoritative statement of what this design does not defend against.

---

# 36. Invalid Codes

An action code is invalid if it is malformed, too short, references an unknown, deleted, or disabled campaign code, fails its check value (where implemented), or does not decode to a syntactically valid email address.

An invalid, unknown, or malformed action code MUST NOT:

* Create a WordPress user.
* Modify an existing WordPress user.
* Create opt-in metadata for any campaign.
* Create opt-out metadata for any campaign.

On the unsubscribe endpoint, the public response SHOULD present a single generic failure message. On any other URL the code is simply ignored and the requested page is served unchanged, which reveals nothing by construction. Neither response SHOULD reveal:

* Whether a particular email address exists in WordPress.
* Whether the campaign code portion was valid.
* Which portion of the code failed validation.

---

# 37. Data Integrity

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

# 38. Administrative Visibility

Administrators SHOULD be able to determine for a relevant WordPress user:

* Email address.
* The list of campaigns the user has acted on.
* Current explicit mailing state **per campaign**.
* Opt-in information per campaign.
* Opt-out information per campaign.
* Relevant campaign codes and campaign names.
* Relevant timestamps.
* Source/reason for actions.
* Whether an email action caused the creation of the account, and which campaign caused it.

Administrators SHALL be able to view and manage the list of campaigns — name, code, creation date, and status — through the Tools screen described in Section 14.

Administrators SHOULD be able to see, per campaign, how many opt-ins and opt-outs have been recorded.

A sophisticated administrative dashboard is not required for Version 1.

---

# 39. Acceptance Criteria

The MVP SHALL satisfy the following:

1. The API accepts an email address that does not currently belong to a WordPress user.
2. Generating an action code does not create a WordPress user.
3. Generating an action code does not store the recipient's email address.
4. Sending an email does not create a WordPress user.
5. A recipient taking no action does not create a WordPress user.
6. Silence creates neither opt-in nor opt-out metadata.
7. An administrator can create a campaign from a screen under the Tools menu by supplying a name, and the plugin assigns a five-character campaign code.
8. An administrator can list, rename, disable, re-enable, and delete campaigns from that screen.
9. A campaign's code cannot be edited after creation.
10. Deleting a campaign does not delete or alter any recorded per-campaign user metadata.
11. A deleted or disabled campaign code is rejected by the public endpoints.
12. A tracking code consists of a five-character campaign code followed by the encoded email address.
13. A tracking code decodes to the original email address with no database lookup.
14. A tracking code is URL-safe and contains no plaintext email address.
15. The administrator-only API returns the tracking code and the opt-in and opt-out URLs for a supplied email address and campaign code.
16. An explicit opt-in creates a WordPress user when none exists.
17. An explicit opt-out creates a WordPress user when none exists.
18. Existing users are reused instead of duplicated.
19. An explicit opt-in records hidden opt-in metadata under the key `email_campaign_{CAMPAIGN_CODE}` for the campaign in the code.
20. An explicit opt-out records hidden opt-out metadata under the key `email_campaign_{CAMPAIGN_CODE}` for the campaign in the code.
21. An action on one campaign does not create, modify, or clear metadata for any other campaign.
22. A single user can simultaneously be opted in to one campaign and opted out of another.
23. The absence of a campaign's metadata entry is reported as no record, not as an opt-out.
24. Action metadata records the relevant campaign code.
25. Action metadata records an appropriate timestamp.
26. Action metadata identifies the nature/source of the action.
27. The system can determine why an account created through this plugin was created, and which campaign caused it.
28. The tracking code API, the campaign management screen, and all campaign CRUD operations are restricted to administrators.
29. Administrative write operations are nonce-protected.
30. An action code with an unknown, deleted, or disabled campaign code cannot create or modify a user.
31. An action code whose encoded portion does not decode to a valid email address cannot create or modify a user.
32. Invalid codes cannot create WordPress users.
33. Invalid codes cannot modify user metadata.
34. Failure responses do not reveal which portion of the code was invalid or whether an address exists.
35. Loading an action URL without confirming the action changes no state.
36. Recipients can unsubscribe without logging into WordPress.
37. Recipients can explicitly opt in without already having a WordPress account.
38. No action performed through an action code logs the recipient in.
40. An action code cannot change a user's role, password, email address, or any field other than the opt-in/opt-out metadata of its own campaign.
39. Users created through an action code receive a non-privileged role.
40. Repeated opt-out operations do not create duplicate accounts.
41. Opt-out does not delete the WordPress account.
42. Historical opt-in information for a campaign is not silently destroyed by an opt-out.
43. No-response/inactivity remains distinct from explicit opt-out.
44. WordPress account existence alone is never treated as proof of opt-in.

---

# 40. Core Business Invariants

The implementation SHALL preserve the following invariants.

### Invariant 1 — Silence Creates Nothing

> **Merely appearing on the external email list, receiving an email, or failing to respond MUST NOT create a WordPress user.**

### Invariant 2 — Explicit Action Creates Identity

> **An explicit opt-in or explicit opt-out SHALL result in a WordPress user existing for that email address.**

If the user does not already exist, the plugin creates the user.

### Invariant 3 — Explicit Action Is Recorded

> **Every completed opt-in or opt-out SHALL be represented in hidden WordPress user metadata, under the key of the campaign that produced it, with sufficient provenance to establish what happened and why.**

### Invariant 4 — No Duplicate Identity

> **If the email address already belongs to a WordPress user, the plugin SHALL modify that existing user rather than creating another account.**

### Invariant 5 — Silence Is Not Opt-Out

> **Removal of an inactive or non-responsive address from the external mailing list is not equivalent to an explicit opt-out and MUST NOT be recorded as one.**

### Invariant 6 — Opt-Out Is Authoritative Within Its Campaign

> **Once a user has explicitly opted out of a campaign, that explicit decision SHALL remain authoritative for that campaign until superseded by a subsequent explicit opt-in to the same campaign.**

### Invariant 7 — The Code Carries the Identity

> **The recipient's identity comes from the action code and from nowhere else. The plugin MUST NOT accept a recipient-supplied email address, and MUST NOT act on any address other than the one decoded from the code.**

### Invariant 8 — The Code Is Not a Login

> **An action code SHALL NOT authenticate the recipient. Its entire authority is to set opt-in or opt-out metadata for one campaign on one email address, creating that user if necessary. No other account operation is reachable through it.**

### Invariant 9 — Campaigns Are Independent

> **Opt-in and opt-out are recorded per campaign under `email_campaign_{CAMPAIGN_CODE}`. An action taken on one campaign SHALL NOT create, modify, or clear the state of any other campaign, and a campaign with no metadata entry for a user SHALL NOT be interpreted as either consent or refusal.**

### Invariant 10 — The Campaign Record May Be Deleted, the Decision May Not

> **Deleting a campaign SHALL remove only the campaign record. The per-campaign user metadata recording explicit recipient decisions SHALL survive, and the deleted campaign code SHALL NOT be reissued.**

These rules allow the external email list to begin much larger than the WordPress user database while allowing the WordPress database to gradually become a durable record of the recipients who have actually made an explicit decision.
