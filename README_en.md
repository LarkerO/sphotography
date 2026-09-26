![Sphotography](promo image link here)

[简体中文](README.md) | **English** | [日本語](README_jp.md)

# Sphotography

Sphotography — A fullscreen map-based WordPress photography theme that turns content into an exploration.

[![GitHub release](https://img.shields.io/github/v/release/ShirazuNagisa/sphotography?color=%231abc9c&style=for-the-badge)](https://github.com/ShirazuNagisa/sphotography/releases) [![GitHub All Releases](https://img.shields.io/github/downloads/ShirazuNagisa/sphotography/total?style=for-the-badge)](https://github.com/ShirazuNagisa/sphotography/releases) [![GitHub](https://img.shields.io/github/license/ShirazuNagisa/sphotography?color=blue&style=for-the-badge)](https://github.com/ShirazuNagisa/sphotography/blob/master/LICENSE) [![Author](https://img.shields.io/badge/author-Shirazu%20Nagisa-yellow?style=for-the-badge)](https://github.com/ShirazuNagisa) [![GitHub stars](https://img.shields.io/github/stars/ShirazuNagisa/sphotography?color=ff69b4&style=for-the-badge)](https://github.com/ShirazuNagisa/sphotography/stargazers)

[![GitHub last commit](https://img.shields.io/github/last-commit/ShirazuNagisa/sphotography?style=flat-square)](https://github.com/ShirazuNagisa/sphotography/commits/master) [![GitHub Release Date](https://img.shields.io/github/release-date/ShirazuNagisa/sphotography?style=flat-square)](https://github.com/ShirazuNagisa/sphotography/releases) ![GitHub code size in bytes](https://img.shields.io/github/languages/code-size/ShirazuNagisa/sphotography?style=flat-square)

# Status

> **Core Philosophy: Content in the backend, exploration in the frontend.** When visitors enter the site, they're not greeted with a list of articles — instead, they see a vector map filling the entire viewport. Every photo with location data becomes a pin on the map; click to expand the article, navigate between pages, or fly to coordinates. Continuously evolving. PRs and Issues are always welcome.

# Features

+ **Fullscreen Map Exploration** — A vector map powered by MapLibre GL JS serves as the homepage, with CartoDB basemaps (Dark Matter / Positron) — no API token required. Photos appear as droplet-shaped markers; nearby points are dynamically merged/split via a gooey filter for real-time clustering.
+ **Night Mode** — Three modes: Follow System / Force Light / Force Dark. The dark scheme also offers Classic / Blue / Purple variants.
+ **Sidebar & Articles** — Collapsible sidebar, instant search (`Ctrl / ⌘ + K`), category and region filtering, full article loading via REST. Features a Windows DWM-style window-snapping expand animation.
+ **Photo Wall** — Photos grouped by date, with pinned posts, infinite scroll, and a lightbox revealing EXIF details (aperture, shutter speed, ISO). A "View on Map" button flies you to the shooting location.
+ **Map Interaction** — Click a marker to reveal a photo grid that follows the map. On desktop, click a grid photo to open the article with a windowed animation and navigate to the corresponding paragraph. Click a geotagged image within an article to pan the map and zoom to a 5 km / 1 cm scale, neatly parked beside the panel.
+ **Custom Comment System** — REST-based, supporting captcha, private comments, email notifications, Markdown, emoji, likes, pinning, edit history, user agent, IP geolocation, text avatars, collapsible threads, and pagination.
+ **Friends & Message Board** — Reuses the WordPress Links Manager with mShots auto-thumbnails, friend link applications with approval workflow, and a message board powered by the same comment engine.
+ **AI Module (Experimental)** — Built-in API key (AES-256 encrypted, server-side only). Supports article completion / polishing (adjustable tone, style, and length), full-text summarization, auto-tagging, and single/dual-model multimodal image-to-text writing — outputting native block HTML.
+ **Social Sharing** — WeChat QR code, QQ, QZone, Weibo, X (Twitter), Facebook, and copy link — all with monochrome theme-colored icons.
+ **Multi-language** — Frontend language switching between 中文 / EN / 日本語. Static dictionary + on-demand model translation + server-side caching. Article translations are pre-generated upon saving.
+ **Statistics** — Expanded sidebar with rich statistics, regional pie charts, a staggered waterfall article list, and word count / reading stats on cards.
+ **Region Coloring** — `region_tag` taxonomy, on-demand administrative boundary downloads, region filtering with plot statistics, and map theme color filters.
+ **Many Details** — Rounded magnetic cursor (iPad-style), floating announcement panel (auto-dismissable), in-article table of contents (TOC), reading progress indicator, page link bar, blurred article cover backgrounds, and more.
+ **Global Config Export / Import** — One-click JSON export/import of all settings (including encrypted API key round-trip, friend links, message board entries, region colors).
+ **Admin Features** — Dedicated top-level settings page, one-click EXIF extraction (GPS / camera / date) from the media library, CDN source switching (jsDelivr / unpkg / cdnjs), one-click GitHub branch update, optional Sphotography-styled admin interface.
+ **Technical Highlights** — Pure vanilla JavaScript (no frameworks, zero global pollution), dual-channel data delivery via REST + inline PHP (with automatic 403 fallback), CSS-variable-driven design system, responsive three-breakpoint layout, accessibility-friendly with respect for `prefers-reduced-motion`.

# Installation

Download the .zip file from the [Releases](https://github.com/ShirazuNagisa/sphotography/releases) page. In your WordPress admin, go to **Appearance → Themes**, upload and activate the theme.

Upon activation, the theme will automatically register the `region_tag` taxonomy, create a "Fullscreen Map" page template, and set it as the static front page.

# Documentation

[Sphotography Documentation](https://sph-doc.shirazu-nagisa.com)

# Demo / User Wall

[sphotography.shirazu-nagisa.com](https://sphotography.shirazu-nagisa.com)

Check out the [User Wall](user wall link here) to see more blogs using this theme.

# Note

Sphotography is open-sourced under the [GPL v2.0 or later](https://github.com/ShirazuNagisa/sphotography/blob/master/LICENSE) license. Please comply with this license for any derivative work.

# Screenshots

![render1](render image 1 link here)

![render2](render image 2 link here)

![render3](render image 3 link here)

# Changelog

## 20260926 v1.6 SP

+ Merge profile controls into the sidebar bottom row and move Theme / GitHub below uptime to give articles more room.
+ Match map controls to sidebar styling, remove the compass, and disable mouse, touch and keyboard rotation to keep north up.
+ Apply the selected font and webfont source to the WordPress global admin appearance and theme settings.

+ Replace friend-link thumbnails at any time using the media library; background fetching preserves manual changes.
+ Drag friend-link handles to reorder and save automatically. Manual sorting clears previous pins; pin buttons remain available. Failed saves restore the previous order.
+ Add ICP and public-security registration numbers and links before custom footer content. Empty items are hidden and narrow screens wrap.
+ Footer links are white without underlines and turn gray on hover or keyboard focus.
+ Add MiSans and HarmonyOS Sans, loaded only when selected from pinned third-party jsDelivr sources, with an optional self-hosted CSS URL and system-font fallbacks. MiSans uses character subsets; HarmonyOS Sans SC uses larger full-font files.
+ Fix font inheritance in home and expanded-page search inputs and placeholders; reset screenshot retry counts on refetch and preserve ordering during background fetches.

## 20260806 v1.5.01

+ 修复 分享链接直达单篇文章时无法渲染内容的问题（单文章 URL 现在会加载地图资源，并自动打开对应文章面板）
+ 修复 后台「检查更新」无法识别 v1.4.91 这类多段版本号的问题，任意长度版本号均能正确比较


## 20260720 v1.4.9

+ 新增 全局设置 导出 / 导入 功能（JSON，含加密 API Key 明文往返、友链、留言板、地域颜色）
+ 可变尺寸错落瀑布流文章卡片
+ 展开页 ↔ 文章 改为两屏推拉滑动
+ 展开按钮光标吸附、加长展开页搜索框
+ 修复个人信息栏无法展开、地块重复计数、后台搜索图标重叠等问题

## 20260720 v1.4.8

+ 新增 边栏展开页大卡片（富统计 + 地区饼图 + 瀑布流文章列表）
+ 移除个人信息卡片，强制边栏；药丸搜索 + 圆形筛选按钮
+ 文章内目录（TOC）加宽、手风琴滚动监听、顶端对齐
+ 修复公告标题换行等问题

## 20260720 v1.4.7

+ 公告间距调整、目录重设计与修复
+ 地区默认值 + 边界后台下载、强制地图首页
+ 新增 PingFang / Songti 字体、iPad 圆点光标
+ 懒加载优化、修复后台搜索图标重叠

## 20260719 v1.4.6

+ 新增 文章索引；保存时预生成地理编码
+ 公告重设计、筛选 FLIP 动画、GitHub 圆形图标
+ 地理照片标注、照片墙按钮淡出、后台设置搜索
+ 收起边栏头像、文章封面模糊背景、文章内目录

## 20260719 v1.4.5

+ 🖱️ 新增 圆角磁吸光标（iPad 式圆角 + 磁性吸附）
+ 公告自动关闭
+ 位置弹层 transform 冲突修复、下滑键→阅读 100%、关闭键固定到遮罩
+ 重构仓库文件布局（源码收进 Sphotography/）

## 20260719 v1.4.4

+ 新增 浮动公告面板
+ 文章译文在保存时预生成
+ 阅读进度中点即 100% + 隐藏三键组、滚动时关闭键固定
+ 位置弹层经反向地理编码代理显示在脉冲点下方、飞图收起地图照片面板
+ 统一注释

## 20260719 v1.4.3

+ 🌐 新增 中 / A / あ 语言切换（静态词典 UI + 按需模型翻译 + 服务端缓存）
+ 修复配置页嵌套表单根因问题，恢复首页与保存
+ 社交板块移至页面末尾

## 20260719 v1.4.2

+ 配置页重构（大板块独立卡片 + 手风琴索引 + 丝滑滑下 + SVG 占位 + 预览卡）
+ 边栏按钮永久圆形
+ 页面链接栏 iOS 流动药丸
+ 文章 / 友链 / 照片墙到底淡出底部毛玻璃
+ 添加友链改居中弹窗
+ 修复评论表情点击插入、配置页索引布局等问题

## 20260718 v1.4.1

+ 设置页扁平化布局
+ 照片墙改用 Web Animations API 消除闪烁
+ 弹层 portal 到 body 避免裁剪

## 20260718 v1.4.0

+ 新增 照片墙圆键弹层、地区筛选、EXIF 回填工具
+ AI 打字机每次执行、配置索引二级菜单
+ 设置页布局修复、友链后端校验

## 20260718 v1.3.9

+ 🖼️ 新增 照片墙（文章照片 / 日期分组 / 置顶 / 无限下拉 / 详情 / 查看位置）
+ EXIF 光圈、快门、ISO
+ 后台配置页整行布局 + 预览非吸顶、面板右缘避让控件
+ 修复友链 / 留言圆形 bug

## 20260718 v1.3.8

+ 后台友链 / 留言板配置修复并并入设置页
+ 配置页大类重排（实时预览吸顶）
+ 页面链接栏 FLIP 展开、面板空白 / 飞图关闭
+ 浅色输入框修复、图片列表重复弹出修复

## 20260718 v1.3.7

+ 边栏默认展开（桌面 / 移动分离）
+ 新增 页面链接栏（友链 / 留言 / 外站）、友链页（mShots 缩略图 / 申请审核）、留言页（复用评论引擎）
+ 文章顶底磨砂过渡带、微信二维码改内联 SVG
+ 评论时间 / 点赞排序、评论 Markdown 工具栏

## 20260717 v1.3.6

+ 🤖 新增 AI 全文概述（后台异步生成 / 首开打字机 / 后台开关）
+ AI 补全润色改到主编辑区审阅浮层（彩色打字机 / 润色词级差异高亮）
+ 滚动按键相对页框静止修复

## 20260717 v1.3.5

+ 新增 文章阅读量计数器（后台开关）、边栏卡片字数 / 阅读量
+ 未保存离开保护
+ 文章展开页滚动按键（回顶 / 到底 / 进度 % / 评论）
+ 分享单色主题色图标（微信悬停二维码）、深色后台原生文字修复

## 20260717 v1.3.4

+ 新增 评论 IP 属地（离线 IP 库 / 懒解析）
+ 新增 文章撰写地点（浏览器定位 + 离线反解析）
+ 新增 社交分享栏（微信二维码 / QQ / 空间 / 微博 / X / Facebook / 复制链接）
+ 后台配色修复（浅色原生文字 / 深色列表条纹 / 深色编辑器统一深底亮字）

## 20260717 v1.3.3

+ 三重强制隐藏前台 WP 管理工具栏（修复过滤器被覆盖导致的隐藏失效）

## 20260717 v1.3.2

+ 明暗三段开关、个人信息点击展开（卡片 / 边栏）
+ 自定义链接、边栏交界渐隐过渡
+ 隐藏前台 WP 管理工具栏

## 20260717 v1.3.1

+ 💬 评论系统重构（自建 REST）
+ 支持 验证码 / 悄悄话 / 邮件提醒 / Markdown / 表情 / 点赞 / 置顶 / 编辑历史 / UA / 文字头像 / 折叠 / 分页

## 20260717 v1.3.0

+ 🤖 新增 AI 补全 / 润色 + 风格 / 长度调节
+ 单 / 双模型多模态
+ 编辑器面板浅色修复

## 20260717 v1.2.9

+ 新增 实验性 AI 模块（自带 Key，加密存储）
+ 个人信息边栏一行展示方式（默认）

## 20260716 v1.2.6 ~ v1.2.8

+ 行政区边界数据改为按需下载到 uploads（不再打包进主题）
+ 边界下载超时放宽、稳定性修复

## 20260716 v1.2.4

+ 新增 阅读信息、地图样式模块、地图主题色滤镜

## 20260715 v1.2.0

+ 主色调改为青绿色
+ 修复深色模式下后台配置页表单控件可读性
+ 全局后台 Sphotography 风格默认启用

## 20260715 v1.1.7

+ 新增 Windows DWM 式文章最小化 / 还原动画
+ 新增 Noto Serif SC 字体
+ 完整 Gutenberg 兼容、边栏默认收起

## 20260714 v1.1.3 ~ v1.1.6

+ 照片网格重构为动态多面板，移除 supercluster 依赖
+ 收窄聚合半径、新增分裂 / 融合动画
+ FLIP 动画 / 聚合过渡 / 网格互斥
+ 全面前端优化（动效令牌 / 按键统一 / 毛玻璃分层 / 品牌化 Loading）

## 20260713 v1.1.0 ~ v1.1.2

+ 聚合交互、位置追踪、单一网格、仅显示已发布文章图片
+ 边栏页脚重设计、修复聚合交互

## 20260713 v1.0.4

+ 修复夜间模式 bug
+ 新增可编辑内容页脚
+ 校验地图标记

## 20260713 v1.0.1

+ 浅色地图、边栏叠加
+ "关于" 按钮位置调整

## 20260713 v1.0.0

+ 正式版
+ GitHub 更新器 + 内联数据回退
+ 媒体库 EXIF GPS 自动检测、可编辑坐标 / 相机 / 日期字段

# Donate

If you like the Sphotography theme, you can buy me a KFC to support my development.

![donate](donation QR code link here)