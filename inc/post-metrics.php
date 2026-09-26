<?php
// 文章指标（阅读量、字数统计），通过 REST 字段暴露给前台

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const SPHOTOGRAPHY_VIEWS_META = '_sp_views';

// 阅读量计数是否启用（默认开启）
function sphotography_views_enabled() {
	return (bool) sphotography_get_mod( 'view_counter' );
}

// 获取文章阅读量
function sphotography_get_views( $post_id ) {
	return (int) get_post_meta( (int) $post_id, SPHOTOGRAPHY_VIEWS_META, true );
}

// CJK 感知的字数统计
function sphotography_post_word_count( $post_id ) {
	$post = get_post( (int) $post_id );
	if ( ! $post ) {
		return 0;
	}
	$text = (string) $post->post_content;
	$text = strip_shortcodes( $text );
	// Drop block comments and tags so only readable text remains.
	$text = preg_replace( '/<!--.*?-->/s', ' ', $text );
	$text = wp_strip_all_tags( $text );
	$text = html_entity_decode( $text, ENT_QUOTES, 'UTF-8' );
	if ( '' === trim( $text ) ) {
		return 0;
	}

	// CJK ranges kept in sync with SP_CJK_RE in app.js.
	$cjk_pattern = '/[\x{3400}-\x{9FFF}\x{F900}-\x{FAFF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]/u';
	$cjk = preg_match_all( $cjk_pattern, $text, $m );
	$cjk = $cjk ? (int) $cjk : 0;

	$latin_text = preg_replace( $cjk_pattern, ' ', $text );
	$latin = preg_match_all( "/[A-Za-z0-9]+(?:['’\\-][A-Za-z0-9]+)*/u", $latin_text, $lm );
	$latin = $latin ? (int) $latin : 0;

	return $cjk + $latin;
}

/**
 * Plain-text card excerpt with the v1.4.9 precedence, shared by the REST field
 * and the inline preload so both data paths agree:
 *   1) a manually-entered excerpt (has_excerpt) — used verbatim;
 *   2) else the AI 概述 (when the AI + summary toggles are on and one exists);
 *   3) else the current WP behaviour (auto-generated excerpt).
 * The AI summary is already a clean single paragraph (≤200 chars); the WP/manual
 * excerpt is tag-stripped to plain text. Search matching does NOT use this — it
 * keeps matching the fuller WP excerpt for findability.
 */
function sphotography_card_excerpt( $post_id ) {
	$post_id = (int) $post_id;
	$post    = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	// 1. Manual excerpt wins.
	if ( has_excerpt( $post ) ) {
		return trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
	}
	// 2. AI 概述, when enabled and present for this post.
	if ( function_exists( 'sphotography_ai_summary_enabled' ) && sphotography_ai_summary_enabled()
		&& function_exists( 'sphotography_ai_get_summary' ) ) {
		$summary = trim( (string) sphotography_ai_get_summary( $post_id ) );
		if ( '' !== $summary ) {
			return $summary;
		}
	}
	// 3. Current logic: WP auto-generated excerpt.
	return trim( wp_strip_all_tags( get_the_excerpt( $post ) ) );
}

// REST 字段注册
function sphotography_metrics_register_rest_fields() {
	register_rest_field( 'post', 'sp_card_excerpt', array(
		'get_callback' => function ( $arr ) {
			return sphotography_card_excerpt( (int) $arr['id'] );
		},
		'schema'       => array(
			'description' => 'Sphotography card excerpt (manual > AI summary > auto).',
			'type'        => 'string',
		),
	) );

	register_rest_field( 'post', 'sp_views', array(
		'get_callback' => function ( $arr ) {
			return sphotography_get_views( (int) $arr['id'] );
		},
		'schema'       => array(
			'description' => 'Sphotography article view count.',
			'type'        => 'integer',
		),
	) );

	register_rest_field( 'post', 'sp_word_count', array(
		'get_callback' => function ( $arr ) {
			return sphotography_post_word_count( (int) $arr['id'] );
		},
		'schema'       => array(
			'description' => 'Sphotography article word count.',
			'type'        => 'integer',
		),
	) );
}
add_action( 'rest_api_init', 'sphotography_metrics_register_rest_fields' );

// 阅读量递增接口：POST sphotography/v1/view/<id>
function sphotography_metrics_register_routes() {
	register_rest_route( 'sphotography/v1', '/view/(?P<id>\d+)', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'sphotography_rest_increment_view',
		'permission_callback' => '__return_true',
		'args'                => array(
			'id' => array(
				'validate_callback' => function ( $param ) {
					return is_numeric( $param );
				},
			),
		),
	) );
}
add_action( 'rest_api_init', 'sphotography_metrics_register_routes' );

// 递增阅读量
function sphotography_rest_increment_view( $request ) {
	$post_id = (int) $request['id'];
	$post    = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'sp_not_found', 'Post not found.', array( 'status' => 404 ) );
	}

	if ( ! sphotography_views_enabled() ) {
		return rest_ensure_response( array(
			'views'   => sphotography_get_views( $post_id ),
			'counted' => false,
		) );
	}

	$views = sphotography_get_views( $post_id ) + 1;
	update_post_meta( $post_id, SPHOTOGRAPHY_VIEWS_META, $views );

	return rest_ensure_response( array(
		'views'   => $views,
		'counted' => true,
	) );
}
