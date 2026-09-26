<?php
// 评论 IP 属地区域解析器（使用 ip2region v2 xdb 离线数据库）

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Bump when the hosted database changes to force installed sites to re-download.
const SPHOTOGRAPHY_IP_DATA_VERSION = '1';
const SPHOTOGRAPHY_IP_REMOTE_BASE  = 'https://cdn.jsdelivr.net/gh/ShirazuNagisa/sphotography@ip-data/';
const SPHOTOGRAPHY_IP_FILE         = 'ip2region.xdb';

// commentmeta key for the cached region label ('' means resolved-but-unknown).
const SPHOTOGRAPHY_IP_META = '_sp_ip_region';

// 设置开关
/**
 * Whether the IP-region feature is switched on.
 *
 * @return bool
 */
function sphotography_ip_region_enabled() {
	if ( function_exists( 'sphotography_comment_setting' ) ) {
		return (bool) sphotography_comment_setting( 'comment_ip_location' );
	}
	return (bool) get_theme_mod( 'sphotography_comment_ip_location', false );
}

// 数据库存储
/**
 * Directory the IP database is cached in.
 */
function sphotography_ip_dir() {
	$u = wp_upload_dir();
	return trailingslashit( $u['basedir'] ) . 'sphotography-ip';
}

/**
 * On-disk path of the xdb database.
 */
function sphotography_ip_db_path() {
	return sphotography_ip_dir() . '/' . SPHOTOGRAPHY_IP_FILE;
}

/**
 * Is the database present on disk at the current data version?
 */
function sphotography_ip_db_ready() {
	$ver_file = sphotography_ip_dir() . '/version.txt';
	$have_ver = is_readable( $ver_file ) ? trim( (string) file_get_contents( $ver_file ) ) : '';
	if ( $have_ver !== SPHOTOGRAPHY_IP_DATA_VERSION ) {
		return false;
	}
	$p = sphotography_ip_db_path();
	// A real ip2region xdb is several MB; guard against truncated downloads.
	return is_readable( $p ) && filesize( $p ) > 1000000;
}

/**
 * Ensure the database is present, downloading it from the hosted ip-data branch
 * into uploads if missing or outdated. Makes an outbound HTTP request, so this
 * must only ever run in an admin/CLI context — never on a frontend render.
 *
 * @param bool $force Re-download even if present.
 * @return true|WP_Error
 */
function sphotography_ip_ensure_file( $force = false ) {
	if ( ! $force && sphotography_ip_db_ready() ) {
		return true;
	}
	$dir = sphotography_ip_dir();
	if ( ! wp_mkdir_p( $dir ) ) {
		return new WP_Error( 'sphotography_ip_mkdir', sprintf( __( '无法创建目录：%s', 'sphotography' ), $dir ) );
	}
	$url  = SPHOTOGRAPHY_IP_REMOTE_BASE . SPHOTOGRAPHY_IP_FILE;
	$resp = wp_remote_get( $url, array( 'timeout' => 180 ) );
	if ( is_wp_error( $resp ) ) {
		return new WP_Error( 'sphotography_ip_http', sprintf( __( '下载失败：%1$s（%2$s）', 'sphotography' ), $url, $resp->get_error_message() ) );
	}
	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( 200 !== $code ) {
		return new WP_Error( 'sphotography_ip_http', sprintf( __( '下载失败：%1$s（HTTP %2$d）', 'sphotography' ), $url, $code ) );
	}
	$body = wp_remote_retrieve_body( $resp );
	if ( strlen( $body ) < 1000000 ) {
		return new WP_Error( 'sphotography_ip_body', sprintf( __( '下载内容异常（体积过小）：%s', 'sphotography' ), $url ) );
	}
	if ( false === file_put_contents( sphotography_ip_db_path(), $body ) ) {
		return new WP_Error( 'sphotography_ip_write', sprintf( __( '无法写入文件：%s', 'sphotography' ), sphotography_ip_db_path() ) );
	}
	@file_put_contents( $dir . '/version.txt', SPHOTOGRAPHY_IP_DATA_VERSION );
	return true;
}

/**
 * Opportunistically fetch the database shortly after the feature is enabled.
 * Runs in the admin only, guarded by a short-lived lock so a server that cannot
 * reach the network does not retry on every page load.
 */
function sphotography_ip_maybe_autodownload() {
	if ( ! sphotography_ip_region_enabled() || sphotography_ip_db_ready() ) {
		return;
	}
	if ( get_transient( 'sphotography_ip_dl_lock' ) ) {
		return;
	}
	// Lock first: whether the fetch succeeds or fails, don't hammer the source.
	set_transient( 'sphotography_ip_dl_lock', 1, HOUR_IN_SECONDS );
	$r = sphotography_ip_ensure_file();
	if ( true === $r ) {
		delete_transient( 'sphotography_ip_dl_lock' );
	}
}
add_action( 'admin_init', 'sphotography_ip_maybe_autodownload' );

// 公共解析 API
/**
 * Resolve a comment's IP to a display region, caching the result in commentmeta.
 * Returns '' when the feature is off, the database is missing, the IP is
 * private/IPv6/unresolvable, or resolution has not run yet on the frontend.
 *
 * @param WP_Comment $comment
 * @return string Region label (e.g. 广东 / 日本), or ''.
 */
function sphotography_comment_ip_region( $comment ) {
	if ( ! $comment || ! sphotography_ip_region_enabled() ) {
		return '';
	}
	$cached = get_comment_meta( $comment->comment_ID, SPHOTOGRAPHY_IP_META, true );
	if ( '' !== $cached && false !== $cached ) {
		return ( '-' === $cached ) ? '' : (string) $cached;
	}
	// Not resolved yet — resolve now if the database is on disk, and cache it
	// (store '-' as a sentinel for "resolved but unknown" so we don't retry).
	if ( ! sphotography_ip_db_ready() ) {
		return '';
	}
	$ip     = isset( $comment->comment_author_IP ) ? (string) $comment->comment_author_IP : '';
	$region = sphotography_ip_region_label( $ip );
	update_comment_meta( $comment->comment_ID, SPHOTOGRAPHY_IP_META, '' === $region ? '-' : $region );
	return $region;
}

/**
 * Map a raw IP string to a coarse region label: province inside China, country
 * elsewhere. Returns '' for private/reserved/IPv6/unknown addresses.
 *
 * @param string $ip
 * @return string
 */
function sphotography_ip_region_label( $ip ) {
	$ip = trim( (string) $ip );
	if ( '' === $ip || false !== strpos( $ip, ':' ) ) {
		return ''; // Empty or IPv6 — ip2region here is IPv4-only.
	}
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		return '';
	}
	// Skip private / reserved ranges outright.
	if ( ! filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
		return '';
	}

	$raw = sphotography_ip_search( $ip );
	if ( '' === $raw ) {
		return '';
	}
	// ip2region v4 (xdb format v3) region string: 国家|省份|城市|ISP|国家代码
	// (e.g. "中国|江苏省|南京市|0|CN", "United States|California|0|Google LLC|US").
	$parts    = explode( '|', $raw );
	$country  = isset( $parts[0] ) ? trim( $parts[0] ) : '';
	$province = isset( $parts[1] ) ? trim( $parts[1] ) : '';

	$blank = function ( $v ) {
		return '' === $v || '0' === $v;
	};

	if ( '中国' === $country || '中國' === $country ) {
		if ( ! $blank( $province ) ) {
			return sphotography_ip_trim_province( $province );
		}
		return '中国';
	}
	if ( ! $blank( $country ) ) {
		return $country;
	}
	return '';
}

/**
 * Trim common Chinese province/region suffixes for a cleaner label, matching
 * the convention used by major platforms (广东省 → 广东, 北京市 → 北京,
 * 内蒙古自治区 → 内蒙古, 香港特别行政区 → 香港).
 *
 * @param string $name
 * @return string
 */
function sphotography_ip_trim_province( $name ) {
	$suffixes = array( '特别行政区', '维吾尔自治区', '壮族自治区', '回族自治区', '自治区', '省', '市' );
	foreach ( $suffixes as $s ) {
		$len = strlen( $s );
		if ( strlen( $name ) > $len && substr( $name, -$len ) === $s ) {
			return substr( $name, 0, strlen( $name ) - $len );
		}
	}
	return $name;
}

// ip2region v2 xdb 搜索器（自包含 PHP 实现）
/**
 * Search the database for an IPv4 address, returning the raw region string or
 * '' on any error. The file handle and vector index are cached per request.
 *
 * @param string $ip Dotted IPv4.
 * @return string
 */
function sphotography_ip_search( $ip ) {
	static $handle  = null;
	static $failed  = false;
	static $vecbuf  = null;

	if ( $failed ) {
		return '';
	}
	if ( null === $handle ) {
		$path = sphotography_ip_db_path();
		$fh   = @fopen( $path, 'rb' );
		if ( ! $fh ) {
			$failed = true;
			return '';
		}
		$handle = $fh;
	}

	$ipl = sphotography_ip_to_long( $ip );
	if ( null === $ipl ) {
		return '';
	}

	// Vector index: header is 256 bytes, vector index follows.
	$il0 = ( $ipl >> 24 ) & 0xFF;
	$il1 = ( $ipl >> 16 ) & 0xFF;
	$idx = $il0 * 256 * 8 + $il1 * 8; // offset within the vector index block.

	// Cache the whole 512 KiB vector index once per request (one read, then
	// pure memory lookups for every subsequent IP).
	if ( null === $vecbuf ) {
		if ( 0 !== fseek( $handle, 256 ) ) {
			$failed = true;
			return '';
		}
		$vecbuf = fread( $handle, 256 * 256 * 8 );
		if ( false === $vecbuf || strlen( $vecbuf ) < 256 * 256 * 8 ) {
			$failed = true;
			return '';
		}
	}

	$s_ptr = sphotography_ip_getlong( $vecbuf, $idx );
	$e_ptr = sphotography_ip_getlong( $vecbuf, $idx + 4 );

	// Binary search the fixed 14-byte index segment [s_ptr, e_ptr].
	$data_len = 0;
	$data_ptr = 0;
	$low      = 0;
	$high     = (int) ( ( $e_ptr - $s_ptr ) / 14 );
	while ( $low <= $high ) {
		$mid = (int) ( ( $low + $high ) >> 1 );
		$off = $s_ptr + $mid * 14;
		if ( 0 !== fseek( $handle, $off ) ) {
			return '';
		}
		$buf = fread( $handle, 14 );
		if ( false === $buf || strlen( $buf ) < 14 ) {
			return '';
		}
		$sip = sphotography_ip_getlong( $buf, 0 );
		if ( $ipl < $sip ) {
			$high = $mid - 1;
		} else {
			$eip = sphotography_ip_getlong( $buf, 4 );
			if ( $ipl > $eip ) {
				$low = $mid + 1;
			} else {
				$data_len = ( ord( $buf[8] ) ) | ( ord( $buf[9] ) << 8 );
				$data_ptr = sphotography_ip_getlong( $buf, 10 );
				break;
			}
		}
	}

	if ( $data_len <= 0 ) {
		return '';
	}
	if ( 0 !== fseek( $handle, $data_ptr ) ) {
		return '';
	}
	$region = fread( $handle, $data_len );
	return ( false === $region ) ? '' : $region;
}

/**
 * Read an unsigned 32-bit little-endian integer from a binary buffer.
 *
 * @param string $buf
 * @param int    $pos
 * @return int
 */
function sphotography_ip_getlong( $buf, $pos ) {
	if ( $pos < 0 || $pos + 4 > strlen( $buf ) ) {
		return 0;
	}
	$v = unpack( 'V', substr( $buf, $pos, 4 ) );
	return isset( $v[1] ) ? ( $v[1] + 0 ) : 0;
}

/**
 * Convert a dotted IPv4 string to an unsigned long, or null if invalid.
 *
 * @param string $ip
 * @return int|null
 */
function sphotography_ip_to_long( $ip ) {
	$n = ip2long( $ip );
	if ( false === $n ) {
		return null;
	}
	// On 32-bit PHP ip2long can be negative; normalise to unsigned.
	if ( $n < 0 ) {
		$n += 4294967296;
	}
	return $n;
}

// AJAX：按需下载/重新下载 IP 数据库
function sphotography_ajax_download_ip_db() {
	check_ajax_referer( 'sphotography_ip_db', 'nonce' );
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => __( '权限不足。', 'sphotography' ) ) );
	}
	$r = sphotography_ip_ensure_file( true );
	if ( is_wp_error( $r ) ) {
		wp_send_json_error( array( 'message' => $r->get_error_message() ) );
	}
	delete_transient( 'sphotography_ip_dl_lock' );
	wp_send_json_success( array( 'message' => __( 'IP 库已就绪。', 'sphotography' ) ) );
}
add_action( 'wp_ajax_sphotography_download_ip_db', 'sphotography_ajax_download_ip_db' );
