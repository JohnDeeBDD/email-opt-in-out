# Email Opt-In / Opt-Out Management (`aiplugin5055`)

Records **explicit, per-campaign** opt-in and opt-out decisions for recipients of an
external email list — people who have never visited the site and have no WordPress
account. Campaign mail goes out from Gmail by mail merge; the only signal WordPress
ever receives is a click, so the link itself carries the recipient's identity.

The governing rule:

> Receiving an email or failing to respond does not create a WordPress user.
> Explicitly opting in or explicitly opting out does.

Implements [`docs/PRD.md`](docs/PRD.md) and [`docs/feature-breakdown.md`](docs/feature-breakdown.md),
structured per [`docs/wordpress-plugin-architecture-guide.md`](docs/wordpress-plugin-architecture-guide.md).

---

## Layout

```
aiplugin5055.php                 Main plugin file: requires and hook wiring only
src/
├── aiplugin5055/
│   ├── Actions/
│   │   ├── ActionRecorder.php   Records an explicit opt-in / opt-out
│   │   └── CampaignState.php    Reads per-campaign state and summaries
│   ├── Admin/
│   │   ├── CampaignsScreen.php  Tools screen controller + write handlers
│   │   ├── CampaignsView.php    Tools screen markup
│   │   └── UserProfileSection.php  Per-user state on the user edit screen
│   ├── Campaigns/
│   │   ├── CampaignCodeGenerator.php  Five-character code generation
│   │   └── CampaignRepository.php     Campaign records (options, not a table)
│   ├── Codec/
│   │   ├── ActionCode.php       campaign code + encoded email + check value
│   │   └── EmailCodec.php       Reversible Base32-style email encoding
│   ├── Frontend/
│   │   ├── ActionEndpoint.php   Public unsubscribe request handling
│   │   ├── ActionPageView.php   Unsubscribe page markup
│   │   ├── PageContentFilter.php  Unsubscribe UI on a configured WP page
│   │   └── SilentOptIn.php      Records opt-in on any URL carrying a code
│   ├── Meta/
│   │   └── MetaKeys.php         email_campaign_{CODE} keys and protection
│   ├── Rest/
│   │   ├── CampaignsController.php   Campaign CRUD over REST
│   │   ├── Permissions.php           Administrator-only authorization
│   │   ├── StateController.php       Recorded-state queries
│   │   └── TrackingCodeController.php Tracking code generation
│   ├── Support/
│   │   ├── RateLimiter.php      Per-IP limits for the public endpoints
│   │   ├── RejectedCodeLog.php  Ring buffer of rejected codes
│   │   ├── Settings.php         Site alphabet + secret (generated once)
│   │   └── Urls.php             Public action URLs
│   └── Users/
│       └── UserProvisioner.php  Find-or-create, never duplicate
├── css/{admin,public}.css
└── js/
    ├── admin.js                 Admin entry module (wiring only)
    ├── frontend.js              Frontend entry module (wiring only)
    ├── services/TrackingCodeClient.js
    └── ui/{ActionForm,CopyButton,DeleteConfirmation,TrackingCodeGenerator}.js
```

No build step. JavaScript ships as ES modules with relative imports and is enqueued
through the Script Modules API (WordPress 6.5+); the pages work without it.

---

## The action code

```
A7K2Q  NBSWY3DPEBLW64TMMQ  X4M
└───┘  └────────────────┘  └─┘
  │            │            └── keyed check value (HMAC, 3 chars)
  │            └── the recipient's address, Base32-encoded through a
  │                site-specific shuffled alphabet
  └── campaign code (authorization + metadata key)
```

* Decodes with no database lookup, because no recipient record exists.
* URL-safe, single case, no `O`/`0`/`I`/`1`, no percent-encoding.
* The check value detects truncated links and stops someone holding one valid code
  from forging codes for other addresses in the same campaign.

The alphabet and secret are generated once per site on first use and never rotate —
codes already in the wild would stop decoding. Set the filter
`aiplugin5055_check_value_length` to `0` to omit the check value (see PRD Section 19
for what that gives up).

---

## Using it

**1. Create a campaign** — *Tools → Email Campaigns*. You supply a name; the plugin
issues the five-character code.

**2. Generate tracking codes** — the same screen has a generator that takes a list of
addresses and returns mail-merge CSV, or call the API:

```bash
curl -X POST https://example.com/wp-json/aiplugin5055/v1/tracking-codes \
  -H 'Content-Type: application/json' \
  -d '{"campaign_code":"A7K2Q","emails":["john@example.com"]}'
```

Generating a code creates no user and stores no address.

**3. Mail merge** — each row carries `opt_in_url` and `opt_out_url`. These URLs are
personal data by construction: keep them out of analytics and shared logs.

**4a. Recipients click the CTA.** The code may ride on *any* URL of the site — a
landing page, a post, the front page — as `?c=CODE`. The visit itself is the opt-in:
the plugin finds or creates the user, writes `email_campaign_{CODE}`, and serves the
page the visitor asked for, unchanged. There is no form, no confirmation, no notice,
and no login — the site says nothing about it. Re-visiting is a no-op while the
address already stands at `OPTED_IN`; an address that has since unsubscribed opts in
again. Opt-in has no page setting: `opt_in_url` defaults to the front page, and the
`aiplugin5055_opt_in_destination` filter (or hand-building the URL) sends recipients
anywhere else.

**4b. Recipients click unsubscribe.** That link still goes to a public page that
states what will happen and does nothing until they submit the form. On submission the
plugin writes the opt-out. Nobody is ever logged in.

---

## REST API

All routes are under `aiplugin5055/v1` and require an authenticated administrator
(`manage_options`, filterable via `aiplugin5055_admin_capability`).

| Method | Route | Purpose |
| --- | --- | --- |
| `POST` | `/tracking-code` | One code + both action URLs |
| `POST` | `/tracking-codes` | Up to 2000 codes for a mail merge |
| `GET` | `/campaigns` | List, with opt-in / opt-out counts |
| `POST` | `/campaigns` | Create (name in, code out) |
| `GET` | `/campaigns/{code}` | One campaign |
| `PUT`/`PATCH` | `/campaigns/{code}` | Rename, or set `active` / `disabled` |
| `DELETE` | `/campaigns/{code}?force=true` | Delete the record only |
| `GET` | `/campaigns/{code}/recipients?state=OPTED_IN` | Who acted |
| `GET` | `/recipients/state?email=…` | One address, every campaign |

Supplying `campaign_code` to create or update is an error: the plugin issues codes, and
they are immutable because recorded metadata keys embed them.

---

## Stored data

Authoritative record, one per campaign the user has acted on:

```
meta_key: email_campaign_A7K2Q
{
  "campaign_code": "A7K2Q",
  "campaign_name": "August 2026 launch",
  "state": "OPTED_OUT",
  "opt_in":  { "timestamp": "…", "first_timestamp": "…", "count": 2,
               "reason": "…", "source": "email_campaign", "details": {…} },
  "opt_out": { … },
  "history": [ { "action": "opt_in", "state": "OPTED_IN", … }, … ],
  "account_created_by": "email_action",
  "account_created_reason": "opt_out"
}
```

`email_campaign_A7K2Q_state` holds just `OPTED_IN` / `OPTED_OUT` so recipient queries
are indexed rather than a full scan. Both keys are marked protected, so neither appears
in the custom-fields UI. Campaign codes contain no underscore, so the two key shapes
cannot collide.

Absence of an entry is `NO_RECORD` — never an opt-out. Deleting a campaign removes the
campaign record only; every recorded decision survives it, and the code is never
reissued.

---

## Filters

| Filter | Default | Purpose |
| --- | --- | --- |
| `aiplugin5055_admin_capability` | `manage_options` | Capability for all admin surfaces |
| `aiplugin5055_new_user_role` | site default role | Role for created users (privileged roles are refused) |
| `aiplugin5055_send_new_user_notification` | `false` | Send WordPress's new-user email |
| `aiplugin5055_check_value_length` | `3` | Check-value characters; `0` disables |
| `aiplugin5055_opt_in_destination` | `home_url( '/' )` | Where CTA links land; the code is appended to it |
| `aiplugin5055_opt_out_slug` | `email-unsubscribe` | Unsubscribe path segment |
| `aiplugin5055_rate_limit` | 60 view / 15 submit / 20 opt_in_probe | Requests per window per IP (the `opt_in_probe` bucket counts only rejected codes) |
| `aiplugin5055_rate_limit_window` | `600` | Window, seconds |
| `aiplugin5055_client_ip` | `REMOTE_ADDR` | Client IP behind a proxy |

Actions: `aiplugin5055_campaign_created`, `aiplugin5055_campaign_updated`,
`aiplugin5055_campaign_deleted`, `aiplugin5055_action_recorded`.

---

## What an action code cannot do

Its entire authority is to set opt-in or opt-out metadata for **one campaign** on **one
address**, creating that user if needed. It cannot log anyone in, issue a password or
reset link, change a role, email address or any profile field, touch another campaign's
metadata, reach another user's data, or accept an address supplied in the request in
place of the encoded one.

Loading a URL that carries a code *does* record the opt-in — that is the point of the
CTA link — so a mail scanner that follows one opts that recipient in. Opt-in is the
reversible direction and the recipient's own unsubscribe always overrides it. Opt-out
is not exposed that way: it still requires the confirming submission on the unsubscribe
page, and the unsubscribe page never records an opt-in.

Changing rewrite slugs requires re-saving *Settings → Permalinks*.
