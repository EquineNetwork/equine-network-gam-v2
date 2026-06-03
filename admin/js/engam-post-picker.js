/**
 * EngamPostPicker — dynamic multi-select for posts/pages/categories.
 *
 * Usage (posts/pages):
 *   engamPostPicker({ wrap:'#w', name:'field[]', selected:[{id,title,type}], types:['post','page'] })
 *
 * Usage (categories — searches terms, stores slugs):
 *   engamPostPicker({ wrap:'#w', name:'field[]', selected:[{id,title,type}],
 *                     action:'engam_v2_search_terms', taxonomy:'category', placeholder:'Search categories…' })
 *
 * IDs are treated as strings throughout so both numeric post IDs and category
 * slugs work without coercion.
 */
(function() {
    window.engamPostPicker = function(opts) {
        var wrap        = document.querySelector(opts.wrap);
        var fieldName   = opts.name;
        var selected    = opts.selected || [];   // [{id, title, type}]
        var types       = opts.types || ['post', 'page'];
        var action      = opts.action || 'engam_v2_search_posts';
        var taxonomy    = opts.taxonomy || '';
        var placeholder = opts.placeholder || 'Search posts & pages…';
        var ajaxUrl     = (window.engamV2 && window.engamV2.ajaxUrl) || '';
        var nonce       = (window.engamV2 && window.engamV2.nonce)   || '';

        if (!wrap) return;

        var timer    = null;
        var items    = selected.slice();  // working copy

        wrap.innerHTML = '';
        wrap.style.cssText = 'position:relative';

        // --- chips container ---
        var chipsEl = document.createElement('div');
        chipsEl.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;min-height:0';

        // --- search input ---
        var inputEl = document.createElement('input');
        inputEl.type = 'text';
        inputEl.className = 'eg-input';
        inputEl.placeholder = placeholder;
        inputEl.autocomplete = 'off';
        inputEl.style.cssText = 'width:100%';

        // --- dropdown ---
        var dropEl = document.createElement('div');
        dropEl.style.cssText = 'display:none;position:absolute;top:100%;left:0;right:0;z-index:9999;'
            + 'background:#fff;border:1px solid #deded8;border-top:none;border-radius:0 0 6px 6px;'
            + 'max-height:220px;overflow-y:auto;box-shadow:0 4px 12px rgba(0,0,0,.1)';

        // --- hidden inputs container ---
        var hiddenEl = document.createElement('div');

        wrap.appendChild(chipsEl);
        wrap.appendChild(inputEl);
        wrap.appendChild(dropEl);
        wrap.appendChild(hiddenEl);

        function renderChips() {
            chipsEl.innerHTML = '';
            hiddenEl.innerHTML = '';
            items.forEach(function(item) {
                // chip
                var chip = document.createElement('span');
                chip.style.cssText = 'display:inline-flex;align-items:center;gap:4px;background:#1e1e2d;color:#e2e8f0;'
                    + 'border-radius:20px;padding:3px 10px 3px 10px;font-size:12px;font-weight:600;white-space:nowrap';
                var typeLabel = item.type === 'page' ? 'PG' : ( item.type === 'category' ? 'CAT' : ( item.type === 'post' ? 'P' : String(item.type || '').toUpperCase().slice(0,3) ) );
                chip.innerHTML = '<span style="opacity:.6;font-size:10px">' + typeLabel + '</span> '
                    + escHtml(item.title)
                    + '<button type="button" data-id="' + item.id + '" '
                    + 'style="background:none;border:none;cursor:pointer;color:#e2e8f0;font-size:14px;line-height:1;padding:0 0 0 4px;opacity:.7">&times;</button>';
                chipsEl.appendChild(chip);

                // hidden input
                var h = document.createElement('input');
                h.type  = 'hidden';
                h.name  = fieldName;
                h.value = item.id;
                hiddenEl.appendChild(h);
            });

            // remove buttons
            chipsEl.querySelectorAll('button[data-id]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var rid = btn.getAttribute('data-id');
                    items = items.filter(function(i) { return String(i.id) !== String(rid); });
                    renderChips();
                });
            });

            // Show hint if empty
            if (!items.length) {
                chipsEl.style.display = 'none';
            } else {
                chipsEl.style.display = 'flex';
            }
        }

        function escHtml(s) {
            return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }

        function showDrop(results) {
            if (!results.length) {
                dropEl.innerHTML = '<div style="padding:10px 12px;color:#888;font-size:13px">No results</div>';
            } else {
                dropEl.innerHTML = results.map(function(r) {
                    var alreadySelected = items.some(function(i) { return String(i.id) === String(r.id); });
                    var style = alreadySelected ? 'background:#f5f5ee;' : '';
                    return '<div class="engam-pp-opt" data-id="' + r.id + '" data-title="' + escHtml(r.title)
                        + '" data-type="' + escHtml(r.type)
                        + '" style="padding:8px 12px;cursor:pointer;border-bottom:1px solid #f0f0ea;font-size:13px;' + style + '">'
                        + '<strong>' + escHtml(r.title) + '</strong>'
                        + ' <span style="color:#888;font-size:11px">' + escHtml(r.type) + ' #' + r.id + '</span>'
                        + (alreadySelected ? ' <span style="color:#22c55e;font-size:11px">✓</span>' : '')
                        + '</div>';
                }).join('');
            }
            dropEl.style.display = 'block';
        }

        function hideDrop() {
            dropEl.style.display = 'none';
        }

        function doSearch(q) {
            if (!ajaxUrl) return;
            var url = ajaxUrl + '?action=' + encodeURIComponent(action) + '&nonce=' + encodeURIComponent(nonce)
                + '&q=' + encodeURIComponent(q);
            if (taxonomy) {
                url += '&taxonomy=' + encodeURIComponent(taxonomy);
            } else {
                url += types.map(function(t){ return '&types[]=' + encodeURIComponent(t); }).join('');
            }
            fetch(url).then(function(r){ return r.json(); }).then(function(data) {
                if (data.success) showDrop(data.data);
            }).catch(function(){});
        }

        inputEl.addEventListener('input', function() {
            clearTimeout(timer);
            var q = this.value.trim();
            if (!q) { hideDrop(); return; }
            timer = setTimeout(function() { doSearch(q); }, 250);
        });

        inputEl.addEventListener('focus', function() {
            if (this.value.trim()) doSearch(this.value.trim());
        });

        dropEl.addEventListener('mousedown', function(e) {
            var opt = e.target.closest('.engam-pp-opt');
            if (!opt) return;
            e.preventDefault();
            var id    = opt.dataset.id;
            var title = opt.dataset.title;
            var type  = opt.dataset.type;
            if (!items.some(function(i){ return String(i.id) === String(id); })) {
                items.push({ id: id, title: title, type: type });
                renderChips();
            }
            inputEl.value = '';
            hideDrop();
            inputEl.focus();
        });

        document.addEventListener('click', function(e) {
            if (!wrap.contains(e.target)) hideDrop();
        });

        renderChips();
    };
})();
