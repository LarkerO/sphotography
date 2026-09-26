<?php
if (!defined('ABSPATH')) exit;
function sphotography_get_friend_links() { return get_option('sphotography_friend_links', array()); }
function sphotography_sort_friend_links(&$links) {
    usort($links, function ($a, $b) {
        $pin = (int) !empty($b['pinned']) - (int) !empty($a['pinned']);
        if ($pin) return $pin;
        $order = ($a['sort_order'] ?? PHP_INT_MAX) <=> ($b['sort_order'] ?? PHP_INT_MAX);
        if ($order) return $order;
        $added = (int) ($a['added'] ?? 0) <=> (int) ($b['added'] ?? 0);
        return $added ?: ((int) $a['id'] <=> (int) $b['id']);
    });
}
// Separate AJAX actions avoid nesting new forms inside the settings form.
add_action('wp_ajax_sphotography_friend_edit', 'sphotography_ajax_friend_edit');
function sphotography_ajax_friend_edit() {
    if (!current_user_can('manage_options')) wp_send_json_error(array('message' => '权限不足。'), 403);
    check_ajax_referer('sphotography_friend_links', 'nonce');
    $links = sphotography_get_friend_links();
    $action = sanitize_key($_POST['operation'] ?? '');
    if ('thumbnail' === $action) {
        $id = absint($_POST['id'] ?? 0);
        $thumb = absint($_POST['thumb_id'] ?? 0);
        if (!$thumb || !wp_attachment_is_image($thumb) || !current_user_can('upload_files')) {
            wp_send_json_error(array('message' => '请选择媒体库中的有效图片。'), 400);
        }
        $found = false;
        foreach ($links as &$link) {
            if ((int) $link['id'] !== $id) continue;
            $link['thumb_id'] = $thumb;
            $link['thumb_attempts'] = 0;
            $found = true;
        }
        unset($link);
        if (!$found) wp_send_json_error(array('message' => '友链已不存在，请刷新页面。'), 404);
        sphotography_update_friend_links($links);
        wp_send_json_success(array('url' => wp_get_attachment_image_url($thumb, 'thumbnail')));
    } elseif ('reorder' === $action) {
        $ids = isset($_POST['ids']) && is_array($_POST['ids']) ? array_map('absint', $_POST['ids']) : array();
        $existing = array_map('intval', array_column($links, 'id'));
        $submitted = $ids;
        sort($existing); sort($submitted);
        if ($existing !== $submitted) wp_send_json_error(array('message' => '友链列表已变化，请刷新页面后重试。'), 409);
        $positions = array_flip($ids);
        foreach ($links as &$link) {
            $link['sort_order'] = $positions[(int) $link['id']];
            // A manual order supersedes the previous pin order, including across groups.
            $link['pinned'] = 0;
        }
        unset($link);
        sphotography_update_friend_links($links);
        wp_send_json_success();
    }
    wp_send_json_error(array('message' => '未知操作。'), 400);
}
function sphotography_get_friend_link_applications() { return get_option('sphotography_friend_link_apps', array()); }
function sphotography_get_friend_link_notify() { return get_option('sphotography_friend_link_notify', '1'); }
function sphotography_update_friend_links($links) { update_option('sphotography_friend_links', $links); }
function sphotography_update_friend_link_applications($apps) { update_option('sphotography_friend_link_apps', $apps); }
function sphotography_register_friend_links_page() {
	// REMOVED: add_submenu_page registration moved to main settings menu badge (see functions.php)
	// Render function below is now called from sphotography_render_settings_page in admin/theme-settings.php
}
// REMOVED: add_action( 'admin_menu', 'sphotography_register_friend_links_page' ) - now managed in functions.php
// Schedule the async title + thumbnail fetch for one friend link.
function sphotography_schedule_friend_meta($id) {
	$id = (int) $id;
	if (!wp_next_scheduled('sphotography_fetch_friend_meta', array($id))) {
		wp_schedule_single_event(time() + 5, 'sphotography_fetch_friend_meta', array($id));
	}
}

// 处理友链页面 POST 操作
function sphotography_friend_links_handle_post() {
	if (empty($_POST['sp_fl_action'])) return array('ok' => true);
	if (!current_user_can('manage_options')) wp_die(esc_html__('权限不足。', 'sphotography'));
	check_admin_referer('sphotography_friend_links');
	$action = sanitize_key($_POST['sp_fl_action']);
	$links = sphotography_get_friend_links();
	$apps = sphotography_get_friend_link_applications();

	if ('add' === $action) {
		// v1.4.0: the URL is no longer `required` (browser validation moved to
		// the server). Format-check first (fast, no network); then run an 8s
		// connect-test; only add the link on success. On any failure, return
		// the form state so the caller can stash it in a transient and the
		// next page load can pre-fill the form + show a red notice.
		$raw_url   = trim( (string) wp_unslash( $_POST['fl_url'] ?? '' ) );
		$name      = sanitize_text_field( wp_unslash( $_POST['fl_name'] ?? '' ) );
		$thumb_id  = (int) ( $_POST['fl_thumb_id'] ?? 0 );
		$pinned    = empty( $_POST['fl_pinned'] ) ? 0 : 1;

		if ( $raw_url === '' || ! preg_match( '#^https?://#i', $raw_url ) ) {
			return array(
				'ok'      => false,
				'kind'    => 'format',
				'message' => __( '请填写正确的网址（需以 http:// 或 https:// 开头）。', 'sphotography' ),
				'form'    => array( 'fl_url' => $raw_url, 'fl_name' => $name, 'fl_pinned' => $pinned, 'fl_thumb_id' => $thumb_id ),
			);
		}
		$url = esc_url_raw( $raw_url );

		// 8s timeout — long enough to survive slow VPS sites, short enough
		// that the admin doesn't get frustrated waiting.
		$response = wp_remote_get( $url, array(
			'timeout'     => 8,
			'redirection' => 5,
			'user-agent'  => 'Sphotography-Link-Checker/1.0 (+' . home_url( '/' ) . ')',
		) );
		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'kind'    => 'connect',
				'message' => sprintf( __( '无法连接到该网址：%s', 'sphotography' ), $response->get_error_message() ),
				'form'    => array( 'fl_url' => $url, 'fl_name' => $name, 'fl_pinned' => $pinned, 'fl_thumb_id' => $thumb_id ),
			);
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 400 ) {
			return array(
				'ok'      => false,
				'kind'    => 'connect',
				'message' => sprintf( __( '该网址返回了错误状态码：HTTP %d', 'sphotography' ), $code ),
				'form'    => array( 'fl_url' => $url, 'fl_name' => $name, 'fl_pinned' => $pinned, 'fl_thumb_id' => $thumb_id ),
			);
		}

		$new_id = ( ! empty( $links ) ? max( array_column( $links, 'id' ) ) : 0 ) + 1;
		$links[] = array(
			'id'       => $new_id,
			'url'      => $url,
			'name'     => $name,
			'thumb_id' => $thumb_id,
			'pinned'   => $pinned,
			'added'    => time(),
		);
		sphotography_update_friend_links( $links );
		sphotography_schedule_friend_meta( $new_id );

		return array(
			'ok'      => true,
			'kind'    => 'added',
			'message' => __( '友链已添加。', 'sphotography' ),
		);
	} elseif ('delete' === $action) {
		$id = (int) ($_POST['fl_id'] ?? 0);
		$links = array_values(array_filter($links, function ($l) use ($id) { return (int) $l['id'] !== $id; }));
		sphotography_update_friend_links($links);
	} elseif ('toggle_pin' === $action) {
		$id = (int) ($_POST['fl_id'] ?? 0);
		foreach ($links as &$l) { if ((int) $l['id'] === $id) { $l['pinned'] = empty($l['pinned']) ? 1 : 0; } }
		unset($l);
		sphotography_update_friend_links($links);
	} elseif ('refetch' === $action) {
		$id = (int) ($_POST['fl_id'] ?? 0);
		foreach ($links as &$l) { if ((int) $l['id'] === $id) { $l['thumb_id'] = 0; $l['thumb_attempts'] = 0; } }
		unset($l);
		sphotography_update_friend_links($links);
		sphotography_schedule_friend_meta($id);
	} elseif ('approve_app' === $action) {
		$id = (int) ($_POST['app_id'] ?? 0);
		foreach ($apps as $app) {
			if ((int) $app['id'] === $id) {
				$new_id = (!empty($links) ? max(array_column($links, 'id')) : 0) + 1;
				$links[] = array('id' => $new_id, 'url' => $app['url'], 'name' => $app['name'], 'thumb_id' => 0, 'pinned' => 0, 'added' => time());
				sphotography_update_friend_links($links);
				sphotography_schedule_friend_meta($new_id);
				break;
			}
		}
		$apps = array_values(array_filter($apps, function ($a) use ($id) { return (int) $a['id'] !== $id; }));
		sphotography_update_friend_link_applications($apps);
	} elseif ('ignore_app' === $action) {
		$id = (int) ($_POST['app_id'] ?? 0);
		$apps = array_values(array_filter($apps, function ($a) use ($id) { return (int) $a['id'] !== $id; }));
		sphotography_update_friend_link_applications($apps);
	} elseif ('save_notify' === $action) {
		update_option('sphotography_friend_link_notify', empty($_POST['fl_notify']) ? '0' : '1');
	}
	return array('ok' => true);
}

// 渲染友链管理面板
function sphotography_render_friend_links_board() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	$links = sphotography_get_friend_links();
	sphotography_sort_friend_links($links);
	$apps = sphotography_get_friend_link_applications();
	$notify = sphotography_get_friend_link_notify();
	// v1.4.0: stashed form values from a failed add (format error or connect
	// failure) so the user doesn't have to retype after a backend reject.
	$form_state = sphotography_get_fl_form_state();
	$fl_url_value   = $form_state ? $form_state['fl_url'] : '';
	$fl_name_value  = $form_state ? $form_state['fl_name'] : '';
	$fl_pinned_chk  = $form_state ? $form_state['fl_pinned'] : false;
	$fl_thumb_value = $form_state ? $form_state['fl_thumb_id'] : 0;
	$fl_thumb_src   = '';
	if ( $fl_thumb_value ) {
		$src = wp_get_attachment_image_src( $fl_thumb_value, 'medium' );
		if ( $src ) { $fl_thumb_src = $src[0]; }
	}

	ob_start();
	?>
	<!-- Friend-Links Management Board (folded into social category) -->
	<div class="sphotography-module" id="sp-mod-friend-links">
		<div class="sphotography-module-header">
			<span class="sphotography-module-icon dashicons dashicons-link"></span>
			<h3><?php esc_html_e( '友链管理', 'sphotography' ); ?></h3>
		</div>
		<div class="sphotography-module-body">

			<?php // v1.4.2: 「添加友链」改为按钮 + 居中弹窗（modal）。表单本体由
			// sphotography_render_friend_links_modal() 渲染在设置大表单之外，彻底消除
			// 此前「表单嵌套在设置大表单内」导致点保存误触发 HTML5 校验（请填写此字段）。 ?>
			<p style="margin:0 0 20px 0;">
				<button type="button" class="button button-primary" id="sp-fl-add-open" style="display:inline-flex;align-items:center;gap:6px;">
					<span class="dashicons dashicons-plus-alt2"></span><?php esc_html_e( '添加友链', 'sphotography' ); ?>
				</button>
			</p>

			<h4 style="margin:20px 0 16px 0;font-size:0.9rem;font-weight:600;color:var(--sp-text);"><?php esc_html_e( '现有友链', 'sphotography' ); ?></h4>
			<?php if ( empty( $links ) ) : ?>
				<p style="color:var(--sp-text-muted);"><?php esc_html_e( '还没有友链。', 'sphotography' ); ?></p>
			<?php else : ?>
				<p class="description">拖住左侧手柄上下排序，松开自动保存；拖拽后以手动顺序为准并清除旧置顶。仍可使用置顶按钮。</p>
                <p id="sp-fl-status" role="status" aria-live="polite"></p>
                <table id="sp-fl-table" class="widefat striped" style="margin-bottom:20px;">
					<thead><tr>
						<th>排序</th><th><?php esc_html_e( '缩略图', 'sphotography' ); ?></th>
						<th><?php esc_html_e( '名称', 'sphotography' ); ?></th>
						<th><?php esc_html_e( '网址', 'sphotography' ); ?></th>
						<th><?php esc_html_e( '操作', 'sphotography' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $links as $l ) :
						$thumb = $l['thumb_id'] ? wp_get_attachment_image_src( $l['thumb_id'], 'thumbnail' ) : false;
					?>
						<tr data-id="<?php echo (int) $l['id']; ?>">
                            <td><span class="sp-fl-drag dashicons dashicons-menu" style="cursor:grab" title="拖拽排序"></span></td>
                            <td class="sp-fl-thumb"><?php if ( $thumb ) : ?><img src="<?php echo esc_url( $thumb[0] ); ?>" style="width:60px;height:45px;object-fit:cover;border-radius:4px;"><?php else : ?>—<?php endif; ?></td>
							<td><?php echo esc_html( $l['name'] ? $l['name'] : '（待抓取）' ); ?><?php echo ! empty( $l['pinned'] ) ? ' <span class="dashicons dashicons-sticky" title="置顶"></span>' : ''; ?></td>
							<td><a href="<?php echo esc_url( $l['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $l['url'] ); ?></a></td>
							<td>
								<button type="button" class="button button-small sp-fl-change-thumb">更换缩略图</button>
                                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"><?php wp_nonce_field( 'sphotography_friend_links' ); ?><input type="hidden" name="action" value="sphotography_friend_links_action"><input type="hidden" name="sp_fl_action" value="toggle_pin"><input type="hidden" name="fl_id" value="<?php echo (int) $l['id']; ?>"><button class="button button-small"><?php echo ! empty( $l['pinned'] ) ? esc_html__( '取消置顶', 'sphotography' ) : esc_html__( '置顶', 'sphotography' ); ?></button></form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"><?php wp_nonce_field( 'sphotography_friend_links' ); ?><input type="hidden" name="action" value="sphotography_friend_links_action"><input type="hidden" name="sp_fl_action" value="refetch"><input type="hidden" name="fl_id" value="<?php echo (int) $l['id']; ?>"><button class="button button-small"><?php esc_html_e( '重新获取缩略图', 'sphotography' ); ?></button></form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline" onsubmit="return confirm('确定删除此友链？');"><?php wp_nonce_field( 'sphotography_friend_links' ); ?><input type="hidden" name="action" value="sphotography_friend_links_action"><input type="hidden" name="sp_fl_action" value="delete"><input type="hidden" name="fl_id" value="<?php echo (int) $l['id']; ?>"><button class="button button-small button-link-delete"><?php esc_html_e( '删除', 'sphotography' ); ?></button></form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h4 style="margin:20px 0 16px 0;font-size:0.9rem;font-weight:600;color:var(--sp-text);"><?php esc_html_e( '待处理申请', 'sphotography' ); ?> <?php echo ! empty( $apps ) ? '<span class="awaiting-mod count-' . count( $apps ) . '"><span class="pending-count">' . number_format_i18n( count( $apps ) ) . '</span></span>' : ''; ?></h4>
			<?php if ( empty( $apps ) ) : ?>
				<p style="color:var(--sp-text-muted);"><?php esc_html_e( '暂无待处理申请。', 'sphotography' ); ?></p>
			<?php else : ?>
				<table class="widefat striped" style="margin-bottom:20px;">
					<thead><tr>
						<th><?php esc_html_e( '站点', 'sphotography' ); ?></th>
						<th><?php esc_html_e( '网址', 'sphotography' ); ?></th>
						<th><?php esc_html_e( '邮箱', 'sphotography' ); ?></th>
						<th><?php esc_html_e( '留言', 'sphotography' ); ?></th>
						<th><?php esc_html_e( '操作', 'sphotography' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $apps as $a ) : ?>
						<tr>
							<td><?php echo esc_html( $a['name'] ); ?></td>
							<td><a href="<?php echo esc_url( $a['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( $a['url'] ); ?></a></td>
							<td><?php echo esc_html( $a['email'] ); ?></td>
							<td><?php echo esc_html( $a['message'] ); ?></td>
							<td>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"><?php wp_nonce_field( 'sphotography_friend_links' ); ?><input type="hidden" name="action" value="sphotography_friend_links_action"><input type="hidden" name="sp_fl_action" value="approve_app"><input type="hidden" name="app_id" value="<?php echo (int) $a['id']; ?>"><button class="button button-primary button-small"><?php esc_html_e( '通过', 'sphotography' ); ?></button></form>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline"><?php wp_nonce_field( 'sphotography_friend_links' ); ?><input type="hidden" name="action" value="sphotography_friend_links_action"><input type="hidden" name="sp_fl_action" value="ignore_app"><input type="hidden" name="app_id" value="<?php echo (int) $a['id']; ?>"><button class="button button-small"><?php esc_html_e( '忽略', 'sphotography' ); ?></button></form>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<h4 style="margin:20px 0 16px 0;font-size:0.9rem;font-weight:600;color:var(--sp-text);"><?php esc_html_e( '通知设置', 'sphotography' ); ?></h4>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sphotography_friend_links' ); ?>
				<input type="hidden" name="action" value="sphotography_friend_links_action">
				<input type="hidden" name="sp_fl_action" value="save_notify">
				<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="fl_notify" value="1" <?php checked( $notify, '1' ); ?>> <?php esc_html_e( '收到新友链申请时通过邮件通知管理员', 'sphotography' ); ?></label>
				<?php submit_button( __( '保存', 'sphotography' ), 'primary', 'submit', false, array( 'style' => 'margin-top:8px;' ) ); ?>
			</form>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

// 添加友链弹窗
function sphotography_render_friend_links_modal() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return '';
	}

	// 复用「添加失败」后暂存的表单值（格式错误 / 连通性测试失败），
	// 使得下次加载时预填，用户无需重填。存在暂存时自动弹开弹窗。
	$form_state     = sphotography_get_fl_form_state();
	$fl_url_value   = $form_state ? $form_state['fl_url'] : '';
	$fl_name_value  = $form_state ? $form_state['fl_name'] : '';
	$fl_pinned_chk  = $form_state ? $form_state['fl_pinned'] : false;
	$fl_thumb_value = $form_state ? $form_state['fl_thumb_id'] : 0;
	$fl_thumb_src   = '';
	if ( $fl_thumb_value ) {
		$src = wp_get_attachment_image_src( $fl_thumb_value, 'medium' );
		if ( $src ) { $fl_thumb_src = $src[0]; }
	}
	$auto_open = $form_state ? '1' : '0';

	ob_start();
	?>
	<div class="sp-fl-modal-overlay" id="sp-fl-modal" data-auto-open="<?php echo esc_attr( $auto_open ); ?>" hidden>
		<div class="sp-fl-modal" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( '添加友链', 'sphotography' ); ?>">
			<button type="button" class="sp-fl-modal-close" id="sp-fl-modal-close" aria-label="<?php esc_attr_e( '关闭', 'sphotography' ); ?>">&times;</button>
			<h3 class="sp-fl-modal-title"><?php esc_html_e( '添加友链', 'sphotography' ); ?></h3>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sphotography_friend_links' ); ?>
				<input type="hidden" name="action" value="sphotography_friend_links_action">
				<input type="hidden" name="sp_fl_action" value="add">
				<input type="hidden" name="fl_thumb_id" id="fl_thumb_id" value="<?php echo esc_attr( $fl_thumb_value ); ?>">

				<div style="display:grid;gap:12px;">
					<div>
						<label class="sphotography-label" for="fl_url"><?php esc_html_e( '网址', 'sphotography' ); ?></label>
						<input type="url" name="fl_url" id="fl_url" class="regular-text" placeholder="https://example.com" value="<?php echo esc_attr( $fl_url_value ); ?>" style="width:100%;max-width:none;padding:8px 12px;border-radius:8px;border:1px solid var(--sp-border);background:var(--sp-surface-2);color:var(--sp-text);">
					</div>
					<div>
						<label class="sphotography-label" for="fl_name"><?php esc_html_e( '站点名称', 'sphotography' ); ?></label>
						<input type="text" name="fl_name" id="fl_name" class="regular-text" placeholder="<?php esc_attr_e( '留空则自动获取网站标题', 'sphotography' ); ?>" value="<?php echo esc_attr( $fl_name_value ); ?>" style="width:100%;max-width:none;padding:8px 12px;border-radius:8px;border:1px solid var(--sp-border);background:var(--sp-surface-2);color:var(--sp-text);">
					</div>
					<div>
						<label class="sphotography-label"><?php esc_html_e( '缩略图', 'sphotography' ); ?></label>
						<img id="fl_thumb_preview" src="<?php echo esc_url( $fl_thumb_src ); ?>" style="max-width:180px;max-height:120px;<?php echo $fl_thumb_src ? '' : 'display:none;'; ?>border-radius:6px;margin-bottom:8px;">
						<p style="margin:0 0 8px 0;">
							<button type="button" class="button" id="fl_thumb_pick"><?php esc_html_e( '选择图片', 'sphotography' ); ?></button>
							<button type="button" class="button" id="fl_thumb_clear"><?php esc_html_e( '移除', 'sphotography' ); ?></button>
						</p>
						<p class="sphotography-desc"><?php esc_html_e( '留空则在保存后自动抓取网站主页截图。', 'sphotography' ); ?></p>
					</div>
					<div>
						<label style="display:flex;align-items:center;gap:8px;"><input type="checkbox" name="fl_pinned" value="1" <?php checked( $fl_pinned_chk ); ?>> <?php esc_html_e( '置顶显示在最前', 'sphotography' ); ?></label>
					</div>
				</div>

				<div class="sp-fl-modal-actions">
					<button type="button" class="button" id="sp-fl-modal-cancel"><?php esc_html_e( '取消', 'sphotography' ); ?></button>
					<?php submit_button( __( '添加友链', 'sphotography' ), 'primary', 'submit', false ); ?>
				</div>
			</form>
		</div>
	</div>

	<script>
	(function(){
		var overlay=document.getElementById('sp-fl-modal');
		if(!overlay) return;
		var openBtn=document.getElementById('sp-fl-add-open'),
		    closeBtn=document.getElementById('sp-fl-modal-close'),
		    cancelBtn=document.getElementById('sp-fl-modal-cancel');
		function open(){ overlay.hidden=false; document.body.classList.add('sp-fl-modal-open'); var u=document.getElementById('fl_url'); if(u) u.focus(); }
		function close(){ overlay.hidden=true; document.body.classList.remove('sp-fl-modal-open'); }
		if(openBtn) openBtn.addEventListener('click',open);
		if(closeBtn) closeBtn.addEventListener('click',close);
		if(cancelBtn) cancelBtn.addEventListener('click',close);
		// 点遮罩空白处关闭；点弹窗内容不关闭。
		overlay.addEventListener('click',function(e){ if(e.target===overlay) close(); });
		document.addEventListener('keydown',function(e){ if(e.key==='Escape' && !overlay.hidden) close(); });
		// 添加失败后（有暂存表单值）自动弹开，方便用户重试。
		if(overlay.getAttribute('data-auto-open')==='1') open();

		// 缩略图选择（媒体库）。
		var pick=document.getElementById('fl_thumb_pick'),clear=document.getElementById('fl_thumb_clear'),idEl=document.getElementById('fl_thumb_id'),prev=document.getElementById('fl_thumb_preview'),frame;
		if(pick){pick.addEventListener('click',function(e){e.preventDefault();if(frame){frame.open();return;}frame=wp.media({title:'选择缩略图',multiple:false});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();idEl.value=a.id;prev.src=a.url;prev.style.display='block';});frame.open();});}
		if(clear){clear.addEventListener('click',function(e){e.preventDefault();idEl.value='0';prev.src='';prev.style.display='none';});}
	})();
	</script>
	<?php
	return ob_get_clean();
}

// Handle friend-links form submissions (admin_post action redirects back to settings)
function sphotography_handle_friend_links_actions() {
	if ( empty( $_POST['sp_fl_action'] ) ) return;
	if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( '权限不足。', 'sphotography' ) );
	check_admin_referer( 'sphotography_friend_links' );

	$result = sphotography_friend_links_handle_post();

	$redirect = admin_url( 'admin.php?page=sphotography-settings' ) . '#sp-cat-social';
	$status   = ! empty( $result['ok'] ) ? 'ok' : 'error';
	$msg      = isset( $result['message'] ) ? $result['message'] : '';
	$kind     = isset( $result['kind'] ) ? $result['kind'] : '';

	// On failure, stash the form values in a per-user transient so the next
	// page load can pre-fill the form. On success, drop any stale transient.
	$user_id = get_current_user_id();
	if ( ! empty( $result['ok'] ) ) {
		delete_transient( 'sphotography_fl_form_' . $user_id );
	} elseif ( ! empty( $result['form'] ) ) {
		set_transient( 'sphotography_fl_form_' . $user_id, $result['form'], MINUTE_IN_SECONDS );
	}

	$redirect = add_query_arg( array(
		'sp_fl_status' => $status,
		'sp_fl_msg'    => rawurlencode( $msg ),
		'sp_fl_kind'   => $kind,
	), $redirect );

	wp_redirect( $redirect );
	exit;
}
add_action( 'admin_post_sphotography_friend_links_action', 'sphotography_handle_friend_links_actions' );

// 友链添加结果通知
function sphotography_fl_add_result_notice() {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || $screen->id !== 'toplevel_page_sphotography-settings' ) {
		return;
	}
	if ( empty( $_GET['sp_fl_status'] ) ) {
		return;
	}
	$status = sanitize_key( $_GET['sp_fl_status'] );
	$msg    = isset( $_GET['sp_fl_msg'] ) ? rawurldecode( wp_unslash( $_GET['sp_fl_msg'] ) ) : '';
	$kind   = isset( $_GET['sp_fl_kind'] ) ? sanitize_key( $_GET['sp_fl_kind'] ) : '';
	if ( $msg === '' ) {
		return;
	}
	$class = $status === 'ok' ? 'notice-success' : 'notice-error';
	$retry = $status !== 'ok' ? ' <button type="button" class="button button-small" onclick="document.getElementById(\'fl_url\').focus();window.scrollTo(0,document.getElementById(\'sp-mod-friend-links\').offsetTop-46);">' . esc_html__( '重试', 'sphotography' ) . '</button>' : '';
	echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible" data-sp-fl-notice data-sp-fl-kind="' . esc_attr( $kind ) . '"><p>' . esc_html( $msg ) . $retry . '</p></div>';
}
add_action( 'admin_notices', 'sphotography_fl_add_result_notice' );

// 获取暂存的表单值（URL/名称/置顶/缩略图）
function sphotography_get_fl_form_state() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return null;
	}
	$state = get_transient( 'sphotography_fl_form_' . $user_id );
	if ( ! is_array( $state ) ) {
		return null;
	}
	return array(
		'fl_url'      => isset( $state['fl_url'] ) ? (string) $state['fl_url'] : '',
		'fl_name'     => isset( $state['fl_name'] ) ? (string) $state['fl_name'] : '',
		'fl_pinned'   => ! empty( $state['fl_pinned'] ),
		'fl_thumb_id' => isset( $state['fl_thumb_id'] ) ? (int) $state['fl_thumb_id'] : 0,
	);
}

function sphotography_friend_links_page() {
	wp_safe_redirect( admin_url( 'admin.php?page=sphotography-settings#sp-cat-social' ) );
	exit;
}

function sphotography_register_friend_links_routes() {
	$ns = 'sphotography/v1';
	register_rest_route($ns, '/friend-links', array('methods' => WP_REST_Server::READABLE, 'callback' => 'sphotography_rest_list_friend_links', 'permission_callback' => '__return_true'));
	register_rest_route($ns, '/friend-links/apply', array('methods' => WP_REST_Server::CREATABLE, 'callback' => 'sphotography_rest_apply_friend_link', 'permission_callback' => '__return_true'));
}
add_action('rest_api_init', 'sphotography_register_friend_links_routes');
function sphotography_rest_list_friend_links($request) {
	$links = sphotography_get_friend_links();
	sphotography_sort_friend_links($links);
	$items = array();
	foreach ($links as $link) {
		if (empty($link['url'])) continue;
		$thumb_url = '';
		if ($link['thumb_id']) {
			$src = wp_get_attachment_image_src($link['thumb_id'], 'full');
			if ($src) $thumb_url = $src[0];
		}
		$items[] = array('name' => $link['name'] ? (string) $link['name'] : '', 'url' => (string) $link['url'], 'thumb' => $thumb_url);
	}
	return new WP_REST_Response(array('items' => $items), 200);
}
function sphotography_rest_apply_friend_link($request) {
	$email = sanitize_email(wp_unslash($request->get_param('email') ?? ''));
	$url = esc_url_raw(wp_unslash($request->get_param('url') ?? ''));
	$name = sanitize_text_field(wp_unslash($request->get_param('name') ?? ''));
	$message = wp_kses(wp_unslash($request->get_param('message') ?? ''), array());
	if (!is_email($email)) return new WP_Error('sp_fl_invalid_email', __('邮箱格式不正确。', 'sphotography'), array('status' => 400));
	if (!$url || !preg_match('/^https?:\/\//', $url)) return new WP_Error('sp_fl_invalid_url', __('网址格式不正确。', 'sphotography'), array('status' => 400));
	if (!$name) return new WP_Error('sp_fl_no_name', __('请填写站点名称。', 'sphotography'), array('status' => 400));
	$message = substr($message, 0, 500);
	$apps = sphotography_get_friend_link_applications();
	if (count($apps) > 200) $apps = array_slice($apps, -199);
	$new_id = (!empty($apps) ? max(array_column($apps, 'id')) : 0) + 1;
	$apps[] = array('id' => $new_id, 'email' => $email, 'url' => $url, 'name' => $name, 'message' => $message, 'time' => time());
	sphotography_update_friend_link_applications($apps);
	if ('1' === sphotography_get_friend_link_notify()) {
		$admin_email = get_option('admin_email');
		$subject = '【友链申请】' . $name;
		$body = sprintf(__('网站名：%s\n网址：%s\n邮箱：%s\n备注：%s', 'sphotography'), $name, $url, $email, $message);
		wp_mail($admin_email, $subject, $body);
	}
	return new WP_REST_Response(array('ok' => true), 200);
}
function sphotography_friend_links_config() { return array('applyEnabled' => true); }
function sphotography_fetch_friend_meta_handler($link_id) {
	$links = sphotography_get_friend_links();
	$link = null;
	foreach ($links as &$l) {
		if ((int) $l['id'] === (int) $link_id) { $link = &$l; break; }
	}
	if (!$link) return;
	if (empty($link['name'])) {
		$response = wp_remote_get($link['url'], array('timeout' => 10));
		if (!is_wp_error($response)) {
			$body = wp_remote_retrieve_body($response);
			if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $body, $m)) {
				$link['name'] = sanitize_text_field($m[1]);
			}
		}
	}
	if (!$link['thumb_id']) {
		$mshots_url = 'https://s.wordpress.com/mshots/v1/' . rawurlencode($link['url']) . '?w=600&h=400';
		$response = wp_remote_get($mshots_url, array('timeout' => 20));
		$saved = false;
		if (!is_wp_error($response) && 200 === (int) wp_remote_retrieve_response_code($response)) {
			$body = wp_remote_retrieve_body($response);
			// mShots serves a tiny "generating" placeholder until the shot is
			// ready; gauge readiness by actual byte length (headers may omit
			// content-length) and retry a few times before giving up.
			if (strlen($body) > 8000) {
				require_once ABSPATH . 'wp-admin/includes/file.php';
				require_once ABSPATH . 'wp-admin/includes/image.php';
				require_once ABSPATH . 'wp-admin/includes/media.php';
				$upload = wp_upload_bits('mshots-' . md5($link['url']) . '.jpg', null, $body);
				if (empty($upload['error'])) {
					$attachment = array('post_mime_type' => 'image/jpeg', 'post_title' => preg_replace('/\.[^.]+$/', '', basename($upload['file'])), 'post_content' => '', 'post_status' => 'inherit');
					$attach_id = wp_insert_attachment($attachment, $upload['file']);
					if (!is_wp_error($attach_id)) {
						$meta = wp_generate_attachment_metadata($attach_id, $upload['file']);
						wp_update_attachment_metadata($attach_id, $meta);
						$link['thumb_id'] = $attach_id;
						$saved = true;
					}
				}
			}
		}
		if (!$saved) {
			// Still generating / transient failure — retry up to 4 times, ~25s apart.
			$attempts = isset($link['thumb_attempts']) ? (int) $link['thumb_attempts'] : 0;
			if ($attempts < 4) {
				$link['thumb_attempts'] = $attempts + 1;
				wp_schedule_single_event(time() + 25, 'sphotography_fetch_friend_meta', array((int) $link_id));
			}
		}
	}
    // Merge into fresh data so a slow fetch cannot overwrite manual edits or sorting.
    wp_cache_delete('sphotography_friend_links', 'options');
    wp_cache_delete('alloptions', 'options');
    $latest = sphotography_get_friend_links();
    foreach ($latest as &$current) {
        if ((int) $current['id'] !== (int) $link_id || $current['url'] !== $link['url']) continue;
        if (empty($current['name'])) $current['name'] = $link['name'];
        if (empty($current['thumb_id'])) {
            $current['thumb_id'] = $link['thumb_id'];
            $current['thumb_attempts'] = $link['thumb_attempts'] ?? 0;
        }
    }
    unset($current);
    sphotography_update_friend_links($latest);
}
add_action('sphotography_fetch_friend_meta', 'sphotography_fetch_friend_meta_handler');
