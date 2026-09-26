<?php
// 模板名称：Fullscreen Map
// 全屏地图页面模板

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

$site_name = get_bloginfo( 'name' ) ?: 'Shirazu Nagisa Photography';

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="description" content="<?php echo esc_attr( get_bloginfo( 'description' ) ); ?>">
    <title><?php echo esc_html( $site_name ); ?></title>
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
</head>
<?php
// 服务端决定侧边栏初始折叠状态（避免闪烁），桌面端和移动端有独立设置
$sphotography_body_classes = array( 'map-template-body' );
$sphotography_sidebar_default_open = wp_is_mobile()
    ? sphotography_get_mod( 'sidebar_default_open_mobile' )
    : sphotography_get_mod( 'sidebar_default_open_desktop' );
if ( ! $sphotography_sidebar_default_open ) {
    $sphotography_body_classes[] = 'sidebar-collapsed';
}
?>
<body <?php body_class( $sphotography_body_classes ); ?>>
<?php wp_body_open(); ?>
<script>
// 在页面渲染前恢复本地存储的明暗模式选择，避免闪烁
(function(){try{var v=localStorage.getItem('sp-night-mode');if(v!=='light'&&v!=='dark'&&v!=='system')return;var b=document.body;b.classList.remove('sphotography-night-force-dark','sphotography-night-force-light','sphotography-night-system');b.classList.add(v==='dark'?'sphotography-night-force-dark':v==='light'?'sphotography-night-force-light':'sphotography-night-system');}catch(e){}})();
</script>

    <?php $sphotography_preloader = sphotography_get_mod( 'preloader_style' ); ?>
    <?php if ( 'aperture' === $sphotography_preloader ) : ?>
    <!-- 光圈加载动画 -->
    <div id="loading-overlay" class="loading-overlay">
        <div class="loading-aperture">
                    <svg class="loading-aperture-ring" viewBox="0 0 88 88">
                <circle class="ring-outer" cx="44" cy="44" r="38"/>
                <circle class="ring-inner" cx="44" cy="44" r="28"/>
            </svg>
            <div class="loading-aperture-core"></div>
        </div>
        <span class="loading-site-name"><?php echo esc_html( $site_name ); ?></span>
        <div class="loading-tip" id="loading-tip" aria-live="polite"></div>
        <div class="loading-progress"></div>
    </div>
    <?php elseif ( 'flythrough' === $sphotography_preloader ) : ?>
    <!-- 流光穿越加载动画 -->
    <div id="loading-overlay" class="loading-overlay loading-overlay--flythrough" data-preloader="flythrough">
        <span class="ft-name"><?php echo esc_html( $site_name ); ?></span>
    </div>
    <?php endif; ?>

    <!-- 地图容器 -->
    <div id="map"></div>

    <!-- 水滴融合滤镜 -->
    <svg class="droplet-goo-defs" width="0" height="0" aria-hidden="true" focusable="false">
        <defs>
            <filter id="droplet-goo">
                <feGaussianBlur in="SourceGraphic" stdDeviation="<?php echo esc_attr( (int) sphotography_get_mod( 'droplet_goo_strength' ) ); ?>" result="blur"></feGaussianBlur>
                <feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0  0 1 0 0 0  0 0 1 0 0  0 0 0 20 -9" result="goo"></feColorMatrix>
                <feBlend in="SourceGraphic" in2="goo"></feBlend>
            </filter>
        </defs>
    </svg>

    <!-- 侧边栏 -->
    <aside id="sidebar" class="sidebar glass-panel" role="complementary" aria-label="<?php esc_attr_e( 'Article sidebar', 'sphotography' ); ?>">
        <!-- 搜索与筛选 -->
        <div class="sidebar-search">
            <div class="sidebar-search-row">
                <div class="sidebar-search-field">
                    <input type="text" id="sidebar-search-input" placeholder="<?php esc_attr_e( '搜索文章...', 'sphotography' ); ?>" aria-label="<?php esc_attr_e( 'Search articles', 'sphotography' ); ?>">
                    <span class="sidebar-search-icon">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </span>
                    <span id="sidebar-search-kbd" class="sidebar-search-kbd" aria-hidden="true">
                        <kbd class="kbd-mod">Ctrl</kbd><kbd>K</kbd>
                    </span>
                </div>
                <button id="sidebar-filter-btn" class="sidebar-filter-btn" type="button" aria-label="<?php esc_attr_e( '筛选文章分类', 'sphotography' ); ?>" aria-expanded="false" aria-controls="sidebar-filter-panel">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>
                </button>
            </div>
            <div id="sidebar-filter-panel" class="sidebar-filter-panel" hidden>
                <div class="filter-panel-header">
                    <span class="filter-panel-title"><?php esc_html_e( '筛选', 'sphotography' ); ?></span>
                    <button type="button" id="filter-clear" class="filter-clear" hidden><?php esc_html_e( '清除', 'sphotography' ); ?></button>
                </div>
                <div class="filter-group">
                    <div class="filter-group-label"><?php esc_html_e( '分类', 'sphotography' ); ?></div>
                    <div id="filter-chips-categories" class="filter-chips"></div>
                </div>
                <div class="filter-group">
                    <div class="filter-group-label"><?php esc_html_e( '地区', 'sphotography' ); ?></div>
                    <div id="filter-chips-regions" class="filter-chips"></div>
                </div>
            </div>
        </div>

        <!-- 文章列表 -->
        <div id="sidebar-posts" class="sidebar-posts">
            <!-- JS 动态填充 -->
        </div>

        <?php
        // 侧边栏个人信息行（头像 + 昵称，无头像时显示首字）。
        // v1.4.8：个人信息展示方式选项已移除，边栏一行为唯一且强制的展示方式。
        $sp_avatar = get_theme_mod( 'sphotography_avatar_url', '' );
        $sp_name   = get_theme_mod( 'sphotography_author_nickname', '' );
        if ( '' === $sp_name ) {
            $sp_name = __( 'Shirazu Nagisa', 'sphotography' );
        }
        $sp_initial = function_exists( 'mb_substr' ) ? mb_substr( $sp_name, 0, 1 ) : substr( $sp_name, 0, 1 );
        $sp_bio = get_theme_mod( 'sphotography_bio', '' );
        ?>
        <div class="sidebar-profile" id="sidebar-profile">
            <!-- 简单个人信息面板（点击个人信息行展开，v1.4.8 之前的行为保留） -->
            <div class="sidebar-profile-panel" id="sidebar-profile-panel" aria-hidden="true">
                <?php
                sphotography_render_profile_expand( array(
                    'avatar'  => $sp_avatar,
                    'name'    => $sp_name,
                    'bio'     => $sp_bio,
                    'initial' => $sp_initial,
                ) );
                ?>
            </div>
            <!-- 丰富统计面板（v1.4.8：点击展开页按钮时向上展开，JS 通过 /stats 填充） -->
            <div class="sidebar-stats-panel" id="sidebar-stats-panel" aria-hidden="true"></div>
            <div class="sidebar-profile-bar sidebar-footer">
            <button id="sidebar-toggle" class="sidebar-toggle-mini" aria-label="<?php esc_attr_e( 'Toggle sidebar', 'sphotography' ); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </button>
                <button type="button" class="sidebar-profile-row" id="sidebar-profile-toggle" aria-expanded="false" aria-controls="sidebar-profile-panel" aria-label="<?php esc_attr_e( '展开个人信息', 'sphotography' ); ?>">
                    <?php if ( $sp_avatar ) : ?>
                        <img src="<?php echo esc_url( $sp_avatar ); ?>" alt="" class="sidebar-profile-avatar">
                    <?php else : ?>
                        <span class="sidebar-profile-avatar sidebar-profile-avatar--placeholder"><?php echo esc_html( $sp_initial ); ?></span>
                    <?php endif; ?>
                    <span class="sidebar-profile-name"><?php echo esc_html( $sp_name ); ?></span>
                </button>
                <!-- v1.4.8：常驻圆形按钮，打开边栏展开页 + 向上展开丰富统计面板 -->
                <button type="button" class="sidebar-expandpage-btn" id="sidebar-expandpage-btn" aria-expanded="false" aria-controls="sidebar-expand-page" aria-label="<?php esc_attr_e( '打开文章列表', 'sphotography' ); ?>">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>
                </button>
            </div>
        </div>

        <template id="sidebar-theme-credit"><div class="sidebar-theme-credit">
            <span class="sidebar-brand">Theme Sphotography</span>
            <a id="sidebar-github" href="https://github.com/ShirazuNagisa/sphotography" target="_blank" rel="noopener noreferrer" class="sidebar-github-link" aria-label="GitHub repository">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.37 0 0 5.37 0 12c0 5.31 3.435 9.795 8.205 11.385.6.105.825-.255.825-.57 0-.285-.015-1.23-.015-2.235-3.015.555-3.795-.735-4.035-1.41-.135-.345-.72-1.41-1.23-1.695-.42-.225-1.02-.78-.015-.795.945-.015 1.62.87 1.845 1.23 1.08 1.815 2.805 1.305 3.495.99.105-.78.42-1.305.765-1.605-2.67-.3-5.46-1.335-5.46-5.925 0-1.305.465-2.385 1.23-3.225-.12-.3-.54-1.53.12-3.18 0 0 1.005-.315 3.3 1.23.96-.27 1.98-.405 3-.405s2.04.135 3 .405c2.295-1.56 3.3-1.23 3.3-1.23.66 1.65.24 2.88.12 3.18.765.84 1.23 1.905 1.23 3.225 0 4.605-2.805 5.625-5.475 5.925.435.375.81 1.095.81 2.22 0 1.605-.015 2.895-.015 3.3 0 .315.225.69.825.57A12.02 12.02 0 0024 12c0-6.63-5.37-12-12-12z"/></svg>
            </a>
        </div></template>
    </aside>

    <!-- 侧边栏展开按钮（折叠时可见） -->
    <?php
    // v1.4.6 (item 8): 边栏折叠时，在展开按钮上方显示博主头像（圆形、尺寸同展开按钮、
    // 暂不可点击）。v1.4.8：不再受个人信息展示方式限制，已设置头像时即显示。
    $sp_collapsed_avatar = get_theme_mod( 'sphotography_avatar_url', '' );
    if ( $sp_collapsed_avatar ) : ?>
        <img src="<?php echo esc_url( $sp_collapsed_avatar ); ?>" alt="" class="sidebar-expand-avatar" aria-hidden="true">
    <?php endif; ?>
    <button id="sidebar-expand" class="sidebar-expand-btn glass-panel" aria-label="<?php esc_attr_e( 'Expand sidebar', 'sphotography' ); ?>">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
    </button>

    <!-- v1.4.8/1.4.9：边栏展开页（文章列表大屏，瀑布流）。与文章面板同一尺寸/位置，置于最上层。
         v1.4.9：两屏推进容器——列表屏(A) 与文章屏(B) 互斥；点开卡片时将真实 #article-panel
         迁移进文章屏内部，列表左出 / 文章右入，关闭再迁回，从容器内部完成动画。 -->
    <div id="sidebar-expand-page" class="expand-page glass-panel" role="dialog" aria-modal="false" aria-label="<?php esc_attr_e( '文章列表', 'sphotography' ); ?>" aria-hidden="true">
        <div class="expand-page-screen expand-page-screen--list">
            <div class="expand-page-header">
                <h3 class="expand-page-title"><?php esc_html_e( '文章列表', 'sphotography' ); ?></h3>
                <div class="expand-page-search-field">
                    <span class="expand-page-search-icon">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                    </span>
                    <input type="text" id="expand-page-search" class="expand-page-search-input" placeholder="<?php esc_attr_e( '搜索文章...', 'sphotography' ); ?>" aria-label="<?php esc_attr_e( 'Search articles', 'sphotography' ); ?>">
                </div>
                <button type="button" id="expand-page-close" class="panel-close-btn expand-page-close" aria-label="<?php esc_attr_e( '退出', 'sphotography' ); ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div id="expand-page-grid" class="expand-page-grid" aria-live="polite"><!-- JS：瀑布流卡片 --></div>
            <div id="expand-page-empty" class="expand-page-empty" hidden><?php esc_html_e( '没有找到匹配的文章', 'sphotography' ); ?></div>
        </div>
        <div id="expand-page-article-slot" class="expand-page-screen expand-page-screen--article"><!-- JS：迁入 #article-panel --></div>
    </div>

    <!-- 文章面板 -->
    <div id="article-panel" class="article-panel glass-panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Article content', 'sphotography' ); ?>">
        <button id="article-close" class="panel-close-btn" aria-label="<?php esc_attr_e( 'Close article', 'sphotography' ); ?>">
            <svg class="panel-close-icon-x" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            <!-- v1.4.9: shown instead of the ✕ when the article was opened inside the sidebar expand-page — click returns to the list. -->
            <svg class="panel-close-icon-back" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        </button>
        <div class="article-panel-header">
            <h3 id="article-title"></h3>
            <div id="article-meta" class="article-meta"></div>
        </div>
        <div id="article-summary" class="article-summary" hidden>
            <!-- AI 概述 -->
        </div>
        <div id="article-content" class="article-content">
            <!-- JS 加载内容 -->
        </div>
        <div id="article-share" class="article-share" hidden>
            <!-- 分享栏 -->
        </div>
        <div id="article-comments" class="article-comments" aria-live="polite">
            <!-- 评论区 -->
        </div>
    </div>

    <!-- 图片网格面板 -->
    <div id="photo-panels" class="photo-panels" aria-live="polite"></div>

    <!-- 图片详情面板 -->
    <div id="detail-sheet" class="detail-sheet glass-panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e( 'Photo detail', 'sphotography' ); ?>">
        <button id="close-detail" class="panel-close-btn" aria-label="<?php esc_attr_e( 'Close detail panel', 'sphotography' ); ?>">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
        <div class="detail-content">
            <div class="drag-handle"></div>
            <img id="detail-img" class="detail-img" src="" alt="" />
            <h3 id="detail-title" class="detail-title"></h3>
            <div id="detail-meta" class="detail-meta"></div>
            <div id="detail-desc" class="detail-desc"></div>
            <div id="detail-tags" class="detail-tags"></div>
            <div id="detail-actions" class="detail-actions">
                <button id="detail-view-article" type="button" class="detail-view-article-btn" hidden>
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                    <span><?php esc_html_e( '查看文章', 'sphotography' ); ?></span>
                </button>
            </div>
        </div>
    </div>

    <!-- v1.4.8：右下角个人信息卡片已移除，边栏一行为唯一展示方式 -->

    <!-- 页脚 -->
    <?php
    $footer_content = get_theme_mod( 'sphotography_footer_content', '' );
    $registrations = array();
    $icp = sphotography_get_mod( 'icp_number' );
    $security = sphotography_get_mod( 'security_number' );
    if ( $icp ) $registrations[] = array( $icp, 'https://beian.miit.gov.cn/' );
    if ( $security ) {
        $security_url = sphotography_get_mod( 'security_url' );
        $digits = preg_replace( '/[^0-9]/', '', $security );
        if ( ! $security_url ) $security_url = $digits ? 'https://beian.mps.gov.cn/#/query/webSearch?code=' . $digits : 'https://beian.mps.gov.cn/';
        $registrations[] = array( $security, $security_url );
    }
    ?>
    <?php if ( ! empty( $footer_content ) || $registrations ) : ?>
    <div id="map-footer" class="map-footer glass-panel">
        <?php // 由受信任管理员保存，不转义 ?>
        <div class="footer-content">
            <?php foreach ( $registrations as $i => $registration ) : ?>
                <?php if ( $i ) : ?><span aria-hidden="true">|</span><?php endif; ?>
                <a class="footer-registration" href="<?php echo esc_url( $registration[1] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $registration[0] ); ?></a>
            <?php endforeach; ?>
            <?php if ( $registrations && ! empty( $footer_content ) ) : ?><span aria-hidden="true">|</span><?php endif; ?>
            <div class="footer-custom"><?php echo $footer_content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div></div>
    </div>
    <?php endif; ?>

<?php wp_footer(); ?>
</body>
</html>
