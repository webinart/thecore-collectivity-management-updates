(() => {
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
        };
        const fromHash = () => {
            const id = location.hash.slice(1);
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
