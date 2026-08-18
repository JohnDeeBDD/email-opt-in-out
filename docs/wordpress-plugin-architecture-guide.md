# WordPress Plugin Architecture Guidelines

These guidelines describe a **modern, modular approach** to WordPress plugin development, with an emphasis on **clean code organization**, **separation of concerns**, and **native JavaScript modules**. The goal is to keep plugins understandable, testable, and scalable without unnecessary build complexity.

## Table of Contents
- [Core Principles](#1-core-principles)
- [Plugin Structure](#2-plugin-structure)
- [The src Directory Requirement](#3-the-src-directory-requirement)
- [PHP Architecture (Server-Side)](#4-php-architecture-server-side)
- [JavaScript Modular System (Client-Side)](#5-javascript-modular-system-client-side)
- [JavaScript Entry Module](#6-javascript-entry-module)
- [Enqueuing JavaScript Modules in WordPress](#7-enqueuing-javascript-modules-in-wordpress)
- [The No-Build Philosophy](#8-the-no-build-philosophy)

---

## 1. Core Principles

This is a **highly opinionated framework** that prioritizes simplicity, transparency, and developer experience over tooling complexity.

**Minimum Requirements:**
- **WordPress 6.5 or higher** — This framework relies on the Script Modules API ([`wp_enqueue_script_module()`](https://developer.wordpress.org/reference/functions/wp_enqueue_script_module/) and [`wp_register_script_module()`](https://developer.wordpress.org/reference/functions/wp_register_script_module/)), which was introduced in WordPress 6.5. Plugins built with this architecture will not function on earlier WordPress versions.

1. **One responsibility per file**
   - OOP classes in PHP, ES6 modules in JS
   - One class / module per file
   - Main entry point PHP file is mostly WordPress actions, hooks and filters to call OOP classes
   - Main entry point JS file is mostly imports of JS files
2. **Clear separation of concerns**
   - UI logic ≠ business logic ≠ data access
3. **Explicit wiring for backend and frontend**
   - There are two explicit entry points: the main PHP plugin file and the main JavaScript module.
   - aiplugin5055.php
   - src/js/aiplugin5055.js
4. **WordPress-first**
   - Respect WordPress lifecycle hooks
   - Use core APIs before inventing abstractions
   - Prefer WordPress data types over custom SQL data tables
5. **No build steps**
   - Ship unbundled ES6 modules directly
   - Browsers natively support ES modules

---

## 2. Plugin Structure

A modular plugin follows a namespace-based directory structure where the plugin slug determines both the namespace and the directory organization. The plugin slug is aiplugin5055 .

**Single-Context Layout** (one JavaScript entry point):

```
aiplugin5055/
├── aiplugin5055.php         # Main plugin PHP file
└── src/
    ├── aiplugin5055/        # Namespaced PHP directory
    │   ├── Hooks/
    │   │   ├── EnqueueAssets.php
    │   │   └── RegisterPostTypes.php
    │   ├── Services/
    │   │   └── ConversationService.php
    │   └── Controllers/
    │       └── RestController.php
    └── js/
        ├── aiplugin5055.js        # JavaScript entry module
        ├── ui/
        │   └── Button.js
        ├── services/
        │   └── ApiClient.js
        └── controllers/
           └── ConversationController.js
```

**Multi-Context Layout** (separate entry points for admin, and frontend):

```
aiplugin5055/
├── aiplugin5055.php         # Main plugin PHP file
├── readme.md                    # Developer facing readme
└── src/
    ├── aiplugin5055/        # Namespaced PHP directory
    │   ├── Hooks/
    │   │   ├── EnqueueAssets.php
    │   │   └── RegisterPostTypes.php
    │   ├── Services/
    │   │   └── ConversationService.php
    │   └── Controllers/
    │       └── RestController.php
    └── js/
        ├── admin.js                   # Admin context entry module
        ├── frontend.js                # Frontend context entry module
        ├── ui/
        │   └── Button.js
        ├── services/
        │   └── ApiClient.js
        └── controllers/
           └── ConversationController.js
```

**Namespace Convention:**
- Plugin slug: `aiplugin5055`
- Root namespace: `aiplugin5055`
- Root directory: `aiplugin5055/`
- Main file lives at the plugin root: `aiplugin5055.php`
- Namespaced PHP code: `aiplugin5055/src/aiplugin5055/`

**Key idea:**
`src/` contains all the files required by the deployed plugin. Both PHP and JavaScript are organized modularly within the `src/` directory.

---

## 3. The src Directory Requirement

**All plugin assets for deployment must reside within the `src/` directory—with one critical exception: the main plugin file lives at the plugin root.**

This is a strict pipeline requirement enforced during automatic plugin packaging and deployment. The main plugin file (e.g., `aiplugin5055.php`) is always located at the root of the plugin directory, not inside `src/`. Everything else ships from `src/`.

### 3.1 What Must Go in src/

Every file that ships with the plugin—regardless of type—must be placed under `src/`:

- **PHP classes**: `src/aiplugin5055/`
- **JavaScript modules**: `src/js/`
- **CSS stylesheets**: `src/css/` or `src/styles/`
- **Images and media**: `src/images/` or `src/assets/`
- **Fonts**: `src/fonts/`
- **Binaries and executables**: `src/bin/`
- **Configuration files**: `src/config/`
- **Third-party libraries**: `src/vendor/` or `src/lib/`

### 3.2 Why This Matters

The plugin packaging pipeline expects all distributable code and assets to exist within `src/`. Files placed outside this directory will not be included in production builds and may cause runtime failures.

**Key principle:** The `src/` directory is not an implementation detail—it is a deployment contract. Violating this structure will break the plugin in production environments.

---

## 4. PHP Architecture (Server-Side)

### 4.1 Main Plugin File

The main plugin file wires together services and hooks directly, using the plugin slug as the root namespace. Do not use autoloaders. We do not use Composer by default, so require_once the plugin's classes. Explicitly require and wire your classes in the main file.

```php
// aiplugin5055.php
/**
 * Plugin Name: Example Plugin
 * Description: Example modular WordPress plugin.
 * Version: 4
 */

namespace aiplugin5055;

require_once plugin_dir_path(__FILE__) . 'src/aiplugin5055/Hooks/RegisterPostTypes.php';

\add_action('init', function () {
    (new \aiplugin5055\Hooks\RegisterPostTypes())->register();
});

\add_action('wp_enqueue_scripts', function() {
    $base_url = \plugin_dir_url(__FILE__);
    
    // CSS from src/css/
    \wp_enqueue_style(
        'aiplugin5055-styles',
        $base_url . 'src/css/styles.css',
        [],
        '1.0.0'
    );
    
    // JavaScript module from src/js/
    \wp_enqueue_script_module(
        'aiplugin5055',
        $base_url . 'src/js/aiplugin5055.js'
    );
});
```

**Namespace Rules:**
- The root namespace matches the plugin slug exactly
- All PHP classes under `src/aiplugin5055/` use this namespace as their base
- Subdirectories map to sub-namespaces (e.g., `src/aiplugin5055/Hooks/` → `aiplugin5055\Hooks`)
- We use lowercase namespaces to match the plugin slug throughout; this departs from PSR-1/PSR-4 convention deliberately.

**Asset Enqueuing:**
- Assets (CSS, JavaScript) are enqueued inline in the main plugin file using WordPress hooks
- Do not create dedicated `EnqueueAssets` classes—keep asset registration simple and visible
- All asset paths reference the `src/` directory structure

This keeps:

- initialization explicit
- dependencies visible
- asset registration centralized and straightforward
- the entire plugin lifecycle in one place
- namespace collision prevention through unique plugin slugs

---

### 4.2 Asset Path Requirements

All enqueued assets must reference paths within `src/`. This is a deployment contract enforced by the plugin packaging pipeline:

```php
$base_url = \plugin_dir_url(__FILE__);

// Correct: references src/css/
\wp_enqueue_style(
    'aiplugin5055-styles',
    $base_url . 'src/css/styles.css',
    [],
    '1.0.0'
);

// Correct: references src/js/
\wp_enqueue_script_module(
    'aiplugin5055',
    $base_url . 'src/js/aiplugin5055.js'
);
```

Assets placed outside `src/` will not be included in production builds.

## 5. JavaScript Modular System (Client-Side)

### 5.1 Goals of the Modular JS System

- One class per file
- Clear import paths
- One entry point per plugin (or per page context)

### 5.2 File Organization

Example:

```
src/js/
├── aiplugin5055.js
├── ui/
│   └── Button.js
├── services/
│   └── ApiClient.js
└── controllers/
    └── ConversationController.js
```

Each folder reflects intent, not technology.

---

## 6. JavaScript Entry Module

The entry module is responsible for wiring, not logic. It lives at `src/js/aiplugin5055.js` — named after the plugin slug, matching every other slug-named artifact in the plugin.

```javascript
// src/js/aiplugin5055.js
import { ConversationController } from './controllers/ConversationController.js';

const controller = new ConversationController();
controller.init();
```

Rules:

- No heavy logic here
- No DOM scanning scattered across files
- This is the only file WordPress enqueues (for single-context plugins)

**Multiple Contexts:**
Plugins serving multiple contexts (admin, frontend, block editor) should use one entry module per context. Each entry module is enqueued conditionally in the appropriate hook:

```
src/js/
├── admin.js
├── frontend.js
└── editor.js
```

Each entry module follows the same wiring-only principle. The module ID follows the pattern `aiplugin5055-{context}`, and each is enqueued in its respective WordPress hook:

```php
// Enqueue frontend module
\add_action('wp_enqueue_scripts', function() {
    \wp_enqueue_script_module(
        'aiplugin5055-frontend',
        \plugin_dir_url(__FILE__) . 'src/js/frontend.js',
        [],
        '1.0.0'
    );
});

// Enqueue admin module
\add_action('admin_enqueue_scripts', function() {
    \wp_enqueue_script_module(
        'aiplugin5055-admin',
        \plugin_dir_url(__FILE__) . 'src/js/admin.js',
        [],
        '1.0.0'
    );
});

// Enqueue block editor module
\add_action('enqueue_block_editor_assets', function() {
    \wp_enqueue_script_module(
        'aiplugin5055-editor',
        \plugin_dir_url(__FILE__) . 'src/js/editor.js',
        [],
        '1.0.0'
    );
});
```

**Module ID Convention:**
- Frontend: `aiplugin5055-frontend` → [`frontend.js`](src/js/frontend.js)
- Admin: `aiplugin5055-admin` → [`admin.js`](src/js/admin.js)
- Block Editor: `aiplugin5055-editor` → [`editor.js`](src/js/editor.js)

Each context-specific entry module imports only the controllers and services needed for that context, keeping the JavaScript payload minimal and context-appropriate.

---

## 7. Enqueuing JavaScript Modules in WordPress

WordPress 6.5+ supports native ES modules through the Script Modules API. **The default approach for this framework is Section 7.1 (relative imports).** Only deviate to Section 7.2 when you have a concrete need for WordPress dependency management or cross-plugin module sharing.

### 7.1 Private Modules: Relative Imports (Default Approach)

For modules used only within your plugin, enqueue only the entry module and let the browser handle relative imports:

```php
\add_action('wp_enqueue_scripts', function(){
    \wp_enqueue_script_module(
        'aiplugin5055',
        \plugin_dir_url(__FILE__) . 'src/js/aiplugin5055.js',
        [],
        '1.0.0'
    );
});
```

When the browser loads the entry module, it will automatically fetch any modules imported with relative paths:

```javascript
// src/js/aiplugin5055.js
import { ConversationController } from './controllers/ConversationController.js';
import { ApiClient } from './services/ApiClient.js';
import { Button } from './ui/Button.js';
```

The browser natively resolves `./controllers/ConversationController.js` relative to the entry module's URL. No WordPress registration needed for these internal modules.

**When to use this approach:**
- All modules are private to your plugin
- No dependencies on WordPress-provided modules
- No need for cross-plugin module sharing
- Simple, self-contained plugin architecture

### 7.2 Shared Modules: Registered Dependencies (Recommended for Complex Plugins)

For modules that need WordPress dependency management, cross-plugin interoperability, or access to WordPress-provided modules, register modules explicitly with `wp_register_script_module()` and use registered IDs for imports:

```php
\add_action('wp_enqueue_scripts', function(){
    $base_url = \plugin_dir_url(__FILE__) . 'src/js/';
    
    // Register shared modules with dependencies
    \wp_register_script_module(
        'aiplugin5055/api-client',
        $base_url . 'services/ApiClient.js',
        ['@wordpress/interactivity'],
        '1.0.0'
    );
    
    \wp_register_script_module(
        'aiplugin5055/controller',
        $base_url . 'controllers/ConversationController.js',
        ['aiplugin5055/api-client'],
        '1.0.0'
    );
    
    // Enqueue the entry module
    \wp_enqueue_script_module(
        'aiplugin5055',
        $base_url . 'aiplugin5055.js',
        ['aiplugin5055/controller'],
        '1.0.0'
    );
});
```

Then import using registered IDs instead of relative paths:

```javascript
// src/js/aiplugin5055.js
import { ConversationController } from 'aiplugin5055/controller';
```

**When to use this approach:**
- Modules depend on WordPress-provided modules (e.g., `@wordpress/interactivity`)
- Modules are shared across multiple plugins
- You need WordPress to manage dependency preloading
- You need import map support for module resolution
- Cross-plugin interoperability is required

### 7.3 Versioning and Cache Busting

Always include version parameters in your enqueue calls. This ensures proper cache invalidation when your code changes:

```php
\wp_enqueue_script_module(
    'aiplugin5055',
    \plugin_dir_url(__FILE__) . 'src/js/aiplugin5055.js',
    [],
    '1.0.0'  // Update this when your JavaScript changes
);
```

Omitting the version parameter weakens cache-busting discipline and can cause users to load stale JavaScript after updates.

---

## 8. The No-Build Philosophy

This framework **rejects build steps as a default practice**. Modern browsers support ES6 modules natively, and the complexity introduced by bundlers, transpilers, and build pipelines is rarely justified for WordPress plugins.

**What this means in practice:**
- Ship `.js` files exactly as written. The file the developer edits is the file the browser loads.
- Use relative `import` paths with explicit `.js` extensions — these work in browsers without resolution config.
- No webpack, Vite, Rollup, or Babel by default. No `package.json`. No `dist/` directory.
- Stylesheets are plain CSS, enqueued via `wp_enqueue_style()`. No Sass/PostCSS unless a concrete need justifies it.

---
