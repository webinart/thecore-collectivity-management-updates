(() => {
    const paper = document.querySelector('#paper');
    const root = document.querySelector('#menu-root');
    const art = document.querySelector('#autumn-art');
    const params = new URL(location.href).searchParams;
    const documentData = document.querySelector('#svj-school-menu-document');
    const embedded = documentData ? JSON.parse(documentData.textContent) : null;
    const standalone = parent === window;
    let theme;
    let latest;
    let revision = 0;
    const send = value => { if (!standalone) parent.postMessage(value, location.origin); };
    const widthFor = mode => mode === 'mobile' ? embedded?.screen ? Math.max(280, innerWidth) : 390 : mode === 'print' ? 1123 : 1280;
    const box = node => {
        const bounds = node.getBoundingClientRect();
        const origin = paper.getBoundingClientRect();
        return { left: bounds.left - origin.left, top: bounds.top - origin.top, right: bounds.right - origin.left, bottom: bounds.bottom - origin.top, width: bounds.width, height: bounds.height };
    };
    const place = (node, left, top, width, height) => Object.assign(node.style, { left: `${left}px`, top: `${top}px`, width: `${width}px`, height: `${height}px` });
    const arrangeArt = mode => {
        const mobile = mode === 'mobile';
        const heading = box(root.querySelector('.tccms-week-heading'));
        const title = box(root.querySelector('.tccms-week-heading h2'));
        if (!mobile) {
            const corner = box(root.querySelector('.tccms-corner'));
            const width = Math.min(corner.width - 12, (corner.bottom - heading.top - 4) * 1.5);
            place(art.querySelector('.autumn-mascot'), corner.left + (corner.width - width) / 2, corner.bottom - width / 1.5 - 3, width, width / 1.5);
        }
        const sprigSize = mobile ? 30 : mode === 'print' ? 48 : 60;
        const sprigGap = mobile ? 6 : 8;
        place(art.querySelector('.autumn-sprig.is-left'), title.left - sprigSize - sprigGap, title.top + (title.height - sprigSize) / 2, sprigSize, sprigSize);
        place(art.querySelector('.autumn-sprig.is-right'), title.right + sprigGap, title.top + (title.height - sprigSize) / 2, sprigSize, sprigSize);
        const layer = (name, source) => {
            let node = art.querySelector(`.${name}`);
            if (!node) {
                node = document.createElement('img'); node.className = name; node.src = source; node.alt = ''; node.draggable = false; art.append(node);
            }
            return node;
        };
        const trailSource = 'assets/autumn-wind-v2.webp';
        const groundSource = 'assets/autumn-leaf-ground-v2.webp';
        const trail = layer('autumn-leaf-trail', trailSource);
        const ground = layer('autumn-leaf-ground', groundSource);
        const table = box(root.querySelector(mobile ? '.tccms-mobile-day:last-child' : '.tccms-table-view'));
        const trailHeight = table.height + 32;
        const trailWidth = trailHeight / 3;
        const inset = mobile ? .8 : mode === 'print' ? .76 : .62;
        const trailLeft = table.right - trailWidth * inset;
        place(trail, mode === 'print' ? Math.min(trailLeft, paper.getBoundingClientRect().width - trailWidth - 2) : trailLeft, table.top - 8, trailWidth, trailHeight);
        const margin = mobile ? 8 : mode === 'print' ? 8 : 14;
        const groundWidth = table.width + margin * 2;
        const crop = mobile ? { left: .35, width: .65 } : { left: 0, width: 1 };
        const groundHeight = groundWidth / (3 * crop.width);
        place(ground, table.left - margin, table.bottom - groundHeight * (mode === 'print' ? .68 : .62), groundWidth, groundHeight);
        const trailCards = mobile ? '.tccms-mobile-day:last-child,.tccms-mobile-day:last-child .tccms-mobile-heading,.tccms-mobile-day:last-child dt' : '.tccms-day-head:last-child,.tccms-table tbody td:last-child';
        const groundCards = mobile ? trailCards : '.tccms-table tbody tr:last-child > *';
        return [{ node: trail, source: trailSource, cards: trailCards, protectionPadding: mode === 'print' ? 1 : 4, foregroundBands: [[0, .18], [.36, 1]] }, { node: ground, source: groundSource, crop, cards: groundCards }];
    };
    const closeMarkers = except => root.querySelectorAll('.tccms-marker-group.is-open').forEach(group => {
        if (group === except) return;
        group.classList.remove('is-open');
        group.querySelector('[data-menu-marker]')?.setAttribute('aria-expanded', 'false');
    });
    root.addEventListener('click', event => {
        const button = event.target.closest('[data-menu-marker]');
        if (!button) { closeMarkers(); return; }
        const group = button.closest('.tccms-marker-group');
        closeMarkers(group);
        const open = group.classList.toggle('is-open');
        button.setAttribute('aria-expanded', String(open));
        if (open) {
            const label = group.querySelector('.tccms-marker-name');
            label.style.marginLeft = '0px';
            const bounds = label.getBoundingClientRect();
            const outer = paper.getBoundingClientRect();
            const offset = bounds.left < outer.left + 8 ? outer.left + 8 - bounds.left : Math.min(0, outer.right - 8 - bounds.right);
            label.style.marginLeft = `${offset}px`;
        }
    });
    document.addEventListener('click', event => { if (!event.target.closest('[data-menu-marker]')) closeMarkers(); });
    document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMarkers(); });
    const dimensions = () => {
        if (!latest || !paper.dataset.ready) return;
        send({ type: 'SVJ_AUTUMN_DIMENSIONS', week: latest.week.start, width: widthFor(latest.mode), height: Math.ceil(paper.getBoundingClientRect().height) });
    };
    const render = async update => {
        const version = ++revision;
        const changedWeek = latest?.week.id !== update.week.id;
        latest = update;
        paper.removeAttribute('data-ready');
        delete document.body.dataset.printReady;
        document.body.className = `mode-${update.mode}${update.mode === 'print' ? ' tccms-print-document' : ''}`;
        document.documentElement.style.width = `${widthFor(update.mode)}px`;
        if (embedded?.screen) {
            paper.style.width = `${widthFor(update.mode)}px`;
            paper.style.minHeight = '0';
        }
        if (changedWeek) {
            root.innerHTML = update.week.menuHtml;
            if (embedded) {
                root.querySelectorAll('script,iframe,object,embed,link,.tccms-week-footer,.tccms-periods').forEach(node => node.remove());
                const replacements = new Map([
                    ['Menus-semaines-47-48-1.png', 'assets/menu-source.webp'],
                    ['ferme-rochette-logo.png', 'assets/ferme-rochette-logo.png'],
                    ['autumn-hedgehog.png', 'assets/hedgehog.webp'],
                    ['autumn-leaves.png', 'assets/leaves.webp']
                ]);
                root.querySelectorAll('img[src],image[href]').forEach(node => {
                    const attribute = node.tagName.toLowerCase() === 'img' ? 'src' : 'href';
                    const source = node.getAttribute(attribute);
                    for (const [name, replacement] of replacements) {
                        if (new URL(source, document.baseURI).pathname.endsWith(`/${name}`)) node.setAttribute(attribute, replacement);
                    }
                    node.setAttribute('loading', 'eager');
                });
                if (!root.querySelector('.tccms-origin-rail')) {
                    const rail = document.createElement('aside');
                    rail.className = 'tccms-origin-rail';
                    rail.innerHTML = '<ul></ul>';
                    root.querySelector('.tccms-week').append(rail);
                }
            }
            root.querySelectorAll('[data-menu-marker]').forEach(button => { button.removeAttribute('title'); button.setAttribute('aria-expanded', 'false'); });
        }
        window.SVJMenuRenderer.apply(theme, update.mode, paper, root);
        root.querySelector('.tccms-legend').append(root.querySelector('.tccms-origin-rail'));
        await new Promise(resolve => requestAnimationFrame(resolve));
        await document.fonts.ready;
        await Promise.all([...document.images].filter(image => image.getAttribute('src')).map(image => image.decode()));
        if (version !== revision) return;
        await window.SVJAutumnPaintMask.prepare(paper, update.mode === 'print' ? theme.style : null);
        if (version !== revision) return;
        const layers = arrangeArt(update.mode);
        await Promise.all([...art.querySelectorAll('img')].map(image => image.decode()));
        if (version !== revision) return;
        if (!await window.SVJAutumnLayers.update(root, layers) || version !== revision) return;
        paper.dataset.ready = 'true';
        document.body.dataset.printReady = 'true';
        document.title = `Menu scolaire — ${update.week.title}`;
        dimensions();
        window.menuReady = true;
    };
    window.addEventListener('message', event => {
        if (event.source !== parent || event.origin !== location.origin || event.data?.type !== 'SVJ_AUTUMN_UPDATE' || !theme) return;
        if (!['screen', 'mobile'].includes(event.data.mode) || typeof event.data.week?.menuHtml !== 'string') return;
        render(event.data).catch(() => send({ type: 'SVJ_AUTUMN_ERROR' }));
    });
    new ResizeObserver(dimensions).observe(paper);
    const loadImage = async source => { const image = new Image(); image.src = source; await image.decode(); };
    Promise.all([
        embedded ? Promise.resolve(embedded.theme) : fetch('theme.json').then(response => { if (!response.ok) throw new Error('Thème indisponible.'); return response.json(); }),
        Promise.all(['assets/menu-source.webp', 'assets/ferme-rochette-logo.png', 'assets/autumn-wind-v2.webp', 'assets/autumn-leaf-ground-v2.webp'].map(loadImage))
    ]).then(async ([model]) => {
        theme = window.SVJMenuTheme.parse(model);
        if (embedded) {
            await render({ week: embedded.week, mode: embedded.screen ? embedded.mobile ? 'mobile' : 'screen' : 'print' });
            if (embedded.autoPrint) window.print();
            return;
        }
        if (!standalone) { send({ type: 'SVJ_AUTUMN_READY' }); return; }
        const response = await fetch('menus.json');
        if (!response.ok) throw new Error('Menus indisponibles.');
        const data = await response.json();
        const week = data.weeks.find(item => item.start === params.get('week')) || data.weeks[0];
        await render({ week, mode: params.get('print') === '1' ? 'print' : innerWidth <= 700 ? 'mobile' : 'screen' });
        if (params.get('print') === '1') window.print();
    }).catch(() => {
        root.innerHTML = '<p class="loading-message" role="alert">Le menu ne peut pas être chargé. Rechargez la page pour réessayer.</p>';
        send({ type: 'SVJ_AUTUMN_ERROR' });
    });
    if (embedded?.screen && embedded.mobile) {
        let previousWidth = innerWidth;
        window.addEventListener('resize', () => {
            if (innerWidth === previousWidth || !latest) return;
            previousWidth = innerWidth;
            render(latest).catch(() => send({ type: 'SVJ_AUTUMN_ERROR' }));
        });
    }
})();
