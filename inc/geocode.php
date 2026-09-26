<?php
// 服务端逆地理编码代理（Nominatim / LocationIQ）
// GET sphotography/v1/reverse-geocode?lat=&lng=&lang=
// 返回 { name: '<display name>'|'', lat, lng }

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SPHOTOGRAPHY_GEOCODE_CACHE_TTL' ) ) {
	// 地名相对稳定，缓存一个月。坐标不同即新 key。
	define( 'SPHOTOGRAPHY_GEOCODE_CACHE_TTL', MONTH_IN_SECONDS );
}
if ( ! defined( 'SPHOTOGRAPHY_GEOCODE_DEFAULT_ENDPOINT' ) ) {
	define( 'SPHOTOGRAPHY_GEOCODE_DEFAULT_ENDPOINT', 'https://nominatim.openstreetmap.org/reverse' );
}

// 后台配置的逆地理编码端点（空则用 Nominatim 默认）
function sphotography_geocode_endpoint() {
	$ep = trim( (string) sphotography_get_mod( 'reverse_geocode_endpoint' ) );
	return '' !== $ep ? $ep : SPHOTOGRAPHY_GEOCODE_DEFAULT_ENDPOINT;
}

// 可选 API key（LocationIQ 等需要）
function sphotography_geocode_api_key() {
	return trim( (string) sphotography_get_mod( 'reverse_geocode_key' ) );
}

// 目标语言 → accept-language 值
function sphotography_geocode_accept_language( $lang ) {
	switch ( $lang ) {
		case 'en':
			return 'en';
		case 'ja':
			return 'ja';
		default:
			return 'zh-CN,zh';
	}
}

function sphotography_geocode_register_routes() {
	register_rest_route( 'sphotography/v1', '/reverse-geocode', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'sphotography_geocode_rest_reverse',
		'permission_callback' => '__return_true', // 公开读；按坐标缓存 + 取整约束请求量
		'args'                => array(
			'lat'  => array( 'required' => true ),
			'lng'  => array( 'required' => true ),
			'lang' => array( 'required' => false ),
		),
	) );
}
add_action( 'rest_api_init', 'sphotography_geocode_register_routes' );

// ---------------------------------------------------------------------------
// v1.4.6 (item 1): 持久化缓存层 + 统一解析器 + 保存时预生成。
// transient 可能被对象缓存驱逐或到期；持久层（autoload=no 的 option）永不过期，
// 保证地图上的照片在展示时永不再触发上游逆地理编码调用。两层 key 一致。
// ---------------------------------------------------------------------------

// 坐标缓存 key 的公共后缀（小数点后 5 位 ≈ 1m）
function sphotography_geocode_coord_hash( $lat, $lng ) {
	return md5( number_format( (float) $lat, 5, '.', '' ) . ',' . number_format( (float) $lng, 5, '.', '' ) );
}
function sphotography_geocode_transient_key( $lang, $lat, $lng ) {
	return 'sp_geo_' . $lang . '_' . sphotography_geocode_coord_hash( $lat, $lng );
}
// 持久层 option 名（≤ 44 字符，远低于 wp_options 191 上限）
function sphotography_geocode_persist_key( $lang, $lat, $lng ) {
	return 'sp_geo_p_' . $lang . '_' . sphotography_geocode_coord_hash( $lat, $lng );
}
function sphotography_geocode_get_persistent( $lang, $lat, $lng ) {
	$v = get_option( sphotography_geocode_persist_key( $lang, $lat, $lng ), false );
	return ( false === $v ) ? false : (string) $v;
}
function sphotography_geocode_set_persistent( $lang, $lat, $lng, $name ) {
	// autoload 'no'：仅按需读取，绝不拖慢每次页面加载。
	update_option( sphotography_geocode_persist_key( $lang, $lat, $lng ), (string) $name, 'no' );
}

// 坐标合法性（(0,0) 视为无效）
function sphotography_geocode_valid_coord( $lat, $lng ) {
	$lat = (float) $lat; $lng = (float) $lng;
	if ( $lat < -90 || $lat > 90 || $lng < -180 || $lng > 180 ) return false;
	if ( 0.0 === $lat && 0.0 === $lng ) return false;
	return true;
}

/**
 * 统一解析器：坐标 + 语言 → 地名。查找顺序 transient → 持久层 → 上游。
 * 上游成功后同时写入两层。$allow_remote=false 时只读缓存（不触发网络）。
 * 失败返回 ''。
 */
function sphotography_geocode_resolve( $lat, $lng, $lang, $allow_remote = true ) {
	if ( ! in_array( $lang, array( 'zh', 'en', 'ja' ), true ) ) {
		$lang = 'zh';
	}
	if ( ! sphotography_geocode_valid_coord( $lat, $lng ) ) {
		return '';
	}
	$lat = (float) $lat; $lng = (float) $lng;

	$tkey = sphotography_geocode_transient_key( $lang, $lat, $lng );
	$cached = get_transient( $tkey );
	if ( false !== $cached ) {
		return (string) $cached;
	}

	$persist = sphotography_geocode_get_persistent( $lang, $lat, $lng );
	if ( false !== $persist ) {
		// 回填 transient，供后续快速命中。
		set_transient( $tkey, $persist, SPHOTOGRAPHY_GEOCODE_CACHE_TTL );
		return $persist;
	}

	if ( ! $allow_remote ) {
		return '';
	}

	$name = sphotography_geocode_lookup( $lat, $lng, $lang );
	if ( is_wp_error( $name ) ) {
		// 上游失败不缓存，前端会退化为只显示经纬度。
		return '';
	}
	set_transient( $tkey, $name, SPHOTOGRAPHY_GEOCODE_CACHE_TTL );
	sphotography_geocode_set_persistent( $lang, $lat, $lng, $name );
	return $name;
}

// REST 回调：坐标 → 详细地名（惰性回退：未命中时实时调用并写入持久层）
function sphotography_geocode_rest_reverse( WP_REST_Request $request ) {
	$lat = (float) $request->get_param( 'lat' );
	$lng = (float) $request->get_param( 'lng' );
	$lang = (string) $request->get_param( 'lang' );
	if ( ! in_array( $lang, array( 'zh', 'en', 'ja' ), true ) ) {
		$lang = 'zh';
	}
	if ( ! sphotography_geocode_valid_coord( $lat, $lng ) ) {
		return rest_ensure_response( array( 'name' => '', 'lat' => $lat, 'lng' => $lng ) );
	}
	$name = sphotography_geocode_resolve( $lat, $lng, $lang, true );
	return rest_ensure_response( array( 'name' => (string) $name, 'lat' => $lat, 'lng' => $lng ) );
}

// 请求上游逆地理编码服务
function sphotography_geocode_lookup( $lat, $lng, $lang ) {
	$endpoint = sphotography_geocode_endpoint();
	$args_url = array(
		'format'          => 'jsonv2',
		'lat'             => number_format( $lat, 7, '.', '' ),
		'lon'             => number_format( $lng, 7, '.', '' ),
		'accept-language' => sphotography_geocode_accept_language( $lang ),
		'zoom'            => 18,
		'addressdetails'  => 1,
	);
	$api_key = sphotography_geocode_api_key();
	if ( '' !== $api_key ) {
		// LocationIQ 等兼容端点用 `key` 参数鉴权。
		$args_url['key'] = $api_key;
	}
	$url = $endpoint . '?' . http_build_query( $args_url );

	$response = wp_remote_get( $url, array(
		'timeout'    => 15,
		'user-agent' => 'Sphotography-Theme/' . ( defined( 'SPHOTOGRAPHY_VERSION' ) ? SPHOTOGRAPHY_VERSION : '1.0' ) . ' (' . home_url( '/' ) . ')',
		'headers'    => array( 'Accept' => 'application/json' ),
	) );

	if ( is_wp_error( $response ) ) {
		return $response;
	}
	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( $code < 200 || $code >= 300 ) {
		return new WP_Error( 'geocode_http', 'Reverse geocode HTTP ' . $code );
	}
	$data = json_decode( (string) wp_remote_retrieve_body( $response ), true );
	if ( ! is_array( $data ) ) {
		return new WP_Error( 'geocode_parse', 'Reverse geocode: bad response' );
	}

	// 优先 display_name（最详细），否则用 name，否则由 address 组装。
	if ( ! empty( $data['display_name'] ) ) {
		return sphotography_geocode_trim_name( (string) $data['display_name'] );
	}
	if ( ! empty( $data['name'] ) ) {
		return sphotography_geocode_trim_name( (string) $data['name'] );
	}
	if ( ! empty( $data['address'] ) && is_array( $data['address'] ) ) {
		$parts = array();
		foreach ( array( 'road', 'neighbourhood', 'suburb', 'city_district', 'city', 'town', 'village', 'county', 'state', 'country' ) as $f ) {
			if ( ! empty( $data['address'][ $f ] ) ) {
				$parts[] = (string) $data['address'][ $f ];
			}
		}
		if ( ! empty( $parts ) ) {
			return sphotography_geocode_trim_name( implode( ', ', array_slice( $parts, 0, 6 ) ) );
		}
	}
	return new WP_Error( 'geocode_empty', 'Reverse geocode: no name' );
}

// 归一化地名
function sphotography_geocode_trim_name( $name ) {
	$name = trim( preg_replace( '/\s+/u', ' ', (string) $name ) );
	if ( mb_strlen( $name ) > 160 ) {
		$name = rtrim( mb_substr( $name, 0, 160 ) ) . '…';
	}
	return $name;
}

// ---------------------------------------------------------------------------
// v1.4.6 (item 1): 保存时预生成 + 一键回填。逆地理编码要走网络且 Nominatim 限速
// (≤1 req/s)，所以不在保存请求里同步跑，而是排一个后台单次 cron（沿用 i18n 的模式），
// 在 cron 请求里按语言逐个解析并写入持久层。
// ---------------------------------------------------------------------------

if ( ! defined( 'SPHOTOGRAPHY_GEOCODE_HOOK' ) ) {
	define( 'SPHOTOGRAPHY_GEOCODE_HOOK', 'sphotography_geocode_pregen_post_event' );
}

// 需要预生成的语言集合 = 中文（源语言）+ 已启用的翻译目标语言（en/ja）。
function sphotography_geocode_enabled_langs() {
	$langs = array( 'zh' );
	if ( function_exists( 'sphotography_i18n_target_langs' ) ) {
		foreach ( array_keys( sphotography_i18n_target_langs() ) as $l ) {
			if ( in_array( $l, array( 'en', 'ja' ), true ) && ! in_array( $l, $langs, true ) ) {
				$langs[] = $l;
			}
		}
	}
	return $langs;
}

// 收集某文章正文里引用的、带经纬度的照片坐标：[ [lat,lng], ... ]（去重）。
function sphotography_geocode_post_photo_coords( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return array();
	}
	$coords = array();
	$seen = array();
	if ( preg_match_all( '/wp-image-(\d+)/', (string) $post->post_content, $m ) ) {
		foreach ( $m[1] as $aid ) {
			$aid = (int) $aid;
			if ( isset( $seen[ $aid ] ) ) {
				continue;
			}
			$seen[ $aid ] = true;
			$lat = get_post_meta( $aid, 'latitude', true );
			$lng = get_post_meta( $aid, 'longitude', true );
			if ( '' === $lat || null === $lat || '' === $lng || null === $lng ) {
				continue;
			}
			if ( ! sphotography_geocode_valid_coord( $lat, $lng ) ) {
				continue;
			}
			$coords[] = array( (float) $lat, (float) $lng );
		}
	}
	return $coords;
}

// 保存文章时排一个后台预生成任务（发布态、非修订/自动保存、避免重复排期）。
function sphotography_geocode_schedule_post( $post_id, $post = null, $update = null ) {
	$post_id = (int) $post_id;
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( 'publish' !== get_post_status( $post_id ) ) {
		return;
	}
	if ( wp_next_scheduled( SPHOTOGRAPHY_GEOCODE_HOOK, array( $post_id ) ) ) {
		return;
	}
	wp_schedule_single_event( time() + 15, SPHOTOGRAPHY_GEOCODE_HOOK, array( $post_id ) );
}
add_action( 'save_post_post', 'sphotography_geocode_schedule_post', 20, 3 );

// cron 任务：解析该文章所有照片坐标 × 所有启用语言，写入持久层。
function sphotography_geocode_run_post_job( $post_id ) {
	$coords = sphotography_geocode_post_photo_coords( (int) $post_id );
	if ( empty( $coords ) ) {
		return;
	}
	$langs = sphotography_geocode_enabled_langs();
	$is_default_ep = ( sphotography_geocode_endpoint() === SPHOTOGRAPHY_GEOCODE_DEFAULT_ENDPOINT );
	foreach ( $coords as $c ) {
		foreach ( $langs as $lang ) {
			// 已持久缓存则跳过，避免重复网络调用。
			if ( false !== sphotography_geocode_get_persistent( $lang, $c[0], $c[1] ) ) {
				continue;
			}
			sphotography_geocode_resolve( $c[0], $c[1], $lang, true );
			// 命中默认 Nominatim 时礼貌限速（≤1 req/s）。
			if ( $is_default_ep ) {
				usleep( 1100000 );
			}
		}
	}
}
add_action( SPHOTOGRAPHY_GEOCODE_HOOK, 'sphotography_geocode_run_post_job' );

// 一键回填：为所有已发布文章排预生成任务（错峰，避免限速任务同时触发）。
// 返回排入的任务数。
function sphotography_geocode_backfill_schedule_all() {
	$posts = get_posts( array(
		'post_type'   => 'post',
		'post_status' => 'publish',
		'numberposts' => -1,
		'fields'      => 'ids',
	) );
	$count = 0;
	foreach ( (array) $posts as $pid ) {
		$pid = (int) $pid;
		if ( wp_next_scheduled( SPHOTOGRAPHY_GEOCODE_HOOK, array( $pid ) ) ) {
			continue;
		}
		wp_schedule_single_event( time() + 20 + ( $count * 5 ), SPHOTOGRAPHY_GEOCODE_HOOK, array( $pid ) );
		$count++;
	}
	return $count;
}

// REST：后台「一键预生成全站照片地址」按钮触发（需管理员权限）。
function sphotography_geocode_register_backfill_route() {
	register_rest_route( 'sphotography/v1', '/geocode-backfill', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'sphotography_geocode_rest_backfill',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	) );
}
add_action( 'rest_api_init', 'sphotography_geocode_register_backfill_route' );

function sphotography_geocode_rest_backfill() {
	$n = sphotography_geocode_backfill_schedule_all();
	return rest_ensure_response( array(
		'scheduled' => (int) $n,
		'langs'     => sphotography_geocode_enabled_langs(),
	) );
}
