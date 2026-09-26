<?php
// 主题设置页面

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 主题设置默认值
function sphotography_get_default_settings() {
    return array(
        // ① Global Theme
        'primary_color'       => '#1abc9c',
        'allow_custom_color'  => false,
        'immersive_color'     => false,
        'night_mode'          => 'system',
        'dark_scheme'         => 'default',
        'frontend_font'       => 'serif',
        'frontend_font_css'   => '',
        'cursor_style'        => 'rounded',
        'admin_global_style'  => true,
        // ② Card Style
        'card_radius'         => 16,
        'card_shadow'         => 'light',
        // ③ Date Format
        'date_format'         => 'Y-m-d',
        'custom_date_format'  => '',
        // ④ Sidebar Info
        'site_title'          => '',
        // Sidebar default open state, split by device (v1.3.7). Desktop defaults
        // to expanded; mobile defaults to collapsed to save screen space.
        'sidebar_default_open_desktop' => true,
        'sidebar_default_open_mobile'  => false,
        'article_card_size'   => 'small',
        // v1.4.8: 边栏展开页瀑布流列数（'2' | '3'），窄屏强制单列。
        'expand_columns'      => '2',
        'enable_hitokoto'     => false,
        // v1.4.8：个人信息展示方式选项已移除，边栏一行为唯一且强制的方式。
        'author_nickname'     => '',
        'avatar_url'          => '',
        'bio'                 => '',
        // Custom personal links for the expanded profile view (v1.3.2).
        // One per line, "名称|链接" (e.g. "GitHub|https://github.com/xxx").
        'custom_links'        => '',
        // Page-links bar 外站 entries (v1.3.7). Up to 3 lines,
        // "名称|链接|悬停文案" (tooltip optional). Shown top-right beside 友链/留言.
        'external_links'      => '',
        // ⑤ Animation
        'preloader_style'     => 'aperture',
        'smooth_scroll'       => 'enabled',
        'entry_animation'     => true,
        'pjax_animation'      => true,
        // ⑤b Motion personality (v1.2.5)
        'motion_tier'          => 'standard',   // subtle | standard | expressive
        'motion_article_easing' => 'inherit',   // inherit | linear | ease-out | ease-in-out | sharp
        'motion_article_scale'  => 100,          // duration multiplier, 50–200 (%)
        'motion_droplet_easing' => 'inherit',   // inherit | linear | ease-out | ease-in-out | spring | sharp
        'motion_droplet_scale'  => 100,          // duration multiplier, 50–200 (%)
        'motion_ignore_reduced' => false,        // play motion even when OS prefers reduced motion
        // ⑥ Reading Info
        'reading_info'        => false,
        'reading_speed_cjk'   => 300,
        'reading_speed_latin' => 200,
        'view_counter'        => true,      // 阅读量计数器（默认开）
        // ⑦ Map Style
        // 地图区域分支：global = 国外供应商（沿用 map_style 预设）；
        // china = 国内合规供应商（天地图 / 高德 / 腾讯）。
        'map_region'          => 'global',
        'map_china_provider'  => 'tianditu', // tianditu | amap | tencent
        'map_china_type'      => 'standard', // standard | satellite
        'map_tianditu_key'    => '',         // 天地图 tk（前端公开密钥，随瓦片 URL 走）
        'map_style'           => 'auto',
        'map_style_custom_url' => '',
        // ⑦b Marker mode & styling (v1.2.6)
        // Single mutually-exclusive marker mode replaces the old tag_color flag:
        //   droplet — plain theme-colour water droplets (default)
        //   tag     — droplets coloured by region_tag
        //   region  — no droplets; fill administrative regions that hold photos
        'marker_mode'          => 'region',     // v1.4.7 (item 3): 默认行政区上色（新站/未设置）
        'cluster_radius'       => 18,        // 10–60 px (droplet/tag modes)
        'droplet_goo_strength' => 7,         // SVG feGaussianBlur stdDeviation, 3–12
        'tag_legend'           => true,      // tag colour legend (tag mode only)
        'region_granularity'   => 'province', // province | city (region mode)
        'region_intensity'     => 35,        // region fill opacity %, 0–100 (region mode)
        // ⑦c Reverse geocoding (v1.4.4 item 4). Endpoint empty → OSM Nominatim
        // public endpoint; optional key for LocationIQ-style compatible services.
        'reverse_geocode_endpoint' => '',
        'reverse_geocode_key'      => '',
        // ⑧ Footer
        'footer_content'      => '',
        'icp_number' => '',
        'security_number' => '',
        'security_url' => '',
        // ⑧b Announcement (v1.4.4 item 6). Markdown notice shown in a top-right
        // panel; auto-opens on load unless disabled or dismissed for this content.
        'announcement_enabled'   => false,
        'announcement_auto_open' => true,
        'announcement_content'   => '',
        // ⑨ CDN
        'cdn_source'          => 'jsdelivr',
        // ⑩ Experimental features (v1.2.9 / v1.3.0). API keys are NOT stored here
        // — they live encrypted in their own options (see inc/ai.php). Master
        // toggle is off by default: experimental features are opt-in.
        'ai_enabled'          => false,
        'ai_model_mode'       => 'single',   // single | dual (vision + text)
        'ai_image_enabled'    => false,       // single-mode: model is multimodal
        'ai_summary'          => false,       // AI 全文概述（前台文章页，默认关）
        'ai_translate'        => false,       // v1.4.4: 后台静默预生成 en/ja 文章译文（前台语言切换据此显示）
        'ai_base_url'         => '',          // primary / text / single model
        'ai_model'            => '',
        'ai_vision_base_url'  => '',          // vision model (dual mode)
        'ai_vision_model'     => '',
        // ⑪ Comments (v1.3.1). The comment system is served by the theme's own
        // REST namespace (sphotography/v1/comments); these flags drive both the
        // backend behaviour and the frontend UI (passed through wp_localize_script).
        'comment_captcha'           => false,      // numeric-sum captcha (anonymous only)
        'comment_allow_edit'        => true,       // commenter may re-edit own comment
        'comment_allow_private'     => false,      // 悄悄话: thread visible only to author + admin
        'comment_mail_notify'       => true,       // reply e-mail notifications (opt-in checkbox)
        'comment_markdown'          => true,       // render a safe Markdown subset
        'comment_emoji_panel'       => true,       // Unicode emoji picker
        'comment_pagination'        => 'infinite', // infinite | paged
        'comment_avatar_align'      => 'top',      // top | center
        'comment_edit_history_view' => 'all',      // all | admin — who may read edit history
        'comment_pin_enabled'       => true,       // admin may pin top-level comments
        'comment_like_enabled'      => true,       // comment likes
        'comment_ua_display'        => 'none',     // none|browser|browser_ver|platform_browser_ver|platform_browser|platform
        'comment_text_avatar'       => true,       // generated text avatar when no Gravatar
        'comment_fold_long'         => true,       // fold comments taller than a threshold
        'comment_show_reply_to'     => true,       // show replied-to username in child comments
        'comment_ip_location'       => false,      // show IP-derived region (省/国) — needs on-demand IP db download
    );
}

// 注册设置与字段
function sphotography_sanitize_settings( $input ) {
    $defaults = sphotography_get_default_settings();
    $input = is_array( $input ) ? wp_unslash( $input ) : array();
    foreach ( array( 'allow_custom_color', 'immersive_color', 'admin_global_style', 'sidebar_default_open_desktop', 'sidebar_default_open_mobile', 'enable_hitokoto', 'entry_animation', 'pjax_animation', 'reading_info', 'view_counter', 'motion_ignore_reduced', 'tag_legend', 'ai_enabled', 'ai_image_enabled', 'ai_summary', 'ai_translate', 'announcement_enabled', 'announcement_auto_open', 'comment_captcha', 'comment_allow_edit', 'comment_allow_private', 'comment_mail_notify', 'comment_markdown', 'comment_emoji_panel', 'comment_pin_enabled', 'comment_like_enabled', 'comment_text_avatar', 'comment_fold_long', 'comment_show_reply_to', 'comment_ip_location' ) as $checkbox ) {
        if ( ! array_key_exists( $checkbox, $input ) ) {
            $input[ $checkbox ] = 0;
        }
    }
    $input = wp_parse_args( $input, $defaults );
    $sanitized = array();

    // ① Global Theme
    $sanitized['primary_color'] = sanitize_hex_color( $input['primary_color'] ) ?: $defaults['primary_color'];
    $sanitized['allow_custom_color'] = ! empty( $input['allow_custom_color'] ) ? 1 : 0;
    $sanitized['immersive_color'] = ! empty( $input['immersive_color'] ) ? 1 : 0;
    $allowed_night = array( 'system', 'light', 'dark' );
    $sanitized['night_mode'] = in_array( $input['night_mode'], $allowed_night, true ) ? $input['night_mode'] : $defaults['night_mode'];
    $allowed_dark = array( 'default', 'blue', 'purple' );
    $sanitized['dark_scheme'] = in_array( $input['dark_scheme'], $allowed_dark, true ) ? $input['dark_scheme'] : $defaults['dark_scheme'];
    $allowed_font = array( 'serif', 'wordpress', 'pingfang', 'songti', 'misans', 'harmonyos' ); // v1.4.7 (item 6): +苹方/宋体
    $sanitized['frontend_font'] = in_array( $input['frontend_font'], $allowed_font, true ) ? $input['frontend_font'] : $defaults['frontend_font'];
    $sanitized['admin_global_style'] = ! empty( $input['admin_global_style'] ) ? 1 : 0;
    $allowed_cursor = array( 'rounded', 'dot', 'normal' );
    $sanitized['cursor_style'] = in_array( $input['cursor_style'], $allowed_cursor, true ) ? $input['cursor_style'] : $defaults['cursor_style'];

    // ② Card Style
    $sanitized['card_radius'] = min( max( (int) $input['card_radius'], 0 ), 40 );
    $sanitized['card_shadow'] = in_array( $input['card_shadow'], array( 'light', 'deep' ), true ) ? $input['card_shadow'] : $defaults['card_shadow'];

    // ③ Date Format
    $sanitized['date_format'] = sanitize_text_field( $input['date_format'] ) ?: $defaults['date_format'];
    $sanitized['custom_date_format'] = sanitize_text_field( $input['custom_date_format'] );

    // ④ Sidebar Info
    $sanitized['site_title'] = sanitize_text_field( $input['site_title'] );
    $sanitized['sidebar_default_open_desktop'] = ! empty( $input['sidebar_default_open_desktop'] ) ? 1 : 0;
    $sanitized['sidebar_default_open_mobile']  = ! empty( $input['sidebar_default_open_mobile'] ) ? 1 : 0;
    $allowed_card_size = array( 'small', 'large' );
    $sanitized['article_card_size'] = in_array( $input['article_card_size'], $allowed_card_size, true ) ? $input['article_card_size'] : $defaults['article_card_size'];
    $allowed_expand_cols = array( '2', '3' );
    $sanitized['expand_columns'] = ( isset( $input['expand_columns'] ) && in_array( (string) $input['expand_columns'], $allowed_expand_cols, true ) ) ? (string) $input['expand_columns'] : $defaults['expand_columns'];
    $sanitized['enable_hitokoto'] = ! empty( $input['enable_hitokoto'] ) ? 1 : 0;
    $sanitized['author_nickname'] = sanitize_text_field( $input['author_nickname'] );
    $sanitized['avatar_url'] = esc_url_raw( $input['avatar_url'] );
    $sanitized['bio'] = sanitize_textarea_field( $input['bio'] );
    // Custom links: keep as a raw multiline string; each line is parsed and
    // URL-escaped at render time by sphotography_parse_profile_links().
    $sanitized['custom_links'] = isset( $input['custom_links'] ) ? sanitize_textarea_field( $input['custom_links'] ) : '';
    // 外站 links: keep at most the first 3 non-empty lines.
    if ( isset( $input['external_links'] ) ) {
        $ext_lines = preg_split( '/\r\n|\r|\n/', sanitize_textarea_field( $input['external_links'] ) );
        $ext_lines = array_values( array_filter( array_map( 'trim', $ext_lines ), 'strlen' ) );
        $sanitized['external_links'] = implode( "\n", array_slice( $ext_lines, 0, 3 ) );
    } else {
        $sanitized['external_links'] = '';
    }

    // ⑤ Animation
    $allowed_preloader = array( 'off', 'aperture', 'flythrough' );
    $sanitized['preloader_style'] = in_array( $input['preloader_style'], $allowed_preloader, true ) ? $input['preloader_style'] : $defaults['preloader_style'];
    $allowed_scroll = array( 'disabled', 'enabled', 'mouse-only' );
    $sanitized['smooth_scroll'] = in_array( $input['smooth_scroll'], $allowed_scroll, true ) ? $input['smooth_scroll'] : $defaults['smooth_scroll'];
    $sanitized['entry_animation'] = ! empty( $input['entry_animation'] ) ? 1 : 0;
    $sanitized['pjax_animation'] = ! empty( $input['pjax_animation'] ) ? 1 : 0;

    // ⑤b Motion personality
    $allowed_tier = array( 'subtle', 'standard', 'expressive' );
    $sanitized['motion_tier'] = in_array( $input['motion_tier'], $allowed_tier, true ) ? $input['motion_tier'] : $defaults['motion_tier'];
    // Article stays monotonic → no spring option offered.
    $allowed_article_easing = array( 'inherit', 'linear', 'ease-out', 'ease-in-out', 'sharp' );
    $sanitized['motion_article_easing'] = in_array( $input['motion_article_easing'], $allowed_article_easing, true ) ? $input['motion_article_easing'] : $defaults['motion_article_easing'];
    $allowed_droplet_easing = array( 'inherit', 'linear', 'ease-out', 'ease-in-out', 'spring', 'sharp' );
    $sanitized['motion_droplet_easing'] = in_array( $input['motion_droplet_easing'], $allowed_droplet_easing, true ) ? $input['motion_droplet_easing'] : $defaults['motion_droplet_easing'];
    $sanitized['motion_article_scale'] = min( max( (int) $input['motion_article_scale'], 50 ), 200 );
    $sanitized['motion_droplet_scale'] = min( max( (int) $input['motion_droplet_scale'], 50 ), 200 );
    $sanitized['motion_ignore_reduced'] = ! empty( $input['motion_ignore_reduced'] ) ? 1 : 0;

    // ⑥ Reading Info
    $sanitized['reading_info'] = ! empty( $input['reading_info'] ) ? 1 : 0;
    $sanitized['view_counter'] = ! empty( $input['view_counter'] ) ? 1 : 0;
    $sanitized['reading_speed_cjk'] = min( max( (int) $input['reading_speed_cjk'], 100 ), 1500 );
    if ( empty( $input['reading_speed_cjk'] ) ) {
        $sanitized['reading_speed_cjk'] = $defaults['reading_speed_cjk'];
    }
    $sanitized['reading_speed_latin'] = min( max( (int) $input['reading_speed_latin'], 50 ), 1000 );
    if ( empty( $input['reading_speed_latin'] ) ) {
        $sanitized['reading_speed_latin'] = $defaults['reading_speed_latin'];
    }

    // ⑦ Map Style
    // Region branch + China provider/type + Tianditu key.
    $allowed_map_region = array( 'global', 'china' );
    $sanitized['map_region'] = in_array( $input['map_region'], $allowed_map_region, true ) ? $input['map_region'] : $defaults['map_region'];
    $allowed_china_provider = array( 'tianditu', 'amap', 'tencent' );
    $sanitized['map_china_provider'] = in_array( $input['map_china_provider'], $allowed_china_provider, true ) ? $input['map_china_provider'] : $defaults['map_china_provider'];
    $allowed_china_type = array( 'standard', 'satellite' );
    $sanitized['map_china_type'] = in_array( $input['map_china_type'], $allowed_china_type, true ) ? $input['map_china_type'] : $defaults['map_china_type'];
    // Tianditu tk is a public front-end key embedded in tile URLs; keep only
    // token-safe chars, no encryption needed.
    $sanitized['map_tianditu_key'] = preg_replace( '/[^A-Za-z0-9]/', '', (string) $input['map_tianditu_key'] );

    $allowed_map_style = array( 'auto', 'satellite', 'terrain', 'voyager', 'watercolor', 'custom' );
    $sanitized['map_style'] = in_array( $input['map_style'], $allowed_map_style, true ) ? $input['map_style'] : $defaults['map_style'];
    // MapLibre fetches this style JSON client-side, so require https to avoid
    // mixed-content on secure sites; fall back to empty (→ auto) otherwise.
    $custom_url = esc_url_raw( trim( (string) $input['map_style_custom_url'] ), array( 'https' ) );
    $sanitized['map_style_custom_url'] = $custom_url;

    // ⑦b Marker mode & styling
    $allowed_marker_mode = array( 'droplet', 'tag', 'region' );
    $sanitized['marker_mode'] = in_array( $input['marker_mode'], $allowed_marker_mode, true ) ? $input['marker_mode'] : $defaults['marker_mode'];
    $sanitized['cluster_radius'] = min( max( (int) $input['cluster_radius'], 10 ), 60 );
    $sanitized['droplet_goo_strength'] = min( max( (int) $input['droplet_goo_strength'], 3 ), 12 );
    $sanitized['tag_legend'] = ! empty( $input['tag_legend'] ) ? 1 : 0;
    $allowed_granularity = array( 'province', 'city' );
    $sanitized['region_granularity'] = in_array( $input['region_granularity'], $allowed_granularity, true ) ? $input['region_granularity'] : $defaults['region_granularity'];
    $sanitized['region_intensity'] = min( max( (int) $input['region_intensity'], 0 ), 100 );

    // ⑧ Footer. This settings page is restricted to trusted administrators.
    // Raw HTML (including scripts) is intentionally supported by the theme.
    $sanitized['frontend_font_css'] = esc_url_raw( $input['frontend_font_css'] ?? '', array( 'http', 'https' ) );
    $sanitized['icp_number'] = sanitize_text_field( $input['icp_number'] ?? '' );
    $sanitized['security_number'] = sanitize_text_field( $input['security_number'] ?? '' );
    $sanitized['security_url'] = esc_url_raw( $input['security_url'] ?? '', array( 'http', 'https' ) );
    $sanitized['footer_content'] = (string) $input['footer_content'];

    // ⑨ CDN
    $allowed_cdn = array( 'jsdelivr', 'unpkg', 'cdnjs' );
    $sanitized['cdn_source'] = in_array( $input['cdn_source'], $allowed_cdn, true )
        ? $input['cdn_source']
        : $defaults['cdn_source'];

    // ⑦c Reverse geocoding (v1.4.4 item 4). Endpoint must be https; key is a
    // plain token appended as ?key= to compatible services.
    $sanitized['reverse_geocode_endpoint'] = esc_url_raw( trim( (string) $input['reverse_geocode_endpoint'] ), array( 'https' ) );
    $sanitized['reverse_geocode_key']      = sanitize_text_field( $input['reverse_geocode_key'] );

    // ⑧b Announcement (v1.4.4 item 6). Markdown source is admin-authored and
    // rendered later through the XSS-safe subset, so store it as given (trusted).
    $sanitized['announcement_enabled']   = ! empty( $input['announcement_enabled'] ) ? 1 : 0;
    $sanitized['announcement_auto_open'] = ! empty( $input['announcement_auto_open'] ) ? 1 : 0;
    $sanitized['announcement_content']   = (string) $input['announcement_content'];

    // ⑩ Experimental (v1.2.9). Require https on the base URL to avoid leaking
    // the bearer token over plain http. The API key is handled separately in
    // the save handler (encrypted), never through this array.
    $sanitized['ai_enabled']       = ! empty( $input['ai_enabled'] ) ? 1 : 0;
    $allowed_ai_mode               = array( 'single', 'dual' );
    $sanitized['ai_model_mode']    = in_array( $input['ai_model_mode'], $allowed_ai_mode, true ) ? $input['ai_model_mode'] : $defaults['ai_model_mode'];
    $sanitized['ai_image_enabled'] = ! empty( $input['ai_image_enabled'] ) ? 1 : 0;
    $sanitized['ai_summary']       = ! empty( $input['ai_summary'] ) ? 1 : 0;
    $sanitized['ai_translate']     = ! empty( $input['ai_translate'] ) ? 1 : 0; // v1.4.4
    $sanitized['ai_base_url']      = esc_url_raw( trim( (string) $input['ai_base_url'] ), array( 'https' ) );
    $sanitized['ai_model']         = sanitize_text_field( $input['ai_model'] );
    $sanitized['ai_vision_base_url'] = esc_url_raw( trim( (string) $input['ai_vision_base_url'] ), array( 'https' ) );
    $sanitized['ai_vision_model']  = sanitize_text_field( $input['ai_vision_model'] );

    // ⑪ Comments (v1.3.1)
    $sanitized['comment_captcha']       = ! empty( $input['comment_captcha'] ) ? 1 : 0;
    $sanitized['comment_allow_edit']    = ! empty( $input['comment_allow_edit'] ) ? 1 : 0;
    $sanitized['comment_allow_private'] = ! empty( $input['comment_allow_private'] ) ? 1 : 0;
    $sanitized['comment_mail_notify']   = ! empty( $input['comment_mail_notify'] ) ? 1 : 0;
    $sanitized['comment_markdown']      = ! empty( $input['comment_markdown'] ) ? 1 : 0;
    $sanitized['comment_emoji_panel']   = ! empty( $input['comment_emoji_panel'] ) ? 1 : 0;
    $sanitized['comment_pin_enabled']   = ! empty( $input['comment_pin_enabled'] ) ? 1 : 0;
    $sanitized['comment_like_enabled']  = ! empty( $input['comment_like_enabled'] ) ? 1 : 0;
    $sanitized['comment_text_avatar']   = ! empty( $input['comment_text_avatar'] ) ? 1 : 0;
    $sanitized['comment_fold_long']     = ! empty( $input['comment_fold_long'] ) ? 1 : 0;
    $sanitized['comment_show_reply_to'] = ! empty( $input['comment_show_reply_to'] ) ? 1 : 0;
    $sanitized['comment_ip_location']   = ! empty( $input['comment_ip_location'] ) ? 1 : 0;
    $allowed_pagination = array( 'infinite', 'paged' );
    $sanitized['comment_pagination'] = in_array( $input['comment_pagination'], $allowed_pagination, true ) ? $input['comment_pagination'] : $defaults['comment_pagination'];
    $allowed_avatar_align = array( 'top', 'center' );
    $sanitized['comment_avatar_align'] = in_array( $input['comment_avatar_align'], $allowed_avatar_align, true ) ? $input['comment_avatar_align'] : $defaults['comment_avatar_align'];
    $allowed_edit_view = array( 'all', 'admin' );
    $sanitized['comment_edit_history_view'] = in_array( $input['comment_edit_history_view'], $allowed_edit_view, true ) ? $input['comment_edit_history_view'] : $defaults['comment_edit_history_view'];
    $allowed_ua = array( 'none', 'browser', 'browser_ver', 'platform_browser_ver', 'platform_browser', 'platform' );
    $sanitized['comment_ua_display'] = in_array( $input['comment_ua_display'], $allowed_ua, true ) ? $input['comment_ua_display'] : $defaults['comment_ua_display'];

    return $sanitized;
}

// Customizer 传输方式
function sphotography_handle_save_settings() {
    if ( ! isset( $_POST['sphotography_save_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['sphotography_save_nonce'], 'sphotography_save_settings' ) ) {
        wp_die( __( 'Security check failed.', 'sphotography' ) );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions.', 'sphotography' ) );
    }

    $raw = isset( $_POST['sphotography'] ) ? $_POST['sphotography'] : array();
    $sanitized = sphotography_sanitize_settings( $raw );

    foreach ( $sanitized as $key => $value ) {
        set_theme_mod( 'sphotography_' . $key, $value );
    }

    // API key (experimental AI) — handled separately so it is never stored as a
    // theme_mod. The field is masked: an empty value keeps the existing key,
    // ticking "clear" removes it, a new value replaces it (encrypted).
    if ( function_exists( 'sphotography_ai_store_key' ) ) {
        // Primary / text / single model key.
        if ( ! empty( $_POST['sphotography_ai_api_key_clear'] ) ) {
            sphotography_ai_store_key( '' );
        } elseif ( isset( $_POST['sphotography_ai_api_key'] ) ) {
            $new_key = trim( (string) wp_unslash( $_POST['sphotography_ai_api_key'] ) );
            if ( '' !== $new_key ) {
                sphotography_ai_store_key( $new_key );
            }
        }
        // Vision model key (dual mode).
        if ( ! empty( $_POST['sphotography_ai_vision_key_clear'] ) ) {
            sphotography_ai_store_vision_key( '' );
        } elseif ( isset( $_POST['sphotography_ai_vision_key'] ) ) {
            $new_vkey = trim( (string) wp_unslash( $_POST['sphotography_ai_vision_key'] ) );
            if ( '' !== $new_vkey ) {
                sphotography_ai_store_vision_key( $new_vkey );
            }
        }
    }

    // Redirect back with success message
    wp_safe_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
    exit;
}
add_action( 'admin_post_sphotography_save_settings', 'sphotography_handle_save_settings' );

// ============================================
// One-time migration (v1.2.6): fold the old boolean tag_color into the new
// mutually-exclusive marker_mode so upgrading sites keep their tag colouring.
// ============================================
function sphotography_migrate_marker_mode() {
    // Only act before marker_mode has ever been set explicitly.
    if ( null !== get_theme_mod( 'sphotography_marker_mode', null ) ) {
        return;
    }
    if ( get_theme_mod( 'sphotography_tag_color', null ) ) {
        set_theme_mod( 'sphotography_marker_mode', 'tag' );
    }
}
add_action( 'admin_init', 'sphotography_migrate_marker_mode' );

// ============================================
// v1.4.8：个人信息展示方式（profile_display）选项已移除，无需迁移。
// ============================================

// 加载设置页 CSS/JS
function sphotography_handle_reset_settings() {
    if ( ! isset( $_POST['sphotography_reset_nonce'] ) ) {
        return;
    }
    if ( ! wp_verify_nonce( $_POST['sphotography_reset_nonce'], 'sphotography_reset_settings' ) ) {
        wp_die( __( 'Security check failed.', 'sphotography' ) );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions.', 'sphotography' ) );
    }

    $defaults = sphotography_get_default_settings();
    foreach ( $defaults as $key => $value ) {
        set_theme_mod( 'sphotography_' . $key, $value );
    }

    wp_safe_redirect( add_query_arg( 'settings-updated', 'true', wp_get_referer() ) );
    exit;
}
add_action( 'admin_post_sphotography_reset_settings', 'sphotography_handle_reset_settings' );

// 渲染主设置页面
function sphotography_render_settings_page() {
    // Get current values from theme mod
    $defaults = sphotography_get_default_settings();
    $values = array();
    foreach ( $defaults as $key => $default_value ) {
        $values[ $key ] = get_theme_mod( 'sphotography_' . $key, $default_value );
    }

    $show_success = isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] === 'true';
    ?>
    <div class="wrap sphotography-settings-wrap">
        <?php /* v1.4.2: 主题动态图标占位——页顶留空容器，日后放入 SVG 文件作为主题动态图标。 */ ?>
        <div class="sphotography-theme-icon" aria-hidden="true"><!-- TODO: 在此插入主题动态图标 SVG --></div>
        <h1 class="sphotography-settings-title"><?php _e( '主题全局配置', 'sphotography' ); ?></h1>
        <p class="sphotography-settings-subtitle"><?php _e( '管理 Sphotography 主题的外观、布局与行为', 'sphotography' ); ?></p>

        <?php if ( $show_success ) : ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e( '设置已保存。', 'sphotography' ); ?></p>
            </div>
        <?php endif; ?>

        <div class="sphotography-settings-layout">
        <?php // v1.4.3: 左栏改为普通 <div> 容器，内含「仅设置项」的 <form> + 独立的社交管理卡片。
        // 此前 <form> 直接作为栅格列并把友链/留言板（各含内联 <form>）包进来，嵌套表单被浏览器
        // 在第一个内层 </form> 处提前闭合 → 保存按钮与其后字段脱离表单（保存失效、跳到友链），
        // 且表单提前闭合后其后各卡片+索引 <aside> 都成了栅格直接子项 → 索引掉到页面下方。
        // 现在栅格恒为「main 列 + aside」两个子项，社交卡片作为 form 的兄弟移到列末尾。 ?>
        <div class="sphotography-settings-main">
        <form id="sphotography-settings-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="sphotography_save_settings">
            <?php wp_nonce_field( 'sphotography_save_settings', 'sphotography_save_nonce' ); ?>

            <!-- ============================================ -->
            <!-- Live Preview (sticky at top) -->
            <!-- ============================================ -->
            <?php // v1.4.2: 实时预览作为最顶部的独立大板块卡片（D1）。 ?>
            <?php $sphotography_preview_url = sphotography_map_preview_url(); ?>
            <?php if ( $sphotography_preview_url ) : ?>
            <section class="sp-cat-card" id="sp-cat-preview">
                <h2 class="sp-cat-card-title"><span class="sp-cat-card-icon dashicons dashicons-visibility"></span><?php _e( '实时预览', 'sphotography' ); ?></h2>
                <div id="sphotography-preview-sticky-wrap">
                    <div class="sphotography-field sphotography-map-preview-field">
                        <div class="sphotography-map-preview" id="sphotography-map-preview" data-preview-base="<?php echo esc_attr( $sphotography_preview_url ); ?>">
                            <iframe id="sphotography-map-preview-frame" title="<?php esc_attr_e( '地图预览', 'sphotography' ); ?>" loading="lazy" referrerpolicy="no-referrer"></iframe>
                            <div class="sphotography-map-preview-refresh" id="sphotography-map-preview-refresh" aria-hidden="true"></div>
                        </div>
                        <p class="sphotography-desc"><?php _e( '改动下方任一地图相关设置后自动刷新预览（约 0.3 秒防抖）。预览使用站点真实照片数据；「行政区上色」需先运行地图样式分类中的「重建行政区索引」。改动尚未保存时预览即时体现，正式生效仍需点击保存。', 'sphotography' ); ?></p>
                    </div>
                </div>
            </section>
            <?php endif; ?>

            <!-- ============================================ -->
            <!-- Category 1: 外观与颜色 -->
            <!-- ============================================ -->
            <!-- Category: 外观与颜色 (v1.4.2: 大板块卡片) -->
            <section class="sp-cat-card" id="sp-cat-appearance">
                <h2 class="sp-cat-card-title"><span class="sp-cat-card-icon dashicons dashicons-art"></span><?php _e( '外观与颜色', 'sphotography' ); ?></h2>

                <!-- Sub-board 1: 配色 -->
                <div class="sphotography-module" id="sp-mod-theme-color">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-art"></span>
                    <h3><?php _e( '配色', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <!-- Primary Color -->
                    <div class="sphotography-field">
                        <label class="sphotography-label"><?php _e( '主题主色调', 'sphotography' ); ?></label>
                        <div class="sphotography-color-group">
                            <input type="text"
                                   class="sphotography-color-picker"
                                   name="sphotography[primary_color]"
                                   value="<?php echo esc_attr( $values['primary_color'] ); ?>"
                                   data-default-color="#1abc9c">
                            <div class="sphotography-preset-colors">
                                <?php
                                $preset_colors = array(
                                    '#e67e22' => __( '暖橙', 'sphotography' ),
                                    '#e74c3c' => __( '赤红', 'sphotography' ),
                                    '#e91e63' => __( '玫红', 'sphotography' ),
                                    '#9b59b6' => __( '紫罗兰', 'sphotography' ),
                                    '#3498db' => __( '天蓝', 'sphotography' ),
                                    '#2ecc71' => __( '翠绿', 'sphotography' ),
                                    '#1abc9c' => __( '青绿', 'sphotography' ),
                                    '#f1c40f' => __( '金黄', 'sphotography' ),
                                    '#e67e22' => __( '暖橙', 'sphotography' ),
                                    '#95a5a6' => __( '灰白', 'sphotography' ),
                                    '#34495e' => __( '深蓝灰', 'sphotography' ),
                                    '#2c3e50' => __( '墨黑', 'sphotography' ),
                                );
                                foreach ( $preset_colors as $color => $name ) :
                                ?>
                                <button type="button"
                                        class="sphotography-preset-btn <?php echo $values['primary_color'] === $color ? 'active' : ''; ?>"
                                        data-color="<?php echo esc_attr( $color ); ?>"
                                        style="background:<?php echo esc_attr( $color ); ?>"
                                        title="<?php echo esc_attr( $name ); ?>">
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Allow custom color -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[allow_custom_color]"
                                   value="1"
                                   <?php checked( $values['allow_custom_color'], 1 ); ?>>
                            <?php _e( '允许前端自定义配色', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，访客可在前端通过颜色选择器自行调整主题色。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Immersive color -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[immersive_color]"
                                   value="1"
                                   <?php checked( $values['immersive_color'], 1 ); ?>>
                            <?php _e( '沉浸式主题色', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，顶部导航栏和底部区域将使用主题主色调填充。', 'sphotography' ); ?></p>
                    </div>

                </div>
                </div>

                <!-- Sub-board 2: 明暗模式 -->
                <div class="sphotography-module" id="sp-mod-theme-darkmode">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-visibility"></span>
                    <h3><?php _e( '明暗模式', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <!-- Night mode -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-night-mode"><?php _e( '夜间模式', 'sphotography' ); ?></label>
                        <select id="sphotography-night-mode" name="sphotography[night_mode]">
                            <option value="system" <?php selected( $values['night_mode'], 'system' ); ?>><?php _e( '跟随系统', 'sphotography' ); ?></option>
                            <option value="light" <?php selected( $values['night_mode'], 'light' ); ?>><?php _e( '浅色常驻', 'sphotography' ); ?></option>
                            <option value="dark" <?php selected( $values['night_mode'], 'dark' ); ?>><?php _e( '深色常驻', 'sphotography' ); ?></option>
                        </select>
                    </div>

                    <!-- Dark scheme -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-dark-scheme"><?php _e( '深色模式配色方案', 'sphotography' ); ?></label>
                        <select id="sphotography-dark-scheme" name="sphotography[dark_scheme]">
                            <option value="default" <?php selected( $values['dark_scheme'], 'default' ); ?>><?php _e( '经典暗色（#0b0b0b）', 'sphotography' ); ?></option>
                            <option value="blue" <?php selected( $values['dark_scheme'], 'blue' ); ?>><?php _e( '深海暗色（#0a1628）', 'sphotography' ); ?></option>
                            <option value="purple" <?php selected( $values['dark_scheme'], 'purple' ); ?>><?php _e( '暗夜紫（#1a0a1e）', 'sphotography' ); ?></option>
                        </select>
                    </div>

                </div>
                </div>

                <!-- Sub-board 3: 字体与光标 -->
                <div class="sphotography-module" id="sp-mod-theme-font">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-editor-textcolor"></span>
                    <h3><?php _e( '字体与光标', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <!-- Frontend font -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-frontend-font"><?php _e( '前端字体', 'sphotography' ); ?></label>
                        <select id="sphotography-frontend-font" name="sphotography[frontend_font]">
                            <option value="serif" <?php selected( $values['frontend_font'], 'serif' ); ?>><?php _e( '衬线字体（Noto Serif SC，默认）', 'sphotography' ); ?></option>
                            <option value="wordpress" <?php selected( $values['frontend_font'], 'wordpress' ); ?>><?php _e( 'WordPress 默认字体（系统无衬线）', 'sphotography' ); ?></option>
                            <option value="pingfang" <?php selected( $values['frontend_font'], 'pingfang' ); ?>><?php _e( '苹方 PingFang（苹果系统原生无衬线）', 'sphotography' ); ?></option>
                            <option value="misans" <?php selected( $values['frontend_font'], 'misans' ); ?>>MiSans</option>
                            <option value="harmonyos" <?php selected( $values['frontend_font'], 'harmonyos' ); ?>>HarmonyOS Sans</option>
                            <option value="songti" <?php selected( $values['frontend_font'], 'songti' ); ?>><?php _e( '宋体 Songti（跨平台衬线）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '选择前端全局字体。衬线字体呈现更优雅的排版；WordPress 默认字体使用系统无衬线字体栈，观感更现代；苹方为苹果系统内置字体，仅在 macOS/iOS/iPadOS 上原生显示，Windows/安卓等设备会自动回退到微软雅黑等系统无衬线字体（苹方受版权保护，无法内嵌为网页字体）；宋体使用系统宋体（Windows 的 SimSun、Mac 的 Songti SC），并以 Noto Serif SC 作为通用回退，跨平台可用。全局生效，默认衬线字体。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sp-font-css">MiSans / HarmonyOS Sans 字体 CSS 地址（可选）</label>
                        <input type="url" id="sp-font-css" name="sphotography[frontend_font_css]" value="<?php echo esc_attr( $values['frontend_font_css'] ); ?>" class="regular-text">
                        <p class="sphotography-desc">仅选中这两种字体时加载。留空使用固定版本的第三方 jsDelivr 字体源；可填写自托管 CSS 地址（保留 MiSans / HarmonyOS Sans SC 字体名称与相对字体文件路径）。加载失败自动回退系统字体。MiSans 使用按字符切片的网络字体。</p>
                    </div>
                    <!-- Cursor style (v1.2.8) -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-cursor-style"><?php _e( '鼠标光标样式', 'sphotography' ); ?></label>
                        <select id="sphotography-cursor-style" name="sphotography[cursor_style]">
                            <option value="rounded" <?php selected( $values['cursor_style'], 'rounded' ); ?>><?php _e( '圆角光标（默认）', 'sphotography' ); ?></option>
                            <option value="dot" <?php selected( $values['cursor_style'], 'dot' ); ?>><?php _e( '点+圆环', 'sphotography' ); ?></option>
                            <option value="normal" <?php selected( $values['cursor_style'], 'normal' ); ?>><?php _e( '普通样式（系统默认）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '选择前端地图页面的鼠标光标样式。「圆角光标」为圆润的 V 形指针（灰色磨砂半透明底、主题色描边环），悬停到可点击的按键上时会吸附变形、包裹按键边框并带轻微粘滞感（触摸设备自动禁用，尊重系统「减弱动态效果」）；「点+圆环」将光标替换为中心圆点外加一小圈圆环的精致指针；「普通样式」使用操作系统默认光标。默认为「圆角光标」。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Global admin style toggle -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[admin_global_style]"
                                   value="1"
                                   <?php checked( $values['admin_global_style'], 1 ); ?>>
                            <?php _e( '启用全局后台 Sphotography 风格', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，整个 WordPress 后台将统一为 Sphotography 风格：优雅衬线字体、主题主色调，并跟随上方"深色模式"设置在深/浅色间切换。默认关闭，保持 WordPress 原生外观。', 'sphotography' ); ?></p>
                    </div>

                </div>
                </div>

                <!-- Sub-board 4: 卡片样式 -->
                <div class="sphotography-module" id="sp-mod-card">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-screenoptions"></span>
                    <h3><?php _e( '卡片样式', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <!-- Card Radius -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-card-radius"><?php _e( '卡片圆角 (px)', 'sphotography' ); ?></label>
                        <input type="number"
                               id="sphotography-card-radius"
                               name="sphotography[card_radius]"
                               value="<?php echo esc_attr( $values['card_radius'] ); ?>"
                               min="0" max="40" step="1">
                        <p class="sphotography-desc"><?php _e( '设置卡片面板的圆角大小，范围 0-40px。推荐 12-20px。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Card Shadow -->
                    <div class="sphotography-field">
                        <label class="sphotography-label"><?php _e( '阴影样式', 'sphotography' ); ?></label>
                        <div class="sphotography-radio-group">
                            <label class="sphotography-radio-label">
                                <input type="radio"
                                       name="sphotography[card_shadow]"
                                       value="light"
                                       <?php checked( $values['card_shadow'], 'light' ); ?>>
                                <span class="sphotography-radio-text"><?php _e( '浅阴影', 'sphotography' ); ?></span>
                            </label>
                            <label class="sphotography-radio-label">
                                <input type="radio"
                                       name="sphotography[card_shadow]"
                                       value="deep"
                                       <?php checked( $values['card_shadow'], 'deep' ); ?>>
                                <span class="sphotography-radio-text"><?php _e( '深阴影', 'sphotography' ); ?></span>
                            </label>
                        </div>
                    </div>
                </div>
                </div><?php // v1.4.2 fix: 补回 sp-mod-card 缺失的模块闭合 </div>，此前 sp-mod-date 被误嵌套其中。 ?>

                <!-- Sub-board 4: 日期格式 -->
                <div class="sphotography-module" id="sp-mod-date">
                    <div class="sphotography-module-header">
                        <span class="sphotography-module-icon dashicons dashicons-calendar-alt"></span>
                        <h3><?php _e( '日期格式', 'sphotography' ); ?></h3>
                    </div>
                    <div class="sphotography-module-body">

                        <div class="sphotography-field">
                            <label class="sphotography-label" for="sphotography-date-format"><?php _e( '日期展示格式', 'sphotography' ); ?></label>
                            <select id="sphotography-date-format" name="sphotography[date_format]">
                                <option value="Y-m-d" <?php selected( $values['date_format'], 'Y-m-d' ); ?>><?php _e( '2026-07-13', 'sphotography' ); ?></option>
                                <option value="Y/m/d" <?php selected( $values['date_format'], 'Y/m/d' ); ?>><?php _e( '2026/07/13', 'sphotography' ); ?></option>
                                <option value="Y年m月d日" <?php selected( $values['date_format'], 'Y年m月d日' ); ?>><?php _e( '2026年7月13日', 'sphotography' ); ?></option>
                                <option value="m/d/Y" <?php selected( $values['date_format'], 'm/d/Y' ); ?>><?php _e( '07/13/2026', 'sphotography' ); ?></option>
                                <option value="d/m/Y" <?php selected( $values['date_format'], 'd/m/Y' ); ?>><?php _e( '13/07/2026', 'sphotography' ); ?></option>
                                <option value="F j, Y" <?php selected( $values['date_format'], 'F j, Y' ); ?>><?php _e( 'July 13, 2026', 'sphotography' ); ?></option>
                                <option value="j F Y" <?php selected( $values['date_format'], 'j F Y' ); ?>><?php _e( '13 July 2026', 'sphotography' ); ?></option>
                                <option value="custom" <?php selected( $values['date_format'], 'custom' ); ?>><?php _e( '自定义格式', 'sphotography' ); ?></option>
                            </select>
                        </div>

                        <div class="sphotography-field sphotography-custom-date-field" style="<?php echo $values['date_format'] === 'custom' ? '' : 'display:none;'; ?>">
                            <label class="sphotography-label" for="sphotography-custom-date-format"><?php _e( '自定义 PHP 日期格式', 'sphotography' ); ?></label>
                            <input type="text"
                                   id="sphotography-custom-date-format"
                                   name="sphotography[custom_date_format]"
                                   value="<?php echo esc_attr( $values['custom_date_format'] ); ?>"
                                   placeholder="<?php esc_attr_e( '例如：l, F j, Y', 'sphotography' ); ?>">
                            <p class="sphotography-desc">
                                <?php _e( '请输入 PHP date() 函数支持的格式字符。', 'sphotography' ); ?>
                                <a href="https://www.php.net/manual/zh/function.date.php" target="_blank"><?php _e( '查看格式参考', 'sphotography' ); ?></a>
                            </p>
                        </div>
                    </div>
                </div>
            

            </section><!-- /.sp-cat-card 外观与颜色 -->

            <!-- Category 2: 边栏与个人 (v1.4.2: 大板块卡片) -->
            <section class="sp-cat-card" id="sp-cat-sidebar">
                <h2 class="sp-cat-card-title"><span class="sp-cat-card-icon dashicons dashicons-admin-users"></span><?php _e( '边栏与个人', 'sphotography' ); ?></h2>

                <!-- Sub-board 1: 站点与边栏 -->
                <div class="sphotography-module" id="sp-mod-sidebar-site">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-info"></span>
                    <h3><?php _e( '站点与边栏', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-site-title"><?php _e( '站点标题', 'sphotography' ); ?></label>
                        <input type="text"
                               id="sphotography-site-title"
                               name="sphotography[site_title]"
                               value="<?php echo esc_attr( $values['site_title'] ); ?>"
                               placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
                        <p class="sphotography-desc"><?php _e( '留空则自动读取 WordPress 站点标题。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[sidebar_default_open_desktop]"
                                   value="1"
                                   <?php checked( $values['sidebar_default_open_desktop'], 1 ); ?>>
                            <?php _e( '桌面端默认展开边栏', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '桌面端首次进入站点时左侧文章栏是否默认展开。默认开启。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[sidebar_default_open_mobile]"
                                   value="1"
                                   <?php checked( $values['sidebar_default_open_mobile'], 1 ); ?>>
                            <?php _e( '移动端默认展开边栏', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '手机 / 窄屏首次进入站点时左侧文章栏是否默认展开。默认关闭，以保留全屏地图空间。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-article-card-size"><?php _e( '文章卡片尺寸', 'sphotography' ); ?></label>
                        <select id="sphotography-article-card-size" name="sphotography[article_card_size]">
                            <option value="small" <?php selected( $values['article_card_size'], 'small' ); ?>><?php _e( '小尺寸（仅标题，默认）', 'sphotography' ); ?></option>
                            <option value="large" <?php selected( $values['article_card_size'], 'large' ); ?>><?php _e( '大尺寸（标题 + 全文简介，纵向为小尺寸两倍）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '选择左侧栏文章列表卡片的尺寸。小尺寸仅展示标题与日期；大尺寸额外展示文章简介，卡片纵向高度约为小尺寸的两倍。默认小尺寸。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-expand-columns"><?php _e( '边栏展开页列数', 'sphotography' ); ?></label>
                        <select id="sphotography-expand-columns" name="sphotography[expand_columns]">
                            <option value="2" <?php selected( $values['expand_columns'], '2' ); ?>><?php _e( '两列（默认）', 'sphotography' ); ?></option>
                            <option value="3" <?php selected( $values['expand_columns'], '3' ); ?>><?php _e( '三列', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '边栏展开页（文章列表大屏）的瀑布流列数。窄屏（手机）下始终为单列。默认两列。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[enable_hitokoto]"
                                   value="1"
                                   <?php checked( $values['enable_hitokoto'], 1 ); ?>>
                            <?php _e( '一言格言', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，在左侧栏底部显示来自一言 API 的随机格言。', 'sphotography' ); ?></p>
                    </div>

                </div>
                </div>

                <!-- Sub-board 2: 个人信息 -->
                <div class="sphotography-module" id="sp-mod-sidebar-profile">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-admin-users"></span>
                    <h3><?php _e( '个人信息', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <?php // v1.4.8：个人信息展示方式选项已移除，边栏一行为唯一展示方式。 ?>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-author-nickname"><?php _e( '作者昵称', 'sphotography' ); ?></label>
                        <input type="text"
                               id="sphotography-author-nickname"
                               name="sphotography[author_nickname]"
                               value="<?php echo esc_attr( $values['author_nickname'] ); ?>"
                               placeholder="<?php echo esc_attr( wp_get_current_user()->display_name ); ?>">
                        <p class="sphotography-desc"><?php _e( '留空自动读取当前用户昵称。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-avatar-url"><?php _e( '头像 URL', 'sphotography' ); ?></label>
                        <input type="url"
                               id="sphotography-avatar-url"
                               name="sphotography[avatar_url]"
                               value="<?php echo esc_attr( $values['avatar_url'] ); ?>"
                               placeholder="<?php esc_attr_e( 'https://example.com/avatar.jpg', 'sphotography' ); ?>">
                        <p class="sphotography-desc"><?php _e( '留空则显示 WordPress 默认 Gravatar 头像。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-bio"><?php _e( '个人简介', 'sphotography' ); ?></label>
                        <textarea id="sphotography-bio"
                                  name="sphotography[bio]"
                                  rows="4"
                                  placeholder="<?php esc_attr_e( '行走于街巷与山野，用镜头收集人间烟火与自然纹理。', 'sphotography' ); ?>"><?php echo esc_textarea( $values['bio'] ); ?></textarea>
                        <p class="sphotography-desc"><?php _e( '留空则自动隐藏简介行（卡片其余部分仍显示）。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-custom-links"><?php _e( '自定义个人链接', 'sphotography' ); ?></label>
                        <textarea id="sphotography-custom-links"
                                  name="sphotography[custom_links]"
                                  rows="4"
                                  placeholder="GitHub|https://github.com/xxx&#10;微博|https://weibo.com/xxx&#10;邮箱|mailto:me@example.com"><?php echo esc_textarea( $values['custom_links'] ); ?></textarea>
                        <p class="sphotography-desc"><?php _e( '一行一个，格式「名称|链接」。展开个人信息时按顺序显示，每行一个可点击链接。留空则不显示链接。', 'sphotography' ); ?></p>
                    </div>

                </div>
                </div>

                <!-- Sub-board 3: 页面链接栏 -->
                <div class="sphotography-module" id="sp-mod-sidebar-external">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-link"></span>
                    <h3><?php _e( '页面链接栏', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-external-links"><?php _e( '外站', 'sphotography' ); ?></label>
                        <textarea id="sphotography-external-links"
                                  name="sphotography[external_links]"
                                  rows="3"
                                  placeholder="博客|https://example.com|我的另一个站点&#10;作品集|https://portfolio.example.com"><?php echo esc_textarea( $values['external_links'] ); ?></textarea>
                        <p class="sphotography-desc"><?php _e( '右上角页面链接栏中「外站」入口。一行一个，最多 3 条，格式「名称|链接|悬停文案」。悬停文案可省略，省略则悬停不显示提示。点击在新标签页打开。', 'sphotography' ); ?></p>
                    </div>

                </div>
                </div>
            

            </section><!-- /.sp-cat-card 边栏与个人 -->

            <!-- Category 3: 动画 (v1.4.2: 大板块卡片) -->
            <section class="sp-cat-card" id="sp-cat-animation">
                <h2 class="sp-cat-card-title"><span class="sp-cat-card-icon dashicons dashicons-update"></span><?php _e( '动画', 'sphotography' ); ?></h2>

                <!-- Sub-board 1: 基础动画 -->
                <div class="sphotography-module" id="sp-mod-animation-basic">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-update"></span>
                    <h3><?php _e( '基础动画', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-preloader-style"><?php _e( '开屏加载动画', 'sphotography' ); ?></label>
                        <select id="sphotography-preloader-style" name="sphotography[preloader_style]">
                            <option value="off" <?php selected( $values['preloader_style'], 'off' ); ?>><?php _e( '关闭', 'sphotography' ); ?></option>
                            <option value="aperture" <?php selected( $values['preloader_style'], 'aperture' ); ?>><?php _e( '光圈（默认）', 'sphotography' ); ?></option>
                            <?php // v1.4.2: 「流光穿越」暂时停用（不可选），代码保留为已弃用状态，待日后打磨后重新启用。已保存该值的站点仍保留在白名单中不被清除。 ?>
                            <option value="flythrough" <?php selected( $values['preloader_style'], 'flythrough' ); ?> disabled><?php _e( '流光穿越（暂未启用）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '地图首页首次加载时的开屏动画。「光圈」为品牌化光圈加载；「流光穿越」以站点名称流光登场，加载完成后镜头穿过文字进入地图；「关闭」则不显示开屏。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-smooth-scroll"><?php _e( '平滑滚动', 'sphotography' ); ?></label>
                        <select id="sphotography-smooth-scroll" name="sphotography[smooth_scroll]">
                            <option value="disabled" <?php selected( $values['smooth_scroll'], 'disabled' ); ?>><?php _e( '禁用', 'sphotography' ); ?></option>
                            <option value="enabled" <?php selected( $values['smooth_scroll'], 'enabled' ); ?>><?php _e( '启用', 'sphotography' ); ?></option>
                            <option value="mouse-only" <?php selected( $values['smooth_scroll'], 'mouse-only' ); ?>><?php _e( '仅鼠标滚轮', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '启用后，页面内锚点跳转和滚轮滚动将带有平滑过渡效果。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[entry_animation]"
                                   value="1"
                                   <?php checked( $values['entry_animation'], 1 ); ?>>
                            <?php _e( '文章进场动画', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，文章卡片在进入视口时会有淡入上滑的入场动画效果。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[pjax_animation]"
                                   value="1"
                                   <?php checked( $values['pjax_animation'], 1 ); ?>>
                            <?php _e( 'Pjax 跳转滚动动画', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，通过 Pjax 无刷新跳转页面时会附带滚动动画过渡效果。', 'sphotography' ); ?></p>
                    </div>

                </div>
                </div>

                <!-- Sub-board 2: 运动性格·高级 -->
                <div class="sphotography-module" id="sp-mod-animation-advanced">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-art"></span>
                    <h3><?php _e( '运动性格·高级', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <!-- Motion personality (v1.2.5) -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-motion-tier"><?php _e( '动效性格', 'sphotography' ); ?></label>
                        <select id="sphotography-motion-tier" name="sphotography[motion_tier]">
                            <option value="subtle" <?php selected( $values['motion_tier'], 'subtle' ); ?>><?php _e( '克制（更快、更平，弱化存在感）', 'sphotography' ); ?></option>
                            <option value="standard" <?php selected( $values['motion_tier'], 'standard' ); ?>><?php _e( '标准（默认，主题原有手感）', 'sphotography' ); ?></option>
                            <option value="expressive" <?php selected( $values['motion_tier'], 'expressive' ); ?>><?php _e( '张扬（更慢、更有弹性，水滴回弹）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '统一调节全站动效的时长与缓动手感。文章面板始终保持单调收放（无回弹），仅地图水滴在「张扬」档带明显弹性。默认「标准」。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Advanced motion (collapsible) -->
                    <div class="sphotography-field">
                        <button type="button" class="sphotography-advanced-toggle" id="sphotography-motion-advanced-toggle" aria-expanded="false">
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                            <?php _e( '高级：分别微调文章与水滴动效', 'sphotography' ); ?>
                        </button>
                        <div class="sphotography-advanced-body" id="sphotography-motion-advanced" hidden>
                            <p class="sphotography-desc" style="margin-top:0;"><?php _e( '两条通道独立微调。缓动保持「跟随动效性格」、倍率保持 100% 时，完全由上方档位决定。', 'sphotography' ); ?></p>

                            <div class="sphotography-advanced-group">
                                <p class="sphotography-advanced-group-title"><?php _e( '文章面板动效', 'sphotography' ); ?></p>
                                <label class="sphotography-sublabel" for="sphotography-motion-article-easing"><?php _e( '缓动曲线', 'sphotography' ); ?></label>
                                <select id="sphotography-motion-article-easing" name="sphotography[motion_article_easing]">
                                    <option value="inherit" <?php selected( $values['motion_article_easing'], 'inherit' ); ?>><?php _e( '跟随动效性格（默认）', 'sphotography' ); ?></option>
                                    <option value="linear" <?php selected( $values['motion_article_easing'], 'linear' ); ?>><?php _e( '线性', 'sphotography' ); ?></option>
                                    <option value="ease-out" <?php selected( $values['motion_article_easing'], 'ease-out' ); ?>><?php _e( '缓出', 'sphotography' ); ?></option>
                                    <option value="ease-in-out" <?php selected( $values['motion_article_easing'], 'ease-in-out' ); ?>><?php _e( '缓入缓出', 'sphotography' ); ?></option>
                                    <option value="sharp" <?php selected( $values['motion_article_easing'], 'sharp' ); ?>><?php _e( '锐利', 'sphotography' ); ?></option>
                                </select>
                                <label class="sphotography-sublabel" for="sphotography-motion-article-scale"><?php _e( '时长倍率', 'sphotography' ); ?></label>
                                <div class="sphotography-slider-row">
                                    <input type="range" id="sphotography-motion-article-scale" name="sphotography[motion_article_scale]"
                                           value="<?php echo esc_attr( $values['motion_article_scale'] ); ?>" min="50" max="200" step="5">
                                    <span class="sphotography-slider-val" data-suffix="%"><?php echo esc_html( $values['motion_article_scale'] ); ?>%</span>
                                </div>
                            </div>

                            <div class="sphotography-advanced-group">
                                <p class="sphotography-advanced-group-title"><?php _e( '地图水滴动效', 'sphotography' ); ?></p>
                                <label class="sphotography-sublabel" for="sphotography-motion-droplet-easing"><?php _e( '缓动曲线', 'sphotography' ); ?></label>
                                <select id="sphotography-motion-droplet-easing" name="sphotography[motion_droplet_easing]">
                                    <option value="inherit" <?php selected( $values['motion_droplet_easing'], 'inherit' ); ?>><?php _e( '跟随动效性格（默认）', 'sphotography' ); ?></option>
                                    <option value="linear" <?php selected( $values['motion_droplet_easing'], 'linear' ); ?>><?php _e( '线性', 'sphotography' ); ?></option>
                                    <option value="ease-out" <?php selected( $values['motion_droplet_easing'], 'ease-out' ); ?>><?php _e( '缓出', 'sphotography' ); ?></option>
                                    <option value="ease-in-out" <?php selected( $values['motion_droplet_easing'], 'ease-in-out' ); ?>><?php _e( '缓入缓出', 'sphotography' ); ?></option>
                                    <option value="spring" <?php selected( $values['motion_droplet_easing'], 'spring' ); ?>><?php _e( '弹性回弹', 'sphotography' ); ?></option>
                                    <option value="sharp" <?php selected( $values['motion_droplet_easing'], 'sharp' ); ?>><?php _e( '锐利', 'sphotography' ); ?></option>
                                </select>
                                <label class="sphotography-sublabel" for="sphotography-motion-droplet-scale"><?php _e( '时长倍率', 'sphotography' ); ?></label>
                                <div class="sphotography-slider-row">
                                    <input type="range" id="sphotography-motion-droplet-scale" name="sphotography[motion_droplet_scale]"
                                           value="<?php echo esc_attr( $values['motion_droplet_scale'] ); ?>" min="50" max="200" step="5">
                                    <span class="sphotography-slider-val" data-suffix="%"><?php echo esc_html( $values['motion_droplet_scale'] ); ?>%</span>
                                </div>
                            </div>

                            <div class="sphotography-field-checkbox" style="margin-top:4px;">
                                <label class="sphotography-label">
                                    <input type="checkbox" name="sphotography[motion_ignore_reduced]" value="1" <?php checked( $values['motion_ignore_reduced'], 1 ); ?>>
                                    <?php _e( '即使系统开启「减弱动态效果」仍播放动效', 'sphotography' ); ?>
                                </label>
                                <p class="sphotography-desc"><?php _e( '默认关闭：尊重操作系统的「减弱动态效果」偏好，此时全站动效自动最小化。仅当你确定要覆盖该无障碍偏好时才勾选。', 'sphotography' ); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                </div>
            

            </section><!-- /.sp-cat-card 动画 -->

            <!-- Category 4: 阅读与评论 (v1.4.2: 大板块卡片) -->
            <section class="sp-cat-card" id="sp-cat-reading_comments">
                <h2 class="sp-cat-card-title"><span class="sp-cat-card-icon dashicons dashicons-book"></span><?php _e( '阅读与评论', 'sphotography' ); ?></h2>

                <!-- Sub-board 1: 阅读信息 -->
                <div class="sphotography-module" id="sp-mod-reading">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-book"></span>
                    <h3><?php _e( '阅读信息', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[reading_info]"
                                   value="1"
                                   <?php checked( $values['reading_info'], 1 ); ?>>
                            <?php _e( '显示字数与阅读时长', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，在文章展开页顶部的日期与分类之间显示「字数 · 约 N 分钟」。阅读时长根据下方阅读速度估算，中英文分别计算。默认关闭。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[view_counter]"
                                   value="1"
                                   <?php checked( $values['view_counter'], 1 ); ?>>
                            <?php _e( '启用阅读量计数器', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，统计每篇文章的阅读量并显示在文章展开页顶部的日期行与边栏卡片上。同一浏览器对同一篇文章每天最多计一次。关闭后停止计数并隐藏阅读量（不影响字数显示）。默认开启。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-reading-speed-cjk"><?php _e( '中文阅读速度（字/分钟）', 'sphotography' ); ?></label>
                        <input type="number"
                               id="sphotography-reading-speed-cjk"
                               name="sphotography[reading_speed_cjk]"
                               value="<?php echo esc_attr( $values['reading_speed_cjk'] ); ?>"
                               min="100" max="1500" step="10">
                        <p class="sphotography-desc"><?php _e( '每分钟阅读的中文字符数，范围 100-1500。默认 300。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-reading-speed-latin"><?php _e( '英文阅读速度（词/分钟）', 'sphotography' ); ?></label>
                        <input type="number"
                               id="sphotography-reading-speed-latin"
                               name="sphotography[reading_speed_latin]"
                               value="<?php echo esc_attr( $values['reading_speed_latin'] ); ?>"
                               min="50" max="1000" step="10">
                        <p class="sphotography-desc"><?php _e( '每分钟阅读的英文单词数，范围 50-1000。默认 200。', 'sphotography' ); ?></p>
                    </div>
                </div>
                </div>

                <!-- Sub-board 2: 评论·显示 -->
                <div class="sphotography-module" id="sp-mod-comments">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-admin-comments"></span>
                    <h3><?php _e( '评论·显示', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <!-- Pagination -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-comment-pagination"><?php _e( '评论分页方式', 'sphotography' ); ?></label>
                        <select id="sphotography-comment-pagination" name="sphotography[comment_pagination]">
                            <option value="infinite" <?php selected( $values['comment_pagination'], 'infinite' ); ?>><?php _e( '无限加载（滚动到底自动加载）', 'sphotography' ); ?></option>
                            <option value="paged" <?php selected( $values['comment_pagination'], 'paged' ); ?>><?php _e( '分页加载（点击翻页）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '每批/每页 10 条顶层评论，子评论随其父评论一起加载。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Avatar align -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-comment-avatar-align"><?php _e( '评论头像垂直位置', 'sphotography' ); ?></label>
                        <select id="sphotography-comment-avatar-align" name="sphotography[comment_avatar_align]">
                            <option value="top" <?php selected( $values['comment_avatar_align'], 'top' ); ?>><?php _e( '居上', 'sphotography' ); ?></option>
                            <option value="center" <?php selected( $values['comment_avatar_align'], 'center' ); ?>><?php _e( '居中', 'sphotography' ); ?></option>
                        </select>
                    </div>

                    <!-- Edit history visibility -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-comment-edit-history-view"><?php _e( '谁可以查看评论编辑记录', 'sphotography' ); ?></label>
                        <select id="sphotography-comment-edit-history-view" name="sphotography[comment_edit_history_view]">
                            <option value="all" <?php selected( $values['comment_edit_history_view'], 'all' ); ?>><?php _e( '所有人', 'sphotography' ); ?></option>
                            <option value="admin" <?php selected( $values['comment_edit_history_view'], 'admin' ); ?>><?php _e( '仅博主', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '选择"所有人"时，被编辑过的评论会显示"已编辑"标记，任何人可点开查看历次版本。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Pin -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_pin_enabled]" value="1" <?php checked( $values['comment_pin_enabled'], 1 ); ?>>
                            <?php _e( '开启评论置顶功能', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，博主可置顶顶层评论，置顶评论显示在评论区最前方。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Like -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_like_enabled]" value="1" <?php checked( $values['comment_like_enabled'], 1 ); ?>>
                            <?php _e( '启用评论点赞', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，每条评论显示点赞按钮，同一访客可取消赞。', 'sphotography' ); ?></p>
                    </div>

                    <!-- UA display -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-comment-ua-display"><?php _e( '评论者 UA 显示', 'sphotography' ); ?></label>
                        <select id="sphotography-comment-ua-display" name="sphotography[comment_ua_display]">
                            <option value="none" <?php selected( $values['comment_ua_display'], 'none' ); ?>><?php _e( '不显示', 'sphotography' ); ?></option>
                            <option value="browser" <?php selected( $values['comment_ua_display'], 'browser' ); ?>><?php _e( '浏览器', 'sphotography' ); ?></option>
                            <option value="browser_ver" <?php selected( $values['comment_ua_display'], 'browser_ver' ); ?>><?php _e( '浏览器 + 版本号', 'sphotography' ); ?></option>
                            <option value="platform_browser_ver" <?php selected( $values['comment_ua_display'], 'platform_browser_ver' ); ?>><?php _e( '平台 + 浏览器 + 版本号', 'sphotography' ); ?></option>
                            <option value="platform_browser" <?php selected( $values['comment_ua_display'], 'platform_browser' ); ?>><?php _e( '平台 + 浏览器', 'sphotography' ); ?></option>
                            <option value="platform" <?php selected( $values['comment_ua_display'], 'platform' ); ?>><?php _e( '平台', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '仅解析并显示浏览器/平台。', 'sphotography' ); ?></p>
                    </div>

                    <!-- IP location -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_ip_location]" value="1" <?php checked( $values['comment_ip_location'], 1 ); ?>>
                            <?php _e( '显示评论者 IP 属地', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，评论旁显示由 IP 解析的归属地（国内到省、国外到国家），不显示完整 IP 地址。首次开启会按需从数据分支下载离线 IP 库到 uploads 目录，在服务器本地解析。历史评论会在下次浏览时自动补全属地。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Text avatar -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_text_avatar]" value="1" <?php checked( $values['comment_text_avatar'], 1 ); ?>>
                            <?php _e( '启用文字头像', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '评论者未设置 Gravatar 时自动生成文字头像，头像颜色由邮箱哈希计算。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Fold long -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_fold_long]" value="1" <?php checked( $values['comment_fold_long'], 1 ); ?>>
                            <?php _e( '折叠过长评论', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，超过约 200px 高度的评论会被折叠，显示"展开阅读全文"。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Show reply-to -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_show_reply_to]" value="1" <?php checked( $values['comment_show_reply_to'], 1 ); ?>>
                            <?php _e( '在子评论中显示被回复者用户名', 'sphotography' ); ?>
                        </label>
                    </div>
                </div>
                </div>

                <!-- Sub-board 3: 评论·功能 -->
                <div class="sphotography-module" id="sp-mod-comments-feature">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-admin-settings"></span>
                    <h3><?php _e( '评论·功能', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <!-- Captcha -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_captcha]" value="1" <?php checked( $values['comment_captcha'], 1 ); ?>>
                            <?php _e( '启用数字求和验证码', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，未登录访客发表评论前需回答一道随机数字加法题（如 3 + 5 = ?）。登录用户自动跳过。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Allow edit -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_allow_edit]" value="1" <?php checked( $values['comment_allow_edit'], 1 ); ?>>
                            <?php _e( '允许评论者再次编辑评论', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，评论者可编辑自己发表的评论（依据登录身份或本浏览器）。每次编辑都会记录到编辑历史。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Private mode -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_allow_private]" value="1" <?php checked( $values['comment_allow_private'], 1 ); ?>>
                            <?php _e( '允许悄悄话模式', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，评论者可将评论设为悄悄话。悄悄话评论及其下所有回复只有发送者和博主可见。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Mail notify -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_mail_notify]" value="1" <?php checked( $values['comment_mail_notify'], 1 ); ?>>
                            <?php _e( '允许评论者接收回复邮件提醒', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，评论框显示"启用邮件通知"复选框（默认勾选）。评论有新回复且已通过审核时，通过站点邮件服务发送提醒。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Markdown -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_markdown]" value="1" <?php checked( $values['comment_markdown'], 1 ); ?>>
                            <?php _e( '允许在评论中使用 Markdown 语法', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，评论支持安全的 Markdown 子集（粗体、斜体、删除线、链接、行内代码、代码块、引用、列表）。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Emoji panel -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[comment_emoji_panel]" value="1" <?php checked( $values['comment_emoji_panel'], 1 ); ?>>
                            <?php _e( '启用评论表情面板', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，评论输入框下显示表情键盘按钮，可插入 Unicode 表情。', 'sphotography' ); ?></p>
                    </div>
                </div>
                </div>
            

            </section><!-- /.sp-cat-card 阅读与评论 -->

            <!-- Category 5: 地图 (v1.4.2: 大板块卡片) -->
            <section class="sp-cat-card" id="sp-cat-map">
                <h2 class="sp-cat-card-title"><span class="sp-cat-card-icon dashicons dashicons-location-alt"></span><?php _e( '地图', 'sphotography' ); ?></h2>

                <!-- Sub-board 1: 底图 -->
            <div class="sphotography-module" id="sp-mod-mapstyle">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-location-alt"></span>
                    <h3><?php _e( '底图', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <!-- Region branch: global (overseas) vs china (compliant domestic) -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-map-region"><?php _e( '地图区域', 'sphotography' ); ?></label>
                        <select id="sphotography-map-region" name="sphotography[map_region]">
                            <option value="global" <?php selected( $values['map_region'], 'global' ); ?>><?php _e( '国外（OpenStreetMap 系底图，全球覆盖）', 'sphotography' ); ?></option>
                            <option value="china" <?php selected( $values['map_region'], 'china' ); ?>><?php _e( '国内（合规供应商，中国大陆推荐）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( 'OpenStreetMap / CartoDB 等底图在中国大陆无测绘备案资质。若站点主要面向中国大陆访客，请选择「国内」并使用下方合规供应商。照片坐标（EXIF GPS，WGS-84）会在使用高德 / 腾讯底图时自动纠偏对齐，无需手动处理。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Style preset (global branch) -->
                    <div class="sphotography-field sp-region-field" data-sp-region="global">
                        <label class="sphotography-label" for="sphotography-map-style"><?php _e( '底图样式', 'sphotography' ); ?></label>
                        <select id="sphotography-map-style" name="sphotography[map_style]">
                            <option value="auto" <?php selected( $values['map_style'], 'auto' ); ?>><?php _e( '自动（跟随夜间模式，默认）', 'sphotography' ); ?></option>
                            <option value="satellite" <?php selected( $values['map_style'], 'satellite' ); ?>><?php _e( '卫星影像（Esri World Imagery）', 'sphotography' ); ?></option>
                            <option value="terrain" <?php selected( $values['map_style'], 'terrain' ); ?>><?php _e( '地形（OpenTopoMap）', 'sphotography' ); ?></option>
                            <option value="voyager" <?php selected( $values['map_style'], 'voyager' ); ?>><?php _e( '街道（CartoDB Voyager）', 'sphotography' ); ?></option>
                            <option value="watercolor" <?php selected( $values['map_style'], 'watercolor' ); ?>><?php _e( '复古水彩（Stamen / Stadia Maps）', 'sphotography' ); ?></option>
                            <option value="custom" <?php selected( $values['map_style'], 'custom' ); ?>><?php _e( '自定义（粘贴 MapLibre style JSON URL）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '选择前端地图的底图样式。选择「自动」时保持跟随夜间模式的深/浅色底图；选择其他预设或自定义样式时，将始终使用该样式，覆盖夜间模式的底图切换（站点界面明暗仍跟随夜间模式）。', 'sphotography' ); ?></p>
                        <p class="sphotography-desc" style="color:#e0a800;"><?php _e( '注意：「复古水彩」由 Stadia Maps 托管，正式站点需在 Stadia 免费注册并添加你的域名后方可正常加载（本地开发无需注册）。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Custom style URL (global branch, revealed when style = custom) -->
                    <div class="sphotography-field sphotography-custom-mapstyle-field sp-region-field" data-sp-region="global" style="<?php echo ( $values['map_region'] === 'global' && $values['map_style'] === 'custom' ) ? '' : 'display:none;'; ?>">
                        <label class="sphotography-label" for="sphotography-map-style-custom-url"><?php _e( '自定义 style JSON URL', 'sphotography' ); ?></label>
                        <input type="url"
                               id="sphotography-map-style-custom-url"
                               name="sphotography[map_style_custom_url]"
                               value="<?php echo esc_attr( $values['map_style_custom_url'] ); ?>"
                               placeholder="https://example.com/style.json">
                        <p class="sphotography-desc"><?php _e( '粘贴任意 MapLibre 兼容的 style JSON 地址（必须为 https）。留空或加载失败时将自动回退到「自动」底图。', 'sphotography' ); ?></p>
                    </div>

                    <!-- China branch: provider -->
                    <div class="sphotography-field sp-region-field" data-sp-region="china">
                        <label class="sphotography-label" for="sphotography-map-china-provider"><?php _e( '国内供应商', 'sphotography' ); ?></label>
                        <select id="sphotography-map-china-provider" name="sphotography[map_china_provider]">
                            <option value="tianditu" <?php selected( $values['map_china_provider'], 'tianditu' ); ?>><?php _e( '天地图（国家平台，备案资质最稳，推荐）', 'sphotography' ); ?></option>
                            <option value="amap" <?php selected( $values['map_china_provider'], 'amap' ); ?>><?php _e( '高德地图', 'sphotography' ); ?></option>
                            <option value="tencent" <?php selected( $values['map_china_provider'], 'tencent' ); ?>><?php _e( '腾讯地图', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '天地图为国家地理信息公共服务平台，坐标系与照片 GPS 一致、无偏移，需下方免费密钥。高德 / 腾讯坐标系为 GCJ-02，主题会自动纠偏照片位置。', 'sphotography' ); ?></p>
                        <p class="sphotography-desc sp-china-provider-note" data-sp-provider="amap tencent" style="color:#e0a800;<?php echo in_array( $values['map_china_provider'], array( 'amap', 'tencent' ), true ) ? '' : 'display:none;'; ?>"><?php _e( '注意：直接调用高德 / 腾讯的瓦片地址属其官方服务条款的灰色地带，个人小站通常可用，但严格合规应申请对应开放平台的 Key 并遵守其 ToS。若追求最稳妥的合规路径，请使用天地图。', 'sphotography' ); ?></p>
                    </div>

                    <!-- China branch: map type -->
                    <div class="sphotography-field sp-region-field" data-sp-region="china">
                        <label class="sphotography-label" for="sphotography-map-china-type"><?php _e( '地图类型', 'sphotography' ); ?></label>
                        <select id="sphotography-map-china-type" name="sphotography[map_china_type]">
                            <option value="standard" <?php selected( $values['map_china_type'], 'standard' ); ?>><?php _e( '标准地图（含注记）', 'sphotography' ); ?></option>
                            <option value="satellite" <?php selected( $values['map_china_type'], 'satellite' ); ?>><?php _e( '卫星影像（含注记）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '国内三家供应商均无原生深色底图。选「标准地图」时，夜间模式为深色（或跟随系统且系统为深色）会自动对底图施加反相「伪深色」滤镜以贴合暗色界面；选「卫星影像」时不反相（影像反相会失真），固定使用原始配色。', 'sphotography' ); ?></p>
                    </div>

                    <!-- China branch: Tianditu key (only when provider = tianditu) -->
                    <div class="sphotography-field sp-china-provider-field" data-sp-provider="tianditu" style="<?php echo ( $values['map_region'] === 'china' && $values['map_china_provider'] === 'tianditu' ) ? '' : 'display:none;'; ?>">
                        <label class="sphotography-label" for="sphotography-map-tianditu-key"><?php _e( '天地图密钥（tk）', 'sphotography' ); ?></label>
                        <input type="text"
                               id="sphotography-map-tianditu-key"
                               name="sphotography[map_tianditu_key]"
                               value="<?php echo esc_attr( $values['map_tianditu_key'] ); ?>"
                               placeholder="<?php esc_attr_e( '在 tianditu.gov.cn 免费申请的浏览器端 tk', 'sphotography' ); ?>"
                               autocomplete="off" spellcheck="false">
                        <p class="sphotography-desc"><?php _e( '前往 <a href="https://console.tianditu.gov.cn/api/key" target="_blank" rel="noopener">天地图控制台</a> 免费注册并创建「浏览器端」应用，将获得的 tk 填入此处。该密钥为前端公开密钥（会出现在瓦片请求地址中），无需保密。留空时天地图无法加载，将自动回退到国外「自动」底图。', 'sphotography' ); ?></p>
                    </div>
                </div>
            </div>

                <!-- Sub-board 2: 标记与聚合 -->
                <div class="sphotography-module" id="sp-mod-mapstyle-marker">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-map-marker"></span>
                    <h3><?php _e( '标记与聚合', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-marker-mode"><?php _e( '地图标记模式', 'sphotography' ); ?></label>
                        <select id="sphotography-marker-mode" name="sphotography[marker_mode]" data-sp-map-preview="markerMode">
                            <option value="region" <?php selected( $values['marker_mode'], 'region' ); ?>><?php _e( '行政区上色（默认，去除钉子，点击色块看照片）', 'sphotography' ); ?></option>
                            <option value="droplet" <?php selected( $values['marker_mode'], 'droplet' ); ?>><?php _e( '水滴标记（主题色）', 'sphotography' ); ?></option>
                            <option value="tag" <?php selected( $values['marker_mode'], 'tag' ); ?>><?php _e( '按地区标签分色（水滴按 region_tag 着色）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '三选一，互斥。「水滴标记」为经典水滴；「按地区标签分色」让水滴按其地区标签着色；「行政区上色」移除所有钉子/水滴，改为把含照片的市/省行政区划用主题色填充，点击色块即可查看该区全部照片。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Cluster radius (droplet/tag modes) -->
                    <div class="sphotography-field sp-mode-field" data-sp-mode="droplet tag">
                        <label class="sphotography-label" for="sphotography-cluster-radius"><?php _e( '标记聚合半径', 'sphotography' ); ?></label>
                        <div class="sphotography-slider-row">
                            <input type="range" id="sphotography-cluster-radius" name="sphotography[cluster_radius]"
                                   value="<?php echo esc_attr( $values['cluster_radius'] ); ?>" min="10" max="60" step="1">
                            <span class="sphotography-slider-val" data-suffix="px"><?php echo esc_html( $values['cluster_radius'] ); ?>px</span>
                        </div>
                        <p class="sphotography-desc"><?php _e( '控制邻近标记合并为聚合水滴的距离阈值，范围 10-60px。值越大越容易合并成大水滴，值越小标记越倾向保持独立。默认 18px。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Gooey fusion strength (droplet/tag modes) -->
                    <div class="sphotography-field sp-mode-field" data-sp-mode="droplet tag">
                        <label class="sphotography-label" for="sphotography-droplet-goo-strength"><?php _e( '水滴融合强度', 'sphotography' ); ?></label>
                        <div class="sphotography-slider-row">
                            <input type="range" id="sphotography-droplet-goo-strength" name="sphotography[droplet_goo_strength]"
                                   value="<?php echo esc_attr( $values['droplet_goo_strength'] ); ?>" min="3" max="12" step="1">
                            <span class="sphotography-slider-val"><?php echo esc_html( $values['droplet_goo_strength'] ); ?></span>
                        </div>
                        <p class="sphotography-desc"><?php _e( '控制聚合／拆分时水滴之间的「拉丝融合」程度，范围 3-12。值越大，邻近水滴越容易黏连成一团；值越小水滴边缘越清爽。默认 7。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Legend toggle (tag mode only) -->
                    <div class="sphotography-field sphotography-field-checkbox sp-mode-field" data-sp-mode="tag">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[tag_legend]" value="1" <?php checked( $values['tag_legend'], 1 ); ?>>
                            <?php _e( '显示标签配色图例', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '仅在「按地区标签分色」模式下生效。在地图左下角显示可折叠的标签配色图例（移动端折叠为「图例」小胶囊）。默认开启。可在「地区标签」编辑页手动为标签指定颜色，留空则按别名自动配色。', 'sphotography' ); ?></p>
                    </div>
                </div>
            </div>

                <!-- Sub-board 3: 区域着色 -->
                <div class="sphotography-module" id="sp-mod-mapstyle-region">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-admin-site-alt"></span>
                    <h3><?php _e( '区域着色', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">
                    <div class="sphotography-field sp-mode-field" data-sp-mode="region">
                        <label class="sphotography-label" for="sphotography-region-granularity"><?php _e( '上色粒度', 'sphotography' ); ?></label>
                        <select id="sphotography-region-granularity" name="sphotography[region_granularity]" data-sp-map-preview="regionGranularity">
                            <option value="province" <?php selected( $values['region_granularity'], 'province' ); ?>><?php _e( '省级 / 州级（默认，全球）', 'sphotography' ); ?></option>
                            <option value="city" <?php selected( $values['region_granularity'], 'city' ); ?>><?php _e( '市级（中国；境外自动回退省级）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '选择着色的行政区划层级。省级：全球按省/州上色。市级：中国按市/区上色，境外因无市级数据自动回退到省/州。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Region colouring: fill intensity (region mode only) -->
                    <div class="sphotography-field sp-mode-field" data-sp-mode="region">
                        <label class="sphotography-label" for="sphotography-region-intensity"><?php _e( '行政区填充强度（%）', 'sphotography' ); ?></label>
                        <div class="sphotography-slider-row">
                            <input type="range" id="sphotography-region-intensity" name="sphotography[region_intensity]"
                                   value="<?php echo esc_attr( $values['region_intensity'] ); ?>" min="0" max="100" step="1" data-sp-map-preview="regionIntensity">
                            <span class="sphotography-slider-val" data-suffix="%"><?php echo esc_html( $values['region_intensity'] ); ?>%</span>
                        </div>
                        <p class="sphotography-desc"><?php _e( '含照片的行政区划以主题主色填充的不透明度，范围 0-100%。所有区块统一使用主题主色，数值越高染色越浓。默认 35%。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Region colouring: rebuild the adcode index (region mode only) -->
                    <div class="sphotography-field sp-mode-field" data-sp-mode="region">
                        <label class="sphotography-label"><?php _e( '行政区索引', 'sphotography' ); ?></label>
                        <button type="button" id="sphotography-rebuild-geo" class="button button-secondary" style="display:inline-flex;align-items:center;gap:4px;">
                            <span class="dashicons dashicons-update" style="font-size:16px;width:16px;height:16px;"></span>
                            <?php _e( '重建行政区索引', 'sphotography' ); ?>
                        </button>
                        <span id="sphotography-rebuild-geo-status" style="margin-left:12px;font-size:0.8125rem;color:var(--sp-text-muted);font-variant-numeric:tabular-nums;"></span>
                        <p class="sphotography-desc"><?php _e( '为每张已定位照片计算其所属的省/市行政区划（点-在-多边形），结果缓存在数据库中。首次运行会自动从 CDN 下载边界数据（约 3.7MB）到 uploads 目录并缓存，之后无需再下载；因此主题包本身保持精简。新上传或修改经纬度的照片会自动索引（前提是边界数据已下载）；点此可回填存量照片。若服务器无法访问外网，错误提示会给出手动放置文件的路径与来源。', 'sphotography' ); ?></p>
                    </div>
                </div>
                </div>

                <!-- Sub-board 4: EXIF 工具 / 照片元数据 (v1.4.0) -->
                <div class="sphotography-module" id="sp-mod-exif-tools">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-camera"></span>
                    <h3><?php _e( 'EXIF 工具 / 照片元数据', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">
                    <div class="sphotography-field">
                        <label class="sphotography-label"><?php _e( '重新读取全部照片 EXIF', 'sphotography' ); ?></label>
                        <button type="button" id="sphotography-exif-backfill" class="button button-secondary" style="display:inline-flex;align-items:center;gap:4px;">
                            <span class="dashicons dashicons-update" style="font-size:16px;width:16px;height:16px;"></span>
                            <?php _e( '开始回填', 'sphotography' ); ?>
                        </button>
                        <span id="sphotography-exif-backfill-status" style="margin-left:12px;font-size:0.8125rem;color:var(--sp-text-muted);font-variant-numeric:tabular-nums;"></span>
                        <p class="sphotography-desc"><?php _e( '为所有已上传的图片重新读取 EXIF（光圈 / 快门 / ISO），常用于：v1.3.9 之前上传的存量照片没有这些字段；或者刚迁移站点后想刷新元数据。处理分批进行（每批 20 张），单张照片失败不会中断整批；处理完成后会清空照片墙缓存，下次打开照片墙即可看到新数据。', 'sphotography' ); ?></p>
                    </div>
                </div>
                </div>

                <!-- Sub-board 5: 图片位置弹窗 / 逆地理编码 (v1.4.4 item 4) -->
                <div class="sphotography-module" id="sp-mod-geocode">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-location"></span>
                    <h3><?php _e( '图片位置弹窗 / 逆地理编码', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-geocode-endpoint"><?php _e( '逆地理编码端点（可选）', 'sphotography' ); ?></label>
                        <input type="url"
                               id="sphotography-geocode-endpoint"
                               class="sphotography-input"
                               name="sphotography[reverse_geocode_endpoint]"
                               value="<?php echo esc_attr( $values['reverse_geocode_endpoint'] ); ?>"
                               placeholder="https://nominatim.openstreetmap.org/reverse">
                        <p class="sphotography-desc"><?php _e( '点击图片让地图飞到拍摄地后，会在闪烁点下方弹出经纬度与详细地名。地名由服务端调用逆地理编码服务解析并缓存。留空则使用 OpenStreetMap Nominatim 公共端点（免费、无需 key，但有 1 次/秒的使用限制）；高频使用可填自建或兼容端点（如 LocationIQ 的 reverse 接口）。', 'sphotography' ); ?></p>
                    </div>
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-geocode-key"><?php _e( 'API Key（可选）', 'sphotography' ); ?></label>
                        <input type="text"
                               id="sphotography-geocode-key"
                               class="sphotography-input"
                               name="sphotography[reverse_geocode_key]"
                               value="<?php echo esc_attr( $values['reverse_geocode_key'] ); ?>"
                               autocomplete="off">
                        <p class="sphotography-desc"><?php _e( '如所用端点需要鉴权（如 LocationIQ），在此填写 key，将以 ?key= 附加到请求。Nominatim 公共端点留空即可。', 'sphotography' ); ?></p>
                    </div>
                    <!-- v1.4.6 (item 1): 一键预生成全站照片地址 -->
                    <div class="sphotography-field">
                        <label class="sphotography-label"><?php _e( '预生成全站照片地址', 'sphotography' ); ?></label>
                        <p class="sphotography-desc"><?php _e( '文章保存时会自动为其中带定位的照片预解析并持久缓存地址（覆盖所有启用语言），前台展示时不再实时调用服务。对早于本功能的旧文章，可点此为全站已发布文章排入后台预生成任务。任务在后台错峰执行、遵守服务限速，无需保持本页打开。', 'sphotography' ); ?></p>
                        <button type="button" class="button button-secondary" id="sphotography-geo-backfill"><?php _e( '为全站照片预生成地址', 'sphotography' ); ?></button>
                        <span class="sphotography-geo-backfill-status" style="margin-left:10px;"></span>
                    </div>
                </div>
                </div>


            </section><!-- /.sp-cat-card 地图 -->

            <?php // v1.4.3: 社交（友链/留言板）卡片已移出本表单、置于列末尾（见 </form> 之后），
            // 因两块管理面板各含内联 <form>，留在设置表单内会造成嵌套表单提前闭合。 ?>

            <!-- Category 7: 其他 (v1.4.2: 大板块卡片) -->
            <section class="sp-cat-card" id="sp-cat-other">
                <h2 class="sp-cat-card-title"><span class="sp-cat-card-icon dashicons dashicons-admin-generic"></span><?php _e( '其他', 'sphotography' ); ?></h2>

                <!-- Sub-board 1: 页脚 -->
                <div class="sphotography-module" id="sp-mod-footer">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-editor-paragraph"></span>
                    <h3><?php _e( '页脚', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">
                    <div class="sphotography-field">
                        <?php foreach ( array( 'icp_number' => 'ICP备案号', 'security_number' => '公安网安备案号', 'security_url' => '公安网安备案查询链接（完整 URL）' ) as $key => $label ) : ?>
                        <p><label for="sp-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label><br>
                        <input class="regular-text" id="sp-<?php echo esc_attr( $key ); ?>" type="<?php echo 'security_url' === $key ? 'url' : 'text'; ?>" name="sphotography[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $values[$key] ); ?>"></p>
                        <?php endforeach; ?>
                        <p class="sphotography-desc">备案号留空时不显示；ICP 链接指向工信部，网安链接可填写备案查询页，未填写时自动提取备案号中的数字生成链接。</p>
                        <label class="sphotography-label" for="sphotography-footer-content"><?php _e( '页脚内容', 'sphotography' ); ?></label>
                        <textarea id="sphotography-footer-content"
                                  name="sphotography[footer_content]"
                                  rows="3"
                                  style="max-width:100%;font-family:monospace;"
                                  placeholder="<?php esc_attr_e( '例如：© 2026 Your Name. All rights reserved.', 'sphotography' ); ?>"><?php echo esc_textarea( $values['footer_content'] ); ?></textarea>
                        <p class="sphotography-desc"><?php _e( '备案号与内容均留空则隐藏页脚。支持可信管理员输入的 HTML 与脚本标签，显示在地图底部中央位置。', 'sphotography' ); ?></p>
                    </div>
                </div>
                </div>

                <!-- Sub-board 1b: 公告 (v1.4.4 item 6) -->
                <div class="sphotography-module" id="sp-mod-announcement">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-megaphone"></span>
                    <h3><?php _e( '公告', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[announcement_enabled]" value="1" <?php checked( $values['announcement_enabled'], 1 ); ?>>
                            <?php _e( '启用公告页', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，前台右上角、页面链接栏下方会出现「公告」按钮与浮层面板；内容为空时不显示。', 'sphotography' ); ?></p>
                    </div>
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox" name="sphotography[announcement_auto_open]" value="1" <?php checked( $values['announcement_auto_open'], 1 ); ?>>
                            <?php _e( '每次打开网站默认展开公告', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启时，访客每次进入站点自动展开公告；访客手动关闭后本浏览器不再自动展开，直到公告内容变化再次展开。关闭此项则仅通过页面链接栏的「公告」按钮手动打开。', 'sphotography' ); ?></p>
                    </div>
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-announcement-content"><?php _e( '公告内容（支持 Markdown）', 'sphotography' ); ?></label>
                        <textarea id="sphotography-announcement-content"
                                  name="sphotography[announcement_content]"
                                  rows="6"
                                  style="max-width:100%;font-family:monospace;"
                                  placeholder="<?php esc_attr_e( "# 欢迎\n\n支持 **粗体**、*斜体*、[链接](https://example.com)、列表、引用、代码与标题。", 'sphotography' ); ?>"><?php echo esc_textarea( $values['announcement_content'] ); ?></textarea>
                        <p class="sphotography-desc"><?php _e( '支持标题(#/##/###)、粗体、斜体、删除线、链接、有序/无序列表、引用、行内与围栏代码。开启「文章翻译」后，保存时会在后台预生成公告的英/日译文，读者切换语言即时显示。', 'sphotography' ); ?></p>
                    </div>
                </div>
                </div>

                <!-- Sub-board 2: CDN -->
                <div class="sphotography-module" id="sp-mod-cdn">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-networking"></span>
                    <h3><?php _e( 'CDN', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-cdn-source"><?php _e( 'MapLibre 加载源', 'sphotography' ); ?></label>
                        <select id="sphotography-cdn-source" name="sphotography[cdn_source]">
                            <option value="jsdelivr" <?php selected( $values['cdn_source'], 'jsdelivr' ); ?>><?php _e( 'jsDelivr（推荐，速度最快）', 'sphotography' ); ?></option>
                            <option value="unpkg" <?php selected( $values['cdn_source'], 'unpkg' ); ?>><?php _e( 'unpkg（当前默认）', 'sphotography' ); ?></option>
                            <option value="cdnjs" <?php selected( $values['cdn_source'], 'cdnjs' ); ?>><?php _e( 'cdnjs（Cloudflare）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '切换前端地图引擎的 CDN 来源。jsDelivr 在中国大陆及全球均有较好的加速效果。更改后保存，刷新前端页面生效。地图瓦片始终从 CartoDB CDN 直接加载，不受此设置影响。', 'sphotography' ); ?></p>
                    </div>
                </div>
                </div>
            

            </section><!-- /.sp-cat-card 其他 -->

            <!-- Category 8: 系统 (v1.4.2: 大板块卡片；保存/重置按钮位于本卡片底部) -->
            <section class="sp-cat-card" id="sp-cat-system">
                <h2 class="sp-cat-card-title"><span class="sp-cat-card-icon dashicons dashicons-admin-tools"></span><?php _e( '系统', 'sphotography' ); ?></h2>

                <!-- Sub-board 1: 实验性功能 -->
            <div class="sphotography-module" id="sp-mod-experimental">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-buddicons-activity"></span>
                    <h3><?php _e( '实验性功能', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">

                    <div class="sphotography-ai-risk">
                        <strong><?php _e( '风险提示', 'sphotography' ); ?></strong>
                        <ul>
                            <li><?php _e( 'AI 生成的内容可能不准确、不完整，甚至凭空捏造，请务必人工核对后再发布。', 'sphotography' ); ?></li>
                            <li><?php _e( '这是实验性功能，仍在完善中，行为与效果可能随版本变化。', 'sphotography' ); ?></li>
                            <li><?php _e( '启用后，你的文章内容与关键词会被发送到你所配置的第三方 AI 服务商，并可能按其计费规则产生费用。', 'sphotography' ); ?></li>
                            <li><?php _e( 'API Key 加密存储于本站数据库、仅在服务器端使用，不会输出到前端；但请仍妥善保管，避免在不受信任的环境中填写。', 'sphotography' ); ?></li>
                        </ul>
                    </div>

                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[ai_enabled]"
                                   value="1"
                                   <?php checked( $values['ai_enabled'], 1 ); ?>>
                            <?php _e( '启用 AI 功能', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '总开关。开启后，文章编辑页会出现「Sphotography AI」面板，可补全正文、润色、自动建议标签。默认关闭。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Model mode (v1.3.0) -->
                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-ai-model-mode"><?php _e( '运行模式', 'sphotography' ); ?></label>
                        <select id="sphotography-ai-model-mode" name="sphotography[ai_model_mode]">
                            <option value="single" <?php selected( $values['ai_model_mode'], 'single' ); ?>><?php _e( '单模型（一个模型完成全部任务）', 'sphotography' ); ?></option>
                            <option value="dual" <?php selected( $values['ai_model_mode'], 'dual' ); ?>><?php _e( '双模型（识图模型 + 文案模型，分别配置）', 'sphotography' ); ?></option>
                        </select>
                        <p class="sphotography-desc"><?php _e( '「单模型」用同一个模型处理文字与图片（分析图片需该模型支持多模态）；「双模型」先用识图模型分析图片生成描述，再交由文案模型写作。图片分析强制需要多模态能力。默认单模型。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Single-mode: image analysis toggle -->
                    <div class="sphotography-field sphotography-field-checkbox sp-ai-mode-field" data-sp-ai-mode="single">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[ai_image_enabled]"
                                   value="1"
                                   <?php checked( $values['ai_image_enabled'], 1 ); ?>>
                            <?php _e( '启用图片分析（单模型为多模态时勾选）', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '仅单模型模式：勾选表示你的单模型支持多模态，补全/润色会把文章图片一并发送分析。若你的模型不支持图片，请保持关闭——此时图片相关分析停用，仅使用文字，其余功能照常。默认关闭。', 'sphotography' ); ?></p>
                    </div>

                    <!-- AI 全文概述 (v1.3.6) -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[ai_summary]"
                                   value="1"
                                   <?php checked( $values['ai_summary'], 1 ); ?>>
                            <?php _e( '启用 AI 全文概述（前台文章页）', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，文章发布/更新时会用文案模型为全文生成一段简短概述，存入数据库，并显示在文章展开页的标题与正文之间（读者首次打开以打字机逐字显示）。仅调用文案模型、只分析正文文字。已发布的旧文章会在下次被打开或保存时自动补生，也可在文章编辑页的 AI 面板手动重新生成。默认关闭。', 'sphotography' ); ?></p>
                    </div>

                    <!-- 文章翻译（前台语言切换）(v1.4.4) -->
                    <div class="sphotography-field sphotography-field-checkbox">
                        <label class="sphotography-label">
                            <input type="checkbox"
                                   name="sphotography[ai_translate]"
                                   value="1"
                                   <?php checked( $values['ai_translate'], 1 ); ?>>
                            <?php _e( '启用文章翻译（前台中/英/日语言切换）', 'sphotography' ); ?>
                        </label>
                        <p class="sphotography-desc"><?php _e( '开启后，文章发布/更新时会在后台静默用文案模型生成英文、日文译文（标题+正文+概述），存入数据库；读者点击右上角语言切换即直接显示译文，无需再次调用模型。旧文章在下次被打开时自动补生。界面文案用内置词典翻译；评论、留言等动态内容按需实时翻译。需先配置好文案模型。默认关闭。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Primary / text / single model -->
                    <h3 class="sphotography-subhead"><?php _e( '文案输出模型（单模型模式下即为唯一模型）', 'sphotography' ); ?></h3>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-ai-base-url"><?php _e( 'API Base URL', 'sphotography' ); ?></label>
                        <input type="url"
                               id="sphotography-ai-base-url"
                               name="sphotography[ai_base_url]"
                               value="<?php echo esc_attr( $values['ai_base_url'] ); ?>"
                               placeholder="https://api.openai.com/v1">
                        <p class="sphotography-desc"><?php _e( '兼容 OpenAI 的接口地址（必须为 https），填写到 /v1 即可，无需带 /chat/completions。支持 OpenAI、DeepSeek、Moonshot、智谱、硅基流动等一切提供 OpenAI 兼容接口的服务商。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-ai-api-key"><?php _e( 'API Key', 'sphotography' ); ?></label>
                        <input type="password"
                               id="sphotography-ai-api-key"
                               name="sphotography_ai_api_key"
                               value=""
                               autocomplete="new-password"
                               placeholder="<?php echo sphotography_ai_has_key() ? esc_attr__( '••••••（已保存，留空则保持不变）', 'sphotography' ) : esc_attr__( 'sk-...', 'sphotography' ); ?>">
                        <?php if ( sphotography_ai_has_key() ) : ?>
                        <label class="sphotography-desc" style="display:flex;align-items:center;gap:6px;margin-top:8px;">
                            <input type="checkbox" name="sphotography_ai_api_key_clear" value="1">
                            <?php _e( '清除已保存的 API Key', 'sphotography' ); ?>
                        </label>
                        <?php endif; ?>
                        <p class="sphotography-desc"><?php _e( 'API Key 使用 AES-256 加密后存储，且从不回显到页面。留空表示保持现有 Key 不变。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label" for="sphotography-ai-model"><?php _e( '模型名称', 'sphotography' ); ?></label>
                        <input type="text"
                               id="sphotography-ai-model"
                               name="sphotography[ai_model]"
                               value="<?php echo esc_attr( $values['ai_model'] ); ?>"
                               placeholder="gpt-4o-mini">
                        <p class="sphotography-desc"><?php _e( '要调用的模型 ID，如 gpt-4o-mini、deepseek-chat、moonshot-v1-8k 等，以你的服务商文档为准。单模型模式若要分析图片，此模型需支持多模态。', 'sphotography' ); ?></p>
                    </div>

                    <!-- Dual-mode: vision model -->
                    <h3 class="sphotography-subhead sp-ai-mode-field" data-sp-ai-mode="dual"><?php _e( '识图模型（仅双模型模式，需支持多模态）', 'sphotography' ); ?></h3>

                    <div class="sphotography-field sp-ai-mode-field" data-sp-ai-mode="dual">
                        <label class="sphotography-label" for="sphotography-ai-vision-base-url"><?php _e( '识图 API Base URL', 'sphotography' ); ?></label>
                        <input type="url"
                               id="sphotography-ai-vision-base-url"
                               name="sphotography[ai_vision_base_url]"
                               value="<?php echo esc_attr( $values['ai_vision_base_url'] ); ?>"
                               placeholder="https://api.openai.com/v1">
                        <p class="sphotography-desc"><?php _e( '识图模型的 OpenAI 兼容接口地址（必须为 https）。可与文案模型相同或不同服务商。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field sp-ai-mode-field" data-sp-ai-mode="dual">
                        <label class="sphotography-label" for="sphotography-ai-vision-key"><?php _e( '识图 API Key', 'sphotography' ); ?></label>
                        <input type="password"
                               id="sphotography-ai-vision-key"
                               name="sphotography_ai_vision_key"
                               value=""
                               autocomplete="new-password"
                               placeholder="<?php echo sphotography_ai_has_vision_key() ? esc_attr__( '••••••（已保存，留空则保持不变）', 'sphotography' ) : esc_attr__( 'sk-...', 'sphotography' ); ?>">
                        <?php if ( sphotography_ai_has_vision_key() ) : ?>
                        <label class="sphotography-desc" style="display:flex;align-items:center;gap:6px;margin-top:8px;">
                            <input type="checkbox" name="sphotography_ai_vision_key_clear" value="1">
                            <?php _e( '清除已保存的识图 API Key', 'sphotography' ); ?>
                        </label>
                        <?php endif; ?>
                        <p class="sphotography-desc"><?php _e( '同样以 AES-256 加密存储，从不回显。留空表示保持现有 Key 不变。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field sp-ai-mode-field" data-sp-ai-mode="dual">
                        <label class="sphotography-label" for="sphotography-ai-vision-model"><?php _e( '识图模型名称', 'sphotography' ); ?></label>
                        <input type="text"
                               id="sphotography-ai-vision-model"
                               name="sphotography[ai_vision_model]"
                               value="<?php echo esc_attr( $values['ai_vision_model'] ); ?>"
                               placeholder="gpt-4o">
                        <p class="sphotography-desc"><?php _e( '支持图片输入的多模态模型 ID，如 gpt-4o、qwen-vl-max、glm-4v 等。', 'sphotography' ); ?></p>
                    </div>

                    <div class="sphotography-field">
                        <label class="sphotography-label"><?php _e( '连接测试', 'sphotography' ); ?></label>
                        <button type="button" id="sphotography-ai-test" class="button button-secondary" style="display:inline-flex;align-items:center;gap:4px;">
                            <span class="dashicons dashicons-admin-plugins" style="font-size:16px;width:16px;height:16px;"></span>
                            <?php _e( '测试连接', 'sphotography' ); ?>
                        </button>
                        <span id="sphotography-ai-test-status" style="margin-left:12px;font-size:0.8125rem;"></span>
                        <p class="sphotography-desc"><?php _e( '发送极小的测试请求验证配置是否可用：单模型测试该模型，双模型分别测试识图模型与文案模型。请先保存设置，再测试已保存的配置。', 'sphotography' ); ?></p>
                    </div>
                </div>
            </div>

                <!-- Sub-board 2: 版本与更新 -->
            <div class="sphotography-module" id="sp-mod-version">
                <div class="sphotography-module-header">
                    <span class="sphotography-module-icon dashicons dashicons-update"></span>
                    <h3><?php _e( '版本与更新', 'sphotography' ); ?></h3>
                </div>
                <div class="sphotography-module-body">
                    <div class="sphotography-field" id="sphotography-updater">
                        <label class="sphotography-label"><?php _e( '当前版本', 'sphotography' ); ?></label>
                        <p style="font-size:1rem;margin-bottom:10px;">
                            <strong><?php echo 'v' . SPHOTOGRAPHY_VERSION; ?></strong>
                            <span id="sphotography-version-status" style="margin-left:12px;font-size:0.8125rem;color:var(--text-muted);"></span>
                        </p>

                        <div style="display:flex;gap:10px;flex-wrap:wrap;">
                            <button type="button" id="sphotography-check-update" class="button button-secondary" style="display:inline-flex;align-items:center;gap:4px;">
                                <span class="dashicons dashicons-search" style="font-size:16px;width:16px;height:16px;"></span>
                                <?php _e( '检查更新', 'sphotography' ); ?>
                            </button>
                            <button type="button" id="sphotography-do-update" class="button button-primary" style="display:inline-flex;align-items:center;gap:4px;background:#1abc9c;border-color:#16a085;">
                                <span class="dashicons dashicons-download" style="font-size:16px;width:16px;height:16px;"></span>
                                <?php _e( '从 master 分支更新主题', 'sphotography' ); ?>
                            </button>
                        </div>

                        <div id="sphotography-update-result" style="margin-top:12px;font-size:0.875rem;"></div>

                        <p class="sphotography-desc" style="margin-top:10px;">
                            <?php _e( '点击「检查更新」从 jsDelivr CDN 获取最新版本信息，点击「从 master 分支更新主题」直接从 GitHub 下载并覆盖主题文件。配置数据存储在数据库中，不受影响。', 'sphotography' ); ?>
                            <a href="https://github.com/ShirazuNagisa/sphotography/releases" target="_blank"><?php _e( '在 GitHub 上查看所有版本', 'sphotography' ); ?></a>
                        </p>
                    </div>
                </div>
            </div>
            

            <!-- Submit Buttons -->
            <!-- ============================================ -->
            <div class="sphotography-actions">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e( '保存设置', 'sphotography' ); ?>
                </button>
                <button type="button" id="sphotography-reset-btn" class="button button-secondary button-large">
                    <span class="dashicons dashicons-dismiss"></span>
                    <?php _e( '重置默认', 'sphotography' ); ?>
                </button>
            </div>
        </section><!-- /.sp-cat-card 系统（含底部 保存/重置 按钮对） -->
        </form>

        <?php // v1.4.3: 社交管理卡片——移出设置表单后作为左栏最后一张卡片。友链/留言板各含
        // 内联 <form>，此处已在设置 </form> 之外，不再触发嵌套表单提前闭合。 ?>
        <section class="sp-cat-card" id="sp-cat-social">
            <h2 class="sp-cat-card-title"><span class="sp-cat-card-icon dashicons dashicons-share"></span><?php _e( '社交', 'sphotography' ); ?></h2>

            <!-- Sub-board 1: 友链管理 -->
            <?php if ( function_exists( 'sphotography_render_friend_links_board' ) ) : ?>
                <?php echo sphotography_render_friend_links_board(); ?>
            <?php endif; ?>

            <!-- Sub-board 2: 留言板设置 -->
            <?php if ( function_exists( 'sphotography_render_guestbook_board' ) ) : ?>
                <?php echo sphotography_render_guestbook_board(); ?>
            <?php endif; ?>
        </section><!-- /.sp-cat-card 社交 -->

        <?php // v1.4.9 (item 1)：配置导出/导入。独立于设置表单，直接 POST 到 admin-post.php。 ?>
        <section class="sp-cat-card" id="sp-cat-config-io">
            <h2 class="sp-cat-card-title"><span class="sp-cat-card-icon dashicons dashicons-database-export"></span><?php _e( '配置备份', 'sphotography' ); ?></h2>
            <div class="sphotography-module-body">
                <p class="sphotography-desc" style="margin-bottom:14px;">
                    <?php _e( '将全部主题设置、API 密钥、友链、留言板设置与地区标签颜色导出为 JSON 文件；在新的 WordPress 上安装本主题后导入该文件，即可一键恢复配置。', 'sphotography' ); ?>
                </p>

                <!-- 导出 -->
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:18px;">
                    <input type="hidden" name="action" value="sphotography_export_config">
                    <?php wp_nonce_field( 'sphotography_config_io', 'sphotography_config_nonce' ); ?>
                    <label class="sphotography-label" style="display:flex;align-items:center;gap:8px;font-weight:400;margin-bottom:10px;">
                        <input type="checkbox" name="include_keys" value="1" checked>
                        <?php _e( '包含 API 密钥（明文）', 'sphotography' ); ?>
                    </label>
                    <p class="sphotography-desc" style="margin:0 0 10px;color:#b26b00;">
                        <span class="dashicons dashicons-warning" style="font-size:15px;width:15px;height:15px;"></span>
                        <?php _e( '注意：勾选后导出的文件将以明文包含你的 API 密钥，请妥善保管、切勿公开分享。', 'sphotography' ); ?>
                    </p>
                    <button type="submit" class="button button-secondary button-large">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e( '导出配置', 'sphotography' ); ?>
                    </button>
                </form>

                <!-- 导入 -->
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data"
                      onsubmit="return confirm('<?php echo esc_js( __( '导入将覆盖当前主题配置（设置、API 密钥、友链、留言板、地区颜色），是否继续？', 'sphotography' ) ); ?>');">
                    <input type="hidden" name="action" value="sphotography_import_config">
                    <?php wp_nonce_field( 'sphotography_config_io', 'sphotography_config_nonce' ); ?>
                    <input type="file" name="config_file" accept="application/json,.json" required style="margin-bottom:10px;display:block;">
                    <button type="submit" class="button button-primary button-large">
                        <span class="dashicons dashicons-upload"></span>
                        <?php _e( '导入配置', 'sphotography' ); ?>
                    </button>
                </form>
            </div>
        </section><!-- /.sp-cat-card 配置备份 -->
        </div><!-- /.sphotography-settings-main -->

        <!-- ============================================ -->
        <!-- Right-side index (TOC) — quick jump + save -->
        <!-- ============================================ -->
        <aside class="sphotography-toc" aria-label="<?php esc_attr_e( '配置索引', 'sphotography' ); ?>">
            <div class="sphotography-toc-inner">
                <?php // v1.4.6 (item 7): 设置搜索框。置于索引栏上方，其顶部与左侧内容顶部平齐（栅格 align-items:start），索引随之下移。 ?>
                <div class="sphotography-toc-search">
                    <span class="dashicons dashicons-search sphotography-toc-search-ico" aria-hidden="true"></span>
                    <input type="search" id="sphotography-settings-search" class="sphotography-toc-search-input" placeholder="<?php esc_attr_e( '搜索设置选项…', 'sphotography' ); ?>" autocomplete="off" aria-label="<?php esc_attr_e( '搜索设置选项', 'sphotography' ); ?>">
                </div>
                <p class="sphotography-toc-heading"><?php _e( '配置索引', 'sphotography' ); ?></p>
                <nav class="sphotography-toc-nav">
                    <?php
                    // v1.4.0: nested TOC. Each category gets its sub-boards
                    // (the same `.sphotography-module` ids in the main
                    // column) listed beneath it. 实时预览 is a leaf.
                    $toc_items = array(
                        array( 'id' => 'sp-cat-preview', 'label' => __( '实时预览', 'sphotography' ) ),
                        array( 'id' => 'sp-cat-appearance', 'label' => __( '外观与颜色', 'sphotography' ), 'children' => array(
                            array( 'id' => 'sp-mod-theme-color',     'label' => __( '配色', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-theme-darkmode',  'label' => __( '明暗', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-theme-font',      'label' => __( '字体光标', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-card',            'label' => __( '卡片', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-date',            'label' => __( '日期', 'sphotography' ) ),
                        ) ),
                        array( 'id' => 'sp-cat-sidebar', 'label' => __( '边栏与个人', 'sphotography' ), 'children' => array(
                            array( 'id' => 'sp-mod-sidebar-site',     'label' => __( '站点', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-sidebar-profile',  'label' => __( '个人信息', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-sidebar-external', 'label' => __( '外站', 'sphotography' ) ),
                        ) ),
                        array( 'id' => 'sp-cat-animation', 'label' => __( '动画', 'sphotography' ), 'children' => array(
                            array( 'id' => 'sp-mod-animation-basic',    'label' => __( '基础', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-animation-advanced', 'label' => __( '高级', 'sphotography' ) ),
                        ) ),
                        array( 'id' => 'sp-cat-reading_comments', 'label' => __( '阅读与评论', 'sphotography' ), 'children' => array(
                            array( 'id' => 'sp-mod-reading',          'label' => __( '阅读', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-comments',         'label' => __( '评论显示', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-comments-feature', 'label' => __( '评论功能', 'sphotography' ) ),
                        ) ),
                        array( 'id' => 'sp-cat-map', 'label' => __( '地图', 'sphotography' ), 'children' => array(
                            array( 'id' => 'sp-mod-mapstyle',         'label' => __( '底图', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-mapstyle-marker',  'label' => __( '标记聚合', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-mapstyle-region',  'label' => __( '区域着色', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-exif-tools',       'label' => __( 'EXIF 工具', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-geocode',          'label' => __( '图片位置', 'sphotography' ) ),
                        ) ),
                        array( 'id' => 'sp-cat-other', 'label' => __( '其他', 'sphotography' ), 'children' => array(
                            array( 'id' => 'sp-mod-footer',       'label' => __( '页脚', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-announcement', 'label' => __( '公告', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-cdn',          'label' => __( 'CDN', 'sphotography' ) ),
                        ) ),
                        array( 'id' => 'sp-cat-system', 'label' => __( '系统', 'sphotography' ), 'children' => array(
                            array( 'id' => 'sp-mod-experimental', 'label' => __( '实验性功能', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-version',      'label' => __( '版本与更新', 'sphotography' ) ),
                        ) ),
                        // v1.4.3: 社交（友链/留言板）移至列末尾，索引项同步排到最后。
                        array( 'id' => 'sp-cat-social', 'label' => __( '社交', 'sphotography' ), 'children' => array(
                            array( 'id' => 'sp-mod-friend-links', 'label' => __( '友链管理', 'sphotography' ) ),
                            array( 'id' => 'sp-mod-guestbook',    'label' => __( '留言板', 'sphotography' ) ),
                        ) ),
                    );
                    foreach ( $toc_items as $item ) :
                        $has_children = ! empty( $item['children'] );
                    ?>
                        <div class="sphotography-toc-group<?php echo $has_children ? ' has-children' : ''; ?>">
                            <?php if ( $has_children ) : ?>
                                <button type="button" class="sphotography-toc-link sphotography-toc-parent" data-target="<?php echo esc_attr( $item['id'] ); ?>" aria-expanded="false">
                                    <span class="sphotography-toc-label"><?php echo esc_html( $item['label'] ); ?></span>
                                    <span class="sphotography-toc-chevron dashicons dashicons-arrow-right" aria-hidden="true"></span>
                                </button>
                                <div class="sphotography-toc-children">
                                    <div class="sphotography-toc-child-wrap">
                                        <?php foreach ( $item['children'] as $child ) : ?>
                                            <a class="sphotography-toc-link sphotography-toc-child" href="#<?php echo esc_attr( $child['id'] ); ?>" data-target="<?php echo esc_attr( $child['id'] ); ?>"><?php echo esc_html( $child['label'] ); ?></a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else : ?>
                                <a class="sphotography-toc-link sphotography-toc-leaf" href="#<?php echo esc_attr( $item['id'] ); ?>" data-target="<?php echo esc_attr( $item['id'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </nav>
                <button type="submit" form="sphotography-settings-form" class="button button-primary sphotography-toc-save">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e( '保存设置', 'sphotography' ); ?>
                </button>
            </div>
        </aside>
        </div><!-- /.sphotography-settings-layout -->

        <?php // v1.4.3: 「添加友链」弹窗（position:fixed）渲染在栅格之外，避免其表单被算作栅格子项。 ?>
        <?php
        if ( function_exists( 'sphotography_render_friend_links_modal' ) ) {
            echo sphotography_render_friend_links_modal();
        }
        ?>

        <!-- Reset form (submitted via JS) -->
        <form id="sphotography-reset-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
            <input type="hidden" name="action" value="sphotography_reset_settings">
            <?php wp_nonce_field( 'sphotography_reset_settings', 'sphotography_reset_nonce' ); ?>
        </form>
    </div>
    <?php
}

// 安装后欢迎钩子
function sphotography_admin_enqueue_settings( $hook ) {
    if ( $hook !== 'toplevel_page_sphotography-settings' ) {
        return;
    }

    // Media library (for friend-links thumbnail picker)
    wp_enqueue_media();

    // Color picker
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );

    // Admin custom styles — Sphotography look: serif type, theme primary as
    // accent, light/dark following the night_mode setting (the scheme body
    // class is added in admin/admin-style.php). Effects are kept subtle.
    $sp_primary = sphotography_admin_primary_color();
    $sp_serif   = sphotography_selected_font_stack();
    // v1.4.2: 大板块卡片圆角跟随前台 card_radius 主题设置（而非固定 14px），
    // 让后台配置卡片与前台面板的圆角观感一致。限幅 0–40 与前端字段一致。
    $sp_card_radius = max( 0, min( 40, (int) get_theme_mod( 'sphotography_card_radius', 16 ) ) );

    $sp_light = "
        --sp-bg: #f4f1ec;
        --sp-surface: #ffffff;
        --sp-surface-2: #faf8f4;
        --sp-text: #2b2622;
        --sp-text-muted: #6b6259;
        --sp-border: #e6e0d8;
        --sp-accent: {$sp_primary};
        --sp-shadow: 0 1px 3px rgba(0,0,0,0.08), 0 1px 2px rgba(0,0,0,0.04);
        color-scheme: light;
    ";
    $sp_dark = "
        --sp-bg: #121212;
        --sp-surface: #1c1c1c;
        --sp-surface-2: #242424;
        --sp-text: #ececec;
        --sp-text-muted: #9a9a9a;
        --sp-border: rgba(255,255,255,0.10);
        --sp-accent: {$sp_primary};
        --sp-shadow: 0 2px 8px rgba(0,0,0,0.4);
        color-scheme: dark;
    ";

    $settings_css = "
        /* Scheme variables — self-contained so the settings page is themed
           whether or not the global admin style is enabled. */
        .sphotography-settings-wrap { {$sp_light} }
        body.sphotography-admin-scheme-dark .sphotography-settings-wrap { {$sp_dark} }
        @media (prefers-color-scheme: dark) {
            body.sphotography-admin-scheme-system .sphotography-settings-wrap { {$sp_dark} }
        }

        .sphotography-settings-wrap {
            max-width: 1180px;
            margin: 20px auto;
            background: var(--sp-bg);
            color: var(--sp-text);
            padding: 30px 40px;
            border-radius: 16px;
            font-family: {$sp_serif};
        }
        /* Two-column layout: settings on the left, sticky index on the right.
           v1.4.0: main column is full-width with boards stacked one-per-row;
           TOC slimmed to 200px so the main column has comfortable breathing room. */
        /* v1.4.2 fix: 两列布局改用 CSS Grid（此前 display:flex 在部分环境下未生效，
           整个索引栏退化为块级铺满整行、掉到页面最下方）。Grid 显式声明两列，物理上
           不可能堆叠；选择器加长并对 display 用 !important，确保不被其它样式覆盖。
           左列 minmax(0,1fr) 允许收缩不溢出，右列固定 200px 作为索引目录。 */
        .sphotography-settings-wrap .sphotography-settings-layout {
            display: grid !important;
            grid-template-columns: minmax(0, 1fr) 200px;
            gap: 28px;
            align-items: start;
        }
        .sphotography-settings-main {
            min-width: 0;
        }
        .sphotography-toc {
            position: sticky;
            top: 46px;
            align-self: start;
        }
        /* v1.4.2: 每个大板块 = 一张独立卡片，彼此不相连（靠 margin 间隔）。
           卡片圆角跟随前台 card_radius 主题设置。内部子模块扁平化为带分隔线的
           带标题分区（去掉各自的卡片外观）。旧的 .sp-cat-anchor/.sp-cat-divider
           已由 <section class=\"sp-cat-card\"> 取代。 */
        .sp-cat-card {
            background: var(--sp-surface);
            border: 1px solid var(--sp-border);
            border-radius: {$sp_card_radius}px;
            box-shadow: var(--sp-shadow);
            margin-bottom: 24px;
            padding: 20px 26px 24px;
            scroll-margin-top: 52px; /* 锚点跳转避开 WP 顶部管理条 */
        }
        .sp-cat-card:last-of-type { margin-bottom: 0; }
        .sp-cat-card-title {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0 0 6px 0;
            padding-bottom: 14px;
            border-bottom: 2px solid var(--sp-border);
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--sp-text);
            font-family: {$sp_serif};
            letter-spacing: 0.01em;
        }
        .sp-cat-card-title .sp-cat-card-icon {
            color: var(--sp-accent);
            font-size: 1.4rem;
            width: auto;
            height: auto;
        }
        /* 扁平化卡片内子模块：脱去卡片外观，改为带分隔线的分区。 */
        .sp-cat-card .sphotography-module {
            background: transparent;
            border: none;
            border-radius: 0;
            box-shadow: none;
            margin: 0;
            overflow: visible;
            scroll-margin-top: 52px;
        }
        .sp-cat-card .sphotography-module + .sphotography-module {
            border-top: 1px solid var(--sp-border);
            margin-top: 6px;
        }
        .sp-cat-card .sphotography-module-header {
            background: transparent;
            border-bottom: none;
            padding: 16px 0 4px;
        }
        .sp-cat-card .sphotography-module-body {
            padding: 6px 0 2px;
        }
        /* 卡片内的实时预览容器（若预览卡沿用旧 id）不再重复卡片外观。 */
        .sp-cat-card #sphotography-preview-sticky-wrap {
            background: transparent;
            border: none;
            box-shadow: none;
            padding: 0;
            margin: 0;
        }
        /* v1.4.0: force every board to fill the main column regardless of
           parent flex/grid quirks. */
        .sphotography-settings-main > .sphotography-module,
        .sphotography-settings-main > .sp-cat,
        .sphotography-settings-main > #sphotography-preview-sticky-wrap {
            width: 100%;
            min-width: 0;
        }
        .sphotography-toc-inner {
            background: var(--sp-surface);
            border: 1px solid var(--sp-border);
            border-radius: 14px;
            box-shadow: var(--sp-shadow);
            padding: 16px 14px;
        }
        /* v1.4.6 (item 7): 设置搜索框，位于索引上方。 */
        .sphotography-toc-search {
            position: relative;
            margin: 0 0 12px 0;
        }
        .sphotography-toc-search-ico {
            position: absolute;
            left: 11px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--sp-text-muted);
            /* v1.4.9 (item 9): 固定图标尺寸（覆盖 dashicons 默认 20px），避免字形超出图标盒撞到文字 */
            font-size: 16px !important;
            width: 18px !important;
            height: 18px !important;
            line-height: 18px !important;
            text-align: center;
            pointer-events: none;
        }
        /* v1.4.9 (item 9): 左内边距再加大，让占位/输入文字彻底避开放大镜图标，不再重叠 */
        .sphotography-toc-search-input {
            width: 100%;
            box-sizing: border-box;
            padding: 7px 10px 7px 40px;
            border: 1px solid var(--sp-border);
            border-radius: 8px;
            background: var(--sp-surface-2);
            color: var(--sp-text);
            font-size: 0.85rem;
            line-height: 1.4;
            transition: border-color 140ms ease, box-shadow 140ms ease;
        }
        .sphotography-toc-search-input:focus {
            outline: none;
            border-color: var(--sp-accent);
            box-shadow: 0 0 0 2px color-mix(in srgb, var(--sp-accent) 25%, transparent);
        }
        .sphotography-toc-heading {
            margin: 0 0 10px 0;
            padding: 0 6px;
            font-size: 0.8125rem;
            font-weight: 600;
            letter-spacing: 0.04em;
            color: var(--sp-text-muted);
            text-transform: uppercase;
        }
        .sphotography-toc-nav {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .sphotography-toc-link {
            display: block;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 0.9rem;
            color: var(--sp-text-muted);
            text-decoration: none;
            border-left: 2px solid transparent;
            transition: background 140ms ease, color 140ms ease, border-color 140ms ease;
        }
        .sphotography-toc-link:hover {
            background: var(--sp-surface-2);
            color: var(--sp-accent);
        }
        .sphotography-toc-link.active {
            background: var(--sp-surface-2);
            color: var(--sp-accent);
            border-left-color: var(--sp-accent);
            font-weight: 600;
        }
        /* v1.4.0: nested TOC. Each parent is a button with a chevron; the
           children list is hidden by default and slides down when the
           group gains .is-expanded. The scroll-spy auto-expands the
           currently-visible category. */
        .sphotography-toc-group { display: block; }
        .sphotography-toc-parent {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 6px;
            width: 100%;
            background: transparent;
            border: none;
            font-family: inherit;
            cursor: pointer;
            text-align: left;
        }
        .sphotography-toc-parent .sphotography-toc-label { flex: 1 1 auto; }
        .sphotography-toc-chevron {
            width: 14px; height: 14px;
            font-size: 14px; line-height: 1;
            color: var(--sp-text-muted);
            transition: transform 160ms ease, color 160ms ease;
            flex: 0 0 auto;
        }
        .sphotography-toc-group.is-expanded > .sphotography-toc-parent .sphotography-toc-chevron {
            transform: rotate(90deg);
            color: var(--sp-accent);
        }
        /* v1.4.2: 更“丝滑”的子索引滑下——240ms 减速曲线 + 子项透明度同步淡入。 */
        .sphotography-toc-children {
            display: grid;
            grid-template-rows: 0fr;
            transition: grid-template-rows 240ms cubic-bezier(0.16,1,0.3,1);
            overflow: hidden;
        }
        .sphotography-toc-children > .sphotography-toc-child-wrap {
            min-height: 0;
            overflow: hidden;
            opacity: 0;
            transition: opacity 200ms ease;
        }
        .sphotography-toc-group.is-expanded > .sphotography-toc-children {
            grid-template-rows: 1fr;
        }
        .sphotography-toc-group.is-expanded > .sphotography-toc-children > .sphotography-toc-child-wrap {
            opacity: 1;
        }
        @media (prefers-reduced-motion: reduce) {
            .sphotography-toc-children,
            .sphotography-toc-children > .sphotography-toc-child-wrap { transition: none; }
        }
        .sphotography-toc-child {
            padding-left: 24px !important;
            font-size: 0.8125rem !important;
        }
        .sphotography-toc-group.is-expanded > .sphotography-toc-parent {
            color: var(--sp-accent);
            font-weight: 600;
        }
        .sphotography-toc-save {
            display: flex !important;
            align-items: center;
            justify-content: center;
            gap: 6px;
            width: 100%;
            margin-top: 14px;
            padding: 9px 12px !important;
            height: auto !important;
            border-radius: 8px !important;
            font-family: {$sp_serif};
            background: var(--sp-accent) !important;
            border-color: var(--sp-accent) !important;
            box-shadow: none !important;
            text-shadow: none !important;
            transition: transform 160ms cubic-bezier(0.16,1,0.3,1), filter 160ms ease;
        }
        .sphotography-toc-save:hover {
            filter: brightness(1.07);
            transform: translateY(-1px);
        }
        .sphotography-toc-save:active { transform: translateY(0); }
        @media (max-width: 960px) {
            .sphotography-settings-wrap .sphotography-settings-layout { display: block !important; }
            .sphotography-toc { display: none; }
        }
        /* v1.4.2: 页顶主题动态图标占位容器——现留空（仅 HTML 注释标记），
           日后放入 SVG 文件即显示。空时自动折叠不占空间。 */
        .sphotography-theme-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 0 14px 0;
        }
        .sphotography-theme-icon:empty { display: none; }
        .sphotography-theme-icon svg,
        .sphotography-theme-icon img { max-width: 96px; max-height: 96px; }
        .sphotography-settings-title {
            font-size: 1.9rem;
            font-weight: 700;
            margin: 0 0 4px 0;
            color: var(--sp-text);
            font-family: {$sp_serif};
            letter-spacing: 0.01em;
        }
        .sphotography-settings-subtitle {
            color: var(--sp-text-muted);
            margin: 0 0 28px 0;
            font-size: 0.9375rem;
        }
        .sphotography-module {
            background: var(--sp-surface);
            border: 1px solid var(--sp-border);
            border-radius: 14px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: var(--sp-shadow);
        }
        /* Categories: group modules and add visual separation */
        .sp-cat {
            margin-bottom: 40px;
        }
        .sp-cat-title {
            margin: 0 0 16px 0;
            padding: 12px 0;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--sp-text);
            font-family: {$sp_serif};
            letter-spacing: 0.01em;
            border-bottom: 2px solid var(--sp-border);
        }
        .sphotography-module {
            background: var(--sp-surface);
            border: 1px solid var(--sp-border);
            border-radius: 14px;
            margin-bottom: 20px;
            overflow: hidden;
            box-shadow: var(--sp-shadow);
        }
        .sphotography-module-header {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 16px 24px;
            background: var(--sp-surface-2);
            border-bottom: 1px solid var(--sp-border);
        }
        .sphotography-module-header h2 {
            margin: 0;
            font-size: 1.15rem;
            font-weight: 600;
            color: var(--sp-text);
            font-family: {$sp_serif};
        }
        .sphotography-module-header h3 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
            color: var(--sp-text);
            font-family: {$sp_serif};
        }
        .sphotography-module-icon {
            color: var(--sp-accent);
            font-size: 1.3rem;
            width: auto;
            height: auto;
        }
        .sphotography-module-body {
            padding: 20px 24px;
        }
        .sphotography-field {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--sp-border);
        }
        .sphotography-field:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        .sphotography-label {
            display: block;
            font-weight: 600;
            color: var(--sp-text);
            margin-bottom: 8px;
            font-size: 0.9375rem;
        }
        .sphotography-field-checkbox .sphotography-label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
        }
        .sphotography-field-checkbox input[type=\"checkbox\"] {
            margin: 0;
        }
        .sphotography-desc {
            color: var(--sp-text-muted);
            font-size: 0.8125rem;
            margin: 6px 0 0 0;
            line-height: 1.6;
        }
        .sphotography-color-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .sphotography-preset-colors {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }
        .sphotography-preset-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 2px solid transparent;
            cursor: pointer;
            transition: transform 180ms cubic-bezier(0.16,1,0.3,1);
            padding: 0;
            outline: none;
        }
        .sphotography-preset-btn:hover {
            transform: scale(1.12);
        }
        .sphotography-preset-btn.active {
            border-color: var(--sp-accent);
            box-shadow: 0 0 0 2px var(--sp-surface), 0 0 0 4px var(--sp-accent);
        }
        .sphotography-radio-group {
            display: flex;
            gap: 24px;
        }
        .sphotography-radio-label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        .sphotography-radio-text {
            font-size: 0.9375rem;
        }
        /* v1.4.2: 保存/重置按钮对现位于最后一张大板块卡片（系统）内部底部，
           以卡片内分隔线与上方选项区隔开。 */
        .sphotography-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--sp-border);
        }
        .sphotography-actions .button-large {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 24px;
            font-size: 0.9375rem;
            border-radius: 8px;
            font-family: {$sp_serif};
        }
        .sphotography-actions .button-primary {
            background: var(--sp-accent);
            border-color: var(--sp-accent);
            box-shadow: none;
            text-shadow: none;
            transition: transform 160ms cubic-bezier(0.16,1,0.3,1), filter 160ms ease;
        }
        .sphotography-actions .button-primary:hover {
            filter: brightness(1.07);
            transform: translateY(-1px);
        }
        .sphotography-actions .button-primary:active { transform: translateY(0); }
        #sphotography-reset-btn {
            color: #e05a4d;
            border-color: #e05a4d;
            background: transparent;
            transition: background 160ms ease, color 160ms ease;
        }
        #sphotography-reset-btn:hover {
            background: #e05a4d;
            color: #fff;
        }
        .sphotography-field input[type=\"text\"],
        .sphotography-field input[type=\"url\"],
        .sphotography-field input[type=\"number\"],
        .sphotography-field select,
        .sphotography-field textarea {
            width: 100%;
            max-width: 420px;
            border-radius: 8px;
            border: 1px solid var(--sp-border);
            background: var(--sp-surface-2);
            color: var(--sp-text);
            padding: 8px 12px;
            font-size: 0.9375rem;
            font-family: {$sp_serif};
        }
        .sphotography-field textarea {
            max-width: 520px;
        }
        .sphotography-field input:focus,
        .sphotography-field select:focus,
        .sphotography-field textarea:focus {
            border-color: var(--sp-accent);
            box-shadow: 0 0 0 1px var(--sp-accent);
            outline: none;
        }
        /* v1.2.8 — drop the default (often bright-blue) focus ring that browsers
           paint on clickable option controls (TOC index links, buttons, preset
           swatches, radios/checkboxes, advanced toggle) when activated by mouse.
           Keyboard focus still gets a subtle accent ring via :focus-visible so
           the page stays navigable without the ugly outline. Text inputs/selects
           keep their own accent box-shadow above and are excluded here. */
        .sphotography-settings-wrap .sphotography-toc-link:focus,
        .sphotography-settings-wrap .sphotography-preset-btn:focus,
        .sphotography-settings-wrap .sphotography-radio-label input:focus,
        .sphotography-settings-wrap .sphotography-field-checkbox input[type=\"checkbox\"]:focus,
        .sphotography-settings-wrap .sphotography-advanced-toggle:focus,
        .sphotography-settings-wrap .button:focus {
            outline: none;
            box-shadow: none;
        }
        .sphotography-settings-wrap .sphotography-toc-link:focus-visible,
        .sphotography-settings-wrap .sphotography-preset-btn:focus-visible,
        .sphotography-settings-wrap .sphotography-radio-label input:focus-visible,
        .sphotography-settings-wrap .sphotography-field-checkbox input[type=\"checkbox\"]:focus-visible,
        .sphotography-settings-wrap .sphotography-advanced-toggle:focus-visible,
        .sphotography-settings-wrap .button:focus-visible {
            outline: 2px solid var(--sp-accent);
            outline-offset: 2px;
        }
        /* Keep native form controls readable in dark mode. Without explicit
           colours on every state, browsers (and WordPress core form.css) fall
           back to a dark/black text colour on the select control and its
           option list — both when hovered and at rest — which becomes
           unreadable on the dark surface. We pin colour + background on the
           control, the option list, and the hovered/checked option, and use
           !important so core rules cannot override them. */
        .sphotography-field select {
            color: var(--sp-text) !important;
            background-color: var(--sp-surface-2) !important;
        }
        .sphotography-field select:hover,
        .sphotography-field select:focus,
        .sphotography-field select:active {
            color: var(--sp-text) !important;
            background-color: var(--sp-surface-2) !important;
        }
        .sphotography-field select option {
            color: var(--sp-text) !important;
            background-color: var(--sp-surface) !important;
        }
        .sphotography-field select option:hover,
        .sphotography-field select option:focus,
        .sphotography-field select option:checked,
        .sphotography-field select option:active {
            color: #ffffff !important;
            background-color: var(--sp-accent) !important;
        }
        .sphotography-field input[type=\"text\"]:hover,
        .sphotography-field input[type=\"url\"]:hover,
        .sphotography-field input[type=\"number\"]:hover,
        .sphotography-field textarea:hover {
            background: var(--sp-surface-2);
            color: var(--sp-text);
        }
        .sphotography-custom-date-field {
            margin-top: 12px;
        }
        /* v1.2.5 — advanced motion block + slider rows */
        .sphotography-advanced-toggle {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: none;
            border: none;
            padding: 0;
            cursor: pointer;
            color: var(--sp-accent);
            font-family: {$sp_serif};
            font-size: 0.9rem;
            font-weight: 600;
        }
        .sphotography-advanced-toggle .dashicons {
            transition: transform 160ms ease;
            font-size: 18px;
            width: 18px;
            height: 18px;
        }
        .sphotography-advanced-toggle[aria-expanded=\"true\"] .dashicons {
            transform: rotate(90deg);
        }
        .sphotography-advanced-body {
            margin-top: 14px;
            padding: 16px 18px;
            border: 1px dashed var(--sp-border);
            border-radius: 10px;
            background: var(--sp-surface-2);
        }
        .sphotography-advanced-group {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--sp-border);
        }
        .sphotography-advanced-group:last-of-type {
            border-bottom: none;
        }
        .sphotography-advanced-group-title {
            margin: 0 0 10px 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--sp-text);
        }
        .sphotography-sublabel {
            display: block;
            margin: 10px 0 4px 0;
            font-size: 0.8125rem;
            color: var(--sp-text-muted);
        }
        .sphotography-slider-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .sphotography-slider-row input[type=\"range\"] {
            flex: 1 1 auto;
            max-width: 320px;
        }
        .sphotography-slider-val {
            font-variant-numeric: tabular-nums;
            font-weight: 600;
            color: var(--sp-text);
            min-width: 3ch;
        }
        .sphotography-module-header .sphotography-module-icon {
            margin-right: 4px;
        }
        .sphotography-settings-wrap .notice {
            border-radius: 10px;
        }
        /* v1.2.6 — live map preview */
        .sphotography-map-preview {
            position: relative;
            width: 100%;
            height: 440px;
            border: 1px solid var(--sp-border);
            border-radius: 12px;
            overflow: hidden;
            background: var(--sp-surface-2);
        }
        .sphotography-map-preview iframe {
            width: 100%;
            height: 100%;
            border: 0;
            display: block;
        }
        .sphotography-map-preview-refresh {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.06);
            opacity: 0;
            pointer-events: none;
            transition: opacity 160ms ease;
        }
        .sphotography-map-preview.is-refreshing .sphotography-map-preview-refresh {
            opacity: 1;
        }
        .sphotography-map-preview.is-refreshing .sphotography-map-preview-refresh::after {
            content: '';
            position: absolute;
            left: 50%;
            top: 50%;
            width: 28px;
            height: 28px;
            margin: -14px 0 0 -14px;
            border: 3px solid rgba(255,255,255,0.5);
            border-top-color: var(--sp-accent);
            border-radius: 50%;
            animation: sphotographyPreviewSpin 0.7s linear infinite;
        }
        @keyframes sphotographyPreviewSpin { to { transform: rotate(360deg); } }
        /* Live-preview board (v1.3.9): a normal full-width board at the top of
           the page (NOT sticky). It occupies one index (TOC) slot. */
        #sphotography-preview-sticky-wrap {
            display: block;
            width: 100%;
            background: var(--sp-surface);
            border: 1px solid var(--sp-border);
            border-radius: {$sp_card_radius}px;
            box-shadow: var(--sp-shadow);
            padding: 20px 24px;
            margin-bottom: 24px;
        }
        /* v1.2.9 — experimental AI risk banner */
        .sphotography-ai-risk {
            border: 1px solid rgba(224,90,77,0.35);
            background: rgba(224,90,77,0.08);
            border-radius: 10px;
            padding: 14px 16px;
            margin-bottom: 20px;
        }
        .sphotography-ai-risk strong {
            display: block;
            color: #e05a4d;
            margin-bottom: 8px;
            font-size: 0.9375rem;
        }
        .sphotography-ai-risk ul {
            margin: 0;
            padding-left: 18px;
            color: var(--sp-text-muted);
            font-size: 0.8125rem;
            line-height: 1.7;
        }
        .sphotography-ai-risk li { margin: 0 0 2px 0; }
        /* v1.3.0 — sub-heading inside the experimental module */
        .sphotography-subhead {
            margin: 8px 0 14px;
            padding-top: 14px;
            border-top: 1px solid var(--sp-border);
            font-size: 0.9375rem;
            font-weight: 600;
            color: var(--sp-text);
        }
        /* v1.4.2 — 「添加友链」居中弹窗（modal）。 */
        .sp-fl-modal-overlay {
            position: fixed;
            inset: 0;
            z-index: 100000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(0,0,0,0.45);
            backdrop-filter: blur(2px);
            -webkit-backdrop-filter: blur(2px);
        }
        .sp-fl-modal-overlay[hidden] { display: none; }
        .sp-fl-modal {
            position: relative;
            width: 100%;
            max-width: 480px;
            max-height: calc(100vh - 48px);
            overflow-y: auto;
            background: var(--sp-surface);
            color: var(--sp-text);
            border: 1px solid var(--sp-border);
            border-radius: {$sp_card_radius}px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.35);
            padding: 24px 26px 22px;
            font-family: {$sp_serif};
        }
        .sp-fl-modal-title {
            margin: 0 0 16px 0;
            font-size: 1.15rem;
            font-weight: 700;
            color: var(--sp-text);
        }
        .sp-fl-modal-close {
            position: absolute;
            top: 12px;
            right: 12px;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            border-radius: 50%;
            background: var(--sp-surface-2);
            color: var(--sp-text-muted);
            font-size: 20px;
            line-height: 1;
            cursor: pointer;
            transition: background 140ms ease, color 140ms ease;
        }
        .sp-fl-modal-close:hover { background: var(--sp-accent); color: #fff; }
        .sp-fl-modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            align-items: center;
            margin-top: 18px;
        }
        .sp-fl-modal-actions .button-primary {
            background: var(--sp-accent);
            border-color: var(--sp-accent);
            box-shadow: none;
            text-shadow: none;
        }
        body.sp-fl-modal-open { overflow: hidden; }
    ";

    wp_add_inline_style( 'wp-color-picker', $settings_css );

    wp_enqueue_script(
        'sphotography-admin-settings',
        get_template_directory_uri() . '/assets/js/admin-settings.js',
        array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ),
        SPHOTOGRAPHY_VERSION,
        true
    );

    wp_localize_script( 'sphotography-admin-settings', 'SphotographyAdmin', array(
        'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
        'friendNonce' => wp_create_nonce( 'sphotography_friend_links' ),
        'currentVersion' => SPHOTOGRAPHY_VERSION,
        'updateUrl'      => 'https://raw.githubusercontent.com/ShirazuNagisa/sphotography/master/version.json',
        'releaseUrl'     => 'https://github.com/ShirazuNagisa/sphotography/releases',
        'updateNonce'    => wp_create_nonce( 'sphotography_update_nonce' ),
        'geoRebuildNonce' => wp_create_nonce( 'sphotography_geo_rebuild' ),
        // v1.4.0: nonce for the EXIF backfill batch AJAX.
        'exifBackfillNonce' => wp_create_nonce( 'sphotography_exif_backfill' ),
        'exifBackfillRunning' => __( '处理中…', 'sphotography' ),
        'exifBackfillDone'    => __( '✓ 完成，已处理 %1$d 张照片，新提取了 %2$d 个 EXIF 字段。', 'sphotography' ),
        'exifBackfillFail'    => __( '回填失败：', 'sphotography' ),
        // v1.4.6 (item 1): 一键预生成全站照片地址（逆地理编码）。走 REST，用 wp_rest nonce。
        'restNonce'          => wp_create_nonce( 'wp_rest' ),
        'geoBackfillUrl'     => esc_url_raw( rest_url( 'sphotography/v1/geocode-backfill' ) ),
        'geoBackfillRunning' => __( '正在排入后台任务…', 'sphotography' ),
        'geoBackfillDone'    => __( '✓ 已为 %d 篇文章排入后台预生成任务，将在后台按语言逐个解析并持久缓存地址。', 'sphotography' ),
        'geoBackfillNone'    => __( '✓ 没有需要新排期的文章（可能任务已在队列中）。', 'sphotography' ),
        'geoBackfillFail'    => __( '预生成失败：', 'sphotography' ),
        'aiTestNonce'    => wp_create_nonce( 'sphotography_ai_test' ),
        'aiTesting'      => __( '测试中…', 'sphotography' ),
        'aiTestOk'       => __( '连接成功', 'sphotography' ),
        'aiTestFail'     => __( '连接失败：', 'sphotography' ),
        'previewUrl'     => sphotography_map_preview_url(),
        'resetConfirm'   => __( '确定要重置所有设置为默认值吗？此操作不可撤销。', 'sphotography' ),
        'unsavedConfirm' => __( '有未保存的修改，确定放弃并离开此页面吗？', 'sphotography' ),
        'updateConfirm'  => __( "确定从 master 分支下载并覆盖主题文件吗？\n\n配置数据存在数据库中，不受影响。\n更新后请重新激活主题。", 'sphotography' ),
    ) );
}
