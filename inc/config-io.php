<?php
/**
 * v1.4.9 (item 1)：主题全局配置的导出 / 导入（JSON）。
 *
 * 导出：浏览器直接下载一个 JSON，包含全部主题设置（theme_mods）、AI API 密钥（明文，
 *       可选）、友链、留言板设置、地区标签颜色，使用户可在新的 WordPress 上一键恢复配置。
 * 导入：上传该 JSON，校验签名后覆盖式恢复；未知/旧键忽略；API 密钥按本站重新加密；
 *       地区标签颜色按标签 slug 匹配恢复（本站不存在的标签跳过）。
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SPHOTOGRAPHY_CONFIG_SIGNATURE' ) ) {
	define( 'SPHOTOGRAPHY_CONFIG_SIGNATURE', 'sphotography-theme-config' );
}

/** 收集全部 region_tag 术语的自定义颜色（slug => #hex）。 */
function sphotography_config_collect_region_colors() {
	$out = array();
	if ( ! taxonomy_exists( 'region_tag' ) ) {
		return $out;
	}
	$terms = get_terms( array( 'taxonomy' => 'region_tag', 'hide_empty' => false ) );
	if ( is_wp_error( $terms ) || empty( $terms ) ) {
		return $out;
	}
	$meta_key = defined( 'SPHOTOGRAPHY_TAG_COLOR_META' ) ? SPHOTOGRAPHY_TAG_COLOR_META : 'sphotography_color';
	foreach ( $terms as $term ) {
		$color = get_term_meta( $term->term_id, $meta_key, true );
		$color = sanitize_hex_color( (string) $color );
		if ( $color ) {
			$out[ $term->slug ] = $color;
		}
	}
	return $out;
}

/** 组装完整配置数组。$include_keys=false 时不含明文 API 密钥。 */
function sphotography_config_build( $include_keys = true ) {
	$defaults   = sphotography_get_default_settings();
	$theme_mods = array();
	foreach ( $defaults as $key => $default_value ) {
		$theme_mods[ $key ] = get_theme_mod( 'sphotography_' . $key, $default_value );
	}

	$data = array(
		'_signature'  => SPHOTOGRAPHY_CONFIG_SIGNATURE,
		'version'     => defined( 'SPHOTOGRAPHY_VERSION' ) ? SPHOTOGRAPHY_VERSION : '',
		'exported_at' => gmdate( 'c' ),
		'site_url'    => home_url(),
		'theme_mods'  => $theme_mods,
		'friend_links'        => get_option( 'sphotography_friend_links', array() ),
		'friend_link_apps'    => get_option( 'sphotography_friend_link_apps', array() ),
		'friend_link_notify'  => get_option( 'sphotography_friend_link_notify', '1' ),
		'guestbook_random'    => (int) get_option( 'sphotography_guestbook_random', 8 ),
		'region_tag_colors'   => sphotography_config_collect_region_colors(),
	);

	if ( $include_keys && function_exists( 'sphotography_ai_get_key' ) ) {
		$data['api_keys'] = array(
			'ai'     => (string) sphotography_ai_get_key(),
			'vision' => function_exists( 'sphotography_ai_get_vision_key' ) ? (string) sphotography_ai_get_vision_key() : '',
		);
	}

	return $data;
}

/** 导出：输出 JSON 附件下载。 */
function sphotography_config_handle_export() {
	if ( ! isset( $_POST['sphotography_config_nonce'] ) || ! wp_verify_nonce( $_POST['sphotography_config_nonce'], 'sphotography_config_io' ) ) {
		wp_die( __( 'Security check failed.', 'sphotography' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions.', 'sphotography' ) );
	}

	$include_keys = ! empty( $_POST['include_keys'] );
	$data = sphotography_config_build( $include_keys );
	$json = wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

	$filename = 'sphotography-config-' . gmdate( 'Ymd-His' ) . '.json';
	nocache_headers();
	header( 'Content-Type: application/json; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
	header( 'Content-Length: ' . strlen( $json ) );
	echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download
	exit;
}
add_action( 'admin_post_sphotography_export_config', 'sphotography_config_handle_export' );

/** 应用一份已解析的配置数组，返回各项计数摘要。 */
function sphotography_config_apply( $data ) {
	$summary = array( 'settings' => 0, 'keys' => 0, 'friends' => 0, 'colors' => 0, 'guestbook' => 0 );

	// 1) 主题设置：仅覆盖默认键中存在的项（忽略未知/旧键）。
	if ( isset( $data['theme_mods'] ) && is_array( $data['theme_mods'] ) ) {
		$defaults = sphotography_get_default_settings();
		foreach ( $data['theme_mods'] as $key => $value ) {
			if ( array_key_exists( $key, $defaults ) ) {
				set_theme_mod( 'sphotography_' . $key, $value );
				$summary['settings']++;
			}
		}
	}

	// 2) API 密钥：按本站 AUTH_KEY 重新加密存储。
	if ( isset( $data['api_keys'] ) && is_array( $data['api_keys'] ) && function_exists( 'sphotography_ai_store_key' ) ) {
		if ( isset( $data['api_keys']['ai'] ) && '' !== trim( (string) $data['api_keys']['ai'] ) ) {
			sphotography_ai_store_key( (string) $data['api_keys']['ai'] );
			$summary['keys']++;
		}
		if ( isset( $data['api_keys']['vision'] ) && '' !== trim( (string) $data['api_keys']['vision'] ) && function_exists( 'sphotography_ai_store_vision_key' ) ) {
			sphotography_ai_store_vision_key( (string) $data['api_keys']['vision'] );
			$summary['keys']++;
		}
	}

	// 3) 友链（整组替换）。
	if ( isset( $data['friend_links'] ) && is_array( $data['friend_links'] ) ) {
		update_option( 'sphotography_friend_links', $data['friend_links'] );
		$summary['friends'] = count( $data['friend_links'] );
	}
	if ( isset( $data['friend_link_apps'] ) && is_array( $data['friend_link_apps'] ) ) {
		update_option( 'sphotography_friend_link_apps', $data['friend_link_apps'] );
	}
	if ( isset( $data['friend_link_notify'] ) ) {
		update_option( 'sphotography_friend_link_notify', $data['friend_link_notify'] ? '1' : '0' );
	}

	// 4) 留言板设置（随机展示条数）。
	if ( isset( $data['guestbook_random'] ) ) {
		update_option( 'sphotography_guestbook_random', max( 0, (int) $data['guestbook_random'] ) );
		$summary['guestbook'] = 1;
	}

	// 5) 地区标签颜色：按 slug 匹配，本站不存在的标签跳过。
	if ( isset( $data['region_tag_colors'] ) && is_array( $data['region_tag_colors'] ) && taxonomy_exists( 'region_tag' ) ) {
		$meta_key = defined( 'SPHOTOGRAPHY_TAG_COLOR_META' ) ? SPHOTOGRAPHY_TAG_COLOR_META : 'sphotography_color';
		foreach ( $data['region_tag_colors'] as $slug => $color ) {
			$color = sanitize_hex_color( (string) $color );
			if ( ! $color ) {
				continue;
			}
			$term = get_term_by( 'slug', (string) $slug, 'region_tag' );
			if ( $term && ! is_wp_error( $term ) ) {
				update_term_meta( $term->term_id, $meta_key, $color );
				$summary['colors']++;
			}
		}
	}

	return $summary;
}

/** 导入：接收上传的 JSON，校验并覆盖式恢复，然后带摘要跳回设置页。 */
function sphotography_config_handle_import() {
	if ( ! isset( $_POST['sphotography_config_nonce'] ) || ! wp_verify_nonce( $_POST['sphotography_config_nonce'], 'sphotography_config_io' ) ) {
		wp_die( __( 'Security check failed.', 'sphotography' ) );
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( __( 'You do not have sufficient permissions.', 'sphotography' ) );
	}

	$redirect = wp_get_referer() ? wp_get_referer() : admin_url( 'admin.php?page=sphotography-settings' );

	if ( empty( $_FILES['config_file'] ) || ! isset( $_FILES['config_file']['tmp_name'] ) || '' === $_FILES['config_file']['tmp_name'] || ! is_uploaded_file( $_FILES['config_file']['tmp_name'] ) ) {
		wp_safe_redirect( add_query_arg( 'sp-import', 'nofile', $redirect ) );
		exit;
	}

	$raw = file_get_contents( $_FILES['config_file']['tmp_name'] );
	$data = json_decode( (string) $raw, true );

	if ( ! is_array( $data ) || ! isset( $data['_signature'] ) || SPHOTOGRAPHY_CONFIG_SIGNATURE !== $data['_signature'] ) {
		wp_safe_redirect( add_query_arg( 'sp-import', 'invalid', $redirect ) );
		exit;
	}

	$summary = sphotography_config_apply( $data );

	$args = array(
		'sp-import'    => 'ok',
		'sp-settings'  => $summary['settings'],
		'sp-keys'      => $summary['keys'],
		'sp-friends'   => $summary['friends'],
		'sp-colors'    => $summary['colors'],
	);
	wp_safe_redirect( add_query_arg( $args, $redirect ) );
	exit;
}
add_action( 'admin_post_sphotography_import_config', 'sphotography_config_handle_import' );

/** 设置页顶部：导入结果通知。 */
function sphotography_config_import_notice() {
	if ( ! isset( $_GET['sp-import'] ) ) {
		return;
	}
	$status = sanitize_key( wp_unslash( $_GET['sp-import'] ) );
	if ( 'ok' === $status ) {
		$msg = sprintf(
			/* translators: 1: settings count, 2: keys, 3: friend links, 4: region colors */
			__( '配置导入成功：%1$d 项设置、%2$d 个 API 密钥、%3$d 条友链、%4$d 个地区颜色已恢复。', 'sphotography' ),
			(int) ( $_GET['sp-settings'] ?? 0 ),
			(int) ( $_GET['sp-keys'] ?? 0 ),
			(int) ( $_GET['sp-friends'] ?? 0 ),
			(int) ( $_GET['sp-colors'] ?? 0 )
		);
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	} elseif ( 'invalid' === $status ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( '导入失败：这不是有效的 Sphotography 配置文件。', 'sphotography' ) . '</p></div>';
	} elseif ( 'nofile' === $status ) {
		echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( '导入失败：未选择文件。', 'sphotography' ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'sphotography_config_import_notice' );
