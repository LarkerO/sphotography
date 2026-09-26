<?php
// 前台多语言（中/英/日）动态内容翻译

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SPHOTOGRAPHY_I18N_CACHE_TTL' ) ) {
	// 译文缓存有效期：一周。源文本变化会生成新 key，旧 key 自然过期。
	define( 'SPHOTOGRAPHY_I18N_CACHE_TTL', WEEK_IN_SECONDS );
}
if ( ! defined( 'SPHOTOGRAPHY_I18N_MAX_SEGMENTS' ) ) {
	define( 'SPHOTOGRAPHY_I18N_MAX_SEGMENTS', 40 );   // 单次请求最多翻译的段数
}
if ( ! defined( 'SPHOTOGRAPHY_I18N_MAX_CHARS' ) ) {
	define( 'SPHOTOGRAPHY_I18N_MAX_CHARS', 12000 );   // 单段最大字符数（超出截断保护）
}

/** 支持的目标语种 → 提示词里用的语言名。中文是原生语言，不在此列。 */
function sphotography_i18n_target_langs() {
	return array(
		'en' => 'English',
		'ja' => 'Japanese (日本語)',
	);
}

/** 计算某段文本在某语言下的缓存 key。 */
function sphotography_i18n_cache_key( $lang, $format, $text ) {
	return 'sp_i18n_' . $lang . '_' . md5( $format . '|' . $text );
}

/**
 * 注册 REST 路由： POST sphotography/v1/translate
 * body: { lang: 'en'|'ja', segments: [ { id, text, format? }, ... ] }
 * 返回: { lang, segments: { id: translated, ... } }
 */
function sphotography_i18n_register_routes() {
	register_rest_route( 'sphotography/v1', '/translate', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'sphotography_i18n_rest_translate',
		'permission_callback' => '__return_true', // 公开读取译文；下方按 AI 开关 + 缓存 + 长度上限约束成本
	) );
}
add_action( 'rest_api_init', 'sphotography_i18n_register_routes' );

/**
 * REST 回调：批量翻译请求。先查缓存，未命中的段落再合并成一次模型调用；
 * 若模型返回无法解析为 JSON，退化为逐段调用，最大程度保证可用。
 */
function sphotography_i18n_rest_translate( WP_REST_Request $request ) {
	if ( ! function_exists( 'sphotography_ai_is_enabled' ) || ! sphotography_ai_is_enabled() ) {
		return new WP_Error( 'ai_disabled', __( 'AI 功能未启用。', 'sphotography' ), array( 'status' => 403 ) );
	}

	$lang = (string) $request->get_param( 'lang' );
	$targets = sphotography_i18n_target_langs();
	if ( ! isset( $targets[ $lang ] ) ) {
		return new WP_Error( 'bad_lang', __( '不支持的目标语言。', 'sphotography' ), array( 'status' => 400 ) );
	}

	$segments = $request->get_param( 'segments' );
	if ( ! is_array( $segments ) || empty( $segments ) ) {
		return new WP_Error( 'no_segments', __( '缺少待翻译内容。', 'sphotography' ), array( 'status' => 400 ) );
	}
	if ( count( $segments ) > SPHOTOGRAPHY_I18N_MAX_SEGMENTS ) {
		$segments = array_slice( $segments, 0, SPHOTOGRAPHY_I18N_MAX_SEGMENTS );
	}

	$result   = array();   // id => translated text
	$todo     = array();   // 未命中缓存、需要送模型的段： id => array( text, format, key )

	foreach ( $segments as $seg ) {
		if ( ! is_array( $seg ) || ! isset( $seg['id'] ) || ! isset( $seg['text'] ) ) {
			continue;
		}
		$id     = (string) $seg['id'];
		$text   = (string) $seg['text'];
		$format = ( isset( $seg['format'] ) && 'html' === $seg['format'] ) ? 'html' : 'text';

		if ( '' === trim( $text ) ) {
			$result[ $id ] = $text; // 空白直接原样返回
			continue;
		}
		if ( mb_strlen( $text ) > SPHOTOGRAPHY_I18N_MAX_CHARS ) {
			$text = mb_substr( $text, 0, SPHOTOGRAPHY_I18N_MAX_CHARS );
		}

		$key    = sphotography_i18n_cache_key( $lang, $format, $text );
		$cached = get_transient( $key );
		if ( false !== $cached ) {
			$result[ $id ] = $cached;
			continue;
		}
		$todo[ $id ] = array( 'text' => $text, 'format' => $format, 'key' => $key );
	}

	if ( ! empty( $todo ) ) {
		$translated = sphotography_i18n_translate_batch( $todo, $lang, $targets[ $lang ] );
		foreach ( $todo as $id => $info ) {
			if ( isset( $translated[ $id ] ) && '' !== trim( (string) $translated[ $id ] ) ) {
				$out = (string) $translated[ $id ];
				set_transient( $info['key'], $out, SPHOTOGRAPHY_I18N_CACHE_TTL );
				$result[ $id ] = $out;
			} else {
				// 翻译失败：回退原文（前端也会据此淡入原文）。不缓存失败结果。
				$result[ $id ] = $info['text'];
			}
		}
	}

	return rest_ensure_response( array(
		'lang'     => $lang,
		'segments' => $result,
	) );
}

/**
 * 将多段文本合并为一次模型调用（JSON 进 / JSON 出）。解析失败时逐段重试。
 * 返回 id => 译文。仅包含成功翻译的项。
 */
function sphotography_i18n_translate_batch( $todo, $lang, $lang_name ) {
	$items = array();
	foreach ( $todo as $id => $info ) {
		$items[] = array( 'id' => $id, 'text' => $info['text'] );
	}

	$system = sprintf(
		'You are a professional translator. Translate every item in the user-provided JSON array into %s. '
		. 'Rules: (1) Preserve meaning, tone and register. (2) Some items contain HTML or Markdown — keep all tags, '
		. 'attributes, URLs, code and structure byte-for-byte; translate only human-readable text between them. '
		. '(3) Do NOT translate proper nouns such as personal names, brand names, place names or camera/EXIF values — leave them as-is. '
		. '(4) Never add notes or explanations. '
		. 'Return ONLY a compact JSON object mapping each item id (string) to its translated text, e.g. {"a":"...","b":"..."}.',
		$lang_name
	);
	$user = wp_json_encode( array( 'items' => $items ), JSON_UNESCAPED_UNICODE );

	$reply = sphotography_ai_chat(
		array(
			array( 'role' => 'system', 'content' => $system ),
			array( 'role' => 'user',   'content' => $user ),
		),
		array(
			'temperature'     => 0.2,
			'max_tokens'      => 8000,
			'response_format' => array( 'type' => 'json_object' ),
			'timeout'         => 90,
		)
	);

	if ( ! is_wp_error( $reply ) ) {
		$map = sphotography_i18n_parse_json_map( (string) $reply );
		if ( is_array( $map ) ) {
			// 至少解析出一部分即视为批量成功；缺失项交给逐段兜底。
			$missing = array();
			foreach ( $todo as $id => $info ) {
				if ( ! isset( $map[ $id ] ) || '' === trim( (string) $map[ $id ] ) ) {
					$missing[ $id ] = $info;
				}
			}
			if ( empty( $missing ) ) {
				return $map;
			}
			return array_merge( $map, sphotography_i18n_translate_each( $missing, $lang_name ) );
		}
	}

	// 批量整体失败（模型报错或非 JSON）：逐段翻译兜底。
	return sphotography_i18n_translate_each( $todo, $lang_name );
}

/**
 * v1.4.4: 预热 /translate 的 transient 缓存 —— 把一段内容在所有目标语言下先翻译好
 * 并按 /translate 使用的同一 key 缓存起来，使前端随后按需请求同一段文本时直接命中
 * 缓存（零模型调用）。用于「公告后台保存时预生成译文」。可在 wp-cron 中调用。
 */
function sphotography_i18n_prewarm( $text, $format ) {
	$text = (string) $text;
	if ( '' === trim( $text ) || ! function_exists( 'sphotography_ai_is_enabled' ) || ! sphotography_ai_is_enabled() ) {
		return;
	}
	$format = ( 'html' === $format ) ? 'html' : 'text';
	if ( mb_strlen( $text ) > SPHOTOGRAPHY_I18N_MAX_CHARS ) {
		$text = mb_substr( $text, 0, SPHOTOGRAPHY_I18N_MAX_CHARS );
	}
	$targets = sphotography_i18n_target_langs();
	foreach ( $targets as $lang => $lang_name ) {
		$key = sphotography_i18n_cache_key( $lang, $format, $text );
		if ( false !== get_transient( $key ) ) {
			continue; // 已缓存
		}
		$map = sphotography_i18n_translate_batch( array( 'a' => array( 'text' => $text, 'format' => $format ) ), $lang, $lang_name );
		if ( isset( $map['a'] ) && '' !== trim( (string) $map['a'] ) ) {
			set_transient( $key, (string) $map['a'], SPHOTOGRAPHY_I18N_CACHE_TTL );
		}
	}
}

/** 逐段翻译兜底（每段一次模型调用，纯文本进出）。 */
function sphotography_i18n_translate_each( $todo, $lang_name ) {
	$out = array();
	$system = sprintf(
		'Translate the user text into %s. Preserve any HTML/Markdown structure and do not translate proper nouns '
		. '(personal names, brands, place names, EXIF values). Output only the translation, no notes.',
		$lang_name
	);
	foreach ( $todo as $id => $info ) {
		$reply = sphotography_ai_chat(
			array(
				array( 'role' => 'system', 'content' => $system ),
				array( 'role' => 'user',   'content' => $info['text'] ),
			),
			array( 'temperature' => 0.2, 'max_tokens' => 8000, 'timeout' => 90 )
		);
		if ( ! is_wp_error( $reply ) ) {
			$out[ $id ] = trim( (string) $reply );
		}
	}
	return $out;
}

/**
 * 从模型返回里稳健地抠出 JSON 对象（有些模型会包裹 ```json fenced``` 或前后废话）。
 * 成功返回关联数组，失败返回 null。
 */
function sphotography_i18n_parse_json_map( $raw ) {
	$raw = trim( $raw );
	if ( '' === $raw ) {
		return null;
	}
	// 去掉 ```json ... ``` 代码围栏
	if ( 0 === strpos( $raw, '```' ) ) {
		$raw = preg_replace( '/^```[a-zA-Z]*\s*/', '', $raw );
		$raw = preg_replace( '/\s*```$/', '', $raw );
		$raw = trim( $raw );
	}
	$decoded = json_decode( $raw, true );
	if ( is_array( $decoded ) ) {
		// 可能返回 { "items": {...} } 或直接 { id: text }
		if ( isset( $decoded['items'] ) && is_array( $decoded['items'] ) ) {
			return $decoded['items'];
		}
		return $decoded;
	}
	// 退一步：截取第一个 { 到最后一个 } 再试
	$start = strpos( $raw, '{' );
	$end   = strrpos( $raw, '}' );
	if ( false !== $start && false !== $end && $end > $start ) {
		$decoded = json_decode( substr( $raw, $start, $end - $start + 1 ), true );
		if ( is_array( $decoded ) ) {
			return isset( $decoded['items'] ) && is_array( $decoded['items'] ) ? $decoded['items'] : $decoded;
		}
	}
	return null;
}

// 后台文章翻译（发布时异步生成 en/ja 译文，缓存在 post-meta 中）

if ( ! defined( 'SPHOTOGRAPHY_I18N_HASH_META' ) ) {
	define( 'SPHOTOGRAPHY_I18N_HASH_META', '_sp_i18n_hash' );      // source hash the stored translations were made from
}
if ( ! defined( 'SPHOTOGRAPHY_I18N_HOOK' ) ) {
	define( 'SPHOTOGRAPHY_I18N_HOOK', 'sphotography_i18n_generate_post_event' );
}

/** Per-language post-meta key holding {title, body, summary}. */
function sphotography_i18n_lang_meta( $lang ) {
	return '_sp_i18n_' . preg_replace( '/[^a-z]/', '', (string) $lang );
}

/** Feature on = AI master switch on AND the 翻译 sub-toggle (ai_translate) on. */
function sphotography_i18n_translate_enabled() {
	return function_exists( 'sphotography_ai_is_enabled' )
		&& sphotography_ai_is_enabled()
		&& (bool) sphotography_get_mod( 'ai_translate' );
}

/** Rendered (filtered) body HTML for a post — matches what the frontend shows. */
function sphotography_i18n_post_body_html( $post ) {
	$content = (string) $post->post_content;
	// Render shortcodes/blocks like the frontend REST `content.rendered` does.
	$content = apply_filters( 'the_content', $content );
	return is_string( $content ) ? $content : '';
}

/** The three source strings for a post: title (text), body (html), summary (text). */
function sphotography_i18n_post_sources( $post_id ) {
	$post = get_post( (int) $post_id );
	if ( ! $post ) {
		return array();
	}
	$summary = function_exists( 'sphotography_ai_get_summary' ) ? sphotography_ai_get_summary( $post_id ) : '';
	$sources = array(
		'title' => array( 'text' => get_the_title( $post ), 'format' => 'text' ),
		'body'  => array( 'text' => sphotography_i18n_post_body_html( $post ), 'format' => 'html' ),
	);
	if ( '' !== trim( (string) $summary ) ) {
		$sources['summary'] = array( 'text' => (string) $summary, 'format' => 'text' );
	}
	return $sources;
}

/** md5 over the source strings — changes whenever title/body/summary changes. */
function sphotography_i18n_post_source_hash( $post_id ) {
	$sources = sphotography_i18n_post_sources( $post_id );
	if ( empty( $sources ) ) {
		return '';
	}
	$parts = array();
	foreach ( $sources as $k => $info ) {
		$parts[] = $k . ':' . $info['text'];
	}
	return md5( implode( "\x1f", $parts ) );
}

/** Stored translation array for a post+lang ({title,body,summary}), or []. */
function sphotography_i18n_get_post_translation( $post_id, $lang ) {
	$val = get_post_meta( (int) $post_id, sphotography_i18n_lang_meta( $lang ), true );
	return is_array( $val ) ? $val : array();
}

/** True when there is no fresh translation set (missing or source changed). */
function sphotography_i18n_post_is_stale( $post_id ) {
	$stored_hash = (string) get_post_meta( (int) $post_id, SPHOTOGRAPHY_I18N_HASH_META, true );
	if ( '' === $stored_hash || $stored_hash !== sphotography_i18n_post_source_hash( $post_id ) ) {
		return true;
	}
	// Also stale if any supported language set is entirely missing.
	foreach ( array_keys( sphotography_i18n_target_langs() ) as $lang ) {
		$t = sphotography_i18n_get_post_translation( $post_id, $lang );
		if ( empty( $t ) ) {
			return true;
		}
	}
	return false;
}

/** Translate all source strings into one language; returns id => translated. */
function sphotography_i18n_translate_sources( $sources, $lang, $lang_name ) {
	$todo = array();
	foreach ( $sources as $id => $info ) {
		$text = (string) $info['text'];
		if ( mb_strlen( $text ) > SPHOTOGRAPHY_I18N_MAX_CHARS ) {
			$text = mb_substr( $text, 0, SPHOTOGRAPHY_I18N_MAX_CHARS );
		}
		$todo[ $id ] = array( 'text' => $text, 'format' => $info['format'] );
	}
	return sphotography_i18n_translate_batch( $todo, $lang, $lang_name );
}

/** Generate + store en/ja translations for a post. Guarded; safe to re-run. */
function sphotography_i18n_run_post_job( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! sphotography_i18n_translate_enabled() || ! function_exists( 'sphotography_ai_primary_ready' ) || ! sphotography_ai_primary_ready() ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return;
	}
	if ( ! sphotography_i18n_post_is_stale( $post_id ) ) {
		return;
	}
	$sources = sphotography_i18n_post_sources( $post_id );
	if ( empty( $sources ) ) {
		return;
	}
	$targets = sphotography_i18n_target_langs();
	$ok = true;
	foreach ( $targets as $lang => $lang_name ) {
		$map = sphotography_i18n_translate_sources( $sources, $lang, $lang_name );
		$store = array();
		foreach ( $sources as $id => $info ) {
			if ( isset( $map[ $id ] ) && '' !== trim( (string) $map[ $id ] ) ) {
				$store[ $id ] = (string) $map[ $id ];
			}
		}
		// Require at least title+body to consider this language done.
		if ( empty( $store['title'] ) || empty( $store['body'] ) ) {
			$ok = false;
			continue;
		}
		update_post_meta( $post_id, sphotography_i18n_lang_meta( $lang ), $store );
	}
	// Only stamp the hash when every language stored — otherwise stay stale so a
	// later run (or lazy backfill) retries the missing ones.
	if ( $ok ) {
		update_post_meta( $post_id, SPHOTOGRAPHY_I18N_HASH_META, sphotography_i18n_post_source_hash( $post_id ) );
	}
}
add_action( SPHOTOGRAPHY_I18N_HOOK, 'sphotography_i18n_run_post_job' );

/** Schedule async generation for a post when enabled + ready + stale (deduped). */
function sphotography_i18n_maybe_schedule( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! sphotography_i18n_translate_enabled() || ! function_exists( 'sphotography_ai_primary_ready' ) || ! sphotography_ai_primary_ready() ) {
		return;
	}
	$post = get_post( $post_id );
	if ( ! $post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return;
	}
	if ( ! sphotography_i18n_post_is_stale( $post_id ) ) {
		return;
	}
	if ( wp_next_scheduled( SPHOTOGRAPHY_I18N_HOOK, array( $post_id ) ) ) {
		return;
	}
	// A little after the AI-summary job (which may itself feed our source).
	wp_schedule_single_event( time() + 12, SPHOTOGRAPHY_I18N_HOOK, array( $post_id ) );
}

/** On publish/update, (re)schedule translation if the source changed. */
function sphotography_i18n_on_save( $post_id, $post, $update ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	if ( ! $post instanceof WP_Post || 'post' !== $post->post_type || 'publish' !== $post->post_status ) {
		return;
	}
	sphotography_i18n_maybe_schedule( $post_id );
}
add_action( 'save_post', 'sphotography_i18n_on_save', 25, 3 );

/**
 * REST field sp_i18n: fresh pre-generated translations for the article panel.
 * Returns { en: {title,body,summary}|null, ja: {...}|null }. When a post's
 * translations are stale/missing, lazily schedule generation (backfill) so old
 * posts get translated the first time they are opened — the reader sees the
 * on-demand /translate fallback in the meantime.
 */
function sphotography_i18n_register_rest_field() {
	register_rest_field( 'post', 'sp_i18n', array(
		'get_callback' => function ( $arr ) {
			$post_id = (int) $arr['id'];
			if ( ! sphotography_i18n_translate_enabled() ) {
				return null;
			}
			$out = array();
			$fresh = ! sphotography_i18n_post_is_stale( $post_id );
			if ( $fresh ) {
				foreach ( array_keys( sphotography_i18n_target_langs() ) as $lang ) {
					$t = sphotography_i18n_get_post_translation( $post_id, $lang );
					$out[ $lang ] = ! empty( $t ) ? $t : null;
				}
			} else {
				// Backfill: schedule generation on first fetch of a stale post.
				sphotography_i18n_maybe_schedule( $post_id );
			}
			return empty( $out ) ? null : $out;
		},
		'schema'       => array(
			'description' => 'Sphotography pre-generated article translations (en/ja).',
			'type'        => 'object',
		),
	) );
}
add_action( 'rest_api_init', 'sphotography_i18n_register_rest_field' );
