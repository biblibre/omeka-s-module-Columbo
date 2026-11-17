(() => {
    'use strict';

    const t = (text) => (window.Omeka && typeof Omeka.jsTranslate === 'function' ? Omeka.jsTranslate(text) : text);
    const collator = new Intl.Collator(document.documentElement.lang || undefined, { numeric: true, sensitivity: 'base' });
    const PREFETCH_DELAY = 150;

    const loads = new Map();

    function panelOf(tab) {
        return document.getElementById(tab.getAttribute('aria-controls'));
    }

    function setLoading(panel) {
        panel.setAttribute('aria-busy', 'true');
        panel.innerHTML = '';
        const loading = document.createElement('p');
        loading.className = 'columbo-loading';
        loading.innerHTML = '<span class="columbo-spinner" aria-hidden="true"></span> ';
        loading.append(t('Loading…'));
        panel.append(loading);
    }

    function setError(tab, panel) {
        panel.removeAttribute('aria-busy');
        panel.innerHTML = '';
        const message = document.createElement('p');
        message.className = 'columbo-error error';
        message.textContent = t('Could not load this tab.') + ' ';
        const retry = document.createElement('button');
        retry.type = 'button';
        retry.textContent = t('Retry');
        retry.addEventListener('click', () => load(tab, true));
        message.append(retry);
        panel.append(message);
    }

    /**
     * Fetches the tab content once. Rendering is left to load(), so that a
     * prefetch never builds the DOM of a tab that may not be displayed.
     */
    function fetchContent(tab, force = false) {
        const section = tab.dataset.section;
        if (!force && loads.has(section)) {
            return loads.get(section);
        }
        const promise = fetch(tab.dataset.contentUrl, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        }).then((response) => {
            if (!response.ok) {
                throw new Error(response.status + ' ' + response.statusText);
            }
            return response.text();
        });
        loads.set(section, promise);
        // Failures are not cached, so that the next display retries.
        promise.catch(() => loads.delete(section));
        return promise;
    }

    function load(tab, force = false) {
        const panel = panelOf(tab);
        if (!force && panel.dataset.state === 'loaded') {
            return;
        }
        if (panel.dataset.state !== 'loading') {
            setLoading(panel);
        }
        panel.dataset.state = 'loading';
        fetchContent(tab, force)
            .then((html) => {
                panel.innerHTML = html;
                panel.dataset.state = 'loaded';
                panel.removeAttribute('aria-busy');
                enhancePanel(tab, panel);
            })
            .catch((error) => {
                console.error('Columbo:', error);
                panel.dataset.state = 'error';
                setError(tab, panel);
            });
    }

    function activate(tab, { focus = false, updateUrl = true } = {}) {
        tab.closest('[role="tablist"]').querySelectorAll('[role="tab"]').forEach((other) => {
            const selected = other === tab;
            other.setAttribute('aria-selected', selected ? 'true' : 'false');
            other.tabIndex = selected ? 0 : -1;
            panelOf(other).hidden = !selected;
        });
        if (focus) {
            tab.focus();
        }
        if (updateUrl) {
            history.replaceState({ section: tab.dataset.section }, '', tab.href);
        }
        load(tab);
    }

    function initTabs(tablist) {
        const tabs = Array.from(tablist.querySelectorAll('[role="tab"]'));
        let prefetchTimer = null;

        tabs.forEach((tab, index) => {
            tab.addEventListener('click', (event) => {
                // Let the browser handle "open in a new tab".
                if (event.ctrlKey || event.metaKey || event.shiftKey || event.button !== 0) {
                    return;
                }
                event.preventDefault();
                activate(tab);
            });

            tab.addEventListener('keydown', (event) => {
                const keys = { ArrowRight: index + 1, ArrowLeft: index - 1, Home: 0, End: tabs.length - 1 };
                if (!(event.key in keys)) {
                    return;
                }
                event.preventDefault();
                activate(tabs[(keys[event.key] + tabs.length) % tabs.length], { focus: true });
            });

            const prefetch = () => {
                clearTimeout(prefetchTimer);
                prefetchTimer = setTimeout(() => fetchContent(tab), PREFETCH_DELAY);
            };
            tab.addEventListener('pointerenter', prefetch);
            tab.addEventListener('focus', prefetch);
            tab.addEventListener('pointerleave', () => clearTimeout(prefetchTimer));
        });

        const active = tabs.find((tab) => tab.getAttribute('aria-selected') === 'true') || tabs[0];
        activate(active, { updateUrl: false });
    }

    function enhancePanel(tab, panel) {
        panel.prepend(buildPanelBar(tab));
        panel.querySelectorAll('.columbo-block').forEach(initBlock);

        // Omeka admin enhances tables on page load only: injected ones need it too.
        if (window.jQuery) {
            window.jQuery(panel).trigger('enhance.tablesaw');
        }

        loadSizes(panel);
    }

    function buildPanelBar(tab) {
        const bar = document.createElement('div');
        bar.className = 'columbo-panel-bar';

        const refresh = document.createElement('button');
        refresh.type = 'button';
        refresh.textContent = t('Refresh');
        refresh.addEventListener('click', () => load(tab, true));
        bar.append(refresh);

        return bar;
    }

    /** Cell text, without the labels added by Tablesaw stack mode. */
    function cellText(cell) {
        const content = cell.querySelector('.tablesaw-cell-content');
        return (content || cell).textContent.replace(/\s+/g, ' ').trim();
    }

    function sortValue(cell) {
        if (!cell) {
            return '';
        }
        const raw = cell.dataset.sortValue ?? cellText(cell);
        const number = Number(raw.replace(/\s/g, '').replace(',', '.'));
        return raw !== '' && Number.isFinite(number) ? number : raw;
    }

    function compare(a, b) {
        if (typeof a === 'number' && typeof b === 'number') {
            return a - b;
        }
        return collator.compare(String(a), String(b));
    }

    function initBlock(block) {
        const table = block.querySelector('table');
        const tbody = table && table.tBodies[0];
        const controls = block.querySelector('.columbo-controls');
        if (!tbody || !controls) {
            return;
        }

        controls.append(buildCsvButton(block, table));

        const form = controls.querySelector('form.sorting');
        if (!form) {
            return;
        }
        if (tbody.rows.length < 2) {
            form.remove();
            return;
        }

        const headers = table.tHead ? Array.from(table.tHead.rows[0].cells) : [];
        const sortBy = form.elements.sort_by;
        const sortOrder = form.elements.sort_order;
        const filter = form.elements.columbo_filter;

        headers.forEach((th, index) => sortBy.add(new Option(cellText(th), index)));

        const sort = () => {
            const column = Number(sortBy.value);
            const direction = sortOrder.value === 'desc' ? -1 : 1;
            const rows = Array.from(tbody.rows).map((row, position) => ({ row, position, value: sortValue(row.cells[column]) }));
            rows.sort((a, b) => compare(a.value, b.value) * direction || a.position - b.position);
            tbody.append(...rows.map((entry) => entry.row));
            headers.forEach((th, index) => {
                th.setAttribute('aria-sort', index === column ? (direction === 1 ? 'ascending' : 'descending') : 'none');
            });
        };

        form.addEventListener('submit', (event) => {
            event.preventDefault();
            sort();
        });

        headers.forEach((th, index) => {
            th.classList.add('columbo-sortable');
            th.tabIndex = 0;
            const sortOnColumn = () => {
                sortOrder.value = th.getAttribute('aria-sort') === 'ascending' ? 'desc' : 'asc';
                sortBy.value = String(index);
                sort();
            };
            th.addEventListener('click', sortOnColumn);
            th.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' || event.key === ' ') {
                    event.preventDefault();
                    sortOnColumn();
                }
            });
        });

        // Rows are hidden rather than removed, so that the sort order is kept.
        filter.addEventListener('input', () => {
            const query = filter.value.trim().toLocaleLowerCase();
            Array.from(tbody.rows).forEach((row) => {
                row.hidden = query !== '' && !Array.from(row.cells).some((cell) => cellText(cell).toLocaleLowerCase().includes(query));
            });
        });
    }

    function csvEscape(value) {
        return /[",;\r\n]/.test(value) ? '"' + value.replace(/"/g, '""') + '"' : value;
    }

    function buildCsvButton(block, table) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'columbo-csv';
        button.textContent = t('Download CSV');
        button.addEventListener('click', () => {
            const lines = Array.from(table.rows)
                .filter((row) => !row.hidden)
                .map((row) => Array.from(row.cells).map((cell) => csvEscape(cellText(cell))).join(','));
            // The BOM lets spreadsheet software detect UTF-8.
            const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = block.id + '.csv';
            document.body.append(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(url), 0);
        });
        return button;
    }

    function loadSizes(panel) {
        // One request at a time, to avoid running several "du" on the server at once.
        Array.from(panel.querySelectorAll('[data-columbo-size]')).reduce((previous, cell) => previous.then(() => fetch(cell.dataset.columboSize, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
        })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(response.statusText))))
            .then((data) => {
                cell.textContent = data.readable;
                cell.dataset.sortValue = data.bytes;
            })
            .catch((error) => {
                console.error('Columbo:', error);
                cell.textContent = t('Could not calculate size.');
            })
            .finally(() => cell.removeAttribute('aria-busy'))), Promise.resolve());
    }

    function initBackToTop(tablist) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'columbo-back-to-top';
        button.title = t('Back to top');
        button.setAttribute('aria-label', t('Back to top'));
        button.innerHTML = '<span aria-hidden="true">↑</span>';
        button.hidden = true;
        document.body.append(button);

        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
        button.addEventListener('click', () => {
            // The whole page, not only the tab bar, which would stay under the fixed admin header.
            window.scrollTo({ top: 0, behavior: reducedMotion.matches ? 'auto' : 'smooth' });
            const selected = tablist.querySelector('[role="tab"][aria-selected="true"]');
            if (selected) {
                selected.focus({ preventScroll: true });
            }
        });

        // Shown only once the tab bar has scrolled out of view.
        new IntersectionObserver(([entry]) => {
            button.hidden = entry.isIntersecting || entry.boundingClientRect.top > 0;
        }).observe(tablist);
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.columbo-tabs[role="tablist"]').forEach((tablist) => {
            initTabs(tablist);
            initBackToTop(tablist);
        });
    });
})();
