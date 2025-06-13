<?php
// File: tools/chronicle/views/chronicles.php
// @vesion 0.8.0
// Author: greghacke

defined( 'ABSPATH' ) || exit;

function owbn_render_namespace_view_chronicles( $context ) {
    $email     = $context['email'];
    $group     = $context['selected_group'] ?? null;
    $groups    = $context['groups'] ?? [];
    $is_admin  = current_user_can( 'administrator' );

    if ( $group ) {
        $raw_data = accessSchema_client_remote_get_roles_by_email( $email, 'owbn_board' );
        $raw_roles = $raw_data['roles'] ?? [];

        // Normalize role list — flatten if needed
        $roles = [];

        foreach ( $raw_roles as $r ) {
            if ( is_string( $r ) ) {
                $roles[] = strtolower( $r );
            } elseif ( is_array( $r ) && isset( $r['role'] ) && is_string( $r['role'] ) ) {
                $roles[] = strtolower( $r['role'] );
            } else {
                error_log("Unexpected role format for user {$email}: " . print_r( $r, true ));
            }
        }

        $has_access = accessSchema_client_remote_check_access( $email, "Chronicle/$group", 'owbn_board', true );

        if ( is_wp_error( $has_access ) ) {
            echo '<p>Error checking access: ' . esc_html( $has_access->get_error_message() ) . '</p>';
            return;
        }

        if ( ! $has_access && ! $is_admin ) {
            echo '<p>You do not have access to this Chronicle: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
            return;
        }

        echo '<h2>' . esc_html( strtoupper( $group ) ) . ' Chronicle View</h2>';
        owbn_render_chronicle_info_section( $group );

        $has_any_access = false;
        $base_path      = strtolower("Chronicle/$group");

        $role_check_order = [
            'HST'     => 'owbn_render_chronicle_hst_section',
            'CM'      => 'owbn_render_chronicle_cm_section',
            'Staff'   => 'owbn_render_chronicle_staff_section',
            'Player'  => 'owbn_render_chronicle_players_section',
        ];

        $matches_base_only = in_array( $base_path, $roles, true );

        foreach ( $role_check_order as $role_key => $render_func ) {
            $role_path = strtolower("$base_path/$role_key");

            $has_role = (
                in_array( $role_path, $roles, true ) ||
                ( $role_key === 'Player' &&
                    (
                        in_array( "$base_path/player", $roles, true ) ||
                        in_array( "$base_path/players", $roles, true ) ||
                        $matches_base_only
                    )
                )
            );

            if ( $has_role ) {
                $has_any_access = true;
                $start = array_search($role_key, array_keys($role_check_order));
                $to_render = array_slice($role_check_order, $start);

                foreach ( $to_render as $f ) {
                    $f( $group, $email );
                }

                break;
            }
        }

        if ( ! $has_any_access ) {
            echo '<p>You have no role-based access to this Chronicle: <strong>' . esc_html( strtoupper( $group ) ) . '</strong></p>';
        }

        return;
    }

    echo '<p>You must select a Chronicle group using the menu.</p>';
}

// Fetch the chronicle details
function owbn_fetch_chronicle_data( $slug ) {
    $slug = strtolower( sanitize_key( $slug ) );
    $transient_key = 'owbn_chronicle_data_' . $slug;

    // Try cache first
    $cached = get_transient( $transient_key );
    if ( false !== $cached ) {
        return $cached;
    }

    $api_base = trailingslashit( get_option( 'owbn_chronicle_manager_api_url', '' ) );
    $api_key  = get_option( 'owbn_chronicle_manager_api_key', '' );

    if ( empty( $api_base ) ) {
        return new WP_Error( 'owbn_missing_api_url', 'API URL is not set.' );
    }

    $response = wp_remote_post( $api_base . 'chronicle-detail', [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key'    => $api_key,
        ],
        'body'    => wp_json_encode([ 'slug' => $slug ]),
        'timeout' => 10,
    ] );

    if ( is_wp_error( $response ) ) {
        return $response;
    }

    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    $data = json_decode( $body, true );

    if ( $code !== 200 || ! is_array( $data ) ) {
        return new WP_Error( 'owbn_invalid_response', 'Invalid API response.', [ 'body' => $body, 'code' => $code ] );
    }

    // Set cache for 4 hours
    set_transient( $transient_key, $data, 4 * HOUR_IN_SECONDS );

    return $data;
}

// Render the Chronicle info section
function owbn_render_chronicle_info_section( $slug ) {
    $data = owbn_fetch_chronicle_data( $slug );

    if ( is_wp_error( $data ) ) {
        echo '<p style="color:red;"><strong>' . esc_html( $data->get_error_message() ) . '</strong></p>';
        $debug_body = $data->get_error_data()['body'] ?? null;
        if ( $debug_body ) {
            echo '<pre><code>' . esc_html( $debug_body ) . '</code></pre>';
        }
        return;
    }

    $api_base = trailingslashit( get_option( 'owbn_chronicle_manager_api_url', '' ) );
    $parsed_url = parse_url( $api_base );
    $host_url   = $parsed_url['scheme'] . '://' . $parsed_url['host'];
    if ( isset( $parsed_url['port'] ) ) {
        $host_url .= ':' . $parsed_url['port'];
    }

    $chronicle_url = trailingslashit( $host_url ) . 'chronicles/' . urlencode( strtolower( $slug ) );
    $title         = $data['title'] ?? 'Untitled';

    echo '<div class="owbn-chronicle-container">';
    echo '<h3><a href="' . esc_url( $chronicle_url ) . '" target="_blank" rel="noopener noreferrer">'
        . esc_html( $title ) . '</a></h3>';

    echo '<div class="owbn-chronicle-columns">';
    owbn_render_chronicle_column_1( $data );
    owbn_render_chronicle_column_2( $data );
    owbn_render_chronicle_column_3( $data );
    echo '</div>';

    // Admin-only debug output
    if ( current_user_can( 'manage_options' ) ) {
        echo '<details style="margin-top:1em;"><summary><strong>Raw Data (debug)</strong></summary>';
        echo '<pre style="margin-top:10px; background:#f8f8f8; padding:1em; border:1px solid #ddd;">' . esc_html( print_r( $data, true ) ) . '</pre>';
        echo '</details>';
    }

    echo '</div>';
}

function owbn_render_chronicle_column_1( $data ) {
    echo '<div class="owbn-column">';
    echo '<h4>Fast Facts</h4>';

    // Genres and Region
    echo '<p><strong>Genres:</strong> ' . esc_html( implode( ', ', $data['genres'] ?? [] ) ) . '</p>';
    echo '<p><strong>Region:</strong> ' . esc_html( $data['chronicle_region'] ?? '—' ) . '</p>';

    echo '<h4>Staff</h4>';

    // HST
    $hst = $data['hst_info'][0] ?? [];
    $hst_name = $hst['display_name'] ?? '';
    $hst_email = $hst['display_email'] ?? '';
    if ( $hst_name ) {
        echo '<p><strong>HST:</strong> ';
        echo $hst_email
            ? '<a href="mailto:' . esc_attr( $hst_email ) . '">' . esc_html( $hst_name ) . '</a>'
            : esc_html( $hst_name );
        echo '</p>';
    }

    // CM
    $cm = $data['cm_info'][0] ?? [];
    $cm_name = $cm['display_name'] ?? '';
    $cm_email = $cm['display_email'] ?? '';
    if ( $cm_name ) {
        echo '<p><strong>CM:</strong> ';
        echo $cm_email
            ? '<a href="mailto:' . esc_attr( $cm_email ) . '">' . esc_html( $cm_name ) . '</a>'
            : esc_html( $cm_name );
        echo '</p>';
    }

    // ASTs (multi-line w/ roles + optional links)
    $asts = $data['ast_list'] ?? [];
    echo '<p><strong>AST:</strong></p>';
    if ( ! empty( $asts ) ) {
        foreach ( $asts as $ast ) {
            $name  = $ast['display_name'] ?? '';
            $email = $ast['display_email'] ?? '';
            $role  = $ast['role'] ?? '';

            if ( $name ) {
                echo '<p style="margin-left:1em;">';
                if ( $role ) {
                    echo esc_html( $role ) . ' ';
                }

                echo $email
                    ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $name ) . '</a>'
                    : esc_html( $name );

                echo '</p>';
            }
        }
    } else {
        echo '<p style="margin-left:1em;">—</p>';
    }

    echo '</div>';
}

function owbn_render_chronicle_column_2( $data ) {
    echo '<div class="owbn-column owbn-column-2">';
    echo '<h4>Content</h4>';
    echo '<p>' . nl2br( esc_html( $data['content'] ?? '' ) ) . '</p>';

    echo '<h4>Premise</h4>';
    echo wp_kses_post( $data['premise'] ?? '' );

    echo '<h4>Game Theme</h4>';
    echo '<p>' . esc_html( $data['game_theme'] ?? '' ) . '</p>';

    echo '<h4>Game Mood</h4>';
    echo '<p>' . esc_html( $data['game_mood'] ?? '' ) . '</p>';

    echo '<h4>Traveler Info</h4>';
    echo wp_kses_post( $data['traveler_info'] ?? '' );
    echo '</div>';
}

function owbn_render_chronicle_column_3( $data ) {
    echo '<div class="owbn-column owbn-column-3">';

    // Sessions Section
    echo '<h4>Sessions</h4>';
    if ( ! empty( $data['session_list'] ) && is_array( $data['session_list'] ) ) {
        foreach ( $data['session_list'] as $session ) {
            echo '<div class="owbn-session">';
            echo '<p><strong>Type:</strong> ' . esc_html( $session['session_type'] ?? '' ) . '</p>';
            echo '<p><strong>When:</strong> ' . esc_html( $session['frequency'] ?? '' ) . ' ' . esc_html( $session['day'] ?? '' ) . '</p>';
            echo '<p><strong>Check-in:</strong> ' . esc_html( $session['checkin_time'] ?? '' ) . '</p>';
            echo '<p><strong>Start:</strong> ' . esc_html( $session['start_time'] ?? '' ) . '</p>';
            echo '</div>';
        }
    } else {
        echo '<p>—</p>';
    }

    // Players Section
    echo '<h4>Players</h4>';
    echo '<p><strong>Active:</strong> ' . esc_html( $data['active_player_count'] ?? '—' ) . '</p>';

    // Game Site Section
    echo '<h4>Game Site</h4>';
    $sites = $data['game_site_list'] ?? [];
    if ( ! empty( $sites ) && is_array( $sites ) ) {
        foreach ( $sites as $site ) {
            $name = $site['name'] ?? 'Unnamed';
            $url  = $site['url'] ?? '';
            $city = $site['city'] ?? '';
            $region = $site['region'] ?? '';
            $country = $site['country'] ?? '';
            $address = $site['address'] ?? '';
            $online = $site['online'] ?? '0';

            echo '<div class="owbn-session">';
            echo '<p><strong>Name:</strong> ';
            echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $name ) . '</a>' : esc_html( $name );
            echo '</p>';

            if ( $online === '1' ) {
                echo '<p><em>(Online)</em></p>';
            } else {
                $location_parts = array_filter([ $address, $city, $region, $country ]);
                if ( ! empty( $location_parts ) ) {
                    echo '<p>' . esc_html( implode( ', ', $location_parts ) ) . '</p>';
                }
            }

            echo '</div>';
        }
    } else {
        echo '<p>—</p>';
    }

    echo '</div>';
}

// Render the sections based on roles
function owbn_render_chronicle_hst_section( $slug, $email ) {
    echo "<div><strong>" . esc_html( $slug ) ." HST Section" . "</div>";
    // Add your logic here
}

function owbn_render_chronicle_cm_section( $slug, $email ) {
    echo "<div><strong>" . esc_html( $slug ) ." CM Section" . "</div>";
}

function owbn_render_chronicle_staff_section( $slug, $email ) {
    echo "<div><strong>" . esc_html( $slug ) ." Staff Section" . "</div>";
}

function owbn_render_chronicle_players_section( $slug, $email ) {
    echo "<div><strong>" . esc_html( $slug ) ." Players Section" . "</div>";
}