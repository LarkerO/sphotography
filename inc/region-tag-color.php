<?php
// region_tag 分类法颜色（词条元数据 + 服务端解析器）

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const SPHOTOGRAPHY_TAG_COLOR_META = 'sphotography_color';

// 从标签别名生成确定性颜色
function sphotography_tag_hash_color( $slug ) {
    $slug = (string) $slug;
    $hash = 0;
    $len  = strlen( $slug );
    for ( $i = 0; $i < $len; $i++ ) {
        // 哈希算法（djb2 变体）
        $hash = ( ( $hash << 5 ) - $hash + ord( $slug[ $i ] ) ) & 0xFFFFFFFF;
    }
    $hue = $hash % 360;
    return sphotography_hsl_to_hex( $hue, 0.62, 0.55 );
}

// 解析分类法颜色：优先使用显式配置，否则用别名哈希
function sphotography_tag_color( $term ) {
    $term = is_numeric( $term ) ? get_term( (int) $term, 'region_tag' ) : $term;
    if ( ! $term || is_wp_error( $term ) ) {
        return '';
    }
    $override = get_term_meta( $term->term_id, SPHOTOGRAPHY_TAG_COLOR_META, true );
    $override = sanitize_hex_color( (string) $override );
    return $override ? $override : sphotography_tag_hash_color( $term->slug );
}

// HSL 转十六进制
function sphotography_hsl_to_hex( $h, $s, $l ) {
    $c = ( 1 - abs( 2 * $l - 1 ) ) * $s;
    $x = $c * ( 1 - abs( fmod( $h / 60, 2 ) - 1 ) );
    $m = $l - $c / 2;
    if ( $h < 60 )       { $r = $c; $g = $x; $b = 0; }
    elseif ( $h < 120 )  { $r = $x; $g = $c; $b = 0; }
    elseif ( $h < 180 )  { $r = 0; $g = $c; $b = $x; }
    elseif ( $h < 240 )  { $r = 0; $g = $x; $b = $c; }
    elseif ( $h < 300 )  { $r = $x; $g = 0; $b = $c; }
    else                 { $r = $c; $g = 0; $b = $x; }
    return sprintf(
        '#%02x%02x%02x',
        (int) round( ( $r + $m ) * 255 ),
        (int) round( ( $g + $m ) * 255 ),
        (int) round( ( $b + $m ) * 255 )
    );
}

// 所有 region_tag 别名的颜色映射
function sphotography_all_tag_colors() {
    $terms = get_terms( array(
        'taxonomy'   => 'region_tag',
        'hide_empty' => false,
    ) );
    $map = array();
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $t ) {
            $map[ $t->slug ] = array(
                'name'  => $t->name,
                'color' => sphotography_tag_color( $t ),
            );
        }
    }
    return $map;
}

// 分类法添加/编辑页面：颜色选择字段
function sphotography_region_tag_add_field() {
    ?>
    <div class="form-field term-sphotography-color-wrap">
        <label for="sphotography-term-color"><?php _e( '标记颜色', 'sphotography' ); ?></label>
        <input type="text" name="sphotography_color" id="sphotography-term-color" value="" class="sphotography-term-color-picker" data-default-color="">
        <p><?php _e( '可选。留空则按标签别名自动生成配色。仅在「地图样式 → 按地区标签分色」开启时生效。', 'sphotography' ); ?></p>
    </div>
    <?php
}
add_action( 'region_tag_add_form_fields', 'sphotography_region_tag_add_field' );

function sphotography_region_tag_edit_field( $term ) {
    $color = get_term_meta( $term->term_id, SPHOTOGRAPHY_TAG_COLOR_META, true );
    $auto  = sphotography_tag_hash_color( $term->slug );
    ?>
    <tr class="form-field term-sphotography-color-wrap">
        <th scope="row"><label for="sphotography-term-color"><?php _e( '标记颜色', 'sphotography' ); ?></label></th>
        <td>
            <input type="text" name="sphotography_color" id="sphotography-term-color" value="<?php echo esc_attr( $color ); ?>" class="sphotography-term-color-picker" data-default-color="">
            <p class="description">
                <?php
                /* translators: %s: auto-generated hex colour */
                printf( esc_html__( '留空则使用自动配色（当前：%s）。仅在「按地区标签分色」开启时生效。', 'sphotography' ), esc_html( $auto ) );
                ?>
            </p>
        </td>
    </tr>
    <?php
}
add_action( 'region_tag_edit_form_fields', 'sphotography_region_tag_edit_field' );

// 保存颜色
function sphotography_region_tag_save_color( $term_id ) {
    // 分类法编辑页面的 nonce 由核心处理，此处仅判断是否有本插件的字段
    if ( ! isset( $_POST['sphotography_color'] ) ) {
        return;
    }
    if ( ! current_user_can( 'manage_categories' ) ) {
        return;
    }
    $color = sanitize_hex_color( wp_unslash( $_POST['sphotography_color'] ) );
    if ( $color ) {
        update_term_meta( $term_id, SPHOTOGRAPHY_TAG_COLOR_META, $color );
    } else {
        delete_term_meta( $term_id, SPHOTOGRAPHY_TAG_COLOR_META );
    }
}
add_action( 'created_region_tag', 'sphotography_region_tag_save_color' );
add_action( 'edited_region_tag', 'sphotography_region_tag_save_color' );

// 启用颜色选择器
function sphotography_region_tag_admin_assets( $hook ) {
    if ( 'edit-tags.php' !== $hook && 'term.php' !== $hook ) {
        return;
    }
    $taxonomy = isset( $_GET['taxonomy'] ) ? sanitize_key( $_GET['taxonomy'] ) : '';
    if ( 'region_tag' !== $taxonomy ) {
        return;
    }
    wp_enqueue_style( 'wp-color-picker' );
    wp_enqueue_script( 'wp-color-picker' );
    wp_add_inline_script(
        'wp-color-picker',
        "jQuery(function($){ $('.sphotography-term-color-picker').wpColorPicker(); });"
    );
}
add_action( 'admin_enqueue_scripts', 'sphotography_region_tag_admin_assets' );
