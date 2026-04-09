# Tile Access Module

Unified admin editor for per-tile read/write role overrides and share-level content scoping. One page controls visibility and grouping for every tile on the site.

## What it does

Every tile in owbn-board ships with two registered role pattern lists:
- `read_roles` — who can see the tile
- `write_roles` — who can interact with it

These defaults work for most sites, but sometimes a site admin needs to change who sees a specific tile without touching code. The **Tile Access** admin page (OWBN Board > Tile Access) provides three editable fields per tile:

1. **Accessible to (read)** — role patterns that override the registered `read_roles`. Any match = visible.
2. **Can edit (write)** — role patterns that override the registered `write_roles`. Any match = interactive.
3. **Share Level (scope)** — role patterns that define how the tile's content is grouped for multi-role users. Only meaningful for tiles that declare `supports_share_level => true`.

Reads and writes are evaluated independently. A user in `chronicle/mckn/player` + `chronicle/mckn/hst` against a tile with read=`chronicle/*/*` / write=`chronicle/*/hst` gets **both** read (via any matching role) and write (via the hst role) — the "lower" player role never blocks anything.

## Share Level — multi-group resolution

Share Level is the mechanism that lets a single tile show different content to different users based on which group they belong to. A user with many roles may belong to many groups at once.

**Example:** a user holds these ASC roles:
```
chronicle/kony/cm
chronicle/kony/hst
chronicle/boston/staff
coordinator/assamite/sub-coordinator
coordinator/ravnos/sub-coordinator
coordinator/sabbat/coordinator
coordinator/salubri/sub-coordinator
coordinator/tremere/sub-coordinator
player/approved
```

With Share Level set to:
```
chronicle/*/*
coordinator/*/*
```

The resolver returns **seven unique groups**:
```
chronicle/kony
chronicle/boston
coordinator/assamite
coordinator/ravnos
coordinator/sabbat
coordinator/salubri
coordinator/tremere
```

(`player/approved` drops — doesn't match either pattern.)

The notebook tile — or any tile that opts in via `supports_share_level => true` — renders **one tile** with a group selector at the top. The user picks which group's content they want to see, and the tile body swaps without a full page reload.

### Group key derivation

The group identifier is derived by stripping trailing wildcards from the pattern, then substituting any remaining wildcards with the matched role segment:

| Pattern | Matched role | Group key |
|---|---|---|
| `chronicle/*/*` | `chronicle/mckn/hst` | `chronicle/mckn` |
| `chronicle/mckn/*` | `chronicle/mckn/cm` | `chronicle/mckn` |
| `exec/*` | `exec/hc/coordinator` | `exec` |
| `chronicle/*/hst` | `chronicle/mckn/hst` | `chronicle/mckn/hst` |

## Storage

All access overrides live inside the existing `owbn_board_layout` option under each tile's entry:

```php
[
    'tiles' => [
        'board:notebook' => [
            'enabled'     => true,
            'size'        => '2x2',
            'priority'    => 5,
            'category'    => 'communication',
            'read_roles'  => [ 'chronicle/*/*', 'exec/*' ],    // optional override
            'write_roles' => [ 'chronicle/*/cm', 'exec/*' ],    // optional override
            'share_level' => [ 'chronicle/*/*', 'coordinator/*/*' ],
        ],
    ],
]
```

Any of the three access keys is optional. When absent, the tile falls back to its registered defaults. This means:
- **Disabling the tile-access module preserves saved overrides** — only the editor disappears.
- **Exporting/importing site layout carries access config automatically.**
- **Re-enabling the module brings the editor back with current state intact.**

## Permission enforcement

Permission checks live in `includes/core/permissions.php`, not in this module. They read the layout option directly and call `owbn_board_tile_effective_read_roles()` / `owbn_board_tile_effective_write_roles()` to get the right list. The tile-access module owns only the editor UI and the share-level resolver.

## API for tile authors

### Opting into share level

```php
owbn_board_register_tile( [
    'id'                   => 'myplugin:my-tile',
    'title'                => 'My Tile',
    'read_roles'           => [ 'chronicle/*/*' ],
    'write_roles'          => [ 'chronicle/*/cm' ],
    'size'                 => '2x2',
    'supports_share_level' => true,  // <-- opt in
    'render'               => 'myplugin_render_tile',
] );
```

### Resolving scope groups in a render callback

```php
function myplugin_render_tile( $tile, $user_id, $can_write ) {
    $groups = owbn_board_tile_access_resolve_scope( $tile['id'], $user_id );
    if ( empty( $groups ) ) {
        // No share level set, or user has no matching role. Fall back.
        $groups = [ myplugin_legacy_scope( $user_id ) ];
    }

    $active_group = $groups[0];
    $content      = myplugin_load_content_for_group( $active_group );

    // Render group selector if more than one group
    if ( count( $groups ) > 1 ) {
        echo '<select class="myplugin__group-select">';
        foreach ( $groups as $g ) {
            printf( '<option value="%s">%s</option>', esc_attr( $g ), esc_html( $g ) );
        }
        echo '</select>';
    }

    // Render content for the active group
    echo $content;
}
```

The tile is responsible for:
- Rendering the group selector UI (dropdown, tabs, pills — whatever fits)
- Swapping content via AJAX when the user picks a different group
- Verifying scope ownership on writes (the user must belong to the group they're writing to)

## Admin workflow

1. Go to **OWBN Board > Tile Access**
2. Each registered tile shows as a card with three textarea fields (one pattern per line)
3. Fields are pre-populated from the tile's registered defaults on first view
4. Edit the patterns you want to override, leave others alone
5. Click **Save** on each card you changed
6. Click **Reset to defaults** to clear all overrides and revert to the tile's registered values
7. For tiles that don't support Share Level, the Share Level field is disabled and grayed out

## Dependencies

- WordPress 5.8+ / PHP 7.4+
- owbn-core (for ASC role resolution)
- Enabled by default on fresh installs and auto-enabled on upgrade via a one-time migration flag
