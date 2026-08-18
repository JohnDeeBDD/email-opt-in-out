# Acceptance Test Guide

This guide is for AI agents writing acceptance tests on plugin testing nodes using the Codeception framework, driving a real Chrome browser through ChromeDriver. Tests run in Docker containers. We use Codeception **Cept files** (procedural) as the primary format.

> **Before writing an acceptance test, check the speed ladder.** Acceptance is the slowest suite — only use it when you need a real browser, visual rendering, or JavaScript execution. If WordPress functions alone are enough, use the WPUnit suite. If no WordPress runtime is needed, use PHPUnit. See [running-tests.md](./running-tests.md#the-speed-ladder) for the full ladder and [running-tests.md](./running-tests.md#concept-statement) for the concept-statement convention used by every test.

## Table of Contents
- [Docker Environment](#docker-environment)
- [Cept File Structure](#cept-file-structure)
- [Comment Conventions](#comment-conventions)
- [WP-CLI for Test Data](#wp-cli-for-test-data)
- [The Db Module](#the-db-module)
- [Screenshots](#screenshots)
- [Wait Strategies](#wait-strategies)
- [Error Handling](#error-handling)

## Docker Environment

The stack is three containers: `wordpress` (Apache, PHP, and WP-CLI), `db` (MySQL, the dev database), and `db_test` (MySQL, the isolated database WPUnit uses).

**There is no Selenium container.** ChromeDriver runs *inside* the `wordpress` container, started by `.devenv/start-chromedriver.sh`, and the suite talks to it directly at `127.0.0.1:9515` — not through a Selenium Grid. `make test-acceptance` and `make test` start it for you; if ChromeDriver is not running, connection failures surface as WebDriver errors on the first browser command. See [running-tests.md](./running-tests.md#runner-commands).

Tests access the WordPress installation at:

```
http://aiplugin5055.localhost
```

Where `aiplugin5055` is the project slug and the site identifier, routed by Traefik. The site URL is configured in `acceptance.suite.yml`.

## Cept File Structure

Every Cept file follows this general structure:

```php
<?php

$I = new AcceptanceTester($scenario);

$I->comment("🎯 Concept: Feature X ensures users can accomplish Y");

$I->comment("🧪 Setting up test data");
$postId = (int) trim(shell_exec(
    "wp post create --post_title='TestPostTitle' --post_content='<p>Test post content.</p>' "
    . "--post_status=publish --porcelain --allow-root --path=/var/www/html"
));
$I->comment('📝 Test post created with ID: ' . $postId);

$I->comment("🔐 Navigating and authenticating");
$I->amOnPage('/');
$I->loginAsAdmin();
$I->amOnPage("/?p={$postId}");
$I->waitForElement('#wpadminbar', 10);

$I->expect("Feature should work as expected");
$I->seeElement('.target-element');
$I->makeScreenshot("initial-state");

$I->comment("🧹 Cleaning up test data");
shell_exec("wp post delete {$postId} --force --allow-root --path=/var/www/html");
$I->comment("✅ Test completed successfully");
```

Every Cept file follows the same flow: concept statement, test data setup, navigation/authentication, actions and assertions, cleanup.

There are **no custom helper methods on `AcceptanceTester`** — it is a stock Codeception actor exposing only the enabled modules' actions. Create and destroy test data with WP-CLI, as above. If you find yourself repeating a setup block across many tests, add the helper to `tests/_support/AcceptanceTester.php` first; do not call a method that does not exist yet.

The concept statement is the first `$I->comment()` call. For the canonical definition and additional examples, see [running-tests.md](./running-tests.md#concept-statement).

## Comment Conventions

**Human-readable** — All narrative text uses Codeception comment functions, i.e. `$I->comment()` and `$I->expect()`. These appear in test reports and execution logs:
```php
$I->comment("🤖 Concept: AI system processes input and generates responses");
$I->comment("🔐 Navigating to login page");
$I->expect("User should be redirected to dashboard");
```

Never put descriptive prose inside PHPDoc blocks. Never use PHP `//` comments for test narration — they won't appear in reports. Inline `//` comments are fine for code-level implementation notes that are not part of the test narrative.

Use emojis generously in `$I->comment()` calls to categorize actions at a glance — a camera 📸 for screenshots, a broom 🧹 for cleanup, a magnifying glass 🔍 for assertions, a lock 🔐 for authentication, and so on. Emojis make the report scrollable and the test phases instantly recognizable.

## WP-CLI for Test Data

Call WP-CLI directly — always specify `--allow-root --path=/var/www/html`:
```php
shell_exec('wp user create testuser test@example.com --role=editor --allow-root --path=/var/www/html');
```

`--porcelain` makes creation commands print the new ID and nothing else, which is what makes the `(int) trim(shell_exec(...))` idiom above reliable.

Always capture resource IDs at creation time so you can clean up reliably. Perform cleanup at the end of the test without wrapping it in try-catch — let meaningful failures surface naturally.

## The Db Module

`acceptance.suite.yml` enables Codeception's `Db` module, so `$I->seeInDatabase()`, `$I->haveInDatabase()`, and `$I->grabFromDatabase()` are available:

```php
$I->seeInDatabase('wp_posts', ['ID' => $postId, 'post_status' => 'publish']);
```

Two things to know before using it:

- **It points at the dev database (`db`), not the test database (`db_test`).** Acceptance tests share data with the running site — that is deliberate, since the browser hits the real site, but it means the rows you create are the site's rows.
- **Nothing is rolled back.** Unlike WPUnit, the acceptance suite has no transaction wrapper. Whatever a test creates survives the run, and leftover data from a failed test is visible to the next one. This is why cleanup at the end of every Cept is mandatory rather than optional.

## Screenshots

Take screenshots at key states with descriptive names. IMPORTANT!: Create comments with anchor links to the screenshots.

`makeScreenshot("name")` always writes to `tests/_output/debug/name.png` on disk. The **URL** that file is reachable at depends on the project type, because the entrypoint mounts the project differently for each:

| `PROJECT_TYPE` | Project lives at | Screenshot URL |
|----------------|------------------|----------------|
| `plugin` | `wp-content/plugins/aiplugin5055` | `http://aiplugin5055.localhost/wp-content/plugins/aiplugin5055/tests/_output/debug/{name}.png` |
| `theme` | `wp-content/themes/aiplugin5055` | `http://aiplugin5055.localhost/wp-content/themes/aiplugin5055/tests/_output/debug/{name}.png` |
| `node` | `{wp-root}/aiplugin5055` | `http://aiplugin5055.localhost/aiplugin5055/tests/_output/debug/{name}.png` |

Note that `node` projects are bind mounted one level below the WordPress root, **not** under `wp-content` — so the plugin/theme path is wrong for them. Check `PROJECT_TYPE` in `.env` if you are unsure which applies.

For a plugin:
```php
$I->makeScreenshot("login-form-displayed");
$I->comment("📸 Screenshot: <a href='http://aiplugin5055.localhost/wp-content/plugins/aiplugin5055/tests/_output/debug/login-form-displayed.png' target='_blank'>Login form</a>");
```

For a node project:
```php
$I->makeScreenshot("login-form-displayed");
$I->comment("📸 Screenshot: <a href='http://aiplugin5055.localhost/aiplugin5055/tests/_output/debug/login-form-displayed.png' target='_blank'>Login form</a>");
```

The `aiplugin5055` placeholder is automatically replaced with your project slug during bootstrap.

Skip screenshots for purely API/backend operations that produce no visual output.

## Wait Strategies

Use explicit waits for test stability — never rely on implicit timing. Always wait on something that is meaningfully not-present until the action you care about has completed:

```php
$I->waitForElement('#wpadminbar', 10);           // Admin bar after login
$I->waitForText('Welcome', 10);                  // Visible text
$I->waitForJS('return jQuery.active == 0', 10);  // AJAX completion
```

Don't wait on `body` or other elements that exist from the moment HTML parsing starts — those waits are no-ops and give false confidence.

## Error Handling

Use try-catch only for non-critical intermediate steps where you want a debug screenshot before re-throwing:

```php
try {
    $I->waitForElement('.target', 10);
    $I->click('.target');
} catch (Exception $e) {
    $I->comment("🐞 Debug - Current URL: " . $I->grabFromCurrentUrl());
    $I->makeScreenshot("error-element-not-found");
    throw $e;
}
```

Do not catch exceptions around final assertions or cleanup — let the test fail meaningfully.

The debug-screenshot pattern is specific to acceptance tests. For PHPUnit/WPUnit error handling, see [phpunit-wpunit-test-guide.md](./phpunit-wpunit-test-guide.md#error-handling).
