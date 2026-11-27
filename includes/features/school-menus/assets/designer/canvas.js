(() => {
    const embedded = JSON.parse(document.querySelector('#svj-school-menu-document')?.textContent || 'null');
    const paper = document.querySelector('#paper');
    const menuRoot = document.querySelector('#menu-root');
    const selection = document.querySelector('#selection');
    let latest;
    let drag;
    let loaded = false;
    let prepared = false;
    const send = value => parent.postMessage(value, location.origin);
    const rect = node => {
        if (!node) return { x: paper.clientWidth, y: 0, width: 0, height: 0, right: paper.clientWidth, bottom: 0 };
        const bounds = node.getBoundingClientRect();
        const outer = paper.getBoundingClientRect();
        return { x: bounds.left - outer.left, y: bounds.top - outer.top, width: bounds.width, height: bounds.height, right: bounds.right - outer.left, bottom: bounds.bottom - outer.top };
    };
    const modeWidth = () => latest?.mode === 'mobile' ? embedded?.screen && !embedded.editing ? innerWidth : 390 : latest?.mode === 'print' ? 1123 : 1280;
    const dimensions = () => {
        const height = Math.max(latest?.mode === 'mobile' ? 0 : 794, Math.ceil(paper.getBoundingClientRect().height));
        send({ type: 'SVJ_MENU_DIMENSIONS', dimensions: { width: modeWidth(), height } });
        if (embedded && !embedded.editing && prepared) {
            send({ type: 'SVJ_AUTUMN_DIMENSIONS', week: embedded.week, width: modeWidth(), height });
            document.body.dataset.printReady = 'true';
        }
    };
    const drawSelection = () => {
        const node = [...document.querySelectorAll('.decor-piece')].find(piece => piece.dataset.piece === latest?.selectedId);
        selection.hidden = !node || !latest?.editingDecorations || latest?.mode === 'mobile';
        if (!node) return;
        const box = rect(node);
        selection.style.left = `${box.x - 3}px`;
        selection.style.top = `${box.y - 3}px`;
        selection.style.width = `${box.width + 6}px`;
        selection.style.height = `${box.height + 6}px`;
        const label = selection.querySelector('.selection-name');
        label.textContent = latest.model.elements.find(piece => piece.id === node.dataset.piece)?.name || 'Illustration';
        label.style.left = box.x + 230 > paper.clientWidth ? 'auto' : '-2px';
        label.style.right = box.x + 230 > paper.clientWidth ? '-2px' : 'auto';
    };
    const sourceAllowed = source => /^(assets\/[a-z0-9][a-z0-9._-]*\.(png|jpg|jpeg|webp)|data:image\/(png|jpeg|webp);base64,[A-Za-z0-9+/]+={0,2})$/.test(source);
    const renderDrapes = pieces => {
        const heading = rect(menuRoot.querySelector('.tccms-week-heading'));
        const content = rect(menuRoot.querySelector(latest.mode === 'mobile' ? '.tccms-day-view' : '.tccms-table-view'));
        const firstDay = latest.mode === 'mobile' ? rect(menuRoot.querySelector('.tccms-mobile-day')) : content;
        for (const layer of ['back', 'front']) {
            const container = document.querySelector(`#drape-${layer}-layer`);
            const shown = pieces.filter(piece => piece.layer === layer);
            for (const node of [...container.children]) if (!shown.some(piece => piece.id === node.dataset.piece)) node.remove();
            for (const piece of shown) {
                let node = [...container.children].find(candidate => candidate.dataset.piece === piece.id);
                if (!node) {
                    node = document.createElement('div'); node.className = 'decor-piece is-drape'; node.dataset.piece = piece.id; node.tabIndex = 0; node.setAttribute('role', 'button');
                    const image = document.createElement('img'); image.alt = ''; image.draggable = false; node.append(image); container.append(node);
                }
                const picture = node.firstElementChild;
                if (picture.getAttribute('src') !== piece.source) { picture.src = piece.source; picture.onload = () => { if (!drag) render(); }; }
                const ratio = picture.naturalWidth && picture.naturalHeight ? picture.naturalWidth / picture.naturalHeight : piece.anchor === 'drape-right' ? 2 / 3 : 3 / 2;
                const rightEdge = piece.anchor === 'drape-right';
                const size = piece.scale / 100;
                const span = latest.mode === 'mobile' ? Math.min(560, firstDay.height + 18) : content.bottom - heading.y + 18;
                const height = rightEdge ? span * size : (latest.mode === 'mobile' ? 110 : latest.mode === 'print' ? 140 : 180) * size / ratio;
                const width = height * ratio;
                const inset = latest.mode === 'mobile' ? 12 : latest.mode === 'print' ? 16 : 24;
                const left = rightEdge ? content.right - width * .52 : content.x - inset;
                const top = rightEdge ? latest.mode === 'mobile' ? firstDay.y - 6 : heading.y - 10 : content.bottom - height * .82;
                Object.assign(node.style, { left: `${left + piece.x}px`, top: `${top + piece.y}px`, width: `${width}px`, height: `${height}px` });
                node.setAttribute('aria-label', `Sélectionner ${piece.name}. Flèches pour déplacer.`);
                node.setAttribute('aria-pressed', String(piece.id === latest.selectedId));
            }
        }
    };
    const placeDecor = (node, piece, boxes) => {
        const factor = piece.scale / 100;
        const image = node.firstElementChild;
        const ratio = image?.naturalWidth && image.naturalHeight ? image.naturalWidth / image.naturalHeight : piece.source.includes('autumn-hedgehog') ? 1.5 : .67;
        let width;
        let height;
        let left;
        let top;
        if (piece.paired && piece.anchor === 'ribbon') {
            width = boxes.ribbon.width + 104;
            height = 50 * factor;
            left = boxes.ribbon.x - 52;
            top = boxes.ribbon.y + (boxes.ribbon.height - height) / 2;
        } else if (piece.anchor === 'index') {
            width = Math.min(boxes.corner.width - 10, 214 * factor);
            height = Math.min(boxes.corner.bottom - boxes.heading.y - 7, width / ratio);
            left = boxes.corner.x + (boxes.corner.width - width) / 2;
            top = boxes.corner.bottom - height - 6;
        } else if (piece.anchor === 'below-badges') {
            width = Math.min(44 * factor, boxes.rail.width);
            height = width / ratio;
            left = boxes.leaf ? boxes.leaf.x + (boxes.leaf.width - width) / 2 : boxes.rail.x + (boxes.rail.width - width) / 2;
            top = boxes.leaf ? boxes.leaf.y : boxes.badges.bottom + 12;
        } else if (piece.anchor === 'ribbon') {
            width = 40 * factor;
            height = width / ratio;
            left = boxes.ribbon.x - width - 10;
            top = boxes.ribbon.y + (boxes.ribbon.height - height) / 2;
        } else if (piece.anchor === 'bottom') {
            width = 100 * factor;
            height = width / ratio;
            left = boxes.menu.right - width;
            top = boxes.menu.bottom + 16;
            paper.style.paddingBottom = `${Math.max(36, height + 42)}px`;
        } else {
            width = Math.min(80 * factor, 100);
            height = width / ratio;
            left = boxes.rail.x + (boxes.rail.width - width) / 2;
            top = boxes.heading.y - 4;
        }
        Object.assign(node.style, { left: `${left + piece.x}px`, top: `${top + piece.y}px`, width: `${width}px`, height: `${height}px`, opacity: piece.layer === 'back' ? '.5' : '1' });
    };
    const render = () => {
        if (!loaded || !latest) return;
        const { model, mode } = latest;
        document.body.className = `mode-${mode}${mode === 'print' ? ' tccms-print-document' : ''}`;
        document.body.dataset.editingDecorations = String(latest.editingDecorations === true);
        if (Math.abs(innerWidth - modeWidth()) > 1) { dimensions(); return; }
        window.SVJMenuRenderer.apply(model, mode, paper, menuRoot);
        const sideBadges = document.body.dataset.badgePlacement === 'right';
        const pieces = model.elements.filter(piece => piece.visible && sourceAllowed(piece.source) && (sideBadges || piece.anchor !== 'below-badges'));
        const drapes = pieces.filter(piece => piece.anchor.startsWith('drape-'));
        const hasCorner = drapes.some(piece => piece.anchor === 'drape-corner');
        document.body.dataset.tableGarland = String(drapes.some(piece => piece.anchor === 'drape-right'));
        menuRoot.style.paddingRight = drapes.length ? mode === 'mobile' ? '8px' : !sideBadges ? mode === 'print' ? '56px' : '64px' : '' : '';
        menuRoot.querySelectorAll('.tccms-table-view,.tccms-day-view').forEach(node => { node.style.marginBottom = hasCorner ? mode === 'mobile' ? '40px' : mode === 'print' ? '45px' : '50px' : ''; });
        const mascot = menuRoot.querySelector('.tccms-season--mobile');
        const character = pieces.find(piece => piece.anchor === 'index');
        if (mascot) { mascot.style.display = character ? '' : 'none'; if (character) mascot.src = character.source; }
        const leaves = pieces.find(piece => piece.anchor === 'below-badges');
        const mobileLeaves = menuRoot.querySelector('.tccms-edge-leaves');
        if (mobileLeaves) {
            mobileLeaves.style.display = leaves ? '' : 'none';
            if (leaves) { mobileLeaves.src = leaves.source; mobileLeaves.style.width = `${Math.min(100, 62 * leaves.scale / 100)}px`; mobileLeaves.style.height = 'auto'; }
        }
        const sprigs = pieces.find(piece => piece.paired && piece.anchor === 'ribbon');
        menuRoot.querySelectorAll('.tccms-week-leaf').forEach(node => {
            node.style.display = sprigs ? '' : 'none';
            if (sprigs) { node.style.backgroundImage = `url("${sprigs.source}")`; node.style.width = `${52 * sprigs.scale / 100}px`; node.style.height = `${64 * sprigs.scale / 100}px`; }
        });
        const mobileArt = document.querySelector('#mobile-extra-art');
        mobileArt.replaceChildren();
        if (mode === 'mobile') for (const piece of pieces.filter(candidate => !candidate.anchor.startsWith('drape-') && candidate !== character && candidate !== leaves && candidate !== sprigs)) {
            const image = document.createElement('img'); image.src = piece.source; image.alt = ''; image.style.width = `${100 * piece.scale / 100}px`; mobileArt.append(image);
        }
        if (mode !== 'mobile') {
            const boxes = {
                menu: rect(menuRoot), heading: rect(menuRoot.querySelector('.tccms-week-heading')), ribbon: rect(menuRoot.querySelector('.tccms-week-heading h2')),
                corner: rect(menuRoot.querySelector('.tccms-corner')), rail: rect(menuRoot.querySelector('.tccms-origin-rail')), badges: rect(menuRoot.querySelector('.tccms-origin-rail ul')),
                leaf: leaves && mobileLeaves ? rect(mobileLeaves) : null
            };
            for (const layer of ['back', 'front']) {
                const container = document.querySelector(`#${layer}-layer`);
                const layerPieces = pieces.filter(piece => !piece.anchor.startsWith('drape-') && piece.layer === layer);
                for (const existing of [...container.children]) if (!layerPieces.some(piece => piece.id === existing.dataset.piece)) existing.remove();
                for (const piece of layerPieces) {
                    let node = [...container.children].find(candidate => candidate.dataset.piece === piece.id);
                    if (!node) {
                        node = document.createElement('div');
                        node.className = 'decor-piece';
                        node.dataset.piece = piece.id;
                        node.tabIndex = 0;
                        node.setAttribute('role', 'button');
                        container.append(node);
                    }
                    const imageCount = piece.paired && piece.anchor === 'ribbon' ? 2 : 1;
                    node.classList.toggle('is-paired', imageCount === 2);
                    while (node.children.length > imageCount) node.lastChild.remove();
                    while (node.children.length < imageCount) { const image = document.createElement('img'); image.alt = ''; image.draggable = false; node.append(image); }
                    for (const image of node.children) if (image.getAttribute('src') !== piece.source) { image.src = piece.source; image.onload = () => { if (!drag) render(); }; }
                    node.setAttribute('aria-label', `Sélectionner ${piece.name}. Flèches pour déplacer.`);
                    node.setAttribute('aria-pressed', String(piece.id === latest.selectedId));
                    placeDecor(node, piece, boxes);
                }
            }
        }
        renderDrapes(drapes);
        const table = menuRoot.querySelector(mode === 'mobile' ? '.tccms-day-view' : '.tccms-table-view');
        window.SVJMenuMotion.update(paper, latest.thumbnail ? null : model.style.animation, mode, menuRoot.querySelector('.tccms-week-heading'), table);
        document.querySelectorAll('.decor-piece').forEach(node => {
            node.tabIndex = latest.editingDecorations ? 0 : -1;
            node.setAttribute('aria-hidden', String(!latest.editingDecorations));
        });
        requestAnimationFrame(() => { drawSelection(); dimensions(); });
    };
    document.addEventListener('pointerdown', event => {
        const node = event.target.closest('.decor-piece');
        if (!node || !latest?.editingDecorations) return;
        const piece = latest.model.elements.find(candidate => candidate.id === node.dataset.piece);
        if (!piece) return;
        send({ type: 'SVJ_MENU_SELECT', id: piece.id });
        drag = { node, id: piece.id, startX: event.clientX, startY: event.clientY, originalX: piece.x, originalY: piece.y, left: parseFloat(node.style.left), top: parseFloat(node.style.top) };
        node.setPointerCapture(event.pointerId);
        node.classList.add('is-dragging');
        event.preventDefault();
    });
    document.addEventListener('pointermove', event => {
        if (!drag) return;
        const offsetX = Math.max(-60 - drag.originalX, Math.min(60 - drag.originalX, event.clientX - drag.startX));
        const offsetY = Math.max(-60 - drag.originalY, Math.min(60 - drag.originalY, event.clientY - drag.startY));
        drag.node.style.left = `${drag.left + offsetX}px`;
        drag.node.style.top = `${drag.top + offsetY}px`;
        drawSelection();
    });
    const finishDrag = event => {
        if (!drag) return;
        drag.node.classList.remove('is-dragging');
        if (Math.abs(event.clientX - drag.startX) > 1 || Math.abs(event.clientY - drag.startY) > 1) send({ type: 'SVJ_MENU_MOVE', id: drag.id, x: drag.originalX + event.clientX - drag.startX, y: drag.originalY + event.clientY - drag.startY });
        drag = null;
    };
    document.addEventListener('pointerup', finishDrag);
    document.addEventListener('pointercancel', () => { drag = null; render(); });
    document.addEventListener('keydown', event => {
        const node = event.target.closest('.decor-piece');
        if (!node || !latest?.editingDecorations) return;
        if (['Enter', ' '].includes(event.key)) { event.preventDefault(); send({ type: 'SVJ_MENU_SELECT', id: node.dataset.piece }); }
        const directions = { ArrowLeft: [-1, 0], ArrowRight: [1, 0], ArrowUp: [0, -1], ArrowDown: [0, 1] };
        if (!directions[event.key]) return;
        event.preventDefault();
        const piece = latest.model.elements.find(candidate => candidate.id === node.dataset.piece);
        const direction = directions[event.key];
        const distance = event.shiftKey ? 10 : 1;
        send({ type: 'SVJ_MENU_MOVE', id: piece.id, x: piece.x + direction[0] * distance, y: piece.y + direction[1] * distance });
    });
    window.addEventListener('message', event => {
        if (event.source !== parent || event.origin !== location.origin || event.data?.type !== 'SVJ_MENU_UPDATE') return;
        const changedMode = latest?.mode !== event.data.mode;
        if (!['screen', 'mobile', 'print'].includes(event.data.mode)) return;
        try { window.SVJMenuTheme.fromEditor(event.data.model); } catch (error) { return; }
        latest = event.data;
        if (changedMode) document.documentElement.style.width = `${modeWidth()}px`;
        render();
    });
    window.addEventListener('resize', () => { if (loaded) render(); });
    new ResizeObserver(() => { if (!drag) render(); }).observe(menuRoot);
    (embedded ? Promise.resolve(embedded) : Promise.reject(new Error('Menu unavailable.'))).then(async data => {
        menuRoot.innerHTML = data.menuHtml;
        menuRoot.querySelectorAll('script').forEach(script => script.remove());
        menuRoot.querySelectorAll('a').forEach(link => { link.removeAttribute('href'); link.removeAttribute('target'); });
        if (data.menuScript) {
            document.body.dataset.publicView = 'true';
            await new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = data.menuScript;
                script.onload = resolve;
                script.onerror = reject;
                document.body.append(script);
            });
        }
        await document.fonts.ready;
        loaded = true;
        if (embedded.theme) {
            latest = { model: window.SVJMenuTheme.toEditor(window.SVJMenuTheme.parse(embedded.theme)), mode: embedded.screen ? embedded.mobile ? 'mobile' : 'screen' : 'print', editingDecorations: false };
            document.documentElement.style.width = `${modeWidth()}px`;
            render();
            // Hidden mobile artwork will never enter the viewport of the A4 renderer.
            const images = [...paper.querySelectorAll('img[src]')];
            if (!embedded.screen) {
                for (const image of images) image.loading = 'eager';
            }
            await Promise.all(images.filter(image => image.loading !== 'lazy' && !image.complete).map(image => new Promise(resolve => {
                image.addEventListener('load', resolve, { once: true });
                image.addEventListener('error', resolve, { once: true });
            })));
            await document.fonts.ready;
            render();
            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            prepared = true;
            dimensions();
            if (embedded.autoPrint) window.print();
        }
        send({ type: 'SVJ_MENU_READY' });
    }).catch(() => { menuRoot.innerHTML = '<p class="loading-message" role="alert">Le menu ne peut pas être chargé. Rechargez la page.</p>'; send({ type: 'SVJ_AUTUMN_ERROR' }); });
})();
