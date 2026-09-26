<?php
// 公告页浮层面板（右上角，支持 Markdown、翻译预生成）

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SPHOTOGRAPHY_ANNOUNCE_HOOK' ) ) {
	define( 'SPHOTOGRAPHY_ANNOUNCE_HOOK', 'sphotography_announcement_prewarm_event' );
}

// 公告功能是否开启（后台开关 + 内容非空）
function sphotography_announcement_enabled() {
	return (bool) sphotography_get_mod( 'announcement_enabled' ) && '' !== trim( sphotography_announcement_markdown() );
}

// 是否默认自动展开
function sphotography_announcement_auto_open() {
	return (bool) sphotography_get_mod( 'announcement_auto_open' );
}

// 后台编写的 Markdown 原文
function sphotography_announcement_markdown() {
	return (string) sphotography_get_mod( 'announcement_content' );
}

// 渲染为安全 HTML
function sphotography_announcement_html() {
	$md = sphotography_announcement_markdown();
	if ( '' === trim( $md ) ) {
		return '';
	}
	if ( ! function_exists( 'sphotography_markdown_subset' ) ) {
		return wpautop( esc_html( $md ) );
	}
	return sphotography_markdown_subset( $md, array( 'headings' => true ) );
}

// 内容 hash
function sphotography_announcement_hash() {
	return md5( sphotography_announcement_markdown() );
}

// 传给前端的公告数据
function sphotography_announcement_data() {
	if ( ! sphotography_announcement_enabled() ) {
		return array( 'enabled' => false );
	}
	return array(
		'enabled'  => true,
		'autoOpen' => sphotography_announcement_auto_open(),
		'hash'     => sphotography_announcement_hash(),
		'html'     => sphotography_announcement_html(),
	);
}

// 保存时预生成译文（异步 wp-cron）
function sphotography_announcement_run_prewarm() {
	if ( ! function_exists( 'sphotography_i18n_translate_enabled' ) || ! sphotography_i18n_translate_enabled() ) {
		return;
	}
	if ( ! sphotography_announcement_enabled() ) {
		return;
	}
	$html = sphotography_announcement_html();
	if ( '' !== trim( $html ) && function_exists( 'sphotography_i18n_prewarm' ) ) {
		sphotography_i18n_prewarm( $html, 'html' );
	}
}
add_action( SPHOTOGRAPHY_ANNOUNCE_HOOK, 'sphotography_announcement_run_prewarm' );

// 公告内容变化后安排异步译文预热
function sphotography_announcement_on_mods_update( $old_value, $value ) {
	// 仅在翻译功能开启且公告开启时才有意义。
	if ( ! function_exists( 'sphotography_i18n_translate_enabled' ) || ! sphotography_i18n_translate_enabled() ) {
		return;
	}
	if ( ! sphotography_announcement_enabled() ) {
		return;
	}
	$hash = sphotography_announcement_hash();
	$last = (string) get_option( 'sphotography_announcement_i18n_hash', '' );
	if ( $hash === $last ) {
		return; // 内容未变，已预热过
	}
	update_option( 'sphotography_announcement_i18n_hash', $hash, false );
	if ( ! wp_next_scheduled( SPHOTOGRAPHY_ANNOUNCE_HOOK ) ) {
		wp_schedule_single_event( time() + 8, SPHOTOGRAPHY_ANNOUNCE_HOOK );
	}
}
add_action( 'update_option_theme_mods_' . get_option( 'stylesheet' ), 'sphotography_announcement_on_mods_update', 10, 2 );
