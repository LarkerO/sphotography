<?php
// 留言板（复用评论系统，支持随机/分页展示）

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 占位文章管理

/**
 * 查找已存在的留言板占位文章。
 *
 * 优先匹配主题专用 slug 'sphotography-guestbook'；若不存在（历史版本并发/误删重建
 * 可能产生 -2 / -3 后缀的孤儿文章），则复用最早的一篇，避免再次创建造成累积。
 *
 * @return int 占位文章 ID，不存在返回 0。
 */
function sphotography_guestbook_find_holder_post() {
	global $wpdb;

	// 精确 slug 优先。
	$id = (int) $wpdb->get_var(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_name = 'sphotography-guestbook' ORDER BY ID ASC LIMIT 1"
	);
	if ( $id > 0 ) {
		return $id;
	}

	// 兜底：任意 'sphotography-guestbook%' 前缀的历史占位文章（最早一篇）。
	$id = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'post' AND post_name LIKE %s ORDER BY ID ASC LIMIT 1",
		'sphotography-guestbook%'
	) );
	return $id;
}

/**
 * 跨请求原子锁：防止并发请求同时创建占位文章。
 *
 * 利用 options 表 option_name 唯一索引实现原子获取；锁超过 $expire 秒未释放
 * 视为过期（前一个创建进程可能崩溃），带旧时间戳条件更新防止并发抢占同一把过期锁。
 *
 * @param int $expire 锁有效期（秒）。
 * @return bool true 表示拿到锁。
 */
function sphotography_guestbook_acquire_lock( $expire = 30 ) {
	global $wpdb;

	$now = time();
	$inserted = $wpdb->insert(
		$wpdb->options,
		array(
			'option_name'  => 'sphotography_gb_create_lock',
			'option_value' => $now,
			'autoload'     => 'no',
		),
		array( '%s', '%d', '%s' )
	);
	if ( $inserted ) {
		return true;
	}

	// 锁已存在：检查是否过期。
	$stored = (int) $wpdb->get_var( $wpdb->prepare(
		"SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
		'sphotography_gb_create_lock'
	) );
	if ( $stored > 0 && ( $now - $stored ) >= $expire ) {
		$updated = $wpdb->update(
			$wpdb->options,
			array( 'option_value' => $now ),
			array( 'option_name' => 'sphotography_gb_create_lock', 'option_value' => $stored ),
			array( '%d' ),
			array( '%s', '%d' )
		);
		if ( 1 === $updated ) {
			return true;
		}
	}
	return false;
}

/**
 * 释放占位文章创建锁。
 */
function sphotography_guestbook_release_lock() {
	delete_option( 'sphotography_gb_create_lock' );
}

/**
 * 获取留言板占位文章 ID。
 *
 * 默认惰性创建，但已具备幂等保护：
 *  - 按主题专用 slug 复用已存在的占位文章（含历史孤儿），并修复 option 引用；
 *  - 仅当确实不存在时才创建，且由跨请求原子锁保证并发下最多创建一篇。
 *
 * $allow_create=false 时为纯只读（绝不写库），供「仅判断是否为留言板」的场景使用。
 *
 * @param bool $allow_create 是否允许在缺失时自动创建。
 * @return int 占位文章 ID；不可用返回 0。
 */
function sphotography_guestbook_post_id( $allow_create = true ) {
	$post_id = (int) get_option( 'sphotography_guestbook_post' );

	// 已有有效引用，直接返回。
	if ( $post_id > 0 ) {
		$post = get_post( $post_id );
		if ( $post && 'post' === $post->post_type ) {
			return $post_id;
		}
	}

	// 幂等：复用已存在的占位文章（含历史孤儿），并修复 option 引用。
	$existing = sphotography_guestbook_find_holder_post();
	if ( $existing > 0 ) {
		if ( $post_id !== $existing ) {
			update_option( 'sphotography_guestbook_post', $existing );
		}
		return $existing;
	}

	if ( ! $allow_create ) {
		return 0;
	}

	// 跨请求原子锁：并发下只允许一个请求进入创建流程。
	if ( ! sphotography_guestbook_acquire_lock() ) {
		return 0;
	}

	// 锁内二次查重：加锁前可能有其他请求已完成创建。
	$existing = sphotography_guestbook_find_holder_post();
	if ( $existing > 0 ) {
		sphotography_guestbook_release_lock();
		if ( $post_id !== $existing ) {
			update_option( 'sphotography_guestbook_post', $existing );
		}
		return $existing;
	}

	// Create the holder post.
	$new_post_id = wp_insert_post( array(
		'post_title'      => '留言板',
		'post_name'       => 'sphotography-guestbook',
		'post_status'     => 'private',
		'post_type'       => 'post',
		'comment_status'  => 'open',
		'ping_status'     => 'closed',
		'post_content'    => '',
	) );

	sphotography_guestbook_release_lock();

	if ( is_wp_error( $new_post_id ) ) {
		return 0;
	}

	update_option( 'sphotography_guestbook_post', (int) $new_post_id );
	return (int) $new_post_id;
}

/**
 * 显式确保占位文章存在（主题激活 / 后台清理时调用）。
 *
 * @return int 占位文章 ID。
 */
function sphotography_guestbook_ensure_post() {
	return sphotography_guestbook_post_id( true );
}

// 从公开查询中排除留言板占位文章
function sphotography_exclude_guestbook_from_queries( $query ) {
	if ( ! is_admin() && $query->is_main_query() ) {
		$post_id = (int) get_option( 'sphotography_guestbook_post' );
		if ( $post_id > 0 ) {
			$query->set( 'post__not_in', array_merge(
				(array) $query->get( 'post__not_in' ),
				array( $post_id )
			) );
		}
	}
}
add_action( 'pre_get_posts', 'sphotography_exclude_guestbook_from_queries' );

// 留言板设置

/**
 * Get the guestbook configuration: post ID and random display count.
 *
 * @return array
 */
function sphotography_guestbook_config() {
	return array(
		'postId'      => sphotography_guestbook_post_id(),
		'randomCount' => (int) sphotography_guestbook_random_count(),
	);
}

/**
 * Get the random display count setting (default 8, range 1-30).
 *
 * @return int
 */
function sphotography_guestbook_random_count() {
	$value = (int) get_option( 'sphotography_guestbook_random', 8 );
	return min( max( $value, 1 ), 30 );
}

/**
 * Register the guestbook settings admin submenu and save handler.
 * REMOVED: submenu registration moved to main settings (see admin/theme-settings.php render function)
 */
function sphotography_register_guestbook_admin() {
	add_action( 'admin_post_sphotography_save_guestbook', 'sphotography_handle_guestbook_save' );
}
add_action( 'admin_menu', 'sphotography_register_guestbook_admin' );

/**
 * Render the guestbook settings board for the settings page.
 * Returns markup (called from sphotography_render_settings_page in admin/theme-settings.php).
 */
function sphotography_render_guestbook_board() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$random_count = sphotography_guestbook_random_count();

	ob_start();
	?>
	<!-- Guestbook Settings Board (folded into social category) -->
	<div class="sphotography-module" id="sp-mod-guestbook">
		<div class="sphotography-module-header">
			<span class="sphotography-module-icon dashicons dashicons-testimonial"></span>
			<h3><?php _e( '留言板设置', 'sphotography' ); ?></h3>
		</div>
		<div class="sphotography-module-body">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sphotography_save_guestbook">
				<?php wp_nonce_field( 'sphotography_save_guestbook', 'sphotography_guestbook_nonce' ); ?>

				<div class="sphotography-field">
					<label class="sphotography-label" for="sphotography-guestbook-random"><?php _e( '随机展示条数', 'sphotography' ); ?></label>
					<input type="number"
						id="sphotography-guestbook-random"
						name="sphotography_guestbook_random"
						value="<?php echo esc_attr( $random_count ); ?>"
						min="1" max="30" step="1">
					<p class="sphotography-desc"><?php _e( '随机模式下展示的留言条数，范围 1-30。默认 8。', 'sphotography' ); ?></p>
				</div>

				<?php submit_button( __( '保存', 'sphotography' ), 'primary', 'submit', false ); ?>
			</form>

			<?php if ( function_exists( 'sphotography_guestbook_holder_posts' ) ) : ?>
				<?php
				$current_gb   = (int) get_option( 'sphotography_guestbook_post' );
				$orphan_count = 0;
				foreach ( sphotography_guestbook_holder_posts() as $r ) {
					if ( (int) $r->ID !== $current_gb ) {
						$orphan_count++;
					}
				}
				?>
				<?php if ( $orphan_count > 0 ) : ?>
				<hr class="sphotography-field-divider">
				<div class="sphotography-field">
					<label class="sphotography-label"><?php _e( '占位文章维护', 'sphotography' ); ?></label>
					<p class="sphotography-desc">
						<?php
						printf(
							/* translators: 1: 多余的留言板占位文章数量 */
							__( '检测到 %1$d 篇多余的「留言板」占位文章（由早期版本重复创建产生）。一键清理会删除其中无留言的空文章；若某篇上仍有历史留言，则会自动保留并重新启用，留言数据不会丢失。', 'sphotography' ),
							$orphan_count
						);
						?>
					</p>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
						onsubmit="return confirm('<?php echo esc_js( __( '确定清理多余的「留言板」占位文章吗？此操作不可恢复。', 'sphotography' ) ); ?>');">
						<input type="hidden" name="action" value="sphotography_cleanup_guestbook">
						<?php wp_nonce_field( 'sphotography_cleanup_guestbook', 'sphotography_cleanup_guestbook_nonce' ); ?>
						<button type="submit" class="button button-secondary"><?php _e( '清理多余占位文章', 'sphotography' ); ?></button>
					</form>
				</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Render the guestbook settings admin page (legacy, no longer used but kept for compatibility).
 */
function sphotography_render_guestbook_admin() {
	// Legacy function - settings now folded into main settings page
	wp_safe_redirect( admin_url( 'admin.php?page=sphotography-settings#sp-cat-social' ) );
	exit;
}

/**
 * Handle guestbook settings save - redirects to main settings page.
 */
function sphotography_handle_guestbook_save() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '权限不足。', 'sphotography' ) );
	}

	if ( ! isset( $_POST['sphotography_guestbook_nonce'] ) ||
		 ! wp_verify_nonce( $_POST['sphotography_guestbook_nonce'], 'sphotography_save_guestbook' ) ) {
		wp_die( esc_html__( '安全验证失败。', 'sphotography' ) );
	}

	$random_count = (int) $_POST['sphotography_guestbook_random'];
	$random_count = min( max( $random_count, 1 ), 30 );
	update_option( 'sphotography_guestbook_random', $random_count );

	wp_redirect( add_query_arg( 'page', 'sphotography-settings', admin_url( 'admin.php' ) ) . '#sp-cat-social' );
	exit;
}

// REST 路由

// 注册 REST 路由
function sphotography_register_guestbook_route() {
	register_rest_route( 'sphotography/v1', '/guestbook', array(
		'methods'             => WP_REST_Server::READABLE,
		'callback'            => 'sphotography_rest_guestbook',
		'permission_callback' => '__return_true',
	) );
}
add_action( 'rest_api_init', 'sphotography_register_guestbook_route' );

// GET /sphotography/v1/guestbook
function sphotography_rest_guestbook( WP_REST_Request $request ) {
	$post_id = sphotography_guestbook_post_id();
	if ( ! $post_id ) {
		return new WP_Error( 'sp_gb_no_post', __( '留言板不存在。', 'sphotography' ), array( 'status' => 500 ) );
	}

	$mode  = $request->get_param( 'mode' );
	$page  = max( 1, (int) $request->get_param( 'page' ) );
	$sort  = $request->get_param( 'sort' );
	$order = $request->get_param( 'order' );

	$mode  = ( 'all' === $mode ) ? 'all' : 'random';
	$sort  = ( 'likes' === $sort ) ? 'likes' : 'time';
	$order = ( 'desc' === $order ) ? 'desc' : 'asc';

	$per = SPHOTOGRAPHY_COMMENTS_PER_PAGE;

	// Fetch all approved top-level comments on the guestbook post.
	$tops = get_comments( array(
		'post_id' => $post_id,
		'parent'  => 0,
		'status'  => 'approve',
		'type'    => 'comment',
		'orderby' => 'comment_date_gmt',
		'order'   => 'ASC',
	) );

	// Filter visible (respecting private threads).
	$visible = array();
	foreach ( $tops as $c ) {
		if ( sphotography_comment_visible( $c ) ) {
			$visible[] = $c;
		}
	}

	// Split pinned vs normal. Pinned float to top, newest pin first.
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

	// Apply sorting to normal (non-pinned) list.
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

	// Determine which comments to return.
	if ( 'random' === $mode ) {
		// Random mode: shuffle normal list, take N, prepend pinned.
		$random_count = (int) sphotography_guestbook_random_count();
		shuffle( $normal );
		$normal_slice = array_slice( $normal, 0, $random_count );
		$slice = array_merge( $pinned, $normal_slice );
		$has_more = false;
	} else {
		// All mode: paginate, pinned first on page 1.
		if ( 1 === $page ) {
			$slice = array_merge( $pinned, array_slice( $normal, 0, $per ) );
			$has_more = count( $normal ) > $per;
		} else {
			$offset = ( $page - 1 ) * $per;
			$slice = array_slice( $normal, $offset, $per );
			$has_more = count( $normal ) > ( $offset + $per );
		}
	}

	// Prepare items with children (reuse comment system's structure).
	$items = array();
	foreach ( $slice as $c ) {
		$node = sphotography_prepare_comment( $c );
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
		'total'    => count( $visible ),
		'has_more' => $has_more,
		'mode'     => $mode,
	), 200 );
}

// ============================================================================
// 多余占位文章清理（v1.5.02：修复早期版本并发重复创建产生的孤儿文章）
// ============================================================================

/**
 * 列出全部留言板占位文章（按主题专用 slug 前缀识别，含历史后缀 -2 / -3）。
 *
 * @return array 每项含 ID / post_title / post_name / post_status / post_date。
 */
function sphotography_guestbook_holder_posts() {
	global $wpdb;

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT ID, post_title, post_name, post_status, post_date
		 FROM {$wpdb->posts}
		 WHERE post_type = 'post' AND post_name LIKE %s
		 ORDER BY ID ASC",
		'sphotography-guestbook%'
	) );

	return is_array( $rows ) ? $rows : array();
}

/**
 * 清理多余占位文章：
 *  - 仅删除「当前 option 未引用」且「无任何留言」的空占位文章；
 *  - 若某篇多余占位文章上仍有历史留言（曾作为留言板被使用过），绝不删除，
 *    而是将其重新采纳为当前引用，避免留言数据丢失；
 *  - 清理后重新确保存在一篇有效占位文章。
 */
function sphotography_handle_guestbook_cleanup() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( '权限不足。', 'sphotography' ) );
	}

	check_admin_referer( 'sphotography_cleanup_guestbook', 'sphotography_cleanup_guestbook_nonce' );

	$current = (int) get_option( 'sphotography_guestbook_post' );
	$deleted = 0;

	foreach ( sphotography_guestbook_holder_posts() as $row ) {
		$id = (int) $row->ID;
		if ( $id === $current ) {
			continue; // 保留当前引用的一篇。
		}

		// 有留言的占位文章（曾承载留言数据）不删，重新采纳为当前引用。
		if ( get_comments_number( $id ) > 0 ) {
			update_option( 'sphotography_guestbook_post', $id );
			$current = $id;
			continue;
		}

		if ( wp_delete_post( $id, true ) ) {
			$deleted++;
		}
	}

	// 若当前引用已失效（文章被删），重置 option，由 ensure 重新确立一篇。
	$post = ( $current > 0 ) ? get_post( $current ) : null;
	if ( ! $post || 'post' !== $post->post_type ) {
		update_option( 'sphotography_guestbook_post', 0 );
	}

	// 确保存在有效占位文章（幂等：优先复用现存文章，绝不重复创建）。
	sphotography_guestbook_ensure_post();

	wp_safe_redirect( add_query_arg(
		array(
			'page'        => 'sphotography-settings',
			'sp-gb-clean' => $deleted,
		),
		admin_url( 'admin.php' )
	) . '#sp-cat-social' );
	exit;
}
add_action( 'admin_post_sphotography_cleanup_guestbook', 'sphotography_handle_guestbook_cleanup' );

/**
 * 清理结果提示（设置页顶部）。
 */
function sphotography_guestbook_cleanup_notice() {
	if ( ! isset( $_GET['sp-gb-clean'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$count = (int) $_GET['sp-gb-clean'];
	if ( $count > 0 ) {
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( sprintf(
			/* translators: 1: 已清理的占位文章数量 */
			__( '已清理 %1$d 篇多余的「留言板」占位文章。', 'sphotography' ),
			$count
		) ) . '</p></div>';
	} else {
		echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( '没有需要清理的留言板占位文章。', 'sphotography' ) . '</p></div>';
	}
}
add_action( 'admin_notices', 'sphotography_guestbook_cleanup_notice' );

