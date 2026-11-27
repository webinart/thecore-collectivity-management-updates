(() => {
    const master = window.tccmPublicationMaster;
    let stopWatching;
    let ready = false;

    function openMaster() {
        const doc = window.elementor?.documents?.getCurrent?.();
        const api = window.elementorV2?.editorComponents;
        const store = window.elementorV2?.store?.__getStore?.();
        if (ready || !store || !api || Number(doc?.id) !== master.id || doc.config.type !== 'elementor_component') return;
        const state = store.getState();
        if (!state.components?.data.some(item => item.id === master.id) || state.components.loadStatus === 'pending') {
            if (!stopWatching) stopWatching = store.subscribe(openMaster);
            return;
        }
        ready = true;
        stopWatching?.();
        api.componentsActions.setOverridableProps(master.id, master.properties);
        api.componentsActions.setPath([{ componentId: master.id }]);
        api.componentsActions.setCurrentComponentId(master.id);
    }

    function listen() {
        window.elementor?.on('preview:loaded document:loaded', () => requestAnimationFrame(openMaster));
        openMaster();
    }
    window.jQuery(window).one('elementor:init', listen);
    window.addEventListener('load', openMaster, { once: true });
})();
