<?php
// v1.4.6 (item 9): 文章头图。默认取正文第一张图片；用户可在文章编辑页通过媒体选择器
// 指定任意一张作为头图。头图以强高斯模糊背景形式铺在边栏文章模块上，保证文字可读性。
// 头图 URL 通过 REST 字段 sp_cover 暴露给前端（app.js 的 renderSidebarPosts 读取）。

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 存所选头图的附件 ID（下划线前缀 → 不出现在自定义字段 UI）。
const SPHOTOGRAPHY_COVER_META = '_sp_cover_image_id';

// ---------------------------------------------------------------------------
// Meta box
// ---------------------------------------------------------------------------
function sphotography_cover_register_meta_box() {
	add_meta_box(
		'sphotography-article-cover',
		__( '文章头图', 'sphotography' ),
		'sphotography_cover_render_meta_box',
		'post',
		'side',
		'default'
	);
}
add_action( 'add_meta_boxes', 'sphotography_cover_register_meta_box' );

function sphotography_cover_render_meta_box( $post ) {
	$cover_id  = (int) get_post_meta( $post->ID, SPHOTOGRAPHY_COVER_META, true );
	$cover_url = $cover_id ? wp_get_attachment_image_url( $cover_id, 'medium' ) : '';
	$auto_url  = $cover_id ? '' : sphotography_cover_first_content_image_url( $post->ID, 'medium' );
	wp_nonce_field( 'sphotography_cover_save', 'sphotography_cover_nonce' );
	?>
	<style>
		#sphotography-cover-box .sphotography-cover-preview { margin: 8px 0; min-height: 40px; border-radius: 8px; overflow: hidden; }
		#sphotography-cover-box .sphotography-cover-preview img { display: block; width: 100%; height: auto; border-radius: 8px; }
		#sphotography-cover-box .sphotography-cover-empty,
		#sphotography-cover-box .sphotography-cover-auto-tag { display: block; color: #888; font-size: 12px; margin-top: 4px; }
		#sphotography-cover-box .sphotography-cover-controls { display: flex; gap: 8px; }
		#sphotography-cover-box .sphotography-cover-hint { color: #888; font-size: 12px; margin: 0 0 6px; }
	</style>
	<div class="sphotography-cover-box" id="sphotography-cover-box">
		<p class="sphotography-cover-hint"><?php esc_html_e( '头图会作为该文章在边栏列表中的模糊背景。默认使用正文第一张图片，可在此指定其他图片。', 'sphotography' ); ?></p>
		<div class="sphotography-cover-preview" id="sphotography-cover-preview">
			<?php if ( $cover_url ) : ?>
				<img src="<?php echo esc_url( $cover_url ); ?>" alt="">
			<?php elseif ( $auto_url ) : ?>
				<img src="<?php echo esc_url( $auto_url ); ?>" alt="">
				<span class="sphotography-cover-auto-tag"><?php esc_html_e( '（自动：正文第一张图）', 'sphotography' ); ?></span>
			<?php else : ?>
				<span class="sphotography-cover-empty"><?php esc_html_e( '正文暂无图片', 'sphotography' ); ?></span>
			<?php endif; ?>
		</div>
		<div class="sphotography-cover-controls">
			<button type="button" class="button" id="sphotography-cover-choose"><?php esc_html_e( '选择头图', 'sphotography' ); ?></button>
			<button type="button" class="button" id="sphotography-cover-clear" <?php disabled( 0 === $cover_id ); ?>><?php esc_html_e( '恢复默认', 'sphotography' ); ?></button>
		</div>
		<input type="hidden" name="sphotography_cover_id" id="sphotography-cover-id" value="<?php echo esc_attr( $cover_id ); ?>">
	</div>
	<?php
}

// 加载 meta box 的媒体选择器脚本
function sphotography_cover_enqueue( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || 'post' !== $screen->post_type ) {
		return;
	}
	wp_enqueue_media();
	$inline = <<<'JS'
(function ($) {
	$(function () {
		var frame;
		var $id = $('#sphotography-cover-id');
		var $preview = $('#sphotography-cover-preview');
		var $clear = $('#sphotography-cover-clear');
		$('#sphotography-cover-choose').on('click', function (e) {
			e.preventDefault();
			if (frame) { frame.open(); return; }
			frame = wp.media({ title: SphotographyCover.title, button: { text: SphotographyCover.choose }, multiple: false, library: { type: 'image' } });
			frame.on('select', function () {
				var att = frame.state().get('selection').first().toJSON();
				var url = (att.sizes && att.sizes.medium && att.sizes.medium.url) || att.url || '';
				$id.val(att.id);
				$preview.html(url ? $('<img>').attr('src', url) : '');
				$clear.prop('disabled', false);
			});
			frame.open();
		});
		$clear.on('click', function (e) {
			e.preventDefault();
			$id.val('');
			$preview.html($('<span class="sphotography-cover-empty">').text(SphotographyCover.cleared));
			$(this).prop('disabled', true);
		});
	});
})(jQuery);
JS;
	wp_register_script( 'sphotography-cover', '', array( 'jquery', 'media-editor' ), SPHOTOGRAPHY_VERSION, true );
	wp_enqueue_script( 'sphotography-cover' );
	wp_localize_script( 'sphotography-cover', 'SphotographyCover', array(
		'title'   => __( '选择文章头图', 'sphotography' ),
		'choose'  => __( '用作头图', 'sphotography' ),
		'cleared' => __( '已恢复默认（正文第一张图）', 'sphotography' ),
	) );
	wp_add_inline_script( 'sphotography-cover', $inline );
}
add_action( 'admin_enqueue_scripts', 'sphotography_cover_enqueue' );

// 保存
function sphotography_cover_save( $post_id ) {
	if ( ! isset( $_POST['sphotography_cover_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sphotography_cover_nonce'] ) ), 'sphotography_cover_save' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$cover_id = isset( $_POST['sphotography_cover_id'] ) ? (int) $_POST['sphotography_cover_id'] : 0;
	if ( $cover_id > 0 && 'attachment' === get_post_type( $cover_id ) ) {
		update_post_meta( $post_id, SPHOTOGRAPHY_COVER_META, $cover_id );
	} else {
		delete_post_meta( $post_id, SPHOTOGRAPHY_COVER_META );
	}
}
add_action( 'save_post_post', 'sphotography_cover_save' );

// ---------------------------------------------------------------------------
// First-content-image extraction
// ---------------------------------------------------------------------------
/**
 * 正文第一张图片的 URL。优先解析 wp-image-<id>（拿到指定尺寸的规整图），
 * 否则退回第一个 <img> 的 src。找不到返回 ''。
 */
function sphotography_cover_first_content_image_url( $post_id, $size = 'large' ) {
	$post = get_post( $post_id );
	if ( ! $post ) {
		return '';
	}
	$content = (string) $post->post_content;

	// 1) 第一个带 wp-image-<id> 的图 → 用附件的规整尺寸。
	if ( preg_match( '/wp-image-(\d+)/', $content, $m ) ) {
		$url = wp_get_attachment_image_url( (int) $m[1], $size );
		if ( $url ) {
			return $url;
		}
	}
	// 2) 退回第一个 <img src="...">。
	if ( preg_match( '/<img[^>]+src\s*=\s*(["\'])(.*?)\1/i', $content, $m2 ) ) {
		return esc_url_raw( $m2[2] );
	}
	return '';
}

/**
 * 一篇文章的头图 URL：所选头图优先，否则正文第一张图。找不到返回 ''。
 */
function sphotography_cover_url( $post_id, $size = 'large' ) {
	$cover_id = (int) get_post_meta( $post_id, SPHOTOGRAPHY_COVER_META, true );
	if ( $cover_id > 0 ) {
		$url = wp_get_attachment_image_url( $cover_id, $size );
		if ( $url ) {
			return $url;
		}
	}
	return sphotography_cover_first_content_image_url( $post_id, $size );
}

// REST 字段：文章头图 URL，供边栏文章模块作模糊背景。
function sphotography_cover_register_rest_field() {
	register_rest_field( 'post', 'sp_cover', array(
		'get_callback' => function ( $arr ) {
			return sphotography_cover_url( (int) $arr['id'], 'large' );
		},
		'schema'       => array(
			'description' => 'Sphotography article cover image URL (chosen cover, else first content image).',
			'type'        => 'string',
		),
	) );
}
add_action( 'rest_api_init', 'sphotography_cover_register_rest_field' );
