# Internal Modules

This directory holds internal LARP tool modules that live inside owbn-board. Each module is a self-contained subdirectory with its own tables, tiles, admin pages, and hooks.

## Adding a New Module

1. Create a new subdirectory named after the module (lowercase, no spaces): `includes/modules/sessions/`
2. Create `module.php` as the entry point that registers the module with the registry:

```php
<?php
defined('ABSPATH') || exit;

add_action('plugins_loaded', function () {
    if (!function_exists('owbn_board_register_module')) {
        return;
    }
    owbn_board_register_module([
        'id'          => 'sessions',
        'label'       => __('Sessions & Attendance', 'owbn-board'),
        'description' => __('Post-Event Logs, attendance, XP awards', 'owbn-board'),
        'version'     => '1.0.0',
        'default'     => false,
        'sites'       => ['chronicles', 'archivist'],
        'depends_on'  => [],
        'schema'      => 'owbn_board_sessions_install_schema',
        'loader'      => 'owbn_board_sessions_init',
    ]);
}, 15); // Before plugins_loaded priority 20 where the board loads enabled modules

// Required includes for your module — always loaded so the registry can call callbacks
require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/models.php';
require_once __DIR__ . '/tiles.php';
if (is_admin()) {
    require_once __DIR__ . '/admin.php';
}
```

3. Create `schema.php` defining the `owbn_board_sessions_install_schema` function that creates your module's tables (prefixed `{$wpdb->prefix}owbn_board_sessions_*`).
4. Create `models.php` with your CRUD functions.
5. Create `tiles.php` that registers tiles via `owbn_board_register_tile()` inside the `owbn_board_sessions_init` loader callback.
6. Create `admin.php` for admin pages if the module needs them.

## Module Contract

- **Own your tables.** Prefix them `owbn_board_{module_id}_*`. Don't query another module's tables directly.
- **Expose data via filters.** If another module needs your data, provide a filter: `apply_filters('owbn_board_sessions_get_recent', [], $user_id)`.
- **Register tiles via the standard API.** `owbn_board_register_tile()` works identically whether called from a module or an external plugin.
- **Respect the enabled state.** Your loader callback is only called when the module is enabled for the current site. Don't do work in `module.php` itself beyond calling `owbn_board_register_module()` and `require_once` includes.
- **Audit sensitive actions.** Use `owbn_board_audit()` to log anything that should be traceable.
- **Don't break on disable.** Your data stays when disabled. Your loader isn't called. Make sure nothing explodes.

## Module Isolation

Modules cannot directly call another module's functions unless they declare it via `depends_on`. Cross-module features should go through filter hooks with a stable contract. This keeps the boundary clean so a module can be extracted to a standalone plugin later if it outgrows the monolith.

## Testing a New Module

1. Add the module directory and files
2. Activate the owbn-board plugin (if not already)
3. Visit **OWBN Board > Modules** and click Enable
4. The schema callback runs, creating your tables
5. The loader callback fires on subsequent page loads, registering your hooks and tiles
6. Visit **OWBN Board > Layout** to see your new tiles in the list
