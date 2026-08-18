# PHPUnit & WPUnit Test Guide

This guide is for AI agents writing PHPUnit and WPUnit tests on testing nodes using the Codeception framework. Tests run in Docker containers.

The two suites use **different test formats**, and the difference is not cosmetic:

| Suite | Format | File name | Class |
|-------|--------|-----------|-------|
| `phpunit` | Cest (class-based, actor injected per method) | `*Cest.php` | plain class, no parent |
| `wpunit` | Test (PHPUnit-style) | `*Test.php` | `extends \Codeception\TestCase\WPTestCase` |

WPUnit uses `WPTestCase` because that is what provides the per-test database transaction — see [Database Rollback](#database-rollback). A Cest in the `wpunit` suite would run, but every row it wrote would leak into the next test.

For browser-driven tests, see [acceptance-test-guide.md](./acceptance-test-guide.md). For the speed ladder, the concept-statement convention, and the runner commands shared across all three suites, see [running-tests.md](./running-tests.md).

## Table of Contents
- [When to Use PHPUnit](#when-to-use-phpunit)
- [When to Use WPUnit](#when-to-use-wpunit)
- [Docker Environment](#docker-environment)
- [PHPUnit: Cest File Structure](#phpunit-cest-file-structure)
- [PHPUnit Examples](#phpunit-examples)
- [WPUnit: Test File Structure](#wpunit-test-file-structure)
- [WPUnit Examples](#wpunit-examples)
- [Comment Conventions](#comment-conventions)
- [Assertions](#assertions)
- [Test Data in WPUnit](#test-data-in-wpunit)
- [Database Rollback](#database-rollback)
- [Cleaning Up Non-Database State](#cleaning-up-non-database-state)
- [Error Handling](#error-handling)

## When to Use PHPUnit

Use the PHPUnit suite when the concept under test involves **pure PHP logic with no WordPress dependency**:

- Data transformation, parsing, or formatting functions
- Utility classes and helper methods
- Business logic that operates on plain PHP inputs/outputs
- String manipulation, math, date handling
- Custom class behavior (constructors, getters, state machines)
- API response parsing (where you supply the raw response data)
- Configuration validation logic
- Any code in `src/` that does not call WordPress functions

PHPUnit tests have **no access** to WordPress functions (`get_option()`, `wp_insert_post()`, `add_filter()`, etc.). Calling them will produce a fatal error. The only Codeception module loaded is `Asserts`.

## When to Use WPUnit

Use the WPUnit suite when the concept under test **requires the WordPress runtime**:

- Hooks and filters (`add_action`, `apply_filters`, `has_filter`)
- Database operations via WordPress APIs (`get_option`, `update_option`, `wp_insert_post`)
- User roles and capabilities (`current_user_can`, `wp_get_current_user`)
- Shortcode registration and rendering (`add_shortcode`, `do_shortcode`)
- Custom post types and taxonomies (`register_post_type`, `get_post_type_object`)
- WordPress HTTP API (`wp_remote_get`, `wp_remote_post`)
- Plugin/theme activation behavior
- Transients, cron events, rewrite rules
- Any code that calls functions defined by WordPress core

WPUnit boots a **full WordPress installation** against an isolated test database (`db_test` container). The `WPLoader` module handles the bootstrap. All WordPress core functions, classes, and globals are available.

## Docker Environment

Both suites execute inside the WordPress Docker container. The project is mounted at `/var/project`. WordPress core lives at `/var/www/html`.

PHPUnit does not connect to a database. WPUnit connects to the isolated test database:

| Setting | Value |
|---------|-------|
| DB Host | `db_test` |
| DB Name | `wp_aiplugin5055_test` |
| DB User | `wp` |
| WP Root | `/var/www/html` |
| Domain  | `aiplugin5055.localhost` |

These values are configured in `tests/wpunit.suite.yml` and substituted during node bootstrap. The database name defaults to `wp_{slug}_test` where hyphens in the slug become underscores (`my-plugin` → `wp_my_plugin_test`), and both the name and the user can be overridden at bootstrap with `--db-test-name` and `--db-user`. Check `.env` for what a given node actually got.

## PHPUnit: Cest File Structure

A Cest file is a PHP class where each public method is a test. The tester actor (`PhpunitTester`) is injected as the first parameter.

```php
<?php

class ExampleFeatureCest
{
    public function testSpecificBehavior(PhpunitTester $I)
    {
        $I->wantToTest('specific behavior works correctly');
        $I->comment("Concept: Feature X transforms input according to business rules");

        // Arrange
        $input = 'raw-data';

        // Act
        $result = MyClass::transform($input);

        // Assert
        $I->assertEquals('expected-output', $result, 'Transform produces correct output');
    }

    public function testEdgeCase(PhpunitTester $I)
    {
        $I->wantToTest('edge case is handled gracefully');
        $I->comment("Concept: Feature X handles empty input without errors");

        $result = MyClass::transform('');

        $I->assertEmpty($result, 'Empty input produces empty output');
    }
}
```

Key rules:
- **One Cest class per file**, in `tests/phpunit/`. The filename must match the class name and end in `Cest` (e.g., `ExampleFeatureCest.php`).
- **Each public method is a test.** Private/protected methods are not executed as tests — use them for shared setup logic within the class.
- **The actor is `PhpunitTester $I`**, injected as the first parameter of every test method.
- **Assertions go through the actor**: `$I->assertEquals(...)`.
- **Shared setup** goes in `_before(PhpunitTester $I)` / `_after(PhpunitTester $I)`.
- **Open every test with a concept statement.** See [running-tests.md](./running-tests.md#concept-statement).

## PHPUnit Examples

**Testing a utility function:**
```php
<?php

class SlugGeneratorCest
{
    public function testBasicSlugGeneration(PhpunitTester $I)
    {
        $I->wantToTest('slug generator converts spaces to hyphens');
        $I->comment("Concept: Slug generator produces URL-safe strings from arbitrary input");

        $result = SlugGenerator::generate('Hello World');

        $I->assertEquals('hello-world', $result);
    }

    public function testSpecialCharacterRemoval(PhpunitTester $I)
    {
        $I->wantToTest('slug generator strips special characters');
        $I->comment("Concept: Slug generator removes non-alphanumeric characters");

        $result = SlugGenerator::generate('Price: $9.99!');

        $I->assertEquals('price-999', $result);
    }
}
```

**Testing a class with state:**
```php
<?php

class ConfigManagerCest
{
    public function testDefaultValues(PhpunitTester $I)
    {
        $I->wantToTest('config manager returns defaults for missing keys');
        $I->comment("Concept: Configuration falls back to defaults when values are not explicitly set");

        $config = new ConfigManager(['timeout' => 30]);

        $I->assertEquals(30, $config->get('timeout'));
        $I->assertNull($config->get('nonexistent'));
        $I->assertEquals('fallback', $config->get('nonexistent', 'fallback'));
    }
}
```

## WPUnit: Test File Structure

WPUnit tests are PHPUnit-style classes extending `\Codeception\TestCase\WPTestCase`:

```php
<?php

class ExampleFeatureTest extends \Codeception\TestCase\WPTestCase
{
    public function testSpecificBehavior(): void
    {
        $this->tester->wantToTest('specific behavior works correctly');
        $this->tester->comment("Concept: Feature X stores its setting through the options API");

        // Arrange
        update_option('my_plugin_mode', 'strict');

        // Act
        $result = MyPlugin::currentMode();

        // Assert
        $this->assertEquals('strict', $result, 'Plugin reads its mode from options');
    }
}
```

Key rules:
- **One class per file**, in `tests/wpunit/`. The filename must match the class name and end in `Test` (e.g., `ExampleFeatureTest.php`). Codeception will not collect a file named `*Cest.php` as a `WPTestCase`.
- **Extend `\Codeception\TestCase\WPTestCase`.** This is what starts the per-test database transaction; a class that does not extend it gets no rollback.
- **Test methods start with `test`** and take no parameters. There is no injected `$I`.
- **Assertions are called on `$this`**: `$this->assertEquals(...)`, not `$I->assertEquals(...)`.
- **Narration goes through `$this->tester`**, the `WpunitTester` actor that Codeception injects: `$this->tester->comment(...)`.
- **Shared setup** goes in `_before()` / `_after()` — see [Cleaning Up Non-Database State](#cleaning-up-non-database-state) for why, and what to do if you need `_setUp()` instead.
- **Open every test with a concept statement.** See [running-tests.md](./running-tests.md#concept-statement).

## WPUnit Examples

**Testing a WordPress hook:**
```php
<?php

class CustomFilterTest extends \Codeception\TestCase\WPTestCase
{
    public function testFilterModifiesContent(): void
    {
        $this->tester->wantToTest('custom filter appends signature to post content');
        $this->tester->comment("Concept: The content filter modifies output for published posts");

        // Register the filter (or trigger plugin code that does)
        add_filter('the_content', [MyPlugin::class, 'appendSignature']);

        $result = apply_filters('the_content', '<p>Original content.</p>');

        $this->assertStringContainsString('Original content', $result);
        $this->assertStringContainsString('Signature', $result);

        // Clean up — hooks are not database state, so nothing rolls this back
        remove_filter('the_content', [MyPlugin::class, 'appendSignature']);
    }
}
```

**Testing with WordPress database functions:**
```php
<?php

class OptionWrapperTest extends \Codeception\TestCase\WPTestCase
{
    public function testSaveAndRetrieveOption(): void
    {
        $this->tester->wantToTest('option wrapper stores and retrieves values');
        $this->tester->comment("Concept: Custom option wrapper persists data through the WordPress options API");

        $wrapper = new OptionWrapper('my_plugin_settings');
        $wrapper->set('color', 'blue');

        $this->assertEquals('blue', $wrapper->get('color'));
        $this->assertEquals('blue', get_option('my_plugin_settings_color'));
    }

    public function testDefaultWhenOptionMissing(): void
    {
        $this->tester->wantToTest('option wrapper returns default for missing options');
        $this->tester->comment("Concept: Option wrapper provides fallback values for unset keys");

        $wrapper = new OptionWrapper('my_plugin_settings');

        $this->assertEquals('red', $wrapper->get('missing_key', 'red'));
    }
}
```

**Testing a shortcode:**
```php
<?php

class GreetingShortcodeTest extends \Codeception\TestCase\WPTestCase
{
    public function testShortcodeRendersWithAttributes(): void
    {
        $this->tester->wantToTest('greeting shortcode renders personalized output');
        $this->tester->comment("Concept: Shortcode processes attributes and produces correct HTML");

        add_shortcode('greeting', [MyPlugin::class, 'renderGreeting']);

        $output = do_shortcode('[greeting name="Alice"]');

        $this->assertStringContainsString('Alice', $output);
        $this->assertStringContainsString('<div', $output);
    }
}
```

**Testing custom post type registration:**
```php
<?php

class CustomPostTypeTest extends \Codeception\TestCase\WPTestCase
{
    public function testPostTypeIsRegistered(): void
    {
        $this->tester->wantToTest('custom post type exists after registration');
        $this->tester->comment("Concept: Plugin registers its custom post type with correct capabilities");

        MyPlugin::registerPostTypes();

        $this->assertTrue(post_type_exists('my_custom_type'));

        $postTypeObject = get_post_type_object('my_custom_type');
        $this->assertEquals('My Custom Items', $postTypeObject->label);
        $this->assertTrue($postTypeObject->public);
    }
}
```

## Comment Conventions

All narrative text uses Codeception comment functions. These appear in test reports and execution logs. The functions are the same in both suites; only how you reach the actor differs.

In PHPUnit Cests, the actor is the injected `$I`:
```php
$I->wantToTest('option wrapper stores values correctly');
$I->comment("Concept: Custom option wrapper persists data through the WordPress options API");
$I->expect("Stored value should match what was saved");
```

In WPUnit tests, the actor is `$this->tester`:
```php
$this->tester->wantToTest('option wrapper stores values correctly');
$this->tester->comment("Concept: Custom option wrapper persists data through the WordPress options API");
$this->tester->expect("Stored value should match what was saved");
```

Never use PHP `//` comments for test narration — they won't appear in reports. Inline `//` comments are fine for code-level implementation notes that are not part of the test narrative.

## Assertions

Both suites load the Codeception `Asserts` module, and `WPTestCase` additionally inherits every PHPUnit assertion. The names are identical; only the receiver differs — `$I->assertEquals(...)` in a PHPUnit Cest, `$this->assertEquals(...)` in a WPUnit test.

```php
// Equality
assertEquals($expected, $actual);
assertNotEquals($expected, $actual);

// Boolean
assertTrue($value);
assertFalse($value);

// Null
assertNull($value);
assertNotNull($value);

// String
assertStringContainsString($needle, $haystack);
assertStringStartsWith($prefix, $string);
assertStringEndsWith($suffix, $string);
assertMatchesRegularExpression($pattern, $string);

// Arrays
assertContains($needle, $array);
assertNotContains($needle, $array);
assertCount($expectedCount, $array);
assertArrayHasKey($key, $array);
assertEmpty($value);
assertNotEmpty($value);

// Type
assertInstanceOf($expectedClass, $object);

// Numeric
assertGreaterThan($expected, $actual);
assertLessThan($expected, $actual);
```

All assertions accept an optional final `$message` parameter describing what failed:
```php
$this->assertEquals('blue', $color, 'Default theme color should be blue');
```

## Test Data in WPUnit

WPUnit runs against an isolated test database. WordPress factory methods and core functions are both available for creating test data directly.

**Factory methods** are the idiomatic way to create fixtures — they fill in sensible defaults and return either an ID or the full object. On a `WPTestCase`, `factory()` is a **static** method, so call it as `static::factory()`:

```php
public function testPostFactoryCreatesPublishedPost(): void
{
    $this->tester->wantToTest('factory produces usable post records');
    $this->tester->comment("Concept: Factory creates fully-formed posts with sensible defaults");

    $postId = static::factory()->post->create([
        'post_title'  => 'Factory Test Post',
        'post_status' => 'publish',
    ]);

    $userId = static::factory()->user->create([
        'role' => 'editor',
    ]);

    $termId = static::factory()->term->create([
        'taxonomy' => 'category',
        'name'     => 'Factory Category',
    ]);

    $this->assertEquals('publish', get_post_status($postId));
    $this->assertTrue(user_can($userId, 'edit_posts'));
    $this->assertEquals('Factory Category', get_term($termId)->name);
}
```

**Core functions** work too, and are appropriate when you need full control over the input:

```php
$postId = wp_insert_post([
    'post_title'   => 'Test Post',
    'post_content' => 'Test content.',
    'post_status'  => 'publish',
]);

$userId = wp_create_user('testuser', 'password123', 'test@example.com');

update_option('my_plugin_key', 'value');
$value = get_option('my_plugin_key');

$term = wp_insert_term('Test Category', 'category');
```

## Database Rollback

`WPTestCase::_setUp()` opens a database transaction before each test and rolls it back afterwards. Anything written through `$wpdb` — posts, users, terms, options, meta — is discarded when the test method ends, so **explicit cleanup of database state is not needed** and tests cannot leak rows into each other.

This is the whole reason the `wpunit` suite uses `WPTestCase` rather than the Cest format the `phpunit` suite uses. A Cest class does not extend `WPTestCase`, so nothing opens the transaction, and every row it writes persists for the rest of the run. If you write a `wpunit` test and find that data from an earlier test is still visible, check that your class actually extends `\Codeception\TestCase\WPTestCase`.

The rollback covers the database and nothing else — see the next section.

## Cleaning Up Non-Database State

The transaction covers the database but not anything else. If your test modifies static class properties, global variables, registered hooks, or the filesystem, reset that state manually using `_before()` / `_after()`:

```php
<?php

class FeatureFlagTest extends \Codeception\TestCase\WPTestCase
{
    /** @var array<string> */
    private array $originalFlags = [];

    public function _before(): void
    {
        // Snapshot the static state before each test.
        $this->originalFlags = FeatureFlags::$enabled;
    }

    public function _after(): void
    {
        // Restore it afterwards so the next test starts clean.
        FeatureFlags::$enabled = $this->originalFlags;
    }

    public function testFlagToggle(): void
    {
        $this->tester->wantToTest('feature flag can be toggled at runtime');
        $this->tester->comment("Concept: FeatureFlags class persists toggled state within a request");

        FeatureFlags::$enabled[] = 'new_dashboard';

        $this->assertContains('new_dashboard', FeatureFlags::$enabled);
    }
}
```

Declare every property you use, as `$originalFlags` is declared above. PHP 8.2+ deprecates writing to undeclared properties, and the container runs PHP 8.1–8.3.

**Use `_before()` / `_after()`, not `_setUp()` / `_tearDown()`.** `WPTestCase` implements `_setUp()` and `_tearDown()` to run the transaction and hook backup; overriding either without calling `parent::` silently disables the rollback for that class. `_before()` and `_after()` are the extension points, and they run inside the transaction. If you genuinely must override `_setUp()`, call `parent::_setUp()` as the first statement.

The same pattern applies to `$GLOBALS`, temporary files, environment variables, added filters, and any other process-level state.

## Error Handling

Let tests fail naturally. Do not wrap assertions in try-catch blocks. If a test throws an unexpected exception, that is a meaningful failure and should surface in the test report.

The acceptance suite has a different convention — it permits try-catch around non-critical intermediate steps to capture a debug screenshot before re-throwing. That pattern is browser-specific and does not apply to PHPUnit/WPUnit. See [acceptance-test-guide.md](./acceptance-test-guide.md#error-handling) for details.

When the test is **verifying that an exception is thrown**, use Codeception's `expectThrowable` in a PHPUnit Cest:

```php
public function testInvalidInputThrowsException(PhpunitTester $I)
{
    $I->wantToTest('invalid input throws InvalidArgumentException');
    $I->comment("Concept: Validator rejects malformed input with a clear exception");

    $I->expectThrowable(\InvalidArgumentException::class, function () {
        Validator::validate('');
    });
}
```

In a WPUnit test, use PHPUnit's own `expectException`, declared before the call that should throw:

```php
public function testInvalidInputThrowsException(): void
{
    $this->tester->wantToTest('invalid input throws InvalidArgumentException');
    $this->tester->comment("Concept: Validator rejects malformed input with a clear exception");

    $this->expectException(\InvalidArgumentException::class);

    Validator::validate('');
}
```
