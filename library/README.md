# aiplugin5055 action-code library

The one implementation of the action code — the tracking code that travels in a
campaign email — and of the two public URLs derived from it.

```text
A7K2Q  HXCBD62PM4YAK69DHR22YU6GHJU8N  8N
└─┬─┘  └──────────────┬────────────┘  └┬┘
  │                   │                └── keyed check value
  │                   └── the recipient's address, Base32 through the site alphabet
  └── campaign code
```

Two independent systems have to agree on those bytes exactly:

* this plugin, which **decodes** a code arriving on a link, and
* the Gmail Campaign Manager (`email-agent`), which **builds** the codes it
  mails.

A disagreement is not recoverable. The links are already in the recipients'
inboxes and they decode to nothing. So there is no second implementation to
keep in step: the plugin's own classes are thin delegates onto this directory,
and the campaign manager loads this directory from disk.

## What is in here

| Class | Responsibility |
| --- | --- |
| `SiteSettings` | The alphabet, the secret and the check-value length, validated once and passed around as one immutable object. |
| `EmailCodec` | Canonicalization, and the Base32-style encode/decode through the site alphabet. |
| `ActionCode` | `build()` and `parse()`, and the truncated HMAC check value. |
| `CampaignCode` | The shape of a five-character campaign code, and minting one. |
| `ActionUrls` | Appending the code — and, for opt-out, the action parameter — to a URL. |

Every function is pure except `CampaignCode::random()`. No WordPress, no
filesystem, no network, no clock. The same inputs produce the same code in any
process, on any machine, which is the property the whole scheme rests on.

The code is written for PHP 7.4 (the plugin's floor), and runs unchanged on 8.x.

## Using it from another application

The plugin is installed at `/var/www/html/wp-content/plugins/aiplugin5055`, so:

```php
require_once '/var/www/html/wp-content/plugins/aiplugin5055/library/autoload.php';

use aiplugin5055\Library\ActionCode;
use aiplugin5055\Library\ActionUrls;
use aiplugin5055\Library\SiteSettings;

// The alphabet and the secret are the site's `aiplugin5055_settings` option.
// They are secrets: keep them in a 0600 file, never in Git, and never rotate
// them — every link already mailed would stop decoding.
$settings = new SiteSettings($alphabet, $secret, 3);

$code = ActionCode::build('A7K2Q', 'john@example.com', $settings);

$urls = ActionUrls::both(
    'https://example.com/',                  // where the call to action lands
    'https://example.com/email-unsubscribe/', // where the unsubscribe flow is served
    $code
);
```

`AIPLUGIN5055_LIBRARY_VERSION` is defined by `autoload.php`. Check it if you
need to refuse a plugin older than the contract you were built against.

## Checking it

On the server, with no test framework and no WordPress:

```console
$ php /var/www/html/wp-content/plugins/aiplugin5055/library/selftest.php
...
57 checks, 0 failed (library 1.0.0)
```

That runs the committed vectors, the round trip through `parse()`, the
rejection cases and the URL shapes. `tests/phpunit/LibraryCest.php` runs the
same list under Codeception.

It does **not** tell you that a particular site's alphabet and secret were
copied correctly into the campaign manager's configuration — only sending
yourself a code and following both links on the live site does that.

Nothing else in the plugin may be loaded this way: `library/` is the only part
that is safe to run outside WordPress, and the only part with a stable shape
for outside callers.

## Changing it

Treat every change as a change to both systems at once.

* A change that alters the bytes `ActionCode::build()` returns for inputs that
  already work invalidates every mailed link. There is no migration for that;
  it is a thing you do not do.
* `library/README.md`, `docs/tracking-code-determinism-report.md` and the
  campaign manager's PRD Section 11.3 all describe this construction. Keep them
  honest.
* The tests in `tests/phpunit/LibraryCest.php` and `library/selftest.php` pin the construction against
  fixed vectors, and the campaign manager's `tests/Unit/Tracking/` pins the
  same vectors from the other side. Both suites must stay green.
