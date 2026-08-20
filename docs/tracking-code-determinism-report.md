# Tracking Code Determinism — Review Report

**Date:** 2026-08-18
**Question:** Is the tracking code a deterministic function of the campaign code and the email address?
**Verdict:** **Yes.** Generation is a pure function of `(campaign_code, email)` plus two immutable
site-level settings. Nothing random, time-based, or per-request enters the code. Three caveats about
what "deterministic" means across sites and across configuration changes are listed in
[Caveats](#caveats).

---

## 1. Where the code is generated

There is exactly one generator: `ActionCode::build()` in
`src/aiplugin5055/Codec/ActionCode.php:39`.

```php
public static function build( $campaign_code, $email ) {
	$campaign_code = strtoupper( (string) $campaign_code );
	$canonical     = EmailCodec::canonicalize( $email );
	$payload       = EmailCodec::encode( $canonical, Settings::alphabet() );

	return $campaign_code . $payload . self::check_value( $canonical, $campaign_code );
}
```

Every call site funnels through it:

| Caller | File | Purpose |
| --- | --- | --- |
| `TrackingCodeController::generate()` | `src/aiplugin5055/Rest/TrackingCodeController.php:158` | The admin REST endpoints (`/tracking-code`, `/tracking-codes`) |
| `ActionEndpoint` | `src/aiplugin5055/Frontend/ActionEndpoint.php:148` | Rebuilds the code for the confirm form instead of reflecting the request |

The browser-side code is a thin transport layer only — `src/js/services/TrackingCodeClient.js` POSTs
the addresses and `src/js/ui/TrackingCodeGenerator.js` renders the response as CSV. **No tracking
code is ever constructed in JavaScript**, so there is no second implementation that could drift.

## 2. The three parts, and what each depends on

```
[ 5-char campaign code ][ encoded email ][ 3-char check value ]
   A7K2Q                  HE92XCFK45LAPCMXHWFFLSCDHJC       8T…
```

| Part | Produced by | Inputs |
| --- | --- | --- |
| Campaign prefix | `strtoupper()` on the campaign code | campaign code only |
| Payload | `EmailCodec::encode()` (`src/aiplugin5055/Codec/EmailCodec.php:38`) | canonical email + site alphabet |
| Check value | `ActionCode::check_value()` (`src/aiplugin5055/Codec/ActionCode.php:128`) | canonical email + campaign code + site secret |

Each step is a pure transformation:

* **Canonicalization** — `EmailCodec::canonicalize()` (`EmailCodec.php:27`) is `strtolower( trim( … ) )`.
  So `JOHN@Example.COM`, `  john@example.com  ` and `john@example.com\n` all produce the same code.
* **Payload** — a Base32-style 5-bit repacking of the canonical address bytes, mapped through the
  site alphabet. Straight-line bit arithmetic; same bytes in, same characters out.
* **Check value** — `hash_hmac( 'sha256', $canonical . '|' . $campaign_code, Settings::secret() )`,
  truncated to 3 characters of the base alphabet. HMAC-SHA256 is deterministic, and the truncation
  (`ord( $raw[ $i ] ) % 32`) is a fixed mapping with no modulo bias, since 256 is a multiple of 32.

A grep for randomness across the whole generation path — `Codec/`, `TrackingCodeController.php`,
`Settings.php`, `Urls.php` — turns up `random_int()` and `wp_generate_password()` in exactly one
place: `Settings::create()` (`src/aiplugin5055/Support/Settings.php:158`), which runs **once per
site, on first use**, to mint the shuffled alphabet and the HMAC secret. Those two values are then
treated as immutable for the life of the install, and `add_option()` is used precisely so a
concurrent request cannot clobber an alphabet that has already been handed out.

The campaign code itself is random (`CampaignCodeGenerator::random_code()`), but that randomness
happens once when the campaign is created; by the time it reaches `build()` it is a fixed input read
from the campaign record, not regenerated.

## 3. Verification

I extracted the real `ActionCode` and `EmailCodec` classes, stubbed only `Settings` (fixed alphabet
and secret) and WordPress's `is_email()`, and ran them under PHP 8.4. Results:

| Check | Result |
| --- | --- |
| 1,000 repeated builds of the same `(campaign, email)` | identical every time |
| Email case varied (`JOHN@Example.COM`) | same code |
| Email whitespace / trailing newline | same code |
| Campaign code lowercased (`a7k2q`) | same code |
| Different email, same campaign | different code |
| Same email, different campaign | different code |
| Round-trip `build()` → `parse()` across 7 address shapes × 3 campaigns | canonical address and campaign code recovered exactly |
| Whole code lowercased, or soft-wrapped with CRLFs | still parses to the same address |
| Code truncated by 2 characters | rejected (`bad_check_value`) |
| Campaign prefix swapped to another campaign | rejected (`bad_check_value`) |

The only inputs that failed the round trip were non-ASCII addresses (`ünïcode@example.com`), which
`is_email()` rejects — and the controller rejects them *before* calling `build()`
(`TrackingCodeController.php:143`), so no code is ever issued for them. That is a validation
boundary, not a determinism defect.

Sample output for `A7K2Q` + `john@example.com` under the test alphabet:
`A7K2QHE92XCFK45LAPCMXHWFFLSCDHJC8T` (34 characters).

## 4. Why this matters downstream

Determinism is load-bearing in three places, and all three currently work because of it:

1. **Idempotent regeneration** — an admin who re-runs the generator for the same campaign and the
   same address list gets byte-identical codes, so a re-sent mail merge does not invalidate links
   already in recipients' inboxes.
2. **No stored state per recipient** — nothing is persisted at generation time (PRD §5, §17). The
   code *is* the record, which is what lets the plugin address people who have no WordPress user.
3. **Safe echo on the confirm page** — `ActionEndpoint` rebuilds the code from the parsed parts
   rather than reflecting the raw request. That is only sound because rebuilding is guaranteed to
   reproduce the original code.

## Caveats

These do not contradict the verdict, but they bound it. Determinism is **per-site** and **per
configuration**, not universal.

### C1 — Codes are site-local, by design

The shuffled alphabet and HMAC secret are generated per install. The same `(campaign_code, email)`
produces a *different* code on a staging site than on production. This is deliberate (PRD §16 asks
for a site-specific alphabet), but it means codes cannot be pre-generated off-site, and a
staging→production database migration must carry `aiplugin5055_settings` across or every already-mailed
link breaks. Worth stating explicitly in the operator docs.

### C2 — `check_value_length` is a runtime filter, and changing it invalidates issued codes

`Settings::check_value_length()` (`Settings.php:88`) reads
`apply_filters( 'aiplugin5055_check_value_length', 3 )` on **every call**. The alphabet and secret are
protected by the "generate once, never change" discipline; this one is not. I confirmed the effect:
flipping the filter from 3 to 4 changes the generated code, and codes issued under the old value
then fail to parse (`bad_check_value`).

That is a real footgun — a one-line filter in a theme's `functions.php` silently breaks every link
already in the field. Options, in increasing order of strictness:

* leave it, and document the filter as "set before first use, never change"; or
* persist the effective length into `aiplugin5055_settings` at creation time and ignore the filter
  thereafter, matching how the alphabet and secret are handled.

I have not changed the behaviour — flagging it for your call.

### C3 — No regression test guards this

`tests/` currently contains only smoke tests (`tests/phpunit/SmokeTestCest.php`,
`tests/wpunit/SmokeTest.php`). There is no test asserting that `build()` is stable, that
`build → parse` round-trips, or that case and whitespace normalize. The properties hold today, but
nothing would catch a future change that, say, folded a timestamp or a nonce into the check value.
A small unit test over `ActionCode` and `EmailCodec` — they are pure and need no WordPress bootstrap
beyond `is_email()` — would lock in section 3's table cheaply.

## Summary

| | |
| --- | --- |
| Deterministic given `(campaign_code, email)` on one site | **Yes — confirmed by code review and execution** |
| Randomness in the generation path | None (only one-time site setup) |
| Case / whitespace insensitive | Yes, both for the email and the campaign code |
| Reversible without a database lookup | Yes |
| Duplicate implementation in JS that could drift | None |
| Open items | C2 (filterable check length) — see the addendum |

---

# Addendum, 2026-08-20: one implementation, in `library/`

The report above was written when this repository held the only implementation.
It did not: the Gmail Campaign Manager (`email-agent`) carried a hand-written
PHP port of `ActionCode::build()` and `EmailCodec::encode()` so that it could
build codes offline. Two implementations of a function whose disagreement
cannot be corrected after the fact — the links are already in the recipients'
inboxes — is the risk this addendum removes.

## What changed

* The construction moved to **`library/`**: `SiteSettings`, `EmailCodec`,
  `ActionCode`, `CampaignCode`, `ActionUrls`. Plain PHP, no WordPress, no
  filesystem, no clock, no randomness except `CampaignCode::random()`.
* `Codec\ActionCode`, `Codec\EmailCodec`, `Support\Urls`,
  `Campaigns\CampaignCodeGenerator` and the two constants on `Support\Settings`
  are now delegates. They keep their signatures; the site's behaviour is
  unchanged. What WordPress still owns is where the settings come from
  (`Settings::site_settings()`), what counts as an address (`is_email()`), and
  where the links point (`Urls::opt_out_endpoint()`).
* The campaign manager **deletes its port** and loads
  `/var/www/html/wp-content/plugins/aiplugin5055/src/library/autoload.php` directly.
  A missing or too-old library is a fatal error when its configuration loads,
  before a campaign can be opened.
* Section 3's table is now a committed regression test: `src/library/selftest.php`,
  runnable on the server as `php src/library/selftest.php`, and under Codeception as
  `tests/phpunit/LibraryCest.php`. **This closes C3.** The campaign manager pins
  the same vectors from the other side.

## A bug the duplication was hiding

The campaign manager built unsubscribe links as `<opt_out_path>?c=CODE`, while
`Support\Urls` builds them with `aiplugin5055_action=opt_out` as well.
`ActionEndpoint::current_action()` returns nothing without that parameter, so
the link only worked on a site using the `/email-unsubscribe/` rewrite rule,
which supplies the value itself. On a site serving the flow from a configured
page — the configuration the Tools screen encourages — every unsubscribe link
in a campaign landed on an ordinary page view. Worse, on any other path
`SilentOptIn` would have treated the visit as an **opt-in**: a recipient
clicking "unsubscribe" would have been recorded as opting in.

Both sides now build the link through `ActionUrls::opt_out_url()`, which always
names the action. WordPress reads a registered public query var from `$_GET`
ahead of the rewritten query and both give `opt_out`, so the slug endpoint is
unaffected; the page and plain-query configurations start working.

## C2 stands

`Settings::check_value_length()` is still a runtime filter, and changing it
still invalidates every issued code. The library now makes the consequence
visible from both ends — the length is part of `SiteSettings`, and the campaign
manager has to be configured with the same number — but nothing yet stops a
filter in a theme from changing it. Persisting the effective length into
`aiplugin5055_settings` at creation time remains the fix, and remains your
call.
