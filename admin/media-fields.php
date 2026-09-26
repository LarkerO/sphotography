<?php
// 媒体库附加字段（经纬度、相机信息、拍摄日期、照片墙置顶）

// 添加字段到附件编辑表单
function sphotography_attachment_fields( $form_fields, $post ) {
    $post_id = $post->ID;

    $form_fields['sphotography_latitude'] = array(
        'label' => __( '纬度 (Latitude)', 'sphotography' ),
        'input' => 'text',
        'value' => get_post_meta( $post_id, 'latitude', true ),
        'helps' => __( '照片拍摄地纬度，例如 28.228', 'sphotography' ),
    );

    $form_fields['sphotography_longitude'] = array(
        'label' => __( '经度 (Longitude)', 'sphotography' ),
        'input' => 'text',
        'value' => get_post_meta( $post_id, 'longitude', true ),
        'helps' => __( '照片拍摄地经度，例如 112.944', 'sphotography' ),
    );

    $form_fields['sphotography_camera_info'] = array(
        'label' => __( '相机信息', 'sphotography' ),
        'input' => 'text',
        'value' => get_post_meta( $post_id, 'camera_info', true ),
        'helps' => __( '相机型号及参数（自动从 EXIF 读取）', 'sphotography' ),
    );

    $form_fields['sphotography_taken_at'] = array(
        'label' => __( '拍摄日期', 'sphotography' ),
        'input' => 'text',
        'value' => get_post_meta( $post_id, 'taken_at', true ),
        'helps' => __( '格式 Y-m-d，例如 2026-07-10（自动从 EXIF 读取）', 'sphotography' ),
    );

    $form_fields['sphotography_wall_pinned'] = array(
        'label' => __( '加入照片墙置顶', 'sphotography' ),
        'input' => 'html',
        'html'  => '<input type="checkbox" name="attachments[' . esc_attr( $post_id ) . '][sphotography_wall_pinned]" value="1" '
                 . ( get_post_meta( $post_id, 'wall_pinned', true ) === '1' ? 'checked="checked"' : '' )
                 . ' />',
    );

    return $form_fields;
}
add_filter( 'attachment_fields_to_edit', 'sphotography_attachment_fields', 10, 2 );

// 保存附件编辑表单的字段
function sphotography_attachment_fields_save( $post, $attachment ) {
    $meta_map = array(
        'sphotography_latitude'   => 'latitude',
        'sphotography_longitude'  => 'longitude',
        'sphotography_camera_info' => 'camera_info',
        'sphotography_taken_at'   => 'taken_at',
    );

    foreach ( $meta_map as $field_key => $meta_key ) {
        if ( isset( $attachment[ $field_key ] ) ) {
            $value = trim( $attachment[ $field_key ] );
            if ( $meta_key === 'latitude' || $meta_key === 'longitude' ) {
                $value = floatval( $value );
            }
            if ( ! empty( $value ) ) {
                update_post_meta( $post['ID'], $meta_key, $value );
            } else {
                delete_post_meta( $post['ID'], $meta_key );
            }
        }
    }

    // Handle checkbox: set if present, delete if absent
    if ( isset( $attachment['sphotography_wall_pinned'] ) ) {
        update_post_meta( $post['ID'], 'wall_pinned', '1' );
    } else {
        delete_post_meta( $post['ID'], 'wall_pinned' );
    }

    return $post;
}
add_filter( 'attachment_fields_to_save', 'sphotography_attachment_fields_save', 10, 2 );
