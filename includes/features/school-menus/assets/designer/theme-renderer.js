(() => {
    const format = window.SVJMenuTheme;
    const presetIcons = { salad: 'salad.svg', utensils: 'utensils.svg', cake: 'cake-slice.svg' };
    const badgeHomes = new WeakMap();
    const cssName = path => `--theme-${path.replace(/^style\./, '').replaceAll('.', '-').replace(/[A-Z]/g, letter => `-${letter.toLowerCase()}`)}`;
    const apply = (model, mode, paper, root) => {
        const theme = format.fromEditor(model);
        const style = theme.style;
        const printing = mode === 'print';
        const mobile = mode === 'mobile';
        const html = document.documentElement;
        for (const setting of format.fields) {
            if (!['color', 'number'].includes(setting.kind)) continue;
            html.style.setProperty(cssName(setting.path), `${format.get(theme, setting.path)}${setting.unit || ''}`);
        }
        const variables = {
            '--ribbon-color': style.ribbonColor,
            '--dish-font': style.font === 'sans' ? '"DM Sans", sans-serif' : '"TCCM Menu Hand", cursive',
            '--heading-font': style.headingFont === 'sans' ? '"DM Sans", sans-serif' : '"TCCM Menu Brush", cursive',
            '--dish-font-size': `${style.fontSize + (printing ? style.print.fontAdjustment : 0)}px`,
            '--menu-ink': style.ink,
            '--menu-index-width': `${style.indexWidth}%`,
            '--menu-days-height': `${style.days.height - (printing ? 25 : 0)}px`,
            '--day-font-size': `${style.days.fontSize - (printing ? 4 : mobile ? 2 : 0)}px`,
            '--date-font-size': `${style.days.dateSize - (printing ? 3 : 0)}px`,
            '--day-padding': `${style.days.padding - (printing ? 4 : 0)}px`,
            '--course-font-size': `${style.courses.fontSize - (printing ? 5 : mobile ? 10 : 0)}px`,
            '--course-icon-size': `${style.courses.iconSize - (printing ? 8 : mobile ? 14 : 0)}px`,
            '--course-padding': `${style.courses.padding - (printing ? 5 : 0)}px`,
            '--cell-padding-x': `${style.cells.paddingX - (printing ? 4 : 0)}px`,
            '--cell-padding-y': `${style.cells.paddingY - (printing ? 5 : 0)}px`,
            '--badge-size': `${style.badges.size - (printing ? 18 : mobile ? 14 : 0)}px`,
            '--legend-padding': `${style.legend.padding - (printing ? 4 : 0)}px`,
            '--legend-font-size': `${style.legend.fontSize - (printing ? 2 : 0)}px`
        };
        for (const [name, value] of Object.entries(variables)) html.style.setProperty(name, value);
        const badgePlacement = root.querySelector('[data-legend-badges]') ? 'legend' : style.badges.placement;
        Object.assign(document.body.dataset, { texture: String(style.texture), ribbonShape: style.ribbonShape, dayShape: style.days.shape, courseShape: style.courses.shape, courseShadow: String(style.courses.shadow), cellShape: style.cells.shape, cellGradient: String(style.cells.gradient), badgePlacement, badgeFrame: style.badges.frame });
        paper.style.padding = `${printing ? style.print.padding * 96 / 25.4 : mobile ? Math.min(style.pagePadding, 24) : style.pagePadding}px`;
        paper.style.backgroundColor = style.pageBackground;
        const background = document.querySelector('#background-layer');
        background.style.backgroundImage = style.backgroundImage ? `url("${style.backgroundImage}")` : 'none';
        background.style.backgroundSize = mobile ? 'cover' : style.backgroundFit === 'stretch' ? '100% 100%' : style.backgroundFit;
        background.style.opacity = style.backgroundOpacity / 100;
        document.body.dataset.backgroundImage = String(Boolean(style.backgroundImage));
        root.querySelector('.tccms-week-heading h2').style.setProperty('--menu-gradient', style.ribbonGradient ? `linear-gradient(110deg, ${style.ribbonSecondary}, ${style.ribbonColor} 24%, ${style.ribbonColor} 76%, ${style.ribbonSecondary})` : style.ribbonColor);
        root.querySelectorAll('.tccm-school-menus, .tccms-week').forEach(node => {
            node.style.setProperty('--menu-index-width', `${style.indexWidth}%`);
            node.style.setProperty('--menu-days-height', variables['--menu-days-height']);
            node.style.setProperty('--menu-ink', style.ink);
        });
        Object.keys(format.days).forEach((day, index) => {
            const colors = style.days[day];
            root.querySelectorAll(`.tccms-color-${index}`).forEach(node => {
                node.style.setProperty('--menu-day', colors.cellBackground);
                node.style.setProperty('--menu-edge', colors.border);
                node.style.setProperty('--menu-head-ink', colors.text);
                node.style.setProperty('--menu-gradient', colors.gradient ? `linear-gradient(120deg, ${colors.background}, ${colors.secondary})` : colors.background);
            });
        });
        for (const course of Object.keys(format.courses)) {
            const colors = style.courses[course];
            root.querySelectorAll(`[data-menu-course-icon="${course}"]`).forEach(original => {
                const label = original.closest('.tccms-course') || original.closest('dt');
                label.style.setProperty('--course-background', colors.gradient ? `linear-gradient(110deg, ${colors.secondary}, ${colors.background} 24%, ${colors.background} 76%, ${colors.secondary})` : colors.background);
                label.style.setProperty('--course-text', colors.text);
                let replacement = original.parentElement.querySelector('.theme-course-icon');
                if (!replacement) { replacement = document.createElement('span'); replacement.className = 'theme-course-icon'; replacement.setAttribute('aria-hidden', 'true'); original.after(replacement); }
                original.style.display = colors.icon === 'original' ? '' : 'none';
                replacement.hidden = colors.icon === 'original';
                replacement.style.setProperty('--course-icon-image', colors.icon === 'original' ? 'none' : `url("assets/${presetIcons[colors.icon]}")`);
            });
        }
        const badgeKeys = Object.keys(format.badgeNames);
        const badgeRail = root.querySelector('.tccms-origin-rail');
        if (badgeRail && !badgeHomes.has(badgeRail)) badgeHomes.set(badgeRail, { parent: badgeRail.parentElement, next: badgeRail.nextSibling });
        if (badgeRail && badgePlacement === 'legend') {
            root.querySelector('.tccms-legend').append(badgeRail);
        } else if (badgeRail) {
            const home = badgeHomes.get(badgeRail);
            home.parent.insertBefore(badgeRail, home.next?.parentNode === home.parent ? home.next : null);
        }
        root.querySelectorAll('.tccms-origin-rail li .tccms-marker').forEach((marker, index) => {
            const key = marker.closest('li').dataset.franceBadge ?? badgeKeys[index];
            const source = style.badges.logos[key];
            let replacement = marker.querySelector('.theme-badge-image');
            if (!replacement) { replacement = document.createElement('img'); replacement.className = 'theme-badge-image'; replacement.alt = ''; marker.append(replacement); }
            marker.querySelectorAll(':scope > svg, :scope > img:not(.theme-badge-image)').forEach(original => { original.style.display = source ? 'none' : ''; });
            replacement.hidden = !source;
            if (source && replacement.getAttribute('src') !== source) replacement.src = source;
        });
    };
    window.SVJMenuRenderer = Object.freeze({ apply });
})();
