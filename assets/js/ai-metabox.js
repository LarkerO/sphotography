/**
 * Sphotography AI meta box (experimental) — v1.3.0
 *
 * Editor panel actions:
 *   - 文章补全: post text + images (+ optional keywords, style, length) → HTML,
 *     previewed then appended as native blocks.
 *   - 润色: post text + images (+ style) → HTML, previewed then replaces content.
 *   - AI 自动标签: text-only tag suggestions → chips → add to post tags.
 *
 * All model calls go through admin-ajax; keys stay server-side. Images are sent
 * as {id,url} references; PHP reads/downscales/base64s them.
 */
(function ($) {
    'use strict';

    var cfg = window.SphotographyAI || {};
    var i18n = cfg.i18n || {};

    // ---- Editor bridges (block editor + classic) ----
    function hasBlockEditor() {
        return !!(window.wp && wp.data && wp.data.select('core/block-editor'));
    }

    function getTitle() {
        try {
            if (window.wp && wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostAttribute('title') || '';
            }
        } catch (e) {}
        var el = document.getElementById('title');
        return el ? el.value : '';
    }

    function getContentHtml() {
        try {
            if (window.wp && wp.data && wp.data.select('core/editor')) {
                return wp.data.select('core/editor').getEditedPostAttribute('content') || '';
            }
        } catch (e) {}
        if (window.tinymce) {
            var ed = window.tinymce.get('content');
            if (ed && !ed.isHidden()) { return ed.getContent(); }
        }
        var ta = document.getElementById('content');
        return ta ? ta.value : '';
    }

    function getContentText() {
        var html = getContentHtml();
        var div = document.createElement('div');
        div.innerHTML = html;
        return (div.textContent || div.innerText || '').replace(/\s+/g, ' ').trim();
    }

    // Gather images referenced in the post as {id,url}.
    function getImages() {
        var acc = [];
        if (hasBlockEditor()) {
            var walk = function (blocks) {
                (blocks || []).forEach(function (b) {
                    if (b.name === 'core/image') {
                        acc.push({ id: b.attributes.id || 0, url: b.attributes.url || '' });
                    }
                    if (b.innerBlocks && b.innerBlocks.length) { walk(b.innerBlocks); }
                });
            };
            try { walk(wp.data.select('core/block-editor').getBlocks()); } catch (e) {}
        }
        if (!acc.length) {
            // Classic editor / fallback: parse <img> from the content HTML.
            var div = document.createElement('div');
            div.innerHTML = getContentHtml();
            div.querySelectorAll('img').forEach(function (img) {
                var id = 0;
                var m = (img.className || '').match(/wp-image-(\d+)/);
                if (m) { id = parseInt(m[1], 10); }
                acc.push({ id: id, url: img.getAttribute('src') || '' });
            });
        }
        // De-dupe and cap.
        var seen = {}, out = [];
        acc.forEach(function (im) {
            var key = (im.id || 0) + '|' + (im.url || '');
            if (!seen[key] && (im.id || im.url)) { seen[key] = 1; out.push(im); }
        });
        return out.slice(0, cfg.maxImages || 6);
    }

    var TEXT_BLOCKS = ['core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/preformatted', 'core/pullquote'];
    function isTextBlock(name) { return TEXT_BLOCKS.indexOf(name) >= 0; }

    // Insert HTML into the editor. mode: 'append' | 'replace'.
    // For 'replace' (polish) in the block editor, non-text blocks (images,
    // galleries, embeds…) are PRESERVED — only text blocks are rewritten — so
    // polishing never silently deletes the post's media.
    function applyHtml(html, mode) {
        if (hasBlockEditor() && wp.blocks && wp.blocks.rawHandler) {
            var blocks = wp.blocks.rawHandler({ HTML: html });
            if (!blocks || !blocks.length) { return false; }
            var dispatch = wp.data.dispatch('core/block-editor');
            if (mode === 'replace') {
                var existing = wp.data.select('core/block-editor').getBlocks();
                var result = [];
                var inserted = false;
                existing.forEach(function (b) {
                    if (isTextBlock(b.name)) {
                        if (!inserted) { result = result.concat(blocks); inserted = true; }
                        // drop the old text block
                    } else {
                        result.push(b); // keep media / other blocks
                    }
                });
                if (!inserted) { result = result.concat(blocks); }
                dispatch.resetBlocks(result);
            } else {
                var all = wp.data.select('core/block-editor').getBlocks();
                dispatch.insertBlocks(blocks, all.length);
            }
            return true;
        }
        // Classic editor / textarea.
        if (window.tinymce) {
            var ed = window.tinymce.get('content');
            if (ed && !ed.isHidden()) {
                ed.setContent(mode === 'replace' ? html : (ed.getContent() + '\n' + html));
                ed.save();
                return true;
            }
        }
        var ta = document.getElementById('content');
        if (ta) {
            ta.value = mode === 'replace' ? html : (ta.value + (ta.value ? '\n\n' : '') + html);
            return true;
        }
        return false;
    }

    function addPostTag(tag) {
        var $field = $('#new-tag-post_tag');
        if ($field.length) {
            var $add = $('.tagadd').first();
            $field.val(tag);
            if ($add.length) { $add.trigger('click'); return true; }
        }
        try {
            if (window.wp && wp.data && wp.data.dispatch('core/editor')) {
                var current = wp.data.select('core/editor').getEditedPostAttribute('tags') || [];
                return wp.apiFetch({ path: '/wp/v2/tags', method: 'POST', data: { name: tag } })
                    .then(function (t) {
                        wp.data.dispatch('core/editor').editPost({ tags: current.concat([t.id]) });
                        return true;
                    })
                    .catch(function () {
                        return wp.apiFetch({ path: '/wp/v2/tags?search=' + encodeURIComponent(tag) }).then(function (list) {
                            if (list && list.length) {
                                wp.data.dispatch('core/editor').editPost({ tags: current.concat([list[0].id]) });
                                return true;
                            }
                            return false;
                        });
                    });
            }
        } catch (e) {}
        return false;
    }

    function setStatus(msg, isError) {
        $('#sphotography-ai-status').text(msg || '').css('color', isError ? '#e05a4d' : '#757575');
    }

    // Show a note when images exist but analysis is off.
    function refreshImageNote() {
        var $note = $('#sphotography-ai-imgnote');
        if (!$note.length) { return; }
        if (cfg.imageActive) { $note.prop('hidden', true).text(''); return; }
        var n = getImages().length;
        if (n > 0 && i18n.imgNoteOff) {
            $note.prop('hidden', false).text(i18n.imgNoteOff.replace('%d', n));
        } else {
            $note.prop('hidden', true).text('');
        }
    }

    // ================================================================
    // Main-editor AI review overlay (v1.3.6)
    //
    // Instead of previewing in the little sidebar box, 补全/润色 open a review
    // overlay centred over the editor: the AI text is typewritten in a distinct
    // "AI ink" colour, 润色 shows a side-by-side 原文|润色 with word-level diff
    // highlighting, and only「确认/应用」commits into the editor (via applyHtml).
    // Editor-agnostic — works identically for the block and classic editors.
    // ================================================================
    var overlay = null, overlayEls = null, typeTimer = null;

    // Plain text from an HTML fragment, preserving paragraph/line breaks.
    function textFromHtml(html) {
        var s = String(html || '');
        s = s.replace(/<\s*br\s*\/?>/gi, '\n');
        s = s.replace(/<\/(p|div|h[1-6]|li|blockquote|ul|ol|pre)>/gi, '\n\n');
        var div = document.createElement('div');
        div.innerHTML = s;
        var text = div.textContent || div.innerText || '';
        return text.replace(/\r/g, '').replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
    }

    function buildOverlay() {
        if (overlay) { return overlayEls; }
        overlay = document.createElement('div');
        overlay.className = 'sp-ai-overlay';
        overlay.innerHTML =
            '<div class="sp-ai-review" role="dialog" aria-modal="true">' +
                '<div class="sp-ai-review-head"><h2></h2>' +
                    '<button type="button" class="sp-ai-review-close" aria-label="' + (i18n.close || '关闭') + '">×</button>' +
                '</div>' +
                '<div class="sp-ai-review-body"></div>' +
                '<div class="sp-ai-review-foot"></div>' +
            '</div>';
        document.body.appendChild(overlay);
        overlayEls = {
            root: overlay,
            title: overlay.querySelector('.sp-ai-review-head h2'),
            body: overlay.querySelector('.sp-ai-review-body'),
            foot: overlay.querySelector('.sp-ai-review-foot'),
            closeBtn: overlay.querySelector('.sp-ai-review-close')
        };
        overlayEls.closeBtn.addEventListener('click', function () { closeOverlay(); });
        overlay.addEventListener('click', function (e) { if (e.target === overlay) { closeOverlay(); } });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('is-open')) { closeOverlay(); }
        });
        return overlayEls;
    }

    function openOverlay() {
        buildOverlay();
        requestAnimationFrame(function () { overlay.classList.add('is-open'); });
    }

    function closeOverlay() {
        if (typeTimer) { clearTimeout(typeTimer); typeTimer = null; }
        if (overlay) { overlay.classList.remove('is-open'); }
    }

    function setFooter(primaryLabel, onPrimary) {
        var f = overlayEls.foot;
        f.innerHTML = '';
        var discard = document.createElement('button');
        discard.type = 'button';
        discard.className = 'button';
        discard.textContent = i18n.discard || '放弃';
        discard.addEventListener('click', function () { closeOverlay(); setStatus(i18n.discarded || '已放弃', false); });
        var primary = document.createElement('button');
        primary.type = 'button';
        primary.className = 'button button-primary';
        primary.textContent = primaryLabel;
        primary.addEventListener('click', onPrimary);
        f.appendChild(discard);
        f.appendChild(primary);
    }

    // Client-side typewriter into a plain-text element. Chunks steps so long
    // outputs finish in a few seconds rather than crawling char-by-char.
    function typewrite(el, text, done) {
        if (typeTimer) { clearTimeout(typeTimer); typeTimer = null; }
        var chars = Array.from(String(text || ''));
        var n = chars.length;
        var chunk = Math.max(1, Math.ceil(n / 320));
        var i = 0;
        el.textContent = '';
        el.classList.add('sp-ai-caret');
        function tick() {
            i = Math.min(n, i + chunk);
            el.textContent = chars.slice(0, i).join('');
            if (i < n) { typeTimer = setTimeout(tick, 14); }
            else { el.classList.remove('sp-ai-caret'); typeTimer = null; if (done) { done(); } }
        }
        typeTimer = setTimeout(tick, 120);
    }

    // Tokenise into diff units: individual CJK chars, Latin/number words,
    // whitespace runs, and single punctuation.
    function tokenize(s) {
        var re = /[㐀-鿿豈-﫿぀-ヿ가-힯]|[A-Za-z0-9]+|\s+|[^\sA-Za-z0-9]/g;
        return String(s || '').match(re) || [];
    }

    // LCS word-level diff → {before:[{t,c}], after:[{t,c}]} where c=1 marks a
    // changed token. Returns null when the inputs are too large to diff cheaply.
    function diffTokens(a, b) {
        var n = a.length, m = b.length;
        if (n * m > 1000000) { return null; }
        var dp = [];
        for (var x = 0; x <= n; x++) { dp[x] = new Int32Array(m + 1); }
        for (var i = n - 1; i >= 0; i--) {
            for (var j = m - 1; j >= 0; j--) {
                dp[i][j] = (a[i] === b[j]) ? dp[i + 1][j + 1] + 1 : Math.max(dp[i + 1][j], dp[i][j + 1]);
            }
        }
        var before = [], after = [];
        var p = 0, q = 0;
        while (p < n && q < m) {
            if (a[p] === b[q]) { before.push({ t: a[p], c: 0 }); after.push({ t: b[q], c: 0 }); p++; q++; }
            else if (dp[p + 1][q] >= dp[p][q + 1]) { before.push({ t: a[p], c: 1 }); p++; }
            else { after.push({ t: b[q], c: 1 }); q++; }
        }
        while (p < n) { before.push({ t: a[p], c: 1 }); p++; }
        while (q < m) { after.push({ t: b[q], c: 1 }); q++; }
        return { before: before, after: after };
    }

    // Render diff parts into an element (text via textContent — never innerHTML).
    function renderDiff(el, parts, cls) {
        el.textContent = '';
        parts.forEach(function (part) {
            if (part.c) {
                var span = document.createElement('span');
                span.className = cls;
                span.textContent = part.t;
                el.appendChild(span);
            } else {
                el.appendChild(document.createTextNode(part.t));
            }
        });
    }

    // Open the overlay for a 文章补全 result (single pane, all AI ink).
    function reviewComplete(html) {
        buildOverlay();
        overlayEls.title.textContent = i18n.reviewCompleteTitle || 'AI 补全预览';
        overlayEls.body.classList.remove('is-split');
        overlayEls.body.innerHTML =
            '<p class="sp-ai-pane-title">' + (i18n.paneAfter || '新增内容') + '</p>' +
            '<div class="sp-ai-pane sp-ai-ink" id="sp-ai-pane-complete"></div>';
        var pane = overlayEls.body.querySelector('#sp-ai-pane-complete');
        setFooter(i18n.confirmInsert || '确认插入', function () {
            if (applyHtml(html, 'append')) { setStatus(i18n.inserted, false); }
            closeOverlay();
        });
        openOverlay();
        typewrite(pane, textFromHtml(html));
    }

    // Open the overlay for a 润色 result (side-by-side 原文|润色 + diff highlight).
    function reviewPolish(originalHtml, polishedHtml) {
        buildOverlay();
        overlayEls.title.textContent = i18n.reviewPolishTitle || 'AI 润色对比';
        overlayEls.body.classList.add('is-split');
        overlayEls.body.innerHTML =
            '<div><p class="sp-ai-pane-title">' + (i18n.paneBefore || '原文') + '</p>' +
                '<div class="sp-ai-pane" id="sp-ai-pane-before"></div></div>' +
            '<div><p class="sp-ai-pane-title">' + (i18n.paneAfter || '润色') + '</p>' +
                '<div class="sp-ai-pane sp-ai-ink" id="sp-ai-pane-after"></div></div>';
        var beforeEl = overlayEls.body.querySelector('#sp-ai-pane-before');
        var afterEl = overlayEls.body.querySelector('#sp-ai-pane-after');
        var beforeText = textFromHtml(originalHtml);
        var afterText = textFromHtml(polishedHtml);
        beforeEl.textContent = beforeText;
        setFooter(i18n.applyPolish || '应用润色', function () {
            if (applyHtml(polishedHtml, 'replace')) { setStatus(i18n.applied, false); }
            closeOverlay();
        });
        openOverlay();
        // Typewriter the polished side, then reveal word-level diff highlights.
        typewrite(afterEl, afterText, function () {
            var d = diffTokens(tokenize(beforeText), tokenize(afterText));
            if (d) {
                renderDiff(beforeEl, d.before, 'sp-ai-diff-del');
                renderDiff(afterEl, d.after, 'sp-ai-diff-add');
            }
        });
    }

    $(function () {
        if (!$('#sphotography-ai-box').length) { return; }

        // Length only affects 补全; make that explicit.
        $('#sphotography-ai-length-hint').prop('hidden', false)
            .text('长度仅影响「文章补全」，润色保持原篇幅。');

        refreshImageNote();

        // ----- 文章补全 → main-editor review overlay -----
        $('#sphotography-ai-complete-btn').on('click', function () {
            var btn = $(this);
            var kw = $('#sphotography-ai-keywords').val() || '';
            var text = getContentText();
            var images = getImages();
            if (!text && !kw.trim() && !images.length) {
                setStatus(i18n.noContent, true);
                return;
            }
            btn.prop('disabled', true);
            setStatus(i18n.working, false);
            $.post(cfg.ajaxUrl, {
                action: 'sphotography_ai_complete',
                nonce: cfg.nonce,
                text: getContentHtml(),
                keywords: kw,
                style: $('#sphotography-ai-style').val(),
                length: $('#sphotography-ai-length').val(),
                images: images
            }).done(function (res) {
                if (res && res.success) {
                    setStatus('', false);
                    reviewComplete(res.data.html || '');
                } else {
                    setStatus(i18n.error + ((res && res.data) || ''), true);
                }
            }).fail(function () {
                setStatus(i18n.error + 'request failed', true);
            }).always(function () {
                btn.prop('disabled', false);
            });
        });

        // ----- 润色 → main-editor side-by-side review overlay -----
        $('#sphotography-ai-polish-btn').on('click', function () {
            var btn = $(this);
            var originalHtml = getContentHtml();
            if (!getContentText()) { setStatus(i18n.noPolish, true); return; }
            btn.prop('disabled', true);
            setStatus(i18n.working, false);
            $.post(cfg.ajaxUrl, {
                action: 'sphotography_ai_polish',
                nonce: cfg.nonce,
                text: originalHtml,
                style: $('#sphotography-ai-style').val(),
                images: getImages()
            }).done(function (res) {
                if (res && res.success) {
                    setStatus('', false);
                    reviewPolish(originalHtml, res.data.html || '');
                } else {
                    setStatus(i18n.error + ((res && res.data) || ''), true);
                }
            }).fail(function () {
                setStatus(i18n.error + 'request failed', true);
            }).always(function () {
                btn.prop('disabled', false);
            });
        });

        // ----- AI 全文概述: manual (re)generate -----
        $('#sphotography-ai-summary-btn').on('click', function () {
            var btn = $(this);
            var $prev = $('#sphotography-ai-summary-preview');
            var text = getContentHtml();
            if (!getContentText() && !cfg.postId) { setStatus(i18n.noPolish, true); return; }
            btn.prop('disabled', true);
            setStatus(i18n.summaryWorking, false);
            $.post(cfg.ajaxUrl, {
                action: 'sphotography_ai_summary',
                nonce: cfg.nonce,
                post_id: cfg.postId || 0,
                title: getTitle(),
                text: text
            }).done(function (res) {
                if (res && res.success && res.data.summary) {
                    $prev.prop('hidden', false).text('');
                    typewrite($prev.get(0), res.data.summary, function () { setStatus(i18n.summaryDone, false); });
                } else {
                    setStatus(i18n.error + ((res && res.data) || ''), true);
                }
            }).fail(function () {
                setStatus(i18n.error + 'request failed', true);
            }).always(function () {
                btn.prop('disabled', false);
            });
        });

        // ----- AI 自动标签 -----
        $('#sphotography-ai-tags-btn').on('click', function () {
            var btn = $(this);
            var body = getContentText();
            if (body.length < 20 && !getTitle()) { setStatus(i18n.noBody, true); return; }
            btn.prop('disabled', true);
            setStatus(i18n.tagWorking, false);
            $.post(cfg.ajaxUrl, {
                action: 'sphotography_ai_tags',
                nonce: cfg.nonce,
                content: body,
                title: getTitle()
            }).done(function (res) {
                if (res && res.success && res.data.tags) {
                    renderTagChips(res.data.tags);
                    setStatus('', false);
                } else {
                    setStatus(i18n.error + ((res && res.data) || ''), true);
                }
            }).fail(function () {
                setStatus(i18n.error + 'request failed', true);
            }).always(function () {
                btn.prop('disabled', false);
            });
        });

        function renderTagChips(tags) {
            var $wrap = $('#sphotography-ai-tags-result').empty();
            tags.forEach(function (tag) {
                var $chip = $('<button type="button" class="sphotography-ai-chip"></button>').text('+ ' + tag);
                $chip.on('click', function () {
                    var ok = addPostTag(tag);
                    if (ok && typeof ok.then === 'function') {
                        ok.then(function () { markAdded($chip); });
                    } else if (ok) {
                        markAdded($chip);
                    }
                });
                $wrap.append($chip);
            });
        }

        function markAdded($chip) {
            $chip.addClass('is-added').prop('disabled', true);
            setStatus(i18n.tagAdded, false);
        }
    });
})(jQuery);
