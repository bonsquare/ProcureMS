{{--
    Page-by-page layout for a long official table (SIP, SOB, APP, APP-CSE, and the PR, PO, RFQ, IAR, RIS, Abstract and Delivery Reconciliation when
    their item list runs long). Included after partials.print-clean by a document that has data-sheets on its page element (the print engine adds it
    by itself to a form that no longer fits one page) and data-paginate on its long table (one in each form page).
    - A table directly inside the page element (SIP, SOB, APP, APP-CSE): everything before it (letterhead, title) stays on the first page and everything
      after it (summary, signatures, notes) goes under the last rows.
    - A table inside a form page (a section of the page element): that form page is the first page, and every page after it gets a copy of the form's boxes
      around its piece of the table; what follows the table inside the form goes under the last rows. Form pages without a long table stay one page each.
    - Several long tables directly inside one form page (Delivery Reconciliation): the form's parts and tables are laid out in order, page after page;
      a heading stays with the first rows of the table it introduces.
    - The rows that fit one printed page go in one `.doc-page` block: the screen shows each block as its own sheet, the print breaks after each.
      Every page after the first gets its own copy of the heading rows (the table's thead, or a row marked data-repeat-head), and a cell merged down several
      rows (rowspan) restarts at the top of the next page.
    - A table marked data-split-merged (SIP) also shares the words of a text cell merged down several rows between the pages it is cut across, by the height
      of the rows on each page, so a group of rows can start near the bottom of a page.
    - A table marked data-split-rows (SIP, AIP) also flows its text across a page break: a row that does not fit the room left on the page is split in
      two rows, the first keeps the words that fit in each cell, the second carries the rest to the next page; both keep every border of the cells.
    - A page element marked data-page-footer="Title" gets a footer in every page block (Page X of Y in the middle, the title on the right) and keeps
      two lines of room at the bottom of each page for it; the document's CSS places it (.doc-footer).
    - The document's CSS must be the same on screen and in print (no @media print size rules), so the rows are measured the way they print.
    - The paper (@page) comes from the shared print engine; this script only reads it.
--}}
<style>
    /* The procurement forms carry their own padding above and below the form page. The sheet already keeps the paper margin, so that padding only made the room of every page smaller
       (and the blank at the bottom longer) and sent the last box of a form to the next page: left and right padding stay. */
    :is([data-doc="pr"],[data-doc="rfq"],[data-doc="po"],[data-doc="ntp"],[data-doc="noa"],[data-doc="abstract"],[data-doc="iar"],[data-doc="ris"],[data-doc="iars"],[data-doc="ics"],[data-doc="reconciliation"],[data-doc="generic"])[data-sheets] > :not(.doc-page), :is([data-doc="pr"],[data-doc="rfq"],[data-doc="po"],[data-doc="ntp"],[data-doc="noa"],[data-doc="abstract"],[data-doc="iar"],[data-doc="ris"],[data-doc="iars"],[data-doc="ics"],[data-doc="reconciliation"],[data-doc="generic"])[data-sheets] > .doc-page > :not([data-doc-footer]) { padding-top: 3mm !important; padding-bottom: 0 !important; }
    /* On screen the form page was a white card with a shadow and its own 16px margin inside the white sheet: it looked like a second sheet over the first. */
    :is([data-doc="pr"],[data-doc="rfq"],[data-doc="po"],[data-doc="ntp"],[data-doc="noa"],[data-doc="abstract"],[data-doc="iar"],[data-doc="ris"],[data-doc="iars"],[data-doc="ics"],[data-doc="reconciliation"],[data-doc="generic"])[data-sheets] > :not(.doc-page), :is([data-doc="pr"],[data-doc="rfq"],[data-doc="po"],[data-doc="ntp"],[data-doc="noa"],[data-doc="abstract"],[data-doc="iar"],[data-doc="ris"],[data-doc="iars"],[data-doc="ics"],[data-doc="reconciliation"],[data-doc="generic"])[data-sheets] > .doc-page > :not([data-doc-footer]) { box-shadow: none !important; margin-top: 0 !important; margin-bottom: 0 !important; }
    [data-fit-scale] > .doc-page { width: fit-content; margin-left: auto !important; margin-right: auto !important; }
    /* Footer of a page that has data-page-footer: the page number in the middle, the title on the right, at the bottom of the printable area. */
    .doc-footer { position: absolute; left: var(--sheet-pl, 0); right: var(--sheet-pr, 0); bottom: var(--sheet-pb, 0); display: grid; grid-template-columns: 1fr auto 1fr; align-items: baseline; column-gap: 12px; padding-top: 3px; border-top: .5pt solid #7b8794; font-size: var(--doc-footer-size, 8pt); letter-spacing: .02em; color: #1f2933; line-height: 1.25; }
    .doc-footer .center { text-align: center; font-weight: 700; font-variant-numeric: tabular-nums; white-space: nowrap; } .doc-footer .right { text-align: right; color: #3e4c59; overflow-wrap: anywhere; }
    @media print {
        html .is-sheets .doc-page { height: var(--doc-page-h, auto) !important; min-height: 0 !important; overflow: visible; display: flow-root; }
        .doc-footer { left: 0; right: 0; bottom: 0; }
    }
</style>
<script>
    (function () {
        var MM = 96 / 25.4;
        var busy = false, laidOutFor = null;
        // The procurement forms may carry a tail (signatures, inspection box) that is up to 10% too tall for the room left: the page is then scaled to fit instead of sending the tail alone to a page of its own.
        var SCALABLE = ['pr', 'rfq', 'po', 'ntp', 'noa', 'abstract', 'iar', 'ris', 'iars', 'ics', 'reconciliation', 'generic'];
        var tailRoom = 1, overAllow = 0;

        function siblingsAfter(node) {
            var list = [];
            for (var next = node.nextElementSibling; next; next = next.nextElementSibling) { list.push(next); }
            return list;
        }

        function tablesOf(unit) { return unit.flow ? unit.tables.map(function (entry) { return entry.table; }) : [unit.table]; }

        // The long tables and the boxes between the page and each of them. Read once, from the page as the server wrote it.
        function unitsOf(page) {
            if (page._units) { return page._units; }
            var all = Array.prototype.slice.call(page.querySelectorAll('table[data-paginate]'));
            var direct = all.filter(function (table) { return table.parentNode === page; });
            var seen = [];
            var counter = 0;
            var units = [];
            var levelsFor = function (table, legacy) {
                var chain = [];
                for (var node = table.parentNode; node && node !== page; node = node.parentNode) { chain.unshift(node); }
                var parents = [page].concat(chain);
                return chain.concat([table]).map(function (child, index) {
                    // Beside a form page the other form pages are not "after the table": they are handled one by one.
                    return { parent: parents[index], child: child, tail: index === 0 && !legacy ? [] : siblingsAfter(child) };
                });
            };
            if (direct.length) {
                units.push({ table: direct[0], legacy: true, levels: levelsFor(direct[0], true), index: counter++ });
            } else {
                all.forEach(function (table) {
                    var levels = levelsFor(table, false);
                    var seg = levels[0].child;
                    if (seen.indexOf(seg) !== -1) { return; }
                    seen.push(seg);
                    var inside = all.filter(function (other) { return other.parentNode === seg; });
                    if (inside.length > 1) {
                        // Several long tables side by side in one form page: laid out in order.
                        units.push({ flow: true, seg: seg, kids: Array.prototype.slice.call(seg.children), levels: [{ parent: page, child: seg, tail: [] }],
                            tables: inside.map(function (one) { return { table: one, index: counter++ }; }) });
                    } else {
                        units.push({ table: table, legacy: false, levels: levels, index: counter++ });
                    }
                });
            }
            page._byIndex = {};
            units.forEach(function (unit) {
                if (unit.flow) { unit.tables.forEach(function (entry) { page._byIndex[entry.index] = entry.table; }); } else { page._byIndex[unit.index] = unit.table; }
            });
            page._units = units;
            return units;
        }

        function restore(page, units) {
            Array.prototype.forEach.call(page.querySelectorAll('[data-doc-footer]'), function (footer) { footer.remove(); });
            Array.prototype.forEach.call(page.querySelectorAll(':scope > .doc-page'), function (block) {
                while (block.firstChild) { page.insertBefore(block.firstChild, block); }
                block.remove();
            });
            Array.prototype.forEach.call(page.querySelectorAll('table[data-chunk]'), function (extra) {
                var owner = page._byIndex[extra.getAttribute('data-chunk')];
                Array.prototype.slice.call(extra.tBodies[0].rows).forEach(function (row) { owner.tBodies[0].appendChild(row); });
                extra.remove();
            });
            units.forEach(function (unit) {
                if (unit.flow) { unit.kids.forEach(function (kid) { unit.seg.appendChild(kid); }); return; }
                // What was moved under the last rows goes back beside its box.
                unit.levels.forEach(function (level) {
                    var after = level.child;
                    level.tail.forEach(function (node) { level.parent.insertBefore(node, after.nextSibling); after = node; });
                });
            });
            Array.prototype.forEach.call(page.querySelectorAll('[data-chain]'), function (copy) { copy.remove(); });
            units.forEach(function (unit) {
                tablesOf(unit).forEach(function (table) {
                    var tbody = table.tBodies[0];
                    Array.prototype.forEach.call(tbody.querySelectorAll('[data-clone]'), function (cell) { cell.remove(); });
                    // A row split to flow its text across a page break is put back whole.
                    Array.prototype.forEach.call(tbody.querySelectorAll('tr[data-split-b]'), function (row) { row.remove(); });
                    Array.prototype.forEach.call(tbody.querySelectorAll('tr[data-split-a]'), function (row) {
                        Array.prototype.forEach.call(row.cells, function (cell, i) { cell.innerHTML = row._whole[i]; });
                        row.removeAttribute('data-split-a');
                    });
                    // A merged cell whose words were shared between pages gets all its words back.
                    Array.prototype.forEach.call(tbody.querySelectorAll('[data-split-m]'), function (cell) { cell.innerHTML = cell._whole; cell.removeAttribute('data-split-m'); });
                    Array.prototype.forEach.call(tbody.querySelectorAll('[data-rs]'), function (cell) { cell.rowSpan = +cell.dataset.rs; });
                });
            });
        }

        // Where every cell starts (row and column), so a merged cell can be cut at a page break.
        function gridOf(rows) {
            var taken = [], cells = [];
            rows.forEach(function (tr, r) {
                taken[r] = taken[r] || {};
                var col = 0;
                Array.prototype.forEach.call(tr.cells, function (cell) {
                    while (taken[r][col]) { col++; }
                    var rowSpan = cell.rowSpan, colSpan = cell.colSpan;
                    for (var i = 0; i < rowSpan; i++) {
                        taken[r + i] = taken[r + i] || {};
                        for (var j = 0; j < colSpan; j++) { taken[r + i][col + j] = true; }
                    }
                    cells.push({ el: cell, row: r, col: col, span: rowSpan });
                    col += colSpan;
                });
            });
            return cells;
        }

        // A cell merged across a page break is cut in two: the first part ends the page, a copy starts the next one.
        // With splitText (a table marked data-split-merged) the words of a text cell are shared between its pieces by the height of the rows each piece covers,
        // so the text flows on to the next page instead of every piece repeating all of it and needing room for it.
        function cutMergedCells(rows, starts, splitText) {
            var cells = gridOf(rows);
            var heights = splitText ? rows.map(function (row) { return row.getBoundingClientRect().height; }) : null;
            starts.forEach(function (k) {
                cells.slice().forEach(function (item) {
                    if (item.row >= k || item.row + item.span <= k) { return; }
                    var copy = item.el.cloneNode(true);
                    copy.setAttribute('data-clone', '');
                    copy.removeAttribute('data-rs');
                    var tokens = splitText ? tokensOf(item.el) : null;
                    if (tokens && tokens.length > 1) {
                        var upper = 0, lower = 0;
                        for (var r = item.row; r < item.row + item.span; r++) { if (r < k) { upper += heights[r]; } else { lower += heights[r]; } }
                        var count = Math.min(tokens.length - 1, Math.max(1, Math.round(tokens.length * upper / ((upper + lower) || 1))));
                        if (!item.el.hasAttribute('data-clone') && !item.el.hasAttribute('data-split-m')) { item.el._whole = item.el.innerHTML; item.el.setAttribute('data-split-m', ''); }
                        fillTokens(item.el, tokens.slice(0, count));
                        fillTokens(copy, tokens.slice(count));
                    }
                    copy.rowSpan = item.row + item.span - k;
                    item.el.rowSpan = k - item.row;
                    var entry = { el: copy, row: k, col: item.col, span: copy.rowSpan };
                    var before = cells.filter(function (other) { return other.row === k && other.col > entry.col && other.el.parentNode === rows[k]; })
                        .sort(function (a, b) { return a.col - b.col; })[0];
                    rows[k].insertBefore(copy, before ? before.el : null);
                    item.span = k - item.row;
                    cells.push(entry);
                });
            });
        }

        function outerHeight(element) {
            var css = getComputedStyle(element);
            return element.getBoundingClientRect().height + parseFloat(css.marginTop) + parseFloat(css.marginBottom);
        }

        // What the boxes around the table add above and below it on a page that holds only a piece of the table.
        // (The outermost box of a form page has its margins taken off on the sheet, so only the others count them.)
        function overhead(levels, segmented) {
            var top = 0, bottom = 0;
            levels.slice(0, -1).forEach(function (level, index) {
                var css = getComputedStyle(level.child);
                var margin = segmented && index === 0 ? [0, 0] : [parseFloat(css.marginTop), parseFloat(css.marginBottom)];
                top += parseFloat(css.paddingTop) + parseFloat(css.borderTopWidth) + margin[0];
                bottom += parseFloat(css.paddingBottom) + parseFloat(css.borderBottomWidth) + margin[1];
            });
            return { top: top, bottom: bottom };
        }

        // The words and line breaks of a cell that holds only text, or null when it holds anything else.
        function tokensOf(cell) {
            var list = [];
            for (var i = 0; i < cell.childNodes.length; i++) {
                var node = cell.childNodes[i];
                if (node.nodeType === 3) { (node.nodeValue.match(/\S+/g) || []).forEach(function (word) { list.push(word); }); } else if (node.nodeName === 'BR') { list.push(null); } else { return null; }
            }
            return list;
        }

        function fillTokens(cell, tokens) {
            cell.textContent = '';
            tokens.forEach(function (token, i) {
                if (token === null) { cell.appendChild(document.createElement('br')); return; }
                cell.appendChild(document.createTextNode((i > 0 && tokens[i - 1] !== null ? ' ' : '') + token));
            });
        }

        // Splits a row that does not fit the room left on the page into two: the first keeps the words of each cell that fit, the second (inserted
        // after it) carries the rest. Cells merged down the rows are stretched over both. False when the row cannot be split.
        function splitRow(rows, index, room) {
            var row = rows[index];
            var cells = Array.prototype.slice.call(row.cells);
            var plain = cells.filter(function (cell) { return cell.rowSpan === 1; });
            var texts = plain.map(tokensOf);
            if (!texts.some(function (tokens) { return tokens && tokens.length > 1; })) { return false; }
            if (room < parseFloat(getComputedStyle(row).fontSize) * 4) { return false; }

            var whole = cells.map(function (cell) { return cell.innerHTML; });
            var restoreAll = function () { cells.forEach(function (cell, i) { cell.innerHTML = whole[i]; }); };
            var fits = function () { return row.getBoundingClientRect().height <= room; };
            var counts = texts.map(function (tokens, i) {
                if (!tokens) { return null; }
                // The words of this cell that fit alone (the other cells empty while it is measured).
                plain.forEach(function (cell, j) { if (j !== i) { cell.textContent = ''; } });
                var low = 0, high = tokens.length;
                while (low < high) {
                    var middle = Math.ceil((low + high) / 2);
                    fillTokens(plain[i], tokens.slice(0, middle));
                    if (fits()) { low = middle; } else { high = middle - 1; }
                }
                return low;
            });
            restoreAll();
            var progress = counts.some(function (n) { return n !== null && n > 0; });
            var rest = counts.some(function (n, i) { return n !== null && n < texts[i].length; });
            if (!progress || !rest) { return false; }
            plain.forEach(function (cell, i) { if (texts[i]) { fillTokens(cell, texts[i].slice(0, counts[i])); } });
            if (!fits()) { restoreAll(); return false; }

            // The cells merged across this row now also cover the new row.
            var covering = gridOf(rows).filter(function (item) { return item.span > 1 && item.row <= index && index < item.row + item.span; });
            var next = row.cloneNode(false);
            next.removeAttribute('id');
            next.setAttribute('data-split-b', '');
            plain.forEach(function (cell, i) {
                var copy = cell.cloneNode(false);
                copy.removeAttribute('data-rs');
                if (texts[i]) { fillTokens(copy, texts[i].slice(counts[i])); }
                next.appendChild(copy);
            });
            row._whole = whole;
            row.setAttribute('data-split-a', '');
            row.parentNode.insertBefore(next, row.nextSibling);
            covering.forEach(function (item) { item.el.rowSpan = item.span + 1; });
            rows.splice(index + 1, 0, next);
            return true;
        }

        // The height a cell needs for its content alone, at its own width (a merged cell cut at a page break keeps all of it in a shorter piece).
        function naturalHeight(cell) {
            var probe = cell.cloneNode(true);
            probe.style.cssText = 'position:absolute;visibility:hidden;left:0;top:0;display:block;height:auto;box-sizing:border-box;width:' + cell.getBoundingClientRect().width + 'px';
            cell.parentNode.parentNode.parentNode.parentNode.appendChild(probe);
            var height = probe.getBoundingClientRect().height;
            probe.remove();
            return height;
        }

        // Where the pages of one table break, measured the way they print.
        function plan(page, unit, capacity, reserves) {
            var table = unit.table;
            var origin = unit.legacy ? page : unit.levels[0].child;
            var rows = Array.prototype.slice.call(table.tBodies[0].rows);
            var around = overhead(unit.levels, !unit.legacy);
            var repeated = table.tHead || table.querySelector('tr[data-repeat-head]');
            var repeatHeight = repeated ? repeated.getBoundingClientRect().height : 0;
            var position = table.getBoundingClientRect().top - origin.getBoundingClientRect().top + (table.tHead ? table.tHead.getBoundingClientRect().height : 0);
            var splitting = table.hasAttribute('data-split-rows');
            var splitMerged = table.hasAttribute('data-split-merged');
            var merged = null;
            var naturals = new Map();
            // What a page that ends at row `last` and starts at row `first` adds because the merged cells cut by its edges keep their whole text
            // in a shorter piece (the piece cannot be shorter than its text); `lastHeight` stands in for the height of the last row.
            var extraFor = function (first, last, lastHeight) {
                if (!merged) { merged = gridOf(rows).filter(function (item) { return item.span > 1; }); }
                var byRow = {};
                merged.forEach(function (item) {
                    var end = item.row + item.span - 1;
                    if (end < first || item.row > last) { return; }
                    // A text cell shares its words between the pages, so a piece never needs the whole text.
                    if (splitMerged && (tokensOf(item.el) || []).length > 1) { return; }
                    var from = Math.max(item.row, first), to = Math.min(end, last);
                    if (from === item.row && to === end) { return; }
                    var sum = 0;
                    for (var i = from; i <= to; i++) { sum += i === last && lastHeight !== undefined ? lastHeight : rows[i].getBoundingClientRect().height; }
                    if (!naturals.has(item.el)) { naturals.set(item.el, naturalHeight(item.el)); }
                    var need = naturals.get(item.el) - sum;
                    if (need > 0 && !(byRow[to] >= need)) { byRow[to] = need; }
                });
                return Object.keys(byRow).reduce(function (total, key) { return total + byRow[key]; }, 0);
            };
            var starts = [];
            var pageStart = 0;
            for (var index = 0; index < rows.length; index++) {
                var height = rows[index].getBoundingClientRect().height;
                var limit = capacity - around.bottom - ((unit.legacy && reserves[starts.length]) || 0);
                if (position + height + (splitting ? extraFor(pageStart, index) : 0) > limit) {
                    // The text flows on to the next page when the row can be split; otherwise the whole row goes there.
                    var room = limit - position;
                    if (splitting && splitRow(rows, index, room - extraFor(pageStart, index, room))) {
                        merged = null;
                        starts.push(index + 1);
                        pageStart = index + 1;
                        position = repeatHeight + around.top;
                        continue;
                    }
                    if (index > 0 && starts[starts.length - 1] !== index) { starts.push(index); pageStart = index; position = repeatHeight + around.top; }
                }
                position += height;
            }
            var tailHeight = 0;
            unit.levels.forEach(function (level) { level.tailSizes = level.tail.map(function (node) { var size = outerHeight(node); tailHeight += size; return size; }); });
            return { rows: rows, starts: starts, position: position, tailHeight: tailHeight, around: around, repeated: repeated };
        }

        // The pages of a form page that holds several long tables: its parts and tables in order, each page as full as it can be.
        function flowPlan(unit, capacity) {
            var css = getComputedStyle(unit.seg);
            var top = parseFloat(css.paddingTop) + parseFloat(css.borderTopWidth);
            var limit = capacity - parseFloat(css.paddingBottom) - parseFloat(css.borderBottomWidth);
            var items = unit.kids.map(function (kid) {
                var entry = unit.tables.filter(function (one) { return one.table === kid; })[0];
                if (!entry) { return { node: kid, height: outerHeight(kid) }; }
                var rows = Array.prototype.slice.call(kid.tBodies[0].rows);
                var tableCss = getComputedStyle(kid);
                return { entry: entry, rows: rows, heights: rows.map(function (row) { return row.getBoundingClientRect().height; }),
                    head: kid.tHead ? kid.tHead.getBoundingClientRect().height : 0, margin: parseFloat(tableCss.marginTop) + parseFloat(tableCss.marginBottom) };
            });
            var pages = [[]];
            var used = top;
            var turn = function () { pages.push([]); used = top; };
            items.forEach(function (item, index) {
                var page = function () { return pages[pages.length - 1]; };
                if (item.node) {
                    // A heading stays with the first rows of the table it introduces.
                    var next = items[index + 1];
                    var need = item.height + (next && next.entry ? next.head + next.margin + (next.heights[0] || 0) : 0);
                    if (used + need > limit && page().length) { turn(); }
                    page().push({ node: item.node });
                    used += item.height;
                    return;
                }
                if (!item.rows.length) { page().push({ entry: item.entry, from: 0, to: 0 }); used += item.head + item.margin; return; }
                var from = 0;
                while (from < item.rows.length) {
                    if (used + item.head + item.margin + item.heights[from] > limit && page().length) { turn(); }
                    used += item.head + item.margin;
                    var to = from;
                    while (to < item.rows.length && (to === from || used + item.heights[to] <= limit)) { used += item.heights[to]; to++; }
                    page().push({ entry: item.entry, from: from, to: to, rows: item.rows });
                    from = to;
                    if (from < item.rows.length) { turn(); }
                }
            });
            return { flow: true, pages: pages, rows: items.filter(function (item) { return item.entry; }) };
        }

        var chunkOf = function (entry, rows) {
            var table = entry.table;
            var chunk = table.cloneNode(false);
            chunk.removeAttribute('data-paginate');
            chunk.style.borderTop = '';
            chunk.setAttribute('data-chunk', entry.index);
            if (table.querySelector('colgroup')) { chunk.appendChild(table.querySelector('colgroup').cloneNode(true)); }
            var repeated = table.tHead || table.querySelector('tr[data-repeat-head]');
            if (table.tHead) {
                chunk.appendChild(table.tHead.cloneNode(true));
            } else if (repeated) {
                var heading = document.createElement('thead');
                var again = repeated.cloneNode(true);
                again.removeAttribute('data-repeat-head');
                heading.appendChild(again);
                chunk.appendChild(heading);
            }
            var body = document.createElement('tbody');
            rows.forEach(function (row) { body.appendChild(row); });
            chunk.appendChild(body);
            return chunk;
        };

        var copyOf = function (element) {
            var copy = element.cloneNode(false);
            copy.setAttribute('data-chain', '');
            copy.removeAttribute('id');
            return copy;
        };

        // The blocks (printed pages) of a form page with several long tables.
        function flowPagesOf(unit, made, newBlock) {
            made.rows.forEach(function (item) {
                var starts = made.pages.reduce(function (list, one) {
                    one.forEach(function (piece) { if (piece.entry === item.entry && piece.from > 0) { list.push(piece.from); } });
                    return list;
                }, []);
                cutMergedCells(item.rows, starts);
            });
            var fragment = document.createDocumentFragment();
            unit.kids.forEach(function (kid) { fragment.appendChild(kid); });
            made.pages.forEach(function (one, number) {
                var block = newBlock();
                var container = number === 0 ? unit.seg : copyOf(unit.seg);
                block.appendChild(container);
                one.forEach(function (piece) {
                    if (piece.node) { container.appendChild(piece.node); return; }
                    container.appendChild(piece.from === 0 ? piece.entry.table : chunkOf(piece.entry, piece.rows.slice(piece.from, piece.to)));
                });
            });
        }

        // The blocks (printed pages) of one table.
        function pagesOf(unit, made, capacity, newBlock) {
            var table = unit.table, levels = unit.levels;
            var head = [];
            if (unit.legacy) { for (var before = levels[0].child.previousElementSibling; before; before = before.previousElementSibling) { head.unshift(before); } }
            cutMergedCells(made.rows, made.starts, unit.table.hasAttribute('data-split-merged'));

            // The boxes of one page: the form's own for the first, copies of them for every other. The last place is the table (or nothing).
            var boxesOf = function (container, inner) {
                var elements = [];
                var parent = container;
                levels.slice(0, -1).forEach(function (level) {
                    var copy = copyOf(level.child);
                    parent.appendChild(copy);
                    elements.push(copy);
                    parent = copy;
                });
                if (inner) { parent.appendChild(inner); }
                elements.push(inner || null);
                return elements;
            };

            var first = newBlock();
            head.forEach(function (element) { first.appendChild(element); });
            first.appendChild(levels[0].child);
            var current = { container: first, elements: levels.map(function (level) { return level.child; }) };
            made.starts.forEach(function (index, number) {
                var container = newBlock();
                var chunk = chunkOf({ table: table, index: unit.index }, made.rows.slice(index, made.starts[number + 1] === undefined ? made.rows.length : made.starts[number + 1]));
                current = { container: container, elements: boxesOf(container, chunk) };
            });
            // What follows the table sits under the last rows; when it does not fit, a page of its own carries it.
            var movedTail = null, tailPartsKept = null, keptLevel = null;
            levels.forEach(function (level) { level.tail.forEach(function (node) { node.removeAttribute('data-cut-top'); node.removeAttribute('data-cut-bottom'); }); });
            if (made.tailHeight && made.position + made.tailHeight + made.around.bottom > capacity * tailRoom) {
                // A form that marks its table data-split-tail keeps the first parts of what follows under the last rows, as many as fit; the rest goes to the next page.
                var tailLevel = levels.filter(function (level) { return level.tail.length > 0; });
                if (table.hasAttribute('data-split-tail') && tailLevel.length === 1) {
                    var tailParts = tailLevel[0].tail, used = made.position + made.around.bottom, keep = 0;
                    while (keep < tailParts.length && used + tailLevel[0].tailSizes[keep] <= capacity) { used += tailLevel[0].tailSizes[keep]; keep++; }
                    if (keep > 0 && keep < tailParts.length) {
                        movedTail = tailParts.slice(keep);
                        keptLevel = tailLevel[0];
                        tailPartsKept = tailParts.slice(0, keep);
                        tailPartsKept[keep - 1].setAttribute('data-cut-bottom', '');
                        movedTail[0].setAttribute('data-cut-top', '');
                    }
                }
                if (movedTail === null) {
                    var spare = newBlock();
                    current = { container: spare, elements: boxesOf(spare, null) };
                }
            } else if (made.tailHeight && made.position + made.tailHeight + made.around.bottom > capacity) {
                // The tail is a little too tall for the room left: this page is scaled to fit it (fitFixed).
                current.container.setAttribute('data-tail-over', '');
            }
            // (On the first page the form's own boxes are still in place, with their parts beside them; only what follows a table that sits
            // directly in the page element is not inside the block yet.)
            for (var i = levels.length - 1; i >= 0; i--) {
                if (current.container === first && !(unit.legacy && i === 0)) { continue; }
                var parent = i === 0 ? current.container : current.elements[i - 1];
                (levels[i] === keptLevel ? tailPartsKept : levels[i].tail).forEach(function (node) { parent.appendChild(node); });
            }
            if (movedTail !== null) {
                var rest = newBlock();
                var restBoxes = boxesOf(rest, null);
                var tailAt = levels.indexOf(keptLevel);
                var restParent = tailAt === 0 ? rest : restBoxes[tailAt - 1];
                movedTail.forEach(function (node) { restParent.appendChild(node); });
            }
        }

        function build(page, units, spec, factor, reserves) {
            var root = document.documentElement;
            // A page that carries a footer (data-page-footer on the page element) keeps two lines of room at the bottom for it.
            var footed = page.hasAttribute('data-page-footer');
            var availableHeight = (+spec[2] - spec[3] - spec[5]) * MM - 3 - (footed ? 2 * parseFloat(getComputedStyle(root).fontSize) : 0);
            // A wide form is printed scaled down (zoom below 1): the rows are measured below at full size, so the same paper holds 1/zoom as many pixels of them.
            var printZoom = parseFloat(page.style.zoom) || 1;
            var capacity = availableHeight * factor / Math.min(1, Math.max(0.5, printZoom));

            // Measure the way the page prints: the paper inside its margins, at full size.
            var kept = { zoom: page.style.zoom, width: page.style.width, preview: root.style.getPropertyValue('--preview-zoom') };
            root.style.setProperty('--preview-zoom', 1);
            page.style.zoom = 1;
            page.style.width = ((+spec[1] - spec[4] - spec[6]) * MM) + 'px';
            var made = units.map(function (unit) { return unit.flow ? flowPlan(unit, capacity) : plan(page, unit, capacity, reserves); });
            var kids = Array.prototype.slice.call(page.children);
            page.style.zoom = kept.zoom;
            page.style.width = kept.width;
            if (kept.preview) { root.style.setProperty('--preview-zoom', kept.preview); } else { root.style.removeProperty('--preview-zoom'); }

            var blocks = [];
            var newBlock = function () { var element = document.createElement('div'); element.className = 'doc-page'; blocks.push(element); return element; };
            if (units[0].legacy) {
                pagesOf(units[0], made[0], capacity, newBlock);
            } else {
                // Form pages one by one: one with a long table becomes several pages, any other stays one page (loose parts share a page).
                var open = null;
                var firsts = units.map(function (unit) { return unit.levels[0].child; });
                kids.forEach(function (kid) {
                    // Styles and scripts written among the form pages show nothing: they stay where they are.
                    if (['STYLE', 'SCRIPT', 'LINK', 'TEMPLATE'].indexOf(kid.tagName) !== -1) { return; }
                    var index = firsts.indexOf(kid);
                    if (index !== -1) {
                        open = null;
                        if (units[index].flow) { flowPagesOf(units[index], made[index], newBlock); } else { pagesOf(units[index], made[index], capacity, newBlock); }
                    } else if (kid.classList.contains('official-fill')) {
                        open = null;
                        newBlock().appendChild(kid);
                    } else {
                        if (!open) { open = newBlock(); }
                        open.appendChild(kid);
                    }
                });
            }
            blocks.forEach(function (element) { page.appendChild(element); });
            // Footer of every page: the page number in the middle, the title of the document (the value of data-page-footer) on the right.
            if (footed) {
                root.style.setProperty('--doc-page-h', ((+spec[2] - spec[3] - spec[5]) * MM - 4) + 'px'); // 4 px of room: at some margins the last page was a few pixels taller than the sheet and its footer printed alone on one more page
                blocks.forEach(function (element, number) {
                    var footer = document.createElement('div');
                    footer.setAttribute('data-doc-footer', '');
                    footer.className = 'doc-footer';
                    [['', 'left'], ['Page ' + (number + 1) + ' of ' + blocks.length, 'center'], [page.getAttribute('data-page-footer'), 'right']].forEach(function (part) {
                        var cell = document.createElement('span');
                        cell.className = part[1];
                        cell.textContent = part[0];
                        footer.appendChild(cell);
                    });
                    element.appendChild(footer);
                });
            }
            return availableHeight;
        }

        // How far each page holds more than the paper can print (a merged cell copy or a wrapped line made it taller than measured); only the pages that do.
        function overflows(page, availableHeight) {
            var root = document.documentElement;
            var preview = root.style.getPropertyValue('--preview-zoom');
            root.style.setProperty('--preview-zoom', 1);
            var over = [];
            Array.prototype.forEach.call(page.querySelectorAll(':scope > .doc-page'), function (block, number) {
                // A fixed form page (instructions, terms) cannot be repacked by lowering the room of the table pages: fitFixed below scales it.
                if (!block.querySelector('table[data-paginate]')) { return; }
                var top = block.getBoundingClientRect().top + parseFloat(getComputedStyle(block).paddingTop);
                var bottom = Math.max.apply(null, Array.prototype.filter.call(block.children, function (child) { return !child.hasAttribute('data-doc-footer'); }).map(function (child) { return child.getBoundingClientRect().bottom; }).concat([top]));
                if (bottom - top > availableHeight * (1 + (block.hasAttribute('data-tail-over') ? overAllow : 0)) + 1) { over[number] = bottom - top - availableHeight; }
            });
            if (preview) { root.style.setProperty('--preview-zoom', preview); } else { root.style.removeProperty('--preview-zoom'); }
            return over.length ? over : [];
        }

        // A fixed form page taller than the paper (a form drawn for longer paper) is scaled down to the page instead of spilling a few lines onto a page of its own.
        function fitFixed(page, availableHeight, all) {
            var root = document.documentElement;
            var preview = root.style.getPropertyValue('--preview-zoom');
            root.style.setProperty('--preview-zoom', 1);
            Array.prototype.forEach.call(page.querySelectorAll(':scope > .doc-page'), function (block) {
                if (!all && block.querySelector('table[data-paginate]')) { return; }
                var kids = Array.prototype.filter.call(block.children, function (child) { return !child.hasAttribute('data-doc-footer'); });
                kids.forEach(function (child) { child.style.zoom = ''; });
                var top = block.getBoundingClientRect().top + parseFloat(getComputedStyle(block).paddingTop);
                var bottom = Math.max.apply(null, kids.map(function (child) { return child.getBoundingClientRect().bottom; }).concat([top]));
                var height = bottom - top;
                if (height > availableHeight - 2) { var scale = (availableHeight - 4) / height; kids.forEach(function (child) { child.style.zoom = scale; }); }
            });
            if (preview) { root.style.setProperty('--preview-zoom', preview); } else { root.style.removeProperty('--preview-zoom'); }
        }

        function paginate() {
            var style = document.getElementById('official-page-style');
            var page = document.querySelector('[data-official-page][data-sheets]');
            var spec = style && /size:\s*([\d.]+)mm\s+([\d.]+)mm;\s*margin:\s*([\d.]+)mm\s+([\d.]+)mm\s+([\d.]+)mm\s+([\d.]+)mm/.exec(style.textContent);
            var units = page && unitsOf(page);
            if (!units || !units.length || !spec || busy) { return; }
            // Rows are measured with the page's own fonts, so nothing is laid out before the page and its fonts have loaded.
            if (document.readyState !== 'complete' || (document.fonts && document.fonts.status === 'loading')) { return; }
            // The same paper and margins as the last time: the pages are already laid out (the engine fires load, DOMContentLoaded, style changes and beforeprint, and each pass is slow on a long document).
            var key = style.textContent;
            if (key === laidOutFor && page.querySelector(':scope > .doc-page')) { return; }
            busy = true;
            try {
                units.forEach(function (unit) {
                    tablesOf(unit).forEach(function (table) {
                        Array.prototype.forEach.call(table.tBodies[0].querySelectorAll('[rowspan]'), function (cell) { if (!cell.dataset.rs) { cell.dataset.rs = cell.rowSpan; } });
                    });
                });
                var scalable = SCALABLE.indexOf(page.getAttribute('data-doc')) !== -1;
                tailRoom = scalable ? 1.02 : 1;
                overAllow = scalable ? 0.04 : 0;
                var single = units.length === 1 && units[0].legacy;
                var fixedScalable = scalable;
                // "Fit on one page" (the print setup box adds /* fit */ to the page style): the whole form is scaled down, as little as it takes, until it fills one sheet.
                var fit = /\/\* fit \*\//.test(style.textContent);
                var fitBase = (parseFloat(page.style.zoom) || 1) / (parseFloat(page.getAttribute('data-fit-scale')) || 1);
                page.removeAttribute('data-fit-scale');
                page.style.zoom = fitBase;
                var blocksNow = function () { return page.querySelectorAll(':scope > .doc-page').length; };
                var run = function () {
                    var reserves = [];
                    var factor = scalable ? (fit ? 0.97 : 1) : 0.98; // a procurement form uses the whole room set by its margins; the overflow check below lowers it when a page comes out too tall
                    var available;
                    for (var attempt = 0; attempt < 10; attempt++) {
                        restore(page, units);
                        available = build(page, units, spec, factor, reserves);
                        var over = overflows(page, available);
                        if (!over.length) { break; }
                        if (single && attempt < 6) {
                            // Only the page that came out too tall gets less room, so the other pages stay as full as they can be.
                            over.forEach(function (excess, number) { if (excess) { reserves[number] = (reserves[number] || 0) + excess + 2; } });
                        } else {
                            factor -= 0.05;
                        }
                    }
                    if (typeof available === 'number') { fitFixed(page, available, fixedScalable); }
                    return available;
                };
                run();
                if (fit && blocksNow() > 1) {
                    var fitted = false;
                    for (var scale = 0.97; scale >= 0.6; scale -= 0.03) {
                        page.style.zoom = fitBase * scale;
                        run();
                        if (blocksNow() === 1) { page.setAttribute('data-fit-scale', scale.toFixed(2)); fitted = true; break; }
                    }
                    if (!fitted) { page.style.zoom = fitBase; run(); }
                }
                laidOutFor = key;
            } finally {
                busy = false;
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            var style = document.getElementById('official-page-style');
            if (style) { new MutationObserver(paginate).observe(style, { childList: true, characterData: true, subtree: true }); }
            window.addEventListener('beforeprint', paginate);
            window.addEventListener('load', paginate);
            if (document.fonts && document.fonts.ready) { document.fonts.ready.then(paginate); }
            paginate();
        });
    })();
</script>
