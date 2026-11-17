(() => {
    document.querySelectorAll('[data-menu-calendar]').forEach(calendar => {
        const today = new Intl.DateTimeFormat('en-CA', { timeZone: calendar.dataset.timezone || 'Europe/Paris', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
        const monday = new Date(`${today}T12:00:00Z`);
        monday.setUTCDate(monday.getUTCDate() - (monday.getUTCDay() + 6) % 7);
        const start = monday.toISOString().slice(0, 10);
        const current = calendar.querySelector('[data-menu-group="current"]');
        const previous = calendar.querySelector('[data-menu-group="archive"]');
        if (!current || !previous) return;
        calendar.querySelectorAll('[data-menu-week]').forEach(section => {
            const group = section.dataset.weekStart < start ? previous : current;
            const link = [...calendar.querySelectorAll('.tccms-periods a')].find(item => item.hash === `#${section.id}`);
            group.append(section);
            if (link) group.querySelector('.tccms-periods').append(link);
        });
        for (const group of [current, previous]) {
            const sections = [...group.querySelectorAll('[data-menu-week]')].sort((first, second) => (group === current ? 1 : -1) * first.dataset.weekStart.localeCompare(second.dataset.weekStart));
            const navigation = group.querySelector('.tccms-periods');
            for (const section of sections) {
                group.append(section);
                const link = [...navigation.children].find(item => item.hash === `#${section.id}`);
                if (link) navigation.append(link);
            }
            group.dataset.defaultMenu = sections[0]?.id || '';
            group.querySelector('[data-menu-empty]').hidden = sections.length > 0;
            navigation.hidden = sections.length === 0;
        }
        calendar.querySelector('[data-menu-archives]').hidden = !previous.querySelector('[data-menu-week]');
    });
    const initialize = root => {
        if (root.dataset.initialized) return;
        root.dataset.initialized = 'true';
        const sections = [...root.querySelectorAll('.tccms-week')];
        const links = [...root.querySelectorAll('.tccms-periods a')];
        let activeMarker = null;
        let hideTimer;
        const closeMarker = () => {
            clearTimeout(hideTimer);
            if (!activeMarker) return;
            activeMarker.label.hidden = true;
            activeMarker.button.setAttribute('aria-expanded', 'false');
            activeMarker = null;
        };
        const positionMarker = marker => {
            marker.label.style.setProperty('--marker-tooltip-shift', '0px');
            marker.label.dataset.placement = 'top';
            if (marker.label.getBoundingClientRect().top < 8) marker.label.dataset.placement = 'bottom';
            const bounds = marker.label.getBoundingClientRect();
            const shift = Math.max(8 - bounds.left, Math.min(0, innerWidth - 8 - bounds.right));
            marker.label.style.setProperty('--marker-tooltip-shift', `${shift}px`);
        };
        const showMarker = marker => {
            clearTimeout(hideTimer);
            if (activeMarker !== marker) closeMarker();
            activeMarker = marker;
            marker.label.hidden = false;
            marker.button.setAttribute('aria-expanded', 'true');
            positionMarker(marker);
        };
        const select = id => {
            if (!sections.some(section => section.id === id)) return;
            closeMarker();
            sections.forEach(section => { section.hidden = section.id !== id; });
            links.forEach(link => {
                if (link.hash === `#${id}`) link.setAttribute('aria-current', 'true');
                else link.removeAttribute('aria-current');
            });
            root.dispatchEvent(new Event('tccm-school-menu-selected', { bubbles: true }));
        };
        const fromHash = () => {
            const id = location.hash.slice(1);
            if (sections.some(section => section.id === id)) {
                const archive = root.closest('[data-menu-archives]');
                if (archive) archive.open = true;
            }
            select(sections.some(section => section.id === id) ? id : root.dataset.defaultMenu);
        };
        links.forEach(link => link.addEventListener('click', () => select(link.hash.slice(1))));
        window.addEventListener('hashchange', fromHash);
        fromHash();
        root.querySelectorAll('[data-menu-marker]').forEach(button => {
            const label = root.querySelector(`[id="${button.getAttribute('aria-controls')}"]`);
            if (!label || button.closest('.tccms-legend')) return;
            const marker = { button, label, group: button.parentElement, pinned: false };
            label.hidden = true;
            button.removeAttribute('title');
            button.setAttribute('aria-expanded', 'false');
            marker.group.addEventListener('mouseenter', () => {
                if (activeMarker !== marker) marker.pinned = false;
                showMarker(marker);
            });
            marker.group.addEventListener('mouseleave', () => {
                if (!marker.pinned && document.activeElement !== button) hideTimer = setTimeout(() => {
                    if (activeMarker === marker) closeMarker();
                }, 120);
            });
            button.addEventListener('focus', () => {
                if (activeMarker !== marker) marker.pinned = false;
                showMarker(marker);
            });
            button.addEventListener('blur', () => {
                if (activeMarker === marker) closeMarker();
            });
            button.addEventListener('click', () => {
                if (activeMarker === marker && marker.pinned) {
                    marker.pinned = false;
                    closeMarker();
                } else {
                    marker.pinned = true;
                    showMarker(marker);
                }
            });
        });
        document.addEventListener('pointerdown', event => {
            if (activeMarker && !activeMarker.group.contains(event.target)) closeMarker();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && activeMarker) closeMarker();
        });
        const reposition = () => { if (activeMarker) positionMarker(activeMarker); };
        window.addEventListener('resize', reposition);
        window.addEventListener('scroll', reposition, { passive: true });
        root.dataset.markerTooltips = 'true';
    };
    const scan = () => document.querySelectorAll('[data-school-menus]').forEach(initialize);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
    else scan();
    if (window.elementorFrontend?.hooks) window.elementorFrontend.hooks.addAction('frontend/element_ready/tccm-school-menus.default', scan);
})();
