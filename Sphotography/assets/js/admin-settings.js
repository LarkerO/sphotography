/**
 * Sphotography admin settings interactions.
 *
 * Kept outside PHP so JavaScript quotes cannot break theme loading.
 */
(function ($) {
    'use strict';

    var config = window.SphotographyAdmin || {};

    function debounce(fn, delay) {
        var t = null;
        return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, delay); };
    }

    // v1.5.0 (fix): 版本比较不再硬编码只比较前 3 段。主题可能发布 `1.4.91`、
    // `1.4.9.1` 这类「段数不限」的版本号（把一个小版本作为一个额外的数字段），
    // 旧实现 `for (i < 3)` 会忽略第 4 段及以后，导致此类版本被误判为「没有更新」。
    // 新实现按`.`拆分为数字段，**补齐到较长的段数**再逐段比较（缺段视为 0），
    // 因此任意长度版本号都能正确比较：1.4.9.1 > 1.4.9、1.4.91 > 1.4.9、
    // 1.5.0 > 1.4.91 均成立。
    function semverGreater(a, b) {
        var pa = String(a || '').split('.');
        var pb = String(b || '').split('.');
        var len = Math.max(pa.length, pb.length);
        for (var i = 0; i < len; i++) {
            var na = parseInt(pa[i], 10) || 0;
            var nb = parseInt(pb[i], 10) || 0;
            if (na > nb) return true;
            if (na < nb) return false;
        }
        return false;
    }

    // Shared across handlers below: flip the settings form to "dirty" so the
    // unsaved-changes guard knows there is something worth warning about.
    var markDirty = function () {};

    $(function () {
        // ----- Unsaved-changes guard (v1.3.5) -----
        // Once any option on the theme settings page is touched, warn before the
        // user leaves without saving — via a confirm() on in-admin link clicks
        // (menu, row actions, etc.) and the browser's native beforeunload prompt
        // for tab close / refresh / back-forward. Saving (or resetting) clears
        // the guard so the submit itself never triggers it.
        var $settingsForm = $('#sphotography-settings-form');
        if ($settingsForm.length) {
            var formDirty = false;
            var formLeaving = false;
            markDirty = function () { formDirty = true; };

            $settingsForm.on('input change', ':input', markDirty);
            $settingsForm.on('submit', function () { formLeaving = true; });
            $('#sphotography-reset-form').on('submit', function () { formLeaving = true; });

            window.addEventListener('beforeunload', function (e) {
                if (formDirty && !formLeaving) {
                    e.preventDefault();
                    e.returnValue = '';
                    return '';
                }
            });

            // Capture-phase so we intercept before WordPress's own link handlers.
            document.addEventListener('click', function (e) {
                if (!formDirty || formLeaving) return;
                var el = e.target;
                var a = (el && el.closest) ? el.closest('a[href]') : null;
                if (!a) return;
                var href = a.getAttribute('href') || '';
                if (!href || href.charAt(0) === '#' || href.toLowerCase().indexOf('javascript:') === 0) return;
                if (a.target && a.target !== '_self') return;          // opens a new tab
                if (a.classList.contains('sphotography-toc-link')) return; // in-page smooth scroll
                if (!window.confirm(config.unsavedConfirm || '有未保存的修改，确定放弃并离开吗？')) {
                    e.preventDefault();
                    e.stopImmediatePropagation();
                } else {
                    // Confirmed: allow this navigation and suppress the native
                    // beforeunload prompt so the user isn't asked twice.
                    formLeaving = true;
                }
            }, true);
        }

        // ----- Live map preview (v1.2.6): debounced iframe reload -----
        var $preview = $('#sphotography-map-preview');
        var $frame = $('#sphotography-map-preview-frame');

        function buildPreviewUrl() {
            var base = $preview.data('preview-base');
            var params = {
                sp_primary: $('.sphotography-color-picker').val(),
                sp_night: $('#sphotography-night-mode').val(),
                sp_mapregion: $('#sphotography-map-region').val(),
                sp_chinaprov: $('#sphotography-map-china-provider').val(),
                sp_chinatype: $('#sphotography-map-china-type').val(),
                sp_tdtkey: $('#sphotography-map-tianditu-key').val(),
                sp_mapstyle: $('#sphotography-map-style').val(),
                sp_mapurl: $('#sphotography-map-style-custom-url').val(),
                sp_markermode: $('#sphotography-marker-mode').val(),
                sp_cluster: $('#sphotography-cluster-radius').val(),
                sp_goo: $('#sphotography-droplet-goo-strength').val(),
                sp_granularity: $('#sphotography-region-granularity').val(),
                sp_intensity: $('#sphotography-region-intensity').val(),
                sp_cachebust: Date.now()
            };
            var sep = String(base).indexOf('?') >= 0 ? '&' : '?';
            var qs = Object.keys(params).filter(function (k) {
                return params[k] !== undefined && params[k] !== null && params[k] !== '';
            }).map(function (k) {
                return encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
            }).join('&');
            return base + sep + qs;
        }

        var reloadPreview = debounce(function () {
            if (!$preview.length) { return; }
            $preview.addClass('is-refreshing');
            $frame.attr('src', buildPreviewUrl());
        }, 350);

        if ($preview.length && $frame.length) {
            $frame.on('load', function () { $preview.removeClass('is-refreshing'); });
            // Reload when any map-related control changes.
            $('#sphotography-night-mode, #sphotography-map-region, #sphotography-map-china-provider, #sphotography-map-china-type, #sphotography-map-tianditu-key, #sphotography-map-style, #sphotography-map-style-custom-url, #sphotography-marker-mode, #sphotography-cluster-radius, #sphotography-droplet-goo-strength, #sphotography-region-granularity, #sphotography-region-intensity')
                .on('change input', reloadPreview);
            // Initial load.
            $preview.addClass('is-refreshing');
            $frame.attr('src', buildPreviewUrl());
        }

        $('.sphotography-color-picker').wpColorPicker({
            change: function () {
                $('.sphotography-preset-btn').removeClass('active');
                markDirty();
                reloadPreview();
            }
        });

        $('.sphotography-preset-btn').on('click', function () {
            var color = $(this).data('color');
            $('.sphotography-color-picker').iris('color', color).val(color);
            $('.sphotography-preset-btn').removeClass('active');
            $(this).addClass('active');
            markDirty();
            reloadPreview();
        });

        $('#sphotography-date-format').on('change', function () {
            $('.sphotography-custom-date-field').toggle($(this).val() === 'custom');
        });

        // Map region branch (global vs china) + China provider sub-fields.
        // The custom-style URL field belongs to the global branch AND only shows
        // when style = custom; the Tianditu key belongs to china + provider =
        // tianditu; the amap/tencent grey-area note shows for those providers.
        var $mapRegion = $('#sphotography-map-region');
        var $mapStyle = $('#sphotography-map-style');
        var $chinaProvider = $('#sphotography-map-china-provider');
        function applyMapBranch() {
            var region = $mapRegion.val();
            var provider = $chinaProvider.val();
            // Region-scoped fields.
            $('.sp-region-field').each(function () {
                $(this).toggle(String($(this).data('sp-region')) === region);
            });
            // Custom-style URL: global branch only, and only when style=custom.
            $('.sphotography-custom-mapstyle-field')
                .toggle(region === 'global' && $mapStyle.val() === 'custom');
            // Provider-scoped fields (Tianditu key) + provider notes.
            $('.sp-china-provider-field').each(function () {
                var providers = String($(this).data('sp-provider') || '').split(/\s+/);
                $(this).toggle(region === 'china' && providers.indexOf(provider) !== -1);
            });
            $('.sp-china-provider-note').each(function () {
                var providers = String($(this).data('sp-provider') || '').split(/\s+/);
                $(this).toggle(region === 'china' && providers.indexOf(provider) !== -1);
            });
        }
        $mapStyle.on('change', applyMapBranch);
        $mapRegion.on('change', applyMapBranch);
        $chinaProvider.on('change', applyMapBranch);
        if ($mapRegion.length) { applyMapBranch(); }

        // Marker mode (v1.2.6): show only the fields relevant to the chosen
        // mode. Each mode-specific field carries data-sp-mode="a b" listing the
        // modes it applies to.
        var $markerMode = $('#sphotography-marker-mode');
        function applyMarkerMode() {
            var mode = $markerMode.val();
            $('.sp-mode-field').each(function () {
                var modes = String($(this).data('sp-mode') || '').split(/\s+/);
                $(this).toggle(modes.indexOf(mode) !== -1);
            });
        }
        if ($markerMode.length) {
            $markerMode.on('change', applyMarkerMode);
            applyMarkerMode();
        }

        // Rebuild administrative-region index (batched, region mode).
        $('#sphotography-rebuild-geo').on('click', function () {
            var btn = $(this);
            var status = $('#sphotography-rebuild-geo-status');
            if (!config.geoRebuildNonce) { return; }
            btn.prop('disabled', true);

            function runBatch(offset) {
                $.post(config.ajaxUrl, {
                    action: 'sphotography_rebuild_geo_index',
                    nonce: config.geoRebuildNonce,
                    offset: offset
                }).done(function (res) {
                    if (!res || !res.success) {
                        status.text((res && res.data && res.data.message) || '重建失败').css('color', '#e74c3c');
                        btn.prop('disabled', false);
                        return;
                    }
                    var d = res.data;
                    status.text('处理中… ' + d.done + ' / ' + d.total).css('color', '');
                    if (d.finished) {
                        status.text('✓ 完成，已索引 ' + d.total + ' 张照片').css('color', '#2ecc71');
                        btn.prop('disabled', false);
                    } else {
                        runBatch(d.next_offset);
                    }
                }).fail(function () {
                    status.text('✗ 请求失败').css('color', '#e74c3c');
                    btn.prop('disabled', false);
                });
            }
            status.text('开始重建…').css('color', '');
            runBatch(0);
        });

        // v1.4.0: Batch backfill of EXIF (aperture / shutter / ISO) for
        // every image attachment. Mirrors the rebuild-geo pattern: paginated
        // batches of 20, skip-on-fail, status text updated after each
        // batch. On completion the server drops the wall-photos transient.
        $('#sphotography-exif-backfill').on('click', function () {
            var btn = $(this);
            var status = $('#sphotography-exif-backfill-status');
            if (!config.exifBackfillNonce) { return; }
            btn.prop('disabled', true);
            var totalNew = 0;

            function runBatch(offset) {
                $.post(config.ajaxUrl, {
                    action: 'sphotography_exif_backfill',
                    nonce: config.exifBackfillNonce,
                    offset: offset
                }).done(function (res) {
                    if (!res || !res.success) {
                        status.text((res && res.data && res.data.message) || (config.exifBackfillFail || '回填失败：')).css('color', '#e74c3c');
                        btn.prop('disabled', false);
                        return;
                    }
                    var d = res.data;
                    if (d.new_fields) { totalNew += d.new_fields; }
                    status.text('处理中… ' + d.done + ' / ' + d.total).css('color', '');
                    if (d.finished) {
                        var msg = (config.exifBackfillDone || '✓ 完成，已处理 %1$d 张照片，新提取了 %2$d 个 EXIF 字段。')
                            .replace('%1$d', d.total).replace('%2$d', totalNew);
                        status.text(msg).css('color', '#2ecc71');
                        btn.prop('disabled', false);
                    } else {
                        runBatch(d.next_offset);
                    }
                }).fail(function () {
                    status.text('✗ 请求失败').css('color', '#e74c3c');
                    btn.prop('disabled', false);
                });
            }
            status.text(config.exifBackfillRunning || '处理中…').css('color', '');
            runBatch(0);
        });

        // v1.4.6 (item 1): 一键预生成全站照片地址。走 REST，服务端只是「排入后台
        // cron 任务」（错峰 + 遵守服务限速），因此这里是一次性请求、无需分批轮询。
        $('#sphotography-geo-backfill').on('click', function () {
            var btn = $(this);
            var status = $('.sphotography-geo-backfill-status');
            if (!config.geoBackfillUrl || !config.restNonce) { return; }
            btn.prop('disabled', true);
            status.text(config.geoBackfillRunning || '正在排入后台任务…').css('color', '');
            $.ajax({
                url: config.geoBackfillUrl,
                method: 'POST',
                beforeSend: function (xhr) { xhr.setRequestHeader('X-WP-Nonce', config.restNonce); }
            }).done(function (res) {
                var n = (res && typeof res.scheduled === 'number') ? res.scheduled : 0;
                if (n > 0) {
                    status.text((config.geoBackfillDone || '✓ 已为 %d 篇文章排入后台预生成任务。').replace('%d', n)).css('color', '#2ecc71');
                } else {
                    status.text(config.geoBackfillNone || '✓ 没有需要新排期的文章。').css('color', '#2ecc71');
                }
                btn.prop('disabled', false);
            }).fail(function (xhr) {
                var msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : '';
                status.text((config.geoBackfillFail || '预生成失败：') + msg).css('color', '#e74c3c');
                btn.prop('disabled', false);
            });
        });

        // v1.4.6 (item 7): 设置项实时搜索。匹配范围 = 大板块标题 + 模块标题 +
        // 字段（标签 + 描述 + 选项文字，即 .sphotography-field 的全文）。不匹配的
        // 字段/模块/板块直接隐藏；清空则全部恢复。
        (function () {
            var input = document.getElementById('sphotography-settings-search');
            if (!input) { return; }
            function applyFilter() {
                var q = (input.value || '').trim().toLowerCase();
                var cats = document.querySelectorAll('.sphotography-settings-main .sp-cat-card');
                for (var i = 0; i < cats.length; i++) {
                    var cat = cats[i];
                    var titleEl = cat.querySelector('.sp-cat-card-title');
                    var catMatch = !!q && titleEl && titleEl.textContent.toLowerCase().indexOf(q) !== -1;
                    var mods = cat.querySelectorAll('.sphotography-module');
                    var anyModVisible = false;
                    for (var j = 0; j < mods.length; j++) {
                        var mod = mods[j];
                        var headEl = mod.querySelector('.sphotography-module-header');
                        var modMatch = !!q && headEl && headEl.textContent.toLowerCase().indexOf(q) !== -1;
                        var fields = mod.querySelectorAll('.sphotography-field');
                        var anyFieldVisible = false;
                        for (var k = 0; k < fields.length; k++) {
                            var f = fields[k];
                            var show = !q || catMatch || modMatch || f.textContent.toLowerCase().indexOf(q) !== -1;
                            f.style.display = show ? '' : 'none';
                            if (show) { anyFieldVisible = true; }
                        }
                        // A module with no .sphotography-field children still shows
                        // when the query is empty or its header/category matches.
                        var showMod = !q || catMatch || modMatch || anyFieldVisible || fields.length === 0;
                        mod.style.display = showMod ? '' : 'none';
                        if (showMod && (fields.length === 0 ? (catMatch || modMatch) : true)) { anyModVisible = true; }
                    }
                    cat.style.display = (!q || catMatch || anyModVisible) ? '' : 'none';
                }
            }
            input.addEventListener('input', applyFilter);
        })();

        // Generic slider readouts (v1.2.5): each .sphotography-slider-row pairs
        // a range input with a .sphotography-slider-val; an optional
        // data-suffix on the value element is appended to the number.
        $('.sphotography-slider-row').each(function () {
            var $range = $(this).find('input[type="range"]');
            var $val = $(this).find('.sphotography-slider-val');
            var suffix = $val.data('suffix') || '';
            $range.on('input change', function () {
                $val.text($(this).val() + suffix);
            });
        });

        // Advanced motion block: collapsible reveal.
        $('#sphotography-motion-advanced-toggle').on('click', function () {
            var expanded = $(this).attr('aria-expanded') === 'true';
            $(this).attr('aria-expanded', expanded ? 'false' : 'true');
            $('#sphotography-motion-advanced').prop('hidden', expanded);
        });

        // ----- Right-side index (TOC): smooth scroll + scrollspy -----
        // v1.4.0: TOC is now nested. Parents are buttons that toggle the
        // child list; leaves and children are links that scroll to the
        // matching section. The scroll-spy auto-expands whichever
        // category is currently in view.
        var $tocLinks = $('.sphotography-toc-link');
        var $tocGroups = $('.sphotography-toc-group');
        if ($tocLinks.length) {
            var sections = [];
            $tocLinks.each(function () {
                var id = $(this).data('target');
                var el = document.getElementById(id);
                if (el) { sections.push({ id: id, el: el }); }
            });

            // Map a section id to its parent group (if it has one) so the
            // scroll-spy can auto-expand the right parent.
            function parentGroupOf(id) {
                return $tocGroups.filter('.has-children').filter(function () {
                    return $(this).find('[data-target="' + id + '"]').length > 0
                        || $(this).children('.sphotography-toc-parent').attr('data-target') === id;
                }).first();
            }

            $tocLinks.on('click', function (e) {
                var $self = $(this);
                var id = $self.data('target');
                var el = document.getElementById(id);
                if (!el) { return; }
                e.preventDefault();
                // Expand the parent group if the click was on a child link
                // (v1.4.2 手风琴：先收起其他已展开的组)。
                var $group = $self.closest('.sphotography-toc-group');
                if ($group.length && !$group.hasClass('is-expanded')) {
                    collapseOthers($group);
                    $group.addClass('is-expanded');
                    $group.children('.sphotography-toc-parent').attr('aria-expanded', 'true');
                }
                var top = el.getBoundingClientRect().top + window.pageYOffset - 46;
                window.scrollTo({ top: top, behavior: 'smooth' });
            });

            // Click on a parent button toggles the child list. Clicking
            // ALSO scrolls to the parent anchor so the user lands on the
            // category title (the natural "head" of the section).
            // v1.4.2: 手风琴式 — 同一时间只展开一个大板块的子索引。
            var collapseOthers = function ($keep) {
                $tocGroups.filter('.is-expanded').each(function () {
                    var $g = $(this);
                    if ($keep && $g.is($keep)) { return; }
                    $g.removeClass('is-expanded');
                    $g.children('.sphotography-toc-parent').attr('aria-expanded', 'false');
                });
            };

            $('.sphotography-toc-parent').on('click', function (e) {
                e.preventDefault();
                var $btn = $(this);
                var $group = $btn.parent('.sphotography-toc-group');
                var willExpand = !$group.hasClass('is-expanded');
                if (willExpand) { collapseOthers($group); } // 手风琴：先收起其他
                $group.toggleClass('is-expanded');
                $btn.attr('aria-expanded', willExpand ? 'true' : 'false');
                if (willExpand) {
                    var id = $btn.data('target');
                    var el = document.getElementById(id);
                    if (el) {
                        var top = el.getBoundingClientRect().top + window.pageYOffset - 46;
                        window.scrollTo({ top: top, behavior: 'smooth' });
                    }
                }
            });

            var setActive = function (id) {
                $tocLinks.removeClass('active');
                $tocLinks.filter('[data-target="' + id + '"]').addClass('active');
            };

            // Auto-expand the parent group of the currently-active section
            // on every scroll-spy tick.
            var expandGroupOf = function (id) {
                var $group = parentGroupOf(id);
                if ($group.length && !$group.hasClass('is-expanded')) {
                    collapseOthers($group); // v1.4.2 手风琴：滚动进入某分类时收起其他
                    $group.addClass('is-expanded');
                    $group.children('.sphotography-toc-parent').attr('aria-expanded', 'true');
                }
            };

            var onScroll = function () {
                var probe = window.pageYOffset + 120;
                var current = sections.length ? sections[0].id : null;
                for (var i = 0; i < sections.length; i++) {
                    if (sections[i].el.offsetTop <= probe) {
                        current = sections[i].id;
                    }
                }
                if (current) {
                    setActive(current);
                    expandGroupOf(current);
                }
            };

            var scrollScheduled = false;
            $(window).on('scroll', function () {
                if (scrollScheduled) { return; }
                scrollScheduled = true;
                window.requestAnimationFrame(function () {
                    scrollScheduled = false;
                    onScroll();
                });
            });
            onScroll();
        }

        $('#sphotography-reset-btn').on('click', function () {
            if (window.confirm(config.resetConfirm || 'Reset all settings?')) {
                $('#sphotography-reset-form').trigger('submit');
            }
        });

        $('#sphotography-check-update').on('click', function () {
            var btn = $(this);
            var result = $('#sphotography-update-result');
            var status = $('#sphotography-version-status');
            btn.prop('disabled', true).text('检查中...');
            result.html('<p style="color:#718096;">正在检查更新...</p>');

            $.ajax({
                url: config.updateUrl,
                type: 'GET',
                dataType: 'json',
                timeout: 15000
            }).done(function (data) {
                var latest = data.version || '';
                var current = config.currentVersion || '';
                var html;
                if (!latest) {
                    html = '<p style="color:#e67e22;">ℹ 无法解析版本信息。</p>';
                    status.text('检查失败').css('color', '#e67e22');
                } else if (semverGreater(latest, current)) {
                    html = '<p style="color:#e67e22;font-weight:600;">★ 发现新版本: v' + latest + '</p>'
                        + '<p style="margin-top:6px;">当前版本: v' + current + '</p>'
                        + '<p style="margin-top:8px;"><a href="' + config.releaseUrl + '" target="_blank" rel="noopener" class="button button-secondary">查看 Release</a></p>';
                    status.text('有新版本: v' + latest).css('color', '#e67e22');
                    if (data.changelog) {
                        html += '<div style="margin-top:10px;padding:10px 14px;background:#f8f9fa;border-radius:8px;font-size:0.8125rem;color:#555;max-height:200px;overflow-y:auto;">'
                            + '<strong>更新说明:</strong><br>' + $('<div>').text(data.changelog).html().replace(/\n/g, '<br>') + '</div>';
                    }
                } else {
                    html = '<p style="color:#2ecc71;font-weight:600;">✓ 当前 v' + current + ' 已是最新版本</p>';
                    status.text('已是最新').css('color', '#2ecc71');
                }
                result.html(html);
            }).fail(function () {
                result.html('<p style="color:#e74c3c;">✗ 无法连接更新服务器，请前往 GitHub Releases 手动查看。</p>');
                status.text('检查失败').css('color', '#e74c3c');
            }).always(function () {
                btn.prop('disabled', false).text('检查更新');
            });
        });

        $('#sphotography-do-update').on('click', function () {
            var btn = $(this);
            var result = $('#sphotography-update-result');
            if (!window.confirm(config.updateConfirm || 'Update the theme from master?')) return;

            btn.prop('disabled', true).text('下载更新中...');
            result.html('<p style="color:#718096;">正在从 master 分支下载更新...</p>');
            $.post(config.ajaxUrl, {
                action: 'sphotography_do_update',
                branch: 'master',
                nonce: config.updateNonce
            }).done(function (response) {
                if (response.success) {
                    result.html('<p style="color:#2ecc71;font-weight:600;">✓ 更新完成！请重新激活主题以确保生效。</p>'
                        + '<p><a href="' + window.location.href + '" class="button button-primary">刷新页面</a></p>');
                } else {
                    result.html('<p style="color:#e74c3c;">✗ 更新失败: ' + $('<div>').text(response.data || '未知错误').html() + '</p>');
                }
            }).fail(function () {
                result.html('<p style="color:#e74c3c;">✗ 请求失败，请查看服务器错误日志</p>');
            }).always(function () {
                btn.prop('disabled', false).text('从 master 分支更新主题');
            });
        });

        // ----- Experimental AI: test connection (v1.2.9) -----
        $('#sphotography-ai-test').on('click', function () {
            var btn = $(this);
            var status = $('#sphotography-ai-test-status');
            btn.prop('disabled', true);
            status.text(config.aiTesting || 'Testing…').css('color', 'var(--sp-text-muted)');
            $.post(config.ajaxUrl, {
                action: 'sphotography_ai_test',
                nonce: config.aiTestNonce
            }).done(function (response) {
                if (response && response.success) {
                    var msg = (response.data && response.data.message) ? response.data.message : (config.aiTestOk || 'OK');
                    status.text('✓ ' + msg).css('color', '#2ecc71');
                } else {
                    status.text('✗ ' + (config.aiTestFail || '') + $('<div>').text((response && response.data) || '').html()).css('color', '#e05a4d');
                }
            }).fail(function () {
                status.text('✗ ' + (config.aiTestFail || '') + 'request failed').css('color', '#e05a4d');
            }).always(function () {
                btn.prop('disabled', false);
            });
        });

        // ----- Experimental AI: show/hide fields by model mode (v1.3.0) -----
        var $aiMode = $('#sphotography-ai-model-mode');
        if ($aiMode.length) {
            var syncAiMode = function () {
                var mode = $aiMode.val();
                $('.sp-ai-mode-field').each(function () {
                    var forMode = $(this).data('sp-ai-mode');
                    $(this).toggle(String(forMode) === String(mode));
                });
            };
            $aiMode.on('change', syncAiMode);
            syncAiMode();
        }
    });
})(jQuery);
