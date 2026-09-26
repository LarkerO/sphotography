<?php
// 照片墙后端：收集已发布文章的图片及元数据（EXIF、地理坐标），支持置顶和按日期排序

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 收集所有照片并按置顶+日期排序，结果缓存在 transient 中
function sphotography_collect_article_photos() {
	$transient_key = 'sphotography_wall_photos';
	$cached = get_transient( $transient_key );
	if ( is_array( $cached ) ) {
		return $cached;
	}

	$photos = array();
	$attachment_to_post = array(); // attachment_id => first post_id

	// Query published posts
	$posts = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'numberposts'    => -1,
		'fields'         => 'ids',
	) );

	if ( ! empty( $posts ) ) {
		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) continue;

			// Scan post_content for wp-image-<id> class
			$matches = array();
			if ( preg_match_all( '/wp-image-(\d+)/', $post->post_content, $matches ) ) {
				foreach ( $matches[1] as $attach_id ) {
					$attach_id = (int) $attach_id;
					if ( ! isset( $attachment_to_post[ $attach_id ] ) ) {
						$attachment_to_post[ $attach_id ] = $post_id;
					}
				}
			}
		}
	}

	// Build photo objects
	foreach ( $attachment_to_post as $attach_id => $post_id ) {
		$thumb = wp_get_attachment_image_url( $attach_id, 'medium' );
		$full = wp_get_attachment_image_url( $attach_id, 'large' );
		$attachment = get_post( $attach_id );
		if ( ! $attachment ) continue;

		$post = get_post( $post_id );
		$post_title = $post ? $post->post_title : '';

		$date = (string) get_post_meta( $attach_id, 'taken_at', true );
		$time = (string) get_post_meta( $attach_id, 'taken_time', true );
		$lat_val = get_post_meta( $attach_id, 'latitude', true );
		$lng_val = get_post_meta( $attach_id, 'longitude', true );
		$camera = (string) get_post_meta( $attach_id, 'camera_info', true );
		$aperture = (string) get_post_meta( $attach_id, 'aperture', true );
		$shutter = (string) get_post_meta( $attach_id, 'shutter', true );
		$iso = (string) get_post_meta( $attach_id, 'iso', true );
		$pinned = get_post_meta( $attach_id, 'wall_pinned', true ) === '1';

		$lat = $lat_val ? (float) $lat_val : '';
		$lng = $lng_val ? (float) $lng_val : '';

		$photos[] = array(
			'id'        => $attach_id,
			'thumbnail' => $thumb ?: '',
			'full'      => $full ?: '',
			'title'     => $attachment->post_title ?: '',
			'postId'    => $post_id,
			'postTitle' => $post_title,
			'date'      => $date,
			'time'      => $time,
			'lat'       => $lat,
			'lng'       => $lng,
			'camera'    => $camera,
			'aperture'  => $aperture,
			'shutter'   => $shutter,
			'iso'       => $iso,
			'pinned'    => $pinned,
		);
	}

	// Order: pinned first, then by date DESC (items with date first, newest), then no-date items
	usort( $photos, function( $a, $b ) {
		// Pinned items come first
		if ( $a['pinned'] && ! $b['pinned'] ) return -1;
		if ( ! $a['pinned'] && $b['pinned'] ) return 1;

		// Within pinned, or within non-pinned, sort by date DESC
		$a_date = $a['date'];
		$b_date = $b['date'];

		// Items with dates first, sorted DESC
		if ( ! empty( $a_date ) && ! empty( $b_date ) ) {
			$cmp = strcmp( $b_date, $a_date ); // DESC
			if ( $cmp !== 0 ) return $cmp;
			// Same day: by attachment id DESC for stable order
			return $b['id'] <=> $a['id'];
		}

		// Items with dates come before no-date items
		if ( ! empty( $a_date ) && empty( $b_date ) ) return -1;
		if ( empty( $a_date ) && ! empty( $b_date ) ) return 1;

		// Both have no date: by id DESC
		return $b['id'] <=> $a['id'];
	} );

	// Attach group key to each photo
	$result = array();
	foreach ( $photos as $photo ) {
		if ( $photo['pinned'] ) {
			$photo['group'] = 'pinned';
		} elseif ( ! empty( $photo['date'] ) ) {
			$photo['group'] = $photo['date'];
		} else {
			$photo['group'] = 'unknown';
		}
		$result[] = $photo;
	}

	// Cache for 1 hour
	set_transient( $transient_key, $result, HOUR_IN_SECONDS );

	return $result;
}

// REST 回调：GET /sphotography/v1/wall-photos
function sphotography_get_wall_photos( $request ) {
	$page = (int) $request->get_param( 'page' ) ?: 1;
	$per_page = (int) $request->get_param( 'per_page' ) ?: 30;

	// Clamp per_page to max 60
	$per_page = min( $per_page, 60 );
	if ( $per_page < 1 ) $per_page = 30;
	if ( $page < 1 ) $page = 1;

	$photos = sphotography_collect_article_photos();
	$total = count( $photos );

	// Slice for this page
	$offset = ( $page - 1 ) * $per_page;
	$items = array_slice( $photos, $offset, $per_page );

	return new WP_REST_Response( array(
		'items'    => $items,
		'page'     => $page,
		'per_page' => $per_page,
		'total'    => $total,
		'has_more' => ( $offset + $per_page ) < $total,
	) );
}

// 前台配置
function sphotography_photo_wall_config() {
	return array(
		'perPage' => 30,
	);
}

// 注册 REST 路由
function sphotography_register_photo_wall_route() {
	register_rest_route( 'sphotography/v1', '/wall-photos', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'sphotography_get_wall_photos',
		'permission_callback' => '__return_true',
		'args'                => array(
			'page' => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => 1,
				'sanitize_callback' => 'absint',
			),
			'per_page' => array(
				'type'              => 'integer',
				'required'          => false,
				'default'           => 30,
				'sanitize_callback' => 'absint',
			),
		),
	) );
}
add_action( 'rest_api_init', 'sphotography_register_photo_wall_route' );
