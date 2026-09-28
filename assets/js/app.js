// Sphotography 前台地图应用 v2

(function () {
    'use strict';

    const SETTINGS = typeof SphotographySettings !== 'undefined' ? SphotographySettings : {};
    const APP = typeof Sphotography !== 'undefined' ? Sphotography : {};
    const PRIMARY_COLOR = SETTINGS.primaryColor || '#e67e22';

    const DARK_STYLE = 'https://basemaps.cartocdn.com/gl/dark-matter-gl-style/style.json';
    const LIGHT_STYLE = 'https://basemaps.cartocdn.com/gl/positron-gl-style/style.json';
    // Exact `background` layer colors of the two basemaps above — used so the
    // flythrough preloader can land on the same color the map reveals.
    const MAP_BG_DARK = '#0e0e0e';   // dark-matter
    const MAP_BG_LIGHT = '#fafaf8';  // positron

    // Built-in presets. Hosted vector styles ship their own tiles + glyphs;
    // raster presets are wrapped in an inline MapLibre style pointing at free,
    // no-API-key tile providers (attribution is surfaced by AttributionControl).
    const VOYAGER_STYLE = 'https://basemaps.cartocdn.com/gl/voyager-gl-style/style.json';
    const WATERCOLOR_STYLE = 'https://tiles.stadiamaps.com/styles/stamen_watercolor.json';

    function rasterStyle(tiles, attribution, maxzoom) {
        return {
            version: 8,
            sources: {
                'sp-raster': {
                    type: 'raster',
                    tiles: tiles,
                    tileSize: 256,
                    maxzoom: maxzoom || 19,
                    attribution: attribution,
                },
            },
            layers: [{ id: 'sp-raster', type: 'raster', source: 'sp-raster' }],
        };
    }

    function satelliteStyle() {
        return rasterStyle(
            ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],
            'Tiles © Esri — Source: Esri, Maxar, Earthstar Geographics, and the GIS User Community',
            19
        );
    }

    function terrainStyle() {
        return rasterStyle(
            ['https://a.tile.opentopomap.org/{z}/{x}/{y}.png',
             'https://b.tile.opentopomap.org/{z}/{x}/{y}.png',
             'https://c.tile.opentopomap.org/{z}/{x}/{y}.png'],
            'Map data: © OpenStreetMap contributors, SRTM | Map style: © OpenTopoMap (CC-BY-SA)',
            17
        );
    }

    // ---------------------------------------------------------------
    // China basemap providers (国内合规底图) + coordinate system
    //
    // OpenStreetMap/CartoDB have no surveying licence for mainland China, so a
    // "china" region branch offers compliant domestic providers. Two of them
    // (Amap/高德, Tencent/腾讯) render in the GCJ-02 datum, which is offset from
    // the raw WGS-84 GPS stored in photo EXIF by ~100–700m. Tianditu (天地图)
    // uses CGCS2000 (≈WGS-84), so it needs no offset.
    //
    // When a GCJ-02 provider is active we transform every WGS-84 coordinate to
    // GCJ-02 *once*, at the single point they enter the map (buildGeoJSON… and
    // the region polygons), and keep the original WGS-84 in feature properties
    // for readouts / reverse-geocoding. See wgs84ToGcj02 + chinaNeedsGcj below.
    // ---------------------------------------------------------------
    var GCJ_A = 6378245.0;              // Krasovsky 1940 semi-major axis
    var GCJ_EE = 0.00669342162296594323; // eccentricity squared

    function outOfChina(lng, lat) {
        // Rough mainland-China bbox; outside it the GCJ-02 offset is not applied
        // (matches how domestic map services leave foreign coords untouched).
        return (lng < 72.004 || lng > 137.8347 || lat < 0.8293 || lat > 55.8271);
    }
    function gcjTransformLat(x, y) {
        var ret = -100.0 + 2.0 * x + 3.0 * y + 0.2 * y * y + 0.1 * x * y + 0.2 * Math.sqrt(Math.abs(x));
        ret += (20.0 * Math.sin(6.0 * x * Math.PI) + 20.0 * Math.sin(2.0 * x * Math.PI)) * 2.0 / 3.0;
        ret += (20.0 * Math.sin(y * Math.PI) + 40.0 * Math.sin(y / 3.0 * Math.PI)) * 2.0 / 3.0;
        ret += (160.0 * Math.sin(y / 12.0 * Math.PI) + 320 * Math.sin(y * Math.PI / 30.0)) * 2.0 / 3.0;
        return ret;
    }
    function gcjTransformLng(x, y) {
        var ret = 300.0 + x + 2.0 * y + 0.1 * x * x + 0.1 * x * y + 0.1 * Math.sqrt(Math.abs(x));
        ret += (20.0 * Math.sin(6.0 * x * Math.PI) + 20.0 * Math.sin(2.0 * x * Math.PI)) * 2.0 / 3.0;
        ret += (20.0 * Math.sin(x * Math.PI) + 40.0 * Math.sin(x / 3.0 * Math.PI)) * 2.0 / 3.0;
        ret += (150.0 * Math.sin(x / 12.0 * Math.PI) + 300.0 * Math.sin(x / 30.0 * Math.PI)) * 2.0 / 3.0;
        return ret;
    }
    // WGS-84 [lng,lat] → GCJ-02 [lng,lat]. Returns a fresh array.
    function wgs84ToGcj02(lng, lat) {
        if (outOfChina(lng, lat)) return [lng, lat];
        var dLat = gcjTransformLat(lng - 105.0, lat - 35.0);
        var dLng = gcjTransformLng(lng - 105.0, lat - 35.0);
        var radLat = lat / 180.0 * Math.PI;
        var magic = Math.sin(radLat);
        magic = 1 - GCJ_EE * magic * magic;
        var sqrtMagic = Math.sqrt(magic);
        dLat = (dLat * 180.0) / ((GCJ_A * (1 - GCJ_EE)) / (magic * sqrtMagic) * Math.PI);
        dLng = (dLng * 180.0) / (GCJ_A / sqrtMagic * Math.cos(radLat) * Math.PI);
        return [lng + dLng, lat + dLat];
    }

    // True when the active China provider renders in GCJ-02 (Amap/Tencent) and
    // photo coordinates therefore need the WGS-84→GCJ-02 shift. Tianditu → false.
    function chinaNeedsGcj() {
        if ((SETTINGS.mapRegion || 'global') !== 'china') return false;
        var p = SETTINGS.mapChinaProvider || 'tianditu';
        return p === 'amap' || p === 'tencent';
    }

    // Convert raw WGS-84 [lng,lat] to the active basemap's datum: shifted to
    // GCJ-02 under Amap/Tencent, unchanged otherwise. Use whenever a WGS-84
    // coordinate needs to be placed on / flown to on the map.
    function toMapCoords(lng, lat) {
        return chinaNeedsGcj() ? wgs84ToGcj02(lng, lat) : [lng, lat];
    }

    // Recover a feature's original WGS-84 [lng,lat] — from the wgsLng/wgsLat we
    // stash in properties (present under GCJ providers), else its geometry
    // (which is already WGS-84 in global / Tianditu modes).
    function featureWgsCoords(f) {
        var p = (f && f.properties) || {};
        if (typeof p.wgsLng === 'number' && typeof p.wgsLat === 'number') {
            return [p.wgsLng, p.wgsLat];
        }
        return (f && f.geometry && f.geometry.coordinates) ? f.geometry.coordinates.slice() : null;
    }

    // --- Provider tile styles ---
    function tiandituStyle() {
        var key = (SETTINGS.mapTiandituKey || '').trim();
        if (!key) return null; // no key → caller falls back to auto (global) style
        var sat = (SETTINGS.mapChinaType === 'satellite');
        var base = sat ? 'img' : 'vec';   // 影像 / 矢量
        var anno = sat ? 'cia' : 'cva';   // 影像注记 / 矢量注记
        var subs = ['0', '1', '2', '3', '4', '5', '6', '7'];
        function layer(t) {
            return subs.map(function (s) {
                return 'https://t' + s + '.tianditu.gov.cn/' + t + '_w/wmts'
                    + '?SERVICE=WMTS&REQUEST=GetTile&VERSION=1.0.0&LAYER=' + t
                    + '&STYLE=default&TILEMATRIXSET=w&FORMAT=tiles'
                    + '&TILEMATRIX={z}&TILEROW={y}&TILECOL={x}&tk=' + encodeURIComponent(key);
            });
        }
        return {
            version: 8,
            sources: {
                'sp-td-base': { type: 'raster', tiles: layer(base), tileSize: 256, maxzoom: 18, attribution: '© 天地图 GS(2023)336号' },
                'sp-td-anno': { type: 'raster', tiles: layer(anno), tileSize: 256, maxzoom: 18 },
            },
            layers: [
                { id: 'sp-td-base', type: 'raster', source: 'sp-td-base' },
                { id: 'sp-td-anno', type: 'raster', source: 'sp-td-anno' },
            ],
        };
    }
    function amapStyle() {
        var sat = (SETTINGS.mapChinaType === 'satellite');
        var subs = ['1', '2', '3', '4'];
        if (!sat) {
            // 标准矢量含注记：style=7 (road net + labels baked in).
            return rasterStyle(
                subs.map(function (s) {
                    return 'https://webrd0' + s + '.is.autonavi.com/appmaptile'
                        + '?lang=zh_cn&size=1&scale=1&style=8&x={x}&y={y}&z={z}';
                }),
                '© 高德地图 AutoNavi', 18
            );
        }
        // 卫星影像 (style=6) + 路网注记叠加 (style=8).
        return {
            version: 8,
            sources: {
                'sp-amap-img': { type: 'raster', tileSize: 256, maxzoom: 18, attribution: '© 高德地图 AutoNavi',
                    tiles: subs.map(function (s) { return 'https://webst0' + s + '.is.autonavi.com/appmaptile?style=6&x={x}&y={y}&z={z}'; }) },
                'sp-amap-anno': { type: 'raster', tileSize: 256, maxzoom: 18,
                    tiles: subs.map(function (s) { return 'https://webst0' + s + '.is.autonavi.com/appmaptile?lang=zh_cn&size=1&scale=1&style=8&x={x}&y={y}&z={z}'; }) },
            },
            layers: [
                { id: 'sp-amap-img', type: 'raster', source: 'sp-amap-img' },
                { id: 'sp-amap-anno', type: 'raster', source: 'sp-amap-anno' },
            ],
        };
    }
    function tencentStyle() {
        var sat = (SETTINGS.mapChinaType === 'satellite');
        var subs = ['0', '1', '2', '3'];
        if (!sat) {
            // Standard tiles use TMS (bottom-origin Y) → {-y}.
            return rasterStyle(
                subs.map(function (s) {
                    return 'https://rt' + s + '.map.gtimg.com/tile'
                        + '?z={z}&x={x}&y={-y}&type=vector&styleid=0';
                }),
                '© 腾讯地图 Tencent', 18
            );
        }
        // Satellite imagery (sateTiles) + label overlay (vector styleid=3), both TMS.
        return {
            version: 8,
            sources: {
                'sp-tx-img': { type: 'raster', tileSize: 256, maxzoom: 18, scheme: 'tms', attribution: '© 腾讯地图 Tencent',
                    tiles: subs.map(function (s) { return 'https://p' + s + '.map.gtimg.com/sateTiles/{z}/{x}/{y}.jpg?version=400'; }) },
                'sp-tx-anno': { type: 'raster', tileSize: 256, maxzoom: 18,
                    tiles: subs.map(function (s) { return 'https://rt' + s + '.map.gtimg.com/tile?z={z}&x={x}&y={-y}&type=vector&styleid=3'; }) },
            },
            layers: [
                { id: 'sp-tx-img', type: 'raster', source: 'sp-tx-img' },
                { id: 'sp-tx-anno', type: 'raster', source: 'sp-tx-anno' },
            ],
        };
    }
    // Resolve the active China provider to a MapLibre style, or null to fall
    // back to the global auto style (e.g. Tianditu with no key).
    function chinaStyle() {
        switch (SETTINGS.mapChinaProvider || 'tianditu') {
            case 'amap':    return amapStyle();
            case 'tencent': return tencentStyle();
            case 'tianditu':
            default:        return tiandituStyle();
        }
    }

    // --- Pseudo-dark for China basemaps ---
    // None of the domestic providers expose a dark raster tileset, so when the
    // resolved theme is dark we invert the map canvas to fake a night basemap
    // (the same trick Baidu/Amap SDKs use internally). Only the WebGL canvas is
    // filtered — HTML overlays (droplets, pulse dot, popups, panels) sit above
    // it untouched. Satellite imagery is excluded (inverting a photo looks
    // wrong), so pseudo-dark applies to the "standard" map type only.
    var CHINA_DARK_FILTER = 'invert(0.92) hue-rotate(180deg) brightness(0.95) contrast(0.9)';

    function chinaPseudoDarkActive() {
        if ((SETTINGS.mapRegion || 'global') !== 'china') return false;
        if (usingAutoStyle()) return false;             // no key → global auto handles dark
        if (state.styleFellBack) return false;          // provider tiles failed → global fallback is rendered, don't invert it
        if (SETTINGS.mapChinaType === 'satellite') return false; // don't invert imagery
        return !resolveMapIsLight();                    // only when theme resolves to dark
    }

    // Apply / clear the invert filter on the map canvas to match the current
    // resolved lightness. Safe to call before the map exists (no-op).
    function applyChinaDarkFilter() {
        if (!state.map || typeof state.map.getCanvas !== 'function') return;
        var canvas = state.map.getCanvas();
        if (!canvas) return;
        canvas.style.filter = chinaPseudoDarkActive() ? CHINA_DARK_FILTER : '';
    }

    // ---------------------------------------------------------------
    // Night mode (v1.3.2)
    //
    // The three-way switch (light / dark / follow-system) overrides the
    // backend `night_mode` at runtime. The choice is remembered in
    // localStorage; the first visit falls back to the backend default. This
    // drives BOTH the UI theme (body class) and — for the auto basemap — the
    // map style, so `nightMode` here is the single source of truth the map
    // resolvers read.
    // ---------------------------------------------------------------
    var NIGHT_STORAGE_KEY = 'sp-night-mode';
    function readStoredNightMode() {
        try {
            var v = localStorage.getItem(NIGHT_STORAGE_KEY);
            if (v === 'light' || v === 'dark' || v === 'system') return v;
        } catch (e) {}
        return null;
    }
    var nightMode = readStoredNightMode() || SETTINGS.nightMode || 'system';

    function resolveMapIsLight() {
        var mode = nightMode;
        if (mode === 'light') return true;
        if (mode === 'dark') return false;
        return !!(window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches);
    }

    // The night-mode-driven basemap: this is what "auto" resolves to and what
    // any failed custom/preset style falls back to.
    function autoStyle() {
        return resolveMapIsLight() ? LIGHT_STYLE : DARK_STYLE;
    }

    function customStyleUrl() {
        var url = (SETTINGS.mapStyleCustomUrl || '').trim();
        return /^https:\/\//i.test(url) ? url : '';
    }

    // Is the effective basemap the auto (night-mode) style? True for the "auto"
    // preset and for "custom" with an empty/invalid URL (which falls back).
    function usingAutoStyle() {
        // China branch: only "auto" (night-mode-driven) when its provider style
        // is unavailable (Tianditu with no key falls back to the global auto).
        if ((SETTINGS.mapRegion || 'global') === 'china') {
            return chinaStyle() === null;
        }
        var choice = SETTINGS.mapStyle || 'auto';
        if (choice === 'auto') return true;
        if (choice === 'custom') return customStyleUrl() === '';
        return false;
    }

    // Resolve the configured style to a MapLibre `style` value: a hosted URL
    // string, or an inline style object for the raster presets.
    function getMapStyle() {
        // China region branch takes precedence over the global map_style preset.
        if ((SETTINGS.mapRegion || 'global') === 'china') {
            return chinaStyle() || autoStyle(); // null (e.g. no Tianditu key) → auto
        }
        switch (SETTINGS.mapStyle || 'auto') {
            case 'satellite':  return satelliteStyle();
            case 'terrain':    return terrainStyle();
            case 'voyager':    return VOYAGER_STYLE;
            case 'watercolor': return WATERCOLOR_STYLE;
            case 'custom':     return customStyleUrl() || autoStyle();
            case 'auto':
            default:           return autoStyle();
        }
    }

    function getMapBgColor() {
        return resolveMapIsLight() ? MAP_BG_LIGHT : MAP_BG_DARK;
    }

    const CONFIG = {
        center: [112.94, 28.23],
        zoom: 5,
        maxZoom: 18,
        minZoom: 2,
        styleUrl: getMapStyle(),
        restBase: (typeof Sphotography !== 'undefined' ? Sphotography.restUrl : '/wp-json').replace(/\/$/, ''),
        markersEndpoint: 'sphotography/v1/photos',
        postsEndpoint: 'wp/v2/posts',
        perPage: 500,
        postsPerPage: 50,
        sourceId: 'photos',
        clusterSourceId: 'photos-clustered',
        layerId: 'photo-points',
        clusterLayerId: 'photo-clusters',
        clusterCountLayerId: 'photo-cluster-count',
        markerColor: '#ffffff',
        markerBorderColor: PRIMARY_COLOR,
        markerRadius: 8,
        markerBorderWidth: 3,
        // Cluster merge distance — tied to droplet diameter by default (18px),
        // exposed as a setting (10–60). See addPhotoSource().
        clusterRadius: (function () {
            var r = parseInt(SETTINGS.clusterRadius, 10);
            return (r >= 10 && r <= 60) ? r : 18;
        })(),
    };

    // ---------------------------------------------------------------
    // 1b. Motion personality (v1.2.5)
    //
    // The admin passes only raw picker values; the tier→value table and all
    // resolution live here so there is one source of truth. The resolved
    // droplet duration/easing are also written to CSS custom properties so the
    // .droplet--transit rule (a CSS transition) stays in sync with the JS
    // timing that drives its cleanup setTimeouts.
    // ---------------------------------------------------------------
    var MOTION_TIERS = {
        subtle:     { artOpen: 180, artClose: 160, switchGap: 60,  artEase: 'cubic-bezier(0.33,0,0.2,1)',    drop: 380, dropEase: 'cubic-bezier(0.33,0,0.2,1)' },
        standard:   { artOpen: 260, artClose: 240, switchGap: 90,  artEase: 'cubic-bezier(0.18,0.85,0.28,1)', drop: 620, dropEase: 'cubic-bezier(0.22,1,0.36,1)' },
        expressive: { artOpen: 340, artClose: 300, switchGap: 120, artEase: 'cubic-bezier(0.16,1,0.3,1)',     drop: 820, dropEase: 'cubic-bezier(0.34,1.3,0.5,1)' }
    };
    // Named easing overrides. Article never offers 'spring' (stays monotonic),
    // but resolution is shared; the admin UI simply doesn't expose it there.
    var EASING_PRESETS = {
        'linear':      'linear',
        'ease-out':    'cubic-bezier(0.16,1,0.3,1)',
        'ease-in-out': 'cubic-bezier(0.65,0,0.35,1)',
        'sharp':       'cubic-bezier(0.4,0,0.2,1)',
        'spring':      'cubic-bezier(0.34,1.3,0.5,1)'
    };

    function motionScale(v) {
        var n = parseInt(v, 10);
        if (!(n >= 50 && n <= 200)) n = 100;
        return n / 100;
    }
    function motionEasing(choice, fallback) {
        return (choice && choice !== 'inherit' && EASING_PRESETS[choice]) ? EASING_PRESETS[choice] : fallback;
    }
    function resolveMotion() {
        var tier = MOTION_TIERS[SETTINGS.motionTier] ? MOTION_TIERS[SETTINGS.motionTier] : MOTION_TIERS.standard;
        var artScale = motionScale(SETTINGS.motionArticleScale);
        var dropScale = motionScale(SETTINGS.motionDropletScale);
        return {
            article: {
                openDuration: Math.round(tier.artOpen * artScale),
                closeDuration: Math.round(tier.artClose * artScale),
                switchGap: Math.round(tier.switchGap * artScale),
                easing: motionEasing(SETTINGS.motionArticleEasing, tier.artEase)
            },
            droplet: {
                transition: Math.round(tier.drop * dropScale),
                easing: motionEasing(SETTINGS.motionDropletEasing, tier.dropEase)
            }
        };
    }
    var MOTION_RESOLVED = resolveMotion();
    // Drive the CSS .droplet--transit rule from the resolved values.
    try {
        var _root = document.documentElement;
        _root.style.setProperty('--sp-droplet-duration', MOTION_RESOLVED.droplet.transition + 'ms');
        _root.style.setProperty('--sp-droplet-ease', MOTION_RESOLVED.droplet.easing);
    } catch (e) {}

    // ---------------------------------------------------------------
    // 1c. Tag colour (v1.2.5)
    //
    // Per-region_tag colouring. Colours are resolved server-side (override or
    // slug hash) and delivered as a slug→{name,color} map, so the frontend
    // never hashes anything — it just looks up. Falls back to the theme
    // primary when a tag has no colour or a droplet shares no common tag.
    // ---------------------------------------------------------------
    // Marker mode (v1.2.6): 'droplet' | 'tag' | 'region'. Mutually exclusive;
    // replaces the old boolean tag_color flag.
    var MARKER_MODE = SETTINGS.markerMode || 'droplet';

    var TAG = {
        enabled: MARKER_MODE === 'tag',
        map: (SETTINGS.tagColors && typeof SETTINGS.tagColors === 'object') ? SETTINGS.tagColors : {},
        color: function (slug) {
            var e = this.map[slug];
            return (e && e.color) ? e.color : PRIMARY_COLOR;
        },
        name: function (slug) {
            var e = this.map[slug];
            return (e && e.name) ? e.name : slug;
        }
    };

    // Feature properties survive clustering, but queryRenderedFeatures may
    // hand array values back as JSON strings — normalise to an array.
    function featureTagSlugs(props) {
        if (!props) return [];
        var t = props.tagSlugs;
        if (typeof t === 'string') {
            try { t = JSON.parse(t); } catch (e) { t = t ? [t] : []; }
        }
        return Array.isArray(t) ? t : [];
    }

    // Colour for a single point: its first tag, else primary.
    function pointColor(slugs) {
        if (!TAG.enabled || !slugs || !slugs.length) return PRIMARY_COLOR;
        return TAG.color(slugs[0]);
    }

    // Majority tag across a set of leaf features: most frequent slug wins,
    // ties broken by slug alphabetical order; no tags at all → null (primary).
    function majorityTagSlug(leaves) {
        var counts = {};
        (leaves || []).forEach(function (leaf) {
            featureTagSlugs(leaf.properties).forEach(function (s) {
                counts[s] = (counts[s] || 0) + 1;
            });
        });
        var best = null, bestCount = 0;
        Object.keys(counts).sort().forEach(function (s) {
            if (counts[s] > bestCount) { bestCount = counts[s]; best = s; }
        });
        return best;
    }

    // ---------------------------------------------------------------
    // 1d. Administrative-region colouring (v1.2.6)
    //
    // In 'region' mode the droplets are replaced by filled admin regions
    // (province worldwide, plus city inside China). Each photo carries its
    // resolved province/city adcode (computed server-side); we group photos by
    // the id for the chosen granularity and fill only the regions that hold
    // photos with the theme colour. Clicking a region opens a fused photo
    // panel. Photos with no resolved region fall back to normal droplets.
    // ---------------------------------------------------------------
    var REGION = {
        active: MARKER_MODE === 'region',
        granularity: (SETTINGS.regionGranularity === 'city') ? 'city' : 'province',
        opacity: (function () { var n = parseInt(SETTINGS.regionIntensity, 10); return (n >= 0 && n <= 100) ? n / 100 : 0.35; })(),
        geo: (typeof SphotographyGeo !== 'undefined' && SphotographyGeo && SphotographyGeo.features) ? SphotographyGeo : { type: 'FeatureCollection', features: [] },
        byId: {},        // region id → boundary feature
        photos: {},      // region id → [photo features]
        centroids: {},   // region id → [lng, lat]
        used: null,      // FeatureCollection of regions that hold photos
        sourceId: 'sp-regions',
        fillLayerId: 'sp-region-fill',
        lineLayerId: 'sp-region-line',
        hoverLayerId: 'sp-region-hover'
    };

    // The region id a photo colours, honouring the granularity + China-only
    // city fallback: city granularity uses the city adcode when present, else
    // the province adcode; province granularity always uses the province.
    function regionIdForPhoto(props) {
        if (!props) return '';
        var prov = props.provAdcode || '';
        var city = props.cityAdcode || '';
        if (REGION.granularity === 'city' && city) return city;
        return prov || '';
    }

    // Bounding-box centre of a Polygon/MultiPolygon — the panel anchor point.
    function geomCentroid(geom) {
        var minx = Infinity, miny = Infinity, maxx = -Infinity, maxy = -Infinity;
        var polys = (geom.type === 'Polygon') ? [geom.coordinates] : geom.coordinates;
        polys.forEach(function (poly) {
            poly.forEach(function (ring) {
                ring.forEach(function (pt) {
                    if (pt[0] < minx) minx = pt[0];
                    if (pt[0] > maxx) maxx = pt[0];
                    if (pt[1] < miny) miny = pt[1];
                    if (pt[1] > maxy) maxy = pt[1];
                });
            });
        });
        return [(minx + maxx) / 2, (miny + maxy) / 2];
    }

    // Partition photos into region-matched (grouped by id) and unmatched (kept
    // for the droplet fallback source). Builds the render FeatureCollection.
    // Deep-convert a Polygon/MultiPolygon's coordinates WGS-84 → GCJ-02 in place.
    function gcjShiftGeometry(geom) {
        if (!geom || !geom.coordinates) return;
        var polys = (geom.type === 'Polygon') ? [geom.coordinates] : geom.coordinates;
        polys.forEach(function (poly) {
            poly.forEach(function (ring) {
                for (var i = 0; i < ring.length; i++) {
                    var c = wgs84ToGcj02(ring[i][0], ring[i][1]);
                    ring[i][0] = c[0]; ring[i][1] = c[1];
                }
            });
        });
    }
    // One-time: shift the boundary polygons to GCJ-02 so region fills align with
    // an Amap/Tencent basemap. No-op for Tianditu / global (WGS-84 basemaps).
    function ensureRegionGeoDatum() {
        if (REGION.geoConverted || !chinaNeedsGcj()) return;
        (REGION.geo.features || []).forEach(function (f) { gcjShiftGeometry(f.geometry); });
        REGION.geoConverted = true;
    }

    function buildRegionData() {
        ensureRegionGeoDatum();
        REGION.byId = {};
        REGION.photos = {};
        REGION.centroids = {};
        (REGION.geo.features || []).forEach(function (f) {
            var id = f.properties && f.properties.id != null ? String(f.properties.id) : '';
            if (id) REGION.byId[id] = f;
        });

        var unmatched = [];
        var feats = (state.allPhotos && state.allPhotos.features) ? state.allPhotos.features : [];
        feats.forEach(function (f) {
            var id = regionIdForPhoto(f.properties);
            if (id && REGION.byId[id]) {
                (REGION.photos[id] = REGION.photos[id] || []).push(f);
            } else {
                unmatched.push(f);
            }
        });

        var usedFeatures = [];
        Object.keys(REGION.photos).forEach(function (id) {
            var f = REGION.byId[id];
            usedFeatures.push(f);
            REGION.centroids[id] = geomCentroid(f.geometry);
        });
        REGION.used = { type: 'FeatureCollection', features: usedFeatures };
        state.regionUnmatched = { type: 'FeatureCollection', features: unmatched };
    }

    // ---------------------------------------------------------------
    // 2. State
    // ---------------------------------------------------------------
    const state = {
        map: null,
        allPhotos: null,
        recentPosts: [],
        allPosts: [],
        sidebarOpen: false,
        articleOpen: false,
        detailOpen: false,
        isMobile: window.innerWidth < 768,
        clickedMarker: false,
        openPhotoIds: new Set(),
        visibleEntities: new Map(),
        photoPanels: new Map(),
        reconcileToken: 0,
        photoClickSeq: 0,       // invalidates stale async map-click callbacks (v1.3.8)
        photoClickLock: null,   // {key,time} — swallows rapid repeat clicks on one target
        openedPostId: null,
        activePhotoPanelKey: null,
        articleMotion: null,
        motionCard: null,
        droplets: new Map(),
        gooTimer: null,
        dropletZoom: undefined,
        tipTimer: null,
        filterOpen: false,
        filterMotion: null,
        // v1.4.0: two filter dimensions. AND across them, OR within each.
        selectedCategories: new Set(),
        selectedRegionTags: new Set(),
        searchQuery: '',
        mapFlyId: 0,
        regionPanels: new Map(),
        regionUnmatched: null,
        pulseDot: null,
        // v1.4.8 (item 2): expand-page article mode + cached site stats.
        expandArticleMode: false,
        siteStats: null,
    };

    // ---------------------------------------------------------------
    // 3. DOM Cache
    // ---------------------------------------------------------------
    const dom = {};
    function cacheDom() {
        dom.map = document.getElementById('map');
        dom.loadingOverlay = document.getElementById('loading-overlay');
        dom.loadingTip = document.getElementById('loading-tip');
        dom.sidebar = document.getElementById('sidebar');
        dom.sidebarPosts = document.getElementById('sidebar-posts');
        dom.sidebarToggle = document.getElementById('sidebar-toggle');
        dom.sidebarExpand = document.getElementById('sidebar-expand');
        dom.sidebarSearch = document.getElementById('sidebar-search-input');
        dom.filterBtn = document.getElementById('sidebar-filter-btn');
        dom.filterPanel = document.getElementById('sidebar-filter-panel');
        // v1.4.0: split into two chip containers (categories + region tags)
        // and a 清除 link.
        dom.filterChipsCategories = document.getElementById('filter-chips-categories');
        dom.filterChipsRegions = document.getElementById('filter-chips-regions');
        dom.filterClear = document.getElementById('filter-clear');
        dom.articlePanel = document.getElementById('article-panel');
        dom.articleClose = document.getElementById('article-close');
        dom.articleTitle = document.getElementById('article-title');
        dom.articleMeta = document.getElementById('article-meta');
        dom.articleSummary = document.getElementById('article-summary');
        dom.articleContent = document.getElementById('article-content');
        dom.articleShare = document.getElementById('article-share');
        dom.articleComments = document.getElementById('article-comments');
        dom.photoPanels = document.getElementById('photo-panels');
        dom.detailSheet = document.getElementById('detail-sheet');
        dom.closeDetail = document.getElementById('close-detail');
        dom.detailImg = document.getElementById('detail-img');
        dom.detailTitle = document.getElementById('detail-title');
        dom.detailMeta = document.getElementById('detail-meta');
        dom.detailDesc = document.getElementById('detail-desc');
        dom.detailTags = document.getElementById('detail-tags');
        dom.detailViewArticle = document.getElementById('detail-view-article');
        dom.sidebarProfile = document.getElementById('sidebar-profile');
        dom.sidebarProfileToggle = document.getElementById('sidebar-profile-toggle');
        dom.sidebarProfilePanel = document.getElementById('sidebar-profile-panel');
        dom.sidebarStatsPanel = document.getElementById('sidebar-stats-panel');
        dom.sidebarExpandPageBtn = document.getElementById('sidebar-expandpage-btn');
        dom.expandPage = document.getElementById('sidebar-expand-page');
    }

    // ---------------------------------------------------------------
    // 4. Utilities
    // ---------------------------------------------------------------
    function stripHtml(text) {
        var d = document.createElement('div');
        d.innerHTML = text;
        return d.textContent || d.innerText || '';
    }

    function escapeHtml(text) {
        if (!text) return '';
        var d = document.createElement('div');
        d.textContent = text;
        return d.innerHTML;
    }

    // Word count + reading estimate from rendered article HTML. CJK characters
    // are counted individually; runs of Latin letters/digits count as words.
    // HTML/media is stripped so only visible text feeds the count. Returns null
    // when there's no readable text (e.g. photo-only posts) so the meta unit is
    // omitted rather than showing "0 字".
    var SP_CJK_RE = /[㐀-鿿豈-﫿぀-ヿ가-힯]/g;
    function computeReadingInfo(html) {
        if (!html) return null;
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        var drop = tmp.querySelectorAll('script,style');
        for (var i = 0; i < drop.length; i++) { drop[i].parentNode.removeChild(drop[i]); }
        var text = (tmp.textContent || '').trim();
        if (!text) return null;
        var cjkMatches = text.match(SP_CJK_RE);
        var cjk = cjkMatches ? cjkMatches.length : 0;
        var latinText = text.replace(SP_CJK_RE, ' ');
        var latinMatches = latinText.match(/[A-Za-z0-9]+(?:['’\-][A-Za-z0-9]+)*/g);
        var latin = latinMatches ? latinMatches.length : 0;
        var total = cjk + latin;
        if (total <= 0) return null;
        var cjkSpeed = SETTINGS.readingSpeedCjk || 300;
        var latinSpeed = SETTINGS.readingSpeedLatin || 200;
        var minutes = Math.ceil(cjk / cjkSpeed + latin / latinSpeed);
        if (minutes < 1) minutes = 1;
        return { words: total, minutes: minutes };
    }

    // Per-post metric accessors. Posts arrive either from wp/v2/posts (REST
    // fields sp_word_count / sp_views) or the inline-data mirror (same keys
    // populated in useInlineData). Returns null when unknown so callers can
    // omit the unit rather than render "0".
    function getPostWordCount(post) {
        var n = post && post.sp_word_count;
        return (typeof n === 'number' && n > 0) ? n : null;
    }
    function getPostViews(post) {
        var n = post && post.sp_views;
        return (typeof n === 'number' && n >= 0) ? n : null;
    }

    // Compact number for tight card lines: 1234 → "1.2k", 12345 → "1.2万".
    function formatCount(n) {
        n = Number(n) || 0;
        if (n < 1000) return String(n);
        if (n < 10000) return (n / 1000).toFixed(n % 1000 === 0 ? 0 : 1).replace(/\.0$/, '') + 'k';
        return (n / 10000).toFixed(n % 10000 === 0 ? 0 : 1).replace(/\.0$/, '') + '万';
    }

    // Small inline SVG icons (stroke = currentColor) reused across meta lines.
    var SP_ICON_EYE = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7-11-7-11-7z"/><circle cx="12" cy="12" r="3"/></svg>';
    var SP_ICON_WORDS = '<svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="14" y2="17"/></svg>';
    // Sparkle mark for the AI summary card.
    var SP_ICON_AI = '<svg viewBox="0 0 24 24" width="14" height="14" fill="currentColor" aria-hidden="true"><path d="M12 2.5l1.6 4.3a4 4 0 0 0 2.35 2.35L20.25 10.75l-4.3 1.6a4 4 0 0 0-2.35 2.35L12 19l-1.6-4.3a4 4 0 0 0-2.35-2.35L3.75 10.75l4.3-1.6a4 4 0 0 0 2.35-2.35L12 2.5z"/><path d="M18.5 3.5l.55 1.45a1.4 1.4 0 0 0 .82.82L21.5 6.4l-1.45.55a1.4 1.4 0 0 0-.82.82L18.5 9.3l-.55-1.45a1.4 1.4 0 0 0-.82-.82L15.5 6.4l1.45-.55a1.4 1.4 0 0 0 .82-.82z"/></svg>';

    // ---------------------------------------------------------------
    // AI full-text summary card (v1.3.6). Shows post.sp_ai_summary between the
    // header and the content. v1.4.0: typewriter runs on EVERY open (was once
    // per browser/post via localStorage — removed so repeat visits animate
    // too). Reduced-motion still short-circuits to the full text instantly.
    // ---------------------------------------------------------------
    var summaryTypeTimer = null;
    function renderArticleSummary(post, summaryPregen) {
        var el = dom.articleSummary;
        if (!el) return;
        if (summaryTypeTimer) { clearTimeout(summaryTypeTimer); summaryTypeTimer = null; }
        el.classList.remove('is-typing');

        var summary = (SETTINGS.aiSummary && post && typeof post.sp_ai_summary === 'string')
            ? post.sp_ai_summary.trim() : '';
        if (!summary) { el.hidden = true; el.innerHTML = ''; return; }

        el.innerHTML =
            '<div class="article-summary-label">' + SP_ICON_AI + '<span>' + t('AI 概述') + '</span></div>' +
            '<div class="article-summary-text"></div>';
        el.hidden = false;
        var textEl = el.querySelector('.article-summary-text');

        var key = 'sp-summary-typed-' + (post.id || '');
        // v1.4.3: 非中文语言下概述需翻译。为避免先打字机显示中文再替换成译文的突兀，
        // 直接落原文后交给按需翻译（加载提示 → 淡入译文）。中文仍走打字机。
        if (siteLang !== 'zh') {
            textEl.textContent = summary;
            i18nRegisterPregen(textEl, 'text', summaryPregen || null); // v1.4.4: 概述优先用预生成译文
            return;
        }
        // v1.4.0: typewriter runs on every open. The localStorage "already
        // typed" guard is gone — previously this hid the typewriter after the
        // first ever open per browser, which made repeat visits feel static.
        // Reduced-motion still short-circuits to the full text instantly.
        if (prefersReducedMotion()) {
            textEl.textContent = summary;
            return;
        }

        // Typewriter reveal (Array.from keeps emoji/surrogate pairs intact).
        el.classList.add('is-typing');
        var chars = Array.from(summary);
        var i = 0;
        var pid = post.id;
        function tick() {
            if (state.openedPostId !== pid) { el.classList.remove('is-typing'); summaryTypeTimer = null; return; }
            i += 1;
            textEl.textContent = chars.slice(0, i).join('');
            if (i < chars.length) {
                summaryTypeTimer = setTimeout(tick, 38);
            } else {
                el.classList.remove('is-typing');
                summaryTypeTimer = null;
            }
        }
        summaryTypeTimer = setTimeout(tick, 260); // brief lead-in after the panel opens
    }

    // Record + fire a view hit for a post, de-duplicated per browser/post/day
    // via localStorage. Calls back with the authoritative count when the server
    // increments. No-op when the feature is disabled.
    function recordArticleView(postId, onCount) {
        if (!SETTINGS.viewCounter) return;
        var key = 'sp-viewed-' + postId;
        var now = Date.now();
        var dedup = false;
        try {
            var last = parseInt(window.localStorage.getItem(key) || '0', 10);
            if (last && (now - last) < 86400000) { dedup = true; }
        } catch (e) {}
        if (dedup) return;
        try { window.localStorage.setItem(key, String(now)); } catch (e) {}
        fetch(CONFIG.restBase + '/sphotography/v1/view/' + postId, {
            method: 'POST',
            headers: { 'X-WP-Nonce': (APP.restNonce || '') },
            credentials: 'same-origin'
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (data) {
            if (data && typeof data.views === 'number' && typeof onCount === 'function') {
                onCount(data.views);
            }
        }).catch(function () {});
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        var parts = dateStr.split('-');
        if (parts.length !== 3) return dateStr;
        var year = parts[0], month = parseInt(parts[1]), day = parseInt(parts[2]);
        var fmt = SETTINGS.dateFormat || 'Y年n月j日';
        if (fmt === 'custom' && SETTINGS.customDateFormat) fmt = SETTINGS.customDateFormat;
        var map = {
            'Y': year, 'y': year.slice(-2),
            'm': ('0'+month).slice(-2), 'n': String(month),
            'd': ('0'+day).slice(-2), 'j': String(day),
            'F': ['January','February','March','April','May','June','July','August','September','October','November','December'][month-1]||'',
            'M': ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'][month-1]||'',
        };
        var r = '';
        for (var i = 0; i < fmt.length; i++) {
            var c = fmt[i];
            if (c === '\\') { if (i+1<fmt.length) { r+=fmt[i+1]; i++; } continue; }
            r += map[c] !== undefined ? map[c] : c;
        }
        return r;
    }

    function debounce(fn, delay) {
        var t = null;
        return function () { var a=arguments, c=this; clearTimeout(t); t=setTimeout(function(){fn.apply(c,a);},delay); };
    }

    function isMobileView() { return window.innerWidth < 768; }

    // ---------------------------------------------------------------
    // 5. REST API
    // ---------------------------------------------------------------
    async function fetchFromRest(endpoint, params) {
        var qs = params ? '?' + new URLSearchParams(params).toString() : '';
        var url = CONFIG.restBase + '/' + endpoint + qs;
        try {
            var res = await fetch(url);
            if (!res.ok) { console.warn('API fail:', url, res.status); return null; }
            return await res.json();
        } catch (err) { console.error('API error:', err); return null; }
    }

    function fetchMarkers(params) {
        return fetchFromRest(CONFIG.markersEndpoint, { ...(params||{}) });
    }

    function fetchPosts(params) {
        return fetchFromRest(CONFIG.postsEndpoint, { per_page: CONFIG.postsPerPage, _embed: '1', ...(params||{}) });
    }

    // ---------------------------------------------------------------
    // 6. Photo Data Processing
    //
    // Markers come from the sphotography/v1/photos endpoint (or the inline
    // data mirror): one entry per geolocated image, each carrying the parent
    // post id so a marker can open its article.
    // ---------------------------------------------------------------
    function buildGeoJSONFromMarkers(markers) {
        var features = [];
        var needGcj = chinaNeedsGcj();
        (markers || []).forEach(function (m) {
            var lat = parseFloat(m.latitude) || 0;
            var lng = parseFloat(m.longitude) || 0;
            if (lat === 0 && lng === 0) return;

            // Under a GCJ-02 provider, plot at the shifted position so markers
            // align with the basemap; keep the raw WGS-84 for readouts and
            // reverse-geocoding (which must use true GPS).
            var coords = needGcj ? wgs84ToGcj02(lng, lat) : [lng, lat];
            var tags = Array.isArray(m.tags) ? m.tags : [];
            features.push({
                type: 'Feature',
                geometry: { type: 'Point', coordinates: coords },
                properties: {
                    id: m.id,
                    wgsLng: lng,
                    wgsLat: lat,
                    postId: (m.post_id !== undefined ? m.post_id : m.postId) || null,
                    postTitle: m.post_title || m.postTitle || '',
                    title: m.title || m.post_title || 'Untitled',
                    description: m.description || '',
                    thumbnail: m.thumbnail || '',
                    fullImage: m.full_image || m.fullImage || '',
                    cameraInfo: m.camera_info || m.cameraInfo || '',
                    takenAt: m.taken_at || m.takenAt || '',
                    tags: tags,
                    tagSlugs: tags.map(function (t) { return t.slug; }),
                    provAdcode: m.prov_adcode || m.provAdcode || '',
                    cityAdcode: m.city_adcode || m.cityAdcode || '',
                },
            });
        });
        return { type: 'FeatureCollection', features: features };
    }

    // ---------------------------------------------------------------
    // 6b. Night-mode switch (v1.3.2)
    //
    // A vertical three-segment control stacked below the zoom/compass in the
    // top-right. Icons only (sun / moon / monitor). Toggling swaps the body
    // night class immediately and, when the auto basemap is active, re-applies
    // the resolved style and re-adds our photo layers.
    // ---------------------------------------------------------------
    var NIGHT_ICONS = {
        light: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="4"/><line x1="12" y1="20" x2="12" y2="22"/><line x1="4.93" y1="4.93" x2="6.34" y2="6.34"/><line x1="17.66" y1="17.66" x2="19.07" y2="19.07"/><line x1="2" y1="12" x2="4" y2="12"/><line x1="20" y1="12" x2="22" y2="12"/><line x1="4.93" y1="19.07" x2="6.34" y2="17.66"/><line x1="17.66" y1="6.34" x2="19.07" y2="4.93"/></svg>',
        dark: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>',
        system: '<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="14" rx="2"/><line x1="8" y1="21" x2="16" y2="21"/><line x1="12" y1="17" x2="12" y2="21"/></svg>'
    };
    var NIGHT_LABELS = { light: '浅色', dark: '深色', system: '跟随系统' };

    function updateNightSwitchUI() {
        var btns = document.querySelectorAll('.sp-night-switch .sp-night-btn');
        Array.prototype.forEach.call(btns, function (b) {
            var on = b.getAttribute('data-mode') === nightMode;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    // Re-apply the auto basemap after a night-mode change: setStyle preserves
    // the camera; on idle we re-add the photo source/layers (and region layers)
    // the new style dropped, then re-sync the DOM droplets.
    function reapplyAutoStyle() {
        if (!state.map) return;
        try {
            state.map.setStyle(autoStyle());
        } catch (e) {
            return;
        }
        state.map.once('idle', function () {
            if (!state.map.getSource(CONFIG.clusterSourceId)) {
                addPhotoSource(REGION.active ? state.regionUnmatched : state.allPhotos);
                addPhotoLayers();
                if (REGION.active) addRegionLayers();
            }
            if (typeof syncDroplets === 'function') syncDroplets();
        });
    }

    function applyNightMode(mode) {
        if (mode !== 'light' && mode !== 'dark' && mode !== 'system') mode = 'system';
        if (mode === nightMode) return;
        var wasLight = resolveMapIsLight();
        nightMode = mode;
        try { localStorage.setItem(NIGHT_STORAGE_KEY, mode); } catch (e) {}

        var b = document.body;
        b.classList.remove('sphotography-night-force-dark', 'sphotography-night-force-light', 'sphotography-night-system');
        b.classList.add(mode === 'dark' ? 'sphotography-night-force-dark'
            : mode === 'light' ? 'sphotography-night-force-light'
            : 'sphotography-night-system');

        updateNightSwitchUI();

        // Only the auto basemap tracks light/dark; other presets keep their
        // tiles. Re-apply just once the resolved lightness actually changed.
        if (usingAutoStyle() && resolveMapIsLight() !== wasLight) {
            reapplyAutoStyle();
        }
        // China basemaps have no dark tiles → toggle the pseudo-dark canvas
        // filter to match the new resolved lightness.
        applyChinaDarkFilter();
    }

    // In "system" mode, follow live OS light/dark changes: re-apply the auto
    // basemap (global) and/or the China pseudo-dark filter when the OS flips.
    (function watchSystemTheme() {
        if (!window.matchMedia) return;
        var mq = window.matchMedia('(prefers-color-scheme: dark)');
        var onChange = function () {
            if (nightMode !== 'system' || !state.map) return;
            if (usingAutoStyle()) reapplyAutoStyle();
            applyChinaDarkFilter();
        };
        if (mq.addEventListener) mq.addEventListener('change', onChange);
        else if (mq.addListener) mq.addListener(onChange); // Safari < 14
    })();

    function NightSwitchControl() {}
    NightSwitchControl.prototype.onAdd = function (map) {
        this._map = map;
        var c = document.createElement('div');
        c.className = 'maplibregl-ctrl maplibregl-ctrl-group sp-night-switch';
        c.setAttribute('role', 'group');
        c.setAttribute('aria-label', '明暗模式');
        ['light', 'dark', 'system'].forEach(function (m) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sp-night-btn';
            btn.setAttribute('data-mode', m);
            btn.title = NIGHT_LABELS[m];
            btn.setAttribute('aria-label', NIGHT_LABELS[m]);
            btn.innerHTML = NIGHT_ICONS[m];
            btn.addEventListener('click', function () { applyNightMode(m); });
            c.appendChild(btn);
        });
        this._container = c;
        // Defer to next frame so the buttons exist before we mark the active one.
        requestAnimationFrame(updateNightSwitchUI);
        return c;
    };
    NightSwitchControl.prototype.onRemove = function () {
        if (this._container && this._container.parentNode) {
            this._container.parentNode.removeChild(this._container);
        }
        this._map = undefined;
    };

    // ---------------------------------------------------------------
    // 6c. Site language (v1.4.3) — 中 / 英 / 日 three-segment switch.
    //
    // A second vertical control stacked directly below the night switch,
    // same dimensions/style. Glyphs 中 / A / あ. Only added when AI is
    // enabled (dynamic content translation needs the text model).
    //
    // Chinese is the native no-op language: no model calls, originals shown.
    // English / Japanese translate DYNAMIC content (article title+body,
    // AI summary, comment/guestbook bodies, photo captions) via the
    // /translate REST endpoint (server-side, cached per language). UI chrome
    // uses the static dictionary below — never the model. Names, signatures,
    // place names and EXIF are never sent (we only pass body text).
    // ---------------------------------------------------------------
    var LANG_STORAGE_KEY = 'sp-site-lang';
    var LANG_GLYPHS = { zh: '中', en: 'A', ja: 'あ' };
    var LANG_LABELS = { zh: '中文', en: 'English', ja: '日本語' };

    // Static UI dictionary. Keyed by the original Chinese string. zh returns
    // the original. Any string not present falls back to the original (stays
    // Chinese) — acceptable per the agreed design; extend as needed.
    var I18N_DICT = {
        en: {
            '浅色': 'Light', '深色': 'Dark', '跟随系统': 'System', '明暗模式': 'Appearance',
            '中文': 'Chinese', 'English': 'English', '日本语': 'Japanese', '站点语言': 'Language',
            '翻译中…': 'Translating…',
            '解析中…': 'Resolving…',
            'AI 概述': 'AI summary',
            '匿名': 'Anonymous',
            '暂无内容': 'No content',
            '文章加载失败': 'Failed to load article',
            '评论': 'Comments', '回复': 'Reply', '编辑': 'Edit', '置顶': 'Pin', '取消置顶': 'Unpin',
            '已编辑': 'edited', '时间': 'Time', '点赞': 'Likes',
            '加载中…': 'Loading…',
            '还没有评论，来抢沙发吧。': 'No comments yet — be the first.',
            '评论已关闭。': 'Comments are closed.',
            '展开阅读全文': 'Read more',
            '阅读量': 'Views', '撰写地点': 'Written at', 'IP 属地': 'IP location',
            '回到顶部': 'Back to top', '到正文末尾': 'Jump to end of text', '阅读进度': 'Reading progress', '跳到评论': 'Jump to comments',
            '昵称 *': 'Nickname *', '邮箱（不公开）*': 'Email (private) *',
            '插入表情': 'Insert emoji', '粗体': 'Bold', '斜体': 'Italic', '删除线': 'Strikethrough', '行内代码': 'Inline code',
            '日期时间': 'Date & time', '经纬度': 'Coordinates', '拍摄设备': 'Camera', '光圈快门ISO': 'Aperture / Shutter / ISO', '暂无参数': 'No metadata',
            // v1.4.4 (item 1): page-links bar + side-panel chrome
            '友链': 'Links', '留言': 'Guestbook', '照片墙': 'Photos', '公告': 'Notice',
            '还没有友链。': 'No links yet.', '申请友链': 'Apply for a link',
            '你的邮箱 *': 'Your email *', '你的网站链接 *': 'Your site URL *', '站点名称（可选）': 'Site name (optional)',
            '留言（可选）': 'Message (optional)', '提交申请': 'Submit', '请填写邮箱和链接。': 'Please fill in email and URL.', '提交中…': 'Submitting…',
            '还没有照片。': 'No photos yet.', '写下留言…支持 Markdown': 'Write a message… Markdown supported',
            '申请已提交，等待站长审核。': 'Submitted — awaiting the admin’s review.',
            '查看照片位置': 'View photo location', '查看对应文章': 'View the article', '查看照片详情': 'Photo details', '上一张': 'Previous', '下一张': 'Next', '退出': 'Close',
            '点击一张照片，定位其位置': 'Click a photo to locate it',
            '文章目录': 'Contents', '本文暂无目录': 'No headings in this article',
            // v1.4.8 (item 2): expand-page + stats panel
            '文章列表': 'Articles', '搜索文章...': 'Search articles...', '没有找到匹配的文章': 'No matching articles',
            '打开文章列表': 'Open article list', '文章': 'Posts', '标签': 'Tags', '地块': 'Regions',
            '图片张数': 'Photos', '本日访问': 'Today', '累计访问': 'Total visits', '已运行': 'Uptime',
            '天': 'd', '其他': 'Others', '暂无地区数据': 'No region data', '图片地区分布': 'Photos by region'
        },
        ja: {
            '浅色': 'ライト', '深色': 'ダーク', '跟随系统': 'システム', '明暗模式': '外観',
            '中文': '中国語', 'English': '英語', '日本语': '日本語', '站点语言': '言語',
            '翻译中…': '翻訳中…',
            '解析中…': '解析中…',
            'AI 概述': 'AI 概要',
            '匿名': '匿名',
            '暂无内容': 'コンテンツがありません',
            '文章加载失败': '記事の読み込みに失敗しました',
            '评论': 'コメント', '回复': '返信', '编辑': '編集', '置顶': '固定', '取消置顶': '固定解除',
            '已编辑': '編集済み', '时间': '時間', '点赞': 'いいね',
            '加载中…': '読み込み中…',
            '还没有评论，来抢沙发吧。': 'まだコメントがありません。最初のコメントをどうぞ。',
            '评论已关闭。': 'コメントは締め切られました。',
            '展开阅读全文': '全文を読む',
            '阅读量': '閲覧数', '撰写地点': '執筆地', 'IP 属地': 'IP 所在地',
            '回到顶部': '先頭へ', '到正文末尾': '本文末尾へ', '阅读进度': '読書進捗', '跳到评论': 'コメントへ',
            '昵称 *': 'ニックネーム *', '邮箱（不公开）*': 'メール（非公開）*',
            '插入表情': '絵文字を挿入', '粗体': '太字', '斜体': '斜体', '删除线': '取り消し線', '行内代码': 'インラインコード',
            '日期时间': '日時', '经纬度': '緯度経度', '拍摄设备': 'カメラ', '光圈快门ISO': '絞り / シャッター / ISO', '暂无参数': 'データなし',
            // v1.4.4 (item 1): page-links bar + side-panel chrome
            '友链': 'リンク', '留言': 'ゲストブック', '照片墙': '写真', '公告': 'お知らせ',
            '还没有友链。': 'まだリンクがありません。', '申请友链': 'リンクを申請',
            '你的邮箱 *': 'あなたのメール *', '你的网站链接 *': 'あなたのサイト URL *', '站点名称（可选）': 'サイト名（任意）',
            '留言（可选）': 'メッセージ（任意）', '提交申请': '申請する', '请填写邮箱和链接。': 'メールと URL を入力してください。', '提交中…': '送信中…',
            '还没有照片。': 'まだ写真がありません。', '写下留言…支持 Markdown': 'メッセージを書く… Markdown 対応',
            '申请已提交，等待站长审核。': '申請を送信しました。管理者の承認をお待ちください。',
            '查看照片位置': '写真の位置を表示', '查看对应文章': '記事を表示', '查看照片详情': '写真の詳細', '上一张': '前へ', '下一张': '次へ', '退出': '閉じる',
            '点击一张照片，定位其位置': '写真をクリックして撮影場所を表示',
            '文章目录': '目次', '本文暂无目录': 'この記事に見出しはありません',
            // v1.4.8 (item 2): expand-page + stats panel
            '文章列表': '記事一覧', '搜索文章...': '記事を検索...', '没有找到匹配的文章': '一致する記事がありません',
            '打开文章列表': '記事一覧を開く', '文章': '記事', '标签': 'タグ', '地块': '地域',
            '图片张数': '写真枚数', '本日访问': '本日', '累计访问': '累計訪問', '已运行': '稼働時間',
            '天': '日', '其他': 'その他', '暂无地区数据': '地域データなし', '图片地区分布': '地域別写真分布'
        }
    };

    function detectSiteLang() {
        var n = (navigator.language || navigator.userLanguage || '').toLowerCase();
        if (n.indexOf('zh') === 0) return 'zh';
        if (n.indexOf('ja') === 0) return 'ja';
        if (n.indexOf('en') === 0) return 'en';
        return 'en'; // 非中英日 → 英语
    }
    function readStoredLang() {
        try { var v = localStorage.getItem(LANG_STORAGE_KEY); if (v === 'zh' || v === 'en' || v === 'ja') return v; } catch (e) {}
        return null;
    }
    // 优先级：已保存的手动选择 → 系统语言 → 英语。
    var siteLang = readStoredLang() || detectSiteLang();

    // t(): static UI-string lookup. Chinese passthrough; missing keys fall back
    // to the original string.
    function t(zh) {
        if (siteLang === 'zh') return zh;
        var d = I18N_DICT[siteLang];
        return (d && Object.prototype.hasOwnProperty.call(d, zh)) ? d[zh] : zh;
    }

    // ---- Dynamic content translation engine ----------------------------
    // Elements carrying [data-sp-tr] are translated in place. Their original
    // (source-language) content is stashed on the element as _spOrig so that
    // switching back to Chinese — or re-translating into another language —
    // always works from the source, never from a prior translation.
    var i18nPending = 0;
    function i18nIndicator() {
        var el = document.getElementById('sp-tr-indicator');
        if (!el) {
            el = document.createElement('div');
            el.id = 'sp-tr-indicator';
            el.setAttribute('aria-live', 'polite');
            document.body.appendChild(el);
        }
        el.textContent = t('翻译中…');
        el.classList.toggle('is-active', i18nPending > 0);
        return el;
    }

    function i18nSetContent(el, val, fade) {
        if (el._spFmt === 'html') { el.innerHTML = val; } else { el.textContent = val; }
        if (typeof el._spAfter === 'function') { try { el._spAfter(el); } catch (e) {} }
        if (fade) {
            el.classList.remove('sp-tr-fade');
            // reflow to restart the transition
            void el.offsetWidth;
            el.classList.add('sp-tr-fade');
        }
    }

    // Register an element as translatable. format: 'text' | 'html'. after: an
    // optional callback re-run whenever the element's content is (re)written
    // (used by the article body to re-wire images/links after a swap).
    function i18nRegister(el, format, after) {
        if (!el) return;
        el._spFmt = (format === 'html') ? 'html' : 'text';
        el._spOrig = (el._spFmt === 'html') ? el.innerHTML : el.textContent;
        if (after) el._spAfter = after;
        el.setAttribute('data-sp-tr', '1');
        if (siteLang !== 'zh') i18nTranslateEls([el], siteLang);
    }

    // v1.4.4 (item 1): register an element that already has server-side
    // pre-generated translations (article title / body / summary, generated in
    // the background on save). `pregen` maps lang → translated string. When the
    // active language has a pre-generated value, the swap is instant with ZERO
    // model call; languages without one fall back to the on-demand /translate
    // path. Chinese always restores the original.
    function i18nRegisterPregen(el, format, pregen, after) {
        if (!el) return;
        el._spPregen = (pregen && typeof pregen === 'object') ? pregen : null;
        i18nRegister(el, format, after);
    }

    // Scan a subtree for known translatable content nodes (comment/guestbook
    // bodies) and register any not yet marked.
    function i18nScan(root) {
        if (!root || !root.querySelectorAll) return;
        var nodes = root.querySelectorAll('.comment-text:not([data-sp-tr])');
        Array.prototype.forEach.call(nodes, function (n) { i18nRegister(n, 'html'); });
    }

    function translateSegments(segments, lang) {
        return fetch(CONFIG.restBase + '/sphotography/v1/translate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': APP.restNonce || '' },
            body: JSON.stringify({ lang: lang, segments: segments })
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) { return (d && d.segments) ? d.segments : null; })
          .catch(function () { return null; });
    }

    // Translate a specific list of registered elements into lang. Chinese
    // restores originals with no network call. On failure each element silently
    // falls back to its original.
    function i18nTranslateEls(els, lang) {
        if (lang === 'zh') {
            els.forEach(function (el) { el.classList.remove('sp-tr-loading'); i18nSetContent(el, el._spOrig, true); });
            return;
        }
        var segs = [], map = {};
        els.forEach(function (el, i) {
            var orig = el._spOrig;
            if (orig == null || !String(orig).trim()) return;
            // v1.4.4 (item 1): pre-generated translation available for this
            // language → apply instantly, skip the model call entirely.
            if (el._spPregen && typeof el._spPregen[lang] === 'string' && el._spPregen[lang].trim()) {
                el.classList.remove('sp-tr-loading');
                i18nSetContent(el, el._spPregen[lang], true);
                return;
            }
            var id = 's' + i + '_' + Math.random().toString(36).slice(2, 7);
            map[id] = el;
            el.classList.add('sp-tr-loading');
            segs.push({ id: id, text: orig, format: el._spFmt });
        });
        if (!segs.length) return;
        i18nPending += 1; i18nIndicator();
        translateSegments(segs, lang).then(function (res) {
            i18nPending -= 1; i18nIndicator();
            // If the user switched language again mid-flight, drop stale results.
            if (siteLang !== lang) return;
            Object.keys(map).forEach(function (id) {
                var el = map[id];
                el.classList.remove('sp-tr-loading');
                var tr = (res && res[id] && String(res[id]).trim()) ? res[id] : el._spOrig;
                i18nSetContent(el, tr, true);
            });
        });
    }

    // Re-translate everything currently marked on the page (used on switch).
    function i18nRetranslateAll() {
        var els = Array.prototype.slice.call(document.querySelectorAll('[data-sp-tr]'));
        if (els.length) i18nTranslateEls(els, siteLang);
    }

    // Update persistent UI chrome (controls that are NOT re-rendered) on switch.
    function applyUiLang() {
        document.documentElement.setAttribute('lang', siteLang);
        // Night switch labels/titles.
        Array.prototype.forEach.call(document.querySelectorAll('.sp-night-switch .sp-night-btn'), function (b) {
            var m = b.getAttribute('data-mode');
            if (NIGHT_LABELS[m]) { b.title = t(NIGHT_LABELS[m]); b.setAttribute('aria-label', t(NIGHT_LABELS[m])); }
        });
        i18nIndicator();
        if (typeof updatePageLinkLabels === 'function') updatePageLinkLabels(); // v1.4.4: 页面链接栏按钮
        if (typeof updateAnnouncementChrome === 'function') updateAnnouncementChrome(); // v1.4.4: 公告面板 chrome
    }

    function applyLang(lang) {
        if (lang !== 'zh' && lang !== 'en' && lang !== 'ja') lang = 'en';
        if (lang === siteLang) return;
        siteLang = lang;
        try { localStorage.setItem(LANG_STORAGE_KEY, lang); } catch (e) {}
        updateLangSwitchUI();
        applyUiLang();
        // Live-flip already-rendered dynamic content.
        i18nRetranslateAll();
        // Reload the open comments so their chrome labels flip too (bodies then
        // re-register for translation via i18nScan on inject).
        if (typeof cState !== 'undefined' && cState && cState.postId && dom.articleComments) {
            var listEl = dom.articleComments.querySelector('#comment-list');
            var countEl = dom.articleComments.querySelector('.comments-count');
            if (listEl && countEl && typeof loadCommentPage === 'function') {
                if (typeof updateCommentSortUI === 'function') updateCommentSortUI();
                listEl.innerHTML = '<li class="comments-loading">' + t('加载中…') + '</li>';
                loadCommentPage(cState.postId, 1, true, listEl, countEl);
            }
        }
    }

    function updateLangSwitchUI() {
        Array.prototype.forEach.call(document.querySelectorAll('.sp-lang-switch .sp-lang-btn'), function (b) {
            var on = b.getAttribute('data-lang') === siteLang;
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function LangSwitchControl() {}
    LangSwitchControl.prototype.onAdd = function (map) {
        this._map = map;
        var c = document.createElement('div');
        c.className = 'maplibregl-ctrl maplibregl-ctrl-group sp-lang-switch';
        c.setAttribute('role', 'group');
        c.setAttribute('aria-label', t('站点语言'));
        ['zh', 'en', 'ja'].forEach(function (m) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'sp-lang-btn';
            btn.setAttribute('data-lang', m);
            btn.title = LANG_LABELS[m];
            btn.setAttribute('aria-label', LANG_LABELS[m]);
            btn.textContent = LANG_GLYPHS[m];
            btn.addEventListener('click', function () { applyLang(m); });
            c.appendChild(btn);
        });
        this._container = c;
        requestAnimationFrame(updateLangSwitchUI);
        return c;
    };
    LangSwitchControl.prototype.onRemove = function () {
        if (this._container && this._container.parentNode) {
            this._container.parentNode.removeChild(this._container);
        }
        this._map = undefined;
    };

    // ---------------------------------------------------------------
    // 7. Map
    // ---------------------------------------------------------------
    function initMap() {
        state.map = new maplibregl.Map({
            container:'map', style:CONFIG.styleUrl,
            center:CONFIG.center, zoom:CONFIG.zoom,
            maxZoom:CONFIG.maxZoom, minZoom:CONFIG.minZoom,
            attributionControl:true,
            bearing: 0, pitch: 0, maxPitch: 0,
            dragRotate: false, pitchWithRotate: false, touchPitch: false,
        });
        state.map.touchZoomRotate.disableRotation();
        state.map.keyboard.disableRotation();
        state.map.addControl(new maplibregl.NavigationControl({showCompass:false}),'top-right');
        // Stacks in top-right immediately below the zoom group.
        state.map.addControl(new NightSwitchControl(), 'top-right');
        // v1.4.3: language switch stacks directly below the night switch. Only
        // shown when the translation feature is on (v1.4.4: dedicated ai_translate
        // toggle, falls back to aiEnabled for older configs).
        if (APP.translateEnabled || (typeof APP.translateEnabled === 'undefined' && APP.aiEnabled)) {
            state.map.addControl(new LangSwitchControl(), 'top-right');
            // Reflect the resolved language on persistent chrome at first paint
            // (night-switch titles, <html lang>). Content translates lazily as
            // it is opened via i18nRegister.
            applyUiLang();
        }
        state.map.addControl(new maplibregl.ScaleControl({unit:'metric',maxWidth:120}),'bottom-left');
        state.map.on('load', function() {
            state.mapLoaded = true;
            // Region mode: only the unmatched photos become droplets; matched
            // ones are represented by the filled regions instead.
            addPhotoSource(REGION.active ? state.regionUnmatched : state.allPhotos);
            addPhotoLayers();
            if (REGION.active) addRegionLayers();
            bindMapEvents();
            applyChinaDarkFilter(); // China basemaps: pseudo-dark if theme is dark
            hideLoading();
        });
        state.map.on('error', function(e) {
            var msg = (e && e.error && e.error.message) || e;
            console.warn('Map error:', msg);
            // A custom/preset style that fails to load before the map ever
            // finishes: fall back once to the auto (night-mode) basemap so the
            // map still renders rather than dying on a broken style URL.
            if (!state.mapLoaded && !state.styleFellBack && !usingAutoStyle()) {
                state.styleFellBack = true;
                applyAutoFallbackStyle();
                return;
            }
            onMapFatalError();
        });

        state.map.on('move', function() {
            positionAllPhotoPanels();
            positionDroplets();
        });

        // Re-evaluate clustering the moment movement stops rather than
        // waiting for full map 'idle' (which also waits on tile loads) —
        // keeps merge/split latency well under 200ms. 'idle' stays as a
        // fallback for changes without movement (e.g. data updates).
        var scheduleClusterSync = debounce(function() {
            reconcileOpenPhotoPanels();
            syncDroplets();
        }, 90);
        state.map.on('moveend', scheduleClusterSync);
        state.map.on('idle', scheduleClusterSync);

        window.addEventListener('resize', debounce(function() {
            if (state.map) state.map.resize();
            var m = isMobileView();
            if (m !== state.isMobile) { state.isMobile = m; }
        }, 200));
    }

    // Swap the failed style for the auto basemap, then re-add our photo source
    // and layers and finish the load sequence the normal 'load' path would have.
    function applyAutoFallbackStyle() {
        try {
            state.map.setStyle(autoStyle());
        } catch (err) {
            onMapFatalError();
            return;
        }
        state.map.once('idle', function() {
            if (!state.map.getSource(CONFIG.clusterSourceId)) {
                addPhotoSource(REGION.active ? state.regionUnmatched : state.allPhotos);
                addPhotoLayers();
                if (REGION.active) addRegionLayers();
            }
            if (!state.mapLoaded) {
                state.mapLoaded = true;
                bindMapEvents();
                hideLoading();
            }
            // We're now on the global auto fallback, not a China provider →
            // make sure the pseudo-dark invert filter is cleared.
            applyChinaDarkFilter();
        });
    }

    function addPhotoSource(geojson) {
        var data = geojson || {type:'FeatureCollection',features:[]};
        [CONFIG.clusterSourceId, CONFIG.sourceId].forEach(function(id){if(state.map.getSource(id))state.map.removeSource(id);});
        state.map.addSource(CONFIG.clusterSourceId, {
            type:'geojson', data:data, cluster:true,
            // Cluster strictly by physical overlap: an individual droplet is
            // 18px across (radius 9), so two markers only overlap when their
            // centres are closer than that diameter. Points farther apart
            // render without overlap and stay separate.
            clusterMaxZoom:CONFIG.maxZoom, clusterRadius:CONFIG.clusterRadius, clusterMinPoints:2
        });
    }

    function addPhotoLayers() {
        [CONFIG.clusterCountLayerId, CONFIG.clusterLayerId, CONFIG.layerId].forEach(function(id){if(state.map.getLayer(id))state.map.removeLayer(id);});
        // The circle/count layers are kept for hit-testing and cluster
        // queries but rendered invisible — the HTML water-droplet overlay
        // is the visible marker. queryRenderedFeatures still returns
        // opacity-0 features, so clicks and panel reconciliation are intact.
        state.map.addLayer({id:CONFIG.clusterLayerId,type:'circle',source:CONFIG.clusterSourceId,filter:['has','point_count'],paint:{'circle-color':'#e67e22','circle-radius':['step',['get','point_count'],18,10,22,50,28,200,36],'circle-opacity':0,'circle-stroke-width':2,'circle-stroke-color':'#ffffff','circle-stroke-opacity':0}});
        state.map.addLayer({id:CONFIG.clusterCountLayerId,type:'symbol',source:CONFIG.clusterSourceId,filter:['has','point_count'],layout:{'text-field':'{point_count_abbreviated}','text-size':12},paint:{'text-color':'#ffffff','text-opacity':0}});
        state.map.addLayer({id:CONFIG.layerId,type:'circle',source:CONFIG.clusterSourceId,filter:['!',['has','point_count']],paint:{'circle-color':CONFIG.markerColor,'circle-radius':CONFIG.markerRadius,'circle-stroke-width':CONFIG.markerBorderWidth,'circle-stroke-color':CONFIG.markerBorderColor,'circle-opacity':0,'circle-stroke-opacity':0}});
        state.map.on('mouseenter',CONFIG.layerId,function(){state.map.getCanvas().style.cursor='pointer';});
        state.map.on('mouseleave',CONFIG.layerId,function(){state.map.getCanvas().style.cursor='';});
        state.map.on('mouseenter',CONFIG.clusterLayerId,function(){state.map.getCanvas().style.cursor='pointer';});
        state.map.on('mouseleave',CONFIG.clusterLayerId,function(){state.map.getCanvas().style.cursor='';});
    }

    function updatePhotoData(geojson) {
        if (!state.map||!(state.map.isStyleLoaded()||state.map.loaded())) return;
        var source=state.map.getSource(CONFIG.clusterSourceId);
        if(source&&typeof source.setData==='function')source.setData(geojson);
    }

    // ---------------------------------------------------------------
    // 7b. Region fill layers (region mode)
    // ---------------------------------------------------------------
    function addRegionLayers() {
        if (!REGION.used) return;
        [REGION.hoverLayerId, REGION.lineLayerId, REGION.fillLayerId].forEach(function (id) {
            if (state.map.getLayer(id)) state.map.removeLayer(id);
        });
        if (state.map.getSource(REGION.sourceId)) state.map.removeSource(REGION.sourceId);

        state.map.addSource(REGION.sourceId, { type: 'geojson', data: REGION.used, promoteId: 'id' });
        // Base fill (theme colour at the configured intensity).
        state.map.addLayer({
            id: REGION.fillLayerId, type: 'fill', source: REGION.sourceId,
            paint: { 'fill-color': PRIMARY_COLOR, 'fill-opacity': REGION.opacity }
        });
        // Hover highlight — a brighter overlay for the feature under the cursor.
        state.map.addLayer({
            id: REGION.hoverLayerId, type: 'fill', source: REGION.sourceId,
            paint: {
                'fill-color': PRIMARY_COLOR,
                'fill-opacity': ['case', ['boolean', ['feature-state', 'hover'], false], Math.min(1, REGION.opacity + 0.25), 0]
            }
        });
        // Outline for legibility.
        state.map.addLayer({
            id: REGION.lineLayerId, type: 'line', source: REGION.sourceId,
            paint: { 'line-color': PRIMARY_COLOR, 'line-width': 1, 'line-opacity': 0.85 }
        });

        var hoveredId = null;
        var setHover = function (id, on) {
            if (id == null) return;
            state.map.setFeatureState({ source: REGION.sourceId, id: id }, { hover: on });
        };
        state.map.on('mousemove', REGION.fillLayerId, function (e) {
            if (!e.features || !e.features.length) return;
            state.map.getCanvas().style.cursor = 'pointer';
            var id = e.features[0].id != null ? e.features[0].id : (e.features[0].properties && e.features[0].properties.id);
            if (id !== hoveredId) { setHover(hoveredId, false); hoveredId = id; setHover(hoveredId, true); }
        });
        state.map.on('mouseleave', REGION.fillLayerId, function () {
            state.map.getCanvas().style.cursor = '';
            setHover(hoveredId, false); hoveredId = null;
        });
        state.map.on('click', REGION.fillLayerId, function (e) {
            if (!e.features || !e.features.length) return;
            state.clickedMarker = true; // suppress the map-background close handler
            var props = e.features[0].properties || {};
            var id = props.id != null ? String(props.id) : '';
            if (id) {
                if (photoClickThrottled('region:' + id)) { if (e.originalEvent) e.originalEvent.stopPropagation(); return; }
                ++state.photoClickSeq; // supersede any pending cluster callback
                openRegionPanel(id);
            }
            if (e.originalEvent) e.originalEvent.stopPropagation();
        });
    }

    // ---------------------------------------------------------------
    // 8. Map Events
    // ---------------------------------------------------------------
    function bindMapEvents() {
        state.map.on('click', CONFIG.layerId, function(e) {
            if (!e.features||e.features.length===0) return;
            state.clickedMarker = true;
            var id = photoId(e.features[0].properties);
            if (id) {
                if (photoClickThrottled('photo:' + id)) { if (e.originalEvent) e.originalEvent.stopPropagation(); return; }
                ++state.photoClickSeq; // supersede any pending cluster callback
                state.openPhotoIds.clear();
                state.openPhotoIds.add(id);
                reconcileOpenPhotoPanels();
            }
            if (e.originalEvent) e.originalEvent.stopPropagation();
        });

        state.map.on('click', CONFIG.clusterLayerId, function(e) {
            if (!e.features||e.features.length===0) return;
            state.clickedMarker = true;
            var clusterFeature = e.features[0];
            var cid = clusterFeature.properties && clusterFeature.properties.cluster_id;
            // (c) same-target opening-lock: swallow rapid repeat clicks on the
            //     same cluster so a "did it work?" double-click can't spawn a
            //     second panel while the first is still resolving.
            if (photoClickThrottled('cluster:' + cid)) { if (e.originalEvent) e.originalEvent.stopPropagation(); return; }
            // (a) sequence guard: getClusterLeaves is async; if another click
            //     (or a close) happens first, this stale callback must bail.
            var seq = ++state.photoClickSeq;
            getClusterLeaves(clusterFeature).then(function(leaves) {
                if (seq !== state.photoClickSeq) return;
                state.openPhotoIds.clear();
                leaves.forEach(function(leaf) {
                    var id = photoId(leaf.properties);
                    if (id) state.openPhotoIds.add(id);
                });
                reconcileOpenPhotoPanels();
            });
            if (e.originalEvent) e.originalEvent.stopPropagation();
        });

        state.map.on('click', function() {
            if (state.clickedMarker) {
                state.clickedMarker = false;
                return;
            }
            closeAllPhotoPanels();
            closeArticlePanel();
            closeAllSidePanels();
            if (state.isMobile) { closeSidebar(); }
        });

        // Pulse dot + location popup: any manual pan/zoom is the "next
        // interaction" that clears them. Bound in BOTH modes (v1.4.4 item 4 shows
        // the dot in normal mode too). Fires harmlessly before a dot exists.
        state.map.on('dragstart', removePulseDot);
        state.map.on('zoomstart', removePulseDot);
    }

    // ---------------------------------------------------------------
    // 8b. Cluster split/merge reconciliation
    // ---------------------------------------------------------------
    function photoId(props) {
        return props && props.id !== undefined && props.id !== null ? String(props.id) : '';
    }

    // Opening-lock (v1.3.8): true if the same map target was clicked within the
    // last 450ms — swallows accidental double-clicks that would otherwise queue
    // a second panel while the first is still resolving.
    function photoClickThrottled(key) {
        var now = Date.now();
        var last = state.photoClickLock;
        if (last && last.key === key && (now - last.time) < 450) { return true; }
        state.photoClickLock = { key: key, time: now };
        return false;
    }

    function getClusterLeaves(feature) {
        return new Promise(function(resolve) {
            var source = state.map && state.map.getSource(CONFIG.clusterSourceId);
            var props = feature && feature.properties;
            if (!source || !props || typeof source.getClusterLeaves !== 'function') {
                resolve([]);
                return;
            }
            var count = Math.max(2, parseInt(props.point_count, 10) || 2);
            if (source.getClusterLeaves.length >= 4) {
                source.getClusterLeaves(props.cluster_id, count, 0, function(err, leaves) {
                    resolve(err || !leaves ? [] : leaves);
                });
                return;
            }
            var result;
            try {
                result = source.getClusterLeaves(props.cluster_id, count, 0);
            } catch (err) {
                resolve([]);
                return;
            }
            if (result && typeof result.then === 'function') {
                result.then(function(leaves) { resolve(leaves || []); })
                    .catch(function() { resolve([]); });
            } else {
                resolve(result || []);
            }
        });
    }

    function entityFromFeature(feature) {
        if (feature.properties && feature.properties.cluster_id !== undefined) {
            return getClusterLeaves(feature).then(function(leaves) {
                return { coords: feature.geometry.coordinates, photos: leaves };
            });
        }
        return Promise.resolve({ coords: feature.geometry.coordinates, photos: [feature] });
    }

    function reconcileOpenPhotoPanels() {
        if (!state.map || !state.map.isStyleLoaded()) return;
        var token = ++state.reconcileToken;
        if (state.openPhotoIds.size === 0) {
            dismissAllPhotoPanels();
            return;
        }

        var rendered = state.map.queryRenderedFeatures(undefined, {
            layers: [CONFIG.clusterLayerId, CONFIG.layerId]
        });
        var seenRendered = new Set();
        var tasks = [];
        rendered.forEach(function(feature) {
            if (!feature.geometry || !feature.properties) return;
            var renderedKey = feature.properties.cluster_id !== undefined
                ? 'cluster:' + feature.properties.cluster_id
                : 'photo:' + photoId(feature.properties);
            if (seenRendered.has(renderedKey)) return;
            seenRendered.add(renderedKey);
            tasks.push(entityFromFeature(feature));
        });

        Promise.all(tasks).then(function(entities) {
            if (token !== state.reconcileToken) return;
            var next = new Map();
            entities.forEach(function(entity) {
                entity.photos = entity.photos.filter(function(photo) { return !!photoId(photo.properties); });
                var ids = entity.photos.map(function(photo) { return photoId(photo.properties); }).sort();
                if (!ids.some(function(id) { return state.openPhotoIds.has(id); })) return;
                ids.forEach(function(id) { state.openPhotoIds.add(id); });
                entity.ids = ids;
                entity.key = 'members:' + ids.join(',');
                next.set(entity.key, entity);
            });
            renderVisibleEntities(next);
        });
    }

    // ---------------------------------------------------------------
    // 8c. Water-droplet (gooey) cluster markers
    //
    // The visible markers are HTML "droplets" laid over the map, driven by
    // the same clustered source. When zoom changes the clustering, droplets
    // slide toward / away from the cluster centre and a gooey SVG filter
    // fuses close ones — reading as liquid merging and splitting. The WebGL
    // circle layers underneath stay invisible but handle all hit-testing.
    // ---------------------------------------------------------------
    var DROPLET = MOTION_RESOLVED.droplet;

    function ensureDropletLayers() {
        if (dom.dropletGoo && dom.dropletGoo.isConnected) return;
        var goo = document.createElement('div');
        goo.className = 'droplet-goo-layer';
        var labels = document.createElement('div');
        labels.className = 'droplet-label-layer';
        document.body.appendChild(goo);
        document.body.appendChild(labels);
        dom.dropletGoo = goo;
        dom.dropletLabels = labels;
    }

    // Cluster blob diameter by member count — mirrors the old circle-radius
    // step ramp (18/22/28/36 px radius → diameter).
    function dropletSize(isCluster, count) {
        if (!isCluster) return 18;
        if (count >= 200) return 60;
        if (count >= 50) return 52;
        if (count >= 10) return 40;
        return 34;
    }

    function projectCoords(coords) {
        return state.map.project(new maplibregl.LngLat(coords[0], coords[1]));
    }

    function applyDropletTransform(rec, x, y, scale, opacity) {
        rec.x = x;
        rec.y = y;
        rec.el.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0) translate(-50%,-50%) scale(' + scale + ')';
        rec.el.style.opacity = opacity;
        if (rec.label) {
            rec.label.style.transform = 'translate3d(' + x + 'px,' + y + 'px,0) translate(-50%,-50%) scale(' + scale + ')';
            rec.label.style.opacity = opacity;
        }
    }

    // Recolour a droplet. Empty/primary clears the override so the CSS default
    // (var(--primary)) applies; the radial sheen is preserved via CSS.
    function applyDropletColor(rec, color) {
        rec.color = color || '';
        if (color && color !== PRIMARY_COLOR) {
            rec.el.style.setProperty('--droplet-color', color);
        } else {
            rec.el.style.removeProperty('--droplet-color');
        }
    }

    function createDroplet(spec) {
        var size = dropletSize(spec.isCluster, spec.count);
        var el = document.createElement('div');
        el.className = 'droplet';
        el.style.width = size + 'px';
        el.style.height = size + 'px';
        dom.dropletGoo.appendChild(el);
        var rec = { key: spec.key, isCluster: spec.isCluster, count: spec.count, coords: spec.coords, el: el, label: null, size: size, color: '', clusterId: spec.clusterId };
        if (TAG.enabled) applyDropletColor(rec, spec.color);
        if (spec.isCluster) {
            var label = document.createElement('div');
            label.className = 'droplet-label';
            label.textContent = spec.count;
            dom.dropletLabels.appendChild(label);
            rec.label = label;
        }
        return rec;
    }

    function updateDropletVisual(rec, spec) {
        rec.coords = spec.coords;
        if (rec.count !== spec.count) {
            rec.count = spec.count;
            var size = dropletSize(spec.isCluster, spec.count);
            rec.size = size;
            rec.el.style.width = size + 'px';
            rec.el.style.height = size + 'px';
            if (rec.label) rec.label.textContent = spec.count;
        }
        if (spec.isCluster && spec.clusterId !== undefined) rec.clusterId = spec.clusterId;
        // Non-cluster (point) colour can update in place; cluster colour is
        // owned by the async majority resolver, so don't clobber it here.
        if (TAG.enabled && !spec.isCluster && spec.color !== undefined && spec.color !== rec.color) {
            applyDropletColor(rec, spec.color);
        }
    }

    function removeDroplet(rec) {
        if (rec.el) rec.el.remove();
        if (rec.label) rec.label.remove();
    }

    function setDropletTransit(rec, on) {
        rec.el.classList.toggle('droplet--transit', on);
        if (rec.label) rec.label.classList.toggle('droplet-label--transit', on);
    }

    function nearestPoint(collection, x, y) {
        var best = null, bestDist = Infinity;
        collection.forEach(function (item) {
            var dx = item.x - x, dy = item.y - y;
            var d = dx * dx + dy * dy;
            if (d < bestDist) { bestDist = d; best = item; }
        });
        return best;
    }

    function enableGoo() {
        if (dom.dropletGoo) dom.dropletGoo.classList.add('goo-active');
    }

    function scheduleGooDisable() {
        if (state.gooTimer) clearTimeout(state.gooTimer);
        state.gooTimer = setTimeout(function () {
            if (dom.dropletGoo) dom.dropletGoo.classList.remove('goo-active');
            state.gooTimer = null;
        }, DROPLET.transition + 80);
    }

    // Reposition existing droplets to track the map (called on every move).
    // No transition here so they follow the map rigidly without lag.
    function positionDroplets() {
        if (!state.map || !state.droplets || state.droplets.size === 0) return;
        state.droplets.forEach(function (rec) {
            // A map move means the user is interacting — track rigidly, so
            // drop any lingering transition from an in-flight split.
            if (rec.el.classList.contains('droplet--transit')) setDropletTransit(rec, false);
            var p = projectCoords(rec.coords);
            applyDropletTransform(rec, p.x, p.y, 1, 1);
        });
    }

    // Rebuild the droplet set from the clustered features and animate the
    // difference: new droplets spring out of the nearest previous one (split),
    // vanished droplets are pulled into the nearest surviving one (merge).
    function syncDroplets() {
        if (!state.map || !state.map.isStyleLoaded()) return;
        ensureDropletLayers();

        var feats = state.map.queryRenderedFeatures(undefined, {
            layers: [CONFIG.clusterLayerId, CONFIG.layerId]
        });
        var next = new Map();
        feats.forEach(function (f) {
            if (!f.geometry || !f.properties) return;
            var isCluster = f.properties.cluster_id !== undefined;
            var key = isCluster ? 'c:' + f.properties.cluster_id : 'p:' + photoId(f.properties);
            if (!isCluster && key === 'p:') return;
            if (next.has(key)) return;
            var spec = {
                key: key,
                isCluster: isCluster,
                count: isCluster ? (parseInt(f.properties.point_count, 10) || 2) : 1,
                coords: f.geometry.coordinates.slice()
            };
            if (TAG.enabled) {
                if (isCluster) {
                    // Preliminary colour; the majority tag resolves async below.
                    spec.color = PRIMARY_COLOR;
                    spec.clusterId = f.properties.cluster_id;
                } else {
                    spec.color = pointColor(featureTagSlugs(f.properties));
                }
            }
            next.set(key, spec);
        });

        var prev = state.droplets;
        var reduced = prefersReducedMotion();
        // Clustering only changes with zoom; panning merely reveals/hides
        // markers. Animate merge/split on zoom change only, so panning
        // doesn't make markers slide in from unrelated neighbours.
        var z = state.map.getZoom();
        var zoomChanged = state.dropletZoom !== undefined && Math.abs(z - state.dropletZoom) > 0.001;
        state.dropletZoom = z;

        // Screen positions of both sets for the nearest-neighbour heuristic.
        var prevPts = [];
        prev.forEach(function (rec) {
            var p = projectCoords(rec.coords);
            rec.x = p.x; rec.y = p.y;
            prevPts.push(rec);
        });
        next.forEach(function (spec) {
            var p = projectCoords(spec.coords);
            spec.x = p.x; spec.y = p.y;
        });

        var result = new Map();
        var animating = false;

        next.forEach(function (spec, key) {
            if (prev.has(key)) {
                // Survivor — keep the element, refresh data/position in place.
                var rec = prev.get(key);
                updateDropletVisual(rec, spec);
                setDropletTransit(rec, false);
                applyDropletTransform(rec, spec.x, spec.y, 1, 1);
                result.set(key, rec);
                prev.delete(key);
            } else {
                // New droplet — split out of the nearest previous droplet.
                var rec2 = createDroplet(spec);
                var origin = (reduced || !zoomChanged) ? null : nearestPoint(prevPts, spec.x, spec.y);
                if (origin) {
                    setDropletTransit(rec2, false);
                    applyDropletTransform(rec2, origin.x, origin.y, 0.25, 0);
                    enableGoo();
                    animating = true;
                    // Next frame: transition to its real position.
                    (function (r, sx, sy) {
                        requestAnimationFrame(function () {
                            requestAnimationFrame(function () {
                                setDropletTransit(r, true);
                                applyDropletTransform(r, sx, sy, 1, 1);
                                // Drop the transition once it has played so
                                // later map moves track rigidly.
                                setTimeout(function () { setDropletTransit(r, false); }, DROPLET.transition + 40);
                            });
                        });
                    })(rec2, spec.x, spec.y);
                } else {
                    // First render or reduced motion — just place it.
                    setDropletTransit(rec2, false);
                    applyDropletTransform(rec2, spec.x, spec.y, 1, 1);
                }
                result.set(key, rec2);
            }
        });

        // Whatever remains in prev has disappeared → merge into nearest next.
        prev.forEach(function (rec) {
            if (reduced || !zoomChanged || result.size === 0) { removeDroplet(rec); return; }
            var target = nearestPoint(result, rec.x, rec.y);
            enableGoo();
            animating = true;
            setDropletTransit(rec, true);
            applyDropletTransform(rec, target ? target.x : rec.x, target ? target.y : rec.y, 0.25, 0);
            (function (r) {
                setTimeout(function () { removeDroplet(r); }, DROPLET.transition);
            })(rec);
        });

        state.droplets = result;
        if (animating) scheduleGooDisable();
        if (TAG.enabled) resolveClusterColors(result);
    }

    // Cluster colour = majority tag among its leaves (ties by slug order),
    // resolved asynchronously from the clustered source. Runs only on the
    // debounced sync, and only for clusters, so the leaf queries stay cheap.
    function resolveClusterColors(dropletMap) {
        var source = state.map && state.map.getSource(CONFIG.clusterSourceId);
        if (!source || typeof source.getClusterLeaves !== 'function') return;
        dropletMap.forEach(function (rec) {
            if (!rec.isCluster || rec.clusterColorFor === rec.key + ':' + rec.count) return;
            var clusterId = rec.clusterId;
            if (clusterId === undefined) return;
            var limit = Math.max(2, rec.count || 2);
            var handle = function (leaves) {
                if (state.droplets.get(rec.key) !== rec) return; // gone/replaced
                var slug = majorityTagSlug(leaves || []);
                applyDropletColor(rec, slug ? TAG.color(slug) : PRIMARY_COLOR);
                rec.clusterColorFor = rec.key + ':' + rec.count;
            };
            try {
                if (source.getClusterLeaves.length >= 4) {
                    source.getClusterLeaves(clusterId, limit, 0, function (err, leaves) { if (!err) handle(leaves); });
                } else {
                    var p = source.getClusterLeaves(clusterId, limit, 0);
                    if (p && typeof p.then === 'function') p.then(handle).catch(function () {});
                }
            } catch (e) {}
        });
    }

    // ---------------------------------------------------------------
    // 9. Sidebar
    // ---------------------------------------------------------------
    // Waterfall entrance: after the sidebar has slid open, the post cards
    // reveal one after another from top to bottom. Uses the Web Animations
    // API with a per-card delay and backwards fill, so each card holds its
    // hidden state until its turn, then settles back to the natural CSS
    // state on finish (no lingering delay to interfere with hover).
    var POST_STAGGER = {
        base: 160,   // wait for the sidebar to settle before cards appear
        step: 55,    // gap between consecutive cards
        duration: 440,
        easing: 'cubic-bezier(0.16, 1, 0.3, 1)'
    };

    function staggerRevealPosts() {
        var cards = dom.sidebarPosts.querySelectorAll('.post-card');
        if (!cards.length) return;
        var reduced = prefersReducedMotion();
        for (var i = 0; i < cards.length; i++) {
            var card = cards[i];
            // Cancel any in-flight entrance from a previous open.
            card.getAnimations().forEach(function (a) { a.cancel(); });
            if (reduced) continue;
            card.animate([
                { opacity: 0, transform: 'translateY(16px)' },
                { opacity: 1, transform: 'translateY(0)' }
            ], {
                duration: POST_STAGGER.duration,
                delay: POST_STAGGER.base + i * POST_STAGGER.step,
                easing: POST_STAGGER.easing,
                fill: 'backwards'
            });
        }
    }

    function openSidebar() {
        var wasCollapsed = !state.sidebarOpen;
        if (state.isMobile) {
            dom.sidebar.classList.add('open');
        }
        document.body.classList.remove('sidebar-collapsed');
        state.sidebarOpen = true;
        // Only run the waterfall when opening from a collapsed state, so
        // repeated openSidebar() calls (e.g. from marker clicks) don't flash.
        if (wasCollapsed) staggerRevealPosts();
    }

    function closeSidebar(preserveOverlays) {
        if (state.isMobile) {
            dom.sidebar.classList.remove('open');
        }
        document.body.classList.add('sidebar-collapsed');
        state.sidebarOpen = false;
        closeFilterPanel();
        if (preserveOverlays) return;
        closeAllPhotoPanels();
        closeArticlePanel();
        dom.detailSheet.classList.remove('active');
        state.detailOpen = false;
    }

    function toggleSidebar() {
        if (state.sidebarOpen) closeSidebar();
        else openSidebar();
    }

    // v1.4.9: the excerpt shown on cards follows the server-computed precedence
    // (manual excerpt > AI 概述 > auto-excerpt), exposed as sp_card_excerpt. Falls
    // back to the plain WP excerpt if the field is ever missing.
    function cardExcerptText(post) {
        var c = (post && typeof post.sp_card_excerpt === 'string') ? post.sp_card_excerpt.trim() : '';
        if (c) return c;
        return stripHtml((post && post.excerpt && post.excerpt.rendered) || '').trim();
    }

    function renderSidebarPosts(posts) {
        dom.sidebarPosts.innerHTML = '';
        if (!posts || posts.length === 0) {
            dom.sidebarPosts.innerHTML = '<div style="padding:20px;text-align:center;color:var(--text-muted);font-size:0.8125rem;">暂无文章</div>';
            return;
        }
        var isLarge = SETTINGS.articleCardSize === 'large';
        posts.forEach(function(post) {
            var card = document.createElement('div');
            card.className = isLarge ? 'post-card post-card--large' : 'post-card';
            card.dataset.postId = post.id;

            // v1.4.6 (item 9): article cover = chosen cover, else first content
            // image (resolved server-side into sp_cover). Rendered as a strongly
            // blurred module background with a readability scrim; the old left
            // thumbnail is gone.
            var coverUrl = post.sp_cover || '';

            var dateStr = post.date ? formatDate(post.date.split('T')[0]) : '';

            // Word count (always, when known) and view count (only when the
            // counter is enabled) sit on the same line as the date.
            var metaExtra = '';
            var wc = getPostWordCount(post);
            if (wc != null) {
                metaExtra += '<span class="post-card-words" title="字数">' + SP_ICON_WORDS + formatCount(wc) + '</span>';
            }
            if (SETTINGS.viewCounter) {
                var pv = getPostViews(post);
                metaExtra += '<span class="post-card-views" title="阅读量">' + SP_ICON_EYE + '<span class="post-card-views-num">' + (pv != null ? formatCount(pv) : '0') + '</span></span>';
            }

            // Large cards add the article excerpt beneath the title. v1.4.9: excerpt
            // text follows the card precedence (manual > AI 概述 > auto) via sp_card_excerpt.
            var excerptHtml = '';
            if (isLarge) {
                var excerptText = cardExcerptText(post);
                if (excerptText) {
                    excerptHtml = '<div class="post-card-excerpt">' + escapeHtml(excerptText) + '</div>';
                }
            }

            card.innerHTML = ''
                + (coverUrl ? '<div class="post-card-cover"></div><div class="post-card-scrim"></div>' : '')
                + '<div class="post-card-body">'
                + '<div class="post-card-title">' + escapeHtml(post.title.rendered || '') + '</div>'
                + excerptHtml
                + '<div class="post-card-date"><span class="post-card-date-item"><svg width=12 height=12 viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' + escapeHtml(dateStr) + '</span>' + metaExtra + '</div>'
                + '</div>';

            if (coverUrl) {
                card.classList.add('has-cover');
                var coverEl = card.querySelector('.post-card-cover');
                // v1.4.7 (item 8): lazy-load the cover — stash the URL and only set
                // the background-image when the card scrolls into view, so a long
                // article list doesn't fetch dozens of cover images up front.
                if (coverEl) {
                    coverEl.dataset.bg = String(coverUrl);
                    observeSidebarCover(coverEl);
                }
            }

            card.addEventListener('click', function(e) {
                e.stopPropagation();
                if (state.articleOpen && state.openedPostId === post.id) {
                    closeArticlePanel();
                } else {
                    openArticle(post.id);
                }
            });

            dom.sidebarPosts.appendChild(card);
        });
    }

    // v1.4.7 (item 8): shared lazy-loader for sidebar cover backgrounds. The
    // cover element carries its URL in data-bg; the observer paints it the moment
    // the card nears the viewport (200px rootMargin so it's ready just before
    // shown), then stops watching it. Falls back to eager paint where
    // IntersectionObserver is unavailable.
    function applySidebarCover(el) {
        if (!el || !el.dataset || !el.dataset.bg) return;
        el.style.backgroundImage = 'url("' + el.dataset.bg.replace(/"/g, '%22') + '")';
        delete el.dataset.bg;
    }
    function observeSidebarCover(el) {
        if (typeof IntersectionObserver === 'undefined') { applySidebarCover(el); return; }
        if (!state.sidebarCoverObserver) {
            state.sidebarCoverObserver = new IntersectionObserver(function (entries, obs) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        applySidebarCover(entry.target);
                        obs.unobserve(entry.target);
                    }
                });
            }, { root: dom.sidebarPosts || null, rootMargin: '200px 0px' });
        }
        state.sidebarCoverObserver.observe(el);
    }

    // Categories (WordPress 分类) attached to a post, from either the REST
    // embed or the inline-data mirror.
    function getPostCategories(post) {
        var cats = [];
        if (post._embedded && post._embedded['wp:term']) {
            post._embedded['wp:term'].forEach(function (group) {
                (group || []).forEach(function (t) {
                    if (t && t.taxonomy === 'category') cats.push({ slug: t.slug, name: t.name });
                });
            });
        }
        return cats;
    }

    // v1.4.0: region tags (WordPress 自定义分类 region_tag). Used as a
    // second filter dimension on the sidebar; chips inherit the colour
    // dot from the existing tag-coloring code (TAG.color) so the visual
    // matches the map droplet legend.
    function getPostRegionTags(post) {
        var tags = [];
        if (post._embedded && post._embedded['wp:term']) {
            post._embedded['wp:term'].forEach(function (group) {
                (group || []).forEach(function (t) {
                    if (t && t.taxonomy === 'region_tag') tags.push({ slug: t.slug, name: t.name });
                });
            });
        }
        return tags;
    }

    function filterSidebarPosts(query) {
        state.searchQuery = query || '';
        applySidebarFilters();
    }

    // Real-time combined filter: text search AND (any of) the selected
    // categories AND (any of) the selected region tags. The sidebar shows
    // only the matching posts. v1.4.0: AND across the two filter groups
    // (categories, region tags); OR within each group.
    function applySidebarFilters() {
        var q = (state.searchQuery || '').toLowerCase().trim();
        var selCats = state.selectedCategories;
        var selTags = state.selectedRegionTags;
        var filtered = state.allPosts.filter(function (p) {
            if (q) {
                var matchesText = (p.title.rendered || '').toLowerCase().indexOf(q) !== -1
                    || stripHtml(p.excerpt && p.excerpt.rendered || '').toLowerCase().indexOf(q) !== -1;
                if (!matchesText) return false;
            }
            if (selCats && selCats.size > 0) {
                var cats = getPostCategories(p);
                var hit = cats.some(function (c) { return selCats.has(c.slug); });
                if (!hit) return false;
            }
            if (selTags && selTags.size > 0) {
                var tags = getPostRegionTags(p);
                var hitT = tags.some(function (t) { return selTags.has(t.slug); });
                if (!hitT) return false;
            }
            return true;
        });
        renderSidebarPosts(filtered);
    }

    // ---------------------------------------------------------------
    // 9b. Filter chip groups — categories + region tags (v1.4.0)
    //
    // Two labelled chip groups inside the filter panel: 分类 (existing
    // WordPress categories) and 地区 (the region_tag custom taxonomy).
    // The 清除 link appears whenever any filter is selected across either
    // group. The filter button shows a "·N" badge when active.
    // ---------------------------------------------------------------
    function totalFilterCount() {
        return (state.selectedCategories ? state.selectedCategories.size : 0)
             + (state.selectedRegionTags ? state.selectedRegionTags.size : 0);
    }

    function syncFilterButtonBadge() {
        if (!dom.filterBtn) return;
        var n = totalFilterCount();
        // Add or update a tiny badge on the button.
        var existing = dom.filterBtn.querySelector('.sidebar-filter-badge');
        if (n > 0) {
            if (!existing) {
                existing = document.createElement('span');
                existing.className = 'sidebar-filter-badge';
                dom.filterBtn.appendChild(existing);
            }
            existing.textContent = '·' + n;
            dom.filterBtn.classList.add('is-active');
        } else {
            if (existing) existing.parentNode.removeChild(existing);
            dom.filterBtn.classList.remove('is-active');
        }
        if (dom.filterClear) {
            dom.filterClear.hidden = (n === 0);
        }
    }

    function buildCategoryChips() {
        if (!dom.filterChipsCategories) return;
        var seen = {};
        var cats = [];
        (state.allPosts || []).forEach(function (p) {
            getPostCategories(p).forEach(function (c) {
                if (c.slug && !seen[c.slug]) { seen[c.slug] = true; cats.push(c); }
            });
        });
        cats.sort(function (a, b) { return a.name.localeCompare(b.name); });

        if (cats.length === 0) {
            dom.filterChipsCategories.innerHTML = '<span class="filter-chips-empty">暂无分类可筛选</span>';
            return;
        }
        dom.filterChipsCategories.innerHTML = '';
        cats.forEach(function (c) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'filter-chip' + (state.selectedCategories.has(c.slug) ? ' is-selected' : '');
            chip.textContent = c.name;
            chip.dataset.slug = c.slug;
            chip.addEventListener('click', function (e) {
                e.stopPropagation();
                if (state.selectedCategories.has(c.slug)) {
                    state.selectedCategories.delete(c.slug);
                    chip.classList.remove('is-selected');
                } else {
                    state.selectedCategories.add(c.slug);
                    chip.classList.add('is-selected');
                }
                syncFilterButtonBadge();
                applySidebarFilters();
            });
            dom.filterChipsCategories.appendChild(chip);
        });
    }

    function buildRegionChips() {
        if (!dom.filterChipsRegions) return;
        var seen = {};
        var tags = [];
        (state.allPosts || []).forEach(function (p) {
            getPostRegionTags(p).forEach(function (t) {
                if (t.slug && !seen[t.slug]) { seen[t.slug] = true; tags.push(t); }
            });
        });
        tags.sort(function (a, b) { return a.name.localeCompare(b.name); });

        if (tags.length === 0) {
            dom.filterChipsRegions.innerHTML = '<span class="filter-chips-empty">暂无地区标签可筛选</span>';
            return;
        }
        dom.filterChipsRegions.innerHTML = '';
        tags.forEach(function (t) {
            var chip = document.createElement('button');
            chip.type = 'button';
            chip.className = 'filter-chip' + (state.selectedRegionTags.has(t.slug) ? ' is-selected' : '');
            // When tag colouring is on, prefix the label with a colour dot
            // so the chip matches the map droplet legend.
            var dot = '';
            try {
                if (typeof TAG !== 'undefined' && TAG && TAG.enabled && TAG.color) {
                    var c = TAG.color(t.slug);
                    if (c) { dot = '<span class="tag-chip-dot" style="background:' + escapeHtml(c) + ';"></span>'; }
                }
            } catch (e) { /* TAG not ready, fall through */ }
            chip.innerHTML = dot + '<span>' + escapeHtml(t.name) + '</span>';
            chip.dataset.slug = t.slug;
            chip.addEventListener('click', function (e) {
                e.stopPropagation();
                if (state.selectedRegionTags.has(t.slug)) {
                    state.selectedRegionTags.delete(t.slug);
                    chip.classList.remove('is-selected');
                } else {
                    state.selectedRegionTags.add(t.slug);
                    chip.classList.add('is-selected');
                }
                syncFilterButtonBadge();
                applySidebarFilters();
            });
            dom.filterChipsRegions.appendChild(chip);
        });
    }

    // Backwards-compat shim: callers that still invoke buildFilterChips()
    // (e.g. after a posts refresh) get the new two-group behaviour.
    function buildFilterChips() {
        buildCategoryChips();
        buildRegionChips();
        syncFilterButtonBadge();
    }

    // ---------------------------------------------------------------
    // Tag colour legend (v1.2.5)
    //
    // A compact, collapsible key of the region_tags present on this map. Only
    // built when tag colouring + legend are both on. Lists tags used by loaded
    // markers (stable — doesn't flicker as clusters merge), sorted by name.
    // Desktop: expanded panel bottom-left; mobile: collapsed to a "图例" pill.
    // ---------------------------------------------------------------
    function buildLegend() {
        if (!TAG.enabled || !SETTINGS.tagLegend) return;
        if (dom.tagLegend) { dom.tagLegend.remove(); dom.tagLegend = null; }

        var seen = {};
        var tags = [];
        var feats = (state.allPhotos && state.allPhotos.features) ? state.allPhotos.features : [];
        feats.forEach(function (f) {
            var arr = (f.properties && Array.isArray(f.properties.tags)) ? f.properties.tags : [];
            arr.forEach(function (t) {
                if (t && t.slug && !seen[t.slug]) {
                    seen[t.slug] = true;
                    tags.push({ slug: t.slug, name: TAG.name(t.slug), color: TAG.color(t.slug) });
                }
            });
        });
        if (!tags.length) return;
        tags.sort(function (a, b) { return a.name.localeCompare(b.name); });

        var panel = document.createElement('div');
        panel.className = 'tag-legend';
        if (isMobileView()) panel.classList.add('tag-legend--collapsed');

        var toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'tag-legend-toggle';
        toggle.setAttribute('aria-label', '标签配色图例');
        toggle.innerHTML = '<span class="tag-legend-toggle-icon" aria-hidden="true"></span><span class="tag-legend-toggle-text">图例</span>';
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            panel.classList.toggle('tag-legend--collapsed');
        });

        var list = document.createElement('div');
        list.className = 'tag-legend-list';
        tags.forEach(function (t) {
            var item = document.createElement('div');
            item.className = 'tag-legend-item';
            var sw = document.createElement('span');
            sw.className = 'tag-legend-swatch';
            sw.style.background = t.color;
            var lbl = document.createElement('span');
            lbl.className = 'tag-legend-name';
            lbl.textContent = t.name;
            item.appendChild(sw);
            item.appendChild(lbl);
            list.appendChild(item);
        });

        panel.appendChild(toggle);
        panel.appendChild(list);
        document.body.appendChild(panel);
        dom.tagLegend = panel;
    }

    // v1.4.6 (item 3): FLIP the post list so it glides with the filter panel
    // instead of snapping when the panel claims/releases its in-flow space.
    // `firstTop` is the list's top BEFORE the layout change; we translate the
    // list back to there and animate to 0 over the panel's own duration.
    function flipSidebarPosts(firstTop, duration) {
        var posts = dom.sidebarPosts;
        if (!posts || prefersReducedMotion()) return;
        var delta = firstTop - posts.getBoundingClientRect().top;
        if (!delta) return;
        if (state.filterPostsFlip) { try { state.filterPostsFlip.cancel(); } catch (e) {} state.filterPostsFlip = null; }
        var a = posts.animate([
            { transform: 'translateY(' + delta + 'px)' },
            { transform: 'translateY(0)' }
        ], { duration: duration, easing: ARTICLE_MOTION.easing, fill: 'both' });
        state.filterPostsFlip = a;
        a.onfinish = function () { if (state.filterPostsFlip === a) { try { a.cancel(); } catch (e) {} state.filterPostsFlip = null; } };
    }

    // Clear any leftover overlay pinning from an interrupted close so the panel
    // sits back in normal flow.
    function unpinFilterPanel() {
        var fp = dom.filterPanel;
        if (!fp) return;
        fp.style.position = '';
        fp.style.left = '';
        fp.style.top = '';
        fp.style.width = '';
        fp.style.margin = '';
        fp.style.zIndex = '';
    }

    // Uses the article panel's window-scale motion: the panel grows out of the
    // filter button and collapses back into it, recomputed live each time.
    function openFilterPanel() {
        if (state.filterOpen || !dom.filterPanel) return;
        state.filterOpen = true;
        dom.filterBtn.setAttribute('aria-expanded', 'true');
        if (state.filterMotion) { state.filterMotion.cancel(); state.filterMotion = null; }
        unpinFilterPanel(); // recover if a prior close was interrupted mid-pin

        // Capture the list position BEFORE the panel claims its flow space.
        var postsFirstTop = dom.sidebarPosts ? dom.sidebarPosts.getBoundingClientRect().top : 0;
        dom.filterPanel.hidden = false;
        // Measure resting geometry, then animate from the button rect.
        var panelRect = dom.filterPanel.getBoundingClientRect();
        var btnRect = dom.filterBtn.getBoundingClientRect();
        if (prefersReducedMotion()) return;
        flipSidebarPosts(postsFirstTop, ARTICLE_MOTION.openDuration);
        var from = collapseTransform(btnRect, panelRect);
        var anim = dom.filterPanel.animate([
            { transform: from, opacity: 0 },
            { opacity: 1, offset: 0.15 },
            { transform: 'translate(0,0) scale(1,1)', opacity: 1 }
        ], { duration: ARTICLE_MOTION.openDuration, easing: ARTICLE_MOTION.easing, fill: 'both' });
        state.filterMotion = anim;
        anim.onfinish = function () { if (state.filterMotion === anim) { anim.cancel(); state.filterMotion = null; } };
    }

    function closeFilterPanel() {
        if (!state.filterOpen || !dom.filterPanel) return;
        state.filterOpen = false;
        dom.filterBtn.setAttribute('aria-expanded', 'false');
        if (state.filterMotion) { state.filterMotion.cancel(); state.filterMotion = null; }

        if (prefersReducedMotion()) { dom.filterPanel.hidden = true; return; }
        var fp = dom.filterPanel;
        var btnRect = dom.filterBtn.getBoundingClientRect();
        var panelRect = fp.getBoundingClientRect();
        var postsFirstTop = dom.sidebarPosts ? dom.sidebarPosts.getBoundingClientRect().top : 0;
        // Pin the panel out of flow at its current spot so it stops holding layout
        // space; the list then reclaims the space and we FLIP it up over the same
        // duration as the panel's shrink (no end-of-anim snap). Use ABSOLUTE (not
        // fixed): .sidebar has transform+backdrop-filter, so it's the containing
        // block for fixed — absolute resolves against .sidebar-search (its
        // position:relative offsetParent) using the panel's own offset box.
        var ot = fp.offsetTop, ol = fp.offsetLeft, ow = fp.offsetWidth; // capture in-flow box first
        fp.style.position = 'absolute';
        fp.style.top = ot + 'px';
        fp.style.left = ol + 'px';
        fp.style.width = ow + 'px';
        fp.style.margin = '0';
        fp.style.zIndex = '5';
        flipSidebarPosts(postsFirstTop, ARTICLE_MOTION.closeDuration);
        var to = collapseTransform(btnRect, panelRect);
        var anim = fp.animate([
            { transform: 'translate(0,0) scale(1,1)', opacity: 1 },
            { opacity: 1, offset: 0.82 },
            { transform: to, opacity: 0 }
        ], { duration: ARTICLE_MOTION.closeDuration, easing: ARTICLE_MOTION.easing, fill: 'both' });
        state.filterMotion = anim;
        anim.onfinish = function () {
            if (state.filterMotion !== anim) return;
            anim.cancel();
            state.filterMotion = null;
            fp.hidden = true;
            unpinFilterPanel();
        };
    }

    function toggleFilterPanel() {
        if (state.filterOpen) closeFilterPanel();
        else openFilterPanel();
    }

    // ---------------------------------------------------------------
    // 10. Article Panel — Windows Native Minimize / Restore Motion
    //
    // The article page grows out of / shrinks into its sidebar card the
    // way Windows DWM animates a window between full size and the taskbar:
    // a full-resolution snapshot of the window is scaled + translated as a
    // single rigid rectangle. We reproduce that with FLIP — a clone laid
    // out at the FULL article geometry (so content stays crisp) is mapped
    // onto the live card rect via a compositor-only transform. Only
    // transform / opacity animate, so there is no reflow and no warp.
    // ---------------------------------------------------------------
    var ARTICLE_MOTION = MOTION_RESOLVED.article;

    // Effective reduced-motion gate: the OS preference wins by default, unless
    // the site owner opted to override it (motionIgnoreReduced).
    function prefersReducedMotion() {
        if (SETTINGS.motionIgnoreReduced) return false;
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    // Live geometry of a sidebar card + its scroll container, recomputed on
    // every animation so we never rely on cached or hard-coded coordinates.
    function getPostCardGeometry(postId) {
        if (postId == null) return null;
        var card = dom.sidebarPosts.querySelector('[data-post-id="' + postId + '"]');
        if (!card) return null;
        var rect = card.getBoundingClientRect();
        var listRect = dom.sidebarPosts.getBoundingClientRect();
        return {
            rect: rect,
            listRect: listRect,
            direction: rect.bottom <= listRect.top ? 'up' : (rect.top >= listRect.bottom ? 'down' : 'visible')
        };
    }

    // The article panel carries a transform while inactive, so its live
    // bounding box is offset. Read the true, untransformed layout rect.
    function measurePanelRect() {
        var panel = dom.articlePanel;
        var prevTransform = panel.style.transform;
        panel.classList.add('article-panel--instant');
        panel.style.transform = 'none';
        // Force layout so the neutralized transform is reflected in the rect.
        var rect = panel.getBoundingClientRect();
        panel.style.transform = prevTransform;
        panel.classList.remove('article-panel--instant');
        return rect;
    }

    // Rectangle the clone collapses into. Visible cards use their real rect;
    // cards scrolled out of the sidebar tuck just beyond the nearest edge so
    // the window slides into the edge instead of vanishing.
    function computeCollapseTarget(geom) {
        var rect = geom.rect, listRect = geom.listRect;
        if (geom.direction === 'up') {
            return { left: rect.left, top: listRect.top - rect.height, width: rect.width, height: rect.height };
        }
        if (geom.direction === 'down') {
            return { left: rect.left, top: listRect.bottom, width: rect.width, height: rect.height };
        }
        return { left: rect.left, top: rect.top, width: rect.width, height: rect.height };
    }

    // FLIP transform mapping the full panel box onto the target rect,
    // with transform-origin at the top-left corner. The effective centre
    // therefore tracks the target card automatically — no magic numbers.
    function collapseTransform(target, panelRect) {
        var sx = target.width / Math.max(1, panelRect.width);
        var sy = target.height / Math.max(1, panelRect.height);
        var tx = target.left - panelRect.left;
        var ty = target.top - panelRect.top;
        return 'translate(' + tx.toFixed(2) + 'px,' + ty.toFixed(2) + 'px) scale(' + sx.toFixed(5) + ',' + sy.toFixed(5) + ')';
    }

    function readRadius(el) {
        var r = window.getComputedStyle(el).borderTopLeftRadius;
        return r || '16px';
    }

    function createMotionCard(panelRect) {
        var layer = document.getElementById('motion-layer');
        if (!layer) {
            layer = document.createElement('div');
            layer.id = 'motion-layer';
            layer.className = 'motion-layer';
            document.body.appendChild(layer);
        }
        var card = document.createElement('div');
        card.className = 'motion-card';
        card.style.left = panelRect.left + 'px';
        card.style.top = panelRect.top + 'px';
        card.style.width = panelRect.width + 'px';
        card.style.height = panelRect.height + 'px';
        card.innerHTML = '<div class="motion-card-content">' + dom.articlePanel.innerHTML + '</div>';
        layer.appendChild(card);
        return card;
    }

    function clearMotion() {
        if (state.articleMotion) {
            state.articleMotion.cancel();
            state.articleMotion = null;
        }
        if (state.motionCard) {
            state.motionCard.remove();
            state.motionCard = null;
        }
    }

    // Snap the real panel into its active/hidden state with no transition,
    // so the handoff to/from the clone is invisible (no second animation).
    function setPanelInstant(active) {
        dom.articlePanel.classList.add('article-panel--instant');
        dom.articlePanel.classList.toggle('active', active);
        // Force reflow so the transition-less change commits this frame.
        void dom.articlePanel.offsetHeight;
        requestAnimationFrame(function () {
            dom.articlePanel.classList.remove('article-panel--instant');
        });
    }

    function animateWindowsOpen(postId) {
        clearMotion();
        // v1.4.9 (item 6): opened inside the expand-page — no FLIP-from-card. The panel
        // is reparented + filled via .article-panel--in-screen; the SCREEN push handles
        // the motion, so we just mark it active (in-screen forces it visible).
        if (state.expandArticleMode) {
            dom.articlePanel.classList.add('active');
            return;
        }
        var geom = getPostCardGeometry(postId);
        if (!geom || state.isMobile || prefersReducedMotion()) {
            dom.articlePanel.classList.add('active');
            return;
        }
        var panelRect = measurePanelRect();
        var target = computeCollapseTarget(geom);
        var card = createMotionCard(panelRect);
        state.motionCard = card;
        // Hide the real panel instantly so no stale content shows behind the
        // clone (e.g. when switching directly from another open article).
        setPanelInstant(false);

        var panelRadius = readRadius(dom.articlePanel);
        var srcCard = dom.sidebarPosts.querySelector('[data-post-id="' + postId + '"]');
        var cardRadius = readRadius(srcCard || dom.articlePanel);

        var anim = card.animate([
            { transform: collapseTransform(target, panelRect), opacity: 0, borderRadius: cardRadius },
            { opacity: 1, offset: 0.12 },
            { transform: 'translate(0,0) scale(1,1)', opacity: 1, borderRadius: panelRadius }
        ], { duration: ARTICLE_MOTION.openDuration, easing: ARTICLE_MOTION.easing, fill: 'both' });
        state.articleMotion = anim;
        anim.onfinish = function () {
            if (state.articleMotion !== anim) return;
            setPanelInstant(true);
            clearMotion();
        };
    }

    function animateWindowsClose(postId, onDone) {
        clearMotion();
        // v1.4.9 (item 6): in-expand articles are closed via popExpandArticle() (routed
        // from closeArticlePanel before we get here), so no special branch is needed.
        var geom = getPostCardGeometry(postId);
        if (!geom || state.isMobile || prefersReducedMotion()) {
            dom.articlePanel.classList.remove('active');
            if (onDone) onDone();
            return;
        }
        var panelRect = measurePanelRect();
        var target = computeCollapseTarget(geom);
        var card = createMotionCard(panelRect);
        state.motionCard = card;
        // Hide the real panel instantly so only the clone plays the motion.
        setPanelInstant(false);

        var panelRadius = readRadius(dom.articlePanel);
        var srcCard = dom.sidebarPosts.querySelector('[data-post-id="' + postId + '"]');
        var cardRadius = readRadius(srcCard || dom.articlePanel);

        var anim = card.animate([
            { transform: 'translate(0,0) scale(1,1)', opacity: 1, borderRadius: panelRadius },
            { opacity: 1, offset: 0.82 },
            { transform: collapseTransform(target, panelRect), opacity: 0, borderRadius: cardRadius }
        ], { duration: ARTICLE_MOTION.closeDuration, easing: ARTICLE_MOTION.easing, fill: 'both' });
        state.articleMotion = anim;
        anim.onfinish = function () {
            if (state.articleMotion !== anim) return;
            clearMotion();
            if (onDone) onDone();
        };
    }

    function openArticle(postId, options) {
        resetArticlePhotoTip();
        var requestPostId = postId;
        closeAllPhotoPanels();

        // Switching from an already-open article: collapse the current one
        // first, then expand the new one after a short gap so the transition
        // reads as a deliberate hand-off rather than an abrupt swap. The
        // fetch runs in parallel with the collapse.
        var switching = state.articleOpen;
        var previousPostId = state.openedPostId;
        var collapseWait = 0;
        if (switching) {
            animateWindowsClose(previousPostId);
            collapseWait = ARTICLE_MOTION.closeDuration + ARTICLE_MOTION.switchGap;
        }

        state.openedPostId = requestPostId;
        state.articleOpen = true;
        if (!switching) {
            dom.articleTitle.textContent = '加载中...';
            dom.articleMeta.textContent = '';
            dom.articleContent.innerHTML = '';
        }
        if (dom.articleSummary) { dom.articleSummary.hidden = true; dom.articleSummary.innerHTML = ''; }
        if (dom.articleShare) { dom.articleShare.hidden = true; dom.articleShare.innerHTML = ''; }
        if (state.isMobile) closeSidebar(true);

        var fetchPromise = fetchFromRest(CONFIG.postsEndpoint + '/' + requestPostId, { _embed: '1' });
        var waitPromise = collapseWait
            ? new Promise(function (resolve) { setTimeout(resolve, collapseWait); })
            : Promise.resolve();

        Promise.all([fetchPromise, waitPromise]).then(function (results) {
            var post = results[0];
            if (state.openedPostId !== requestPostId) return;
            if (!post) {
                // v1.5.01 (fix): 深链/初始化场景（options.deepLink）拉取失败时
                // 静默回退到地图，绝不弹出「文章加载失败」——否则访问主页在
                // 某些环境下 REST 单篇接口偶发失败会误弹错误面板。仅用户主动
                // 点击卡片打开时才显示失败提示。
                if (options && options.deepLink) {
                    state.articleOpen = false;
                    state.openedPostId = null;
                    dom.articlePanel.classList.remove('active');
                    return;
                }
                dom.articleTitle.textContent = t('文章加载失败');
                dom.articleMeta.textContent = '';
                dom.articleContent.innerHTML = '';
                if (dom.articleSummary) { dom.articleSummary.hidden = true; dom.articleSummary.innerHTML = ''; }
                if (dom.articleShare) { dom.articleShare.hidden = true; dom.articleShare.innerHTML = ''; }
                dom.articlePanel.classList.add('active');
                return;
            }
            var dateStr = post.date ? formatDate(post.date.split('T')[0]) : '';
            // v1.4.4 (item 1): pull the background-generated translations exposed
            // on the post (sp_i18n = { en:{title,body,summary}, ja:{...} }). When
            // present, title/body/summary translate instantly with no model call;
            // absent langs fall back to the on-demand /translate path (v1.4.3).
            var spI18n = (post.sp_i18n && typeof post.sp_i18n === 'object') ? post.sp_i18n : null;
            var pregenFor = function (field) {
                if (!spI18n) return null;
                var out = {};
                if (spI18n.en && spI18n.en[field]) out.en = spI18n.en[field];
                if (spI18n.ja && spI18n.ja[field]) out.ja = spI18n.ja[field];
                return (out.en || out.ja) ? out : null;
            };
            dom.articleTitle.textContent = post.title.rendered || '';
            i18nRegisterPregen(dom.articleTitle, 'text', pregenFor('title')); // v1.4.4: 标题优先用预生成译文
            var metaHtml = '';
            if (dateStr) metaHtml += '<span>' + escapeHtml(dateStr) + '</span>';
            if (SETTINGS.viewCounter) {
                var vc = getPostViews(post);
                metaHtml += '<span class="article-views" title="阅读量">' + SP_ICON_EYE + '<span class="article-views-num">' + (vc != null ? vc.toLocaleString('en-US') : '—') + '</span></span>';
            }
            if (post.sp_write_location) {
                metaHtml += '<span class="article-wloc" title="撰写地点"><svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>' + escapeHtml(post.sp_write_location) + '</span>';
            }
            if (SETTINGS.readingInfo && post.content && post.content.rendered) {
                var ri = computeReadingInfo(post.content.rendered);
                if (ri) {
                    metaHtml += '<span class="article-reading-info">' + ri.words.toLocaleString('en-US') + ' 字 · 约 ' + ri.minutes + ' 分钟</span>';
                }
            }
            if (post._embedded && post._embedded['wp:term']) {
                post._embedded['wp:term'].forEach(function(ta) { ta.forEach(function(t) {
                    if (t.taxonomy !== 'category' && t.taxonomy !== 'region_tag') return;
                    // When tag colouring is on, region_tag chips get a leading
                    // colour dot (text/background stay unchanged).
                    var dot = '';
                    if (TAG.enabled && t.taxonomy === 'region_tag' && t.slug) {
                        dot = '<span class="tag-chip-dot" style="background:' + escapeHtml(TAG.color(t.slug)) + ';"></span>';
                    }
                    metaHtml += '<span class="article-term-chip" style="color:var(--primary);font-size:0.75rem;">' + dot + '#' + escapeHtml(t.name) + '</span>';
                }); });
            }
            dom.articleMeta.innerHTML = metaHtml;
            renderArticleSummary(post, pregenFor('summary'));
            var articleHtml = post.content && post.content.rendered ? post.content.rendered : '<p style="color:var(--text-muted)">' + t('暂无内容') + '</p>';
            dom.articleContent.innerHTML = articleHtml;
            dom.articlePanel.scrollTop = 0;
            // v1.4.3: 正文按需翻译（en/ja）；每次内容被写入（含译文替换）后都需重新
            // 处理外链新窗口与图片交互，故用 _spAfter 回调复跑这两步。
            var wireArticleDom = function () {
                dom.articleContent.querySelectorAll('a').forEach(function(a) { if(!a.href.startsWith(window.location.origin)) a.target='_blank'; });
                wireArticleImages();
                // v1.4.6 (item 10): rebuild the TOC when content is (re)written —
                // covers on-demand translation swaps. Guarded until the bar exists
                // (first open builds it in setupArticleNav, refreshed just below).
                if (dom.articleTocBar) refreshArticleToc();
            };
            wireArticleDom();
            i18nRegisterPregen(dom.articleContent, 'html', pregenFor('body'), wireArticleDom); // v1.4.4: 正文优先用预生成译文
            renderShareBar(post);
            renderComments(requestPostId, post.comment_status);
            animateWindowsOpen(requestPostId);
            setupArticleNav();
            showArticlePhotoTip();
            refreshArticleToc(); // v1.4.6 (item 10): build TOC now the bar exists
            // Count the view (de-duplicated client-side) and reflect the fresh
            // number in the meta line once the server confirms.
            recordArticleView(requestPostId, function (n) {
                if (state.openedPostId !== requestPostId) return;
                var numEl = dom.articleMeta.querySelector('.article-views-num');
                if (numEl) numEl.textContent = Number(n).toLocaleString('en-US');
                // Keep the sidebar card (if present) in sync.
                var card = dom.sidebarPosts && dom.sidebarPosts.querySelector('.post-card[data-post-id="' + requestPostId + '"] .post-card-views-num');
                if (card) card.textContent = formatCount(n);
                var sp = state.allPosts && state.allPosts.filter(function (p) { return p.id === requestPostId; })[0];
                if (sp) sp.sp_views = n;
            });
            // Desktop (Feature 1): once the window-scale open settles, glide
            // the panel to the paragraph that holds the clicked photo.
            if (options && (options.scrollToImageId != null || options.scrollToImageUrl)) {
                var scrollId = options.scrollToImageId;
                var scrollUrl = options.scrollToImageUrl;
                setTimeout(function () {
                    if (state.openedPostId !== requestPostId) return;
                    scrollArticleToImage(scrollId, scrollUrl);
                }, ARTICLE_MOTION.openDuration + 60);
            }
        });
    }

    function closeArticlePanel() {
        if (!state.articleOpen) return;
        var targetPostId = state.openedPostId;
        state.articleOpen = false;
        state.openedPostId = null;
        hideArticleNav();
        // v1.4.4 (item 2): stop watching content height once the panel closes.
        if (state.readingTailRO) { try { state.readingTailRO.disconnect(); } catch (e) {} state.readingTailRO = null; }
        // v1.4.9 (item 6): an in-expand article pops back to the list screen inside the
        // expand-page container (which stays open) — not the normal FLIP/slide close.
        if (state.expandArticleMode) {
            popExpandArticle();
            return;
        }
        // Photograph articles have no source card — fall back to a plain fade.
        if (targetPostId == null || !getPostCardGeometry(targetPostId)) {
            clearMotion();
            dom.articlePanel.classList.remove('active');
            return;
        }
        animateWindowsClose(targetPostId);
    }

    // ---------------------------------------------------------------
    // 10a2. Page-links bar + 友链 / 留言 side panels (v1.3.7)
    //
    // A horizontal glass strip pinned top-right (left of the map controls) with
    // 友链 / 留言 entries plus up to three admin-configured 外站 links. The two
    // panels open over the right half of the screen and coexist with an open
    // article (which lives on the left); they are mutually exclusive with each
    // other — opening one first collapses the other.
    // ---------------------------------------------------------------
    var GB_STATE = { mode: 'random', page: 1, sort: 'time', order: 'asc', hasMore: false, loading: false };
    var sidePanels = { built: false, friend: null, guestbook: null, open: null };

    // v1.4.4 (item 1): page-links bar button labels, translated via the static
    // dictionary and refreshed in place on language switch (the bar is built once).
    var PAGE_LINK_LABELS = { friend: '友链', guestbook: '留言', photowall: '照片墙', announcement: '公告' };
    function updatePageLinkLabels() {
        Array.prototype.forEach.call(document.querySelectorAll('#page-links-bar .page-link-btn[data-sp-panel]'), function (b) {
            var k = b.getAttribute('data-sp-panel');
            var txt = b.querySelector('.page-link-txt');
            if (txt && PAGE_LINK_LABELS[k]) txt.textContent = t(PAGE_LINK_LABELS[k]);
        });
    }

    function initPageLinks() {
        buildPageLinksBar();
    }

    function buildPageLinksBar() {
        if (document.getElementById('page-links-bar')) return;
        var friendIcon = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>';
        var msgIcon = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';
        var extIcon = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>';
        var wallIcon = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>';
        var annIcon = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l18-5v12L3 14v-3z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>';

        var bar = document.createElement('div');
        bar.id = 'page-links-bar';
        bar.className = 'page-links-bar';
        // v1.4.4 (item 1): 栏内按钮文字走静态词典翻译（t()），语言切换时由
        // updatePageLinkLabels() 就地更新。外站链接名保持作者原文（专有名词）。
        // v1.4.4 (item 6): 公告按钮（仅在公告开启时）排在最前，点击切换公告面板。
        var annBtn = (APP.announcement && APP.announcement.enabled)
            ? '<button type="button" class="page-link-btn" data-sp-panel="announcement"><span class="page-link-ico">' + annIcon + '</span><span class="page-link-txt">' + escapeHtml(t('公告')) + '</span></button>'
            : '';
        var html = ''
            + annBtn
            + '<button type="button" class="page-link-btn" data-sp-panel="friend"><span class="page-link-ico">' + friendIcon + '</span><span class="page-link-txt">' + escapeHtml(t('友链')) + '</span></button>'
            + '<button type="button" class="page-link-btn" data-sp-panel="guestbook"><span class="page-link-ico">' + msgIcon + '</span><span class="page-link-txt">' + escapeHtml(t('留言')) + '</span></button>'
            + '<button type="button" class="page-link-btn" data-sp-panel="photowall"><span class="page-link-ico">' + wallIcon + '</span><span class="page-link-txt">' + escapeHtml(t('照片墙')) + '</span></button>';
        var ext = Array.isArray(APP.externalLinks) ? APP.externalLinks : [];
        ext.forEach(function (e) {
            if (!e || !e.url) return;
            var tip = e.tip ? ' title="' + escapeHtml(e.tip) + '"' : '';
            html += '<a class="page-link-btn page-link-ext" href="' + escapeHtml(e.url) + '" target="_blank" rel="noopener"' + tip + '><span class="page-link-ico">' + extIcon + '</span><span class="page-link-txt">' + escapeHtml(e.name || e.url) + '</span></a>';
        });
        bar.innerHTML = html;

        // v1.4.2: 共享滑动药丸(iOS-26 流动高亮)。置于按钮之下,hover/focus 时
        // 平移+变宽到目标按钮矩形,高亮在按钮间“流动”。离开整栏时淡出。
        var pill = document.createElement('span');
        pill.className = 'page-link-pill';
        pill.setAttribute('aria-hidden', 'true');
        bar.insertBefore(pill, bar.firstChild);

        document.body.appendChild(bar);

        function movePillTo(btn) {
            var barRect = bar.getBoundingClientRect();
            var r = btn.getBoundingClientRect();
            // 相对栏内边距盒定位(clientLeft/Top 扣除边框),兼容栏有边框的情况。
            var x = r.left - barRect.left - bar.clientLeft;
            var y = r.top - barRect.top - bar.clientTop;
            pill.style.width = r.width + 'px';
            pill.style.height = r.height + 'px';
            pill.style.transform = 'translate(' + x + 'px,' + y + 'px)';
            bar.classList.add('pill-active');
        }
        // 预置到首个按钮下方(隐形),让首次 hover 是一段流动而非从角落窜出。
        requestAnimationFrame(function () {
            var first = bar.querySelector('.page-link-btn');
            if (!first) return;
            var barRect = bar.getBoundingClientRect();
            var r = first.getBoundingClientRect();
            pill.style.width = r.width + 'px';
            pill.style.height = r.height + 'px';
            pill.style.transform = 'translate(' + (r.left - barRect.left - bar.clientLeft) + 'px,' + (r.top - barRect.top - bar.clientTop) + 'px)';
        });
        bar.addEventListener('mouseover', function (e) {
            var btn = e.target.closest('.page-link-btn');
            if (btn) movePillTo(btn);
        });
        bar.addEventListener('focusin', function (e) {
            var btn = e.target.closest('.page-link-btn');
            if (btn) movePillTo(btn);
        });
        bar.addEventListener('mouseleave', function () { bar.classList.remove('pill-active'); });
        bar.addEventListener('focusout', function (e) {
            if (!bar.contains(e.relatedTarget)) bar.classList.remove('pill-active');
        });

        bar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-sp-panel]');
            if (!btn) return;
            var which = btn.getAttribute('data-sp-panel');
            // v1.4.4 (item 6): announcement is a top-right dropdown, not a
            // right-half side panel — toggle it separately.
            if (which === 'announcement') { toggleAnnouncement(btn); return; }
            toggleSidePanel(which, btn);
        });
    }

    // ---------------------------------------------------------------
    // 10a3. Announcement panel (v1.4.4 item 6)
    //
    // A closable glass dropdown pinned top-right, below the page-links bar and
    // left of the map controls. Width tracks the page-links bar; height follows
    // content (the .announcement-content region scrolls when long, so the close
    // button + header stay pinned — item 3). Auto-opens on load (backend toggle)
    // unless the reader dismissed the current announcement (remembered by content
    // hash in localStorage; a new announcement re-opens). Content is admin Markdown
    // rendered server-side and registered for i18n so a non-Chinese language shows
    // the pre-generated (cache-warmed) translation.
    // ---------------------------------------------------------------
    var ANNOUNCE = { built: false, panel: null, open: false, contentEl: null };
    var ANNOUNCE_DISMISS_KEY = 'sp-announce-dismissed';

    function announcementCfg() { return (APP.announcement && APP.announcement.enabled) ? APP.announcement : null; }

    function buildAnnouncement() {
        if (ANNOUNCE.built) return;
        var cfg = announcementCfg();
        if (!cfg) return;
        var panel = document.createElement('div');
        panel.className = 'announcement-panel glass-panel';
        panel.id = 'announcement-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', t('公告'));
        panel.innerHTML = ''
            + '<button type="button" class="panel-close-btn announcement-close" aria-label="' + escAttr(t('退出')) + '"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
            + '<header class="announcement-header"><h3>' + escapeHtml(t('公告')) + '</h3></header>'
            + '<div class="announcement-content side-panel-scroll"></div>';
        document.body.appendChild(panel);
        var content = panel.querySelector('.announcement-content');
        content.innerHTML = cfg.html || '';
        // Register for translation: zh is a no-op, en/ja translate via the
        // /translate cache warmed on save (falls back to a live call if cold).
        i18nRegister(content, 'html');
        panel.querySelector('.announcement-close').addEventListener('click', function () { closeAnnouncement(true); });
        ANNOUNCE.panel = panel;
        ANNOUNCE.contentEl = content;
        ANNOUNCE.built = true;
        window.addEventListener('resize', function () { if (ANNOUNCE.open) syncAnnouncementGeom(); });
    }

    // Position below the page-links bar; width = the bar's current width so the
    // two align, right edges flush (v1.4.4 item 6).
    function syncAnnouncementGeom() {
        var panel = ANNOUNCE.panel;
        var bar = document.getElementById('page-links-bar');
        if (!panel || !bar) return;
        var r = bar.getBoundingClientRect();
        panel.style.width = r.width + 'px';
        panel.style.left = r.left + 'px';
        panel.style.top = (r.bottom + 8) + 'px';
    }

    // v1.4.5 (item 1): while the panel is open, ANY interaction elsewhere —
    // a pointerdown anywhere outside the panel, or Esc — collapses it. Two
    // carve-outs: clicks inside the panel (e.g. an announcement link) stay open,
    // and clicks on the 公告 toggle button are left to toggleAnnouncement (its
    // own re-click logic closes+remembers, so we must not double-fire here).
    // A casual outside dismiss does NOT remember (still auto-opens next visit);
    // only the ✕ button (closeAnnouncement(true)) silences it.
    function onAnnouncementOutside(e) {
        if (!ANNOUNCE.open || !ANNOUNCE.panel) return;
        if (ANNOUNCE.panel.contains(e.target)) return;              // inside the panel
        if (e.target.closest && e.target.closest('[data-sp-panel="announcement"]')) return; // the toggle
        closeAnnouncement(false);
    }
    function onAnnouncementKey(e) {
        if (ANNOUNCE.open && (e.key === 'Escape' || e.key === 'Esc')) closeAnnouncement(false);
    }

    function openAnnouncement() {
        buildAnnouncement();
        if (!ANNOUNCE.panel) return;
        syncAnnouncementGeom();
        ANNOUNCE.open = true;
        ANNOUNCE.panel.classList.add('active');
        // Defer listener attach to the next frame so the very click that opened
        // the panel (still bubbling) can't immediately close it.
        requestAnimationFrame(function () {
            if (!ANNOUNCE.open) return;
            document.addEventListener('pointerdown', onAnnouncementOutside, true);
            document.addEventListener('keydown', onAnnouncementKey);
        });
    }

    function closeAnnouncement(remember) {
        if (!ANNOUNCE.panel) return;
        ANNOUNCE.open = false;
        ANNOUNCE.panel.classList.remove('active');
        document.removeEventListener('pointerdown', onAnnouncementOutside, true);
        document.removeEventListener('keydown', onAnnouncementKey);
        if (remember) {
            var cfg = announcementCfg();
            try { if (cfg) localStorage.setItem(ANNOUNCE_DISMISS_KEY, cfg.hash); } catch (e) {}
        }
    }

    function toggleAnnouncement() {
        if (ANNOUNCE.open) closeAnnouncement(true); else openAnnouncement();
    }

    // Auto-open on load unless disabled or the reader already dismissed THIS
    // announcement (matched by content hash).
    function maybeAutoOpenAnnouncement() {
        var cfg = announcementCfg();
        if (!cfg || !cfg.autoOpen) return;
        var dismissed = '';
        try { dismissed = localStorage.getItem(ANNOUNCE_DISMISS_KEY) || ''; } catch (e) {}
        if (dismissed === cfg.hash) return;
        openAnnouncement();
    }

    // Refresh translatable chrome on language switch (header + labels). Content
    // itself re-translates via i18nRetranslateAll (it carries data-sp-tr).
    function updateAnnouncementChrome() {
        if (!ANNOUNCE.panel) return;
        var h = ANNOUNCE.panel.querySelector('.announcement-header h3');
        if (h) h.textContent = t('公告');
        ANNOUNCE.panel.setAttribute('aria-label', t('公告'));
        var close = ANNOUNCE.panel.querySelector('.announcement-close');
        if (close) close.setAttribute('aria-label', t('退出'));
    }

    function ensureSidePanels() {
        if (sidePanels.built) return;
        sidePanels.friend = buildSidePanel('friend', '友链');
        sidePanels.guestbook = buildSidePanel('guestbook', '留言');
        sidePanels.photowall = buildSidePanel('photowall', '照片墙');
        sidePanels.built = true;
    }

    function buildSidePanel(kind, title) {
        var panel = document.createElement('div');
        panel.className = 'side-panel side-panel--' + kind + ' glass-panel';
        panel.id = kind + '-panel';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-label', title);
        var bottomBand = (kind === 'guestbook') ? '' : '<div class="sp-frost-band sp-frost-band--bottom is-visible" aria-hidden="true"></div>';
        panel.innerHTML = ''
            + '<button type="button" class="panel-close-btn side-panel-close" aria-label="关闭"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
            + '<div class="sp-frost-band sp-frost-band--top" aria-hidden="true"></div>'
            + bottomBand
            + '<div class="side-panel-scroll"></div>';
        document.body.appendChild(panel);

        panel.querySelector('.side-panel-close').addEventListener('click', function () { closeSidePanel(kind); });

        var scroll = panel.querySelector('.side-panel-scroll');
        var bandTop = panel.querySelector('.sp-frost-band--top');
        var bandBottom = panel.querySelector('.sp-frost-band--bottom'); // guestbook 无底部带 → null
        var scheduled = false;
        scroll.addEventListener('scroll', function () {
            if (scheduled) return;
            scheduled = true;
            requestAnimationFrame(function () {
                scheduled = false;
                if (bandTop) bandTop.classList.toggle('is-visible', scroll.scrollTop > 6);
                // v1.4.2: 到达最底部时淡出底部毛玻璃带（友链/照片墙有底部带；留言板无，跳过）。
                if (bandBottom) {
                    var atBottom = scroll.scrollTop + scroll.clientHeight >= scroll.scrollHeight - 2;
                    bandBottom.classList.toggle('is-at-bottom', atBottom);
                }
            });
        });
        return panel;
    }

    function toggleSidePanel(which, btn) {
        if (sidePanels.open === which) { closeSidePanel(which); return; }
        openSidePanel(which, btn);
    }

    // FLIP a side panel between its clicked bar-button rect and its resting box,
    // reusing the article-panel motion. `srcRect` is the button rect; on open we
    // grow from it, on close we shrink back to it.
    function flipSidePanel(panel, srcRect, opening, onDone) {
        var reduce = state.isMobile || prefersReducedMotion() || !srcRect;
        if (reduce) {
            // No FLIP — let the CSS transition handle the slide/fade.
            panel.classList.toggle('active', !!opening);
            if (onDone) { setTimeout(onDone, opening ? 0 : 340); }
            return;
        }
        // Snap to the resting layout (transition off) so we can measure the true box.
        panel.classList.add('side-panel--instant');
        panel.classList.add('active');
        void panel.offsetHeight;
        var dst = panel.getBoundingClientRect();
        if (!dst.width) {
            panel.classList.toggle('active', !!opening);
            requestAnimationFrame(function () { panel.classList.remove('side-panel--instant'); });
            if (onDone) onDone();
            return;
        }
        // Only animate transform + opacity. We deliberately do NOT animate
        // borderRadius: a filled (fill:both) close animation used to keep
        // holding borderRadius:999px, and the next open read that back via
        // getComputedStyle as its target radius → the panel opened as a pill /
        // circle (v1.3.8 bug). The radius now always stays the panel's CSS
        // --panel-radius.
        var shrunk = { transform: collapseTransform(srcRect, dst), opacity: 0.15 };
        var full = { transform: 'translate(0,0) scale(1,1)', opacity: 1 };
        var frames = opening ? [shrunk, full] : [full, shrunk];
        if (panel._flip) { panel._flip.cancel(); panel._flip = null; }
        panel.style.transformOrigin = 'top left';
        var anim = panel.animate(frames, {
            duration: opening ? ARTICLE_MOTION.openDuration : ARTICLE_MOTION.closeDuration,
            easing: ARTICLE_MOTION.easing,
            fill: 'both'
        });
        panel._flip = anim;
        anim.onfinish = function () {
            if (panel._flip !== anim) return; // superseded by a newer flip
            panel.style.transform = '';
            panel.style.transformOrigin = '';
            if (!opening) panel.classList.remove('active');
            panel.classList.remove('side-panel--instant');
            // Cancel so no fill:both state lingers on the element.
            anim.cancel();
            panel._flip = null;
            if (onDone) onDone();
        };
    }

    function openSidePanel(which, btn) {
        ensureSidePanels();
        var srcRect = btn ? btn.getBoundingClientRect() : null;
        var doOpen = function () {
            sidePanels.open = which;
            var panel = sidePanels[which];
            panel._srcRect = srcRect; // remember origin so outside-close can shrink back
            flipSidePanel(panel, srcRect, true);
            if (which === 'friend') { loadFriendPanel(panel); }
            else if (which === 'guestbook') { loadGuestbookPanel(panel); }
            else if (which === 'photowall') { loadPhotoWallPanel(panel); }
        };
        // 友链 / 留言 share the right slot: collapse the other one first.
        if (sidePanels.open && sidePanels.open !== which) {
            closeSidePanel(sidePanels.open, doOpen);
        } else {
            doOpen();
        }
    }

    function closeSidePanel(which, cb) {
        // v1.4.1: when the photo-wall panel is closing, tear down any open
        // per-photo popup first so it doesn't outlive its host panel.
        if (which === 'photowall' && typeof closePhotoWallPopup === 'function') {
            closePhotoWallPopup();
        }
        var panel = sidePanels[which];
        if (!panel || !panel.classList.contains('active')) { if (panel) panel.classList.remove('active'); if (sidePanels.open === which) sidePanels.open = null; if (cb) cb(); return; }
        if (sidePanels.open === which) sidePanels.open = null;
        flipSidePanel(panel, panel._srcRect, false, cb);
    }

    // Close whichever side panel (友链/留言) is open. Used by the map-blank
    // click and the article-image map-fly so the panels don't obscure the map.
    function closeAllSidePanels() {
        if (sidePanels.open) closeSidePanel(sidePanels.open);
    }

    // ---- 友链 ----
    function loadFriendPanel(panel) {
        var scroll = panel.querySelector('.side-panel-scroll');
        scroll.scrollTop = 0;
        scroll.innerHTML = '<header class="side-panel-header"><h3>' + escapeHtml(t('友链')) + '</h3></header><div class="friend-grid"><p class="friend-loading">' + escapeHtml(t('加载中…')) + '</p></div>';
        fetchFromRest('sphotography/v1/friend-links').then(function (data) {
            if (sidePanels.open !== 'friend') return;
            var items = (data && Array.isArray(data.items)) ? data.items : [];
            renderFriendCards(scroll, items);
        });
    }

    function renderFriendCards(scroll, items) {
        var grid;
        if (items.length) {
            var cards = items.map(function (it) {
                var thumb = it.thumb
                    ? '<span class="friend-card-thumb"><img src="' + escapeHtml(it.thumb) + '" alt="" loading="lazy"></span>'
                    : '<span class="friend-card-thumb friend-card-thumb--empty"></span>';
                return '<a class="friend-card" href="' + escapeHtml(it.url) + '" target="_blank" rel="noopener">'
                    + thumb
                    + '<span class="friend-card-name">' + escapeHtml(it.name || it.url) + '</span>'
                    + '</a>';
            }).join('');
            grid = '<div class="friend-grid">' + cards + '</div>';
        } else {
            grid = '<p class="friend-empty">' + escapeHtml(t('还没有友链。')) + '</p>';
        }
        scroll.innerHTML = '<header class="side-panel-header"><h3>' + escapeHtml(t('友链')) + '</h3></header>' + grid + buildFriendApplyForm();
        wireFriendApply(scroll);
    }

    function buildFriendApplyForm() {
        return ''
            + '<div class="friend-apply">'
            +   '<h4 class="friend-apply-title">' + escapeHtml(t('申请友链')) + '</h4>'
            +   '<form class="friend-apply-form" novalidate>'
            +     '<input type="email" class="friend-apply-email" placeholder="' + escAttr(t('你的邮箱 *')) + '" autocomplete="email">'
            +     '<input type="url" class="friend-apply-url" placeholder="' + escAttr(t('你的网站链接 *')) + '">'
            +     '<input type="text" class="friend-apply-name" placeholder="' + escAttr(t('站点名称（可选）')) + '">'
            +     '<textarea class="friend-apply-msg" rows="2" placeholder="' + escAttr(t('留言（可选）')) + '"></textarea>'
            +     '<div class="friend-apply-footer"><span class="friend-apply-feedback"></span><button type="submit" class="friend-apply-submit">' + escapeHtml(t('提交申请')) + '</button></div>'
            +   '</form>'
            + '</div>';
    }

    function wireFriendApply(scroll) {
        var form = scroll.querySelector('.friend-apply-form');
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var email = (form.querySelector('.friend-apply-email').value || '').trim();
            var url = (form.querySelector('.friend-apply-url').value || '').trim();
            var name = (form.querySelector('.friend-apply-name').value || '').trim();
            var msg = (form.querySelector('.friend-apply-msg').value || '').trim();
            var fb = form.querySelector('.friend-apply-feedback');
            fb.className = 'friend-apply-feedback';
            if (!email || !url) { fb.textContent = t('请填写邮箱和链接。'); fb.classList.add('is-error'); return; }
            var btn = form.querySelector('.friend-apply-submit');
            btn.disabled = true; btn.textContent = t('提交中…');
            postJson(CONFIG.restBase + '/sphotography/v1/friend-links/apply', { email: email, url: url, name: name, message: msg }).then(function (r) {
                btn.disabled = false; btn.textContent = t('提交申请');
                if (!r.ok) { fb.textContent = ccError(r); fb.classList.add('is-error'); return; }
                form.reset();
                fb.textContent = t('申请已提交，等待站长审核。');
                fb.classList.add('is-success');
            });
        });
    }

    // ---- 留言 (guestbook) ----
    function guestbookPostId() { return (APP.guestbook && APP.guestbook.postId) ? APP.guestbook.postId : 0; }

    function guestbookSortHtml() {
        return ''
            + '<div class="comment-sort gb-sort-ctrl" role="group" aria-label="留言排序">'
            +   '<button type="button" class="comment-sort-btn" data-gb-sort="time"></button>'
            +   '<button type="button" class="comment-sort-btn" data-gb-sort="likes">点赞</button>'
            + '</div>';
    }

    function updateGuestbookSortUI(scroll) {
        var timeBtn = scroll.querySelector('.comment-sort-btn[data-gb-sort="time"]');
        var likeBtn = scroll.querySelector('.comment-sort-btn[data-gb-sort="likes"]');
        if (timeBtn) {
            timeBtn.textContent = '时间 ' + (GB_STATE.order === 'desc' ? '↓' : '↑');
            timeBtn.classList.toggle('is-active', GB_STATE.sort === 'time');
        }
        if (likeBtn) likeBtn.classList.toggle('is-active', GB_STATE.sort === 'likes');
    }

    function loadGuestbookPanel(panel) {
        GB_STATE = { mode: 'random', page: 1, sort: 'time', order: 'asc', hasMore: false, loading: false };
        var scroll = panel.querySelector('.side-panel-scroll');
        scroll.scrollTop = 0;
        scroll.innerHTML = ''
            + '<header class="side-panel-header gb-head"><h3>' + escapeHtml(t('留言')) + '</h3><div class="gb-sort-wrap" hidden>' + guestbookSortHtml() + '</div><span class="gb-count" hidden></span></header>'
            + '<ul class="comment-list gb-list" id="gb-list"><li class="gb-loading">' + escapeHtml(t('加载中…')) + '</li></ul>'
            + '<div class="gb-more" id="gb-more"></div>';
        ensureGuestbookComposer(panel);
        wireGuestbookList(panel, scroll);
        loadGuestbook(scroll, true);
    }

    function loadGuestbook(scroll, replace) {
        if (GB_STATE.loading) return;
        GB_STATE.loading = true;
        var listEl = scroll.querySelector('#gb-list');
        var params = { mode: GB_STATE.mode, page: GB_STATE.page };
        if (GB_STATE.mode === 'all') { params.sort = GB_STATE.sort; params.order = GB_STATE.order; }
        ccGet('sphotography/v1/guestbook', params).then(function (data) {
            GB_STATE.loading = false;
            if (sidePanels.open !== 'guestbook') return;
            var loading = listEl.querySelector('.gb-loading');
            if (loading) loading.remove();
            if (!data) { if (replace) listEl.innerHTML = '<li class="gb-empty">加载失败，请稍后再试。</li>'; return; }
            var items = Array.isArray(data.items) ? data.items : [];
            GB_STATE.hasMore = !!data.has_more;
            GB_STATE.page = data.page || GB_STATE.page;
            var countEl = scroll.querySelector('.gb-count');
            if (countEl && typeof data.total === 'number') { countEl.hidden = false; countEl.textContent = '(' + data.total + ')'; }
            if (replace) listEl.innerHTML = '';
            if (replace && !items.length) {
                listEl.innerHTML = '<li class="gb-empty">还没有留言，来写第一条吧。</li>';
            } else {
                listEl.insertAdjacentHTML('beforeend', items.map(function (c) { return buildCommentNode(c, false); }).join(''));
                applyFolding(listEl);
                i18nScan(listEl); // v1.4.3: 留言正文按需翻译（en/ja）
            }
            renderGuestbookMore(scroll);
        });
    }

    function renderGuestbookMore(scroll) {
        var more = scroll.querySelector('#gb-more');
        if (!more) return;
        more.innerHTML = '';
        if (GB_STATE.mode === 'random') {
            more.innerHTML = '<button type="button" class="gb-showall" data-gb-showall>展示全部留言</button>';
        } else if (GB_STATE.hasMore) {
            more.innerHTML = '<button type="button" class="comment-page-btn gb-loadmore" data-gb-page="' + (GB_STATE.page + 1) + '">加载更多留言</button>';
        }
    }

    function wireGuestbookList(panel, scroll) {
        var pid = guestbookPostId();
        var listEl = scroll.querySelector('#gb-list');
        var countEl = scroll.querySelector('.gb-count');
        scroll.addEventListener('click', function (e) {
            var t = e.target;

            var showAll = t.closest('[data-gb-showall]');
            if (showAll) {
                GB_STATE.mode = 'all'; GB_STATE.page = 1;
                var sortWrap = scroll.querySelector('.gb-sort-wrap');
                if (sortWrap) sortWrap.hidden = false;
                updateGuestbookSortUI(scroll);
                listEl.innerHTML = '<li class="gb-loading">加载中…</li>';
                loadGuestbook(scroll, true);
                return;
            }

            var sortBtn = t.closest('[data-gb-sort]');
            if (sortBtn) {
                var s = sortBtn.getAttribute('data-gb-sort');
                if (s === 'time') {
                    if (GB_STATE.sort === 'time') { GB_STATE.order = (GB_STATE.order === 'asc') ? 'desc' : 'asc'; }
                    else { GB_STATE.sort = 'time'; }
                } else if (s === 'likes') {
                    if (GB_STATE.sort === 'likes') return;
                    GB_STATE.sort = 'likes';
                }
                GB_STATE.page = 1;
                updateGuestbookSortUI(scroll);
                listEl.innerHTML = '<li class="gb-loading">加载中…</li>';
                loadGuestbook(scroll, true);
                return;
            }

            var pageBtn = t.closest('[data-gb-page]');
            if (pageBtn) {
                GB_STATE.page = parseInt(pageBtn.getAttribute('data-gb-page'), 10) || (GB_STATE.page + 1);
                loadGuestbook(scroll, false);
                return;
            }

            var likeBtn = t.closest('[data-cc-like]');
            if (likeBtn) {
                var lid = likeBtn.getAttribute('data-cc-like');
                postJson(ccEndpoint('/' + lid + '/like'), {}).then(function (r) {
                    if (!r.ok) return;
                    likeBtn.classList.toggle('is-liked', !!r.data.liked);
                    var cnt = likeBtn.querySelector('.comment-like-count');
                    if (cnt) cnt.textContent = r.data.likes;
                });
                return;
            }

            var pinBtn = t.closest('[data-cc-pin]');
            if (pinBtn) {
                postJson(ccEndpoint('/' + pinBtn.getAttribute('data-cc-pin') + '/pin'), {}).then(function (r) {
                    if (!r.ok) return;
                    listEl.innerHTML = '<li class="gb-loading">加载中…</li>';
                    loadGuestbook(scroll, true);
                });
                return;
            }

            var replyBtn = t.closest('[data-cc-reply]');
            if (replyBtn) { openReplyForm(pid, replyBtn, listEl, countEl); return; }

            var editBtn = t.closest('[data-cc-edit]');
            if (editBtn) { openEditForm(pid, editBtn, listEl, countEl); return; }

            var foldBtn = t.closest('[data-cc-fold]');
            if (foldBtn) {
                var text = foldBtn.previousElementSibling;
                if (text && text.classList.contains('comment-text')) {
                    var expanded = text.classList.toggle('is-expanded');
                    text.style.maxHeight = expanded ? 'none' : (CCFG.foldPx || 200) + 'px';
                    foldBtn.textContent = expanded ? '收起' : '展开阅读全文';
                }
                return;
            }

            var histBtn = t.closest('[data-cc-history]');
            if (histBtn) { toggleHistory(histBtn); return; }
        });
    }

    // Guestbook composer: separate rounded email / message inputs + a circular
    // send button, pinned to the panel bottom over a frosted band. The message
    // box (and the band) grow upward as the text wraps.
    function ensureGuestbookComposer(panel) {
        if (panel.querySelector('.gb-composer')) return;
        var composer = document.createElement('div');
        composer.className = 'gb-composer';
        composer.innerHTML = ''
            + '<div class="gb-composer-inner">'
            +   '<div class="gb-feedback"></div>'
            +   '<div class="gb-fields">'
            +     '<input type="email" class="gb-email" placeholder="邮箱（不公开）" autocomplete="email">'
            +     '<input type="text" class="gb-nick" placeholder="昵称（可选）" autocomplete="nickname">'
            +   '</div>'
            +   '<div class="gb-msg-row">'
            +     '<textarea class="gb-msg" rows="1" placeholder="' + escAttr(t('写下留言…支持 Markdown')) + '"></textarea>'
            +     '<button type="button" class="gb-send" aria-label="发送"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></button>'
            +   '</div>'
            + '</div>';
        panel.appendChild(composer);

        var ta = composer.querySelector('.gb-msg');
        var scroll = panel.querySelector('.side-panel-scroll');
        function syncComposerPad() {
            scroll.style.paddingBottom = (composer.offsetHeight + 12) + 'px';
        }
        function autoGrow() {
            ta.style.height = 'auto';
            ta.style.height = Math.min(ta.scrollHeight, 140) + 'px';
            syncComposerPad();
        }
        ta.addEventListener('input', autoGrow);
        composer.querySelector('.gb-send').addEventListener('click', function () { submitGuestbook(panel); });
        ta.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); submitGuestbook(panel); }
        });
        // Initial padding once laid out.
        requestAnimationFrame(syncComposerPad);
    }

    function submitGuestbook(panel) {
        var pid = guestbookPostId();
        if (!pid) return;
        var composer = panel.querySelector('.gb-composer');
        var scroll = panel.querySelector('.side-panel-scroll');
        var listEl = scroll.querySelector('#gb-list');
        var countEl = scroll.querySelector('.gb-count');
        var email = (composer.querySelector('.gb-email').value || '').trim();
        var nick = (composer.querySelector('.gb-nick').value || '').trim();
        var msg = (composer.querySelector('.gb-msg').value || '').trim();
        var fb = composer.querySelector('.gb-feedback');
        var sendBtn = composer.querySelector('.gb-send');
        fb.className = 'gb-feedback';
        if (!msg) { fb.textContent = '请输入留言内容。'; fb.classList.add('is-error'); return; }
        if (!APP.loggedIn && !email) { fb.textContent = '请填写邮箱（不公开）。'; fb.classList.add('is-error'); return; }
        var payload = { post: pid, content: msg, parent: 0 };
        if (!APP.loggedIn) { payload.author_name = nick || '匿名'; payload.author_email = email; }
        payload.notify = 0;
        sendBtn.disabled = true;
        postJson(ccEndpoint(''), payload).then(function (r) {
            sendBtn.disabled = false;
            if (!r.ok) { fb.textContent = ccError(r); fb.classList.add('is-error'); return; }
            if (r.data.status !== 'approved') {
                fb.textContent = '留言已提交，等待审核后显示。';
                fb.classList.add('is-success');
                composer.querySelector('.gb-msg').value = '';
                composer.querySelector('.gb-msg').style.height = 'auto';
                return;
            }
            var empty = listEl.querySelector('.gb-empty');
            if (empty) empty.remove();
            // Prepend the new message so it is immediately visible.
            listEl.insertAdjacentHTML('afterbegin', buildCommentNode(r.data.comment, false));
            applyFolding(listEl);
            i18nScan(listEl); // v1.4.3: 新留言按当前语言翻译
            if (countEl) {
                var cur = parseInt((countEl.textContent || '').replace(/\D/g, ''), 10) || 0;
                countEl.hidden = false; countEl.textContent = '(' + (cur + 1) + ')';
            }
            composer.querySelector('.gb-msg').value = '';
            composer.querySelector('.gb-msg').style.height = 'auto';
            fb.textContent = '留言成功！';
            fb.classList.add('is-success');
        });
    }

    // ---------------------------------------------------------------
    // 10a3. 照片墙 (photo wall) panel — grid grouped by shot day, infinite
    // scroll, per-photo popup (article / detail / location) + full-panel
    // detail view, and a three-case "view location" map fly. (v1.3.9)
    // ---------------------------------------------------------------
    var PW = { page: 0, perPage: (APP.photoWall && APP.photoWall.perPage) || 30, loading: false, hasMore: true, lastGroup: null, curGrid: null, items: [], scrollEl: null, panel: null };

    function pwGroupLabel(group) {
        if (group === 'pinned') return '置顶';
        if (group === 'unknown' || !group) return '未知日期';
        return formatDate(group);
    }

    function loadPhotoWallPanel(panel) {
        PW = { page: 0, perPage: (APP.photoWall && APP.photoWall.perPage) || 30, loading: false, hasMore: true, lastGroup: null, curGrid: null, items: [], scrollEl: null, panel: panel, detailIndex: -1 };
        var openDetail = panel.querySelector('.pw-detail');
        if (openDetail) openDetail.classList.remove('is-open'); // reset stale detail overlay
        var scroll = panel.querySelector('.side-panel-scroll');
        PW.scrollEl = scroll;
        scroll.scrollTop = 0;
        scroll.innerHTML = ''
            + '<header class="side-panel-header"><h3>' + escapeHtml(t('照片墙')) + '</h3></header>'
            + '<div class="pw-container" id="pw-container"></div>'
            + '<div class="pw-more" id="pw-more"></div>';

        if (!panel._pwWired) {
            panel._pwWired = true;
            // Infinite scroll: prefetch the next page as the bottom approaches.
            var scheduled = false;
            scroll.addEventListener('scroll', function () {
                if (scheduled) return;
                scheduled = true;
                requestAnimationFrame(function () {
                    scheduled = false;
                    if (PW.loading || !PW.hasMore) return;
                    if (scroll.scrollTop + scroll.clientHeight >= scroll.scrollHeight - 600) {
                        loadPhotoWallPage();
                    }
                });
            });
            // Delegated clicks: open the per-photo popup / act on its options.
            scroll.addEventListener('click', function (e) { onPhotoWallClick(e, scroll); });
        }

        loadPhotoWallPage();
    }

    function loadPhotoWallPage() {
        if (PW.loading || !PW.hasMore) return;
        PW.loading = true;
        var moreEl = PW.scrollEl.querySelector('#pw-more');
        if (moreEl) moreEl.textContent = '加载中…';
        fetchFromRest('sphotography/v1/wall-photos', { page: PW.page + 1, per_page: PW.perPage }).then(function (data) {
            PW.loading = false;
            if (sidePanels.open !== 'photowall') return;
            if (!data) { if (moreEl) moreEl.textContent = '加载失败'; return; }
            PW.page = data.page || (PW.page + 1);
            PW.hasMore = !!data.has_more;
            var items = Array.isArray(data.items) ? data.items : [];
            var container = PW.scrollEl.querySelector('#pw-container');
            if (PW.items.length === 0 && items.length === 0) {
                container.innerHTML = '<p class="pw-empty">' + escapeHtml(t('还没有照片。')) + '</p>';
            }
            items.forEach(function (it) {
                var idx = PW.items.length;
                PW.items.push(it);
                if (it.group !== PW.lastGroup || !PW.curGrid) {
                    PW.lastGroup = it.group;
                    var grp = document.createElement('div');
                    grp.className = 'pw-group';
                    grp.innerHTML = '<div class="pw-group-header">' + escapeHtml(pwGroupLabel(it.group)) + '</div><div class="pw-group-grid"></div>';
                    container.appendChild(grp);
                    PW.curGrid = grp.querySelector('.pw-group-grid');
                }
                var cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'pw-cell';
                cell.setAttribute('data-pw-idx', idx);
                cell.innerHTML = '<img class="pw-img" src="' + escapeHtml(it.thumbnail || it.full || '') + '" alt="' + escapeHtml(it.title || '') + '" loading="lazy">';
                PW.curGrid.appendChild(cell);
            });
            if (moreEl) moreEl.textContent = PW.hasMore ? '' : '';
        });
    }

    // v1.4.1: per-photo popup state. The popup is now portal-mounted to
    // <body> (via a single shared #pw-popup-portal) so it lives outside
    // the side panel's overflow:hidden + backdrop-filter stacking context
    // — that fixes the "leftmost cell's popup is clipped by the panel's
    // left border" bug. Open/close animations are driven by the Web
    // Animations API (Element.animate), not CSS keyframes, so the close
    // path has no animation-fill-mode flicker.
    var PW_POPUP = {
        openAnim: null,        // Array<Animation> for in-flight open
        closing: false,        // true while a close is in progress
        active: null,          // the current popup element (or null)
        cell: null,            // the cell the popup is anchored to
        panel: null,           // the side panel (for scroll/resize listeners)
        portal: null,          // the #pw-popup-portal element
        scrollHandler: null,   // panel-scroll listener (closes the popup)
        resizeHandler: null    // window-resize listener (closes the popup)
    };

    function ensurePopupPortal() {
        if (PW_POPUP.portal && PW_POPUP.portal.isConnected) return PW_POPUP.portal;
        var p = document.getElementById('pw-popup-portal');
        if (!p) {
            p = document.createElement('div');
            p.id = 'pw-popup-portal';
            p.className = 'pw-popup-portal';
            document.body.appendChild(p);
        }
        PW_POPUP.portal = p;
        return p;
    }

    // True if the cell is the leftmost in its CSS-grid row (no other cell
    // at the same offsetTop has a smaller offsetLeft). Used to flip the
    // popup to the right side of the cell so it never gets clipped by the
    // side panel's left edge.
    function isLeftmostInRow(cell) {
        var top = cell.offsetTop;
        var left = cell.offsetLeft;
        var siblings = cell.parentNode.children;
        for (var i = 0; i < siblings.length; i++) {
            var s = siblings[i];
            if (s === cell) continue;
            if (s.offsetTop === top && s.offsetLeft < left) return false;
        }
        return true;
    }

    // Detach scroll/resize listeners and clear the active popup ref. Called
    // when the popup is removed for any reason (close, scroll, resize, panel
    // close, action-click). Does NOT animate or remove the element.
    function teardownPopupState() {
        if (PW_POPUP.scrollHandler && PW_POPUP.panel) {
            var scroll = PW_POPUP.panel.querySelector('.side-panel-scroll');
            if (scroll) scroll.removeEventListener('scroll', PW_POPUP.scrollHandler);
        }
        if (PW_POPUP.resizeHandler) {
            window.removeEventListener('resize', PW_POPUP.resizeHandler);
        }
        PW_POPUP.scrollHandler = null;
        PW_POPUP.resizeHandler = null;
        PW_POPUP.active = null;
        PW_POPUP.cell = null;
        PW_POPUP.panel = null;
        PW_POPUP.closing = false;
        PW_POPUP.openAnim = null;
    }

    // Animate one button. Pure helper; the caller passes the keyframe pair
    // and the per-button delay. The returned Animation is in .openAnim
    // (open path) or its .finished promise is awaited (close path).
    function animatePwBtn(btn, keyframes, delay) {
        return btn.animate(keyframes, {
            duration: 180,
            delay: delay || 0,
            easing: 'cubic-bezier(0.16, 1, 0.3, 1)',
            fill: 'forwards'
        });
    }

    function closePhotoWallPopup() {
        // v1.4.1: Web Animations API owns the open/close lifecycle. The close
        // path cancels any in-flight open animations, then animates each
        // button to the closed state with a reverse stagger (button 1 starts
        // at 80ms, 2 at 40ms, 3 at 0ms), and removes the popup only after
        // all three animations finish. If a close is already in progress,
        // remove the element instantly (defensive).
        var p = PW_POPUP.active;
        if (!p) return;
        if (PW_POPUP.closing) {
            if (p.parentNode) p.parentNode.removeChild(p);
            teardownPopupState();
            return;
        }
        PW_POPUP.closing = true;

        var buttons = Array.prototype.slice.call(p.querySelectorAll('.pw-side-btn'));

        // v1.4.6 (item 6): bake each button's CURRENT visual state into inline
        // styles BEFORE cancelling the open animation. cancel() drops the open
        // animation's forwards-fill, which would otherwise snap the button back
        // to its CSS base (opacity:0, translateX(8px)) — making the whole popup
        // blink out, then the close stagger re-shows & fades it (the flicker).
        // commitStyles() persists the visible state so the close simply fades
        // from where the button actually is.
        if (PW_POPUP.openAnim) {
            PW_POPUP.openAnim.forEach(function (a) {
                try { a.commitStyles(); } catch (e) {}
                try { a.cancel(); } catch (e) {}
            });
            PW_POPUP.openAnim = null;
        }
        // Defensive: a button whose open animation was missing is treated as
        // fully open, so the close fades from visible rather than the base.
        buttons.forEach(function (btn) {
            if (!btn.style.opacity) { btn.style.opacity = '1'; btn.style.transform = 'translateX(0)'; }
        });

        var closeDelays = [80, 40, 0];
        var promises = buttons.map(function (btn, i) {
            // Single-keyframe target → animates FROM the committed inline state
            // (no jump). During the stagger delay the button holds its committed
            // visible state, then fades out cleanly.
            return btn.animate(
                [{ opacity: 0, transform: 'translateX(8px)' }],
                { duration: 180, delay: closeDelays[i] || 0, easing: 'cubic-bezier(0.16, 1, 0.3, 1)', fill: 'forwards' }
            ).finished;
        });

        Promise.all(promises).then(function () {
            // Guard against re-entrancy: if a new popup opened in the
            // meantime, the old one's element might already be detached.
            if (p && p.parentNode) p.parentNode.removeChild(p);
            // Only reset state if `p` is still the same active popup.
            if (PW_POPUP.active === p) teardownPopupState();
        }).catch(function () {
            if (p && p.parentNode) p.parentNode.removeChild(p);
            if (PW_POPUP.active === p) teardownPopupState();
        });
    }

    function onPhotoWallClick(e, scroll) {
        // The popup lives in the portal now; its buttons carry data-pw-act
        // and the cell click handler is still on the panel scroll. Only the
        // "click elsewhere" branch closes any open popup.
        var cell = e.target.closest('.pw-cell');
        if (cell) {
            e.stopPropagation();
            var i = parseInt(cell.getAttribute('data-pw-idx'), 10);
            togglePhotoWallPopup(cell, i);
            return;
        }
        // Click anywhere else in the scroll area (other than a cell or a
        // portal-resident popup) closes any open popup.
        closePhotoWallPopup();
    }

    function togglePhotoWallPopup(cell, idx) {
        var existing = PW_POPUP.active;
        var wasForThis = existing && existing.getAttribute('data-for') === String(idx);
        closePhotoWallPopup();
        if (wasForThis) return; // second click on same photo → just close
        var it = PW.items[idx];
        var hasGeo = it && it.lat !== '' && it.lat != null && it.lng !== '' && it.lng != null;
        // v1.4.1: the popup is portal-mounted to <body>, not the cell.
        // That puts it OUTSIDE the side panel's overflow:hidden and its
        // backdrop-filter stacking context, so it can never be clipped
        // by the panel's left border. Position is set in JS from the
        // cell's bounding rect (left or right of the cell depending on
        // whether the cell is the leftmost in its row).
        var portal = ensurePopupPortal();
        var pop = document.createElement('div');
        pop.className = 'pw-popup pw-popup--side';
        pop.setAttribute('data-for', String(idx));
        // Icons are reused from the existing detail-view buttons (article,
        // search/detail, location) so the visual language stays consistent.
        pop.innerHTML = ''
            + '<button type="button" class="pw-side-btn" data-pw-act="article" data-pw-idx="' + idx + '" title="' + escAttr(photoWallPopupTitle('article')) + '" aria-label="' + escAttr(photoWallPopupTitle('article')) + '">'
            +   '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>'
            + '</button>'
            + '<button type="button" class="pw-side-btn" data-pw-act="detail" data-pw-idx="' + idx + '" title="' + escAttr(photoWallPopupTitle('detail')) + '" aria-label="' + escAttr(photoWallPopupTitle('detail')) + '">'
            +   '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>'
            + '</button>'
            + (hasGeo ? '<button type="button" class="pw-side-btn" data-pw-act="location" data-pw-idx="' + idx + '" title="' + escAttr(photoWallPopupTitle('location')) + '" aria-label="' + escAttr(photoWallPopupTitle('location')) + '">'
            +   '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>'
            + '</button>' : '');
        // Self-contained click handler: the popup no longer lives inside
        // the panel scroll, so the panel's event delegation can't see it.
        pop.addEventListener('click', function (e) {
            var actBtn = e.target.closest('[data-pw-act]');
            if (!actBtn) return;
            e.stopPropagation();
            var i = parseInt(actBtn.getAttribute('data-pw-idx'), 10);
            var act = actBtn.getAttribute('data-pw-act');
            closePhotoWallPopup();
            if (act === 'article') photoWallViewArticle(PW.items[i]);
            else if (act === 'detail') openPhotoWallDetail(i);
            else if (act === 'location') photoWallViewLocation(PW.items[i]);
        });
        portal.appendChild(pop);

        // Position: left or right of the cell, aligned to the cell's top.
        // The flip avoids the panel's left border on the leftmost column.
        var POP_W = 38, POP_GAP = 8;
        var rect = cell.getBoundingClientRect();
        if (isLeftmostInRow(cell)) {
            pop.style.left = (rect.right + POP_GAP) + 'px';
        } else {
            pop.style.left = (rect.left - POP_W - POP_GAP) + 'px';
        }
        pop.style.top = rect.top + 'px';

        // Animate open. The Web Animations API gives us cancel() and
        // finished for free, so we don't have to manage the setTimeout /
        // classList dance that v1.4.0 had.
        var buttons = Array.prototype.slice.call(pop.querySelectorAll('.pw-side-btn'));
        PW_POPUP.openAnim = buttons.map(function (btn, i) {
            return animatePwBtn(btn, [
                { opacity: 0, transform: 'translateX(8px)' },
                { opacity: 1, transform: 'translateX(0)' }
            ], [0, 40, 80][i] || 0);
        });
        PW_POPUP.active = pop;
        PW_POPUP.cell = cell;
        PW_POPUP.panel = PW.panel;
        PW_POPUP.closing = false;

        // v1.4.1: close-on-scroll / close-on-resize. The popup is anchored
        // to the cell via getBoundingClientRect at creation time, but the
        // cell moves when the user scrolls within the panel or when the
        // window resizes; rather than re-anchor on every tick, we just
        // close the popup — that's the standard click-triggered-popover
        // pattern and avoids needing rAF + scroll math.
        var panel = PW.panel;
        if (panel) {
            var scroll = panel.querySelector('.side-panel-scroll');
            if (scroll) {
                PW_POPUP.scrollHandler = function () { closePhotoWallPopup(); };
                scroll.addEventListener('scroll', PW_POPUP.scrollHandler, { passive: true });
            }
        }
        PW_POPUP.resizeHandler = function () { closePhotoWallPopup(); };
        window.addEventListener('resize', PW_POPUP.resizeHandler, { passive: true });
    }

    // Localized tooltip labels for the side popup (extracted so they can be
    // tested / re-used if a third action is added later).
    function photoWallPopupTitle(act) {
        if (act === 'article') return t('查看对应文章');
        if (act === 'detail')  return t('查看照片详情');
        if (act === 'location') return t('查看照片位置');
        return '';
    }
    function escAttr(s) { return String(s || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;'); }

    function photoWallViewArticle(it) {
        if (!it || !it.postId) return;
        openSidebar();
        openArticle(it.postId, { scrollToImageId: it.id, scrollToImageUrl: it.full || it.thumbnail || '' });
    }

    // ---- full-panel detail view ----
    function openPhotoWallDetail(idx) {
        if (idx < 0 || idx >= PW.items.length) return;
        var panel = PW.panel;
        var detail = panel.querySelector('.pw-detail');
        if (!detail) {
            detail = document.createElement('div');
            detail.className = 'pw-detail';
            panel.appendChild(detail);
            detail.addEventListener('click', function (e) {
                var nav = e.target.closest('[data-pw-nav]');
                if (nav) { openPhotoWallDetail(PW.detailIndex + parseInt(nav.getAttribute('data-pw-nav'), 10)); return; }
                var act = e.target.closest('[data-pw-dact]');
                if (act) {
                    var a = act.getAttribute('data-pw-dact');
                    if (a === 'exit') { closePhotoWallDetail(); }
                    else if (a === 'article') { photoWallViewArticle(PW.items[PW.detailIndex]); }
                    else if (a === 'location') { photoWallViewLocation(PW.items[PW.detailIndex]); }
                }
            });
        }
        PW.detailIndex = idx;
        renderPhotoWallDetail(detail, idx);
        detail.classList.add('is-open');
    }

    function closePhotoWallDetail() {
        var detail = PW.panel && PW.panel.querySelector('.pw-detail');
        if (detail) detail.classList.remove('is-open');
    }

    function renderPhotoWallDetail(detail, idx) {
        var it = PW.items[idx];
        if (!it) return;
        var hasGeo = it.lat !== '' && it.lat != null && it.lng !== '' && it.lng != null;
        var rows = [];
        var dt = it.date ? (formatDate(it.date) + (it.time ? ' ' + it.time : '')) : '';
        if (dt) rows.push('<div class="pw-detail-row"><span>' + t('日期时间') + '</span><b>' + escapeHtml(dt) + '</b></div>');
        if (hasGeo) rows.push('<div class="pw-detail-row"><span>' + t('经纬度') + '</span><b>' + escapeHtml(Number(it.lat).toFixed(5) + ', ' + Number(it.lng).toFixed(5)) + '</b></div>');
        if (it.camera) rows.push('<div class="pw-detail-row"><span>' + t('拍摄设备') + '</span><b>' + escapeHtml(it.camera) + '</b></div>');
        var exposure = [it.aperture, it.shutter, (it.iso ? 'ISO ' + it.iso : '')].filter(Boolean).join('  ·  ');
        if (exposure) rows.push('<div class="pw-detail-row"><span>' + t('光圈快门ISO') + '</span><b>' + escapeHtml(exposure) + '</b></div>');

        var atStart = idx <= 0, atEnd = idx >= PW.items.length - 1;
        var arrow = function (dir, disabled, path) {
            return '<button type="button" class="pw-detail-arrow pw-detail-arrow--' + dir + '"' + (disabled ? ' disabled' : '') + ' data-pw-nav="' + (dir === 'prev' ? -1 : 1) + '" aria-label="' + escAttr(t(dir === 'prev' ? '上一张' : '下一张')) + '"><svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="' + path + '"/></svg></button>';
        };
        var locBtn = hasGeo ? '<button type="button" class="pw-detail-round" data-pw-dact="location" title="' + escAttr(t('查看照片位置')) + '"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></button>' : '';
        detail.innerHTML = ''
            + '<button type="button" class="pw-detail-exit" data-pw-dact="exit" aria-label="' + escAttr(t('退出')) + '"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>'
            + '<div class="pw-detail-stage">'
            +   arrow('prev', atStart, '15 18 9 12 15 6')
            +   '<img class="pw-detail-img" src="' + escapeHtml(it.full || it.thumbnail || '') + '" alt="' + escapeHtml(it.title || '') + '">'
            +   arrow('next', atEnd, '9 18 15 12 9 6')
            + '</div>'
            + '<div class="pw-detail-info">'
            +   '<div class="pw-detail-params">' + (rows.join('') || '<div class="pw-detail-row"><span>' + t('暂无参数') + '</span></div>') + '</div>'
            +   '<div class="pw-detail-actions">'
            +     '<button type="button" class="pw-detail-round" data-pw-dact="article" title="' + escAttr(t('查看对应文章')) + '"><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></button>'
            +     locBtn
            +   '</div>'
            + '</div>';
    }

    // ---- three-case "view location" fly ----
    // Centre of the free map area to the LEFT of the photo-wall panel.
    function photoWallLeftAreaCenter() {
        var H = window.innerHeight;
        var panel = sidePanels.photowall;
        var panelLeft = window.innerWidth * 0.6;
        if (panel) { var pr = panel.getBoundingClientRect(); if (pr.width) panelLeft = pr.left; }
        var leftBound = 0;
        if (state.sidebarOpen && dom.sidebar) {
            var sr = dom.sidebar.getBoundingClientRect();
            if (sr.width) leftBound = sr.right;
        }
        return { x: (leftBound + panelLeft) / 2, y: H / 2 };
    }

    // wgs: photo location in WGS-84 (from photo-wall data). See flyMapToPhoto.
    function flyMapToPhotoWallPhoto(wgs) {
        if (!state.map || !wgs || state.isMobile) return;
        // v1.4.4 (item 5): 触发地图位移时，同时收起地图上已打开的图片展开面板
        // （地图标记点开的照片网格面板 + 地区面板），让飞行目标不被遮挡。
        closeAllPhotoPanels();
        var coords = toMapCoords(wgs[0], wgs[1]);
        var lngLat = new maplibregl.LngLat(coords[0], coords[1]);
        var center = photoWallLeftAreaCenter();
        var offset = [center.x - window.innerWidth / 2, center.y - window.innerHeight / 2];
        var targetZoom = zoomForScale(5000, coords[1]);
        targetZoom = Math.max(CONFIG.minZoom, Math.min(CONFIG.maxZoom, targetZoom));
        var flyId = ++state.mapFlyId;
        removePulseDot();
        state.map.easeTo({ center: lngLat, offset: offset, duration: 1200, easing: easeInOutSine });
        state.map.once('moveend', function () {
            if (flyId !== state.mapFlyId) return;
            setTimeout(function () {
                if (flyId !== state.mapFlyId) return;
                state.map.easeTo({ zoom: targetZoom, around: lngLat, duration: 1600, easing: easeInOutSine });
                // v1.4.4 (item 4): drop the pulse dot + location popup in BOTH
                // region and normal mode once the zoom settles.
                state.map.once('moveend', function () { if (flyId === state.mapFlyId) showPulseDot(coords, wgs); });
            }, 100);
        });
    }

    function photoWallViewLocation(it) {
        if (!it) return;
        var hasGeo = it.lat !== '' && it.lat != null && it.lng !== '' && it.lng != null;
        if (!hasGeo) return;
        var coords = [parseFloat(it.lng), parseFloat(it.lat)];
        // Case C: article + sidebar open → collapse the article first, then fly.
        if (state.articleOpen) {
            var wait = (ARTICLE_MOTION && ARTICLE_MOTION.closeDuration ? ARTICLE_MOTION.closeDuration : 500) + 40;
            closeArticlePanel();
            setTimeout(function () { flyMapToPhotoWallPhoto(coords); }, wait);
        } else {
            // Cases A (nothing) and B (sidebar only) are handled by the
            // left-area centre calc (leftBound = 0 or sidebar right edge).
            flyMapToPhotoWallPhoto(coords);
        }
    }

    // ---------------------------------------------------------------
    // 10b. Social share bar (after content, before comments)
    // ---------------------------------------------------------------
    function htmlToText(s) {
        var d = document.createElement('div');
        d.innerHTML = String(s || '');
        return (d.textContent || d.innerText || '').trim();
    }

    function shareTargetUrls(url, title) {
        var u = encodeURIComponent(url);
        var t = encodeURIComponent(title);
        return {
            qq:       'https://connect.qq.com/widget/shareqq/index.html?url=' + u + '&title=' + t,
            qzone:    'https://sns.qzone.qq.com/cgi-bin/qzshare/cgi_qzshare_onekey?url=' + u + '&title=' + t,
            weibo:    'https://service.weibo.com/share/share.php?url=' + u + '&title=' + t,
            twitter:  'https://twitter.com/intent/tweet?url=' + u + '&text=' + t,
            facebook: 'https://www.facebook.com/sharer/sharer.php?u=' + u
        };
    }

    function makeShareQr(el, text) {
        if (typeof qrcode === 'undefined') { el.textContent = text; return; }
        try {
            if (qrcode.stringToBytesFuncs && qrcode.stringToBytesFuncs['UTF-8']) {
                qrcode.stringToBytes = qrcode.stringToBytesFuncs['UTF-8'];
            }
            var qr = qrcode(0, 'M');
            qr.addData(text);
            qr.make();
            // Render as inline SVG rather than a data-URL <img> (v1.3.7): a
            // data-URL image is blocked by strict img-src CSPs and mangled by
            // some image-optimization proxies, which showed as a broken QR.
            // Inline SVG is real DOM, immune to both, and stays crisp. Use the
            // scalable form so the container CSS controls its size.
            el.innerHTML = qr.createSvgTag({ cellSize: 4, margin: 8, scalable: true, alt: 'QR', title: 'QR' });
        } catch (e) {
            el.textContent = text;
        }
    }

    function copyShareLink(url, btn) {
        var done = function () {
            var prevHtml = btn.getAttribute('data-icon') || btn.innerHTML;
            btn.classList.add('is-copied');
            btn.innerHTML = '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
            setTimeout(function () { btn.classList.remove('is-copied'); btn.innerHTML = prevHtml; }, 1600);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(url).then(done, function () { fallbackCopy(url); done(); });
        } else {
            fallbackCopy(url);
            done();
        }
    }

    function fallbackCopy(text) {
        try {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        } catch (e) {}
    }

    // Monochrome brand glyphs (single <path>, filled with currentColor so they
    // follow the theme primary). Copy uses a stroked link icon to match the
    // theme's line-icon set.
    var SHARE_ICONS = {
        wechat:   '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M8.691 2.188C3.891 2.188 0 5.476 0 9.53c0 2.212 1.17 4.203 3.002 5.55a.59.59 0 0 1 .213.665l-.39 1.48c-.019.07-.048.141-.048.213 0 .163.13.295.29.295a.326.326 0 0 0 .167-.054l1.903-1.114a.864.864 0 0 1 .717-.098 10.16 10.16 0 0 0 2.837.403c.276 0 .543-.027.811-.05-.857-2.578.157-4.972 1.932-6.446 1.703-1.415 3.882-1.98 5.853-1.838-.576-3.583-4.196-6.348-8.596-6.348zM5.785 5.991c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178A1.17 1.17 0 0 1 4.623 7.17c0-.651.52-1.18 1.162-1.18zm5.813 0c.642 0 1.162.529 1.162 1.18a1.17 1.17 0 0 1-1.162 1.178 1.17 1.17 0 0 1-1.162-1.178c0-.651.52-1.18 1.162-1.18zm5.34 2.867c-1.797-.052-3.746.512-5.28 1.786-1.72 1.428-2.687 3.72-1.78 6.22.942 2.453 3.666 4.229 6.884 4.229.826 0 1.622-.12 2.361-.336a.722.722 0 0 1 .598.082l1.584.926a.272.272 0 0 0 .14.047c.134 0 .24-.111.24-.247 0-.06-.023-.12-.038-.177l-.327-1.233a.582.582 0 0 1-.023-.156.49.49 0 0 1 .201-.398C23.024 18.48 24 16.82 24 14.98c0-3.21-2.931-5.837-6.656-6.088V8.89c-.135-.01-.27-.027-.407-.03zm-2.53 3.274c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.97-.982zm4.844 0c.535 0 .969.44.969.982a.976.976 0 0 1-.969.983.976.976 0 0 1-.969-.983c0-.542.434-.982.969-.982z"/></svg>',
        qq:       '<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" aria-hidden="true"><path d="M21.395 15.035a40 40 0 0 0-.803-2.264l-1.079-2.695c.001-.032.014-.562.014-.836C19.526 4.632 17.351 0 12 0S4.474 4.632 4.474 9.241c0 .274.013.804.014.836l-1.08 2.695a39 39 0 0 0-.802 2.264c-1.021 3.283-.69 4.643-.438 4.673.54.065 2.103-2.472 2.103-2.472 0 1.469.756 3.387 2.394 4.771-.612.188-1.363.479-1.845.835-.434.32-.379.646-.301.778.343.578 5.883.369 7.482.189 1.6.18 7.14.389 7.483-.189.078-.132.132-.458-.301-.778-.483-.356-1.233-.646-1.846-.836 1.637-1.384 2.393-3.302 2.393-4.771 0 0 1.563 2.537 2.103 2.472.251-.03.581-1.39-.438-4.673"/></svg>',
        qzone:    '<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" aria-hidden="true"><path d="M23.9868 9.2012c-.032-.099-.127-.223-.334-.258-.207-.036-7.352-1.4063-7.352-1.4063s-.105-.022-.198-.07c-.092-.047-.127-.167-.127-.167S12.4472.954 12.3491.7679c-.099-.187-.245-.238-.349-.238-.104 0-.251.051-.349.238C11.5531.954 8.0245 7.3 8.0245 7.3s-.035.12-.128.167c-.092.047-.197.07-.197.07S.5546 8.9071.3466 8.9421c-.208.036-.302.16-.333.258a.477.477 0 00.125.4491L5.5013 15.14s.072.08.119.172c.016.104.005.21.005.21s-1.1891 7.243-1.2201 7.451c-.031.208.075.369.159.4301.083.062.233.106.421.013.189-.093 6.813-3.2614 6.813-3.2614s.098-.044.201-.061c.103-.017.201.061.201.061s6.624 3.1684 6.813 3.2614c.188.094.338.049.421-.013a.463.463 0 00.159-.43c-.021-.14-.93-5.6778-.93-5.6778.876-.5401 1.4251-1.0392 1.8492-1.7473-2.5944.9692-6.0069 1.7173-9.4163 1.8663-.9152.041-2.4104.097-3.4735-.015-.6781-.071-1.1702-.144-1.2432-.438-.053-.2151.054-.4601.5451-.8312a2640.8625 2640.8625 0 012.8614-2.1553c1.2852-.9681 3.5595-2.4703 3.5595-2.7314 0-.285-2.1443-.781-4.0376-.781-1.9452 0-2.2753.132-2.8114.168-.488.034-.769.005-.804-.138-.06-.2481.183-.3891.588-.5682.7091-.314 1.8603-.594 1.9843-.626.194-.052 3.0824-.8051 5.6188-.5351 1.3181.14 3.2444.668 3.2444 1.2762 0 .342-1.7212 1.4942-3.2254 2.5973-1.1492.8431-2.2173 1.5612-2.2173 1.6883 0 .342 3.5334 1.2411 6.6899 1.01l.003-.022c.048-.092.119-.172.119-.172l5.3627-5.4907a.477.477 0 00.127-.449z"/></svg>',
        weibo:    '<svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor" aria-hidden="true"><path d="M10.098 20.323c-3.977.391-7.414-1.406-7.672-4.02-.259-2.609 2.759-5.047 6.74-5.441 3.979-.394 7.413 1.404 7.671 4.018.259 2.6-2.759 5.049-6.737 5.439l-.002.004zM9.05 17.219c-.384.616-1.208.884-1.829.602-.612-.279-.793-.991-.406-1.593.379-.595 1.176-.861 1.793-.601.622.263.82.972.442 1.592zm1.27-1.627c-.141.237-.449.353-.689.253-.236-.09-.313-.361-.177-.586.138-.227.436-.346.672-.24.239.09.315.36.18.601l.014-.028zm.176-2.719c-1.893-.493-4.033.45-4.857 2.118-.836 1.704-.026 3.591 1.886 4.21 1.983.64 4.318-.341 5.132-2.179.8-1.793-.201-3.642-2.161-4.149zm7.563-1.224c-.346-.105-.57-.18-.405-.615.375-.977.42-1.804 0-2.404-.781-1.112-2.915-1.053-5.364-.03 0 0-.766.331-.571-.271.376-1.217.315-2.224-.27-2.809-1.338-1.337-4.869.045-7.888 3.08C1.309 10.87 0 13.273 0 15.348c0 3.981 5.099 6.395 10.086 6.395 6.536 0 10.888-3.801 10.888-6.82 0-1.822-1.547-2.854-2.915-3.284v.01zm1.908-5.092c-.766-.856-1.908-1.187-2.96-.962-.436.09-.706.511-.616.932.09.42.511.691.932.602.511-.105 1.067.044 1.442.465.376.421.466.977.316 1.473-.136.406.089.856.51.992.405.119.857-.105.992-.512.33-1.021.12-2.178-.646-3.035l.03.045zm2.418-2.195c-1.576-1.757-3.905-2.419-6.054-1.968-.496.104-.812.587-.706 1.081.104.496.586.813 1.082.707 1.532-.331 3.185.15 4.296 1.383 1.112 1.246 1.429 2.943.947 4.416-.165.48.106 1.007.586 1.157.479.165.991-.104 1.157-.586.675-2.088.241-4.478-1.338-6.235l.03.045z"/></svg>',
        twitter:  '<svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M14.234 10.162 22.977 0h-2.072l-7.591 8.824L7.251 0H.258l9.168 13.343L.258 24H2.33l8.016-9.318L16.749 24h6.993zm-2.837 3.299-.929-1.329L3.076 1.56h3.182l5.965 8.532.929 1.329 7.754 11.09h-3.182z"/></svg>',
        facebook: '<svg viewBox="0 0 24 24" width="17" height="17" fill="currentColor" aria-hidden="true"><path d="M9.101 23.691v-7.98H6.627v-3.667h2.474v-1.58c0-4.085 1.848-5.978 5.858-5.978.401 0 .955.042 1.468.103a8.68 8.68 0 0 1 1.141.195v3.325a8.623 8.623 0 0 0-.653-.036 26.805 26.805 0 0 0-.733-.009c-.707 0-1.259.096-1.675.309a1.686 1.686 0 0 0-.679.622c-.258.42-.374.995-.374 1.752v1.297h3.919l-.386 2.103-.287 1.564h-3.246v8.245C19.396 23.238 24 18.179 24 12.044c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.628 3.874 10.35 9.101 11.647Z"/></svg>',
        copy:     '<svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>'
    };

    function renderShareBar(post) {
        var bar = dom.articleShare;
        if (!bar) return;
        var url = post.link || (window.location.origin + '/?p=' + post.id);
        var title = (post.title && post.title.rendered) ? htmlToText(post.title.rendered) : (APP.siteName || '');

        var order = ['wechat', 'qq', 'qzone', 'weibo', 'twitter', 'facebook', 'copy'];
        var titles = { wechat: '微信', qq: 'QQ', qzone: 'QQ 空间', weibo: '微博', twitter: 'Twitter / X', facebook: 'Facebook', copy: '复制链接' };

        var buttonsHtml = '';
        order.forEach(function (k) {
            var btn = '<button type="button" class="share-btn share-' + k + '" data-share="' + k + '" data-icon=\'' + SHARE_ICONS[k] + '\' title="' + escapeHtml(titles[k]) + '" aria-label="' + escapeHtml(titles[k]) + '">' + SHARE_ICONS[k] + '</button>';
            if (k === 'wechat') {
                // Wrap so the QR popover can float above the button on hover
                // without taking layout space.
                btn = '<span class="share-wechat-wrap">' + btn
                    + '<div class="share-qr" hidden><div class="share-qr-code"></div><p class="share-qr-hint">微信扫码打开</p></div>'
                    + '</span>';
            }
            buttonsHtml += btn;
        });

        bar.innerHTML = '<div class="share-bar">'
            + '<span class="share-bar-label">分享</span>'
            + '<div class="share-bar-buttons">' + buttonsHtml + '</div>'
            + '</div>';
        bar.hidden = false;

        var urls = shareTargetUrls(url, title);
        var wechatWrap = bar.querySelector('.share-wechat-wrap');
        var qrWrap = bar.querySelector('.share-qr');
        var qrCode = bar.querySelector('.share-qr-code');
        var qrBuilt = false;
        function ensureQr() { if (!qrBuilt) { makeShareQr(qrCode, url); qrBuilt = true; } }

        // WeChat: QR floats in on hover, out on leave. Click toggles it too so
        // touch devices (no hover) can still reach it.
        if (wechatWrap && qrWrap) {
            wechatWrap.addEventListener('mouseenter', function () { ensureQr(); qrWrap.hidden = false; });
            wechatWrap.addEventListener('mouseleave', function () { qrWrap.hidden = true; });
        }

        bar.querySelectorAll('.share-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.stopPropagation();
                var k = btn.getAttribute('data-share');
                if (k === 'wechat') {
                    ensureQr();
                    qrWrap.hidden = !qrWrap.hidden;
                    return;
                }
                if (k === 'copy') { copyShareLink(url, btn); return; }
                if (urls[k]) { window.open(urls[k], '_blank', 'width=620,height=520,noopener'); }
            });
        });
    }

    // ---------------------------------------------------------------
    // 10c. Photo ↔ Map linking (desktop)
    //
    // A marker's `id` is the image's attachment id, and WordPress tags body
    // images with `wp-image-<id>`. That shared id is the reliable join
    // between an article-content image and its geolocated marker (coords).
    // ---------------------------------------------------------------

    // Look up a geolocated marker by image attachment id. The same image can
    // appear in several posts, but every such marker shares the image's
    // coordinates, so the first match is authoritative for position.
    function photoGeoById(id) {
        if (id == null || !state.allPhotos || !state.allPhotos.features) return null;
        var key = String(id);
        var feats = state.allPhotos.features;
        for (var i = 0; i < feats.length; i++) {
            var f = feats[i];
            if (f && f.properties && String(f.properties.id) === key && f.geometry && f.geometry.coordinates) {
                return { coords: featureWgsCoords(f), postId: f.properties.postId };
            }
        }
        return null;
    }

    // Strip a WordPress size suffix (e.g. "-1024x768") so medium/full/scaled
    // variants of the same upload compare equal by file stem.
    function imageStem(url) {
        if (!url) return '';
        var base = url.split('/').pop().split('?')[0];
        return base.replace(/-\d+x\d+(?=\.\w+$)/, '');
    }

    // Resolve the marker (coords) behind a rendered content image: prefer the
    // wp-image-<id> class, fall back to matching the file stem against the
    // marker set. Returns null for non-geolocated images.
    function photoGeoForImage(img) {
        var m = /wp-image-(\d+)/.exec(img.className || '');
        if (m) {
            var byId = photoGeoById(m[1]);
            if (byId) return byId;
        }
        var src = img.currentSrc || img.getAttribute('src') || '';
        var stem = imageStem(src);
        if (!stem) return null;
        var feats = (state.allPhotos && state.allPhotos.features) || [];
        for (var i = 0; i < feats.length; i++) {
            var p = feats[i].properties || {};
            if ((p.fullImage && imageStem(p.fullImage) === stem) ||
                (p.thumbnail && imageStem(p.thumbnail) === stem)) {
                return { coords: featureWgsCoords(feats[i]), postId: p.postId };
            }
        }
        return null;
    }

    // Feature 2: make geolocated content images clickable (desktop only) so a
    // click flies the background map to that photo's location.
    function wireArticleImages() {
        var root = dom.articleContent;
        if (!root) return;
        var imgs = root.querySelectorAll('img');
        // v1.4.7 (item 8): defer in-article images — the browser only fetches each
        // one as it nears the viewport instead of loading the whole article's
        // media the instant it's opened. Applies on every device (runs before the
        // desktop-only geo wiring below).
        for (var k = 0; k < imgs.length; k++) {
            if (!imgs[k].hasAttribute('loading')) imgs[k].setAttribute('loading', 'lazy');
            if (!imgs[k].hasAttribute('decoding')) imgs[k].setAttribute('decoding', 'async');
        }
        if (state.isMobile) return;
        for (var i = 0; i < imgs.length; i++) {
            (function (img) {
                var geo = photoGeoForImage(img);
                if (!geo) return; // non-geo images stay inert
                img.classList.add('article-geo-img');
                img.addEventListener('click', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    closeAllSidePanels(); // don't let 友链/留言 obscure the fly-to
                    flyMapToPhoto(geo.coords);
                    dismissArticlePhotoTip();
                });
            })(imgs[i]);
        }
    }

    // Centre of the visible map area to the right of the (open) article panel.
    // Read the panel's live rect so it adapts to width / window size.
    function rightMapAreaCenter() {
        var W = window.innerWidth, H = window.innerHeight;
        var panelRight = W * 0.5;
        if (dom.articlePanel) {
            var r = dom.articlePanel.getBoundingClientRect();
            if (r.width) panelRight = r.right;
        }
        return { x: (panelRight + W) / 2, y: H / 2 };
    }

    // ease-in-out sine — the "--ease-in-out-sine" token, as a JS easing fn for
    // MapLibre's animation options (accel then decel, no overshoot).
    function easeInOutSine(t) {
        return -(Math.cos(Math.PI * t) - 1) / 2;
    }

    // MapLibre zoom that renders `metersPerCm` at the 96dpi CSS reference
    // (1cm = 96/2.54 CSS px), matching the on-screen ScaleControl. Web Mercator
    // scale is latitude-dependent, so the target zoom is computed at `lat`.
    function zoomForScale(metersPerCm, lat) {
        var CSS_PX_PER_CM = 96 / 2.54; // ≈ 37.795
        var metersPerPixel = metersPerCm / CSS_PX_PER_CM;
        var latRad = lat * Math.PI / 180;
        return Math.log2(156543.03392 * Math.cos(latRad) / metersPerPixel);
    }

    // Feature 2 motion: pan the map (at current zoom) so the point rests at the
    // right-map-area centre, brief beat, then zoom about that pixel to 5km/1cm.
    // Two eased stages read like a hand dragging, then zooming in.
    // wgs: photo location in WGS-84. The map flies to the datum-adjusted
    // position (GCJ under Amap/Tencent) so it lands on the right spot, while the
    // pulse-dot popup still reports the true WGS-84 coordinate.
    function flyMapToPhoto(wgs) {
        if (!state.map || !wgs || state.isMobile) return;
        // v1.4.4 (item 5): 触发地图位移时，同时收起地图上已打开的图片展开面板。
        closeAllPhotoPanels();
        var coords = toMapCoords(wgs[0], wgs[1]);
        var lngLat = new maplibregl.LngLat(coords[0], coords[1]);
        var target = rightMapAreaCenter();
        var offset = [target.x - window.innerWidth / 2, target.y - window.innerHeight / 2];

        var targetZoom = zoomForScale(5000, coords[1]);
        targetZoom = Math.max(CONFIG.minZoom, Math.min(CONFIG.maxZoom, targetZoom));

        var flyId = ++state.mapFlyId;
        removePulseDot(); // a fresh fly supersedes any previous dot

        // Phase 1 — pan at the current zoom until the point hits the target pixel.
        state.map.easeTo({
            center: lngLat,
            offset: offset,
            duration: 1200,
            easing: easeInOutSine
        });

        // Phase 2 — after a short beat, zoom about that same pixel (`around`
        // pins the point in place while the scale changes).
        state.map.once('moveend', function () {
            if (flyId !== state.mapFlyId) return; // superseded by a newer click
            setTimeout(function () {
                if (flyId !== state.mapFlyId) return;
                state.map.easeTo({
                    zoom: targetZoom,
                    around: lngLat,
                    duration: 1600,
                    easing: easeInOutSine
                });
                // v1.4.4 (item 4): mark the exact photo location once the zoom
                // settles, in BOTH region and normal mode, and pop the location
                // popup below it (showPulseDot drives both).
                state.map.once('moveend', function () {
                    if (flyId !== state.mapFlyId) return;
                    showPulseDot(coords, wgs);
                });
            }, 100);
        });
    }

    // Locate a content image by attachment id (wp-image-<id>) or, failing that,
    // by file stem — the node the article should scroll to.
    function findContentImage(imgId, imgUrl) {
        var root = dom.articleContent;
        if (!root) return null;
        if (imgId != null) {
            var byClass = root.querySelector('img.wp-image-' + imgId);
            if (byClass) return byClass;
        }
        var stem = imageStem(imgUrl);
        if (stem) {
            var imgs = root.querySelectorAll('img');
            for (var i = 0; i < imgs.length; i++) {
                var src = imgs[i].currentSrc || imgs[i].getAttribute('src') || '';
                if (imageStem(src) === stem) return imgs[i];
            }
        }
        return null;
    }

    // Eased scrollTop animation on an element (ease-in-out quad).
    function animateScroll(el, from, to, duration) {
        if (prefersReducedMotion() || Math.abs(to - from) < 2) { el.scrollTop = to; return; }
        var start = null;
        function ease(t) { return t < 0.5 ? 2 * t * t : 1 - Math.pow(-2 * t + 2, 2) / 2; }
        function step(ts) {
            if (start === null) start = ts;
            var p = Math.min(1, (ts - start) / duration);
            el.scrollTop = from + (to - from) * ease(p);
            if (p < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    }

    // Feature 1 motion: glide the article panel so the target image rests ~18%
    // below the panel's top edge. Silent no-op (rest at top) if not found.
    function scrollArticleToImage(imgId, imgUrl) {
        var panel = dom.articlePanel;
        var img = findContentImage(imgId, imgUrl);
        if (!panel || !img) return;
        var panelRect = panel.getBoundingClientRect();
        var imgRect = img.getBoundingClientRect();
        var current = panel.scrollTop;
        var target = current + (imgRect.top - panelRect.top) - panelRect.height * 0.18;
        var max = panel.scrollHeight - panel.clientHeight;
        target = Math.max(0, Math.min(target, max));
        animateScroll(panel, current, target, 650);
    }

    // ---------------------------------------------------------------
    // 10a-bis. Article panel scroll controls (v1.3.6)
    //
    // The panel is the scroll container AND carries a transform + backdrop-filter
    // while active, which makes position:fixed descendants scroll WITH the
    // content (the v1.3.5 bug). Fix: the controls live in a body-mounted overlay
    // that is NOT inside the panel, so it never scrolls; JS mirrors the overlay's
    // box onto the panel's on-screen rect (getBoundingClientRect) on
    // open/scroll/resize. The buttons are position:absolute within that
    // non-transformed, non-scrolling overlay, so they stay pinned to the frame.
    //   • back-to-top — top-centre, appears once scrolled down
    //   • bottom trio — to-content-end / reading-progress% / jump-to-comment,
    //     centred at the panel bottom, visible only while the article text still
    //     extends below the panel bottom.
    // ---------------------------------------------------------------
    var SP_NAV_SCROLL_MS = 640; // 中等偏快
    var articlePhotoTipTimer = null;
    var articlePhotoTipScrollTop = 0;
    function resetArticlePhotoTip() {
        clearTimeout(articlePhotoTipTimer);
        articlePhotoTipTimer = null;
        if (dom.articlePhotoTip) dom.articlePhotoTip.remove();
        dom.articlePhotoTip = null;
    }

    function dismissArticlePhotoTip() {
        if (!dom.articlePhotoTip || articlePhotoTipTimer !== null) return;
        articlePhotoTipTimer = setTimeout(function () {
            if (dom.articlePhotoTip) dom.articlePhotoTip.classList.remove('is-visible');
        }, 1500);
    }

    function showArticlePhotoTip() {
        resetArticlePhotoTip();
        if (state.isMobile || !dom.articleNavOverlay) return;
        var tip = document.createElement('div');
        tip.className = 'article-photo-tip';
        var label = document.createElement('span');
        label.textContent = t('点击一张照片，定位其位置');
        tip.appendChild(label);
        tip.insertAdjacentHTML('beforeend', '<svg viewBox="0 0 100 52" width="100" height="52" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="36" y="13" width="33" height="26" rx="4"/><circle cx="59" cy="21" r="2.5"/><path d="m39 35 9-10 8 8 4-4 6 6"/><circle class="photo-tip-click" cx="43" cy="23" r="9"/><g class="photo-tip-pin"><path d="M88 22c0 6-8 12-8 12s-8-6-8-12a8 8 0 1 1 16 0Z"/><circle cx="80" cy="22" r="2.5"/></g><path class="photo-tip-cursor" d="m43 23 3 18 4-6 7-1Z" fill="currentColor" stroke="#555" stroke-width="1"/></svg>');
        dom.articleNavOverlay.appendChild(tip);
        dom.articlePhotoTip = tip;
        articlePhotoTipScrollTop = dom.articlePanel.scrollTop;
        requestAnimationFrame(function () {
            requestAnimationFrame(function () {
                if (dom.articlePhotoTip === tip) tip.classList.add('is-visible');
            });
        });
    }

    function buildArticleNav() {
        if (dom.articleNavBuilt) return;
        var panel = dom.articlePanel;
        if (!panel) return;

        var upIcon = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>';
        var downIcon = '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
        var commentIcon = '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>';

        // Overlay wrapper: fixed to the viewport, geometry synced to the panel.
        // pointer-events:none so it never blocks panel scroll/clicks; only the
        // buttons re-enable pointer events.
        var overlay = document.createElement('div');
        overlay.className = 'article-nav-overlay';

        // Frosted gradient bands (v1.3.7) behind the nav buttons: content blurs
        // as it scrolls under them. The top band stays off at scrollTop=0 and
        // fades in on scroll; the bottom band is always present.
        var bandTop = document.createElement('div');
        bandTop.className = 'sp-frost-band sp-frost-band--top';
        bandTop.setAttribute('aria-hidden', 'true');
        var bandBottom = document.createElement('div');
        bandBottom.className = 'sp-frost-band sp-frost-band--bottom is-visible';
        bandBottom.setAttribute('aria-hidden', 'true');
        overlay.appendChild(bandTop);
        overlay.appendChild(bandBottom);
        dom.articleNavBandTop = bandTop;
        dom.articleNavBandBottom = bandBottom; // v1.4.2: 供到底淡出用

        var top = document.createElement('button');
        top.type = 'button';
        top.className = 'article-nav-btn article-nav-top';
        top.setAttribute('aria-label', '回到顶部');
        top.title = '回到顶部';
        top.innerHTML = upIcon;
        top.addEventListener('click', function (e) {
            e.stopPropagation();
            animateScroll(panel, panel.scrollTop, 0, SP_NAV_SCROLL_MS);
        });

        var bottom = document.createElement('div');
        bottom.className = 'article-nav-bottom';

        var toBottom = document.createElement('button');
        toBottom.type = 'button';
        toBottom.className = 'article-nav-btn article-nav-to-bottom';
        toBottom.setAttribute('aria-label', '到正文末尾');
        toBottom.title = '到正文末尾';
        toBottom.innerHTML = downIcon;
        toBottom.addEventListener('click', function (e) {
            e.stopPropagation();
            // v1.4.5 (item 6): jump to the reading-100% position (last line at
            // panel centre), not the true scroll bottom.
            animateScroll(panel, panel.scrollTop, articleReading100ScrollTop(), SP_NAV_SCROLL_MS);
        });

        var progress = document.createElement('div');
        progress.className = 'article-nav-btn article-nav-progress';
        progress.setAttribute('aria-hidden', 'true');
        progress.title = '阅读进度';
        progress.innerHTML = '<span class="article-nav-progress-num">0%</span>';

        var comment = document.createElement('button');
        comment.type = 'button';
        comment.className = 'article-nav-btn article-nav-comment';
        comment.setAttribute('aria-label', '跳到评论');
        comment.title = '跳到评论';
        comment.innerHTML = commentIcon;
        comment.addEventListener('click', function (e) {
            e.stopPropagation();
            var target = articleCommentsScrollTop();
            animateScroll(panel, panel.scrollTop, target, SP_NAV_SCROLL_MS);
            setTimeout(function () {
                var ta = dom.articleComments && dom.articleComments.querySelector('.comment-textarea');
                if (ta) { try { ta.focus({ preventScroll: true }); } catch (err) { ta.focus(); } }
            }, SP_NAV_SCROLL_MS + 40);
        });

        bottom.appendChild(toBottom);
        bottom.appendChild(progress);
        bottom.appendChild(comment);

        overlay.appendChild(top);
        overlay.appendChild(bottom);

        // v1.4.5 (item 7): relocate the article close button OUT of the scrolling
        // panel and INTO this fixed, panel-glued overlay. Two wins: it now lives
        // in the same layer as the frost bands (z-index above them, so the top
        // band no longer blurs over it), and it no longer rides the scroll layer,
        // so it stays put with zero JS re-pinning (retires pinScrollingPanelClose
        // for the article). The overlay's rect is kept glued to the panel every
        // frame by syncArticleNavGeom, so top/right stays visually top-right.
        if (dom.articleClose) {
            dom.articleClose.classList.add('article-nav-close');
            dom.articleClose.style.top = '';   // clear any prior inline pin
            overlay.appendChild(dom.articleClose);
        }

        document.body.appendChild(overlay);

        // v1.4.6 (item 10): the in-article TOC bar. Mounted on <body> (NOT the
        // overlay, which has overflow:hidden and would clip a bar sitting outside
        // the panel's right edge). Its geometry is synced to the panel rect in
        // syncArticleNavGeom; visibility is driven by .is-active (article open) +
        // .is-hidden (article has no headings). The expanded index region is a
        // separate top-layer element built lazily.
        var tocBar = document.createElement('button');
        tocBar.type = 'button';
        tocBar.className = 'article-toc-bar is-hidden';
        tocBar.setAttribute('aria-label', t('文章目录'));
        tocBar.title = t('文章目录');
        tocBar.innerHTML = '<span class="article-toc-bar-grip" aria-hidden="true"></span>';
        tocBar.addEventListener('click', function (e) { e.stopPropagation(); toggleTocRegion(); });
        document.body.appendChild(tocBar);
        dom.articleTocBar = tocBar;

        dom.articleNavOverlay = overlay;
        dom.articleNavTop = top;
        dom.articleNavBottom = bottom;
        dom.articleNavProgressNum = progress.querySelector('.article-nav-progress-num');

        var scheduled = false;
        panel.addEventListener('scroll', function () {
            if (panel.scrollTop > articlePhotoTipScrollTop) dismissArticlePhotoTip();
            articlePhotoTipScrollTop = panel.scrollTop;
            if (scheduled) return;
            scheduled = true;
            requestAnimationFrame(function () {
                scheduled = false;
                updateArticleNav();
                // v1.4.7 (item 2D): the index now STAYS OPEN while scrolling (both
                // manual scroll and the smooth jump from clicking an entry) — it
                // only closes on bar re-click / outside click / Esc. We just keep
                // the current-section highlight fresh here.
                updateTocActive();
            });
        });
        window.addEventListener('resize', function () {
            if (state.articleOpen) { syncArticleNavGeom(); ensureReadingTailSpace(); updateArticleNav(); }
        });

        dom.articleNavBuilt = true;
    }

    // Mirror the overlay box onto the panel's current on-screen rect so the
    // absolutely-positioned buttons resolve against the panel frame.
    function syncArticleNavGeom() {
        var panel = dom.articlePanel;
        var overlay = dom.articleNavOverlay;
        if (!panel || !overlay) return;
        var r = panel.getBoundingClientRect();
        overlay.style.left = r.left + 'px';
        overlay.style.top = r.top + 'px';
        overlay.style.width = r.width + 'px';
        overlay.style.height = r.height + 'px';
        // v1.4.7 (item 2): the TOC bar is no longer full panel height — it is a
        // content-sized pill top-anchored at the panel's top-right. layoutToc()
        // reads the live panel rect and (re)positions the bar + any open region.
        if (dom.articleTocBar && !state.isMobile) layoutToc(false);
    }

    function hideArticleNav() {
        resetArticlePhotoTip();
        if (dom.articleNavOverlay) dom.articleNavOverlay.classList.remove('is-active');
        if (dom.articleNavTop) dom.articleNavTop.classList.remove('is-visible');
        if (dom.articleNavBottom) dom.articleNavBottom.classList.remove('is-visible');
        if (dom.articleTocBar) dom.articleTocBar.classList.remove('is-active'); // v1.4.6 (item 10)
        closeTocRegion(true); // v1.4.6 (item 10): TOC folds away with the article
    }

    // Absolute scrollTop that brings the bottom of #article-content level with
    // the bottom of the panel viewport (i.e. the last line of text sits at the
    // panel bottom). Clamped to the scrollable range.
    function articleTextEndScrollTop() {
        var panel = dom.articlePanel;
        var content = dom.articleContent;
        if (!panel || !content) return 0;
        var panelRect = panel.getBoundingClientRect();
        var contentRect = content.getBoundingClientRect();
        var target = panel.scrollTop + (contentRect.bottom - panelRect.bottom);
        var max = panel.scrollHeight - panel.clientHeight;
        return Math.max(0, Math.min(target, max));
    }

    // v1.4.5 (item 6): scroll target for the down button — the reading-100%
    // position, i.e. the article body's last line resting at the panel's
    // vertical centre (the exact point where the progress readout hits 100%,
    // matching v1.4.4 item 2). Lands one scroll short of the true bottom so the
    // reader ends on "read complete" rather than on the comments/footer.
    function articleReading100ScrollTop() {
        var panel = dom.articlePanel;
        var content = dom.articleContent;
        if (!panel || !content) return 0;
        var panelRect = panel.getBoundingClientRect();
        var contentRect = content.getBoundingClientRect();
        var panelCenterY = panelRect.top + panelRect.height / 2;
        var target = panel.scrollTop + (contentRect.bottom - panelCenterY);
        var max = panel.scrollHeight - panel.clientHeight;
        return Math.max(0, Math.min(target, max));
    }

    function articleCommentsScrollTop() {
        var panel = dom.articlePanel;
        var el = dom.articleComments;
        if (!panel || !el) return panel ? panel.scrollHeight : 0;
        var panelRect = panel.getBoundingClientRect();
        var rect = el.getBoundingClientRect();
        var target = panel.scrollTop + (rect.top - panelRect.top) - 24;
        var max = panel.scrollHeight - panel.clientHeight;
        return Math.max(0, Math.min(target, max));
    }

    // Recompute button visibility + progress readout. Cheap; called on rAF from
    // the panel scroll handler and on open/resize.
    function updateArticleNav() {
        if (!dom.articleNavBuilt || !state.articleOpen) return;
        var panel = dom.articlePanel;
        var content = dom.articleContent;
        if (!panel || !content) return;
        syncArticleNavGeom();
        var panelRect = panel.getBoundingClientRect();
        var contentRect = content.getBoundingClientRect();
        var scrollTop = panel.scrollTop;

        // Back-to-top: once the reader has scrolled down a little.
        dom.articleNavTop.classList.toggle('is-visible', scrollTop > 120);

        // Top frosted band: off while the article is at the very top (no blur
        // over the first line), fades in as soon as scrolling begins.
        if (dom.articleNavBandTop) dom.articleNavBandTop.classList.toggle('is-visible', scrollTop > 6);

        // v1.4.4 (item 2): 100% 的判定位置从「正文末尾到达面板底部」改为「正文
        // 末尾到达面板垂直中点」。deltaToCenter 是正文底边还需上移多少才触及中点，
        // <=0 即已到/越过中点 → 100%。底部留白（ensureReadingTailSpace）保证再短的
        // 文章也能滚到让末尾抵达中点。
        var panelCenterY = panelRect.top + panelRect.height / 2;
        var deltaToCenter = contentRect.bottom - panelCenterY;

        // Bottom trio: visible only before 100%; disappears once the last line of
        // article text reaches the panel centre (v1.4.4 item 2).
        var trioVisible = deltaToCenter > 2;
        dom.articleNavBottom.classList.toggle('is-visible', trioVisible);

        // v1.4.2: 滚动到最底部时淡出底部毛玻璃带（下方已无正文需要虚化）。
        if (dom.articleNavBandBottom) {
            var atBottom = scrollTop + panel.clientHeight >= panel.scrollHeight - 2;
            dom.articleNavBandBottom.classList.toggle('is-at-bottom', atBottom);
        }

        // Reading progress: how far the reader is toward the text end resting at
        // the panel centre (0 → 100%).
        var textCenterScroll = scrollTop + deltaToCenter;
        var pct = textCenterScroll > 4 ? Math.round(Math.max(0, Math.min(1, scrollTop / textCenterScroll)) * 100) : 100;
        if (dom.articleNavProgressNum) dom.articleNavProgressNum.textContent = pct + '%';
    }

    // v1.4.4 (item 2): ensure there is always enough scroll room below the
    // article text so its last line can rise to the panel centre — i.e. progress
    // can always reach 100% and the bottom trio can always disappear. When the
    // natural content below #article-content (share + comments) is too short, a
    // transparent tail spacer at the very bottom of the scroll makes up the gap.
    function ensureReadingTailSpace() {
        var panel = dom.articlePanel;
        var content = dom.articleContent;
        if (!panel || !content) return;
        var spacer = dom.articleTailSpacer;
        if (!spacer || !spacer.isConnected) {
            spacer = document.createElement('div');
            spacer.className = 'article-reading-tail';
            spacer.setAttribute('aria-hidden', 'true');
            panel.appendChild(spacer); // last child of the scroll container
            dom.articleTailSpacer = spacer;
        }
        // Measure without the spacer's own contribution.
        spacer.style.height = '0px';
        void panel.offsetHeight;
        var panelRect = panel.getBoundingClientRect();
        var contentRect = content.getBoundingClientRect();
        var centerY = panelRect.top + panelRect.height / 2;
        var deltaToCenter = contentRect.bottom - centerY;      // at current scrollTop
        var needScrollTop = panel.scrollTop + deltaToCenter;   // scrollTop to hit centre
        var maxScroll = panel.scrollHeight - panel.clientHeight;
        var shortfall = needScrollTop - maxScroll;
        spacer.style.height = shortfall > 0 ? Math.ceil(shortfall) + 'px' : '0px';
    }

    // Called once an article's content is in place: ensure controls exist, start
    // at the top, and sync their state. Because the panel slides/scales in over
    // ~openDuration, keep the overlay glued to it for the length of the entrance.
    function setupArticleNav() {
        buildArticleNav();
        if (dom.articleNavOverlay) dom.articleNavOverlay.classList.add('is-active');
        if (dom.articleTocBar) dom.articleTocBar.classList.add('is-active'); // v1.4.6 (item 10)
        syncArticleNavGeom();
        ensureReadingTailSpace();
        // v1.4.4 (item 2): content height changes over time — comments load
        // async, images decode late — so keep the tail spacer + progress in sync
        // whenever #article-content / #article-comments resize. One observer,
        // rebuilt per open, torn down on close.
        setupReadingTailObserver();
        var until = (typeof ARTICLE_MOTION === 'object' && ARTICLE_MOTION.openDuration ? ARTICLE_MOTION.openDuration : 600) + 80;
        var t0 = null;
        requestAnimationFrame(function loop(t) {
            if (!state.articleOpen) return;
            if (t0 === null) t0 = t;
            syncArticleNavGeom();
            if (t - t0 < until) requestAnimationFrame(loop);
            else { ensureReadingTailSpace(); updateArticleNav(); }
        });
    }

    // ===============================================================
    // 10a3. In-article TOC (v1.4.6 item 10)
    //
    // A rounded vertical bar hugging the article panel's right edge (built into
    // the panel-glued nav overlay). Clicking it expands a top-layer index region
    // whose entries come strictly from the article's H1–H6. Top-level headings
    // are accordion groups; deeper levels are shown with multi-level indent.
    // Clicking any entry scrolls the panel to that heading and collapses the
    // region; clicking anywhere else, pressing Esc, or scrolling also collapses
    // it. The bar + region match the panel's frosted glass, track the current
    // section, and are cursor-magnet targets. On mobile the bar is a floating
    // toggle and the region a side sheet (positioned via CSS).
    // ===============================================================
    function ensureTocRegion() {
        if (dom.articleTocRegion) return dom.articleTocRegion;
        var region = document.createElement('div');
        region.className = 'article-toc-region';
        region.setAttribute('role', 'navigation');
        region.setAttribute('aria-label', t('文章目录'));
        region.innerHTML = '<div class="article-toc-region-inner"><div class="article-toc-title"></div><div class="article-toc-list"></div></div>';
        // Clicks inside the region must not bubble to the outside-close handler.
        region.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
        document.body.appendChild(region);
        dom.articleTocRegion = region;
        dom.articleTocList = region.querySelector('.article-toc-list');
        region.querySelector('.article-toc-title').textContent = t('文章目录');
        return region;
    }

    // v1.4.7 (item 2) layout constants — the collapsed pill and the expanded
    // region share the same top-left anchor at the panel's top-right corner.
    var TOC_TOP_GAP = 0;        // v1.4.8 (item 4D)：与文章面板顶部齐平（不再下移）
    var TOC_SIDE_GAP = 8;       // gap from the panel's right edge
    var TOC_BAR_W = 28;         // collapsed pill width (matches CSS, v1.4.8 item 4A)
    var TOC_REGION_MIN_W = 170; // v1.4.8 (item 4D)：略收窄索引页面宽度
    var TOC_REGION_MAX_W = 248;

    // Parse headings out of the freshly-written article body, (re)build the index
    // region, and show/hide the bar (hidden when the article has no headings).
    function refreshArticleToc() {
        closeTocRegion(true);
        var bar = dom.articleTocBar;
        if (!bar) return;
        var content = dom.articleContent;
        var hs = content ? content.querySelectorAll('h1,h2,h3,h4,h5,h6') : [];
        var headings = [];
        for (var i = 0; i < hs.length; i++) {
            var el = hs[i];
            var text = (el.textContent || '').trim();
            if (!text) continue;
            if (!el.id) el.id = 'sp-toc-h-' + i;
            headings.push({ el: el, level: parseInt(el.tagName.charAt(1), 10), text: text, itemEl: null });
        }
        state.tocHeadings = headings;
        if (headings.length === 0) { bar.classList.add('is-hidden'); return; }
        bar.classList.remove('is-hidden');
        renderTocRegion(headings);
        // Measure the collapsed index height and size the bar to it (2C).
        layoutToc(true);
    }

    function renderTocRegion(headings) {
        ensureTocRegion();
        var list = dom.articleTocList;
        list.innerHTML = '';

        // Build a nested tree honouring heading levels, with parent pointers so the
        // scroll-driven accordion (v1.4.8 item 4C) can walk ancestor chains.
        var roots = [];
        var stack = [{ level: 0, children: roots, node: null }];
        headings.forEach(function (h) {
            var node = { h: h, children: [], parent: null, groupEl: null };
            while (stack.length > 1 && stack[stack.length - 1].level >= h.level) stack.pop();
            var top = stack[stack.length - 1];
            node.parent = top.node;
            top.children.push(node);
            h.node = node;
            stack.push({ level: h.level, children: node.children, node: node });
        });

        var minLevel = headings.reduce(function (m, h) { return Math.min(m, h.level); }, 6);
        state.tocTree = roots;
        state.tocMinLevel = minLevel;
        // v1.4.8 (item 4C): fully NESTED accordion — every heading with children is
        // its own collapsible group, so only ONE level expands at a time.
        roots.forEach(function (node) { list.appendChild(renderTocNode(node, minLevel, true)); });
    }

    function makeTocItem(h, minLevel, isTop) {
        var item = document.createElement('button');
        item.type = 'button';
        item.className = 'article-toc-item' + (isTop ? ' article-toc-item--top' : ' article-toc-item--child');
        item.style.paddingLeft = (14 + (h.level - minLevel) * 14) + 'px';
        item.textContent = h.text;
        // Jump to the heading; the index STAYS OPEN (v1.4.7 item 2D) so you can
        // keep navigating between sections.
        item.addEventListener('click', function (e) { e.stopPropagation(); gotoTocHeading(h); });
        h.itemEl = item;
        return item;
    }

    // Render one tree node as a collapsible group (recursively). Leaves render as a
    // group with just a row (no chevron / children container). All groups start
    // collapsed; the scroll-spy accordion opens the current branch one level deep.
    function renderTocNode(node, minLevel, isRoot) {
        var group = document.createElement('div');
        group.className = 'article-toc-group is-collapsed';
        node.groupEl = group;
        var hasKids = node.children.length > 0;

        var row = document.createElement('div');
        row.className = 'article-toc-row';
        row.appendChild(makeTocItem(node.h, minLevel, isRoot));

        if (hasKids) {
            var chev = document.createElement('button');
            chev.type = 'button';
            chev.className = 'article-toc-chevron';
            chev.setAttribute('aria-expanded', 'false');
            chev.innerHTML = '<svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
            chev.addEventListener('click', function (e) {
                e.stopPropagation();
                // Manual toggle still works; the next scroll re-asserts the accordion.
                var collapsed = group.classList.toggle('is-collapsed');
                chev.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                fitTocRegionHeight();
            });
            row.appendChild(chev);
        }
        group.appendChild(row);

        if (hasKids) {
            var kids = document.createElement('div');
            kids.className = 'article-toc-children';
            node.children.forEach(function (c) { kids.appendChild(renderTocNode(c, minLevel, false)); });
            group.appendChild(kids);
        }
        return group;
    }

    // Strict accordion driven by scroll position (v1.4.8 item 4C): expand only the
    // current heading's branch — its ancestors (to reveal it) plus itself (to show
    // its DIRECT children). Every other group collapses. Grandchildren stay hidden
    // until you scroll into a child. Returns true if any group's state changed.
    function applyTocAccordion(node) {
        if (!state.tocTree || !node) return false;
        var expand = [];
        for (var n = node; n; n = n.parent) expand.push(n);
        var changed = false;
        (function walk(nodes) {
            nodes.forEach(function (nd) {
                if (nd.children.length && nd.groupEl) {
                    var shouldCollapse = expand.indexOf(nd) === -1;
                    if (shouldCollapse !== nd.groupEl.classList.contains('is-collapsed')) {
                        nd.groupEl.classList.toggle('is-collapsed', shouldCollapse);
                        var chev = nd.groupEl.querySelector('.article-toc-row > .article-toc-chevron');
                        if (chev) chev.setAttribute('aria-expanded', shouldCollapse ? 'false' : 'true');
                        changed = true;
                    }
                    walk(nd.children);
                }
            });
        })(state.tocTree);
        return changed;
    }

    // Precise FINAL content height of the region regardless of any in-flight
    // child-reveal transitions. Sums (recursively) each node's row height plus its
    // expanded children — honouring the nested collapse state — so it never
    // double-counts, plus title + inner padding + borders.
    function tocNodeHeight(node) {
        if (!node.groupEl) return 0;
        var row = node.groupEl.querySelector('.article-toc-row');
        var h = row ? row.offsetHeight : 0;
        if (node.children.length && !node.groupEl.classList.contains('is-collapsed')) {
            node.children.forEach(function (c) { h += tocNodeHeight(c); });
        }
        return h;
    }
    function tocContentHeight() {
        var region = dom.articleTocRegion;
        if (!region || !state.tocTree) return 0;
        var h = 24 + 2; // inner vertical padding (14 + 10) + 1px top/bottom border
        var titleEl = region.querySelector('.article-toc-title');
        if (titleEl) h += titleEl.offsetHeight + 8; // + title padding-bottom
        state.tocTree.forEach(function (n) { h += tocNodeHeight(n); });
        return h;
    }

    // Size + position the bar (and, when open, the region) from the live panel
    // rect. remeasure=true recomputes the collapsed index height (on (re)build /
    // width change); scroll repositioning passes false and reuses the cached one.
    function layoutToc(remeasure) {
        var panel = dom.articlePanel, bar = dom.articleTocBar, region = dom.articleTocRegion;
        if (!panel || !bar || state.isMobile) return;
        var r = panel.getBoundingClientRect();
        var top = r.top + TOC_TOP_GAP;
        var left = r.right + TOC_SIDE_GAP;
        var avail = Math.max(80, r.bottom - top - TOC_TOP_GAP);
        var regionW = Math.max(TOC_REGION_MIN_W, Math.min(TOC_REGION_MAX_W, window.innerWidth - left - 12));
        var cached = state.tocGeom ? state.tocGeom.collapsedH : avail;
        state.tocGeom = { left: left, top: top, avail: avail, regionW: regionW, barW: TOC_BAR_W, collapsedH: cached };

        if (remeasure && region) {
            region.style.width = regionW + 'px'; // measure row wrapping at the open width
            state.tocGeom.collapsedH = Math.min(tocContentHeight(), avail);
        }
        var collapsedH = Math.min(state.tocGeom.collapsedH || 120, avail);
        bar.style.left = left + 'px';
        bar.style.top = top + 'px';
        bar.style.height = collapsedH + 'px';

        if (state.tocOpen && region) {
            region.style.left = left + 'px';
            region.style.top = top + 'px';
            region.style.width = regionW + 'px';
            fitTocRegionHeight();
        }
    }

    // Animate the open region's height to fit its current entries, capped at the
    // panel height (then the list scrolls internally). Leaves width alone so the
    // rightward extend animation is never interrupted.
    function fitTocRegionHeight() {
        var region = dom.articleTocRegion, geom = state.tocGeom;
        if (!region || !geom || state.isMobile || !state.tocOpen) return;
        region.style.height = Math.min(tocContentHeight(), geom.avail) + 'px';
    }

    function gotoTocHeading(h) {
        var panel = dom.articlePanel;
        if (!panel || !h || !h.el) return;
        var panelRect = panel.getBoundingClientRect();
        var rect = h.el.getBoundingClientRect();
        var target = panel.scrollTop + (rect.top - panelRect.top) - 16;
        var max = panel.scrollHeight - panel.clientHeight;
        target = Math.max(0, Math.min(target, max));
        // v1.4.7 (item 2D): do NOT close — keep the index open while jumping.
        animateScroll(panel, panel.scrollTop, target, SP_NAV_SCROLL_MS);
        setTocActive(h);
        // v1.4.8 (item 4C): reflect the clicked section in the accordion immediately.
        if (state.tocOpen && !state.isMobile && h.node) {
            if (applyTocAccordion(h.node)) fitTocRegionHeight();
        }
    }

    function toggleTocRegion() { if (state.tocOpen) closeTocRegion(); else openTocRegion(); }

    function openTocRegion() {
        if (!state.tocHeadings || !state.tocHeadings.length) return;
        ensureTocRegion();
        var region = dom.articleTocRegion, bar = dom.articleTocBar;

        if (state.isMobile) {
            // Mobile: CSS pins the side sheet; just clear inline geometry + show.
            region.style.left = region.style.top = region.style.width = region.style.height = '';
            state.tocOpen = true;
            region.classList.add('is-open');
            updateTocActive();
            bindTocDismiss();
            return;
        }

        layoutToc(false);
        var geom = state.tocGeom;
        // Start collapsed at the bar (no transition), then animate outward — the
        // region visually grows RIGHTWARD out of the bar (2E) and downward (2B).
        region.style.transition = 'none';
        region.style.left = geom.left + 'px';
        region.style.top = geom.top + 'px';
        region.style.width = geom.barW + 'px';
        region.style.height = geom.collapsedH + 'px';
        void region.offsetHeight; // commit the collapsed start
        region.style.transition = '';
        state.tocOpen = true;
        region.classList.add('is-open');
        if (bar) bar.classList.add('is-open'); // fade the bar out; region replaces it
        region.style.width = geom.regionW + 'px';
        fitTocRegionHeight();
        updateTocActive();
        bindTocDismiss();
    }

    // Defer binding the dismiss listeners so the opening click doesn't instantly
    // close it (the click's own pointer events would otherwise reach the handler).
    function bindTocDismiss() {
        setTimeout(function () {
            document.addEventListener('pointerdown', tocOutsideHandler, true);
            document.addEventListener('keydown', tocEscHandler);
        }, 0);
    }

    function closeTocRegion(immediate) {
        state.tocOpen = false;
        document.removeEventListener('pointerdown', tocOutsideHandler, true);
        document.removeEventListener('keydown', tocEscHandler);
        var region = dom.articleTocRegion, bar = dom.articleTocBar, geom = state.tocGeom;
        if (!region) return;
        if (!state.isMobile && geom && !immediate) {
            // Reverse morph: narrow the region back into the bar (2E).
            region.style.width = geom.barW + 'px';
            region.style.height = geom.collapsedH + 'px';
        }
        region.classList.remove('is-open');
        // v1.4.7 (item 2A/2D): the bar fades back in. Crucially we do NOT remove the
        // bar's `is-active` here — that class is owned by the article-nav show/hide
        // and is what keeps the bar visible + clickable. Removing it on close was
        // why the index vanished and couldn't be re-opened.
        if (bar) bar.classList.remove('is-open');
    }

    function tocOutsideHandler(e) {
        if (dom.articleTocRegion && dom.articleTocRegion.contains(e.target)) return;
        if (dom.articleTocBar && dom.articleTocBar.contains(e.target)) return;
        closeTocRegion();
    }
    function tocEscHandler(e) { if (e.key === 'Escape') closeTocRegion(); }

    function setTocActive(h) {
        var hs = state.tocHeadings;
        if (!hs) return;
        hs.forEach(function (x) { if (x.itemEl) x.itemEl.classList.toggle('is-current', x === h); });
    }

    function updateTocActive() {
        var panel = dom.articlePanel, hs = state.tocHeadings;
        if (!panel || !hs || !hs.length) return;
        var panelTop = panel.getBoundingClientRect().top;
        var current = hs[0];
        for (var i = 0; i < hs.length; i++) {
            if (hs[i].el.getBoundingClientRect().top - panelTop <= 80) current = hs[i];
            else break;
        }
        setTocActive(current);
        // v1.4.8 (item 4C): while open, the accordion follows the scroll position —
        // expand the current section's direct children, collapse other branches.
        if (state.tocOpen && !state.isMobile && current.node) {
            if (applyTocAccordion(current.node)) fitTocRegionHeight();
        }
    }

    // Observe the article body + comments for height changes and re-fit the tail
    // spacer (debounced via rAF). Rebuilt each open; disconnected on close.
    function setupReadingTailObserver() {
        if (typeof ResizeObserver === 'undefined') return;
        if (state.readingTailRO) { try { state.readingTailRO.disconnect(); } catch (e) {} }
        var scheduled = false;
        var ro = new ResizeObserver(function () {
            if (scheduled) return;
            scheduled = true;
            requestAnimationFrame(function () {
                scheduled = false;
                if (!state.articleOpen) return;
                ensureReadingTailSpace();
                updateArticleNav();
            });
        });
        if (dom.articleContent) ro.observe(dom.articleContent);
        if (dom.articleComments) ro.observe(dom.articleComments);
        state.readingTailRO = ro;
    }

    // ---------------------------------------------------------------
    // 10b. Article Comments (custom sphotography/v1/comments REST API)
    //
    // Single-level threading, captcha, 悄悄话 (private) threads, reply e-mail
    // notifications, safe Markdown, Unicode emoji, likes, pinning, commenter
    // editing with edit history, UA display and generated text avatars — all
    // driven by the per-site config in APP.comments.
    // ---------------------------------------------------------------
    var CCFG = (APP.comments && typeof APP.comments === 'object') ? APP.comments : {};
    var COMMENTS_BASE = 'sphotography/v1/comments';
    // Per-post render state so pagination / reloads stay scoped to one article.
    // sort/order (v1.3.7): 'time' (order asc|desc) or 'likes'. Pinned always lead.
    var cState = { postId: 0, page: 1, hasMore: false, loading: false, sort: 'time', order: 'asc' };

    var EMOJI_LIST = ['😀','😁','😂','🤣','😊','😍','😘','😎','🤩','🥳','😅','😜','🤔','😴','😇','🙃','😉','😌','😢','😭','😤','😱','😳','🥺','😔','🙄','😏','😬','🤯','🤗','👍','👎','👌','🙏','👏','🙌','💪','🤝','✌️','🤞','❤️','🧡','💛','💚','💙','💜','🖤','💔','✨','🔥','🎉','🎂','🌟','⭐','☀️','🌈','🌸','🍀','🐶','🐱','🍎','🍕','☕','🎵','📷','💯'];

    function ccEndpoint(path) {
        return CONFIG.restBase + '/' + COMMENTS_BASE + (path || '');
    }

    // Avatar block: preferred Gravatar with a text-avatar fallback (colour from
    // the email hash) revealed when the Gravatar 404s. When text avatars are
    // disabled the fallback is a neutral initial placeholder.
    function commentAvatar(c) {
        var name = (c.author || '匿名');
        var initial = escapeHtml(name.trim().charAt(0).toUpperCase() || '?');
        var textAvatar = CCFG.textAvatar !== false;
        var hue = hashHue(c.hash || name);
        var baseStyle = textAvatar ? (' style="background:hsl(' + hue + ',60%,52%)"') : '';
        var baseCls = 'comment-avatar comment-avatar-fallback' + (textAvatar ? ' comment-text-avatar' : ' comment-avatar--placeholder');
        var html = '<span class="comment-avatar-wrap">';
        html += '<span class="' + baseCls + '"' + baseStyle + '>' + initial + '</span>';
        if (c.gravatar) {
            html += '<img class="comment-avatar comment-gravatar" src="' + escapeHtml(c.gravatar) + '" alt="" loading="lazy" onerror="this.remove()">';
        }
        html += '</span>';
        return html;
    }

    // Deterministic hue (0–359) from a string hash — matches the server's
    // "colour by email hash" rule closely enough for a stable per-user colour.
    function hashHue(str) {
        str = String(str || '');
        var h = 0;
        for (var i = 0; i < str.length; i++) { h = (h * 31 + str.charCodeAt(i)) >>> 0; }
        return h % 360;
    }

    function commentMetaLine(c, isChild) {
        var bits = [];
        if (c.author_is_admin) bits.push('<span class="comment-badge comment-badge-admin">博主</span>');
        if (c.pinned) bits.push('<span class="comment-badge comment-badge-pin">置顶</span>');
        if (c.is_private) bits.push('<span class="comment-badge comment-badge-private">悄悄话</span>');
        var reply = '';
        if (isChild && CCFG.showReplyTo !== false && c.reply_to && c.reply_to.name) {
            reply = '<span class="comment-reply-to">回复 @' + escapeHtml(c.reply_to.name) + '</span>';
        }
        var date = c.date ? formatDate(String(c.date).split('T')[0]) : '';
        var ua = c.ua ? '<span class="comment-ua">' + escapeHtml(c.ua) + '</span>' : '';
        var loc = (CCFG.ipLocation && c.ip_region) ? '<span class="comment-ip-loc" title="IP 属地">' + escapeHtml(c.ip_region) + '</span>' : '';
        var edited = c.edited ? '<button type="button" class="comment-edited" data-cc-history="' + c.id + '">已编辑</button>' : '';
        return ''
            + '<div class="comment-head">'
            +   '<span class="comment-author">' + escapeHtml(c.author || t('匿名')) + '</span>'
            +   bits.join('')
            +   reply
            + '</div>'
            + '<div class="comment-sub">'
            +   (date ? '<span class="comment-date">' + escapeHtml(date) + '</span>' : '')
            +   loc + ua + edited
            + '</div>';
    }

    function commentActions(c) {
        var acts = [];
        if (CCFG.likeEnabled !== false) {
            acts.push('<button type="button" class="comment-act comment-like' + (c.liked ? ' is-liked' : '') + '" data-cc-like="' + c.id + '">♥ <span class="comment-like-count">' + (c.likes || 0) + '</span></button>');
        }
        acts.push('<button type="button" class="comment-act" data-cc-reply="' + c.id + '" data-cc-name="' + escapeHtml(c.author || '') + '">' + t('回复') + '</button>');
        if (c.can_edit) acts.push('<button type="button" class="comment-act" data-cc-edit="' + c.id + '">' + t('编辑') + '</button>');
        if (c.can_pin) acts.push('<button type="button" class="comment-act" data-cc-pin="' + c.id + '">' + (c.pinned ? t('取消置顶') : t('置顶')) + '</button>');
        return '<div class="comment-actions">' + acts.join('') + '</div>';
    }

    function buildCommentNode(c, isChild) {
        // Cache per-comment raw text (for edit prefill) and edit history.
        ccEditRaw[c.id] = c.content_raw || '';
        ccHistory[c.id] = c.history || [];
        var children = '';
        if (!isChild && c.children && c.children.length) {
            children = '<ul class="comment-children">' + c.children.map(function (ch) { return buildCommentNode(ch, true); }).join('') + '</ul>';
        }
        return ''
            + '<li class="comment-item' + (isChild ? ' comment-item--child' : '') + (c.pinned ? ' is-pinned' : '') + '" id="comment-' + c.id + '" data-cc-id="' + c.id + '">'
            +   commentAvatar(c)
            +   '<div class="comment-body">'
            +     commentMetaLine(c, isChild)
            +     '<div class="comment-text" data-cc-text="' + c.id + '">' + (c.content || '') + '</div>'
            +     commentActions(c)
            +     '<div class="comment-reply-slot"></div>'
            +   '</div>'
            +   children
            + '</li>';
    }

    // Comment sort control (v1.3.7): a 时间 toggle (asc↔desc) + a 点赞 button.
    // Pinned comments are unaffected server-side.
    function commentSortHtml() {
        return ''
            + '<div class="comment-sort" role="group" aria-label="评论排序">'
            +   '<button type="button" class="comment-sort-btn" data-cc-sort="time"></button>'
            +   '<button type="button" class="comment-sort-btn" data-cc-sort="likes">' + t('点赞') + '</button>'
            + '</div>';
    }

    function updateCommentSortUI() {
        var wrap = dom.articleComments;
        if (!wrap) return;
        var timeBtn = wrap.querySelector('.comment-sort-btn[data-cc-sort="time"]');
        var likeBtn = wrap.querySelector('.comment-sort-btn[data-cc-sort="likes"]');
        if (timeBtn) {
            var arrow = cState.order === 'desc' ? '↓' : '↑';
            timeBtn.textContent = t('时间') + ' ' + arrow;
            timeBtn.classList.toggle('is-active', cState.sort === 'time');
        }
        if (likeBtn) likeBtn.classList.toggle('is-active', cState.sort === 'likes');
    }

    function renderComments(postId, commentStatus) {
        var wrap = dom.articleComments;
        if (!wrap) return;
        cState = { postId: postId, page: 1, hasMore: false, loading: false, sort: 'time', order: 'asc' };
        var isOpen = commentStatus !== 'closed';
        wrap.innerHTML = ''
            + '<div class="comments-section" data-cc-align="' + escapeHtml(CCFG.avatarAlign || 'top') + '">'
            +   '<div class="comments-head">'
            +     '<h4 class="comments-title"><span class="comments-count-label">' + t('评论') + '</span> <span class="comments-count">…</span></h4>'
            +     commentSortHtml()
            +   '</div>'
            +   '<ul class="comment-list" id="comment-list"></ul>'
            +   '<div class="comment-pager" id="comment-pager"></div>'
            +   (isOpen ? buildCommentFormHtml(0) : '<p class="comments-closed">' + escapeHtml(APP.commentsClosedText || '评论已关闭。') + '</p>')
            + '</div>';

        var listEl = wrap.querySelector('#comment-list');
        var countEl = wrap.querySelector('.comments-count');
        listEl.innerHTML = '<li class="comments-loading">' + t('加载中…') + '</li>';

        updateCommentSortUI();
        wireCommentSort(postId, listEl, countEl);
        wireCommentList(postId, listEl, countEl);
        if (isOpen) wireCommentForm(postId, wrap.querySelector('.comment-form'), listEl, countEl);

        loadCommentPage(postId, 1, true, listEl, countEl);
    }

    // Sort buttons: 时间 toggles asc↔desc; 点赞 switches to like-count order.
    // Changing sort reloads from page 1 (server does the ordering).
    function wireCommentSort(postId, listEl, countEl) {
        var sortWrap = dom.articleComments.querySelector('.comment-sort');
        if (!sortWrap) return;
        sortWrap.addEventListener('click', function (e) {
            var btn = e.target.closest('.comment-sort-btn');
            if (!btn) return;
            var sort = btn.getAttribute('data-cc-sort');
            if (sort === 'time') {
                if (cState.sort === 'time') {
                    cState.order = (cState.order === 'asc') ? 'desc' : 'asc';
                } else {
                    cState.sort = 'time';
                }
            } else if (sort === 'likes') {
                if (cState.sort === 'likes') return;
                cState.sort = 'likes';
            }
            updateCommentSortUI();
            listEl.innerHTML = '<li class="comments-loading">' + t('加载中…') + '</li>';
            loadCommentPage(postId, 1, true, listEl, countEl);
        });
    }

    // GET with credentials + nonce. Logged-in users MUST send the REST nonce or
    // WordPress rejects cookie-authenticated requests (rest_cookie_invalid_nonce),
    // so we can't reuse the plain fetchFromRest helper here.
    function ccGet(endpoint, params) {
        var qs = params ? '?' + new URLSearchParams(params).toString() : '';
        return fetch(CONFIG.restBase + '/' + endpoint + qs, {
            credentials: 'same-origin',
            headers: { 'X-WP-Nonce': APP.restNonce || '' }
        }).then(function (res) {
            if (!res.ok) return null;
            return res.json();
        }).catch(function () { return null; });
    }

    function loadCommentPage(postId, page, replace, listEl, countEl) {
        if (cState.loading) return;
        cState.loading = true;
        ccGet(COMMENTS_BASE, { post: postId, page: page, sort: cState.sort, order: cState.order }).then(function (data) {
            cState.loading = false;
            if (state.openedPostId !== postId || !data) return;
            var items = Array.isArray(data.items) ? data.items : [];
            cState.page = data.page || page;
            cState.hasMore = !!data.has_more;
            countEl.textContent = '(' + (data.total || 0) + ')';

            var loading = listEl.querySelector('.comments-loading');
            if (loading) loading.remove();
            if (replace) listEl.innerHTML = '';
            var emptyEl = listEl.querySelector('.comments-empty');
            if (emptyEl) emptyEl.remove();

            if (replace && items.length === 0) {
                listEl.innerHTML = '<li class="comments-empty">' + t('还没有评论，来抢沙发吧。') + '</li>';
            } else {
                listEl.insertAdjacentHTML('beforeend', items.map(function (c) { return buildCommentNode(c, false); }).join(''));
                applyFolding(listEl);
                i18nScan(listEl); // v1.4.3: 按需翻译评论正文（en/ja），名字/签名不发送
            }
            renderPager(postId, listEl, countEl);
        });
    }

    function renderPager(postId, listEl, countEl) {
        var pager = dom.articleComments.querySelector('#comment-pager');
        if (!pager) return;
        var paged = (CCFG.pagination === 'paged');
        pager.innerHTML = '';
        if (!cState.hasMore && cState.page <= 1) return;

        if (paged) {
            var html = '';
            if (cState.page > 1) html += '<button type="button" class="comment-page-btn" data-cc-page="' + (cState.page - 1) + '">上一页</button>';
            html += '<span class="comment-page-cur">第 ' + cState.page + ' 页</span>';
            if (cState.hasMore) html += '<button type="button" class="comment-page-btn" data-cc-page="' + (cState.page + 1) + '">下一页</button>';
            pager.innerHTML = html;
        } else if (cState.hasMore) {
            pager.innerHTML = '<button type="button" class="comment-page-btn comment-load-more" data-cc-page="' + (cState.page + 1) + '">加载更多评论</button>';
        }
    }

    function applyFolding(listEl) {
        if (CCFG.foldLong === false) return;
        var limit = CCFG.foldPx || 200;
        var texts = listEl.querySelectorAll('.comment-text');
        for (var i = 0; i < texts.length; i++) {
            var el = texts[i];
            if (el.dataset.ccFolded) continue;
            if (el.scrollHeight > limit + 40) {
                el.dataset.ccFolded = '1';
                el.classList.add('is-folded');
                el.style.maxHeight = limit + 'px';
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'comment-fold-toggle';
                btn.textContent = '展开阅读全文';
                btn.setAttribute('data-cc-fold', '1');
                el.parentNode.insertBefore(btn, el.nextSibling);
            }
        }
    }

    function buildCommentFormHtml(parentId, replyName) {
        var loggedIn = !!APP.loggedIn;
        var isReply = parentId > 0;
        var identityRow = loggedIn
            ? '<p class="comment-identity">以 <strong>' + escapeHtml(APP.currentUserName || '') + '</strong> 的身份' + (isReply ? '回复' : '评论') + '</p>'
            : ''
                + '<div class="comment-fields">'
                +   '<input type="text" class="comment-input comment-author" placeholder="昵称 *" autocomplete="name" required>'
                +   '<input type="email" class="comment-input comment-email" placeholder="邮箱（不公开）*" autocomplete="email" required>'
                + '</div>';

        var emojiBtn = (CCFG.emojiPanel !== false)
            ? '<button type="button" class="comment-emoji-btn" title="插入表情">😊</button>'
            : '';

        // Markdown toolbar + live preview (v1.3.7). Only when the site renders a
        // Markdown subset; buttons wrap the selection, preview renders client-side.
        var mdToolbar = '';
        if (CCFG.markdown) {
            mdToolbar = ''
                + '<div class="comment-md-toolbar" role="group" aria-label="Markdown 工具">'
                +   '<button type="button" class="comment-md-btn" data-md="bold" title="粗体"><b>B</b></button>'
                +   '<button type="button" class="comment-md-btn" data-md="italic" title="斜体"><i>I</i></button>'
                +   '<button type="button" class="comment-md-btn" data-md="strike" title="删除线"><s>S</s></button>'
                +   '<button type="button" class="comment-md-btn" data-md="code" title="行内代码">&lt;/&gt;</button>'
                +   '<button type="button" class="comment-md-btn" data-md="link" title="链接">🔗</button>'
                +   '<button type="button" class="comment-md-btn" data-md="quote" title="引用">❝</button>'
                +   '<button type="button" class="comment-md-btn" data-md="list" title="列表">≔</button>'
                +   '<button type="button" class="comment-md-preview-toggle" data-md="preview" title="预览">预览</button>'
                + '</div>';
        }

        var captchaRow = (CCFG.captcha && !loggedIn)
            ? '<div class="comment-captcha-row"><span class="comment-captcha-q">…</span><input type="text" class="comment-input comment-captcha-input" inputmode="numeric" placeholder="= ?" autocomplete="off"><button type="button" class="comment-captcha-refresh" title="换一题">↻</button></div>'
            : '';

        var options = '';
        if (!isReply && CCFG.allowPrivate) {
            options += '<label class="comment-opt"><input type="checkbox" class="comment-private"> 悄悄话（仅自己和博主可见）</label>';
        }
        if (CCFG.mailNotify) {
            options += '<label class="comment-opt"><input type="checkbox" class="comment-notify" checked> 启用邮件通知</label>';
        }

        return ''
            + '<form class="comment-form' + (isReply ? ' comment-form--reply' : '') + '" novalidate data-cc-parent="' + (parentId || 0) + '">'
            +   identityRow
            +   mdToolbar
            +   '<textarea class="comment-textarea" rows="3" placeholder="' + (isReply ? ('回复 @' + escapeHtml(replyName || '') + '…') : '写下你的评论…') + '" required></textarea>'
            +   (CCFG.markdown ? '<div class="comment-md-preview" hidden></div>' : '')
            +   (CCFG.emojiPanel !== false ? '<div class="comment-emoji-panel" hidden>' + EMOJI_LIST.map(function (e) { return '<button type="button" class="comment-emoji">' + e + '</button>'; }).join('') + '</div>' : '')
            +   captchaRow
            +   (options ? '<div class="comment-options">' + options + '</div>' : '')
            +   '<div class="comment-form-footer">'
            +     emojiBtn
            +     '<span class="comment-feedback"></span>'
            +     '<button type="submit" class="comment-submit">' + (isReply ? '回复' : '发表评论') + '</button>'
            +   '</div>'
            + '</form>';
    }

    // Fetch and inject a fresh captcha challenge into a form.
    function loadCaptcha(form) {
        var qEl = form.querySelector('.comment-captcha-q');
        var input = form.querySelector('.comment-captcha-input');
        if (!qEl || !input) return;
        qEl.textContent = '…';
        input.value = '';
        input.removeAttribute('data-cc-token');
        fetchFromRest(COMMENTS_BASE + '/captcha', null).then(function (data) {
            if (!data) { qEl.textContent = '验证码加载失败'; return; }
            qEl.textContent = data.question + ' =';
            input.setAttribute('data-cc-token', data.token);
        });
    }

    // Insert text at the caret of a textarea.
    function insertAtCaret(textarea, text) {
        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;
        var val = textarea.value;
        textarea.value = val.slice(0, start) + text + val.slice(end);
        var pos = start + text.length;
        textarea.selectionStart = textarea.selectionEnd = pos;
        textarea.focus();
        // A programmatic `.value` set fires no `input` event; dispatch one so any
        // listeners (markdown live-preview, char counters) see the change. (v1.4.2)
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    }

    // Wrap the current textarea selection with markdown markers (or insert a
    // placeholder when nothing is selected). For line-oriented markers (quote,
    // list) prefix each selected line instead.
    function applyMdMarker(textarea, kind) {
        var start = textarea.selectionStart || 0;
        var end = textarea.selectionEnd || 0;
        var val = textarea.value;
        var sel = val.slice(start, end);
        var before = val.slice(0, start);
        var after = val.slice(end);
        var out, caretStart, caretEnd;

        function wrap(mark, placeholder) {
            var body = sel || placeholder;
            out = before + mark + body + mark + after;
            caretStart = start + mark.length;
            caretEnd = caretStart + body.length;
        }
        function linePrefix(prefix, placeholder) {
            var body = sel || placeholder;
            var prefixed = body.split('\n').map(function (l) { return prefix + l; }).join('\n');
            out = before + prefixed + after;
            caretStart = start;
            caretEnd = start + prefixed.length;
        }

        if (kind === 'bold')        wrap('**', '粗体');
        else if (kind === 'italic') wrap('*', '斜体');
        else if (kind === 'strike') wrap('~~', '删除线');
        else if (kind === 'code')   wrap('`', '代码');
        else if (kind === 'quote')  linePrefix('> ', '引用');
        else if (kind === 'list')   linePrefix('- ', '列表项');
        else if (kind === 'link') {
            var body = sel || '链接文字';
            var tail = '](https://)';
            out = before + '[' + body + tail + after;
            caretStart = start + 1;
            caretEnd = caretStart + body.length;
        } else { return; }

        textarea.value = out;
        textarea.selectionStart = caretStart;
        textarea.selectionEnd = caretEnd;
        textarea.focus();
    }

    // Compact client-side renderer for the same Markdown subset the server
    // supports (bold/italic/strike, inline+fenced code, links, blockquote,
    // ordered/unordered lists). Escape first so no raw HTML survives — the
    // server re-sanitizes on submit; this is preview-only.
    function renderMarkdownSubset(text) {
        text = String(text || '').replace(/\r\n?/g, '\n');
        // Pull fenced code blocks and inline code out first so their contents
        // are not reparsed. Placeholders use a control-char delimiter that
        // never appears in user text.
        var SP = '\u0001';
        var codeBlocks = [];
        text = text.replace(/```[ \t]*\n?([\s\S]*?)```/g, function (m, code) {
            var key = SP + 'C' + codeBlocks.length + SP;
            codeBlocks.push('<pre><code>' + escapeHtml(code.replace(/\n+$/, '')) + '</code></pre>');
            return '\n' + key + '\n';
        });
        var inline = [];
        text = text.replace(/`([^`\n]+)`/g, function (m, code) {
            var key = SP + 'I' + inline.length + SP;
            inline.push('<code>' + escapeHtml(code) + '</code>');
            return key;
        });
        // Inline formatter for one raw line: escape first (no user HTML
        // survives; preview only, the server re-sanitizes on submit), then
        // re-introduce the whitelisted markup.
        function fmt(s) {
            s = escapeHtml(s);
            s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|mailto:[^\s)]+)\)/g, function (m, label, url) {
                return '<a href="' + url + '" rel="nofollow noopener" target="_blank">' + label + '</a>';
            });
            s = s.replace(/\*\*([^*\n]+)\*\*/g, '<strong>$1</strong>');
            s = s.replace(/(^|[^*])\*([^*\n]+)\*/g, '$1<em>$2</em>');
            s = s.replace(/~~([^~\n]+)~~/g, '<del>$1</del>');
            return s;
        }
        // Block assembly: fenced code, blockquotes, lists, paragraphs.
        var lines = text.split('\n');
        var html = '';
        var i = 0;
        var phRaw = new RegExp('^' + SP + 'C(\\d+)' + SP + '$');
        while (i < lines.length) {
            var line = lines[i];
            var mph = line.match(phRaw);
            if (mph) { html += codeBlocks[+mph[1]]; i++; continue; }
            if (/^\s*>\s?/.test(line)) {
                var quote = [];
                while (i < lines.length && /^\s*>\s?/.test(lines[i])) { quote.push(fmt(lines[i].replace(/^\s*>\s?/, ''))); i++; }
                html += '<blockquote>' + quote.join('<br>') + '</blockquote>';
                continue;
            }
            if (/^\s*[-*+]\s+/.test(line)) {
                var ul = [];
                while (i < lines.length && /^\s*[-*+]\s+/.test(lines[i])) { ul.push('<li>' + fmt(lines[i].replace(/^\s*[-*+]\s+/, '')) + '</li>'); i++; }
                html += '<ul>' + ul.join('') + '</ul>';
                continue;
            }
            if (/^\s*\d+\.\s+/.test(line)) {
                var ol = [];
                while (i < lines.length && /^\s*\d+\.\s+/.test(lines[i])) { ol.push('<li>' + fmt(lines[i].replace(/^\s*\d+\.\s+/, '')) + '</li>'); i++; }
                html += '<ol>' + ol.join('') + '</ol>';
                continue;
            }
            if (line.trim() === '') { i++; continue; }
            var para = [];
            while (i < lines.length && lines[i].trim() !== '' && !/^\s*(>|[-*+]\s|\d+\.\s)/.test(lines[i]) && !phRaw.test(lines[i])) {
                para.push(fmt(lines[i])); i++;
            }
            html += '<p>' + para.join('<br>') + '</p>';
        }
        inline.forEach(function (v, idx) { html = html.split(SP + 'I' + idx + SP).join(v); });
        return html;
    }

    // Wire an individual form (top-level or reply/edit) — submit, emoji, captcha.
    function wireCommentForm(postId, form, listEl, countEl, editId) {
        if (!form) return;
        form.addEventListener('click', function (e) { e.stopPropagation(); });

        var textarea = form.querySelector('.comment-textarea');
        var emojiBtn = form.querySelector('.comment-emoji-btn');
        var emojiPanel = form.querySelector('.comment-emoji-panel');
        if (emojiBtn && emojiPanel) {
            emojiBtn.addEventListener('click', function () { emojiPanel.hidden = !emojiPanel.hidden; });
            // v1.4.2 emoji-insert fix: clicking an emoji <button> used to blur the
            // textarea and drop the caret, so the emoji never appeared to land in
            // the box. Cancel the mousedown default so focus/selection stay on the
            // textarea (caret preserved), then insert on click — click still fires
            // on touch devices, so this works cross-device without double-insert.
            emojiPanel.addEventListener('mousedown', function (e) {
                if (e.target.closest('.comment-emoji')) { e.preventDefault(); }
            });
            emojiPanel.addEventListener('click', function (e) {
                var b = e.target.closest('.comment-emoji');
                if (b) { insertAtCaret(textarea, b.textContent); }
            });
        }

        // Markdown toolbar + preview toggle (v1.3.7).
        var mdToolbar = form.querySelector('.comment-md-toolbar');
        var mdPreview = form.querySelector('.comment-md-preview');
        if (mdToolbar && textarea) {
            mdToolbar.addEventListener('click', function (e) {
                var btn = e.target.closest('[data-md]');
                if (!btn) return;
                var kind = btn.getAttribute('data-md');
                if (kind === 'preview') {
                    var showing = !mdPreview.hidden;
                    if (showing) {
                        mdPreview.hidden = true;
                        textarea.hidden = false;
                        btn.classList.remove('is-active');
                        btn.textContent = '预览';
                        textarea.focus();
                    } else {
                        mdPreview.innerHTML = (textarea.value || '').trim()
                            ? renderMarkdownSubset(textarea.value)
                            : '<p class="comment-md-preview-empty">没有可预览的内容。</p>';
                        mdPreview.hidden = false;
                        textarea.hidden = true;
                        btn.classList.add('is-active');
                        btn.textContent = '编辑';
                    }
                    return;
                }
                applyMdMarker(textarea, kind);
            });
        }

        var captchaInput = form.querySelector('.comment-captcha-input');
        if (captchaInput) {
            loadCaptcha(form);
            var refresh = form.querySelector('.comment-captcha-refresh');
            if (refresh) refresh.addEventListener('click', function () { loadCaptcha(form); });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            submitCommentForm(postId, form, listEl, countEl, editId);
        });
    }

    function submitCommentForm(postId, form, listEl, countEl, editId) {
        var feedback = form.querySelector('.comment-feedback');
        var submitBtn = form.querySelector('.comment-submit');
        var textarea = form.querySelector('.comment-textarea');
        var content = (textarea.value || '').trim();
        var submitLabel = submitBtn.textContent;
        feedback.className = 'comment-feedback';
        if (!content) { feedback.textContent = '请输入评论内容。'; feedback.classList.add('is-error'); return; }

        // Edit mode → PUT-style edit endpoint.
        if (editId) {
            postJson(ccEndpoint('/' + editId + '/edit'), { content: content }).then(function (r) {
                if (!r.ok) { feedback.textContent = ccError(r); feedback.classList.add('is-error'); return; }
                var textEl = listEl.querySelector('[data-cc-text="' + editId + '"]');
                if (textEl && r.data.comment) {
                    textEl.innerHTML = r.data.comment.content || '';
                    textEl.removeAttribute('data-cc-folded');
                    textEl.classList.remove('is-folded');
                    textEl.style.maxHeight = '';
                    var stale = textEl.parentNode.querySelector('.comment-fold-toggle');
                    if (stale) stale.remove();
                    applyFolding(listEl);
                    // Mark edited.
                    var head = textEl.parentNode.querySelector('.comment-sub');
                    if (head && !head.querySelector('.comment-edited')) {
                        head.insertAdjacentHTML('beforeend', '<button type="button" class="comment-edited" data-cc-history="' + editId + '">已编辑</button>');
                    }
                }
                closeInlineForm(form);
            });
            return;
        }

        var parentId = parseInt(form.getAttribute('data-cc-parent'), 10) || 0;
        var payload = { post: postId, content: content, parent: parentId };

        if (!APP.loggedIn) {
            var author = (form.querySelector('.comment-author').value || '').trim();
            var email = (form.querySelector('.comment-email').value || '').trim();
            if (!author || !email) { feedback.textContent = '请填写昵称与邮箱。'; feedback.classList.add('is-error'); return; }
            payload.author_name = author;
            payload.author_email = email;
        }

        var captchaInput = form.querySelector('.comment-captcha-input');
        if (captchaInput) {
            payload.captcha_token = captchaInput.getAttribute('data-cc-token') || '';
            payload.captcha_answer = (captchaInput.value || '').trim();
            if (!payload.captcha_answer) { feedback.textContent = '请回答验证码。'; feedback.classList.add('is-error'); return; }
        }

        var privateEl = form.querySelector('.comment-private');
        if (privateEl && privateEl.checked) payload.is_private = 1;
        var notifyEl = form.querySelector('.comment-notify');
        if (notifyEl) payload.notify = notifyEl.checked ? 1 : 0;

        submitBtn.disabled = true;
        submitBtn.textContent = '提交中…';
        feedback.textContent = '';

        postJson(ccEndpoint(''), payload).then(function (r) {
            submitBtn.disabled = false;
            submitBtn.textContent = submitLabel;
            if (!r.ok) {
                feedback.textContent = ccError(r);
                feedback.classList.add('is-error');
                if (captchaInput) loadCaptcha(form);
                return;
            }
            textarea.value = '';
            var c = r.data.comment;
            if (r.data.status !== 'approved') {
                feedback.textContent = '评论已提交，等待审核后显示。';
                feedback.classList.add('is-success');
                if (captchaInput) loadCaptcha(form);
                return;
            }
            insertNewComment(c, parentId, listEl, countEl, form);
            feedback.textContent = parentId ? '回复成功！' : '评论发表成功！';
            feedback.classList.add('is-success');
            if (captchaInput) loadCaptcha(form);
        });
    }

    // Place a freshly-approved comment into the DOM.
    function insertNewComment(c, parentId, listEl, countEl, form) {
        var empty = listEl.querySelector('.comments-empty');
        if (empty) empty.remove();
        if (parentId) {
            var parentLi = listEl.querySelector('.comment-item[data-cc-id="' + parentId + '"]');
            // Replies always attach to the top-level ancestor's children list.
            var rootLi = parentLi && parentLi.classList.contains('comment-item--child')
                ? listEl.querySelector('.comment-item[data-cc-id="' + c.parent + '"]')
                : parentLi;
            if (rootLi) {
                var childUl = rootLi.querySelector(':scope > .comment-children');
                if (!childUl) {
                    childUl = document.createElement('ul');
                    childUl.className = 'comment-children';
                    rootLi.appendChild(childUl);
                }
                childUl.insertAdjacentHTML('beforeend', buildCommentNode(c, true));
                i18nScan(childUl); // v1.4.3: 新回复也按当前语言翻译
            }
            closeInlineForm(form);
        } else {
            listEl.insertAdjacentHTML('beforeend', buildCommentNode(c, false));
        }
        applyFolding(listEl);
        i18nScan(listEl); // v1.4.3: 新评论也按当前语言翻译
        var current = parseInt((countEl.textContent || '').replace(/\D/g, ''), 10) || 0;
        countEl.textContent = '(' + (current + 1) + ')';
    }

    function closeInlineForm(form) {
        var slot = form.closest('.comment-reply-slot');
        if (slot) slot.innerHTML = '';
    }

    // Delegated handling for the whole list: like / reply / edit / pin / fold /
    // pager / history.
    function wireCommentList(postId, listEl, countEl) {
        var section = dom.articleComments.querySelector('.comments-section');
        section.addEventListener('click', function (e) {
            var t = e.target;

            var likeBtn = t.closest('[data-cc-like]');
            if (likeBtn) {
                var lid = likeBtn.getAttribute('data-cc-like');
                postJson(ccEndpoint('/' + lid + '/like'), {}).then(function (r) {
                    if (!r.ok) return;
                    likeBtn.classList.toggle('is-liked', !!r.data.liked);
                    var cnt = likeBtn.querySelector('.comment-like-count');
                    if (cnt) cnt.textContent = r.data.likes;
                });
                return;
            }

            var pinBtn = t.closest('[data-cc-pin]');
            if (pinBtn) {
                var pid = pinBtn.getAttribute('data-cc-pin');
                postJson(ccEndpoint('/' + pid + '/pin'), {}).then(function (r) {
                    if (!r.ok) return;
                    // Simplest correct refresh: reload from page 1.
                    loadCommentPage(postId, 1, true, listEl, countEl);
                });
                return;
            }

            var replyBtn = t.closest('[data-cc-reply]');
            if (replyBtn) {
                openReplyForm(postId, replyBtn, listEl, countEl);
                return;
            }

            var editBtn = t.closest('[data-cc-edit]');
            if (editBtn) {
                openEditForm(postId, editBtn, listEl, countEl);
                return;
            }

            var foldBtn = t.closest('[data-cc-fold]');
            if (foldBtn) {
                var text = foldBtn.previousElementSibling;
                if (text && text.classList.contains('comment-text')) {
                    var expanded = text.classList.toggle('is-expanded');
                    text.style.maxHeight = expanded ? 'none' : (CCFG.foldPx || 200) + 'px';
                    foldBtn.textContent = expanded ? '收起' : '展开阅读全文';
                }
                return;
            }

            var histBtn = t.closest('[data-cc-history]');
            if (histBtn) {
                toggleHistory(histBtn);
                return;
            }

            var pageBtn = t.closest('[data-cc-page]');
            if (pageBtn) {
                var pg = parseInt(pageBtn.getAttribute('data-cc-page'), 10) || 1;
                var replace = (CCFG.pagination === 'paged');
                loadCommentPage(postId, pg, replace, listEl, countEl);
                return;
            }
        });
    }

    function openReplyForm(postId, btn, listEl, countEl) {
        var li = btn.closest('.comment-item');
        var slot = li.querySelector(':scope > .comment-body > .comment-reply-slot');
        if (!slot) return;
        if (slot.innerHTML) { slot.innerHTML = ''; return; } // toggle off.
        var name = btn.getAttribute('data-cc-name') || '';
        slot.innerHTML = buildCommentFormHtml(parseInt(btn.getAttribute('data-cc-reply'), 10), name);
        wireCommentForm(postId, slot.querySelector('.comment-form'), listEl, countEl);
        var ta = slot.querySelector('.comment-textarea');
        if (ta) ta.focus();
    }

    function openEditForm(postId, btn, listEl, countEl) {
        var li = btn.closest('.comment-item');
        var id = parseInt(btn.getAttribute('data-cc-edit'), 10);
        var slot = li.querySelector(':scope > .comment-body > .comment-reply-slot');
        if (!slot) return;
        if (slot.innerHTML) { slot.innerHTML = ''; return; }
        var textEl = li.querySelector(':scope > .comment-body > [data-cc-text="' + id + '"]');
        var raw = (textEl && textEl.getAttribute('data-cc-raw')) || '';
        // Edit form: a minimal textarea + save/cancel.
        slot.innerHTML = ''
            + '<form class="comment-form comment-form--edit" novalidate>'
            +   '<textarea class="comment-textarea" rows="3"></textarea>'
            + (CCFG.emojiPanel !== false ? '<button type="button" class="comment-emoji-btn" title="插入表情">😊</button><div class="comment-emoji-panel" hidden>' + EMOJI_LIST.map(function (em) { return '<button type="button" class="comment-emoji">' + em + '</button>'; }).join('') + '</div>' : '')
            +   '<div class="comment-form-footer"><span class="comment-feedback"></span><button type="button" class="comment-edit-cancel comment-act">取消</button><button type="submit" class="comment-submit">保存</button></div>'
            + '</form>';
        var form = slot.querySelector('.comment-form');
        var ta = form.querySelector('.comment-textarea');
        ta.value = ccEditRaw[id] || raw || stripHtml(textEl ? textEl.innerHTML : '');
        wireCommentForm(postId, form, listEl, countEl, id);
        form.querySelector('.comment-edit-cancel').addEventListener('click', function () { slot.innerHTML = ''; });
        ta.focus();
    }

    function toggleHistory(btn) {
        var id = btn.getAttribute('data-cc-history');
        var body = btn.closest('.comment-body');
        var existing = body.querySelector('.comment-history');
        if (existing) { existing.remove(); return; }
        var hist = ccHistory[id];
        var html = '<div class="comment-history">';
        if (hist && hist.length) {
            html += '<p class="comment-history-title">编辑记录</p>';
            for (var i = 0; i < hist.length; i++) {
                var d = hist[i].date ? formatDate(String(hist[i].date).split('T')[0]) : '';
                html += '<div class="comment-history-item"><span class="comment-history-date">' + escapeHtml(d) + '</span>' + (hist[i].content || '') + '</div>';
            }
        } else {
            html += '<p class="comment-history-empty">无可查看的编辑记录。</p>';
        }
        html += '</div>';
        btn.closest('.comment-body').querySelector('.comment-text').insertAdjacentHTML('afterend', html);
    }

    // Caches keyed by comment id, populated as nodes are built.
    var ccHistory = {};
    var ccEditRaw = {};

    // POST helper: JSON body + nonce, returns { ok, data }.
    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': APP.restNonce || '' },
            body: JSON.stringify(payload || {})
        }).then(function (res) {
            return res.json().then(function (data) { return { ok: res.ok, data: data }; })
                .catch(function () { return { ok: res.ok, data: {} }; });
        }).catch(function () { return { ok: false, data: {} }; });
    }

    function ccError(r) {
        if (r.data && r.data.message) return stripHtml(r.data.message);
        return '操作失败，请稍后再试。';
    }

    // ---------------------------------------------------------------
    // 11. Dynamic Photo Grid Panels
    // ---------------------------------------------------------------
    var PHOTO_GRID_MARGIN = 16;
    var PANEL_PADDING = 16;
    var THUMB_SIZE = 120;

    function normalizePhotoProperties(properties) {
        var props = properties || {};
        if (typeof props.tags === 'string') {
            try { props.tags = JSON.parse(props.tags); } catch (err) { props.tags = []; }
        }
        return props;
    }

    // Live screen position of a map coordinate — recomputed at animation time
    // so the panel always grows from / shrinks into the marker's current spot.
    function pointRectFor(coords) {
        if (!state.map || !coords) return null;
        var p = state.map.project(new maplibregl.LngLat(coords[0], coords[1]));
        return { left: p.x, top: p.y, width: 1, height: 1 };
    }

    // Grow the panel out of its map point (same window-scale motion as the
    // article panel: a FLIP transform with a top-left origin).
    function animatePhotoPanelOpen(el, coords) {
        if (!el || state.isMobile || prefersReducedMotion()) return;
        var target = pointRectFor(coords);
        var panelRect = el.getBoundingClientRect();
        if (!target || !panelRect.width || !panelRect.height) return;
        if (el._photoMotion) { el._photoMotion.cancel(); el._photoMotion = null; }
        var from = collapseTransform(target, panelRect);
        var anim = el.animate([
            { transform: from, opacity: 0 },
            { opacity: 1, offset: 0.15 },
            { transform: 'translate(0,0) scale(1,1)', opacity: 1 }
        ], { duration: ARTICLE_MOTION.openDuration, easing: ARTICLE_MOTION.easing, fill: 'both' });
        el._photoMotion = anim;
        anim.onfinish = function () { if (el._photoMotion === anim) { anim.cancel(); el._photoMotion = null; } };
    }

    // Shrink the panel back into its map point, then run onDone (removal).
    function animatePhotoPanelClose(el, coords, onDone) {
        if (!el) { if (onDone) onDone(); return; }
        el._closing = true; // spare legitimately-closing panels from the hard sweep
        el.style.pointerEvents = 'none';
        var target = pointRectFor(coords);
        var panelRect = el.getBoundingClientRect();
        if (state.isMobile || prefersReducedMotion() || !target || !panelRect.width) {
            el.classList.add('photo-grid-panel--dismiss');
            el.classList.remove('active');
            setTimeout(function () { if (onDone) onDone(); }, 400);
            return;
        }
        if (el._photoMotion) { el._photoMotion.cancel(); el._photoMotion = null; }
        var to = collapseTransform(target, panelRect);
        var anim = el.animate([
            { transform: 'translate(0,0) scale(1,1)', opacity: 1 },
            { opacity: 1, offset: 0.82 },
            { transform: to, opacity: 0 }
        ], { duration: ARTICLE_MOTION.closeDuration, easing: ARTICLE_MOTION.easing, fill: 'both' });
        el._photoMotion = anim;
        anim.onfinish = function () { if (onDone) onDone(); };
    }

    function dismissPhotoPanelWithAnim(key) {
        var panel = state.photoPanels.get(key);
        if (!panel || panel.dismissing) return;
        panel.dismissing = true;
        var el = panel.element;
        animatePhotoPanelClose(el, panel.entity && panel.entity.coords, function () {
            el.remove();
            if (state.photoPanels.get(key) === panel) state.photoPanels.delete(key);
        });
    }

    // Shrink every open panel back into its point, then drop them all.
    function dismissAllPhotoPanels() {
        if (state.photoPanels.size === 0) {
            state.visibleEntities.clear();
            state.activePhotoPanelKey = null;
            return;
        }
        var panels = Array.from(state.photoPanels.values());
        state.photoPanels = new Map();
        state.visibleEntities = new Map();
        state.activePhotoPanelKey = null;
        panels.forEach(function (panel) {
            var el = panel.element;
            animatePhotoPanelClose(el, panel.entity && panel.entity.coords, function () { el.remove(); });
        });
    }

    function renderVisibleEntities(nextEntities) {
        var prevActiveKey = state.activePhotoPanelKey;
        var keys = Array.from(nextEntities.keys());
        var newActiveKey = keys.length > 0
            ? (nextEntities.has(prevActiveKey) ? prevActiveKey : keys[0])
            : null;
        state.activePhotoPanelKey = newActiveKey;

        state.photoPanels.forEach(function(panel, key) {
            if (!nextEntities.has(key)) {
                dismissPhotoPanelWithAnim(key);
            }
        });

        var newlyCreated = [];
        nextEntities.forEach(function(entity, key) {
            var panel = state.photoPanels.get(key);
            if (!panel) {
                panel = createPhotoPanel(entity, key === newActiveKey);
                state.photoPanels.set(key, panel);
                newlyCreated.push(panel);
            } else {
                panel.entity = entity;
                panel.element.classList.toggle('active', key === newActiveKey);
                panel.element.classList.remove('photo-grid-panel--dismiss');
            }
        });

        state.visibleEntities = nextEntities;
        positionAllPhotoPanels();
        if (!state.isMobile && nextEntities.size > 0) openSidebar();

        requestAnimationFrame(function() {
            state.photoPanels.forEach(function(panel) {
                panel.element.classList.add('photo-grid-panel--positioned');
            });
            // Grow freshly opened panels out of their corresponding map point.
            newlyCreated.forEach(function(panel) {
                if (panel.element.classList.contains('active')) {
                    animatePhotoPanelOpen(panel.element, panel.entity.coords);
                }
            });
        });
    }

    function createPhotoPanel(entity, isActive, opts) {
        if (typeof isActive === 'undefined') isActive = true;
        opts = opts || {};
        var element = document.createElement('div');
        element.className = 'photo-grid-panel glass-panel' + (isActive ? ' active' : '');
        element.setAttribute('role', 'dialog');
        element.setAttribute('aria-modal', 'false');
        element.setAttribute('aria-label', entity.photos.length > 1 ? 'Photo cluster' : 'Photo');

        var close = document.createElement('button');
        close.className = 'panel-close-btn';
        close.setAttribute('aria-label', 'Close photo grid');
        close.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

        var title = document.createElement('div');
        title.className = 'photo-grid-title';
        title.textContent = entity.photos.length > 1 ? '聚合（' + entity.photos.length + ' 张）' : '';

        var container = document.createElement('div');
        container.className = 'photo-grid-container';
        var cols = Math.max(1, Math.min(entity.photos.length, 3));
        element.style.width = (cols * THUMB_SIZE + (cols - 1) * 10 + PANEL_PADDING * 2) + 'px';
        container.style.gridTemplateColumns = 'repeat(' + cols + ', 1fr)';

        entity.photos.forEach(function(feature) {
            var props = normalizePhotoProperties(feature.properties || feature);
            var item = document.createElement('div');
            item.className = 'photo-grid-item';
            var imgUrl = props.thumbnail || props.fullImage || '';
            item.innerHTML = (imgUrl
                ? '<img src="' + escapeHtml(imgUrl) + '" alt="' + escapeHtml(props.title) + '" loading="lazy">'
                : '<div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;color:var(--text-muted);font-size:0.75rem;">无图</div>')
                + '<div class="photo-item-overlay">' + escapeHtml(props.title) + '</div>';
            item.addEventListener('click', function(event) {
                event.stopPropagation();
                // Mobile keeps the large detail sheet (parent article reachable
                // via its "查看文章" button). Desktop opens the parent article
                // directly and glides to the paragraph holding this image.
                if (state.isMobile) {
                    closeAllPhotoPanels();
                    openDetailPanel(props);
                    return;
                }
                var postId = props.postId || props.post_id || null;
                if (!postId) {
                    closeAllPhotoPanels();
                    openDetailPanel(props);
                    return;
                }
                openSidebar();
                openArticle(postId, {
                    scrollToImageId: props.id,
                    scrollToImageUrl: props.fullImage || props.thumbnail || ''
                });
            });
            container.appendChild(item);
        });

        close.addEventListener('click', function(event) {
            event.stopPropagation();
            if (opts.onClose) { opts.onClose(); return; }
            entity.ids.forEach(function(id) { state.openPhotoIds.delete(id); });
            reconcileOpenPhotoPanels();
        });
        element.addEventListener('click', function(event) { event.stopPropagation(); });
        element.appendChild(close);
        element.appendChild(title);
        element.appendChild(container);
        dom.photoPanels.appendChild(element);
        return { element: element, entity: entity };
    }

    function positionAllPhotoPanels() {
        var index = 0;
        state.photoPanels.forEach(function(panel) {
            positionPhotoPanel(panel.element, panel.entity.coords, index++);
        });
        positionRegionPanels();
    }

    function positionPhotoPanel(panel, coords, index) {
        if (!state.map || !coords) return;
        var panelW = panel.offsetWidth || parseInt(panel.style.width, 10) || 200;
        var panelH = panel.offsetHeight || 200;
        var screenPoint = state.map.project(new maplibregl.LngLat(coords[0], coords[1]));
        var left = screenPoint.x + PHOTO_GRID_MARGIN;
        var top = screenPoint.y - panelH / 2 + (index % 3) * 18;
        if (left + panelW > window.innerWidth - 20) left = screenPoint.x - panelW - PHOTO_GRID_MARGIN;
        left = Math.max(12, Math.min(left, window.innerWidth - panelW - 12));
        top = Math.max(12, Math.min(top, window.innerHeight - panelH - 12));
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
    }

    function hasOpenPhotoPanels() {
        return state.photoPanels.size > 0;
    }

    function closeAllPhotoPanels() {
        state.reconcileToken++;
        state.photoClickSeq++;   // invalidate any in-flight cluster callback (v1.3.8)
        state.openPhotoIds.clear();
        dismissAllPhotoPanels();
        closeAllRegionPanels();
        // (b) hard sweep: remove any stray .photo-grid-panel nodes that escaped
        //     the state maps (the "can't-close orphan" from a lost race). Panels
        //     that dismissAllPhotoPanels/closeAllRegionPanels just started
        //     animating are flagged _closing, so only true orphans are removed.
        if (dom.photoPanels) {
            var strays = dom.photoPanels.querySelectorAll('.photo-grid-panel');
            for (var i = 0; i < strays.length; i++) {
                if (!strays[i]._closing) { strays[i].remove(); }
            }
        }
    }

    // ---------------------------------------------------------------
    // 12b. Region photo panels (region mode)
    //
    // Kept in their own map so the droplet reconcile loop (which rebuilds
    // state.photoPanels from rendered clusters) never dismisses them. Only one
    // region panel is open at a time, anchored at the region centroid.
    // ---------------------------------------------------------------
    function openRegionPanel(id) {
        var photos = REGION.photos[id] || [];
        if (!photos.length) return;
        closeAllRegionPanels();
        var entity = {
            coords: REGION.centroids[id] || (photos[0].geometry && photos[0].geometry.coordinates),
            photos: photos,
            ids: photos.map(function (f) { return photoId(f.properties); }),
            key: 'region:' + id
        };
        var panel = createPhotoPanel(entity, true, { onClose: closeAllRegionPanels });
        state.regionPanels.set(entity.key, panel);
        positionPhotoPanel(panel.element, entity.coords, 0);
        if (!state.isMobile) openSidebar();
        requestAnimationFrame(function () {
            panel.element.classList.add('photo-grid-panel--positioned');
            animatePhotoPanelOpen(panel.element, entity.coords);
        });
    }

    function closeAllRegionPanels() {
        if (!state.regionPanels || state.regionPanels.size === 0) return;
        var panels = Array.from(state.regionPanels.values());
        state.regionPanels = new Map();
        panels.forEach(function (panel) {
            var el = panel.element;
            animatePhotoPanelClose(el, panel.entity && panel.entity.coords, function () { el.remove(); });
        });
    }

    function positionRegionPanels() {
        if (!state.regionPanels) return;
        state.regionPanels.forEach(function (panel) {
            positionPhotoPanel(panel.element, panel.entity.coords, 0);
        });
    }

    // ---------------------------------------------------------------
    // 12c. Photo pulse dot (region mode)
    //
    // Region mode has no droplets, so after an article image click flies the
    // map to a photo we drop a small pulsing dot at its exact coordinate to
    // show precisely where it sits. Colour keeps contrast against the basemap:
    // white on the dark auto style, black otherwise. Cleared on the next
    // interaction.
    // ---------------------------------------------------------------
    function pulseDotIsLight() {
        // A light dot (white) is wanted on any dark basemap: the dark auto style,
        // or a China basemap under the pseudo-dark canvas filter.
        return (usingAutoStyle() && !resolveMapIsLight()) || chinaPseudoDarkActive();
    }

    // coords: map-datum position (GCJ under Amap/Tencent) for the dot/popup
    // placement. wgsCoords: the true WGS-84 coordinate shown as text and sent to
    // reverse-geocoding; defaults to coords when they are already WGS-84.
    function showPulseDot(coords, wgsCoords) {
        removePulseDot();
        if (!state.map || !coords) return;
        var el = document.createElement('div');
        el.className = 'sp-pulse-dot' + (pulseDotIsLight() ? ' sp-pulse-dot--light' : '');
        if (prefersReducedMotion()) el.classList.add('sp-pulse-dot--static');
        el.innerHTML = '<span class="sp-pulse-ring"></span><span class="sp-pulse-core"></span>';
        try {
            state.pulseDot = new maplibregl.Marker({ element: el, anchor: 'center' })
                .setLngLat(new maplibregl.LngLat(coords[0], coords[1]))
                .addTo(state.map);
        } catch (e) { state.pulseDot = null; }
        // v1.4.4 (item 4): a small popup hangs below the dot with lng/lat + name.
        showLocationPopup(coords, wgsCoords || coords);
    }

    function removePulseDot() {
        if (state.pulseDot) { try { state.pulseDot.remove(); } catch (e) {} state.pulseDot = null; }
        removeLocationPopup(); // v1.4.4 item 4: same lifecycle as the pulse dot
    }

    // v1.4.4 (item 4): location popup below the pulse dot — lng/lat immediately,
    // reverse-geocoded place name filled in when it returns. Lifecycle is tied to
    // the pulse dot (created/removed together). Open/close grow from / shrink
    // toward the dot using the article-panel motion tokens, via WAAPI with
    // onfinish/oncancel guards so the animation ALWAYS runs to completion.
    function locPopupCoordText(coords) {
        var lat = coords[1], lng = coords[0];
        var ns = lat >= 0 ? 'N' : 'S', ew = lng >= 0 ? 'E' : 'W';
        return Math.abs(lat).toFixed(5) + '° ' + ns + ' · ' + Math.abs(lng).toFixed(5) + '° ' + ew;
    }

    // coords: map-datum position for placement. wgs: true WGS-84 for the coord
    // text + reverse-geocode (defaults to coords when already WGS-84).
    function showLocationPopup(coords, wgs) {
        removeLocationPopup(true); // instantly clear a superseded popup
        if (!state.map || !coords) return;
        wgs = wgs || coords;
        var el = document.createElement('div');
        el.className = 'sp-loc-popup' + (prefersReducedMotion() ? ' sp-loc-popup--static' : '');
        // v1.4.5 (item 5): the grow/shrink animation must run on an INNER wrapper,
        // not on `el` — MapLibre positions the marker by writing `transform:
        // translate()` onto the marker root element, so animating transform on
        // `el` (with fill:both) clobbers that translate and the popup jumps to the
        // map origin (why it never appeared in v1.4.4). Animate `.sp-loc-popup-inner`.
        el.innerHTML =
            '<div class="sp-loc-popup-inner">'
          +   '<span class="sp-loc-popup-arrow" aria-hidden="true"></span>'
          +   '<div class="sp-loc-popup-body">'
          +     '<div class="sp-loc-popup-coord">' + escapeHtml(locPopupCoordText(wgs)) + '</div>'
          +     '<div class="sp-loc-popup-name"><span class="sp-loc-popup-primary">' + escapeHtml(t('解析中…')) + '</span></div>'
          +     '<div class="sp-loc-popup-actions">'
          +       '<a class="sp-loc-popup-map-link" target="_blank" rel="noopener noreferrer" href="' + escapeHtml(locationMapUrl('amap', wgs)) + '">' + escapeHtml(t('高德地图')) + '</a>'
          +       '<a class="sp-loc-popup-map-link" target="_blank" rel="noopener noreferrer" href="' + escapeHtml(locationMapUrl('baidu', wgs)) + '">' + escapeHtml(t('百度地图')) + '</a>'
          +     '</div>'
          +   '</div>'
          + '</div>';
        var inner = el.querySelector('.sp-loc-popup-inner');
        var token = (state.locPopupSeq = (state.locPopupSeq || 0) + 1);
        var marker;
        try {
            marker = new maplibregl.Marker({ element: el, anchor: 'top', offset: [0, 16] })
                .setLngLat(new maplibregl.LngLat(coords[0], coords[1]))
                .addTo(state.map);
        } catch (e) { return; }
        state.locPopup = { marker: marker, el: el, inner: inner, token: token, closing: false, anim: null };

        if (!prefersReducedMotion() && inner && inner.animate) {
            var dur = (typeof ARTICLE_MOTION === 'object' && ARTICLE_MOTION.openDuration) ? ARTICLE_MOTION.openDuration : 320;
            var ease = (typeof ARTICLE_MOTION === 'object' && ARTICLE_MOTION.easing) ? ARTICLE_MOTION.easing : 'cubic-bezier(0.16,1,0.3,1)';
            var anim = inner.animate([
                { transform: 'scale(0.2)', opacity: 0 },
                { opacity: 1, offset: 0.3 },
                { transform: 'scale(1)', opacity: 1 }
            ], { duration: dur, easing: ease, fill: 'both' });
            state.locPopup.anim = anim;
            anim.onfinish = function () { if (state.locPopup && state.locPopup.token === token) state.locPopup.anim = null; };
        }

        // Async reverse-geocode using the TRUE WGS-84 coordinate (the geocoding
        // service expects real GPS, not the GCJ-02-shifted display position).
        fetchReverseGeocode(wgs).then(function (place) {
            if (!state.locPopup || state.locPopup.token !== token || state.locPopup.closing) return;
            var nameEl = el.querySelector('.sp-loc-popup-name');
            if (!nameEl) return;
            if (place && place.name) {
                nameEl.innerHTML = '';
                if (place.primary) {
                    var primary = document.createElement('span');
                    primary.className = 'sp-loc-popup-primary';
                    primary.textContent = place.primary;
                    nameEl.appendChild(primary);
                }
                if (place.admin) {
                    var admin = document.createElement('strong');
                    admin.className = 'sp-loc-popup-admin';
                    admin.textContent = place.admin;
                    nameEl.appendChild(admin);
                }
                if (!place.primary && !place.admin) nameEl.textContent = place.name;
            }
            else { nameEl.remove(); } // resolve failed → keep only lng/lat
        });
    }

    function locationMapUrl(provider, coords) {
        var lat = Number(coords[1]).toFixed(7), lng = Number(coords[0]).toFixed(7);
        if (provider === 'baidu') {
            return 'https://api.map.baidu.com/marker?location=' + encodeURIComponent(lat + ',' + lng)
                + '&title=' + encodeURIComponent(t('照片位置')) + '&content=' + encodeURIComponent(t('照片位置'))
                + '&output=html&coord_type=wgs84&src=webapp.sphotography.theme';
        }
        return 'https://uri.amap.com/marker?position=' + encodeURIComponent(lng + ',' + lat)
            + '&name=' + encodeURIComponent(t('照片位置')) + '&src=sphotography&coordinate=wgs84&callnative=0';
    }

    function removeLocationPopup(instant) {
        var p = state.locPopup;
        if (!p) return;
        state.locPopup = null;   // detach now so a new popup can supersede cleanly
        p.closing = true;
        var done = function () { try { p.marker.remove(); } catch (e) {} };
        var animEl = p.inner || p.el; // v1.4.5 (item 5): animate the inner wrapper, not the marker root
        if (instant || prefersReducedMotion() || !animEl.animate) { done(); return; }
        if (p.anim) { try { p.anim.cancel(); } catch (e) {} }
        var dur = (typeof ARTICLE_MOTION === 'object' && ARTICLE_MOTION.closeDuration) ? ARTICLE_MOTION.closeDuration : 280;
        var ease = (typeof ARTICLE_MOTION === 'object' && ARTICLE_MOTION.easing) ? ARTICLE_MOTION.easing : 'cubic-bezier(0.16,1,0.3,1)';
        var anim = animEl.animate([
            { transform: 'scale(1)', opacity: 1 },
            { opacity: 1, offset: 0.18 },
            { transform: 'scale(0.2)', opacity: 0 }
        ], { duration: dur, easing: ease, fill: 'both' });
        anim.onfinish = done;
        anim.oncancel = done; // guarantee removal even if interrupted
    }

    function fetchReverseGeocode(coords) {
        var url = CONFIG.restBase + '/sphotography/v1/reverse-geocode'
            + '?lat=' + encodeURIComponent(coords[1])
            + '&lng=' + encodeURIComponent(coords[0])
            + '&lang=' + encodeURIComponent((typeof siteLang === 'string' && siteLang) ? siteLang : 'zh');
        return fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) { return (d && d.name) ? d : null; })
            .catch(function () { return null; });
    }

    // ---------------------------------------------------------------
    // 13. Detail Panel
    // ---------------------------------------------------------------
    function openDetailPanel(props) {
        if (!props) return;
        // v1.4.7 (item 8): the inline payload no longer carries the full-res URL.
        // Show the thumbnail instantly, then fetch the full image on demand by
        // attachment id and swap it in when ready (only for this opened photo).
        dom.detailImg.src = props.fullImage || props.thumbnail || '';
        dom.detailImg.alt = props.title || '';
        if (!props.fullImage && props.id) {
            var wantId = props.id;
            fetchFromRest('sphotography/v1/photo-full/' + encodeURIComponent(props.id)).then(function (data) {
                if (!data || !data.full) return;
                props.fullImage = data.full; // cache on the feature props
                // Only swap if the detail view is still showing this same photo.
                if (state.currentDetailId === wantId) dom.detailImg.src = data.full;
            }).catch(function () {});
        }
        state.currentDetailId = props.id || null;
        dom.detailTitle.textContent = props.title || '';

        var metaHtml = '';
        if (props.cameraInfo) {
            metaHtml += '<span class="detail-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="14" rx="2"/><circle cx="12" cy="13" r="4"/></svg>' + escapeHtml(props.cameraInfo) + '</span>';
        }
        if (props.takenAt) {
            metaHtml += '<span class="detail-meta-item"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>' + formatDate(props.takenAt) + '</span>';
        }
        dom.detailMeta.innerHTML = metaHtml;
        dom.detailDesc.textContent = props.description || '';

        var tagsHtml = '';
        (props.tags || []).forEach(function(tag) { tagsHtml += '<span class="detail-tag">' + escapeHtml(tag.name) + '</span>'; });
        dom.detailTags.innerHTML = tagsHtml;

        // Link to the parent post. Hidden when the marker has no article
        // (e.g. an orphaned attachment with coordinates but no post).
        var postId = props.postId || props.post_id || null;
        if (dom.detailViewArticle) {
            if (postId) {
                dom.detailViewArticle.hidden = false;
                dom.detailViewArticle.onclick = function (event) {
                    event.stopPropagation();
                    closeDetailPanel();
                    openSidebar();
                    openArticle(postId);
                };
            } else {
                dom.detailViewArticle.hidden = true;
                dom.detailViewArticle.onclick = null;
            }
        }

        // v1.4.5 (item 7): the scroller is now .detail-content, not the sheet.
        var detailScroll = dom.detailSheet.querySelector('.detail-content');
        if (detailScroll) detailScroll.scrollTop = 0;
        dom.detailSheet.classList.add('active');
        state.detailOpen = true;
    }

    function closeDetailPanel() {
        dom.detailSheet.classList.remove('active');
        state.detailOpen = false;
        removePulseDot();
    }

    // ---------------------------------------------------------------
    // 14. About Card — 常驻右下角，无需展开/收起逻辑
    // ---------------------------------------------------------------

    // ---------------------------------------------------------------
    // 16. Loading
    // ---------------------------------------------------------------
    var LOADING_TIPS = [
        '正在搭建传送门',
        '再等等，马上就加载好了',
        '正在手磨咖啡中，好喝！',
        '正在摸鱼，不对这怎么能叫摸鱼呢',
        '正在环游世界',
        '正在标记地图钉',
        '正在打电动，美滋滋',
        '正在......不知道正在做什么呢',
        '正在劈里啪啦敲键盘'
    ];

    // Preloader style chosen in the admin (⑤ Animation): 'off' renders no
    // overlay at all, 'aperture' is the legacy branded loader, 'flythrough' is
    // the site-name streaming-light + camera-through-text reveal.
    var PRELOADER_STYLE = SETTINGS.preloaderStyle || 'aperture';
    // Minimum on-screen time for the flythrough so its rhythm reads properly
    // even on instant (cached) loads. Only enforced for 'flythrough'.
    var MIN_PRELOADER_MS = 1500;
    // Cap on waiting for web fonts before building the knockout mask / revealing
    // the name — a slow or stalled font never blocks the intro.
    var FONT_WAIT_MS = 1200;
    var SVG_NS = 'http://www.w3.org/2000/svg';
    var PRELOADER_START = Date.now();
    var loadingHidden = false;
    // Populated by prepareFlythrough(); stays null for other styles.
    var flythrough = null;

    // Show a random loading tip below the aperture, swapping every 3s in random
    // order until loading finishes. The first tip is random too, and we avoid
    // repeating the immediately-previous one so it never looks frozen.
    function startLoadingTips() {
        if (!dom.loadingTip) return;
        var lastIndex = -1;

        function pickIndex() {
            if (LOADING_TIPS.length <= 1) return 0;
            var i;
            do { i = Math.floor(Math.random() * LOADING_TIPS.length); }
            while (i === lastIndex);
            return i;
        }

        function swap() {
            var i = pickIndex();
            lastIndex = i;
            // Fade out, change text, fade back in.
            dom.loadingTip.classList.remove('is-visible');
            setTimeout(function () {
                dom.loadingTip.textContent = LOADING_TIPS[i];
                dom.loadingTip.classList.add('is-visible');
            }, 300);
        }

        // First tip shows immediately (no fade-out delay).
        lastIndex = pickIndex();
        dom.loadingTip.textContent = LOADING_TIPS[lastIndex];
        dom.loadingTip.classList.add('is-visible');
        state.tipTimer = setInterval(swap, 3000);
    }

    function stopLoadingTips() {
        if (state.tipTimer) {
            clearInterval(state.tipTimer);
            state.tipTimer = null;
        }
    }

    // ---- Flythrough preloader ----

    // Emoji in the site name can't be cleanly knocked out of an SVG luminance
    // mask, so such names fall back to a plain fade (see runFlythroughExit).
    function preloaderContainsEmoji(str) {
        if (!str) return false;
        try { return /\p{Extended_Pictographic}/u.test(str); }
        catch (e) {
            return /[☀-➿⬀-⯿️‼⁉]|[\uD83C-\uDBFF][\uDC00-\uDFFF]/.test(str);
        }
    }

    // The reveal needs background-clip:text (for 流光) plus SVG masking. If the
    // browser lacks either, degrade to a plain fade.
    function preloaderSupportsKnockout() {
        var clip = false;
        if (window.CSS && CSS.supports) {
            clip = CSS.supports('-webkit-background-clip', 'text') || CSS.supports('background-clip', 'text');
        }
        return clip && typeof document.createElementNS === 'function';
    }

    // Run cb once fonts are ready, or after FONT_WAIT_MS — whichever comes
    // first — so hole shapes are correct without ever hanging on a slow font.
    function preloaderFontsReady(cb) {
        var done = false;
        function run() { if (!done) { done = true; cb(); } }
        if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
            document.fonts.ready.then(run, run);
            setTimeout(run, FONT_WAIT_MS);
        } else {
            setTimeout(run, 0);
        }
    }

    // Split an over-long name across two centered lines. Prefers a space near
    // the middle; falls back to a hard split for space-less (e.g. CJK) names.
    function wrapSvgTextTwoLines(textEl, name, vw, fontSize) {
        while (textEl.firstChild) { textEl.removeChild(textEl.firstChild); }
        var l1, l2;
        var mid = Math.floor(name.length / 2);
        var sp = name.indexOf(' ', mid);
        if (sp === -1) { sp = name.lastIndexOf(' ', mid); }
        if (sp > 0) { l1 = name.slice(0, sp).trim(); l2 = name.slice(sp + 1).trim(); }
        else { var c = Math.ceil(name.length / 2); l1 = name.slice(0, c); l2 = name.slice(c); }
        var lineH = fontSize * 1.1;
        var t1 = document.createElementNS(SVG_NS, 'tspan');
        t1.setAttribute('x', vw / 2); t1.setAttribute('dy', -lineH * 0.5); t1.textContent = l1;
        var t2 = document.createElementNS(SVG_NS, 'tspan');
        t2.setAttribute('x', vw / 2); t2.setAttribute('dy', lineH); t2.textContent = l2;
        textEl.appendChild(t1); textEl.appendChild(t2);
    }

    // Build the full-screen solid rect with the site name knocked out of it,
    // matching the visible .ft-name's font/size so the crossfade is seamless.
    function buildKnockoutSVG(nameEl, name) {
        var cs = window.getComputedStyle(nameEl);
        var vw = Math.max(window.innerWidth, 1);
        var vh = Math.max(window.innerHeight, 1);

        var svg = document.createElementNS(SVG_NS, 'svg');
        svg.setAttribute('class', 'ft-mask-svg');
        svg.setAttribute('viewBox', '0 0 ' + vw + ' ' + vh);
        svg.setAttribute('preserveAspectRatio', 'xMidYMid slice');
        svg.setAttribute('aria-hidden', 'true');

        var maskId = 'ft-knockout-' + Math.random().toString(36).slice(2);
        var defs = document.createElementNS(SVG_NS, 'defs');
        var mask = document.createElementNS(SVG_NS, 'mask');
        mask.setAttribute('id', maskId);
        mask.setAttribute('maskUnits', 'userSpaceOnUse');
        mask.setAttribute('x', '0'); mask.setAttribute('y', '0');
        mask.setAttribute('width', vw); mask.setAttribute('height', vh);

        var whiteRect = document.createElementNS(SVG_NS, 'rect');
        whiteRect.setAttribute('x', '0'); whiteRect.setAttribute('y', '0');
        whiteRect.setAttribute('width', vw); whiteRect.setAttribute('height', vh);
        whiteRect.setAttribute('fill', '#fff');
        mask.appendChild(whiteRect);

        var textEl = document.createElementNS(SVG_NS, 'text');
        textEl.setAttribute('x', vw / 2);
        textEl.setAttribute('y', vh / 2);
        textEl.setAttribute('text-anchor', 'middle');
        textEl.setAttribute('dominant-baseline', 'central');
        textEl.setAttribute('fill', '#000');
        textEl.setAttribute('font-family', cs.fontFamily || 'serif');
        textEl.setAttribute('font-weight', cs.fontWeight || '700');
        var lsPx = parseFloat(cs.letterSpacing);
        if (!isNaN(lsPx) && lsPx) { textEl.setAttribute('letter-spacing', lsPx); }
        var fontSize = parseFloat(cs.fontSize) || Math.min(vw, vh) * 0.1;
        textEl.setAttribute('font-size', fontSize);
        textEl.textContent = name;
        mask.appendChild(textEl);
        defs.appendChild(mask);
        svg.appendChild(defs);

        var fillRect = document.createElementNS(SVG_NS, 'rect');
        fillRect.setAttribute('class', 'ft-mask-fill');
        fillRect.setAttribute('x', '0'); fillRect.setAttribute('y', '0');
        fillRect.setAttribute('width', vw); fillRect.setAttribute('height', vh);
        fillRect.setAttribute('mask', 'url(#' + maskId + ')');
        svg.appendChild(fillRect);

        // Must be attached before measuring glyph widths.
        nameEl.parentNode.appendChild(svg);

        // Shrink, then wrap, so the knocked-out text fits ~80% of viewport width
        // even for very long names.
        var target = vw * 0.8;
        if (typeof textEl.getComputedTextLength === 'function') {
            try {
                var w = textEl.getComputedTextLength();
                if (w > target && w > 0) {
                    fontSize = Math.max(fontSize * (target / w), 14);
                    textEl.setAttribute('font-size', fontSize);
                    w = textEl.getComputedTextLength();
                    if (w > target) { wrapSvgTextTwoLines(textEl, name, vw, fontSize); }
                }
            } catch (e) { /* measurement unsupported → keep single line */ }
        }
        return svg;
    }

    function prepareFlythrough() {
        var overlay = dom.loadingOverlay;
        var nameEl = overlay ? overlay.querySelector('.ft-name') : null;
        if (!overlay || !nameEl) return;

        // Match the overlay + knockout fill to the actual basemap background so
        // the reveal lands on the same color (no color jump into the map).
        overlay.style.setProperty('--ft-map-bg', getMapBgColor());

        var name = (nameEl.textContent || '').trim();
        var reduced = prefersReducedMotion();
        var canKnockout = !reduced && name.length > 0 &&
            !preloaderContainsEmoji(name) && preloaderSupportsKnockout();

        flythrough = { overlay: overlay, nameEl: nameEl, name: name, canKnockout: canKnockout, svg: null, hintEl: null };

        // Wait for fonts before showing the name (avoids a fallback-font flash /
        // reflow) and before building the knockout mask (correct hole shapes).
        preloaderFontsReady(function () {
            if (!flythrough || !document.body.contains(nameEl)) { return; }
            nameEl.classList.add('is-in');
            if (flythrough.canKnockout) {
                try { flythrough.svg = buildKnockoutSVG(nameEl, name); }
                catch (e) { flythrough.canKnockout = false; flythrough.svg = null; }
            }
        });
    }

    // --- Load watchdogs (both styles) ---
    // Slow network: after SLOW_LOAD_MS still loading, reassure the user and keep
    // waiting for the real map 'load' (no upper cap). Only a *fatal* map error
    // (map can never load) force-reveals, after a short grace, so we never hang
    // forever. See onMapFatalError().
    var SLOW_LOAD_MS = 8000;
    var ERROR_GRACE_MS = 5000;
    var slowLoadTimer = null;
    var errorGraceTimer = null;

    function armLoadWatchdogs() {
        if (!dom.loadingOverlay) return; // 'off'
        slowLoadTimer = setTimeout(showSlowLoadHint, SLOW_LOAD_MS);
    }

    function clearLoadWatchdogs() {
        if (slowLoadTimer) { clearTimeout(slowLoadTimer); slowLoadTimer = null; }
        if (errorGraceTimer) { clearTimeout(errorGraceTimer); errorGraceTimer = null; }
    }

    function showSlowLoadHint() {
        if (loadingHidden || !dom.loadingOverlay) return;
        if (PRELOADER_STYLE === 'flythrough' && flythrough && !flythrough.hintEl) {
            var hint = document.createElement('span');
            hint.className = 'ft-hint';
            hint.textContent = '加载中，请稍候…';
            dom.loadingOverlay.appendChild(hint);
            // Next frame so the opacity transition actually plays.
            requestAnimationFrame(function () { hint.classList.add('is-in'); });
            flythrough.hintEl = hint;
        }
        // The aperture style already cycles reassuring tips, so it needs nothing.
    }

    // Called on every map 'error'. A single error may be a transient tile 404,
    // so we don't reveal immediately: we start a grace timer and only force the
    // reveal if 'load' still hasn't fired by the time it elapses (a successful
    // load runs hideLoading, which clears this timer).
    function onMapFatalError() {
        if (loadingHidden || errorGraceTimer) return;
        errorGraceTimer = setTimeout(function () {
            if (!loadingHidden) { hideLoading(); }
        }, ERROR_GRACE_MS);
    }

    function startPreloader() {
        PRELOADER_START = Date.now();
        if (PRELOADER_STYLE === 'flythrough') {
            prepareFlythrough();
        } else if (PRELOADER_STYLE === 'aperture') {
            startLoadingTips();
        }
        // 'off' → no overlay was rendered; nothing to start.
        armLoadWatchdogs();
    }

    function runApertureExit() {
        dom.loadingOverlay.classList.add('fade-out');
        setTimeout(function () { if (dom.loadingOverlay) { dom.loadingOverlay.style.display = 'none'; } }, 600);
    }

    function runFlythroughExit() {
        var ov = dom.loadingOverlay;
        if (!ov) return;
        if (flythrough && flythrough.canKnockout && flythrough.svg) {
            // Clear the inline landing color so the CSS `background: transparent`
            // in .is-revealing can take effect and the knockout holes show the map.
            ov.style.background = 'transparent';
            ov.classList.add('is-revealing');
            // Total = dissolve (460) overlapped + warp (340 delay + 1050) ≈ 1390ms.
            setTimeout(function () { if (ov) { ov.style.display = 'none'; } }, 1550);
        } else {
            // Reduced motion / emoji / unsupported / font timeout → plain fade.
            ov.classList.add('fade-out');
            setTimeout(function () { if (ov) { ov.style.display = 'none'; } }, 800);
        }
    }

    function hideLoading() {
        if (loadingHidden) return;
        loadingHidden = true;
        clearLoadWatchdogs();
        stopLoadingTips();
        // v1.4.5 (item 3): bring the rounded/magnetic cursor alive once the intro
        // is done, so it never fights the open-screen animation. Runs in every
        // path, including 'off' (no overlay) which returns just below.
        try { initRoundedCursor(); } catch (e) {}
        if (!dom.loadingOverlay) return; // 'off' or overlay missing
        // Enforce a minimum on-screen time only for the flythrough so its
        // entrance + 流光 + reveal always reads; aperture keeps its instant exit.
        var wait = 0;
        if (PRELOADER_STYLE === 'flythrough') {
            wait = Math.max(0, MIN_PRELOADER_MS - (Date.now() - PRELOADER_START));
        }
        setTimeout(function () {
            if (PRELOADER_STYLE === 'flythrough') { runFlythroughExit(); }
            else { runApertureExit(); }
        }, wait);
    }

    // ---------------------------------------------------------------
    // 16b. Profile expand (v1.3.2)
    //
    // Both profile modes reveal the same content (avatar / name / bio / stats /
    // links). The card (mode A) grows in place upward; the sidebar panel
    // (mode B) slides up over the article list. Height is measured so the
    // max-height transition lands exactly on the content height for a clean,
    // reversible animation. Closes on outside click, re-click, or Esc.
    // ---------------------------------------------------------------
    function initProfileExpand() {
        // v1.4.8：右下角卡片模式已移除。仅保留边栏一行个人信息面板（点击个人信息行展开）。
        // --- 侧边栏简单个人信息面板 ---
        var wrap = dom.sidebarProfile;
        var toggle = dom.sidebarProfileToggle;
        var panel = dom.sidebarProfilePanel;
        if (wrap && toggle && panel) {
            var setSidebar = function (open) {
                wrap.classList.toggle('is-expanded', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                panel.setAttribute('aria-hidden', open ? 'false' : 'true');
                panel.style.maxHeight = open ? (panel.scrollHeight + 'px') : '';
            };
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                // v1.4.8: mutually exclusive with the rich stats / expand-page.
                if (!wrap.classList.contains('is-expanded') && typeof EXPAND !== 'undefined' && EXPAND.open) closeExpandPage();
                setSidebar(!wrap.classList.contains('is-expanded'));
            });
            // The expanded panel covers the trigger row, so a click on it
            // (anywhere but a link) is the "click again to close" gesture.
            panel.addEventListener('click', function (e) {
                e.stopPropagation();
                if (e.target.closest('a')) return; // let links work
                setSidebar(false);
            });
            document.addEventListener('click', function (e) {
                if (wrap.classList.contains('is-expanded') && !wrap.contains(e.target)) {
                    setSidebar(false);
                }
            });
            window.addEventListener('resize', debounce(function () {
                if (wrap.classList.contains('is-expanded')) {
                    panel.style.maxHeight = panel.scrollHeight + 'px';
                }
            }, 150));
            state._closeSidebarProfile = function () {
                if (wrap.classList.contains('is-expanded')) setSidebar(false);
            };
        }
    }

    // ---------------------------------------------------------------
    // 17. Hitokoto
    // ---------------------------------------------------------------
    function initHitokoto() {
        if (!SETTINGS.enableHitokoto) return;
        var el = document.getElementById('hitokoto-text');
        if (!el) return;
        fetch('https://v1.hitokoto.cn/?c=d&c=i&c=k')
            .then(function(r){return r.json();})
            .then(function(d){el.textContent=d.hitokoto||' ';if(d.from)el.textContent+=' ——'+d.from;})
            .catch(function(){el.textContent=' ';});
    }

    // ---------------------------------------------------------------
    // 18. Entry Animation
    // ---------------------------------------------------------------
    function initEntryAnimation() {
        if (!SETTINGS.entryAnimation) return;
        if (!('IntersectionObserver' in window)) return;
        var targets = document.querySelectorAll('.animate-on-scroll');
        if (targets.length === 0) return;
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) { entry.target.classList.add('animated'); observer.unobserve(entry.target); }
            });
        }, {threshold:0.1});
        targets.forEach(function(el){observer.observe(el);});
    }

    // ---------------------------------------------------------------
    // 19. Use inline PHP data
    // ---------------------------------------------------------------
    function useInlineData() {
        if (typeof SphotographyInlineData === 'undefined') return false;
        var data = SphotographyInlineData;

        if (data.photos && data.photos.length > 0) {
            state.allPhotos = buildGeoJSONFromMarkers(data.photos);
        }

        if (data.posts && data.posts.length > 0) {
            state.allPosts = data.posts.map(function(p) {
                return {
                    id: p.id,
                    title: { rendered: p.title },
                    date: p.date,
                    excerpt: { rendered: p.excerpt },
                    sp_card_excerpt: p.cardExcerpt || '',
                    sp_word_count: p.wordCount,
                    sp_views: p.views,
                    sp_cover: p.cover || '', // v1.4.6 (item 9): article cover URL
                    _embedded: {
                        'wp:featuredmedia': p.thumb ? [{ source_url: p.thumb, media_details: { sizes: { thumbnail: { source_url: p.thumb } } } }] : [],
                        'wp:term': [p.terms || []],
                    },
                };
            });
            state.recentPosts = state.allPosts;
        }

        return true;
    }

    // ---------------------------------------------------------------
    // 20. UI Event Bindings
    // ---------------------------------------------------------------
    function bindUIEvents() {
        dom.sidebarToggle.addEventListener('click', function(e) { e.stopPropagation(); toggleSidebar(); });
        dom.sidebarExpand.addEventListener('click', function(e) { e.stopPropagation(); openSidebar(); });
        dom.sidebarSearch.addEventListener('input', debounce(function() { filterSidebarPosts(this.value); }, 300));
        if (dom.filterBtn) dom.filterBtn.addEventListener('click', function(e) { e.stopPropagation(); toggleFilterPanel(); });
        if (dom.filterPanel) dom.filterPanel.addEventListener('click', function(e) { e.stopPropagation(); });
        // 清除: drop both filter sets, refresh chips, re-render the list.
        if (dom.filterClear) dom.filterClear.addEventListener('click', function(e) {
            e.stopPropagation();
            state.selectedCategories.clear();
            state.selectedRegionTags.clear();
            buildCategoryChips();
            buildRegionChips();
            syncFilterButtonBadge();
            applySidebarFilters();
        });
        dom.articleClose.addEventListener('click', function(e) { e.stopPropagation(); closeArticlePanel(); });
        dom.closeDetail.addEventListener('click', function(e) { e.stopPropagation(); closeDetailPanel(); });

        // Show the platform-correct modifier in the search hint (⌘ on Mac).
        var isMac = /Mac|iPhone|iPad|iPod/.test(navigator.platform || navigator.userAgent || '');
        if (isMac) {
            var kbdMod = document.querySelector('#sidebar-search-kbd .kbd-mod');
            if (kbdMod) kbdMod.textContent = '⌘';
        }

        document.addEventListener('keydown', function(e) {
            // Ctrl+K / ⌘+K focuses the search field.
            if ((e.ctrlKey || e.metaKey) && !e.altKey && (e.key === 'k' || e.key === 'K')) {
                e.preventDefault();
                if (!state.sidebarOpen) openSidebar();
                requestAnimationFrame(function () {
                    dom.sidebarSearch.focus();
                    dom.sidebarSearch.select();
                });
                return;
            }
            if (e.key === 'Escape' || e.key === 'Esc') {
                // Let Escape clear/blur the search field first.
                if (document.activeElement === dom.sidebarSearch && dom.sidebarSearch.value) {
                    dom.sidebarSearch.value = '';
                    filterSidebarPosts('');
                    dom.sidebarSearch.blur();
                    return;
                }
                closeFilterPanel();
                closeAllPhotoPanels();
                closeArticlePanel();
                if (state._closeSidebarProfile) state._closeSidebarProfile();
                dom.detailSheet.classList.remove('active');
                state.detailOpen = false;
            }
        });

        dom.articlePanel.addEventListener('click', function(e) { e.stopPropagation(); });
        dom.detailSheet.addEventListener('click', function(e) { e.stopPropagation(); });
    }

    // ---------------------------------------------------------------
    // 20b. Rounded / magnetic cursor (v1.4.5 items 3 & 4)
    //
    // A JS-driven follower that replaces the OS pointer when cursor_style =
    // 'rounded'. Two visuals share the layer:
    //   • idle: a smooth rounded V-arrow (grey blurred-translucent fill + theme
    //     -colour ring) that trails the pointer with a slight easing lag.
    //   • adsorbed: when the pointer enters a magnet target, the arrow fades and a
    //     rounded-rect RING grows to hug the target's exact border (theme-colour
    //     stroke, faint theme fill). While inside, both ring and — for small
    //     targets only — the element drift toward the pointer (parallax); on exit
    //     the ring releases back to the arrow with a short lag (the "stickiness").
    // Gated: only runs on hover+fine-pointer devices, and activates after the
    // loading intro. prefers-reduced-motion keeps the shape/adsorb but drops the
    // lag/parallax. The MapLibre canvas is intentionally NOT a magnet target.
    // ---------------------------------------------------------------
    var CURSOR_MAGNET_SEL = [
        '.panel-close-btn', '.announcement-close', '.side-panel-close', '.pw-detail-exit',
        '#sidebar-toggle', '#sidebar-expand', '.sidebar-expandpage-btn',
        '.post-card', '.expand-card',
        '#sidebar-search-input', '#sidebar-filter-btn', '.filter-chip', '.filter-clear',
        '.comment-input', '.comment-author', '.comment-email', '.comment-textarea',
        '.comment-captcha-input',
        '.friend-apply-email', '.friend-apply-url', '.friend-apply-name', '.friend-apply-msg',
        '.gb-email', '.gb-nick', '.gb-msg',
        '.profile-expand-link',
        '.comment-submit', '.friend-apply-submit', '.gb-send', '.comment-page-btn', '.gb-loadmore', '.gb-showall',
        '.sp-night-btn', '.sp-lang-btn',
        '.friend-card',
        '.page-link-btn',
        '#sidebar-github',
        '.article-nav-btn',
        '.share-btn',
        '.comment-md-btn', '.comment-md-preview-toggle', '.comment-sort-btn',
        '.pw-cell',
        '.pw-detail-round', '.pw-side-btn',
        '.article-toc-bar', '.article-toc-item', '.article-toc-chevron'
    ].join(',');

    var CURSOR = {
        active: false, started: false, reduced: false,
        arrow: null, ring: null,
        px: 0, py: 0,          // real pointer
        ax: 0, ay: 0,          // eased arrow position
        target: null, rect: null, radius: '0px', drift: false,
        raf: null, visible: false, overFocusedInput: false
    };
    var CURSOR_DRIFT_MAX = 6;      // px the ring/element leans toward the pointer
    var CURSOR_BIG = 180;          // targets larger than this (either dim) don't drift
    var CURSOR_LAG = 0.28;         // idle follow easing (1 = no lag)

    function roundedCursorEnabled() {
        if (!document.body.classList.contains('sphotography-cursor-rounded')) return false;
        return !!(window.matchMedia && window.matchMedia('(hover: hover) and (pointer: fine)').matches);
    }

    function cursorMagnetFor(node) {
        if (!node || node.nodeType !== 1) return null;
        if (!node.closest) return null;
        // Never adsorb over the map or its controls.
        if (node.closest('#map, .maplibregl-map, .maplibregl-control-container')) return null;
        var el = node.closest(CURSOR_MAGNET_SEL);
        return el || null;
    }

    // A focused text field yields to the native I-beam (spec: adsorb on hover,
    // release to the caret while typing, re-adsorb on blur).
    function cursorIsYieldingField(el) {
        if (!el) return false;
        if (el !== document.activeElement) return false;
        var tag = el.tagName;
        return tag === 'INPUT' || tag === 'TEXTAREA';
    }

    function initRoundedCursor() {
        if (CURSOR.started || !roundedCursorEnabled()) return;
        CURSOR.started = CURSOR.active = true;
        CURSOR.reduced = prefersReducedMotion();

        var arrow = document.createElement('div');
        arrow.className = 'sp-cursor-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        // v1.4.7 (item 7): iPadOS-style soft round dot — the element itself is a
        // theme-tinted translucent circle (styled in CSS), no sharp SVG arrow.
        // Its hotspot is the centre; on hover the dot fades and the adsorb ring
        // morphs over the target (unchanged).

        var ring = document.createElement('div');
        ring.className = 'sp-cursor-ring';
        ring.setAttribute('aria-hidden', 'true');

        document.body.appendChild(ring);
        document.body.appendChild(arrow);
        document.body.classList.add('sp-rounded-cursor-on'); // hides the OS cursor
        CURSOR.arrow = arrow;
        CURSOR.ring = ring;

        document.addEventListener('pointermove', onCursorMove, { passive: true });
        document.addEventListener('pointerover', onCursorOver, { passive: true });
        document.addEventListener('pointerout', onCursorOut, { passive: true });
        document.addEventListener('focusin', onCursorFocus);
        document.addEventListener('focusout', onCursorFocus);
        window.addEventListener('blur', function () { setCursorVisible(false); });
        // Re-measure the adsorbed target if the layout shifts under it.
        window.addEventListener('scroll', function () { if (CURSOR.target) measureCursorTarget(); }, true);
        window.addEventListener('resize', function () { if (CURSOR.target) measureCursorTarget(); });

        CURSOR.raf = requestAnimationFrame(cursorLoop);
    }

    function setCursorVisible(v) {
        if (CURSOR.visible === v) return;
        CURSOR.visible = v;
        if (CURSOR.arrow) CURSOR.arrow.classList.toggle('is-on', v);
        if (!v) releaseCursorTarget();
    }

    function onCursorMove(e) {
        CURSOR.px = e.clientX;
        CURSOR.py = e.clientY;
        if (!CURSOR.visible) {
            // First real move: drop the follower straight onto the pointer.
            CURSOR.ax = e.clientX; CURSOR.ay = e.clientY;
            setCursorVisible(true);
        }
    }

    function onCursorOver(e) {
        var el = cursorMagnetFor(e.target);
        if (!el) return;
        if (cursorIsYieldingField(el)) { setCursorFieldYield(true); return; }
        adsorbCursorTarget(el);
    }

    function onCursorOut(e) {
        // Leaving the whole window.
        if (!e.relatedTarget && !e.toElement) { releaseCursorTarget(); setCursorFieldYield(false); return; }
        var from = cursorMagnetFor(e.target);
        var to = cursorMagnetFor(e.relatedTarget);
        if (from && from !== to) {
            if (CURSOR.target === from) releaseCursorTarget();
            if (cursorIsYieldingField(from)) setCursorFieldYield(false);
        }
    }

    function onCursorFocus() {
        // A field gaining focus under the pointer should yield; losing it should
        // re-adsorb if the pointer is still over a target.
        var el = document.elementFromPoint(CURSOR.px, CURSOR.py);
        var magnet = cursorMagnetFor(el);
        if (magnet && cursorIsYieldingField(magnet)) { setCursorFieldYield(true); releaseCursorTarget(); }
        else { setCursorFieldYield(false); if (magnet) adsorbCursorTarget(magnet); }
    }

    function setCursorFieldYield(on) {
        if (CURSOR.overFocusedInput === on) return;
        CURSOR.overFocusedInput = on;
        // While yielding, hide the follower entirely so the CSS caret shows.
        if (CURSOR.arrow) CURSOR.arrow.classList.toggle('is-hidden', on);
        if (CURSOR.ring) CURSOR.ring.classList.toggle('is-hidden', on);
    }

    // Briefly transition the ring so it eases (grows from the cursor / releases
    // back to it) — then drop the transition so per-frame tracking stays instant.
    // Skipped under reduced-motion (instant snap, no lag/stretch).
    function pulseCursorMorph() {
        var ring = CURSOR.ring;
        if (!ring || CURSOR.reduced) return;
        ring.classList.add('is-morphing');
        clearTimeout(CURSOR._morphT);
        CURSOR._morphT = setTimeout(function () { ring.classList.remove('is-morphing'); }, 340);
    }

    function adsorbCursorTarget(el) {
        if (CURSOR.target === el) return;
        releaseCursorTarget();
        CURSOR.target = el;
        measureCursorTarget();
        var r = CURSOR.rect;
        CURSOR.drift = !!r && Math.max(r.width, r.height) <= CURSOR_BIG && !CURSOR.reduced;
        pulseCursorMorph();
        CURSOR.ring.classList.add('is-adsorbed');
        if (CURSOR.arrow) CURSOR.arrow.classList.add('is-faded');
        el.classList.add('sp-magnet-active');
    }

    function measureCursorTarget() {
        var el = CURSOR.target;
        if (!el) return;
        CURSOR.rect = el.getBoundingClientRect();
        var cs = window.getComputedStyle(el);
        CURSOR.radius = cs.borderRadius && cs.borderRadius !== '0px' ? cs.borderRadius : '10px';
    }

    function releaseCursorTarget() {
        var el = CURSOR.target;
        if (!el) return;
        el.classList.remove('sp-magnet-active');
        el.style.transform = '';   // clear any parallax drift we applied
        CURSOR.target = null;
        CURSOR.rect = null;
        CURSOR.drift = false;
        pulseCursorMorph();        // ease the ring back to the cursor (release lag)
        if (CURSOR.ring) CURSOR.ring.classList.remove('is-adsorbed');
        if (CURSOR.arrow) CURSOR.arrow.classList.remove('is-faded');
    }

    function cursorLoop() {
        CURSOR.raf = requestAnimationFrame(cursorLoop);
        var arrow = CURSOR.arrow, ring = CURSOR.ring;
        if (!arrow || !ring) return;

        // Arrow follows the pointer (with lag unless reduced-motion).
        if (CURSOR.reduced) { CURSOR.ax = CURSOR.px; CURSOR.ay = CURSOR.py; }
        else {
            CURSOR.ax += (CURSOR.px - CURSOR.ax) * CURSOR_LAG;
            CURSOR.ay += (CURSOR.py - CURSOR.ay) * CURSOR_LAG;
        }
        arrow.style.transform = 'translate(' + CURSOR.ax + 'px,' + CURSOR.ay + 'px)';

        if (CURSOR.target && CURSOR.rect) {
            var r = CURSOR.rect;
            var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
            var dx = 0, dy = 0;
            if (CURSOR.drift) {
                // Lean toward the pointer within a small cap → the sticky feel.
                dx = Math.max(-CURSOR_DRIFT_MAX, Math.min(CURSOR_DRIFT_MAX, (CURSOR.px - cx) * 0.18));
                dy = Math.max(-CURSOR_DRIFT_MAX, Math.min(CURSOR_DRIFT_MAX, (CURSOR.py - cy) * 0.18));
                CURSOR.target.style.transform = 'translate(' + (dx * 0.5) + 'px,' + (dy * 0.5) + 'px)';
            }
            ring.style.width = r.width + 'px';
            ring.style.height = r.height + 'px';
            ring.style.borderRadius = CURSOR.radius;
            ring.style.transform = 'translate(' + (r.left + dx) + 'px,' + (r.top + dy) + 'px)';
        } else {
            // Idle: a tiny dot parked at the arrow tip (hidden), ready to grow.
            ring.style.width = '10px';
            ring.style.height = '10px';
            ring.style.transform = 'translate(' + (CURSOR.ax - 5) + 'px,' + (CURSOR.ay - 5) + 'px)';
        }
    }

    // ---------------------------------------------------------------
    // 20b. Site visit beacon (v1.4.8) — one count per browser per day, no IP.
    // ---------------------------------------------------------------
    function recordSiteVisit() {
        var today = new Date();
        var key = 'sp_visited_' + today.getFullYear() + ('0' + (today.getMonth() + 1)).slice(-2) + ('0' + today.getDate()).slice(-2);
        try { if (window.localStorage.getItem(key)) return; } catch (e) {}
        try { window.localStorage.setItem(key, '1'); } catch (e) {}
        fetch(CONFIG.restBase + '/sphotography/v1/visit', {
            method: 'POST',
            headers: { 'X-WP-Nonce': (APP.restNonce || '') },
            credentials: 'same-origin'
        }).then(function (r) { return r.ok ? r.json() : null; })
          .then(function (data) {
            if (data && state.siteStats) {
                if (typeof data.today === 'number') state.siteStats.visitsToday = data.today;
                if (typeof data.total === 'number') state.siteStats.visitsTotal = data.total;
            }
        }).catch(function () {});
    }

    // ---------------------------------------------------------------
    // 20c. Sidebar expand-page (v1.4.8 item 2) — a large article-list card at the
    // article-panel footprint, top layer, with a rich stats panel that unfurls the
    // profile row upward. Masonry list ↔ single article via the real #article-panel
    // (reused render pipeline) sliding in from the right.
    // ---------------------------------------------------------------
    var EXPAND = { built: false, open: false, items: [], rendered: 0, chunk: 12, query: '', uptimeTimer: null };

    function initExpandPage() {
        var btn = dom.sidebarExpandPageBtn, page = dom.expandPage;
        if (!btn || !page) return;
        btn.addEventListener('click', function (e) { e.stopPropagation(); toggleExpandPage(); });
        var closeBtn = document.getElementById('expand-page-close');
        if (closeBtn) closeBtn.addEventListener('click', function (e) { e.stopPropagation(); closeExpandPage(); });
        // Clicks inside the page must not bubble to the outside-close handler.
        page.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
        var search = document.getElementById('expand-page-search');
        if (search) {
            search.addEventListener('input', debounce(function () {
                EXPAND.query = (search.value || '').trim().toLowerCase();
                resetExpandGrid();
            }, 160));
        }
        var grid = document.getElementById('expand-page-grid');
        if (grid) {
            grid.addEventListener('scroll', function () {
                if (grid.scrollTop + grid.clientHeight >= grid.scrollHeight - 240) renderExpandChunk();
            });
        }
        EXPAND.built = true;
    }

    function toggleExpandPage() { if (EXPAND.open) closeExpandPage(); else openExpandPage(); }

    function openExpandPage() {
        if (EXPAND.open) return;
        // Mutually exclusive with the simple profile panel.
        if (state._closeSidebarProfile) state._closeSidebarProfile();
        // v1.4.9 (item 6): global exclusivity — a normal (sidebar-opened) article and the
        // expand-page must never coexist. Close any open article first.
        if (state.articleOpen && !state.expandArticleMode) closeArticlePanel();
        if (!state.sidebarOpen) openSidebar();
        EXPAND.open = true;
        var page = dom.expandPage, btn = dom.sidebarExpandPageBtn;
        page.setAttribute('aria-hidden', 'false');
        // rAF so the transition plays from the hidden state.
        requestAnimationFrame(function () { page.classList.add('is-open'); });
        if (btn) btn.setAttribute('aria-expanded', 'true');
        openStatsPanel();
        // Column count from the backend setting; forced to 1 on mobile via CSS.
        var grid = document.getElementById('expand-page-grid');
        if (grid) grid.classList.toggle('cols-3', String(SETTINGS.expandColumns) === '3');
        resetExpandGrid();
        bindExpandDismiss();
    }

    function closeExpandPage() {
        if (!EXPAND.open) return;
        EXPAND.open = false;
        // Close an in-expand article too, if one is open.
        if (state.expandArticleMode && state.articleOpen) closeArticlePanel();
        var page = dom.expandPage, btn = dom.sidebarExpandPageBtn;
        page.classList.remove('is-open');
        page.setAttribute('aria-hidden', 'true');
        if (btn) btn.setAttribute('aria-expanded', 'false');
        closeStatsPanel();
        unbindExpandDismiss();
    }

    function expandOutsideHandler(e) {
        if (dom.expandPage && dom.expandPage.contains(e.target)) return;
        if (dom.sidebarExpandPageBtn && dom.sidebarExpandPageBtn.contains(e.target)) return;
        // When an in-expand article is open, its chrome is reparented/mounted on <body>
        // OUTSIDE #expand-page — the reparented article panel plus the body-mounted nav
        // overlay (close/back button, scroll buttons), TOC bar and expanded TOC region.
        // A click on any of these is part of the article view, NOT an outside click, so
        // it must not close the whole expand-page. (This handler runs on pointerdown in
        // the CAPTURE phase, so the elements' own bubble-phase stopPropagation can't stop
        // it — we have to whitelist them here.) The close/back button then falls through
        // to its own handler, which pops back to the list instead of closing the page.
        if (state.expandArticleMode) {
            if (dom.articlePanel && dom.articlePanel.contains(e.target)) return;
            if (dom.articleNavOverlay && dom.articleNavOverlay.contains(e.target)) return;
            if (dom.articleTocBar && dom.articleTocBar.contains(e.target)) return;
            if (dom.articleTocRegion && dom.articleTocRegion.contains(e.target)) return;
        }
        closeExpandPage();
    }
    // Capture-phase + stopPropagation so the global Esc handler doesn't ALSO fire
    // (which would close the whole page on the same keypress that closes an article).
    function expandEscHandler(e) {
        if (e.key !== 'Escape' && e.key !== 'Esc') return;
        e.stopPropagation();
        if (state.expandArticleMode && state.articleOpen) { closeArticlePanel(); }
        else { closeExpandPage(); }
    }
    function bindExpandDismiss() {
        setTimeout(function () {
            document.addEventListener('pointerdown', expandOutsideHandler, true);
            document.addEventListener('keydown', expandEscHandler, true);
        }, 0);
    }
    function unbindExpandDismiss() {
        document.removeEventListener('pointerdown', expandOutsideHandler, true);
        document.removeEventListener('keydown', expandEscHandler, true);
    }

    // ---- Masonry article list ----
    function expandPostMatches(post, q) {
        if (!q) return true;
        var hay = (post.title && post.title.rendered ? post.title.rendered : '') + ' ';
        hay += stripHtml((post.excerpt && post.excerpt.rendered) || '') + ' ';
        var emb = post._embedded && post._embedded['wp:term'];
        if (Array.isArray(emb)) emb.forEach(function (grp) { if (Array.isArray(grp)) grp.forEach(function (t2) { if (t2 && t2.name) hay += t2.name + ' '; }); });
        return hay.toLowerCase().indexOf(q) !== -1;
    }

    function resetExpandGrid() {
        var grid = document.getElementById('expand-page-grid');
        var empty = document.getElementById('expand-page-empty');
        if (!grid) return;
        grid.innerHTML = '';
        EXPAND.rendered = 0;
        EXPAND.items = (state.allPosts || []).filter(function (p) { return expandPostMatches(p, EXPAND.query); });
        if (empty) empty.hidden = EXPAND.items.length > 0;
        grid.scrollTop = 0;
        renderExpandChunk();
    }

    function renderExpandChunk() {
        var grid = document.getElementById('expand-page-grid');
        if (!grid || EXPAND.rendered >= EXPAND.items.length) return;
        var end = Math.min(EXPAND.rendered + EXPAND.chunk, EXPAND.items.length);
        for (var i = EXPAND.rendered; i < end; i++) grid.appendChild(buildExpandCard(EXPAND.items[i]));
        EXPAND.rendered = end;
        // Keep filling while the grid isn't tall enough to scroll (so the rest of a
        // short-but-many list still appears without a scroll event to trigger it).
        if (EXPAND.rendered < EXPAND.items.length && grid.scrollHeight <= grid.clientHeight + 8) {
            requestAnimationFrame(renderExpandChunk);
        }
    }

    function buildExpandCard(post) {
        var coverUrl = post.sp_cover || '';
        var hasCover = !!coverUrl;
        var card = document.createElement('button');
        card.type = 'button';
        card.className = 'expand-card' + (hasCover ? ' has-cover' : ' expand-card--plain');
        card.dataset.postId = post.id;
        var dateStr = post.date ? formatDate(post.date.split('T')[0]) : '';
        var wc = getPostWordCount(post);
        var metaExtra = (wc != null) ? '<span>' + formatCount(wc) + ' 字</span>' : '';
        var titleText = stripHtml((post.title && post.title.rendered) || '').trim();
        var excerptText = cardExcerptText(post);

        // v1.4.9: card height follows the excerpt exactly — no min-height heuristic and
        // no line-clamp. The full excerpt is shown; the card is as tall as its content
        // (title + excerpt + meta), and the blurred cover backdrop fills that height.
        var excerptHtml = excerptText
            ? '<div class="expand-card-excerpt">' + escapeHtml(excerptText) + '</div>'
            : '';

        card.innerHTML = ''
            + (hasCover ? '<div class="expand-card-cover"></div><div class="expand-card-scrim"></div>' : '')
            + '<div class="expand-card-body">'
            + '<div class="expand-card-title">' + escapeHtml(titleText) + '</div>'
            + excerptHtml
            + '<div class="expand-card-meta"><span>' + escapeHtml(dateStr) + '</span>' + metaExtra + '</div>'
            + '</div>';
        if (hasCover) {
            var coverEl = card.querySelector('.expand-card-cover');
            if (coverEl) coverEl.style.backgroundImage = 'url("' + String(coverUrl).replace(/"/g, '%22') + '")';
        }
        card.addEventListener('click', function (e) { e.stopPropagation(); openArticleInExpandPage(post.id); });
        return card;
    }

    // ---- In-expand article view (v1.4.9 item 6): reparent the real #article-panel
    // into the expand-page's article screen and push-slide to it. Mutually exclusive
    // with the list screen; the animation happens INSIDE the expand-page container. ----
    function openArticleInExpandPage(postId) {
        var slot = document.getElementById('expand-page-article-slot');
        if (!slot) return;
        state.expandArticleMode = true;
        dom.articlePanel.classList.add('article-panel--in-screen');
        // v1.4.9: the close button becomes a "back to list" affordance in this mode.
        if (dom.articleClose) {
            dom.articleClose.classList.add('is-back');
            dom.articleClose.setAttribute('aria-label', t('返回列表'));
            dom.articleClose.title = t('返回列表');
        }
        slot.appendChild(dom.articlePanel);              // reparent into screen B
        document.body.classList.add('expand-article-open'); // lift nav overlay above the page
        dom.expandPage.classList.add('showing-article');  // list slides left / article slides in
        openArticle(postId);                              // full render pipeline
        // Re-sync the body-mounted nav overlay + TOC to the panel rect after the slide.
        setTimeout(function () {
            if (typeof syncArticleNavGeom === 'function') syncArticleNavGeom();
            if (dom.articleTocBar && typeof layoutToc === 'function') layoutToc(true);
        }, 460);
    }

    // Pop back to the list screen: slide screens back, then move the panel home.
    function popExpandArticle() {
        var page = dom.expandPage, panel = dom.articlePanel;
        page.classList.remove('showing-article');
        // Restore the close button to its normal ✕ (destructive-close) form.
        if (dom.articleClose) {
            dom.articleClose.classList.remove('is-back');
            dom.articleClose.setAttribute('aria-label', t('关闭文章'));
            dom.articleClose.title = '';
        }
        setTimeout(function () {
            panel.classList.remove('article-panel--in-screen', 'active');
            document.body.appendChild(panel);            // move #article-panel back to body
            document.body.classList.remove('expand-article-open');
            state.expandArticleMode = false;
        }, 460);
    }

    // ---- Rich stats panel ----
    function openStatsPanel() {
        var wrap = dom.sidebarProfile, panel = dom.sidebarStatsPanel;
        if (!wrap || !panel) return;
        renderStatsPanel();
        wrap.classList.add('stats-open');
        panel.setAttribute('aria-hidden', 'false');
        // v1.4.9: the panel ALWAYS fills the whole sidebar area above the profile bar
        // (from ~12px below the sidebar's inner top down to the bar), regardless of how
        // much content there is — the inner is a full-height flex column whose top region
        // scrolls and whose links footer stays pinned to the bottom. No content-height cap.
        var inner = panel.querySelector('.stats-panel-inner');
        var sidebar = document.getElementById('sidebar');
        var bar = wrap.querySelector('.sidebar-profile-bar');
        var target = 320;
        if (sidebar && bar) {
            target = Math.max(160, bar.getBoundingClientRect().bottom - (sidebar.getBoundingClientRect().top + 12));
        }
        panel.style.maxHeight = target + 'px';
        if (inner) inner.style.height = target + 'px';
    }
    function closeStatsPanel() {
        var wrap = dom.sidebarProfile, panel = dom.sidebarStatsPanel;
        if (!wrap || !panel) return;
        wrap.classList.remove('stats-open');
        panel.setAttribute('aria-hidden', 'true');
        panel.style.maxHeight = '';
        var inner = panel.querySelector('.stats-panel-inner');
        if (inner) { inner.style.maxHeight = ''; inner.style.height = ''; }
        if (EXPAND.uptimeTimer) { clearInterval(EXPAND.uptimeTimer); EXPAND.uptimeTimer = null; }
    }

    function renderStatsPanel() {
        var panel = dom.sidebarStatsPanel;
        if (!panel) return;
        var s = state.siteStats || {};
        // Avatar + name cloned from the server-rendered profile row.
        var avatarSrc = '', initial = '';
        var srcAvatar = dom.sidebarProfile.querySelector('.sidebar-profile-avatar');
        var srcName = dom.sidebarProfile.querySelector('.sidebar-profile-name');
        var name = srcName ? srcName.textContent : '';
        if (srcAvatar && srcAvatar.tagName === 'IMG') avatarSrc = srcAvatar.getAttribute('src') || '';
        else if (srcAvatar) initial = srcAvatar.textContent || '';

        var avatarHtml = avatarSrc
            ? '<img class="stats-avatar" src="' + escapeHtml(avatarSrc) + '" alt="">'
            : '<span class="stats-avatar stats-avatar--placeholder">' + escapeHtml(initial || (name.charAt(0) || '?')) + '</span>';

        // Bio + external links are cloned from the server-rendered simple profile
        // panel (already escaped / URL-validated in PHP). Each is omitted if absent.
        var srcPanel = dom.sidebarProfilePanel;
        var bioEl = srcPanel ? srcPanel.querySelector('.profile-expand-bio') : null;
        var bioText = bioEl ? (bioEl.textContent || '').trim() : '';
        var linksEl = srcPanel ? srcPanel.querySelector('.profile-expand-links') : null;
        var linksHtml = (linksEl && linksEl.children.length) ? linksEl.innerHTML : '';
        var creditTemplate = document.getElementById('sidebar-theme-credit');
        var creditHtml = creditTemplate ? creditTemplate.innerHTML : '';

        // Layout: a top-anchored scrolling region (current stats, unchanged
        // proportions, with bio inserted under the name) + a sticky links footer
        // pinned to the panel bottom. The footer is a non-scrolling flex sibling,
        // so it stays visible even when the region above overflows.
        var html = ''
            + '<div class="stats-panel-inner">'
            +   '<div class="stats-scroll">'
            +     avatarHtml
            +     '<div class="stats-name">' + escapeHtml(name) + '</div>'
            +     (bioText ? '<div class="profile-expand-bio stats-bio">' + escapeHtml(bioText) + '</div>' : '')
            +     '<div class="stats-counts">'
            +       '<div class="stats-count-cell"><span class="stats-count-num">' + formatCount(s.posts || 0) + '</span><span class="stats-count-label">' + escapeHtml(t('文章')) + '</span></div>'
            +       '<span class="stats-count-sep"></span>'
            +       '<div class="stats-count-cell"><span class="stats-count-num">' + formatCount(s.tags || 0) + '</span><span class="stats-count-label">' + escapeHtml(t('标签')) + '</span></div>'
            +       '<span class="stats-count-sep"></span>'
            +       '<div class="stats-count-cell"><span class="stats-count-num">' + formatCount(s.regions || 0) + '</span><span class="stats-count-label">' + escapeHtml(t('地块')) + '</span></div>'
            +     '</div>'
            +     '<div class="stats-row"><span class="stats-row-label">' + escapeHtml(t('图片张数')) + '</span><span class="stats-row-value">' + formatCount(s.photos || 0) + '</span></div>'
            +     '<div class="stats-pie-wrap" id="stats-pie-wrap"></div>'
            +     '<div class="stats-row"><span class="stats-row-label">' + escapeHtml(t('本日访问')) + '</span><span class="stats-row-value">' + formatCount(s.visitsToday || 0) + '</span></div>'
            +     '<div class="stats-row"><span class="stats-row-label">' + escapeHtml(t('累计访问')) + '</span><span class="stats-row-value">' + formatCount(s.visitsTotal || 0) + '</span></div>'
            +     '<div class="stats-row"><span class="stats-row-label">' + escapeHtml(t('已运行')) + '</span><span class="stats-row-value stats-uptime-value" id="stats-uptime">—</span></div>'
            +     creditHtml
            +   '</div>'
            +   (linksHtml ? '<div class="stats-links-footer"><div class="profile-expand-links">' + linksHtml + '</div></div>' : '')
            + '</div>';
        panel.innerHTML = html;

        buildRegionPie(document.getElementById('stats-pie-wrap'));
        startUptimeTicker(s.installTime, s.serverTime);
    }

    function startUptimeTicker(installTime, serverTime) {
        if (EXPAND.uptimeTimer) { clearInterval(EXPAND.uptimeTimer); EXPAND.uptimeTimer = null; }
        var el = document.getElementById('stats-uptime');
        if (!el || !installTime) return;
        // Base elapsed seconds at fetch time, advanced by the local clock so it ticks live.
        var baseElapsed = Math.max(0, (serverTime || (Date.now() / 1000)) - installTime);
        var t0 = Date.now();
        function paint() {
            var secs = Math.floor(baseElapsed + (Date.now() - t0) / 1000);
            var d = Math.floor(secs / 86400);
            var h = Math.floor((secs % 86400) / 3600);
            var m = Math.floor((secs % 3600) / 60);
            var sec = secs % 60;
            el.textContent = d + ' ' + t('天') + ' ' + ('0' + h).slice(-2) + ':' + ('0' + m).slice(-2) + ':' + ('0' + sec).slice(-2);
        }
        paint();
        EXPAND.uptimeTimer = setInterval(paint, 1000);
    }

    // Photo-by-region distribution (province or city per granularity), computed
    // client-side from the loaded photos + boundary names. SVG donut, top-8 + 其他,
    // revealed with a full counter-clockwise rotation.
    function buildRegionPie(wrap) {
        if (!wrap) return;
        var feats = (state.allPhotos && state.allPhotos.features) ? state.allPhotos.features : [];
        var counts = {};
        feats.forEach(function (f) {
            var id = regionIdForPhoto(f.properties);
            if (id) counts[id] = (counts[id] || 0) + 1;
        });
        // adcode → display name from the boundary features.
        var nameById = {};
        (REGION.geo.features || []).forEach(function (f) {
            if (f.properties && f.properties.id != null) nameById[String(f.properties.id)] = f.properties.name || String(f.properties.id);
        });
        var entries = Object.keys(counts).map(function (id) { return { id: id, name: nameById[id] || id, count: counts[id] }; });
        if (!entries.length) { wrap.innerHTML = '<div class="stats-pie-empty">' + escapeHtml(t('暂无地区数据')) + '</div>'; return; }
        entries.sort(function (a, b) { return b.count - a.count; });
        var TOP = 8;
        var shown = entries.slice(0, TOP);
        if (entries.length > TOP) {
            var rest = entries.slice(TOP).reduce(function (n, e) { return n + e.count; }, 0);
            if (rest > 0) shown.push({ id: '__other__', name: t('其他'), count: rest, other: true });
        }
        var total = shown.reduce(function (n, e) { return n + e.count; }, 0) || 1;

        // Donut via stroke-dasharray on stacked circles.
        var R = 45, C = 2 * Math.PI * R, cx = 60, cy = 60, sw = 22;
        var offset = 0, segs = '', legend = '';
        shown.forEach(function (e, i) {
            var color = e.other ? 'var(--text-muted)' : pieColor(i, shown.length);
            var frac = e.count / total;
            var len = frac * C;
            segs += '<circle cx="' + cx + '" cy="' + cy + '" r="' + R + '" fill="none" stroke="' + color + '" stroke-width="' + sw + '" '
                 + 'stroke-dasharray="' + len.toFixed(2) + ' ' + (C - len).toFixed(2) + '" stroke-dashoffset="' + (-offset).toFixed(2) + '"></circle>';
            offset += len;
            legend += '<span class="stats-legend-item"><span class="stats-legend-dot" style="background:' + color + '"></span>' + escapeHtml(e.name) + ' ' + e.count + '</span>';
        });
        wrap.innerHTML = ''
            + '<svg class="stats-pie is-revealing" viewBox="0 0 120 120" role="img" aria-label="' + escAttr(t('图片地区分布')) + '">'
            + '<g transform="rotate(-90 60 60)">' + segs + '</g>'
            + '</svg>'
            + '<div class="stats-legend">' + legend + '</div>';
    }

    // Evenly-spread, cohesive palette for pie slices.
    function pieColor(i, n) {
        var hue = Math.round((i * 360 / Math.max(1, n) + 8) % 360);
        return 'hsl(' + hue + ', 62%, 55%)';
    }

    function loadSiteStats() {
        fetch(CONFIG.restBase + '/sphotography/v1/stats', { credentials: 'same-origin' })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (data) {
                if (data && typeof data === 'object') {
                    state.siteStats = data;
                    // If the stats panel is already open, refresh it with live numbers.
                    if (EXPAND.open) renderStatsPanel();
                }
            }).catch(function () {});
    }

    // ---------------------------------------------------------------
    // 21. Main Init
    // ---------------------------------------------------------------
    async function init() {
        cacheDom();
        // v1.4.5 (item 7): both self-scrolling panels' close buttons are now pinned
        // structurally (article close relocated into the fixed nav overlay; detail
        // sheet made non-scrolling with .detail-content as the scroller), so the
        // v1.4.4 JS re-pin (pinScrollingPanelClose) is retired — no more scroll
        // jitter, and the article close is no longer blurred by the top frost band.
        startPreloader();

        var hasInlineData = useInlineData();

        try {
            if (!hasInlineData) {
                var photosData = await fetchMarkers();
                if (photosData && Array.isArray(photosData) && photosData.length > 0) {
                    state.allPhotos = buildGeoJSONFromMarkers(photosData);
                }

                var postsData = await fetchPosts();
                if (postsData && Array.isArray(postsData)) {
                    state.allPosts = postsData;
                    state.recentPosts = postsData;
                }
            }

            renderSidebarPosts(state.recentPosts);
            buildFilterChips();
            buildLegend();
            if (REGION.active) buildRegionData();
            initMap();
            initHitokoto();
            initEntryAnimation();
            bindUIEvents();
            initProfileExpand();
            initExpandPage();     // v1.4.8 (item 2): 边栏展开页 + 圆形按钮 + 统计面板
            loadSiteStats();      // v1.4.8: 拉取统计（访问人数/运行时间/汇总）
            recordSiteVisit();    // v1.4.8: 记一次访问（每浏览器每日一次）
            initPageLinks();
            maybeAutoOpenAnnouncement(); // v1.4.4 (item 6): 默认展开公告（可后台关闭 / 用户关闭后记忆）
            // Sidebar defaults to collapsed unless the "default expand sidebar"
            // setting is enabled.
            if (SETTINGS.sidebarDefaultOpen) {
                openSidebar();
            } else {
                closeSidebar(true);
            }

            // v1.5.0 (fix): 分享链接直达单篇文章时自动打开对应文章。
            // 仅使用服务端 PHP 通过 Sphotography.currentPostId 注入的
            // 权威文章 ID —— WordPress 的 is_singular('post') 正确解析了
            // 当前请求，JS 不再自行从 URL 猜测（否则主页 URL 若携带 ?p= 之类的
            // 查询参数会被误判为文章，触发「文章加载失败」界面）。
            // 必须等 init 完成（地图、数据、事件都就绪）之后再打开，
            // 这样 animateWindowsOpen 的 fallback（无源卡片直接显示面板）
            // 与评论/分享/阅读量等子模块都能正常工作。
            if (APP.currentPostId) {
                openArticle(APP.currentPostId, { deepLink: true });
            }
        } catch (err) {
            console.error('Init error:', err);
            hideLoading();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
