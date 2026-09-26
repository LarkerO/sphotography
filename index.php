<?php
// 备用模板 / 最后回退：本主题是「地图优先」的，主页即全屏地图。
//
// v1.4.7 (item 4): 过去当没有配置「Fullscreen Map」页面/首页时，这里会显示一张
// 「请先创建页面并设为首页」的引导卡片，导致新用户装完主题后还得手动操作。现在
// 首页/博客主页已由 sphotography_force_map_template() 强制走 template-map.php，
// 而 index.php 作为模板层级的最终回退，也直接渲染全屏地图，彻底去掉引导卡片，
// 保证任何情况下访问站点都能进入地图，无需任何手动设置。

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$sphotography_map_template = locate_template( 'template-map.php' );
if ( $sphotography_map_template ) {
    require $sphotography_map_template;
    return;
}

// 极端兜底：连地图模板文件都找不到时，给出一个最小页面而非报错。
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php bloginfo( 'name' ); ?></title>
    <?php wp_head(); ?>
</head>
<body>
    <?php bloginfo( 'name' ); ?>
    <?php wp_footer(); ?>
</body>
</html>
