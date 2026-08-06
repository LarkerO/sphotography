<?php
// 主题主函数

// 0. 加载包含文件
$sphotography_theme = wp_get_theme();
if ( ! defined( 'SPHOTOGRAPHY_VERSION' ) ) {
    define( 'SPHOTOGRAPHY_VERSION', $sphotography_theme->get( 'Version' ) );
}

require_once get_template_directory() . '/admin/theme-settings.php';
require_once get_template_directory() . '/admin/admin-style.php';
require_once get_template_directory() . '/inc/theme-mods-applier.php';
require_once get_template_directory() . '/inc/region-tag-color.php';
require_once get_template_directory() . '/inc/region-index.php';
require_once get_template_directory() . '/inc/ai.php';
require_once get_template_directory() . '/inc/ip-region.php';
require_once get_template_directory() . '/inc/article-location.php';
require_once get_template_directory() . '/inc/post-metrics.php';
require_once get_template_directory() . '/inc/comments.php';
require_once get_template_directory() . '/inc/friend-links.php';
require_once get_template_directory() . '/inc/guestbook.php';
require_once get_template_directory() . '/inc/photo-wall.php';
require_once get_template_directory() . '/inc/i18n.php';
require_once get_template_directory() . '/inc/geocode.php';       // v1.4.4: 逆地理编码代理（图片位置弹窗）
require_once get_template_directory() . '/inc/announcement.php';  // v1.4.4: 公告页
require_once get_template_directory() . '/inc/article-cover.php'; // v1.4.6: 文章头图（边栏模块模糊背景）
require_once get_template_directory() . '/inc/site-stats.php';    // v1.4.8: 站点统计（访问人数、运行时间、汇总）
require_once get_template_directory() . '/inc/config-io.php';     // v1.4.9: 配置导出/导入（JSON）

// 1.（已移除）自定义文章类型
// Sphotography now uses native WordPress posts exclusively. Articles are
// authored as ordinary posts; any image inside a post that carries
// latitude/longitude (set in the media library or auto-read from EXIF) is
// plotted on the map as a marker linking back to its parent post. The old
// "photograph" post type has been retired — see the sphotography/v1/photos
// REST route below for how markers are now derived.

// 2. 注册分类法 region_tag
function sphotography_register_region_tag_taxonomy() {
    $labels = array(
        'name'                       => _x( 'Region Tags', 'Taxonomy General Name', 'sphotography' ),
        'singular_name'              => _x( 'Region Tag', 'Taxonomy Singular Name', 'sphotography' ),
        'menu_name'                  => __( 'Region Tags', 'sphotography' ),
        'all_items'                  => __( 'All Region Tags', 'sphotography' ),
        'parent_item'                => __( 'Parent Region Tag', 'sphotography' ),
        'parent_item_colon'          => __( 'Parent Region Tag:', 'sphotography' ),
        'new_item_name'              => __( 'New Region Tag Name', 'sphotography' ),
        'add_new_item'               => __( 'Add New Region Tag', 'sphotography' ),
        'edit_item'                  => __( 'Edit Region Tag', 'sphotography' ),
        'update_item'                => __( 'Update Region Tag', 'sphotography' ),
        'view_item'                  => __( 'View Region Tag', 'sphotography' ),
        'separate_items_with_commas' => __( 'Separate tags with commas', 'sphotography' ),
        'add_or_remove_items'        => __( 'Add or remove tags', 'sphotography' ),
        'choose_from_most_used'      => __( 'Choose from the most used', 'sphotography' ),
        'popular_items'              => __( 'Popular Region Tags', 'sphotography' ),
        'search_items'               => __( 'Search Region Tags', 'sphotography' ),
        'not_found'                  => __( 'Not Found', 'sphotography' ),
        'no_terms'                   => __( 'No region tags', 'sphotography' ),
        'items_list'                 => __( 'Region Tags list', 'sphotography' ),
        'items_list_navigation'      => __( 'Region Tags list navigation', 'sphotography' ),
    );
    $args = array(
        'labels'                     => $labels,
        'hierarchical'               => false,
        'public'                     => true,
        'show_ui'                    => true,
        'show_admin_column'          => true,
        'show_in_nav_menus'          => false,
        'show_tagcloud'              => false,
        'show_in_rest'               => true,
        'rest_base'                  => 'region_tag',
        'rest_controller_class'      => 'WP_REST_Terms_Controller',
    );
    register_taxonomy( 'region_tag', array( 'post' ), $args );
}
add_action( 'init', 'sphotography_register_region_tag_taxonomy' );

// 3. 注册自定义元数据
function sphotography_register_photograph_meta() {
    $meta_fields = array(
        'latitude' => array(
            'type'        => 'number',
            'description' => 'Latitude coordinate of the photograph location',
            'default'     => 0,
        ),
        'longitude' => array(
            'type'        => 'number',
            'description' => 'Longitude coordinate of the photograph location',
            'default'     => 0,
        ),
        'camera_info' => array(
            'type'        => 'string',
            'description' => 'Camera model and shooting parameters',
            'default'     => '',
        ),
        'taken_at' => array(
            'type'        => 'string',
            'description' => 'Date the photograph was taken (Y-m-d format)',
            'default'     => '',
        ),
        'taken_time' => array(
            'type'        => 'string',
            'description' => 'Time the photograph was taken (H:i:s format)',
            'default'     => '',
        ),
        'aperture' => array(
            'type'        => 'string',
            'description' => 'Aperture value (f/N format)',
            'default'     => '',
        ),
        'shutter' => array(
            'type'        => 'string',
            'description' => 'Shutter speed (e.g. 1/200 or 2s)',
            'default'     => '',
        ),
        'iso' => array(
            'type'        => 'string',
            'description' => 'ISO speed rating',
            'default'     => '',
        ),
        'wall_pinned' => array(
            'type'        => 'string',
            'description' => 'Whether the photo is pinned to the photo wall (1 or unset)',
            'default'     => '',
        ),
    );

    foreach ( $meta_fields as $key => $args ) {
        // Register for standard posts (used for post-level coordinates if any)
        register_post_meta( 'post', $key, array(
            'show_in_rest'  => true, 'single' => true,
            'type' => $args['type'], 'description' => $args['description'],
            'default' => $args['default'],
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ) );
        // Register for attachments (media library images)
        register_post_meta( 'attachment', $key, array(
            'show_in_rest'  => true, 'single' => true,
            'type' => $args['type'], 'description' => $args['description'],
            'default' => $args['default'],
            'auth_callback' => function() { return current_user_can( 'edit_posts' ); },
        ) );
    }
}
add_action( 'init', 'sphotography_register_photograph_meta' );

// 3b. 加载媒体库字段
require_once get_template_directory() . '/admin/media-fields.php';

// 3c. 上传图片时自动读取 EXIF GPS
function sphotography_read_exif_and_save( $attachment_id, $file_path ) {
    $result = array( 'gps' => false, 'camera' => false, 'date' => false, 'debug' => '' );

    if ( ! $file_path || ! file_exists( $file_path ) ) {
        $result['debug'] = 'File not found: ' . ( $file_path ?: 'empty path' );
        return $result;
    }

    // Method 1: PHP exif_read_data (requires PHP EXIF extension)
    if ( function_exists( 'exif_read_data' ) ) {
        $exif = @exif_read_data( $file_path, 0, true );
        if ( $exif ) {
            // Debug: log the actual raw GPS data keys
            $gps_raw_debug = '';
            if ( isset( $exif['GPS'] ) ) {
                $gps_keys = array_keys( $exif['GPS'] );
                $gps_raw_debug = 'GPS keys: ' . implode(',', $gps_keys) . ' ';
            }

            // Try multiple sources for GPS data
            $gps_source = null;

            // Source A: Standard GPS IFD
            if ( isset( $exif['GPS'] ) && is_array( $exif['GPS'] ) ) {
                $gps_source = $exif['GPS'];
                $result['debug'] .= 'Src=GPS ';
            }
            // Source B: Top-level GPS coordinates (some cameras)
            if ( $gps_source === null && isset( $exif['GPSLatitude'] ) ) {
                $gps_source = $exif;
                $result['debug'] .= 'Src=TOP ';
            }
            // Source C: In COMPUTED section
            if ( $gps_source === null && isset( $exif['COMPUTED']['GPSLatitude'] ) ) {
                $gps_source = $exif['COMPUTED'];
                $result['debug'] .= 'Src=COMPUTED ';
            }

            if ( $gps_source !== null ) {
                // Debug: include raw GPS data structure
                $gps_json = @json_encode( $gps_source, JSON_PRETTY_PRINT );
                $result['debug'] .= 'Raw: ' . ( $gps_json ?: 'json_encode failed' ) . ' ';

                $lat = sphotography_gps_to_decimal( $gps_source, 'GPSLatitude', 'GPSLatitudeRef' );
                $lng = sphotography_gps_to_decimal( $gps_source, 'GPSLongitude', 'GPSLongitudeRef' );

                if ( $lat === null ) {
                    // Try alternate key names (lowercase, mixed case)
                    foreach ( array_keys( $gps_source ) as $key ) {
                        if ( stripos( $key, 'lat' ) !== false && $lat === null ) {
                            $ref_key = str_replace( 'latitude', 'latituderef', $key );
                            $ref_key = str_replace( 'Latitude', 'LatitudeRef', $ref_key );
                            $ref_key = str_replace( 'latitude', 'LatitudeRef', ucfirst( $key ) );
                            $lat = sphotography_gps_to_decimal( $gps_source, $key, isset( $gps_source[ $ref_key ] ) ? $ref_key : 'GPSLatitudeRef' );
                        }
                        if ( stripos( $key, 'lng' ) !== false || stripos( $key, 'lon' ) !== false ) {
                            $ref_key2 = str_replace( array( 'longitude', 'Longitude' ), array( 'longituderef', 'LongitudeRef' ), $key );
                            $lng = sphotography_gps_to_decimal( $gps_source, $key, isset( $gps_source[ $ref_key2 ] ) ? $ref_key2 : 'GPSLongitudeRef' );
                        }
                    }
                }

                if ( $lat !== null && $lng !== null ) {
                    update_post_meta( $attachment_id, 'latitude', $lat );
                    update_post_meta( $attachment_id, 'longitude', $lng );
                    $result['gps'] = true;
                    $result['debug'] .= 'GPS OK ';
                } else {
                    $result['debug'] .= 'GPS parse failed ';
                }
            } else {
                $result['debug'] .= 'No GPS section in EXIF ';
            }

            // Camera model from IFD0
            $camera = '';
            if ( isset( $exif['IFD0']['Model'] ) ) {
                $camera = $exif['IFD0']['Model'];
            } elseif ( isset( $exif['IFD0']['Make'] ) ) {
                $camera = $exif['IFD0']['Make'];
            }
            if ( ! empty( $camera ) ) {
                update_post_meta( $attachment_id, 'camera_info', sanitize_text_field( $camera ) );
                $result['camera'] = true;
            }

            // Date and Time
            $date_str = '';
            if ( isset( $exif['EXIF']['DateTimeOriginal'] ) ) {
                $date_str = $exif['EXIF']['DateTimeOriginal'];
            } elseif ( isset( $exif['IFD0']['DateTime'] ) ) {
                $date_str = $exif['IFD0']['DateTime'];
            }
            if ( ! empty( $date_str ) ) {
                $ts = strtotime( $date_str );
                if ( $ts !== false ) {
                    update_post_meta( $attachment_id, 'taken_at', date( 'Y-m-d', $ts ) );
                    update_post_meta( $attachment_id, 'taken_time', date( 'H:i:s', $ts ) );
                    $result['date'] = true;
                }
            }

            // Aperture: FNumber or ApertureValue, formatted as f/N
            if ( isset( $exif['EXIF']['FNumber'] ) ) {
                $aperture_val = $exif['EXIF']['FNumber'];
            } elseif ( isset( $exif['EXIF']['ApertureValue'] ) ) {
                $aperture_val = $exif['EXIF']['ApertureValue'];
            } else {
                $aperture_val = null;
            }
            if ( $aperture_val !== null ) {
                $aperture_decimal = sphotography_parse_exif_rational( $aperture_val );
                if ( $aperture_decimal !== null ) {
                    $aperture_str = 'f/' . number_format( $aperture_decimal, 1 );
                    update_post_meta( $attachment_id, 'aperture', $aperture_str );
                }
            }

            // Shutter: ExposureTime, formatted as 1/N or Ns
            if ( isset( $exif['EXIF']['ExposureTime'] ) ) {
                $shutter_str = sphotography_format_shutter_speed( $exif['EXIF']['ExposureTime'] );
                if ( $shutter_str ) {
                    update_post_meta( $attachment_id, 'shutter', $shutter_str );
                }
            }

            // ISO: ISOSpeedRatings (may be array, take first)
            if ( isset( $exif['EXIF']['ISOSpeedRatings'] ) ) {
                $iso_val = $exif['EXIF']['ISOSpeedRatings'];
                if ( is_array( $iso_val ) && ! empty( $iso_val ) ) {
                    $iso_val = $iso_val[0];
                }
                $iso_int = (int) $iso_val;
                if ( $iso_int > 0 ) {
                    update_post_meta( $attachment_id, 'iso', (string) $iso_int );
                }
            }
        } else {
            $result['debug'] .= 'exif_read_data returned false ';
        }
    } else {
        $result['debug'] .= 'PHP EXIF extension not available ';
    }

    return $result;
}

// Main hook: fires when WordPress generates attachment metadata (file is guaranteed to exist)
function sphotography_auto_gps_on_metadata( $metadata, $attachment_id ) {
    if ( get_post_type( $attachment_id ) !== 'attachment' ) return $metadata;

    $existing_lat = get_post_meta( $attachment_id, 'latitude', true );
    $existing_lng = get_post_meta( $attachment_id, 'longitude', true );
    if ( ! empty( $existing_lat ) && ! empty( $existing_lng ) ) return $metadata;

    $file_path = get_attached_file( $attachment_id );
    $result = sphotography_read_exif_and_save( $attachment_id, $file_path );

    // Log debug info if WP_DEBUG is enabled
    if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( sprintf(
            'Sphotography EXIF: attachment=%d file=%s gps=%s camera=%s date=%s debug=%s',
            $attachment_id, basename( $file_path ),
            $result['gps'] ? 'YES' : 'no',
            $result['camera'] ? 'YES' : 'no',
            $result['date'] ? 'YES' : 'no',
            $result['debug']
        ) );
    }

    return $metadata;
}
add_filter( 'wp_generate_attachment_metadata', 'sphotography_auto_gps_on_metadata', 10, 2 );

// AJAX handler: manually trigger EXIF reading for an attachment
function sphotography_ajax_read_exif() {
    if ( ! current_user_can( 'upload_files' ) ) {
        wp_send_json_error( 'Permission denied' );
    }
    $attachment_id = intval( $_POST['attachment_id'] );
    if ( ! $attachment_id ) {
        wp_send_json_error( 'Invalid attachment ID' );
    }
    $file_path = get_attached_file( $attachment_id );
    $result = sphotography_read_exif_and_save( $attachment_id, $file_path );

    $lat = get_post_meta( $attachment_id, 'latitude', true );
    $lng = get_post_meta( $attachment_id, 'longitude', true );

    wp_send_json_success( array(
        'latitude'    => $lat ? floatval( $lat ) : '',
        'longitude'   => $lng ? floatval( $lng ) : '',
        'hasGps'      => $result['gps'],
        'hasCamera'   => $result['camera'],
        'hasDate'     => $result['date'],
        'debug'       => $result['debug'],
    ) );
}
add_action( 'wp_ajax_sphotography_read_exif', 'sphotography_ajax_read_exif' );

// ============================================
// v1.4.0 — Batch backfill of EXIF (aperture / shutter / ISO) for all
// existing image attachments. The per-image `sphotography_read_exif` AJAX
// handles single files; this new handler iterates every image attachment
// in paginated batches of 20, re-running the EXIF read for any photo that
// is missing at least one of the three fields. On the final batch it
// drops the `sphotography_wall_photos` transient so the next photo-wall
// load shows fresh data.
// ============================================
function sphotography_ajax_exif_backfill_batch() {
    check_ajax_referer( 'sphotography_exif_backfill', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => __( '权限不足。', 'sphotography' ) ) );
    }

    $offset = isset( $_POST['offset'] ) ? max( 0, (int) $_POST['offset'] ) : 0;
    $batch  = 20;

    // All image attachments. We do NOT pre-filter on missing meta because
    // a single attachment can have aperture but no shutter, and we want
    // to backfill every missing field.
    $ids = get_posts( array(
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'post_status'    => 'inherit',
        'fields'         => 'ids',
        'numberposts'    => -1,
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ) );
    $total = count( $ids );
    $slice = array_slice( $ids, $offset, $batch );

    $new_fields = 0;
    $skipped    = 0;
    foreach ( $slice as $id ) {
        $before = array(
            'aperture' => (string) get_post_meta( $id, 'aperture', true ),
            'shutter'  => (string) get_post_meta( $id, 'shutter', true ),
            'iso'      => (string) get_post_meta( $id, 'iso', true ),
        );
        // Skip the file if all three are already present.
        if ( $before['aperture'] !== '' && $before['shutter'] !== '' && $before['iso'] !== '' ) {
            $skipped++;
            continue;
        }
        $file_path = get_attached_file( $id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            $skipped++;
            continue;
        }
        // Run the EXIF read. Failures are swallowed inside
        // sphotography_read_exif_and_save (it logs a debug string); we
        // continue with the next attachment.
        sphotography_read_exif_and_save( $id, $file_path );
        $after = array(
            'aperture' => (string) get_post_meta( $id, 'aperture', true ),
            'shutter'  => (string) get_post_meta( $id, 'shutter', true ),
            'iso'      => (string) get_post_meta( $id, 'iso', true ),
        );
        foreach ( array( 'aperture', 'shutter', 'iso' ) as $k ) {
            if ( $before[ $k ] === '' && $after[ $k ] !== '' ) {
                $new_fields++;
            }
        }
    }

    $done = $offset + count( $slice );

    // On the last batch, drop the wall-photos cache so the next
    // request re-reads with fresh meta.
    if ( $done >= $total ) {
        delete_transient( 'sphotography_wall_photos' );
    }

    wp_send_json_success( array(
        'total'       => $total,
        'done'        => $done,
        'next_offset' => $done,
        'finished'    => $done >= $total,
        'new_fields'  => $new_fields,
        'skipped'     => $skipped,
    ) );
}
add_action( 'wp_ajax_sphotography_exif_backfill', 'sphotography_ajax_exif_backfill_batch' );

// Add "Read EXIF" button to media library fields
function sphotography_attachment_exif_button( $form_fields, $post ) {
    $form_fields['sphotography_read_exif'] = array(
        'label' => __( 'EXIF 操作', 'sphotography' ),
        'input' => 'html',
        'html'  => '<button type="button" class="button sphotography-read-exif-btn" data-id="' . esc_attr( $post->ID ) . '">'
                 . __( '从图片读取 GPS/EXIF', 'sphotography' ) . '</button>'
                 . '<span class="sphotography-exif-status" style="margin-left:8px;font-size:0.8rem;color:#666;"></span>'
                 . '<p class="description" style="margin-top:4px;">'
                 . __( '点击按钮从图片文件中重新读取 GPS 坐标、相机信息和拍摄日期。', 'sphotography' )
                 . ' <a href="#" class="sphotography-exif-debug-link" style="color:#999;">调试</a>'
                 . '<span class="sphotography-exif-debug" style="display:none;font-size:0.75rem;color:#999;"></span>'
                 . '</p>',
    );
    return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'sphotography_attachment_exif_button', 20, 2 );

// Enqueue admin JS for EXIF button (loads on ALL admin pages where media modal can appear)
function sphotography_enqueue_media_scripts() {
    // Load exif-js for client-side EXIF reading (bypasses PHP EXIF extension)
    wp_enqueue_script(
        'exif-js',
        'https://cdn.jsdelivr.net/npm/exif-js@2.3.0/dist/exif.min.js',
        array(),
        '2.3.0',
        true
    );

    wp_add_inline_script( 'exif-js', '
        jQuery(document).on("click", ".sphotography-read-exif-btn", function() {
            var btn = jQuery(this);
            var td = btn.closest("td");
            var statusEl = td.find(".sphotography-exif-status");
            var debugEl = td.find(".sphotography-exif-debug");
            var aid = btn.data("id");
            statusEl.text("读取中...");
            btn.prop("disabled", true);

            // Method 1: Try AJAX (PHP exif_read_data)
            jQuery.post(ajaxurl, {
                action: "sphotography_read_exif",
                attachment_id: aid
            }, function(res) {
                if (res.success) {
                    var d = res.data;
                    statusEl.html("GPS: " + (d.hasGps ? "✅ " + d.latitude + ", " + d.longitude : "❌")
                        + " | 相机: " + (d.hasCamera ? "✅" : "❌")
                        + " | 日期: " + (d.hasDate ? "✅" : "❌")
                        + (d.debug ? " <span style=\"color:#aaa;font-size:0.7rem;\">[" + d.debug + "]</span>" : ""));
                    // Auto-fill the form fields
                    td.closest("tr").siblings().find("input[name*=\'sphotography_latitude\']").val(d.latitude || "");
                    td.closest("tr").siblings().find("input[name*=\'sphotography_longitude\']").val(d.longitude || "");
                    debugEl.text(d.debug || "");
                } else {
                    statusEl.text("❌ " + (res.data || "读取失败"));
                }
                btn.prop("disabled", false);
            }).fail(function() {
                statusEl.text("❌ 请求失败");
                btn.prop("disabled", false);
            });
        });

        jQuery(document).on("click", ".sphotography-exif-debug-link", function(e) {
            e.preventDefault();
            jQuery(this).parent().find(".sphotography-exif-debug").toggle();
        });
    ' );
}
add_action( 'admin_enqueue_scripts', 'sphotography_enqueue_media_scripts' );

// Also load on the media modal (which can appear on any admin page)
add_action( 'wp_enqueue_media', 'sphotography_enqueue_media_scripts' );

function sphotography_gps_to_decimal( $gps, $coord_key, $ref_key ) {
    if ( ! isset( $gps[ $coord_key ] ) || ! isset( $gps[ $ref_key ] ) ) {
        return null;
    }

    $parts = $gps[ $coord_key ];
    if ( ! is_array( $parts ) ) {
        // Some cameras store a single float value directly
        $decimal = floatval( $parts );
    } elseif ( count( $parts ) === 1 ) {
        // Single degree value
        $decimal = sphotography_exif_frac_to_float( $parts[0] );
    } elseif ( count( $parts ) === 2 ) {
        // deg + min (no seconds)
        $degrees = sphotography_exif_frac_to_float( $parts[0] );
        $minutes = sphotography_exif_frac_to_float( $parts[1] );
        if ( $degrees === null || $minutes === null ) return null;
        $decimal = $degrees + ( $minutes / 60 );
    } elseif ( count( $parts ) === 3 ) {
        // deg + min + sec (standard)
        $degrees = sphotography_exif_frac_to_float( $parts[0] );
        $minutes = sphotography_exif_frac_to_float( $parts[1] );
        $seconds = sphotography_exif_frac_to_float( $parts[2] );
        if ( $degrees === null || $minutes === null || $seconds === null ) return null;
        $decimal = $degrees + ( $minutes / 60 ) + ( $seconds / 3600 );
    } else {
        return null;
    }

    if ( $decimal === null ) return null;

    $ref = $gps[ $ref_key ];
    // GPSLatitudeRef can be 'N' or 'S', GPSLongitudeRef can be 'E' or 'W'
    // Also handle string like "N28°13'40\""
    if ( is_string( $ref ) && ( $ref === 'S' || $ref === 'W' || strpos( $ref, 'S' ) !== false || strpos( $ref, 'W' ) !== false ) ) {
        $decimal = -abs( $decimal );
    } else {
        $decimal = abs( $decimal );
    }

    return round( $decimal, 6 );
}

function sphotography_exif_frac_to_float( $frac ) {
    // Case 1: array with [numerator, denominator] — e.g., [28, 1]
    if ( is_array( $frac ) && count( $frac ) >= 2 && isset( $frac[1] ) && $frac[1] != 0 ) {
        return $frac[0] / $frac[1];
    }
    // Case 2: float or int
    if ( is_float( $frac ) || is_int( $frac ) ) {
        return floatval( $frac );
    }
    // Case 3: string like "28/1" or "147506/10000"
    if ( is_string( $frac ) && strpos( $frac, '/' ) !== false ) {
        $parts = explode( '/', $frac );
        if ( count( $parts ) === 2 && is_numeric( $parts[0] ) && is_numeric( $parts[1] ) && $parts[1] != 0 ) {
            return floatval( $parts[0] ) / floatval( $parts[1] );
        }
    }
    // Case 4: numeric string like "28"
    if ( is_string( $frac ) && is_numeric( $frac ) ) {
        return floatval( $frac );
    }
    return null;
}

/**
 * Parse EXIF rational value to float (used for aperture, shutter, ISO).
 *
 * @param mixed $val EXIF value (array, string, or number)
 * @return float|null
 */
function sphotography_parse_exif_rational( $val ) {
    if ( is_array( $val ) && count( $val ) >= 2 && isset( $val[1] ) && $val[1] != 0 ) {
        return floatval( $val[0] ) / floatval( $val[1] );
    }
    if ( is_float( $val ) || is_int( $val ) ) {
        return floatval( $val );
    }
    if ( is_string( $val ) && strpos( $val, '/' ) !== false ) {
        $parts = explode( '/', $val );
        if ( count( $parts ) === 2 && is_numeric( $parts[0] ) && is_numeric( $parts[1] ) && $parts[1] != 0 ) {
            return floatval( $parts[0] ) / floatval( $parts[1] );
        }
    }
    if ( is_string( $val ) && is_numeric( $val ) ) {
        return floatval( $val );
    }
    return null;
}

/**
 * Format shutter speed EXIF value (ExposureTime) as string.
 *
 * @param mixed $val EXIF ExposureTime value
 * @return string|null e.g. "1/200" or "2s"
 */
function sphotography_format_shutter_speed( $val ) {
    $decimal = sphotography_parse_exif_rational( $val );
    if ( $decimal === null || $decimal <= 0 ) {
        return null;
    }
    // If >= 1 second, format as "Ns"
    if ( $decimal >= 1 ) {
        return round( $decimal ) . 's';
    }
    // If < 1 second, format as "1/N"
    $denominator = round( 1 / $decimal );
    return '1/' . $denominator;
}

// 4. 地图标记 REST 端点：sphotography/v1/photos
function sphotography_register_marker_route() {
    register_rest_route( 'sphotography/v1', '/photos', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'sphotography_get_photo_markers',
        'permission_callback' => '__return_true',
        'args'                => array(
            'region_tag' => array(
                'type'              => 'string',
                'required'          => false,
                'sanitize_callback' => 'sanitize_text_field',
            ),
        ),
    ) );
}
add_action( 'rest_api_init', 'sphotography_register_marker_route' );

// v1.4.7 (item 8): on-demand full-resolution image URL for one attachment.
// The inline marker payload no longer ships full_image (only the thumbnail);
// the detail view fetches the full URL here by attachment id when a photo is
// actually opened, keeping the initial page payload small.
function sphotography_register_photo_full_route() {
    register_rest_route( 'sphotography/v1', '/photo-full/(?P<id>\d+)', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'sphotography_get_photo_full',
        'permission_callback' => '__return_true',
        'args'                => array(
            'id' => array(
                'validate_callback' => function ( $param ) { return is_numeric( $param ); },
            ),
        ),
    ) );
}
add_action( 'rest_api_init', 'sphotography_register_photo_full_route' );

function sphotography_get_photo_full( $request ) {
    $id = (int) $request['id'];
    if ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) {
        return new WP_REST_Response( array( 'full' => '' ), 404 );
    }
    $src = wp_get_attachment_image_src( $id, 'full' );
    return new WP_REST_Response( array( 'full' => $src ? $src[0] : '' ), 200 );
}

/**
 * Collect the unique image attachment IDs used by a post.
 *
 * @param WP_Post $post
 * @return int[]
 */
function sphotography_collect_post_image_ids( $post ) {
    $ids = array();

    // Featured image.
    if ( has_post_thumbnail( $post ) ) {
        $ids[] = (int) get_post_thumbnail_id( $post );
    }

    // Images attached to the post (uploaded while editing it).
    $attached = get_posts( array(
        'post_parent'    => $post->ID,
        'post_type'      => 'attachment',
        'post_mime_type' => 'image',
        'numberposts'    => -1,
        'fields'         => 'ids',
    ) );
    if ( ! empty( $attached ) ) {
        $ids = array_merge( $ids, array_map( 'intval', $attached ) );
    }

    // Images inserted into the body — WordPress tags them with wp-image-<id>.
    if ( preg_match_all( '/wp-image-(\d+)/', (string) $post->post_content, $m ) ) {
        $ids = array_merge( $ids, array_map( 'intval', $m[1] ) );
    }

    return array_values( array_unique( array_filter( $ids ) ) );
}

/**
 * Build the flat list of geolocated image markers for all published posts.
 * Shared by the REST route and the inline-data path so both emit the same
 * shape.
 *
 * @param string $region_tag Optional comma-separated region_tag slugs to filter by.
 * @return array[]
 */
function sphotography_collect_all_markers( $region_tag = '' ) {
    $query_args = array(
        'post_type'      => 'post',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
    );

    if ( ! empty( $region_tag ) ) {
        $slugs = array_filter( array_map( 'sanitize_title', explode( ',', $region_tag ) ) );
        if ( ! empty( $slugs ) ) {
            $query_args['tax_query'] = array(
                array(
                    'taxonomy' => 'region_tag',
                    'field'    => 'slug',
                    'terms'    => $slugs,
                    'operator' => 'IN',
                ),
            );
        }
    }

    $posts   = get_posts( $query_args );
    $markers = array();

    foreach ( $posts as $post ) {
        // Region tags of the parent post, shared by all its markers.
        $tag_data = array();
        $terms    = wp_get_post_terms( $post->ID, 'region_tag' );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                $tag_data[] = array(
                    'id'    => $t->term_id,
                    'name'  => $t->name,
                    'slug'  => $t->slug,
                    'color' => sphotography_tag_color( $t ),
                );
            }
        }

        $post_title = get_the_title( $post );

        foreach ( sphotography_collect_post_image_ids( $post ) as $img_id ) {
            $lat = get_post_meta( $img_id, 'latitude', true );
            $lng = get_post_meta( $img_id, 'longitude', true );
            if ( $lat === '' || $lng === '' ) {
                continue;
            }
            $lat = (float) $lat;
            $lng = (float) $lng;
            if ( 0.0 === $lat && 0.0 === $lng ) {
                continue;
            }

            $medium = wp_get_attachment_image_src( $img_id, 'medium' );
            $full   = wp_get_attachment_image_src( $img_id, 'full' );
            $att    = get_post( $img_id );
            $caption = $att ? wp_strip_all_tags( $att->post_excerpt ? $att->post_excerpt : $att->post_content ) : '';

            $adcodes = sphotography_attachment_adcodes( $img_id );

            $markers[] = array(
                'id'          => $img_id,
                'post_id'     => (int) $post->ID,
                'post_title'  => $post_title,
                'title'       => ( $att && $att->post_title ) ? $att->post_title : $post_title,
                'latitude'    => $lat,
                'longitude'   => $lng,
                'prov_adcode' => $adcodes['prov'],
                'city_adcode' => $adcodes['city'],
                'thumbnail'   => $medium ? $medium[0] : '',
                'full_image'  => $full ? $full[0] : '',
                'camera_info' => (string) get_post_meta( $img_id, 'camera_info', true ),
                'taken_at'    => (string) get_post_meta( $img_id, 'taken_at', true ),
                'description' => $caption,
                'tags'        => $tag_data,
            );
        }
    }

    return $markers;
}

/**
 * REST callback wrapping the shared marker builder.
 *
 * @param WP_REST_Request $request
 * @return WP_REST_Response
 */
function sphotography_get_photo_markers( $request ) {
    $region_tag = $request ? (string) $request->get_param( 'region_tag' ) : '';
    return rest_ensure_response( sphotography_collect_all_markers( $region_tag ) );
}

// 7. 加载前台资源
function sphotography_get_cdn_urls() {
    $source = get_theme_mod( 'sphotography_cdn_source', 'jsdelivr' );
    $urls = array(
        'maplibre_js'   => '',
        'maplibre_css'  => '',
        'domain'        => '',
    );

    switch ( $source ) {
        case 'jsdelivr':
            $urls['maplibre_js']  = 'https://cdn.jsdelivr.net/npm/maplibre-gl@4/dist/maplibre-gl.js';
            $urls['maplibre_css'] = 'https://cdn.jsdelivr.net/npm/maplibre-gl@4/dist/maplibre-gl.css';
            $urls['domain']       = 'cdn.jsdelivr.net';
            break;
        case 'unpkg':
            $urls['maplibre_js']  = 'https://unpkg.com/maplibre-gl@4/dist/maplibre-gl.js';
            $urls['maplibre_css'] = 'https://unpkg.com/maplibre-gl@4/dist/maplibre-gl.css';
            $urls['domain']       = 'unpkg.com';
            break;
        case 'cdnjs':
            $urls['maplibre_js']  = 'https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.0.0/maplibre-gl.js';
            $urls['maplibre_css'] = 'https://cdnjs.cloudflare.com/ajax/libs/maplibre-gl/4.0.0/maplibre-gl.css';
            $urls['domain']       = 'cdnjs.cloudflare.com';
            break;
    }
    return $urls;
}

/**
 * v1.4.7 (item 4): is the current request the fullscreen map view?
 *
 * True for any page explicitly using the map template AND for the site front
 * page / blog home — because the homepage IS the map, forced regardless of the
 * reading settings so a fresh install needs zero manual setup. Both the map
 * template filter and the script/asset enqueue key off this single predicate,
 * so forcing the template also loads MapLibre + the app bundle + inline data.
 */
function sphotography_is_map_view() {
    // v1.4.x (fix): 单篇文章 URL（分享链接直达）也会渲染地图模板（index.php
    // 无条件回退到 template-map.php），因此这里必须同样视为地图视图，
    // 否则地图资源 / 内联数据 / 动态 CSS 全部缺失，页面只剩空壳。
    return is_page_template( 'template-map.php' ) || is_front_page() || is_home() || is_singular( 'post' );
}

/**
 * Force the fullscreen map template on the front page / blog home, whatever the
 * reading settings say. Other URLs (single posts, real pages, archives) are
 * untouched and render normally.
 */
function sphotography_force_map_template( $template ) {
    if ( ( is_front_page() || is_home() ) && ! is_page_template( 'template-map.php' ) ) {
        $map = locate_template( 'template-map.php' );
        if ( $map ) {
            return $map;
        }
    }
    return $template;
}
add_filter( 'template_include', 'sphotography_force_map_template', 99 );

function sphotography_enqueue_scripts() {
    if ( ! sphotography_is_map_view() ) {
        return;
    }

    $cdn = sphotography_get_cdn_urls();

    wp_enqueue_style(
        'maplibre-gl',
        $cdn['maplibre_css'],
        array(),
        '4.0.0'
    );

    wp_enqueue_style(
        'google-fonts',
        'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;700&display=swap',
        array(),
        null
    );

    wp_enqueue_style(
        'sphotography-style',
        get_template_directory_uri() . '/style.css',
        array( 'maplibre-gl' ),
        SPHOTOGRAPHY_VERSION
    );

    wp_enqueue_script(
        'maplibre-gl',
        $cdn['maplibre_js'],
        array(),
        '4.0.0',
        true
    );

    // Offline QR generator (qrcode-generator, MIT) — powers the WeChat share
    // QR code without any runtime network request.
    wp_enqueue_script(
        'sphotography-qrcode',
        get_template_directory_uri() . '/assets/js/qrcode.js',
        array(),
        '1.5.01',
        true
    );

    wp_enqueue_script(
        'sphotography-app',
        get_template_directory_uri() . '/assets/js/app.js',
        array( 'maplibre-gl', 'sphotography-qrcode' ),
        SPHOTOGRAPHY_VERSION,
        true
    );

    // Parse the 外站 links (name|url|tooltip, max 3) for the page-links bar.
    $sp_external_links = array();
    $sp_ext_raw = (string) sphotography_get_mod( 'external_links' );
    if ( '' !== trim( $sp_ext_raw ) ) {
        $sp_ext_lines = preg_split( '/\r\n|\r|\n/', $sp_ext_raw );
        foreach ( $sp_ext_lines as $sp_line ) {
            $sp_line = trim( $sp_line );
            if ( '' === $sp_line ) {
                continue;
            }
            $sp_parts = array_map( 'trim', explode( '|', $sp_line ) );
            $sp_url = isset( $sp_parts[1] ) ? esc_url_raw( $sp_parts[1] ) : '';
            if ( '' === $sp_url ) {
                continue;
            }
            $sp_external_links[] = array(
                'name' => $sp_parts[0] !== '' ? $sp_parts[0] : $sp_url,
                'url'  => $sp_url,
                'tip'  => isset( $sp_parts[2] ) ? $sp_parts[2] : '',
            );
            if ( count( $sp_external_links ) >= 3 ) {
                break;
            }
        }
    }

    $sp_current_user = wp_get_current_user();
    // v1.4.x (fix): 分享链接直接打开单篇文章时，服务端把当前文章 ID 传给前端，
    // app.js 初始化完成后据此自动打开文章面板。
    $sp_current_post_id = is_singular( 'post' ) ? (int) get_queried_object_id() : 0;
    wp_localize_script(
        'sphotography-app',
        'Sphotography',
        array(
            'restUrl'         => esc_url_raw( rest_url() ),
            'siteName'        => get_bloginfo( 'name' ),
            'restNonce'       => wp_create_nonce( 'wp_rest' ),
            'currentPostId'   => $sp_current_post_id,
            'currentPostLink' => $sp_current_post_id ? get_permalink( $sp_current_post_id ) : '',
            // v1.4.3: 语言切换控件仅在 AI 开启时显示（动态正文翻译依赖文本模型）。
            'aiEnabled'       => ( function_exists( 'sphotography_ai_is_enabled' ) && sphotography_ai_is_enabled() ),
            // v1.4.4: 翻译功能独立开关（AI 开启 + 翻译子开关）。语言切换控件据此显示。
            'translateEnabled' => ( function_exists( 'sphotography_i18n_translate_enabled' ) && sphotography_i18n_translate_enabled() ),
            // v1.4.4 (item 6): 公告页数据（enabled/autoOpen/hash/html）。
            'announcement'    => function_exists( 'sphotography_announcement_data' ) ? sphotography_announcement_data() : array( 'enabled' => false ),
            'loggedIn'        => is_user_logged_in(),
            'currentUserName' => is_user_logged_in() ? $sp_current_user->display_name : '',
            'currentUserEmail' => is_user_logged_in() ? $sp_current_user->user_email : '',
            'commentsClosedText' => __( '评论已关闭。', 'sphotography' ),
            'comments'        => function_exists( 'sphotography_comment_config' ) ? sphotography_comment_config() : array(),
            'externalLinks'   => $sp_external_links,
            'friendLinks'     => function_exists( 'sphotography_friend_links_config' ) ? sphotography_friend_links_config() : array(),
            'guestbook'       => function_exists( 'sphotography_guestbook_config' ) ? sphotography_guestbook_config() : array(),
            'photoWall'       => function_exists( 'sphotography_photo_wall_config' ) ? sphotography_photo_wall_config() : array(),
        )
    );
}
add_action( 'wp_enqueue_scripts', 'sphotography_enqueue_scripts' );

// 8. 主题激活：自动创建地图页面
function sphotography_theme_activation() {
    // Register taxonomies first so rewrite rules flush cleanly.
    sphotography_register_region_tag_taxonomy();

    // Check if the map page already exists
    $map_page = get_page_by_path( 'photography-map', OBJECT, 'page' );

    if ( ! $map_page ) {
        // Create the map page
        $map_page_id = wp_insert_post( array(
            'post_title'     => __( 'Photography Map', 'sphotography' ),
            'post_name'      => 'photography-map',
            'post_content'   => '<!-- Sphotography fullscreen map page -->',
            'post_status'    => 'publish',
            'post_type'      => 'page',
            'page_template'  => 'template-map.php',
            'comment_status' => 'closed',
            'ping_status'    => 'closed',
        ) );

        if ( ! is_wp_error( $map_page_id ) && $map_page_id > 0 ) {
            // Set it as the static front page
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $map_page_id );
        }
    } else {
        // Ensure the existing page uses the map template
        if ( get_page_template_slug( $map_page->ID ) !== 'template-map.php' ) {
            update_post_meta( $map_page->ID, '_wp_page_template', 'template-map.php' );
        }

        // Ensure it is set as front page
        if ( get_option( 'page_on_front' ) != $map_page->ID ) {
            update_option( 'show_on_front', 'page' );
            update_option( 'page_on_front', $map_page->ID );
        }
    }

    // v1.4.7 (item 3): region coloring is now the default marker mode, so kick off
    // a background download of the boundary data (never blocks activation).
    if ( function_exists( 'sphotography_geo_schedule_bg_download' ) ) {
        sphotography_geo_schedule_bg_download();
    }

    // v1.4.8: 记录站点运行起始时间（首次激活即固定，用于运行时长展示）。
    if ( function_exists( 'sphotography_site_stats_capture_install_time' ) ) {
        sphotography_site_stats_capture_install_time();
    }

    // Flush only after all types and the front page are in their final state.
    flush_rewrite_rules();
}
add_action( 'after_switch_theme', 'sphotography_theme_activation' );

// 9. 注册管理菜单
function sphotography_register_admin_menu() {
    $menu_title = __( '主题全局配置', 'sphotography' );

    // Add pending friend-links applications badge to menu title
    if ( function_exists( 'sphotography_get_friend_link_applications' ) ) {
        $apps = sphotography_get_friend_link_applications();
        $pending_count = count( $apps );
        if ( $pending_count > 0 ) {
            $menu_title .= ' <span class="awaiting-mod count-' . esc_attr( $pending_count ) . '"><span class="pending-count">' . number_format_i18n( $pending_count ) . '</span></span>';
        }
    }

    add_menu_page(
        __( '主题全局配置', 'sphotography' ),   // Page title
        $menu_title,                            // Menu title (with badge if pending)
        'manage_options',                       // Capability
        'sphotography-settings',                // Menu slug
        'sphotography_render_settings_page',    // Callback function (from theme-settings.php)
        'dashicons-admin-generic',              // Icon
        3                                       // Position (after Dashboard)
    );
}
add_action( 'admin_menu', 'sphotography_register_admin_menu' );

// Enqueue admin scripts for settings page
add_action( 'admin_enqueue_scripts', 'sphotography_admin_enqueue_settings' );

// ============================================
// 10. Hide the WordPress admin toolbar on the front-end
// ============================================
// The fullscreen map covers the viewport, so WP's admin toolbar (and the
// html{margin-top:32px} bump it injects) pushes the map down and obscures the
// top of the page for logged-in users.
//
// `show_admin_bar => false` is the documented switch, but it can be overridden:
// a plugin (or per-user "Show Toolbar" meta) may re-enable the bar at a later
// filter priority, in which case the plain one-liner silently loses. So we do
// three things, all front-end only — the wp-admin dashboard is never touched:
//   1. Register the filter at PHP_INT_MAX so it wins any priority race.
//   2. Force-hide the toolbar again on the `wp` hook, after all other plugins
//      have had their say but before rendering.
//   3. Emit a CSS safety net in <head> that neutralises the layout bump and
//      hides the node even if something still forces it into the DOM.
add_filter( 'show_admin_bar', '__return_false', PHP_INT_MAX );

function sphotography_force_hide_admin_bar() {
    if ( ! is_admin() ) {
        show_admin_bar( false );
    }
}
add_action( 'wp', 'sphotography_force_hide_admin_bar' );

function sphotography_hide_admin_bar_css() {
    if ( is_admin() ) {
        return;
    }
    echo "<style id=\"sphotography-hide-adminbar\">html{margin-top:0 !important}#wpadminbar{display:none !important}</style>\n";
}
add_action( 'wp_head', 'sphotography_hide_admin_bar_css', 100 );

// ============================================
// 11. AJAX: Update theme from GitHub branch
// ============================================
function sphotography_ajax_do_update() {
    if ( ! wp_verify_nonce( $_POST['nonce'], 'sphotography_update_nonce' ) ) {
        wp_send_json_error( 'Security check failed' );
    }
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( 'Permission denied' );
    }

    $branch = sanitize_text_field( $_POST['branch'] );
    if ( empty( $branch ) ) {
        wp_send_json_error( 'No branch specified' );
    }

    $theme_dir = get_template_directory();
    $zip_url   = 'https://github.com/ShirazuNagisa/sphotography/archive/refs/heads/' . $branch . '.zip';
    $tmp_zip   = wp_tempnam( 'sphotography-update' );

    // Download ZIP
    $response = wp_remote_get( $zip_url, array( 'timeout' => 120, 'stream' => true, 'filename' => $tmp_zip ) );

    if ( is_wp_error( $response ) ) {
        unlink( $tmp_zip );
        wp_send_json_error( 'Download failed: ' . $response->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $response );
    if ( $code !== 200 ) {
        unlink( $tmp_zip );
        wp_send_json_error( 'Download failed with HTTP code ' . $code );
    }

    // Unzip
    require_once ABSPATH . 'wp-admin/includes/file.php';
    WP_Filesystem();

    $unzip_dir = get_temp_dir() . 'sphotography-update-' . uniqid();
    $unzipped  = unzip_file( $tmp_zip, $unzip_dir );
    unlink( $tmp_zip );

    if ( is_wp_error( $unzipped ) ) {
        wp_send_json_error( 'Unzip failed: ' . $unzipped->get_error_message() );
    }

    // Find the extracted folder (contains branch name, e.g. sphotography-beta)
    $extracted = glob( $unzip_dir . '/sphotography-*' );
    if ( empty( $extracted ) || ! is_dir( $extracted[0] ) ) {
        // Cleanup
        sphotography_rrmdir( $unzip_dir );
        wp_send_json_error( 'Extracted folder not found' );
    }
    $src_dir = $extracted[0];

    // v1.4.5: theme source now lives in a nested `Sphotography/` folder inside the
    // repo (dev files kept at repo root so they aren't shipped). The GitHub archive
    // therefore contains sphotography-<branch>/Sphotography/style.css — copy from
    // that subfolder so only the theme files land in the theme dir. Fall back to the
    // archive root for older branches that pre-date the reorg.
    if ( is_dir( $src_dir . '/Sphotography' ) && file_exists( $src_dir . '/Sphotography/style.css' ) ) {
        $src_dir = $src_dir . '/Sphotography';
    }

    // Copy all files from src to theme directory, overwriting
    $copied = copy_dir( $src_dir, $theme_dir );

    // Cleanup temp
    sphotography_rrmdir( $unzip_dir );

    if ( is_wp_error( $copied ) ) {
        wp_send_json_error( 'Copy failed: ' . $copied->get_error_message() );
    }

    wp_send_json_success( 'Theme updated from branch: ' . $branch );
}
add_action( 'wp_ajax_sphotography_do_update', 'sphotography_ajax_do_update' );

// Recursive rmdir helper
function sphotography_rrmdir( $dir ) {
    if ( ! is_dir( $dir ) ) return;
    $items = scandir( $dir );
    foreach ( $items as $item ) {
        if ( $item === '.' || $item === '..' ) continue;
        $path = $dir . '/' . $item;
        if ( is_dir( $path ) ) {
            sphotography_rrmdir( $path );
        } else {
            unlink( $path );
        }
    }
    rmdir( $dir );
}
