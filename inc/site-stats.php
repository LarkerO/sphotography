<?php
/**
 * v1.4.8：站点统计（边栏展开页丰富统计面板用）。
 *
 * 提供：文章/标签/地块/图片总数、本日与累计站点访问人数、站点运行起始时间。
 * 访问人数由前端每浏览器每日一次的信标（localStorage 去重、不记录 IP）累加。
 * 饼状图（图片地区分布）由前端基于 state.allPhotos + SphotographyGeo 直接计算，
 * 此处不重复聚合。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SPHOTOGRAPHY_OPT_VISITS_TOTAL = 'sphotography_visits_total';
const SPHOTOGRAPHY_OPT_VISITS_TODAY = 'sphotography_visits_today'; // array{ date:string(Ymd), count:int }
const SPHOTOGRAPHY_OPT_INSTALL_TIME = 'sphotography_install_time';  // unix timestamp

/**
 * 站点运行起始时间（unix 时间戳）。
 * 首次调用时若未记录：已有文章则回填为最早已发布文章的时间，否则记为当前时间。
 */
function sphotography_site_install_time() {
	$ts = (int) get_option( SPHOTOGRAPHY_OPT_INSTALL_TIME, 0 );
	if ( $ts > 0 ) {
		return $ts;
	}
	// 回填：最早已发布文章的发布时间（GMT），更贴近老站点真实运行时长。
	$oldest = get_posts( array(
		'post_type'      => 'post',
		'post_status'    => 'publish',
		'numberposts'    => 1,
		'orderby'        => 'date',
		'order'          => 'ASC',
		'fields'         => 'ids',
	) );
	if ( ! empty( $oldest ) ) {
		$gmt = get_post_field( 'post_date_gmt', (int) $oldest[0] );
		$ts  = $gmt ? (int) ( strtotime( $gmt . ' UTC' ) ) : 0;
	}
	if ( $ts <= 0 ) {
		$ts = time();
	}
	update_option( SPHOTOGRAPHY_OPT_INSTALL_TIME, $ts, false );
	return $ts;
}

/**
 * 激活时记录运行起始时间（若尚未记录）。由 sphotography_theme_activation() 调用。
 */
function sphotography_site_stats_capture_install_time() {
	if ( (int) get_option( SPHOTOGRAPHY_OPT_INSTALL_TIME, 0 ) <= 0 ) {
		sphotography_site_install_time();
	}
}

/**
 * 本日访问人数（按站点当前日期分桶；跨日自动归零）。
 */
function sphotography_visits_today() {
	$bucket = get_option( SPHOTOGRAPHY_OPT_VISITS_TODAY, array() );
	$today  = date_i18n( 'Ymd' );
	if ( ! is_array( $bucket ) || ! isset( $bucket['date'] ) || $bucket['date'] !== $today ) {
		return 0;
	}
	return (int) $bucket['count'];
}

/**
 * 累计访问人数。
 */
function sphotography_visits_total() {
	return (int) get_option( SPHOTOGRAPHY_OPT_VISITS_TOTAL, 0 );
}

/**
 * 记一次访问（前端已按每浏览器每日一次去重）。累加累计值与本日值。
 */
function sphotography_record_visit() {
	$total = sphotography_visits_total() + 1;
	update_option( SPHOTOGRAPHY_OPT_VISITS_TOTAL, $total, false );

	$today  = date_i18n( 'Ymd' );
	$bucket = get_option( SPHOTOGRAPHY_OPT_VISITS_TODAY, array() );
	if ( ! is_array( $bucket ) || ! isset( $bucket['date'] ) || $bucket['date'] !== $today ) {
		$bucket = array( 'date' => $today, 'count' => 0 );
	}
	$bucket['count'] = (int) $bucket['count'] + 1;
	update_option( SPHOTOGRAPHY_OPT_VISITS_TODAY, $bucket, false );

	return array( 'today' => (int) $bucket['count'], 'total' => $total );
}

/**
 * 汇总统计数据。标签取 region_tag 分类法术语数（主题的标签系统）。
 *
 * @return array
 */
function sphotography_site_stats() {
	$posts_obj = wp_count_posts( 'post' );
	$posts     = $posts_obj && isset( $posts_obj->publish ) ? (int) $posts_obj->publish : 0;

	$tags = (int) wp_count_terms( array( 'taxonomy' => 'region_tag', 'hide_empty' => false ) );
	if ( is_wp_error( $tags ) || $tags < 0 ) {
		$tags = 0;
	}

	$regions = function_exists( 'sphotography_lit_region_count' ) ? (int) sphotography_lit_region_count() : 0;

	$photos = 0;
	if ( function_exists( 'sphotography_collect_article_photos' ) ) {
		$photo_list = sphotography_collect_article_photos();
		$photos     = is_array( $photo_list ) ? count( $photo_list ) : 0;
	}

	return array(
		'posts'        => $posts,
		'tags'         => $tags,
		'regions'      => $regions,
		'photos'       => $photos,
		'visitsToday'  => sphotography_visits_today(),
		'visitsTotal'  => sphotography_visits_total(),
		'installTime'  => sphotography_site_install_time(), // unix 秒
		'serverTime'   => time(),                            // 用于前端实时跳动对齐
	);
}

// REST：GET /sphotography/v1/stats（统计汇总）与 POST /sphotography/v1/visit（记一次访问）
function sphotography_site_stats_register_routes() {
	register_rest_route( 'sphotography/v1', '/stats', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => function () {
			return rest_ensure_response( sphotography_site_stats() );
		},
		'permission_callback' => '__return_true',
	) );

	register_rest_route( 'sphotography/v1', '/visit', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => function () {
			$res = sphotography_record_visit();
			return rest_ensure_response( array(
				'counted' => true,
				'today'   => $res['today'],
				'total'   => $res['total'],
			) );
		},
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'sphotography_site_stats_register_routes' );
