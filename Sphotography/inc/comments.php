<?php
// 评论系统（RESTful，支持 Markdown、悄悄话、点赞、置顶、编辑历史、UA 显示）

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'SPHOTOGRAPHY_COMMENT_COOKIE' ) ) {
	define( 'SPHOTOGRAPHY_COMMENT_COOKIE', 'sphotography_c' );
}
if ( ! defined( 'SPHOTOGRAPHY_COMMENTS_PER_PAGE' ) ) {
	define( 'SPHOTOGRAPHY_COMMENTS_PER_PAGE', 10 );
}
if ( ! defined( 'SPHOTOGRAPHY_COMMENT_FOLD_PX' ) ) {
	define( 'SPHOTOGRAPHY_COMMENT_FOLD_PX', 200 );
}

// 设置辅助

/**
 * Read a comment-related theme setting with its registered default.
 *
 * @param string $key Setting key without the sphotography_ prefix.
 * @return mixed
 */
function sphotography_comment_setting( $key ) {
	static $defaults = null;
	if ( null === $defaults && function_exists( 'sphotography_get_default_settings' ) ) {
		$defaults = sphotography_get_default_settings();
	}
	$default = isset( $defaults[ $key ] ) ? $defaults[ $key ] : false;
	return get_theme_mod( 'sphotography_' . $key, $default );
}

/**
 * Whether the current user counts as the blog owner (admin).
 *
 * @return bool
 */
function sphotography_is_blog_admin() {
	return current_user_can( 'manage_options' );
}

/**
 * The full comment configuration, shaped for the frontend.
 *
 * @return array
 */
function sphotography_comment_config() {
	return array(
		'captcha'        => (bool) sphotography_comment_setting( 'comment_captcha' ),
		'allowEdit'      => (bool) sphotography_comment_setting( 'comment_allow_edit' ),
		'allowPrivate'   => (bool) sphotography_comment_setting( 'comment_allow_private' ),
		'mailNotify'     => (bool) sphotography_comment_setting( 'comment_mail_notify' ),
		'markdown'       => (bool) sphotography_comment_setting( 'comment_markdown' ),
		'emojiPanel'     => (bool) sphotography_comment_setting( 'comment_emoji_panel' ),
		'pagination'     => sphotography_comment_setting( 'comment_pagination' ),
		'avatarAlign'    => sphotography_comment_setting( 'comment_avatar_align' ),
		'editHistoryView' => sphotography_comment_setting( 'comment_edit_history_view' ),
		'pinEnabled'     => (bool) sphotography_comment_setting( 'comment_pin_enabled' ),
		'likeEnabled'    => (bool) sphotography_comment_setting( 'comment_like_enabled' ),
		'uaDisplay'      => sphotography_comment_setting( 'comment_ua_display' ),
		'textAvatar'     => (bool) sphotography_comment_setting( 'comment_text_avatar' ),
		'foldLong'       => (bool) sphotography_comment_setting( 'comment_fold_long' ),
		'foldPx'         => SPHOTOGRAPHY_COMMENT_FOLD_PX,
		'showReplyTo'    => (bool) sphotography_comment_setting( 'comment_show_reply_to' ),
		'ipLocation'     => (bool) sphotography_comment_setting( 'comment_ip_location' ),
		'perPage'        => SPHOTOGRAPHY_COMMENTS_PER_PAGE,
		'isAdmin'        => sphotography_is_blog_admin(),
	);
}

// 匿名身份（bearer-token cookie）

/**
 * Read the visitor's identity cookie: { t: [tokens], l: [liked comment ids] }.
 *
 * @return array
 */
function &sphotography_identity() {
	static $data = null;
	if ( null === $data ) {
		$data = array( 't' => array(), 'l' => array() );
		if ( ! empty( $_COOKIE[ SPHOTOGRAPHY_COMMENT_COOKIE ] ) ) {
			$raw = json_decode( base64_decode( wp_unslash( $_COOKIE[ SPHOTOGRAPHY_COMMENT_COOKIE ] ) ), true );
			if ( is_array( $raw ) ) {
				if ( ! empty( $raw['t'] ) && is_array( $raw['t'] ) ) {
					$data['t'] = array_values( array_map( 'strval', $raw['t'] ) );
				}
				if ( ! empty( $raw['l'] ) && is_array( $raw['l'] ) ) {
					$data['l'] = array_values( array_map( 'intval', $raw['l'] ) );
				}
			}
		}
	}
	return $data;
}

/**
 * Persist the identity cookie (HttpOnly) and reflect it for the current request.
 */
function sphotography_save_identity() {
	$data = sphotography_identity();
	// Bound cookie size: keep the most recent 200 tokens / liked ids.
	if ( count( $data['t'] ) > 200 ) {
		$data['t'] = array_slice( $data['t'], -200 );
	}
	if ( count( $data['l'] ) > 500 ) {
		$data['l'] = array_slice( $data['l'], -500 );
	}
	$value = base64_encode( wp_json_encode( $data ) );
	if ( ! headers_sent() ) {
		setcookie(
			SPHOTOGRAPHY_COMMENT_COOKIE,
			$value,
			time() + YEAR_IN_SECONDS,
			COOKIEPATH ? COOKIEPATH : '/',
			COOKIE_DOMAIN,
			is_ssl(),
			true
		);
	}
	$_COOKIE[ SPHOTOGRAPHY_COMMENT_COOKIE ] = $value;
}

/**
 * Does the current visitor own this comment (may edit / see it privately)?
 *
 * @param WP_Comment $comment
 * @return bool
 */
function sphotography_owns_comment( $comment ) {
	if ( ! $comment ) {
		return false;
	}
	if ( is_user_logged_in() && $comment->user_id && (int) $comment->user_id === get_current_user_id() ) {
		return true;
	}
	$token = get_comment_meta( $comment->comment_ID, '_sp_token', true );
	if ( $token ) {
		$id = sphotography_identity();
		return in_array( (string) $token, $id['t'], true );
	}
	return false;
}

// 路由注册

function sphotography_register_comment_routes() {
	$ns = 'sphotography/v1';

	register_rest_route( $ns, '/comments', array(
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'sphotography_rest_list_comments',
			'permission_callback' => '__return_true',
		),
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'sphotography_rest_create_comment',
			'permission_callback' => '__return_true',
		),
	) );

	register_rest_route( $ns, '/comments/captcha', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'sphotography_rest_captcha',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( $ns, '/comments/(?P<id>\d+)/edit', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'sphotography_rest_edit_comment',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( $ns, '/comments/(?P<id>\d+)/like', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'sphotography_rest_like_comment',
		'permission_callback' => '__return_true',
	) );

	register_rest_route( $ns, '/comments/(?P<id>\d+)/pin', array(
		'methods'             => WP_REST_Server::CREATABLE,
		'callback'            => 'sphotography_rest_pin_comment',
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'sphotography_register_comment_routes' );

// 验证码

/**
 * Issue a numeric-sum captcha challenge. Answer is stored server-side in a
 * short-lived, single-use transient keyed by an opaque token.
 */
function sphotography_rest_captcha( $request ) {
	$a = wp_rand( 1, 9 );
	$b = wp_rand( 1, 9 );
	$token = wp_generate_password( 20, false );
	set_transient( 'sp_cap_' . $token, $a + $b, 10 * MINUTE_IN_SECONDS );
	return new WP_REST_Response( array(
		'token'    => $token,
		'question' => sprintf( '%d + %d', $a, $b ),
	), 200 );
}

/**
 * Verify (and consume) a captcha answer.
 *
 * @return bool
 */
function sphotography_verify_captcha( $token, $answer ) {
	if ( ! $token ) {
		return false;
	}
	$expected = get_transient( 'sp_cap_' . $token );
	if ( false === $expected ) {
		return false;
	}
	delete_transient( 'sp_cap_' . $token ); // single use.
	return (int) $answer === (int) $expected;
}

// 创建评论

function sphotography_rest_create_comment( WP_REST_Request $request ) {
	$post_id = (int) $request->get_param( 'post' );
	$content = trim( (string) $request->get_param( 'content' ) );
	$parent  = (int) $request->get_param( 'parent' );

	$post = get_post( $post_id );
	if ( ! $post ) {
		return new WP_Error( 'sp_no_post', __( '文章不存在。', 'sphotography' ), array( 'status' => 404 ) );
	}
	if ( ! comments_open( $post_id ) ) {
		return new WP_Error( 'sp_closed', __( '评论已关闭。', 'sphotography' ), array( 'status' => 403 ) );
	}
	if ( '' === $content ) {
		return new WP_Error( 'sp_empty', __( '请输入评论内容。', 'sphotography' ), array( 'status' => 400 ) );
	}

	$logged_in = is_user_logged_in();

	// Captcha (anonymous only). The guestbook (留言板) is exempt by design —
	// it never shows a captcha regardless of the global comment setting.
	// 只读判断（false）：提交评论不应触发占位文章的创建。
	$is_guestbook = function_exists( 'sphotography_guestbook_post_id' ) && $post_id === sphotography_guestbook_post_id( false );
	if ( sphotography_comment_setting( 'comment_captcha' ) && ! $logged_in && ! $is_guestbook ) {
		if ( ! sphotography_verify_captcha( $request->get_param( 'captcha_token' ), $request->get_param( 'captcha_answer' ) ) ) {
			return new WP_Error( 'sp_captcha', __( '验证码错误，请重试。', 'sphotography' ), array( 'status' => 400 ) );
		}
	}

	// Resolve author identity.
	if ( $logged_in ) {
		$user         = wp_get_current_user();
		$author_name  = $user->display_name;
		$author_email = $user->user_email;
		$author_url   = $user->user_url;
	} else {
		$author_name  = sanitize_text_field( (string) $request->get_param( 'author_name' ) );
		$author_email = sanitize_email( (string) $request->get_param( 'author_email' ) );
		$author_url   = '';
		if ( '' === $author_name || '' === $author_email || ! is_email( $author_email ) ) {
			return new WP_Error( 'sp_identity', __( '请填写有效的昵称与邮箱。', 'sphotography' ), array( 'status' => 400 ) );
		}
	}

	// Resolve threading: flatten to a single level. comment_parent is always the
	// top-level ancestor; the specific comment being replied to is recorded in
	// meta for @mention display and reply notifications.
	$reply_to_id   = 0;
	$comment_parent = 0;
	if ( $parent > 0 ) {
		$parent_comment = get_comment( $parent );
		if ( ! $parent_comment || (int) $parent_comment->comment_post_ID !== $post_id ) {
			return new WP_Error( 'sp_bad_parent', __( '回复的评论不存在。', 'sphotography' ), array( 'status' => 400 ) );
		}
		// Cannot reply to something you are not allowed to see (private thread).
		if ( ! sphotography_comment_visible( $parent_comment ) ) {
			return new WP_Error( 'sp_bad_parent', __( '回复的评论不存在。', 'sphotography' ), array( 'status' => 403 ) );
		}
		$reply_to_id    = $parent;
		$comment_parent = (int) $parent_comment->comment_parent ? (int) $parent_comment->comment_parent : $parent;
	}

	// Private (悄悄话) — only meaningful on a top-level comment. Replies inherit
	// visibility from their private root, so we never set it on children.
	$is_private = 0;
	if ( sphotography_comment_setting( 'comment_allow_private' ) && $request->get_param( 'is_private' ) && 0 === $comment_parent ) {
		$is_private = 1;
	}

	// Build comment data and let WordPress apply moderation / anti-spam.
	$commentdata = array(
		'comment_post_ID'      => $post_id,
		'comment_content'      => $content,
		'comment_parent'       => $comment_parent,
		'comment_author'       => $author_name,
		'comment_author_email' => $author_email,
		'comment_author_url'   => $author_url,
		'comment_type'         => 'comment',
	);
	if ( $logged_in ) {
		$commentdata['user_id'] = get_current_user_id();
	}

	$comment_id = wp_new_comment( wp_slash( $commentdata ), true );
	if ( is_wp_error( $comment_id ) ) {
		return new WP_Error( 'sp_insert', $comment_id->get_error_message(), array( 'status' => 400 ) );
	}

	// Persist metadata.
	if ( $reply_to_id ) {
		update_comment_meta( $comment_id, '_sp_reply_to', $reply_to_id );
	}
	if ( $is_private ) {
		update_comment_meta( $comment_id, '_sp_private', 1 );
	}
	if ( sphotography_comment_setting( 'comment_mail_notify' ) && $request->get_param( 'notify' ) ) {
		update_comment_meta( $comment_id, '_sp_notify', 1 );
	}

	// Anonymous ownership token → commentmeta + identity cookie.
	if ( ! $logged_in ) {
		$token = wp_generate_password( 24, false );
		update_comment_meta( $comment_id, '_sp_token', $token );
		$identity = &sphotography_identity();
		$identity['t'][] = $token;
		sphotography_save_identity();
	}

	$comment = get_comment( $comment_id );

	// Fire a reply notification if this reply is already approved.
	if ( $reply_to_id && '1' === (string) $comment->comment_approved ) {
		sphotography_maybe_send_reply_notification( $comment );
	}

	return new WP_REST_Response( array(
		'comment' => sphotography_prepare_comment( $comment ),
		'status'  => ( '1' === (string) $comment->comment_approved ) ? 'approved' : 'hold',
	), 201 );
}

// 列出评论

/**
 * Whether a comment (top-level or child) is visible to the current viewer,
 * accounting for 悄悄话 threads.
 *
 * @param WP_Comment $comment
 * @return bool
 */
function sphotography_comment_visible( $comment ) {
	if ( ! $comment ) {
		return false;
	}
	$root_id = (int) $comment->comment_parent ? (int) $comment->comment_parent : (int) $comment->comment_ID;
	$private = get_comment_meta( $root_id, '_sp_private', true );
	if ( ! $private ) {
		return true;
	}
	if ( sphotography_is_blog_admin() ) {
		return true;
	}
	$root = ( $root_id === (int) $comment->comment_ID ) ? $comment : get_comment( $root_id );
	return sphotography_owns_comment( $root );
}

function sphotography_rest_list_comments( WP_REST_Request $request ) {
	$post_id = (int) $request->get_param( 'post' );
	$page    = max( 1, (int) $request->get_param( 'page' ) );
	$per     = SPHOTOGRAPHY_COMMENTS_PER_PAGE;

	if ( ! get_post( $post_id ) ) {
		return new WP_Error( 'sp_no_post', __( '文章不存在。', 'sphotography' ), array( 'status' => 404 ) );
	}

	// Fetch all approved top-level comments, then filter/sort/paginate in PHP.
	// Comment volumes per post are modest; this keeps private-thread visibility
	// and pin ordering correct without brittle meta-driven SQL.
	$tops = get_comments( array(
		'post_id' => $post_id,
		'parent'  => 0,
		'status'  => 'approve',
		'type'    => 'comment',
		'orderby' => 'comment_date_gmt',
		'order'   => 'ASC',
	) );

	$visible = array();
	foreach ( $tops as $c ) {
		if ( sphotography_comment_visible( $c ) ) {
			$visible[] = $c;
		}
	}

	// Split pinned vs normal. Pinned float to the top, newest pin first.
	$pin_enabled  = sphotography_comment_setting( 'comment_pin_enabled' );
	$pinned_pairs = array();
	$normal       = array();
	foreach ( $visible as $c ) {
		$pin_time = $pin_enabled ? (int) get_comment_meta( $c->comment_ID, '_sp_pinned', true ) : 0;
		if ( $pin_time ) {
			$pinned_pairs[] = array( 'time' => $pin_time, 'comment' => $c );
		} else {
			$normal[] = $c;
		}
	}
	usort( $pinned_pairs, function ( $a, $b ) {
		return $b['time'] - $a['time'];
	} );
	$pinned = array();
	foreach ( $pinned_pairs as $pair ) {
		$pinned[] = $pair['comment'];
	}

	// Sort the normal (non-pinned) list per the requested order (v1.3.7).
	// Pinned comments are unaffected and always lead. $normal arrives in
	// comment_date_gmt ASC order from the query above.
	//   sort=time  + order=asc  → oldest first (default, unchanged)
	//   sort=time  + order=desc → newest first
	//   sort=likes             → most-liked first, ties broken newest-first
	$sort  = $request->get_param( 'sort' );
	$order = $request->get_param( 'order' );
	$sort  = ( 'likes' === $sort ) ? 'likes' : 'time';
	$order = ( 'desc' === $order ) ? 'desc' : 'asc';
	if ( 'likes' === $sort ) {
		usort( $normal, function ( $a, $b ) {
			$la = (int) get_comment_meta( $a->comment_ID, '_sp_likes', true );
			$lb = (int) get_comment_meta( $b->comment_ID, '_sp_likes', true );
			if ( $la !== $lb ) {
				return $lb - $la; // more likes first
			}
			// Tie-break: newer comment first.
			return strcmp( $b->comment_date_gmt, $a->comment_date_gmt );
		} );
	} elseif ( 'desc' === $order ) {
		$normal = array_reverse( $normal );
	}

	// Page 1 shows pinned first, then the first page of normal comments.
	// Later pages continue through the normal list only.
	if ( 1 === $page ) {
		$slice = array_merge( $pinned, array_slice( $normal, 0, $per ) );
		$has_more = count( $normal ) > $per;
	} else {
		$offset   = ( $page - 1 ) * $per;
		$slice    = array_slice( $normal, $offset, $per );
		$has_more = count( $normal ) > ( $offset + $per );
	}

	$items = array();
	foreach ( $slice as $c ) {
		$node = sphotography_prepare_comment( $c );
		// Children (single-level: every descendant is stored flat under this root).
		$children = get_comments( array(
			'post_id' => $post_id,
			'parent'  => (int) $c->comment_ID,
			'status'  => 'approve',
			'type'    => 'comment',
			'orderby' => 'comment_date_gmt',
			'order'   => 'ASC',
		) );
		$node['children'] = array();
		foreach ( $children as $child ) {
			$node['children'][] = sphotography_prepare_comment( $child );
		}
		$items[] = $node;
	}

	return new WP_REST_Response( array(
		'items'    => $items,
		'page'     => $page,
		'per_page' => $per,
		'total'    => sphotography_count_visible_comments( $post_id ),
		'has_more' => $has_more,
	), 200 );
}

/**
 * Total number of approved comments (top-level + replies) visible to the viewer.
 *
 * @param int $post_id
 * @return int
 */
function sphotography_count_visible_comments( $post_id ) {
	$all = get_comments( array(
		'post_id' => $post_id,
		'status'  => 'approve',
		'type'    => 'comment',
	) );
	$n = 0;
	foreach ( $all as $c ) {
		if ( sphotography_comment_visible( $c ) ) {
			$n++;
		}
	}
	return $n;
}

// 格式化评论为 JSON

/**
 * Shape a WP_Comment into the frontend payload, applying rendering and
 * per-viewer permission flags.
 *
 * @param WP_Comment $comment
 * @return array
 */
function sphotography_prepare_comment( $comment ) {
	$id    = (int) $comment->comment_ID;
	$owns  = sphotography_owns_comment( $comment );
	$admin = sphotography_is_blog_admin();

	$email = $comment->comment_author_email;
	$hash  = md5( strtolower( trim( $email ? $email : $comment->comment_author ) ) );
	$gravatar = get_avatar_url( $email ? $email : $hash . '@md5.gravatar.com', array( 'size' => 96, 'default' => '404' ) );

	// Reply-to (@mention) target.
	$reply_to = null;
	$reply_to_id = (int) get_comment_meta( $id, '_sp_reply_to', true );
	if ( $reply_to_id ) {
		$target = get_comment( $reply_to_id );
		if ( $target ) {
			$reply_to = array(
				'id'   => $reply_to_id,
				'name' => $target->comment_author ? $target->comment_author : __( '匿名', 'sphotography' ),
			);
		}
	}

	// Author is the blog owner?
	$author_is_admin = ( $comment->user_id && user_can( (int) $comment->user_id, 'manage_options' ) );

	// Edit history (visible per setting).
	$history_raw = get_comment_meta( $id, '_sp_edit_history', true );
	$edited      = is_array( $history_raw ) && ! empty( $history_raw );
	$can_see_history = ( 'all' === sphotography_comment_setting( 'comment_edit_history_view' ) ) || $admin;
	$history = array();
	if ( $edited && $can_see_history ) {
		foreach ( $history_raw as $h ) {
			$history[] = array(
				'content' => sphotography_render_comment_content( isset( $h['content'] ) ? $h['content'] : '' ),
				'date'    => isset( $h['date'] ) ? $h['date'] : '',
			);
		}
	}

	$likes = (int) get_comment_meta( $id, '_sp_likes', true );

	return array(
		'id'              => $id,
		'parent'          => (int) $comment->comment_parent,
		'author'          => $comment->comment_author ? $comment->comment_author : __( '匿名', 'sphotography' ),
		'author_is_admin' => (bool) $author_is_admin,
		'date'            => mysql_to_rfc3339( $comment->comment_date ),
		'content'         => sphotography_render_comment_content( $comment->comment_content ),
		'content_raw'     => ( $owns && sphotography_comment_setting( 'comment_allow_edit' ) ) ? $comment->comment_content : '',
		'gravatar'        => $gravatar,
		'hash'            => $hash,
		'reply_to'        => $reply_to,
		'is_private'      => (bool) get_comment_meta( $id, '_sp_private', true ),
		'pinned'          => (bool) ( sphotography_comment_setting( 'comment_pin_enabled' ) && get_comment_meta( $id, '_sp_pinned', true ) ),
		'likes'           => $likes,
		'liked'           => sphotography_has_liked( $id ),
		'can_edit'        => (bool) ( $owns && sphotography_comment_setting( 'comment_allow_edit' ) ),
		'can_pin'         => (bool) ( $admin && sphotography_comment_setting( 'comment_pin_enabled' ) && 0 === (int) $comment->comment_parent ),
		'is_mine'         => (bool) $owns,
		'edited'          => (bool) ( $edited && $can_see_history ),
		'history'         => $history,
		'ua'              => sphotography_format_comment_ua( $comment ),
		'ip_region'       => function_exists( 'sphotography_comment_ip_region' ) ? sphotography_comment_ip_region( $comment ) : '',
	);
}

// 编辑评论

function sphotography_rest_edit_comment( WP_REST_Request $request ) {
	$id      = (int) $request['id'];
	$content = trim( (string) $request->get_param( 'content' ) );
	$comment = get_comment( $id );

	if ( ! $comment ) {
		return new WP_Error( 'sp_no_comment', __( '评论不存在。', 'sphotography' ), array( 'status' => 404 ) );
	}
	if ( ! sphotography_comment_setting( 'comment_allow_edit' ) && ! sphotography_is_blog_admin() ) {
		return new WP_Error( 'sp_edit_off', __( '评论编辑功能未开启。', 'sphotography' ), array( 'status' => 403 ) );
	}
	if ( ! sphotography_owns_comment( $comment ) && ! sphotography_is_blog_admin() ) {
		return new WP_Error( 'sp_not_owner', __( '你无权编辑这条评论。', 'sphotography' ), array( 'status' => 403 ) );
	}
	if ( '' === $content ) {
		return new WP_Error( 'sp_empty', __( '评论内容不能为空。', 'sphotography' ), array( 'status' => 400 ) );
	}
	if ( $content === $comment->comment_content ) {
		return new WP_REST_Response( array( 'comment' => sphotography_prepare_comment( $comment ) ), 200 );
	}

	// Record the previous version to the edit history.
	$history = get_comment_meta( $id, '_sp_edit_history', true );
	if ( ! is_array( $history ) ) {
		$history = array();
	}
	$history[] = array(
		'content' => $comment->comment_content,
		'date'    => mysql_to_rfc3339( current_time( 'mysql' ) ),
	);
	update_comment_meta( $id, '_sp_edit_history', $history );

	wp_update_comment( array(
		'comment_ID'      => $id,
		'comment_content' => wp_slash( $content ),
	) );

	return new WP_REST_Response( array( 'comment' => sphotography_prepare_comment( get_comment( $id ) ) ), 200 );
}

// 点赞

function sphotography_has_liked( $comment_id ) {
	$comment_id = (int) $comment_id;
	if ( is_user_logged_in() ) {
		$liked = get_user_meta( get_current_user_id(), '_sp_liked_comments', true );
		return is_array( $liked ) && in_array( $comment_id, array_map( 'intval', $liked ), true );
	}
	$id = sphotography_identity();
	return in_array( $comment_id, $id['l'], true );
}

function sphotography_rest_like_comment( WP_REST_Request $request ) {
	if ( ! sphotography_comment_setting( 'comment_like_enabled' ) ) {
		return new WP_Error( 'sp_like_off', __( '点赞功能未开启。', 'sphotography' ), array( 'status' => 403 ) );
	}
	$id      = (int) $request['id'];
	$comment = get_comment( $id );
	if ( ! $comment ) {
		return new WP_Error( 'sp_no_comment', __( '评论不存在。', 'sphotography' ), array( 'status' => 404 ) );
	}
	if ( ! sphotography_comment_visible( $comment ) ) {
		return new WP_Error( 'sp_no_comment', __( '评论不存在。', 'sphotography' ), array( 'status' => 404 ) );
	}

	$count = (int) get_comment_meta( $id, '_sp_likes', true );
	$liked = sphotography_has_liked( $id );

	if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();
		$list    = get_user_meta( $user_id, '_sp_liked_comments', true );
		$list    = is_array( $list ) ? array_map( 'intval', $list ) : array();
		if ( $liked ) {
			$list  = array_values( array_diff( $list, array( $id ) ) );
			$count = max( 0, $count - 1 );
		} else {
			$list[] = $id;
			$count++;
		}
		update_user_meta( $user_id, '_sp_liked_comments', $list );
	} else {
		$identity = &sphotography_identity();
		if ( $liked ) {
			$identity['l'] = array_values( array_diff( $identity['l'], array( $id ) ) );
			$count = max( 0, $count - 1 );
		} else {
			$identity['l'][] = $id;
			$count++;
		}
		sphotography_save_identity();
	}

	update_comment_meta( $id, '_sp_likes', $count );

	return new WP_REST_Response( array(
		'likes' => $count,
		'liked' => ! $liked,
	), 200 );
}

// 置顶评论（仅管理员，仅顶层）

function sphotography_rest_pin_comment( WP_REST_Request $request ) {
	if ( ! sphotography_is_blog_admin() ) {
		return new WP_Error( 'sp_not_admin', __( '只有博主可以置顶评论。', 'sphotography' ), array( 'status' => 403 ) );
	}
	if ( ! sphotography_comment_setting( 'comment_pin_enabled' ) ) {
		return new WP_Error( 'sp_pin_off', __( '置顶功能未开启。', 'sphotography' ), array( 'status' => 403 ) );
	}
	$id      = (int) $request['id'];
	$comment = get_comment( $id );
	if ( ! $comment ) {
		return new WP_Error( 'sp_no_comment', __( '评论不存在。', 'sphotography' ), array( 'status' => 404 ) );
	}
	if ( (int) $comment->comment_parent !== 0 ) {
		return new WP_Error( 'sp_child', __( '只能置顶顶层评论。', 'sphotography' ), array( 'status' => 400 ) );
	}

	$pinned = (int) get_comment_meta( $id, '_sp_pinned', true );
	if ( $pinned ) {
		delete_comment_meta( $id, '_sp_pinned' );
		$now = false;
	} else {
		update_comment_meta( $id, '_sp_pinned', time() );
		$now = true;
	}

	return new WP_REST_Response( array( 'pinned' => $now ), 200 );
}

// 内容渲染：安全 Markdown 子集 + kses

/**
 * Render stored raw comment text to safe HTML. Applies a small Markdown subset
 * when enabled, then always runs the result through a strict kses whitelist.
 * Unicode emoji pass through untouched.
 *
 * @param string $raw
 * @return string
 */
function sphotography_render_comment_content( $raw ) {
	$raw = (string) $raw;
	if ( '' === $raw ) {
		return '';
	}

	if ( sphotography_comment_setting( 'comment_markdown' ) ) {
		$html = sphotography_markdown_subset( $raw );
	} else {
		$html = nl2br( esc_html( $raw ) );
	}

	$allowed = array(
		'p'          => array(),
		'br'         => array(),
		'strong'     => array(),
		'em'         => array(),
		'del'        => array(),
		'code'       => array(),
		'pre'        => array(),
		'blockquote' => array(),
		'ul'         => array(),
		'ol'         => array(),
		'li'         => array(),
		'a'          => array( 'href' => array(), 'rel' => array(), 'target' => array() ),
	);
	return wp_kses( $html, $allowed );
}

/**
 * A deliberately small, XSS-safe Markdown renderer. The input is HTML-escaped
 * first, so no user HTML ever survives; we then re-introduce only the tags in
 * the whitelist above.
 *
 * Supports: fenced code blocks (```), inline code (`), bold (**), italic (*),
 * strikethrough (~~), links [text](url), blockquotes (>), and unordered /
 * ordered lists.
 *
 * @param string $text
 * @return string
 */
function sphotography_markdown_subset( $text, $opts = array() ) {
	// v1.4.4: optional heading support (# → h3, ## → h4, ### → h5). Off by
	// default so comments/guestbook stay heading-free; the announcement page
	// (inc/announcement.php) opts in via array( 'headings' => true ).
	$allow_headings = ! empty( $opts['headings'] );
	$text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );

	// Extract fenced code blocks first so their contents are not further parsed.
	$blocks = array();
	$text = preg_replace_callback( '/```[ \t]*\n?(.*?)```/s', function ( $m ) use ( &$blocks ) {
		$key = '%%SPCODE' . count( $blocks ) . '%%';
		$blocks[ $key ] = '<pre><code>' . esc_html( rtrim( $m[1], "\n" ) ) . '</code></pre>';
		return "\n" . $key . "\n";
	}, $text );

	// Extract inline code next.
	$inline = array();
	$text = preg_replace_callback( '/`([^`\n]+)`/', function ( $m ) use ( &$inline ) {
		$key = '%%SPINLINE' . count( $inline ) . '%%';
		$inline[ $key ] = '<code>' . esc_html( $m[1] ) . '</code>';
		return $key;
	}, $text );

	// Escape everything else.
	$text = esc_html( $text );

	// Links: [text](http...). URL is validated by esc_url.
	$text = preg_replace_callback( '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/', function ( $m ) {
		$url = esc_url( $m[2] );
		if ( ! $url ) {
			return $m[0];
		}
		return '<a href="' . $url . '" rel="nofollow noopener" target="_blank">' . $m[1] . '</a>';
	}, $text );

	// Bold, then italic, then strikethrough.
	$text = preg_replace( '/\*\*([^*\n]+)\*\*/', '<strong>$1</strong>', $text );
	$text = preg_replace( '/(?<!\*)\*([^*\n]+)\*(?!\*)/', '<em>$1</em>', $text );
	$text = preg_replace( '/~~([^~\n]+)~~/', '<del>$1</del>', $text );

	// Block-level parsing line by line for blockquotes and lists.
	$lines  = explode( "\n", $text );
	$out    = array();
	$in_ul  = false;
	$in_ol  = false;
	$in_bq  = false;
	$para   = array();

	$flush_para = function () use ( &$para, &$out ) {
		if ( ! empty( $para ) ) {
			$out[] = '<p>' . implode( '<br>', $para ) . '</p>';
			$para = array();
		}
	};
	$close_lists = function () use ( &$in_ul, &$in_ol, &$out ) {
		if ( $in_ul ) { $out[] = '</ul>'; $in_ul = false; }
		if ( $in_ol ) { $out[] = '</ol>'; $in_ol = false; }
	};
	$close_bq = function () use ( &$in_bq, &$out ) {
		if ( $in_bq ) { $out[] = '</blockquote>'; $in_bq = false; }
	};

	foreach ( $lines as $line ) {
		$trimmed = trim( $line );

		// Placeholder-only line (code block) → emit as-is.
		if ( preg_match( '/^%%SPCODE\d+%%$/', $trimmed ) ) {
			$flush_para(); $close_lists(); $close_bq();
			$out[] = $trimmed;
			continue;
		}

		if ( '' === $trimmed ) {
			$flush_para(); $close_lists(); $close_bq();
			continue;
		}

		// Heading (v1.4.4, opt-in): #/##/### → h3/h4/h5. Kept shallow so the
		// announcement never emits an h1/h2 that clashes with page structure.
		if ( $allow_headings && preg_match( '/^(#{1,3})\s+(.*)$/', $trimmed, $m ) ) {
			$flush_para(); $close_lists(); $close_bq();
			$level = strlen( $m[1] ) + 2; // # → h3, ## → h4, ### → h5
			$out[] = '<h' . $level . '>' . $m[2] . '</h' . $level . '>';
			continue;
		}

		// Unordered list item.
		if ( preg_match( '/^[-*+]\s+(.*)$/', $trimmed, $m ) ) {
			$flush_para(); $close_bq();
			if ( $in_ol ) { $out[] = '</ol>'; $in_ol = false; }
			if ( ! $in_ul ) { $out[] = '<ul>'; $in_ul = true; }
			$out[] = '<li>' . $m[1] . '</li>';
			continue;
		}
		// Ordered list item.
		if ( preg_match( '/^\d+\.\s+(.*)$/', $trimmed, $m ) ) {
			$flush_para(); $close_bq();
			if ( $in_ul ) { $out[] = '</ul>'; $in_ul = false; }
			if ( ! $in_ol ) { $out[] = '<ol>'; $in_ol = true; }
			$out[] = '<li>' . $m[1] . '</li>';
			continue;
		}
		// Blockquote.
		if ( preg_match( '/^&gt;\s?(.*)$/', $trimmed, $m ) ) {
			$flush_para(); $close_lists();
			if ( ! $in_bq ) { $out[] = '<blockquote>'; $in_bq = true; }
			$out[] = $m[1] . '<br>';
			continue;
		}

		// Regular paragraph line.
		$close_lists(); $close_bq();
		$para[] = $line;
	}
	$flush_para(); $close_lists(); $close_bq();

	$html = implode( "\n", $out );

	// Restore code placeholders.
	$html = strtr( $html, $inline );
	$html = strtr( $html, $blocks );

	return $html;
}

// UA 解析与显示

/**
 * Format a comment's stored User-Agent string according to the display setting.
 *
 * @param WP_Comment $comment
 * @return string
 */
function sphotography_format_comment_ua( $comment ) {
	$mode = sphotography_comment_setting( 'comment_ua_display' );
	if ( 'none' === $mode || empty( $comment->comment_agent ) ) {
		return '';
	}
	$parsed = sphotography_parse_ua( $comment->comment_agent );
	$browser  = $parsed['browser'];
	$version  = $parsed['version'];
	$platform = $parsed['platform'];

	switch ( $mode ) {
		case 'browser':
			return $browser;
		case 'browser_ver':
			return trim( $browser . ' ' . $version );
		case 'platform':
			return $platform;
		case 'platform_browser':
			return trim( $platform . ' · ' . $browser, ' ·' );
		case 'platform_browser_ver':
			return trim( $platform . ' · ' . trim( $browser . ' ' . $version ), ' ·' );
	}
	return '';
}

/**
 * Lightweight regex UA parser for common browsers and platforms. Unknown
 * agents fall back to 未知.
 *
 * @param string $ua
 * @return array{browser:string,version:string,platform:string}
 */
function sphotography_parse_ua( $ua ) {
	$unknown  = __( '未知', 'sphotography' );
	$browser  = $unknown;
	$version  = '';
	$platform = $unknown;

	// Platform (order matters).
	if ( preg_match( '/Windows NT 10\.0/', $ua ) ) {
		$platform = 'Windows';
	} elseif ( preg_match( '/Windows NT/', $ua ) ) {
		$platform = 'Windows';
	} elseif ( preg_match( '/Android/', $ua ) ) {
		$platform = 'Android';
	} elseif ( preg_match( '/(iPhone|iPad|iPod)/', $ua ) ) {
		$platform = 'iOS';
	} elseif ( preg_match( '/Mac OS X/', $ua ) ) {
		$platform = 'macOS';
	} elseif ( preg_match( '/(HarmonyOS|OpenHarmony)/', $ua ) ) {
		$platform = 'HarmonyOS';
	} elseif ( preg_match( '/Linux/', $ua ) ) {
		$platform = 'Linux';
	}

	// Browser + version (order matters: specific engines before generic).
	if ( preg_match( '/Edg(?:e|iOS|A)?\/([\d.]+)/', $ua, $m ) ) {
		$browser = 'Edge';
		$version = $m[1];
	} elseif ( preg_match( '/OPR\/([\d.]+)/', $ua, $m ) || preg_match( '/Opera\/([\d.]+)/', $ua, $m ) ) {
		$browser = 'Opera';
		$version = $m[1];
	} elseif ( preg_match( '/(?:MicroMessenger)\/([\d.]+)/', $ua, $m ) ) {
		$browser = 'WeChat';
		$version = $m[1];
	} elseif ( preg_match( '/Firefox\/([\d.]+)/', $ua, $m ) ) {
		$browser = 'Firefox';
		$version = $m[1];
	} elseif ( preg_match( '/Chrome\/([\d.]+)/', $ua, $m ) ) {
		$browser = 'Chrome';
		$version = $m[1];
	} elseif ( preg_match( '/Version\/([\d.]+).*Safari/', $ua, $m ) ) {
		$browser = 'Safari';
		$version = $m[1];
	} elseif ( preg_match( '/Safari\/([\d.]+)/', $ua, $m ) ) {
		$browser = 'Safari';
	}

	// Keep only the major version for a cleaner display.
	if ( $version ) {
		$parts   = explode( '.', $version );
		$version = $parts[0];
	}

	return array(
		'browser'  => $browser,
		'version'  => $version,
		'platform' => $platform,
	);
}

// ============================================================================
// Reply e-mail notifications
// ============================================================================

/**
 * Send a reply notification for a freshly-approved reply, once.
 *
 * @param WP_Comment $comment The reply.
 */
function sphotography_maybe_send_reply_notification( $comment ) {
	if ( ! sphotography_comment_setting( 'comment_mail_notify' ) ) {
		return;
	}
	if ( ! $comment || '1' !== (string) $comment->comment_approved ) {
		return;
	}
	if ( get_comment_meta( $comment->comment_ID, '_sp_notified', true ) ) {
		return; // already sent.
	}

	$reply_to_id = (int) get_comment_meta( $comment->comment_ID, '_sp_reply_to', true );
	if ( ! $reply_to_id ) {
		$reply_to_id = (int) $comment->comment_parent;
	}
	if ( ! $reply_to_id ) {
		return; // top-level comment, nobody to notify.
	}

	$target = get_comment( $reply_to_id );
	if ( ! $target ) {
		return;
	}
	// The parent author must have opted in and have a valid email.
	if ( ! get_comment_meta( $reply_to_id, '_sp_notify', true ) ) {
		return;
	}
	$to = $target->comment_author_email;
	if ( ! $to || ! is_email( $to ) ) {
		return;
	}
	// Don't email people about their own replies.
	if ( strtolower( $to ) === strtolower( $comment->comment_author_email ) ) {
		update_comment_meta( $comment->comment_ID, '_sp_notified', 1 );
		return;
	}

	$post       = get_post( $comment->comment_post_ID );
	$post_title = $post ? $post->post_title : get_bloginfo( 'name' );
	$permalink  = get_permalink( $comment->comment_post_ID ) . '#comment-' . $comment->comment_ID;
	$blog_name  = get_bloginfo( 'name' );

	$unsub = add_query_arg( array(
		'sp_unsub' => $reply_to_id,
		'k'        => sphotography_unsub_key( $reply_to_id ),
	), home_url( '/' ) );

	$subject = sprintf( __( '[%s] 你的评论有了新回复', 'sphotography' ), $blog_name );

	$reply_html = sphotography_render_comment_content( $comment->comment_content );
	$body  = '<div style="font-family:sans-serif;line-height:1.7;color:#333;">';
	$body .= '<p>' . esc_html( $target->comment_author ) . '，你好：</p>';
	$body .= '<p>你在《' . esc_html( $post_title ) . '》下的评论收到了来自 <strong>' . esc_html( $comment->comment_author ) . '</strong> 的回复：</p>';
	$body .= '<blockquote style="margin:12px 0;padding:10px 14px;border-left:3px solid #1abc9c;background:#f6f6f6;">' . $reply_html . '</blockquote>';
	$body .= '<p><a href="' . esc_url( $permalink ) . '" style="color:#1abc9c;">点此查看并回复 →</a></p>';
	$body .= '<hr style="border:none;border-top:1px solid #eee;margin:18px 0;">';
	$body .= '<p style="font-size:12px;color:#999;">如果你不想再收到此评论的回复提醒，请<a href="' . esc_url( $unsub ) . '" style="color:#999;">点此退订</a>。</p>';
	$body .= '</div>';

	$headers = array( 'Content-Type: text/html; charset=UTF-8' );
	wp_mail( $to, $subject, $body, $headers );

	update_comment_meta( $comment->comment_ID, '_sp_notified', 1 );
}

/**
 * Fire notifications when a held comment later becomes approved.
 */
function sphotography_comment_status_transition( $new_status, $old_status, $comment ) {
	if ( 'approved' === $new_status && 'approved' !== $old_status ) {
		sphotography_maybe_send_reply_notification( $comment );
	}
}
add_action( 'transition_comment_status', 'sphotography_comment_status_transition', 10, 3 );

/**
 * HMAC key for the unsubscribe link of a given comment.
 *
 * @param int $comment_id
 * @return string
 */
function sphotography_unsub_key( $comment_id ) {
	return hash_hmac( 'sha256', 'sp_unsub_' . (int) $comment_id, wp_salt( 'auth' ) );
}

/**
 * Handle unsubscribe links: ?sp_unsub=<id>&k=<hmac>.
 */
function sphotography_handle_unsubscribe() {
	if ( ! isset( $_GET['sp_unsub'], $_GET['k'] ) ) {
		return;
	}
	$comment_id = (int) $_GET['sp_unsub'];
	$key        = sanitize_text_field( wp_unslash( $_GET['k'] ) );
	if ( ! $comment_id || ! hash_equals( sphotography_unsub_key( $comment_id ), $key ) ) {
		wp_die( esc_html__( '退订链接无效或已过期。', 'sphotography' ), '', array( 'response' => 403 ) );
	}
	delete_comment_meta( $comment_id, '_sp_notify' );
	wp_die(
		esc_html__( '你已成功退订该评论的回复提醒。', 'sphotography' ),
		esc_html__( '退订成功', 'sphotography' ),
		array( 'response' => 200 )
	);
}
add_action( 'template_redirect', 'sphotography_handle_unsubscribe' );
