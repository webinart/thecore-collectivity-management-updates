(() => {
    const frames = new Set();
    const visible = section => !section.hidden && (!section.closest('[data-menu-archives]') || section.closest('[data-menu-archives]').open);
    const prepare = section => {
        if (section.dataset.illustratedInitialized) return;
        section.dataset.illustratedInitialized = 'true';
        const root = section.closest('[data-school-menus]');
        clearTimeout(root.tccmsLoadingTimer);
        root.dataset.illustratedLoading = 'true';
        const fallback = section.querySelector('.tccms-illustrated-fallback');
        const stage = section.querySelector('.tccms-illustrated-stage');
        if (!fallback || !stage) return;
        const frame = document.createElement('iframe');
        frame.className = 'tccms-illustrated-frame';
        frame.title = section.querySelector('h2').textContent.trim();
        frame.setAttribute('aria-hidden', 'true');
        frame.tabIndex = -1;
        frame.referrerPolicy = 'same-origin';
        stage.append(frame);
        const state = { section, stage, frame, fallback, layout: '', width: 1280, height: null, ready: false, timer: null, cacheKey: '' };
        const fit = () => {
            if (!visible(section) || stage.clientWidth < 1) return;
            const layout = stage.clientWidth < 960 ? 'mobile' : 'screen';
            state.width = layout === 'mobile' ? stage.clientWidth : 1280;
            const scale = Math.min(1, stage.clientWidth / state.width);
            frame.style.width = `${state.width}px`;
            frame.style.height = `${state.height || 794}px`;
            frame.style.transform = `scale(${scale})`;
            if (state.ready) stage.style.height = `${Math.ceil(state.height * scale)}px`;
            if (layout !== state.layout) {
                state.layout = layout;
                state.ready = false;
                stage.classList.remove('is-ready');
                stage.classList.remove('has-failed');
                stage.style.height = '';
                fallback.hidden = false;
                section.dataset.illustratedState = 'loading';
                stage.setAttribute('aria-busy', 'true');
                frame.setAttribute('aria-hidden', 'true');
                frame.tabIndex = -1;
                const source = new URL(section.dataset.illustratedUrl, location.href);
                if (source.origin !== location.origin) { fail(); return; }
                source.searchParams.set('layout', layout);
                state.cacheKey = `tccms-illustrated:${source.href}:${state.width}`;
                state.height = null;
                try {
                    const height = Number(sessionStorage.getItem(state.cacheKey));
                    if (height > 0 && height <= 50000) state.height = height;
                } catch {}
                clearTimeout(state.timer);
                state.timer = setTimeout(fail, 30000);
                frame.src = source.href;
            }
            state.cacheKey = `tccms-illustrated:${frame.src}:${state.width}`;
            if (!state.ready && state.height) stage.style.height = `${Math.ceil(state.height * scale)}px`;
        };
        const fail = () => {
            clearTimeout(state.timer);
            state.ready = false;
            fallback.hidden = false;
            stage.style.height = '';
            stage.classList.remove('is-ready');
            stage.classList.add('has-failed');
            stage.removeAttribute('aria-busy');
            section.dataset.illustratedState = 'fallback';
            frame.setAttribute('aria-hidden', 'true');
            frame.tabIndex = -1;
        };
        frame.addEventListener('error', fail);
        frame.addEventListener('load', () => {
            try { if (!frame.contentDocument?.getElementById('svj-school-menu-document')) fail(); }
            catch { fail(); }
        });
        state.fit = fit;
        state.fail = fail;
        frames.add(state);
        new ResizeObserver(fit).observe(stage);
        fit();
    };
    const scan = () => {
        document.querySelectorAll('[data-illustrated-url]').forEach(section => {
            if (visible(section)) prepare(section);
        });
        for (const state of frames) if (visible(state.section)) state.fit();
    };
    window.addEventListener('message', event => {
        if (event.origin !== location.origin) return;
        const state = [...frames].find(item => item.frame.contentWindow === event.source);
        if (!state) return;
        if (event.data?.type === 'SVJ_AUTUMN_ERROR') { state.fail(); return; }
        if (event.data?.type !== 'SVJ_AUTUMN_DIMENSIONS' || state.stage.classList.contains('has-failed') || event.data.week !== state.section.dataset.weekStart || !Number.isFinite(event.data.height) || event.data.height < 1 || event.data.height > 50000 || !Number.isFinite(event.data.width) || Math.abs(event.data.width - state.width) > 2) return;
        clearTimeout(state.timer);
        state.height = event.data.height;
        state.ready = true;
        try { sessionStorage.setItem(state.cacheKey, String(state.height)); } catch {}
        state.fallback.hidden = true;
        state.stage.classList.add('is-ready');
        state.stage.removeAttribute('aria-busy');
        state.section.dataset.illustratedState = 'ready';
        state.frame.removeAttribute('aria-hidden');
        state.frame.tabIndex = 0;
        state.fit();
    });
    document.addEventListener('tccm-school-menu-selected', scan);
    document.querySelectorAll('[data-menu-archives]').forEach(details => details.addEventListener('toggle', scan));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
    else scan();
})();
