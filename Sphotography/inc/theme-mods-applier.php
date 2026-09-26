<?php
// 应用主题设置到前台

// 1. 读取主题设置
function sphotography_get_mod( $key ) {
    $defaults = sphotography_get_default_settings();
    $value    = get_theme_mod( 'sphotography_' . $key, $defaults[ $key ] );
    return sphotography_maybe_preview_override( $key, $value );
}

// 1b. 实时预览覆盖（设置页 iframe 中通过查询参数临时覆盖设置）
function sphotography_is_map_preview() {
    static $is = null;
    if ( null !== $is ) {
        return $is;
    }
    $is = isset( $_GET['sp_preview'] )
        && current_user_can( 'edit_theme_options' )
        && isset( $_GET['sp_nonce'] )
        && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['sp_nonce'] ) ), 'sphotography_map_preview' );
    return $is;
}

// 主题设置 key → [查询参数, 类型] 映射
function sphotography_preview_param_map() {
    return array(
        'primary_color'        => array( 'sp_primary', 'hex' ),
        'night_mode'           => array( 'sp_night', 'enum', array( 'system', 'light', 'dark' ) ),
        'map_region'           => array( 'sp_mapregion', 'enum', array( 'global', 'china' ) ),
        'map_china_provider'   => array( 'sp_chinaprov', 'enum', array( 'tianditu', 'amap', 'tencent' ) ),
        'map_china_type'       => array( 'sp_chinatype', 'enum', array( 'standard', 'satellite' ) ),
        'map_tianditu_key'     => array( 'sp_tdtkey', 'token' ),
        'map_style'            => array( 'sp_mapstyle', 'enum', array( 'auto', 'satellite', 'terrain', 'voyager', 'watercolor', 'custom' ) ),
        'map_style_custom_url' => array( 'sp_mapurl', 'url' ),
        'marker_mode'          => array( 'sp_markermode', 'enum', array( 'droplet', 'tag', 'region' ) ),
        'cluster_radius'       => array( 'sp_cluster', 'int', 10, 60 ),
        'droplet_goo_strength' => array( 'sp_goo', 'int', 3, 12 ),
        'region_granularity'   => array( 'sp_granularity', 'enum', array( 'province', 'city' ) ),
        'region_intensity'     => array( 'sp_intensity', 'int', 0, 100 ),
    );
}

function sphotography_maybe_preview_override( $key, $value ) {
    if ( ! sphotography_is_map_preview() ) {
        return $value;
    }
    $map = sphotography_preview_param_map();
    if ( ! isset( $map[ $key ] ) ) {
        return $value;
    }
    $spec  = $map[ $key ];
    $param = $spec[0];
    if ( ! isset( $_GET[ $param ] ) ) {
        return $value;
    }
    $raw = wp_unslash( $_GET[ $param ] );
    switch ( $spec[1] ) {
        case 'hex':
            $c = sanitize_hex_color( (string) $raw );
            return $c ? $c : $value;
        case 'url':
            return esc_url_raw( trim( (string) $raw ), array( 'https' ) );
        case 'enum':
            return in_array( $raw, $spec[2], true ) ? $raw : $value;
        case 'int':
            return min( max( (int) $raw, $spec[2] ), $spec[3] );
        case 'token':
            return preg_replace( '/[^A-Za-z0-9]/', '', (string) $raw );
    }
    return $value;
}

// 预览模式的地图页面 URL
function sphotography_map_preview_url() {
    $pages = get_posts( array(
        'post_type'      => 'page',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => '_wp_page_template',
        'meta_value'     => 'template-map.php',
    ) );
    if ( empty( $pages ) ) {
        // Fall back to the front page if it is set to the map template.
        $front = (int) get_option( 'page_on_front' );
        if ( $front && 'template-map.php' === get_page_template_slug( $front ) ) {
            $pages = array( $front );
        }
    }
    if ( empty( $pages ) ) {
        return '';
    }
    return add_query_arg(
        array(
            'sp_preview' => '1',
            'sp_nonce'   => wp_create_nonce( 'sphotography_map_preview' ),
        ),
        get_permalink( $pages[0] )
    );
}

// 2. 输出动态 CSS 变量到 <head>
function sphotography_output_head_links() {
    if ( ! sphotography_is_map_view() ) {
        return;
    }
    // DNS preconnect for CDN resources to reduce latency
    $cdn = sphotography_get_cdn_urls();
    ?>
    <link rel="preconnect" href="https://<?php echo esc_attr( $cdn['domain'] ); ?>" crossorigin>
    <link rel="preconnect" href="https://basemaps.cartocdn.com" crossorigin>
    <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="dns-prefetch" href="https://<?php echo esc_attr( $cdn['domain'] ); ?>">
    <link rel="dns-prefetch" href="https://basemaps.cartocdn.com">
    <?php
}
add_action( 'wp_head', 'sphotography_output_head_links', 1 );

function sphotography_output_dynamic_css() {
    if ( ! sphotography_is_map_view() ) {
        return;
    }

    $primary   = sphotography_get_mod( 'primary_color' );
    $radius    = (int) sphotography_get_mod( 'card_radius' );
    $shadow    = sphotography_get_mod( 'card_shadow' );
    $scheme    = sphotography_get_mod( 'dark_scheme' );
    $immersive = sphotography_get_mod( 'immersive_color' );

    $scheme_bgs = array(
        'default' => '#0b0b0b',
        'blue'    => '#0a1628',
        'purple'  => '#1a0a1e',
    );
    $bg = isset( $scheme_bgs[ $scheme ] ) ? $scheme_bgs[ $scheme ] : '#0b0b0b';

    $shadow_value = ( $shadow === 'deep' )
        ? '0 8px 32px rgba(0,0,0,0.4)'
        : '0 1px 3px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)';

    $immersive_bg = $immersive ? $primary : 'transparent';

    // Region fill opacity (0–1) from the intensity percentage (region mode).
    $region_opacity = max( 0, min( 100, (int) sphotography_get_mod( 'region_intensity' ) ) ) / 100;

    ?>
    <style id="sphotography-dynamic-css">
        :root {
            --sphotography-primary-color: <?php echo esc_attr( $primary ); ?>;
            --sphotography-card-radius: <?php echo esc_attr( $radius ); ?>px;
            --sphotography-card-shadow: <?php echo esc_attr( $shadow_value ); ?>;
            --sphotography-bg: <?php echo esc_attr( $bg ); ?>;
            --sphotography-immersive-bg: <?php echo esc_attr( $immersive_bg ); ?>;
            --sphotography-region-opacity: <?php echo esc_attr( $region_opacity ); ?>;
        }
    </style>
    <?php
}
add_action( 'wp_head', 'sphotography_output_dynamic_css', 20 );

// 3. 添加 body 类名
function sphotography_body_classes( $classes ) {
    if ( ! sphotography_is_map_view() ) {
        return $classes;
    }

    $night_mode = sphotography_get_mod( 'night_mode' );

    if ( $night_mode === 'dark' ) {
        $classes[] = 'sphotography-night-force-dark';
    } elseif ( $night_mode === 'light' ) {
        $classes[] = 'sphotography-night-force-light';
    } else {
        $classes[] = 'sphotography-night-system';
    }

    $smooth = sphotography_get_mod( 'smooth_scroll' );
    if ( $smooth === 'disabled' ) {
        $classes[] = 'sphotography-scroll-disabled';
    } elseif ( $smooth === 'mouse-only' ) {
        $classes[] = 'sphotography-scroll-mouse';
    } else {
        $classes[] = 'sphotography-scroll-enabled';
    }

    // Frontend font: serif (default) keeps the theme's Noto Serif SC stack;
    // 'wordpress' switches to the system sans-serif stack; v1.4.7 (item 6) adds
    // 'pingfang' (苹方, Apple-native / graceful fallback) and 'songti' (宋体,
    // cross-platform system serif + Noto Serif SC fallback). All are system-font
    // stacks — no bundled webfonts (PingFang is proprietary).
    $frontend_font = sphotography_get_mod( 'frontend_font' );
    if ( in_array( $frontend_font, array( 'misans', 'harmonyos' ), true ) ) {
        $classes[] = 'sphotography-font-' . $frontend_font;
    } elseif ( 'wordpress' === $frontend_font ) {
        $classes[] = 'sphotography-font-wordpress';
    } elseif ( 'pingfang' === $frontend_font ) {
        $classes[] = 'sphotography-font-pingfang';
    } elseif ( 'songti' === $frontend_font ) {
        $classes[] = 'sphotography-font-songti';
    }

    // Cursor style: 'dot' swaps the OS arrow for a dot+ring pointer (v1.2.8);
    // 'rounded' (v1.4.5, default) enables the JS-driven rounded/magnetic cursor
    // (idle rounded V-arrow that adsorbs onto interactive elements). The rounded
    // engine (app.js) further gates itself on hover/fine-pointer + intro-complete.
    $cursor_style = sphotography_get_mod( 'cursor_style' );
    if ( 'dot' === $cursor_style ) {
        $classes[] = 'sphotography-cursor-dot';
    } elseif ( 'rounded' === $cursor_style ) {
        $classes[] = 'sphotography-cursor-rounded';
    }

    // Marker mode drives the frontend map rendering; expose it as a body class
    // so mode-specific styling (e.g. region fill vs droplets) can key off it.
    $classes[] = 'sphotography-markers-' . sphotography_get_mod( 'marker_mode' );

    return $classes;
}
add_filter( 'body_class', 'sphotography_body_classes' );

// 3b. 个人信息统计与自定义链接
/**
 * Counts shown in the expanded profile view: published posts, non-empty
 * categories, and lit administrative regions. Cached per request.
 *
 * @return array{posts:int, categories:int, regions:int}
 */
function sphotography_profile_stats() {
    static $cached = null;
    if ( null !== $cached ) {
        return $cached;
    }
    $counts = wp_count_posts( 'post' );
    $posts  = $counts && isset( $counts->publish ) ? (int) $counts->publish : 0;
    $cats   = get_categories( array( 'hide_empty' => true ) );
    $regions = function_exists( 'sphotography_lit_region_count' ) ? sphotography_lit_region_count() : 0;
    $cached = array(
        'posts'      => $posts,
        'categories' => is_array( $cats ) ? count( $cats ) : 0,
        'regions'    => $regions,
    );
    return $cached;
}

/**
 * Parse the "custom_links" mod (one "名称|链接" per line) into a list of
 * validated { name, url } pairs. Lines without a valid URL are dropped.
 *
 * @return array[] List of array{name:string, url:string}.
 */
function sphotography_parse_profile_links() {
    $raw = (string) sphotography_get_mod( 'custom_links' );
    if ( '' === trim( $raw ) ) {
        return array();
    }
    $links = array();
    $lines = preg_split( '/\r\n|\r|\n/', $raw );
    foreach ( $lines as $line ) {
        $line = trim( $line );
        if ( '' === $line ) {
            continue;
        }
        $parts = explode( '|', $line, 2 );
        if ( count( $parts ) === 2 ) {
            $name = trim( $parts[0] );
            $url  = trim( $parts[1] );
        } else {
            // No separator: use the URL itself as the label.
            $name = trim( $parts[0] );
            $url  = $name;
        }
        $url = esc_url_raw( $url );
        if ( '' === $url ) {
            continue;
        }
        if ( '' === $name ) {
            $name = $url;
        }
        $links[] = array( 'name' => $name, 'url' => $url );
    }
    return $links;
}

/**
 * Render the shared expanded-profile inner markup (avatar, name, bio, stats,
 * custom links). Used by both the bottom-right card and the sidebar panel.
 *
 * @param array $args {
 *     @type string $avatar   Avatar URL (may be empty).
 *     @type string $name     Display name.
 *     @type string $bio      Bio text (may be empty).
 *     @type string $initial  Placeholder initial when no avatar.
 * }
 */
function sphotography_render_profile_expand( $args ) {
    $avatar  = isset( $args['avatar'] ) ? $args['avatar'] : '';
    $name    = isset( $args['name'] ) ? $args['name'] : '';
    $bio     = isset( $args['bio'] ) ? $args['bio'] : '';
    $initial = isset( $args['initial'] ) ? $args['initial'] : '';
    $stats   = sphotography_profile_stats();
    $links   = sphotography_parse_profile_links();
    ?>
    <div class="profile-expand-inner">
        <?php if ( $avatar ) : ?>
            <img src="<?php echo esc_url( $avatar ); ?>" alt="" class="profile-expand-avatar">
        <?php else : ?>
            <span class="profile-expand-avatar profile-expand-avatar--placeholder"><?php echo esc_html( $initial ); ?></span>
        <?php endif; ?>
        <div class="profile-expand-name"><?php echo esc_html( $name ); ?></div>
        <?php if ( '' !== trim( (string) $bio ) ) : ?>
            <div class="profile-expand-bio"><?php echo esc_html( $bio ); ?></div>
        <?php endif; ?>
        <div class="profile-expand-stats">
            <div class="profile-stat"><span class="profile-stat-num"><?php echo (int) $stats['posts']; ?></span><span class="profile-stat-label"><?php esc_html_e( '文章', 'sphotography' ); ?></span></div>
            <span class="profile-stat-sep" aria-hidden="true"></span>
            <div class="profile-stat"><span class="profile-stat-num"><?php echo (int) $stats['categories']; ?></span><span class="profile-stat-label"><?php esc_html_e( '分类', 'sphotography' ); ?></span></div>
            <span class="profile-stat-sep" aria-hidden="true"></span>
            <div class="profile-stat"><span class="profile-stat-num"><?php echo (int) $stats['regions']; ?></span><span class="profile-stat-label"><?php esc_html_e( '地块', 'sphotography' ); ?></span></div>
        </div>
        <?php if ( ! empty( $links ) ) : ?>
            <div class="profile-expand-links">
                <?php foreach ( $links as $link ) : ?>
                    <a class="profile-expand-link" href="<?php echo esc_url( $link['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $link['name'] ); ?></a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// 4. 传递设置到 JS
function sphotography_localize_data() {
    if ( ! sphotography_is_map_view() ) {
        return;
    }

    // Settings
    wp_localize_script( 'sphotography-app', 'SphotographySettings', array(
        'nightMode'        => sphotography_get_mod( 'night_mode' ),
        'darkScheme'       => sphotography_get_mod( 'dark_scheme' ),
        'preloaderStyle'   => sphotography_get_mod( 'preloader_style' ),
        'dateFormat'       => sphotography_get_mod( 'date_format' ),
        'customDateFormat' => sphotography_get_mod( 'custom_date_format' ),
        'enableHitokoto'   => (bool) sphotography_get_mod( 'enable_hitokoto' ),
        'smoothScroll'     => sphotography_get_mod( 'smooth_scroll' ),
        'entryAnimation'   => (bool) sphotography_get_mod( 'entry_animation' ),
        'pjaxAnimation'    => (bool) sphotography_get_mod( 'pjax_animation' ),
        'primaryColor'     => sphotography_get_mod( 'primary_color' ),
        'frontendFont'     => sphotography_get_mod( 'frontend_font' ),
        // Device-aware sidebar default (v1.3.7): resolved server-side with
        // wp_is_mobile() so the JS reads one boolean, matching the body class
        // emitted in template-map.php.
        'sidebarDefaultOpen' => (bool) ( wp_is_mobile()
            ? sphotography_get_mod( 'sidebar_default_open_mobile' )
            : sphotography_get_mod( 'sidebar_default_open_desktop' ) ),
        'articleCardSize'  => sphotography_get_mod( 'article_card_size' ),
        'expandColumns'    => sphotography_get_mod( 'expand_columns' ), // v1.4.8 边栏展开页列数
        'readingInfo'      => (bool) sphotography_get_mod( 'reading_info' ),
        'readingSpeedCjk'  => (int) sphotography_get_mod( 'reading_speed_cjk' ),
        'readingSpeedLatin' => (int) sphotography_get_mod( 'reading_speed_latin' ),
        'viewCounter'      => (bool) sphotography_get_mod( 'view_counter' ),
        'aiSummary'        => function_exists( 'sphotography_ai_summary_enabled' ) ? sphotography_ai_summary_enabled() : false,
        'mapRegion'        => sphotography_get_mod( 'map_region' ),
        'mapChinaProvider' => sphotography_get_mod( 'map_china_provider' ),
        'mapChinaType'     => sphotography_get_mod( 'map_china_type' ),
        'mapTiandituKey'   => sphotography_get_mod( 'map_tianditu_key' ),
        'mapStyle'         => sphotography_get_mod( 'map_style' ),
        'mapStyleCustomUrl' => sphotography_get_mod( 'map_style_custom_url' ),
        // Motion personality (v1.2.5) — raw picker values; app.js resolves them.
        'motionTier'       => sphotography_get_mod( 'motion_tier' ),
        'motionArticleEasing' => sphotography_get_mod( 'motion_article_easing' ),
        'motionArticleScale'  => (int) sphotography_get_mod( 'motion_article_scale' ),
        'motionDropletEasing' => sphotography_get_mod( 'motion_droplet_easing' ),
        'motionDropletScale'  => (int) sphotography_get_mod( 'motion_droplet_scale' ),
        'motionIgnoreReduced' => (bool) sphotography_get_mod( 'motion_ignore_reduced' ),
        // Marker mode & styling (v1.2.6).
        'markerMode'       => sphotography_get_mod( 'marker_mode' ),
        'clusterRadius'    => (int) sphotography_get_mod( 'cluster_radius' ),
        'tagLegend'        => (bool) sphotography_get_mod( 'tag_legend' ),
        'regionGranularity' => sphotography_get_mod( 'region_granularity' ),
        'regionIntensity'  => (int) sphotography_get_mod( 'region_intensity' ),
        // Full slug→colour map for every region_tag term (override-or-hash),
        // so the frontend never hashes slugs itself.
        'tagColors'        => sphotography_all_tag_colors(),
    ) );

    // ============================================
    // Embed markers as inline JSON (bypasses REST API 403).
    // Markers come from the shared builder: every geolocated image used by a
    // published post, each linking back to its parent post. This mirrors the
    // sphotography/v1/photos REST route exactly.
    // ============================================
    $photo_data_arr = sphotography_collect_all_markers();

    // v1.4.7 (item 8): slim the inline payload. The full-resolution image URL is
    // only ever needed when a photo's detail view is opened (a deliberate click),
    // so we drop it from the inline blob — the map + popups render from the
    // thumbnail — and the frontend fetches the full URL on demand by attachment id
    // (sphotography/v1/photo-full/<id>). This trims the inlined HTML on
    // photo-heavy sites. The REST /photos route still returns full_image intact.
    foreach ( $photo_data_arr as &$sp_marker ) {
        unset( $sp_marker['full_image'] );
    }
    unset( $sp_marker );

    // Embed recent posts
    $recent_posts = get_posts( array(
        'post_type'      => 'post',
        'posts_per_page' => 50,
        'post_status'    => 'publish',
    ) );

    $post_data = array();
    foreach ( $recent_posts as $p ) {
        $thumb_p = '';
        if ( has_post_thumbnail( $p->ID ) ) {
            $t = wp_get_attachment_image_src( get_post_thumbnail_id( $p->ID ), 'thumbnail' );
            if ( $t ) { $thumb_p = $t[0]; }
        }

        $cats = wp_get_post_categories( $p->ID, array( 'fields' => 'all' ) );
        $terms_data = array();
        foreach ( $cats as $cat ) {
            $terms_data[] = array(
                'taxonomy' => 'category',
                'name'     => $cat->name,
                'slug'     => $cat->slug,
            );
        }
        $region_terms = wp_get_post_terms( $p->ID, 'region_tag', array( 'fields' => 'all' ) );
        foreach ( $region_terms as $rt ) {
            $terms_data[] = array(
                'taxonomy' => 'region_tag',
                'name'     => $rt->name,
                'slug'     => $rt->slug,
            );
        }

        $post_data[] = array(
            'id'      => $p->ID,
            'title'   => get_the_title( $p->ID ),
            'date'    => $p->post_date,
            'excerpt' => strip_tags( $p->post_excerpt ?: wp_trim_words( $p->post_content, 30 ) ),
            // v1.4.9: card excerpt with precedence (manual > AI 概述 > auto), matching the
            // sp_card_excerpt REST field so the preload and REST paths agree.
            'cardExcerpt' => function_exists( 'sphotography_card_excerpt' ) ? sphotography_card_excerpt( $p->ID ) : '',
            'thumb'   => $thumb_p,
            'cover'   => function_exists( 'sphotography_cover_url' ) ? sphotography_cover_url( $p->ID, 'large' ) : '', // v1.4.6 (item 9)
            'terms'   => $terms_data,
            'link'    => get_permalink( $p->ID ),
            'writeLocation' => function_exists( 'sphotography_wloc_get' ) ? sphotography_wloc_get( $p->ID ) : '',
            'wordCount' => function_exists( 'sphotography_post_word_count' ) ? sphotography_post_word_count( $p->ID ) : 0,
            'views'   => function_exists( 'sphotography_get_views' ) ? sphotography_get_views( $p->ID ) : 0,
        );
    }

    // Inline script with all data (before map initializes)
    wp_add_inline_script( 'sphotography-app',
        'var SphotographyInlineData = ' . json_encode( array(
            'photos' => $photo_data_arr,
            'posts'  => $post_data,
        ) ) . ';',
        'before'
    );

    // Region mode: emit only the boundary polygons that actually contain
    // photos (province adcode for province granularity, plus city adcode for
    // China). The frontend picks the right feature per granularity at render.
    if ( 'region' === sphotography_get_mod( 'marker_mode' ) ) {
        $used_ids = array();
        foreach ( $photo_data_arr as $m ) {
            if ( ! empty( $m['prov_adcode'] ) ) { $used_ids[ $m['prov_adcode'] ] = true; }
            if ( ! empty( $m['city_adcode'] ) ) { $used_ids[ $m['city_adcode'] ] = true; }
        }
        $geo = sphotography_geo_features_for_ids( array_keys( $used_ids ) );
        wp_add_inline_script( 'sphotography-app',
            'var SphotographyGeo = ' . wp_json_encode( $geo ) . ';',
            'before'
        );
    }
}
add_action( 'wp_enqueue_scripts', 'sphotography_localize_data', 20 );
