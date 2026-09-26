<?php
// 撰写地点 meta box（读取浏览器定位 → 离线地理引擎解析 → 存储行政区域名）

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Post meta keys (underscore-prefixed → hidden from the custom-fields UI).
const SPHOTOGRAPHY_WLOC_ENABLED = '_sp_wloc_enabled';
const SPHOTOGRAPHY_WLOC_LAT     = '_sp_wloc_lat';
const SPHOTOGRAPHY_WLOC_LNG     = '_sp_wloc_lng';
const SPHOTOGRAPHY_WLOC_REGION  = '_sp_wloc_region';

// 坐标 → 区域名
// 坐标 → 可读区域标签（国内到市、国外到省/州）
function sphotography_wloc_resolve_label( $lat, $lng ) {
	if ( ! function_exists( 'sphotography_geo_resolve' ) ) {
		return '';
	}
	$lat = (float) $lat;
	$lng = (float) $lng;
	if ( 0.0 === $lat && 0.0 === $lng ) {
		return '';
	}
	$r    = sphotography_geo_resolve( $lat, $lng );
	$prov = ( isset( $r['prov']['name'] ) ) ? (string) $r['prov']['name'] : '';
	$city = ( isset( $r['city']['name'] ) ) ? (string) $r['city']['name'] : '';
	if ( '' !== $city ) {
		return $city;
	}
	return $prov;
}

// 注册 meta box
function sphotography_wloc_register_meta_box() {
	add_meta_box(
		'sphotography-write-location',
		__( '撰写地点', 'sphotography' ),
		'sphotography_wloc_render_meta_box',
		'post',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'sphotography_wloc_register_meta_box' );

function sphotography_wloc_render_meta_box( $post ) {
	$enabled = (bool) get_post_meta( $post->ID, SPHOTOGRAPHY_WLOC_ENABLED, true );
	$lat     = (string) get_post_meta( $post->ID, SPHOTOGRAPHY_WLOC_LAT, true );
	$lng     = (string) get_post_meta( $post->ID, SPHOTOGRAPHY_WLOC_LNG, true );
	$region  = (string) get_post_meta( $post->ID, SPHOTOGRAPHY_WLOC_REGION, true );
	wp_nonce_field( 'sphotography_wloc_save', 'sphotography_wloc_nonce' );
	?>
	<div class="sphotography-wloc-box" id="sphotography-wloc-box">
		<label class="sphotography-wloc-toggle">
			<input type="checkbox" name="sphotography_wloc_enabled" id="sphotography-wloc-enabled" value="1" <?php checked( $enabled, true ); ?>>
			<?php esc_html_e( '标注本文撰写地点', 'sphotography' ); ?>
		</label>
		<p class="sphotography-wloc-hint"><?php esc_html_e( '开启后，前台文章会显示由浏览器定位解析出的地区（国内到市、国外到省/州）。定位需 HTTPS 与你的授权。', 'sphotography' ); ?></p>
		<div class="sphotography-wloc-controls">
			<button type="button" class="button" id="sphotography-wloc-locate"><?php esc_html_e( '获取当前位置', 'sphotography' ); ?></button>
			<span class="sphotography-wloc-status" id="sphotography-wloc-status"></span>
		</div>
		<p class="sphotography-wloc-current">
			<?php esc_html_e( '当前地点：', 'sphotography' ); ?>
			<strong id="sphotography-wloc-region-label"><?php echo $region ? esc_html( $region ) : esc_html__( '（未设置）', 'sphotography' ); ?></strong>
		</p>
		<input type="hidden" name="sphotography_wloc_lat" id="sphotography-wloc-lat" value="<?php echo esc_attr( $lat ); ?>">
		<input type="hidden" name="sphotography_wloc_lng" id="sphotography-wloc-lng" value="<?php echo esc_attr( $lng ); ?>">
		<input type="hidden" name="sphotography_wloc_region" id="sphotography-wloc-region" value="<?php echo esc_attr( $region ); ?>">
	</div>
	<?php
}

// 加载 meta box JS
function sphotography_wloc_enqueue( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_script(
		'sphotography-wloc',
		get_template_directory_uri() . '/assets/js/write-location.js',
		array( 'jquery' ),
		SPHOTOGRAPHY_VERSION,
		true
	);
	wp_localize_script( 'sphotography-wloc', 'SphotographyWloc', array(
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
		'nonce'   => wp_create_nonce( 'sphotography_wloc_resolve' ),
		'i18n'    => array(
			'locating'    => __( '定位中…', 'sphotography' ),
			'resolving'   => __( '解析地区中…', 'sphotography' ),
			'unsupported' => __( '此浏览器不支持定位。', 'sphotography' ),
			'denied'      => __( '定位被拒绝或失败。', 'sphotography' ),
			'unresolved'  => __( '无法解析该坐标的地区（可能超出边界数据范围）。', 'sphotography' ),
			'done'        => __( '已更新。', 'sphotography' ),
			'notSet'      => __( '（未设置）', 'sphotography' ),
		),
	) );
}
add_action( 'admin_enqueue_scripts', 'sphotography_wloc_enqueue' );

// AJAX：坐标反查
function sphotography_wloc_ajax_resolve() {
	check_ajax_referer( 'sphotography_wloc_resolve', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( array( 'message' => __( '权限不足。', 'sphotography' ) ) );
	}
	$lat = isset( $_POST['lat'] ) ? (float) $_POST['lat'] : 0.0;
	$lng = isset( $_POST['lng'] ) ? (float) $_POST['lng'] : 0.0;

	// Make sure the boundary data is on disk (downloaded on demand, admin only).
	if ( function_exists( 'sphotography_geo_ensure_files' ) ) {
		$ready = sphotography_geo_ensure_files();
		if ( is_wp_error( $ready ) ) {
			wp_send_json_error( array( 'message' => $ready->get_error_message() ) );
		}
	}

	$region = sphotography_wloc_resolve_label( $lat, $lng );
	if ( '' === $region ) {
		wp_send_json_error( array( 'message' => __( '无法解析该坐标的地区。', 'sphotography' ) ) );
	}
	wp_send_json_success( array( 'region' => $region ) );
}
add_action( 'wp_ajax_sphotography_wloc_resolve', 'sphotography_wloc_ajax_resolve' );

// 保存
function sphotography_wloc_save( $post_id ) {
	if ( ! isset( $_POST['sphotography_wloc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sphotography_wloc_nonce'] ) ), 'sphotography_wloc_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$enabled = ! empty( $_POST['sphotography_wloc_enabled'] );
	update_post_meta( $post_id, SPHOTOGRAPHY_WLOC_ENABLED, $enabled ? 1 : 0 );

	$lat = isset( $_POST['sphotography_wloc_lat'] ) ? (float) $_POST['sphotography_wloc_lat'] : 0.0;
	$lng = isset( $_POST['sphotography_wloc_lng'] ) ? (float) $_POST['sphotography_wloc_lng'] : 0.0;

	if ( 0.0 === $lat && 0.0 === $lng ) {
		delete_post_meta( $post_id, SPHOTOGRAPHY_WLOC_LAT );
		delete_post_meta( $post_id, SPHOTOGRAPHY_WLOC_LNG );
		delete_post_meta( $post_id, SPHOTOGRAPHY_WLOC_REGION );
		return;
	}

	update_post_meta( $post_id, SPHOTOGRAPHY_WLOC_LAT, $lat );
	update_post_meta( $post_id, SPHOTOGRAPHY_WLOC_LNG, $lng );

	// Re-resolve server-side from the authoritative coordinates so the stored
	// region can never drift from what the coordinates actually map to. Fall
	// back to the client-submitted label if the boundary data is missing.
	if ( function_exists( 'sphotography_geo_ensure_files' ) ) {
		sphotography_geo_ensure_files();
	}
	$region = sphotography_wloc_resolve_label( $lat, $lng );
	if ( '' === $region && isset( $_POST['sphotography_wloc_region'] ) ) {
		$region = sanitize_text_field( wp_unslash( $_POST['sphotography_wloc_region'] ) );
	}
	if ( '' !== $region ) {
		update_post_meta( $post_id, SPHOTOGRAPHY_WLOC_REGION, $region );
	} else {
		delete_post_meta( $post_id, SPHOTOGRAPHY_WLOC_REGION );
	}
}
add_action( 'save_post_post', 'sphotography_wloc_save' );

// ============================================
// Frontend exposure
// ============================================
/**
 * The display region for a post, or '' when the feature is off for it.
 *
 * @param int $post_id
 * @return string
 */
function sphotography_wloc_get( $post_id ) {
	if ( ! get_post_meta( $post_id, SPHOTOGRAPHY_WLOC_ENABLED, true ) ) {
		return '';
	}
	return (string) get_post_meta( $post_id, SPHOTOGRAPHY_WLOC_REGION, true );
}

// Expose as a REST field so the article panel (which fetches the post via REST)
// can read it.
function sphotography_wloc_register_rest_field() {
	register_rest_field( 'post', 'sp_write_location', array(
		'get_callback' => function ( $arr ) {
			return sphotography_wloc_get( (int) $arr['id'] );
		},
		'schema'       => array(
			'description' => 'Sphotography article writing location label.',
			'type'        => 'string',
		),
	) );
}
add_action( 'rest_api_init', 'sphotography_wloc_register_rest_field' );
