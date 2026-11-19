(() => {
    const preference = matchMedia('(prefers-reduced-motion: reduce)');
    const profiles = {
        snow: { area: 12500, limit: 90, size: [6, 16], speed: [17, 70], sway: [5, 26] },
        leaves: { area: 47000, limit: 25, size: [12, 24], speed: [17, 37], sway: [12, 34] },
        bubbles: { area: 39000, limit: 28, size: [8, 22], speed: [12, 32], sway: [3, 12] }
    };
    const between = (min, max) => min + Math.random() * (max - min);
    const layer = document.createElement('div');
    layer.className = 'menu-motion';
    layer.setAttribute('aria-hidden', 'true');
    const toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'menu-motion-toggle';

    let lastConfig;
    let lastWidth, lastHeight;
    let paused = false;
    let visible = false;
    let scene;
    const sync = () => {
        layer.dataset.paused = String(paused || !visible || document.hidden);
        toggle.textContent = paused ? 'Reprendre l’animation' : 'Mettre l’animation en pause';
    };
    toggle.addEventListener('click', () => { paused = !paused; sync(); });
    const observer = new IntersectionObserver(entries => {
        visible = entries[entries.length - 1].isIntersecting;
        sync();
    });
    document.addEventListener('visibilitychange', sync);
    preference.addEventListener('change', () => { if (scene) update(...scene); });

    function clear() {
        if (!layer.isConnected) return;
        observer.disconnect();
        layer.remove();
        layer.replaceChildren();
        toggle.remove();
        visible = false;
        lastConfig = undefined;
    }

    function update(paper, effect, mode, heading, table) {
        scene = [paper, effect, mode, heading, table];
        if (!effect || !Object.hasOwn(profiles, effect.mode) || mode === 'print' || preference.matches || !table || !heading) {
            clear();
            return;
        }
        const bounds = table.getBoundingClientRect();
        const top = heading.getBoundingClientRect().top;
        const origin = paper.getBoundingClientRect();
        const height = Math.round(bounds.bottom - top);
        const width = Math.round(bounds.width);
        if (height <= 0 || width <= 0) { clear(); return; }
        if (!layer.isConnected) {
            paper.append(layer, toggle);
            observer.observe(layer);
            sync();
        }
        Object.assign(layer.style, { top: (top - origin.top) + 'px', left: (bounds.left - origin.left) + 'px', width: width + 'px', height: height + 'px' });
        if (lastConfig && Object.keys(effect).every(key => effect[key] === lastConfig[key]) && width === lastWidth && height === lastHeight) return;
        lastConfig = { ...effect };
        lastWidth = width;
        lastHeight = height;
        const profile = profiles[effect.mode];
        const density = { low: .55, normal: 1, high: 1.5 }[effect.density];
        const speed = { slow: .74, normal: 1, fast: 1.4 }[effect.speed];
        const edges = effect.zone === 'edges';
        const count = Math.max(3, Math.min(profile.limit, Math.round(width * height * density * (edges ? .55 : 1) / profile.area)));
        const distance = height + 64;
        layer.dataset.mode = effect.mode;
        layer.style.setProperty('--travel', distance + 'px');
        for (const [key, image] of [['image', effect.image], ['variant', effect.variant || effect.image]]) {
            if (image) layer.style.setProperty('--motif-' + key, 'url("' + image + '")');
            else layer.style.removeProperty('--motif-' + key);
        }
        const particles = document.createDocumentFragment();
        for (let i = 0; i < count; i++) {
            const carrier = document.createElement('span');
            const motif = document.createElement('i');
            const lane = (i + Math.random()) / count;
            const x = edges ? (i % 2 ? 100 - lane * 12 : lane * 12) : lane * 100;
            const duration = distance / (between(...profile.speed) * speed);
            carrier.style.left = x + '%';
            carrier.style.animationDuration = duration + 's';
            carrier.style.animationDelay = -Math.random() * duration + 's';
            carrier.style.opacity = between(.53, .82);
            motif.style.setProperty('--size', between(...profile.size) + 'px');
            motif.style.setProperty('--sway', between(...profile.sway) * (i % 2 ? 1 : -1) + 'px');
            motif.style.animationDuration = between(2.6, 6.3) / speed + 's';
            motif.style.animationDelay = -between(0, 6) + 's';
            carrier.append(motif);
            particles.append(carrier);
        }
        layer.replaceChildren(particles);
    }
    window.SVJMenuMotion = { update };
})();
