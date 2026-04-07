(() => {
	const FACET_KEYS = ["universes", "categories", "themes", "audiences", "territories", "transportModes"];
	const FILTER_TYPE_POINT = "point";
	const FILTER_TYPE_ROUTE = "route";
	const FILTER_TYPE_TRANSPORT_ROUTE = "transport_route";
	const DEFAULT_TYPES = [FILTER_TYPE_POINT, FILTER_TYPE_ROUTE, FILTER_TYPE_TRANSPORT_ROUTE];

	const createDefaultState = () => ({
		search: "",
		accessibleOnly: false,
		types: new Set(DEFAULT_TYPES),
		universes: "",
		categories: "",
		themes: "",
		audiences: "",
		territories: "",
		transportModes: ""
	});

	const cloneState = (state) => ({
		...createDefaultState(),
		...(state || {}),
		types: new Set(state && state.types ? Array.from(state.types) : DEFAULT_TYPES)
	});

	class TheCoreCollectivityMapStore {
		constructor(groupId) {
			this.groupId = groupId;
			this.state = createDefaultState();
			this.context = {
				facets: {},
				hasAccessible: false,
				hasPoints: false,
				hasRoutes: false,
				hasMapRoutes: false,
				hasTransportRoutes: false,
				availableTypes: DEFAULT_TYPES,
				totalCount: 0
			};
			this.results = {
				visibleCount: 0,
				layerCount: 0
			};
			this.subscribers = new Set();
		}

		subscribe(callback) {
			this.subscribers.add(callback);
			callback(this.getSnapshot());

			return () => {
				this.subscribers.delete(callback);
			};
		}

		getSnapshot() {
			return {
				state: cloneState(this.state),
				context: {
					...this.context,
					facets: { ...(this.context.facets || {}) }
				},
				results: { ...this.results }
			};
		}

		notify() {
			const snapshot = this.getSnapshot();
			this.subscribers.forEach((callback) => callback(snapshot));
		}

		setContext(context) {
			this.context = {
				facets: {},
				hasAccessible: false,
				hasPoints: false,
				hasRoutes: false,
				hasMapRoutes: false,
				hasTransportRoutes: false,
				availableTypes: DEFAULT_TYPES,
				totalCount: 0,
				...(context || {}),
				facets: { ...((context && context.facets) || {}) }
			};
			this.normalizeStateAgainstContext();
			this.notify();
		}

		setResults(results) {
			this.results = {
				...this.results,
				...(results || {})
			};
			this.notify();
		}

		setState(patch) {
			this.state = {
				...this.state,
				...(patch || {})
			};
			this.notify();
		}

		toggleType(type) {
			if (!type) {
				return;
			}

			const nextTypes = new Set(this.state.types || DEFAULT_TYPES);
			if (nextTypes.has(type)) {
				if (nextTypes.size === 1) {
					return;
				}
				nextTypes.delete(type);
			} else {
				nextTypes.add(type);
			}

			this.state.types = nextTypes;
			this.notify();
		}

		reset() {
			this.state = createDefaultState();
			this.normalizeStateAgainstContext();
			this.notify();
		}

		normalizeStateAgainstContext() {
			const facets = this.context.facets || {};
			FACET_KEYS.forEach((key) => {
				const value = this.state[key];
				if (!value) {
					return;
				}

				const values = Array.isArray(facets[key]) ? facets[key] : [];
				const exists = values.some((item) => item.slug === value);
				if (!exists) {
					this.state[key] = "";
				}
			});

			if (!this.context.hasAccessible) {
				this.state.accessibleOnly = false;
			}

			const availableTypes = new Set();
			if (this.context.hasPoints) {
				availableTypes.add(FILTER_TYPE_POINT);
			}
			if (this.context.hasMapRoutes) {
				availableTypes.add(FILTER_TYPE_ROUTE);
			}
			if (this.context.hasTransportRoutes) {
				availableTypes.add(FILTER_TYPE_TRANSPORT_ROUTE);
			}

			if (!availableTypes.size) {
				this.state.types = new Set(DEFAULT_TYPES);
				return;
			}

			const nextTypes = Array.from(this.state.types || []).filter((type) => availableTypes.has(type));
			this.state.types = new Set(nextTypes.length ? nextTypes : Array.from(availableTypes));
		}
	}

	const mapStores = new Map();
	const getMapStore = (groupId) => {
		const normalizedGroupId = groupId || "tccm-map-default";
		if (!mapStores.has(normalizedGroupId)) {
			mapStores.set(normalizedGroupId, new TheCoreCollectivityMapStore(normalizedGroupId));
		}

		return mapStores.get(normalizedGroupId);
	};

	class TheCoreCollectivityMapControls {
		constructor(root) {
			this.root = root;
			this.groupId = "";
			this.store = null;
			this.showSearch = true;
			this.showFilters = true;
			this.showSummary = true;
			this.countElement = root.querySelector("[data-map-count]");
			this.filterSearch = root.querySelector("[data-filter-search]");
			this.filterAccessible = root.querySelector("[data-filter-accessible]");
			this.resetButton = root.querySelector("[data-filter-reset]");
			this.typeButtons = Array.from(root.querySelectorAll("[data-filter-type]"));
			this.selectNodes = {
				universes: root.querySelector('[data-filter-select="universes"]'),
				categories: root.querySelector('[data-filter-select="categories"]'),
				themes: root.querySelector('[data-filter-select="themes"]'),
				audiences: root.querySelector('[data-filter-select="audiences"]'),
				territories: root.querySelector('[data-filter-select="territories"]'),
				transportModes: root.querySelector('[data-filter-select="transportModes"]')
			};
			this.unsubscribe = null;
		}

		init() {
			this.syncAttributes();
			this.bindEvents();
			this.unsubscribe = this.store.subscribe((snapshot) => this.sync(snapshot));
		}

		refresh() {
			const previousGroupId = this.groupId;
			this.syncAttributes();

			if (previousGroupId !== this.groupId && typeof this.unsubscribe === "function") {
				this.unsubscribe();
				this.unsubscribe = this.store.subscribe((snapshot) => this.sync(snapshot));
				return;
			}

			this.sync(this.store.getSnapshot());
		}

		destroy() {
			if (typeof this.unsubscribe === "function") {
				this.unsubscribe();
			}

			if (this.root && this.root.__tccmMapControlsInstance === this) {
				delete this.root.__tccmMapControlsInstance;
			}
		}

		syncAttributes() {
			this.groupId = this.root.getAttribute("data-map-group") || "tccm-map-default";
			this.store = getMapStore(this.groupId);
			this.showSearch = this.root.getAttribute("data-show-search") === "1";
			this.showFilters = this.root.getAttribute("data-show-filters") === "1";
			this.showSummary = this.root.getAttribute("data-show-summary") === "1";
		}

		bindEvents() {
			if (this.filterSearch) {
				this.filterSearch.addEventListener("input", () => {
					this.store.setState({
						search: (this.filterSearch.value || "").trim().toLowerCase()
					});
				});
			}

			if (this.filterAccessible) {
				this.filterAccessible.addEventListener("change", () => {
					this.store.setState({
						accessibleOnly: !!this.filterAccessible.checked
					});
				});
			}

			this.typeButtons.forEach((button) => {
				button.addEventListener("click", () => {
					const type = button.getAttribute("data-filter-type");
					this.store.toggleType(type);
				});
			});

			Object.keys(this.selectNodes).forEach((key) => {
				const select = this.selectNodes[key];
				if (!select) {
					return;
				}

				select.addEventListener("change", () => {
					this.store.setState({
						[key]: select.value || ""
					});
				});
			});

			if (this.resetButton) {
				this.resetButton.addEventListener("click", () => this.store.reset());
			}
		}

		sync(snapshot) {
			const state = snapshot.state || createDefaultState();
			const context = snapshot.context || {};
			const results = snapshot.results || {};
			this.populateFacetSelects(context.facets || {}, state);
			this.syncVisibility(context);
			this.syncState(state, context);
			this.syncCount(results);
		}

		populateFacetSelects(facets, state) {
			const labels = {
				universes: "Tous les univers",
				categories: "Toutes les catégories",
				themes: "Tous les thèmes",
				audiences: "Tous les publics",
				territories: "Tous les territoires",
				transportModes: "Tous les modes de transport"
			};

			Object.keys(this.selectNodes).forEach((key) => {
				const select = this.selectNodes[key];
				if (!select) {
					return;
				}

				const values = Array.isArray(facets[key]) ? facets[key] : [];
				const signature = JSON.stringify(values);
				if (select.dataset.optionsSignature !== signature) {
					select.innerHTML = "";
					const defaultOption = document.createElement("option");
					defaultOption.value = "";
					defaultOption.textContent = labels[key];
					select.appendChild(defaultOption);

					values.forEach((item) => {
						const option = document.createElement("option");
						option.value = item.slug;
						option.textContent = item.name;
						select.appendChild(option);
					});

					select.dataset.optionsSignature = signature;
				}

				select.value = state[key] || "";
			});
		}

		syncVisibility(context) {
			const wraps = {
				search: this.root.querySelector('[data-filter-wrap="search"]'),
				types: this.root.querySelector('[data-filter-wrap="types"]'),
				facets: this.root.querySelector('[data-filter-wrap="facets"]'),
				accessible: this.root.querySelector('[data-filter-wrap="accessible"]'),
				summary: this.root.querySelector("[data-filter-summary]")
			};

			if (wraps.search) {
				wraps.search.hidden = !this.showSearch;
			}

			const availableTypes = Array.isArray(context.availableTypes) ? context.availableTypes : [];
			if (wraps.types) {
				wraps.types.hidden = !this.showFilters || availableTypes.length <= 1;
			}

			if (wraps.facets) {
				wraps.facets.hidden = !this.showFilters;
			}

			if (wraps.accessible) {
				wraps.accessible.hidden = !this.showFilters || !context.hasAccessible;
			}

			if (wraps.summary) {
				wraps.summary.hidden = !this.showSummary;
			}

			if (this.resetButton) {
				this.resetButton.hidden = !this.showSearch && !this.showFilters;
			}

			Object.keys(this.selectNodes).forEach((key) => {
				const select = this.selectNodes[key];
				if (!select) {
					return;
				}

				const values = Array.isArray((context.facets || {})[key]) ? context.facets[key] : [];
				select.hidden = !this.showFilters || values.length <= 1;
			});
		}

		syncState(state, context = {}) {
			if (this.filterSearch && this.filterSearch.value !== state.search) {
				this.filterSearch.value = state.search || "";
			}

			if (this.filterAccessible) {
				this.filterAccessible.checked = !!state.accessibleOnly;
			}

			const availableTypes = Array.isArray(context.availableTypes) ? context.availableTypes : [];
			this.typeButtons.forEach((button) => {
				const type = button.getAttribute("data-filter-type");
				button.hidden = availableTypes.length ? !availableTypes.includes(type) : false;
				button.classList.toggle("is-active", state.types.has(type));
			});
		}

		syncCount(results) {
			if (!this.countElement) {
				return;
			}

			this.countElement.textContent = String(results.visibleCount || 0);
		}
	}

	class TheCoreCollectivityMap {
		constructor(root) {
			this.root = root;
			this.groupId = root.getAttribute("data-map-group") || root.id || "tccm-map-default";
			this.store = getMapStore(this.groupId);
			this.dataNode = root.querySelector(".tccm-map__data");
			this.mapElement = root.querySelector("[data-map]");
			this.emptyElement = root.querySelector("[data-map-empty]");
			this.controlsRoot = root.querySelector("[data-map-controls]");
			this.editorPreview = root.querySelector("[data-editor-style-preview]");
			this.editorPreviewItems = root.querySelector("[data-editor-style-preview-items]");
			this.editorDebug = root.querySelector("[data-editor-debug]");
			this.payload = this.readPayload();
			this.items = Array.isArray(this.payload.items) ? this.payload.items : [];
			this.facets = this.payload.facets || {};
			this.categoryStyles = this.payload.categoryStyles || {};
			this.routeStyles = this.payload.routeStyles || {};
			this.viewSettings = this.payload.view || { mode: "fit_bounds" };
			this.map = null;
			this.layers = null;
			this.controls = null;
			this.unsubscribe = null;
			this.state = cloneState(this.store.state);
			this.lastStateSignature = "";
			this.hasAppliedManualView = false;
		}

		readPayload() {
			if (!this.dataNode) {
				return { items: [], facets: {} };
			}

			try {
				const raw = this.dataNode.textContent || this.dataNode.innerHTML || "{}";
				return JSON.parse(raw);
			} catch (error) {
				const legacyNode = this.root.querySelector('script.tccm-map__data[type="application/json"]');
				if (legacyNode && legacyNode !== this.dataNode) {
					try {
						return JSON.parse(legacyNode.textContent || "{}");
					} catch (legacyError) {
						return { items: [], facets: {} };
					}
				}

				return { items: [], facets: {} };
			}
		}

		init() {
			this.store.setContext(this.buildContext());

			if (this.controlsRoot) {
				this.controls = this.controlsRoot.__tccmMapControlsInstance || null;
				if (!this.controls) {
					this.controls = new TheCoreCollectivityMapControls(this.controlsRoot);
					this.controlsRoot.__tccmMapControlsInstance = this.controls;
					this.controls.init();
				}
			}

			this.unsubscribe = this.store.subscribe((snapshot) => {
				const nextState = cloneState(snapshot.state);
				const nextSignature = this.getStateSignature(nextState);
				const shouldRender = nextSignature !== this.lastStateSignature;
				this.state = nextState;
				this.lastStateSignature = nextSignature;

				if (this.map && this.layers && shouldRender) {
					this.render();
				}
			});

			if (!this.mapElement || !window.L) {
				this.reportInitFailure({
					hasMapElement: !!this.mapElement,
					hasLeaflet: !!window.L,
					payloadCount: Array.isArray(this.items) ? this.items.length : 0
				});
				return;
			}

			this.renderEditorStylePreview();
			this.initMap();
			this.render();
		}

		reportInitFailure(data) {
			if (this.editorPreview) {
				this.editorPreview.hidden = false;
			}

			if (this.editorDebug) {
				const payloadCount = typeof data.payloadCount === "number" ? data.payloadCount : 0;
				const reasons = [];
				if (!data.hasMapElement) {
					reasons.push("mapElement=absent");
				}
				if (!data.hasLeaflet) {
					reasons.push("leaflet=absent");
				}

				this.editorDebug.textContent =
					"Debug éditeur: payload=" + payloadCount +
					" | init=ko" +
					(reasons.length ? " | " + reasons.join(" | ") : "");
				this.editorDebug.hidden = false;
			}
		}

		destroy() {
			if (typeof this.unsubscribe === "function") {
				this.unsubscribe();
				this.unsubscribe = null;
			}

			if (this.controls) {
				this.controls.destroy();
				this.controls = null;
			}

			if (this.map) {
				this.map.remove();
				this.map = null;
				this.layers = null;
			}

			if (this.root && this.root.__tccmMapInstance === this) {
				delete this.root.__tccmMapInstance;
			}
		}

		buildContext() {
			const hasPoints = this.items.some((item) => item.geometryType === "point");
			const hasMapRoutes = this.items.some((item) => this.getFilterType(item) === FILTER_TYPE_ROUTE);
			const hasTransportRoutes = this.items.some((item) => this.getFilterType(item) === FILTER_TYPE_TRANSPORT_ROUTE);
			const availableTypes = [];
			if (hasPoints) {
				availableTypes.push(FILTER_TYPE_POINT);
			}
			if (hasMapRoutes) {
				availableTypes.push(FILTER_TYPE_ROUTE);
			}
			if (hasTransportRoutes) {
				availableTypes.push(FILTER_TYPE_TRANSPORT_ROUTE);
			}

			return {
				facets: this.facets || {},
				hasAccessible: this.items.some((item) => item.isAccessible),
				hasPoints,
				hasRoutes: hasMapRoutes || hasTransportRoutes,
				hasMapRoutes,
				hasTransportRoutes,
				availableTypes,
				totalCount: this.items.length
			};
		}

		getStateSignature(state) {
			return JSON.stringify({
				search: state.search || "",
				accessibleOnly: !!state.accessibleOnly,
				types: Array.from(state.types || []).sort(),
				universes: state.universes || "",
				categories: state.categories || "",
				themes: state.themes || "",
				audiences: state.audiences || "",
				territories: state.territories || "",
				transportModes: state.transportModes || ""
			});
		}

		initMap() {
			this.map = window.L.map(this.mapElement, {
				scrollWheelZoom: true,
				attributionControl: true,
				zoomSnap: 0.25,
				zoomDelta: 0.5
			});

			window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
			}).addTo(this.map);

			this.layers = window.L.featureGroup().addTo(this.map);
			this.applyInitialMapView();
			if (this.map.dragging) {
				this.map.dragging.enable();
			}
			if (this.map.touchZoom) {
				this.map.touchZoom.enable();
			}
		}

		applyInitialMapView() {
			const manualView = this.getManualViewSettings();
			if (manualView) {
				this.map.setView([manualView.lat, manualView.lng], manualView.zoom);
				this.hasAppliedManualView = true;
				return;
			}

			this.map.setView([48.8584, 2.2945], 12);
		}

		getManualViewSettings() {
			if (!this.viewSettings || this.viewSettings.mode !== "manual") {
				return null;
			}

			const lat = typeof this.viewSettings.lat === "number" ? this.viewSettings.lat : null;
			const lng = typeof this.viewSettings.lng === "number" ? this.viewSettings.lng : null;
			const zoom = typeof this.viewSettings.zoom === "number" ? this.viewSettings.zoom : 13;

			if (lat === null || lng === null) {
				return null;
			}

			return {
				lat,
				lng,
				zoom: Math.max(1, Math.min(20, zoom))
			};
		}

		filterItems() {
			return this.items.filter((item) => {
				if (!this.state.types.has(this.getFilterType(item))) {
					return false;
				}

				if (this.state.accessibleOnly && !item.isAccessible) {
					return false;
				}

				if (this.state.search && !(item.searchText || "").toLowerCase().includes(this.state.search)) {
					return false;
				}

				return ["universes", "categories", "themes", "audiences", "territories", "transportModes"].every((key) => {
					const value = this.state[key];
					if (!value) {
						return true;
					}
					const terms = Array.isArray(item[key]) ? item[key] : [];
					return terms.some((term) => term.slug === value);
				});
			});
		}

		getFilterType(item) {
			if (!item) {
				return FILTER_TYPE_POINT;
			}

			if (item.geometryType === "route" && item.source === "transport") {
				return FILTER_TYPE_TRANSPORT_ROUTE;
			}

			if (item.geometryType === "route") {
				return FILTER_TYPE_ROUTE;
			}

			return FILTER_TYPE_POINT;
		}

		render() {
			if (!this.map || !this.layers) {
				this.store.setResults({
					visibleCount: 0,
					layerCount: 0
				});
				this.renderEditorDebug(0, 0);
				return;
			}

			const items = this.filterItems();
			this.layers.clearLayers();

			items.forEach((item) => {
				if (item.geometryType === "point") {
					this.renderPoint(item);
					return;
				}

				if (item.geometryType === "route") {
					this.renderRoute(item);
				}
			});

			const hasLayers = this.layers.getLayers().length > 0;
			this.store.setResults({
				visibleCount: items.length,
				layerCount: this.layers.getLayers().length
			});
			this.renderEditorDebug(items.length, this.layers.getLayers().length);
			if (this.emptyElement) {
				this.emptyElement.hidden = hasLayers;
			}

			if (hasLayers) {
				const bounds = this.layers.getBounds();
				const manualView = this.getManualViewSettings();
				if (manualView) {
					if (!this.hasAppliedManualView) {
						this.map.setView([manualView.lat, manualView.lng], manualView.zoom);
						this.hasAppliedManualView = true;
					}
				} else if (bounds.isValid()) {
					this.map.fitBounds(bounds, {
						padding: [32, 32],
						maxZoom: 15
					});
				}
			}
		}

		renderEditorDebug(filteredCount, layerCount) {
			if (!this.editorDebug) {
				return;
			}

			const payloadCount = Array.isArray(this.items) ? this.items.length : 0;
			const activeTypes = Array.from(this.state.types).join(",") || "none";
			this.editorDebug.textContent =
				"Debug éditeur: payload=" + payloadCount +
				" | filtres=" + filteredCount +
				" | calques=" + layerCount +
				" | types=" + activeTypes;
			this.editorDebug.hidden = false;
		}

		renderPoint(item) {
			if (typeof item.latitude !== "number" || typeof item.longitude !== "number") {
				return;
			}

			const marker = window.L.marker([item.latitude, item.longitude], {
				icon: this.createPointIcon(item),
				zIndexOffset: item.source === "transport" ? 800 : 0
			});

			marker.bindPopup(this.buildPopupMarkup(item), {
				maxWidth: 320,
				className: "tccm-map__popup"
			});

			this.layers.addLayer(marker);
		}

		renderRoute(item) {
			if (!item.geojson) {
				return;
			}

			const displayedGeojson = this.getDisplayedRouteGeoJson(item);
			if (!displayedGeojson) {
				return;
			}

			const routeStyle = this.getRouteStyle(item);
			const routeLayer = window.L.geoJSON(displayedGeojson, {
				style: () => routeStyle,
				onEachFeature: (feature, layer) => {
					layer.bindPopup(this.buildPopupMarkup(item), {
						maxWidth: 320,
						className: "tccm-map__popup"
					});
				}
			});

			this.layers.addLayer(routeLayer);

			const markerLatLng = this.getRouteMarkerLatLng(item, displayedGeojson, displayedGeojson !== item.geojson);
			if (markerLatLng && !item.hideRouteMarker) {
				const routeMarker = window.L.marker(markerLatLng, {
					icon: this.createRouteIcon(item),
					zIndexOffset: item.source === "transport" ? 900 : 100
				});

				routeMarker.bindPopup(this.buildPopupMarkup(item), {
					maxWidth: 320,
					className: "tccm-map__popup"
				});

				this.layers.addLayer(routeMarker);
			}

			this.renderRouteAdditionalIcons(item, displayedGeojson);
		}

		createPointIcon(item) {
			const color = this.getItemColor(item, "#2f855a");
			const iconMarkup = this.getPointIconMarkup(item);
			const isTransport = item.source === "transport";
			const wrapperClass = isTransport
				? "tccm-map__marker-wrapper tccm-map__marker-wrapper--transport"
				: "tccm-map__marker-wrapper";
			const markerClass = isTransport
				? "tccm-map__marker tccm-map__marker--transport"
				: "tccm-map__marker";
			const iconSize = isTransport ? [40, 40] : [34, 34];
			const iconAnchor = isTransport ? [20, 20] : [17, 17];

			return window.L.divIcon({
				className: wrapperClass,
				html: '<span class="' + markerClass + '" style="' + this.escapeAttr(this.getMarkerStyleValue(color, isTransport ? 40 : 34)) + '">' + iconMarkup + "</span>",
				iconSize,
				iconAnchor,
				popupAnchor: [0, -18]
			});
		}

		createRouteIcon(item) {
			const color = this.getItemColor(item, "#1d4ed8");
			const iconMarkup = this.getRouteIconMarkup(item);

			return window.L.divIcon({
				className: "tccm-map__marker-wrapper tccm-map__marker-wrapper--route",
				html: '<span class="tccm-map__marker tccm-map__marker--route" style="' + this.escapeAttr(this.getMarkerStyleValue(color, 38)) + '">' + iconMarkup + "</span>",
				iconSize: [38, 38],
				iconAnchor: [19, 19],
				popupAnchor: [0, -18]
			});
		}

		createRouteAdditionalIcon(item, iconDefinition) {
			const size = this.getRouteAdditionalIconSize(iconDefinition);
			const color = iconDefinition && iconDefinition.color ? iconDefinition.color : this.getItemColor(item, "#1d4ed8");
			const iconMarkup = this.getRouteAdditionalIconMarkup(item, iconDefinition);
			const hasOutline = !!(iconDefinition && iconDefinition.withOutline);

			return window.L.divIcon({
				className: "tccm-map__marker-wrapper tccm-map__marker-wrapper--route",
				html: '<span class="tccm-map__marker tccm-map__marker--route' + (hasOutline ? ' tccm-map__marker--outlined' : '') + '" style="' + this.escapeAttr(this.getMarkerStyleValue(color, size, {
					withOutline: hasOutline
				})) + '">' + iconMarkup + "</span>",
				iconSize: [size, size],
				iconAnchor: [Math.round(size / 2), Math.round(size / 2)],
				popupAnchor: [0, Math.max(-18, -Math.round(size / 2))]
			});
		}

		getMarkerStyleValue(color, size, options = {}) {
			const resolvedSize = Number.isFinite(size) && size > 0 ? size : 34;
			const iconSize = Math.max(16, Math.round(resolvedSize * 0.53));
			const fontSize = Math.max(11, Math.round(resolvedSize * 0.32));
			const hasOutline = !!options.withOutline;

			return [
				"--tccm-marker-color:" + String(color || "#1d4ed8"),
				"--tccm-marker-size:" + resolvedSize + "px",
				"--tccm-marker-icon-size:" + iconSize + "px",
				"--tccm-marker-font-size:" + fontSize + "px",
				"--tccm-marker-border-width:" + (hasOutline ? "3px" : "0px"),
				"--tccm-marker-border-color:rgba(255, 255, 255, 0.96)"
			].join(";");
		}

		renderEditorStylePreview() {
			if (!this.editorPreview || !this.editorPreviewItems) {
				return;
			}

			const categoryStyles = this.categoryStyles && typeof this.categoryStyles === "object" ? Object.entries(this.categoryStyles) : [];
			const routeStyles = this.routeStyles && typeof this.routeStyles === "object" ? Object.entries(this.routeStyles) : [];
			const styles = categoryStyles.concat(routeStyles);
			if (!styles.length) {
				this.editorPreview.hidden = true;
				this.editorPreviewItems.innerHTML = "";
				return;
			}

			this.editorPreviewItems.innerHTML = styles
				.map(([slug, style]) => {
					const color = style && style.color ? style.color : "#2f855a";
					const iconMarkup = this.getStyleIconMarkup(slug, style || {});
					const label = this.escapeHtml((style && style.name) || slug || "Style");
					return (
						'<span class="tccm-map__editor-preview-item">' +
							'<span class="tccm-map__marker" style="--tccm-marker-color:' + this.escapeAttr(color) + ';">' + iconMarkup + '</span>' +
							'<span class="tccm-map__editor-preview-label">' + label + '</span>' +
						'</span>'
					);
				})
				.join("");
			this.editorPreview.hidden = false;
		}

		getStyleIconMarkup(slug, style) {
			const iconType = style && style.iconType ? style.iconType : "default";

			if (iconType === "elementor_icon" && style && style.elementorIconHtml) {
				return '<span class="tccm-map__marker-icon" aria-hidden="true">' + style.elementorIconHtml + "</span>";
			}

			if (iconType === "label" || iconType === "default") {
				const rawLabel = iconType === "label" && style && style.label ? style.label : String(slug || "M").slice(0, 2).toUpperCase();
				return '<span class="tccm-map__marker-label">' + this.escapeHtml(rawLabel) + "</span>";
			}

			if (iconType === "custom_svg" && style && style.customSvg) {
				return '<span class="tccm-map__marker-icon" aria-hidden="true">' + style.customSvg + "</span>";
			}

			return '<span class="tccm-map__marker-icon" aria-hidden="true">' + this.getSvgIcon(iconType) + "</span>";
		}

		getPointIconMarkup(item) {
			const style = this.getItemStyle(item);
			const iconType = style && style.iconType ? style.iconType : (item.defaultIconType || "default");

			if (iconType === "elementor_icon" && style && style.elementorIconHtml) {
				return '<span class="tccm-map__marker-icon" aria-hidden="true">' + style.elementorIconHtml + "</span>";
			}

			if (iconType === "label" || iconType === "default") {
				const label = iconType === "label" && style && style.label ? style.label : this.getPointLabel(item);
				return '<span class="tccm-map__marker-label">' + this.escapeHtml(label) + "</span>";
			}

			if (iconType === "custom_svg" && style && style.customSvg) {
				return '<span class="tccm-map__marker-icon" aria-hidden="true">' + style.customSvg + "</span>";
			}

			return '<span class="tccm-map__marker-icon" aria-hidden="true">' + this.getSvgIcon(iconType) + "</span>";
		}

		getRouteIconMarkup(item) {
			const style = this.getItemStyle(item);
			const defaultIconType = item.defaultIconType || "route";
			const iconType = style && style.iconType ? style.iconType : defaultIconType;

			if (iconType === "elementor_icon" && style && style.elementorIconHtml) {
				return '<span class="tccm-map__marker-icon" aria-hidden="true">' + style.elementorIconHtml + "</span>";
			}

			if (iconType === "label") {
				const label = style && style.label ? style.label : this.getPointLabel(item);
				return '<span class="tccm-map__marker-label">' + this.escapeHtml(label) + "</span>";
			}

			if (iconType === "custom_svg" && style && style.customSvg) {
				return '<span class="tccm-map__marker-icon" aria-hidden="true">' + style.customSvg + "</span>";
			}

			return '<span class="tccm-map__marker-icon" aria-hidden="true">' + this.getSvgIcon(iconType === "default" ? defaultIconType : iconType) + "</span>";
		}

		getRouteAdditionalIconMarkup(item, iconDefinition) {
			const iconType = iconDefinition && iconDefinition.iconType ? iconDefinition.iconType : "route";
			const fallbackIconType = item && item.defaultIconType ? item.defaultIconType : "route";

			if (iconType === "elementor_icon" && iconDefinition && iconDefinition.elementorIconHtml) {
				return '<span class="tccm-map__marker-icon" aria-hidden="true">' + iconDefinition.elementorIconHtml + "</span>";
			}

			if (iconType === "label") {
				const label = iconDefinition && iconDefinition.label ? iconDefinition.label : this.getPointLabel(item);
				return '<span class="tccm-map__marker-label">' + this.escapeHtml(label) + "</span>";
			}

			if (iconType === "custom_svg" && iconDefinition && iconDefinition.customSvg) {
				return '<span class="tccm-map__marker-icon" aria-hidden="true">' + iconDefinition.customSvg + "</span>";
			}

			return '<span class="tccm-map__marker-icon" aria-hidden="true">' + this.getSvgIcon(iconType === "default" ? fallbackIconType : iconType) + "</span>";
		}

		getPointLabel(item) {
			if (item.iconKey) {
				return item.iconKey.slice(0, 2).toUpperCase();
			}

			const firstCategory = Array.isArray(item.categories) && item.categories[0] ? item.categories[0].name : "";
			const source = firstCategory || item.title || "M";
			return source.replace(/[^A-Za-zÀ-ÿ0-9]/g, "").slice(0, 2).toUpperCase() || "M";
		}

		getPrimaryCategorySlug(item) {
			return Array.isArray(item.categories) && item.categories[0] ? item.categories[0].slug : "";
		}

		getCategoryStyle(item) {
			const categorySlug = this.getPrimaryCategorySlug(item);
			if (!categorySlug || !this.categoryStyles || typeof this.categoryStyles !== "object") {
				return null;
			}

			return this.categoryStyles[categorySlug] || null;
		}

		getRouteSpecificStyle(item) {
			if (!item || item.geometryType !== "route" || !this.routeStyles || typeof this.routeStyles !== "object") {
				return null;
			}

			const itemId = item.id !== undefined && item.id !== null ? String(item.id) : "";
			if (!itemId) {
				return null;
			}

			return this.routeStyles[itemId] || null;
		}

		getRouteAdditionalIcons(item) {
			const style = this.getItemStyle(item) || {};
			return Array.isArray(style.routeIcons) ? style.routeIcons : [];
		}

		getItemStyle(item) {
			const categoryStyle = this.getCategoryStyle(item) || null;
			const routeStyle = this.getRouteSpecificStyle(item);

			if (!routeStyle) {
				return categoryStyle;
			}

			return {
				...(categoryStyle || {}),
				...routeStyle
			};
		}

		getRelatedTransportLineStyle(item) {
			if (!item || item.source !== "transport" || item.geometryType !== "point" || !this.routeStyles || typeof this.routeStyles !== "object") {
				return null;
			}

			const relatedLineIds = Array.isArray(item.relatedLineIds)
				? item.relatedLineIds.map((value) => parseInt(value, 10)).filter((value) => Number.isFinite(value) && value > 0)
				: [];

			if (relatedLineIds.length !== 1) {
				return null;
			}

			return this.routeStyles["transport-line-" + relatedLineIds[0]] || null;
		}

		getItemColor(item, fallback) {
			const style = this.getItemStyle(item);
			if (style && style.color) {
				return style.color;
			}

			const inheritedTransportStyle = this.getRelatedTransportLineStyle(item);
			if (inheritedTransportStyle && inheritedTransportStyle.color) {
				return inheritedTransportStyle.color;
			}

			return item.accentColor || fallback;
		}

		getRouteStyle(item) {
			const style = this.getItemStyle(item) || {};

			return {
				color: this.getItemColor(item, "#1d4ed8"),
				weight: style.routeWeight || 4,
				opacity: 0.9,
				dashArray: style.routeDashArray || "",
				lineCap: "round",
				lineJoin: "round"
			};
		}

		getRouteTrimSettings(item) {
			const style = this.getItemStyle(item) || {};
			let trimStart = typeof style.routeTrimStart === "number" ? style.routeTrimStart : 0;
			let trimEnd = typeof style.routeTrimEnd === "number" ? style.routeTrimEnd : 0;

			trimStart = Math.max(0, Math.min(100, trimStart));
			trimEnd = Math.max(0, Math.min(100, trimEnd));

			let startRatio = trimStart / 200;
			let endRatio = trimEnd / 200;

			if ((startRatio + endRatio) >= 0.99) {
				const scale = 0.99 / (startRatio + endRatio);
				startRatio *= scale;
				endRatio *= scale;
			}

			return {
				startRatio,
				endRatio
			};
		}

		getDisplayedRouteGeoJson(item) {
			if (!item || !item.geojson) {
				return null;
			}

			const baseGeojson = this.getDisplayBaseRouteGeoJson(item);
			const trim = this.getRouteTrimSettings(item);
			if (trim.startRatio <= 0 && trim.endRatio <= 0) {
				return baseGeojson;
			}

			return this.trimGeoJson(baseGeojson, trim.startRatio, trim.endRatio);
		}

		getDisplayBaseRouteGeoJson(item) {
			if (!item || !item.geojson) {
				return null;
			}

			if (item.source !== "transport" || item.geojson.type !== "FeatureCollection" || !Array.isArray(item.geojson.features) || item.geojson.features.length <= 1) {
				return item.geojson;
			}

			const anchor = (typeof item.markerLatitude === "number" && typeof item.markerLongitude === "number")
				? [item.markerLongitude, item.markerLatitude]
				: null;

			if (!anchor) {
				return item.geojson;
			}

			let bestFeature = null;
			let bestDistance = Number.POSITIVE_INFINITY;
			let bestLength = -1;

			for (const feature of item.geojson.features) {
				if (!feature || !feature.geometry) {
					continue;
				}

				const coordinates = this.extractCoordinatesFromGeometry(feature.geometry);
				if (coordinates.length < 2) {
					continue;
				}

				const minDistance = this.getMinimumCoordinateDistance(anchor, coordinates);
				const lineLength = this.getCoordinateSequenceLength(coordinates);

				if (minDistance < bestDistance || (Math.abs(minDistance - bestDistance) < 0.5 && lineLength > bestLength)) {
					bestFeature = feature;
					bestDistance = minDistance;
					bestLength = lineLength;
				}
			}

			if (!bestFeature) {
				return item.geojson;
			}

			return {
				...item.geojson,
				features: [bestFeature]
			};
		}

		getRouteMarkerLatLng(item, geojson, isTrimmed = false) {
			if (!isTrimmed && typeof item.markerLatitude === "number" && typeof item.markerLongitude === "number") {
				return window.L.latLng(item.markerLatitude, item.markerLongitude);
			}

			const coordinates = this.extractRouteLatLngs(geojson || item.geojson);
			if (!coordinates.length) {
				return null;
			}

			if (coordinates.length === 1) {
				return coordinates[0];
			}

			const style = this.getItemStyle(item) || {};
			const ratio = Math.max(0, Math.min(1, (typeof style.routeIconPosition === "number" ? style.routeIconPosition : 50) / 100));
			return this.getLatLngAlongRouteCoordinates(coordinates, ratio);
		}

		getLatLngAlongRouteCoordinates(coordinates, ratio) {
			if (!Array.isArray(coordinates) || !coordinates.length) {
				return null;
			}

			if (ratio <= 0) {
				return coordinates[0];
			}
			if (ratio >= 1) {
				return coordinates[coordinates.length - 1];
			}

			let totalDistance = 0;
			const segments = [];
			for (let index = 1; index < coordinates.length; index += 1) {
				const start = coordinates[index - 1];
				const end = coordinates[index];
				const length = start.distanceTo(end);
				if (length <= 0) {
					continue;
				}
				segments.push({ start, end, length });
				totalDistance += length;
			}

			if (!segments.length || totalDistance <= 0) {
				return coordinates[0];
			}

			const targetDistance = totalDistance * ratio;
			let coveredDistance = 0;

			for (const segment of segments) {
				if (coveredDistance + segment.length >= targetDistance) {
					const offset = targetDistance - coveredDistance;
					const segmentRatio = offset / segment.length;
					return window.L.latLng(
						segment.start.lat + ((segment.end.lat - segment.start.lat) * segmentRatio),
						segment.start.lng + ((segment.end.lng - segment.start.lng) * segmentRatio)
					);
				}

				coveredDistance += segment.length;
			}

			return coordinates[coordinates.length - 1];
		}

		getLatLngAlongRouteGeojson(geojson, ratio) {
			const coordinates = this.extractRouteLatLngs(geojson);
			return this.getLatLngAlongRouteCoordinates(coordinates, ratio);
		}

		getRouteAdditionalIconSize(iconDefinition) {
			const rawSize = iconDefinition && Number.isFinite(iconDefinition.size) ? iconDefinition.size : 34;
			return Math.max(22, Math.min(56, rawSize));
		}

		renderRouteAdditionalIcons(item, displayedGeojson) {
			const icons = this.getRouteAdditionalIcons(item);
			if (!icons.length || !displayedGeojson) {
				return;
			}

			icons.forEach((iconDefinition, index) => {
				const ratio = Math.max(0, Math.min(1, ((iconDefinition && typeof iconDefinition.position === "number") ? iconDefinition.position : 50) / 100));
				const markerLatLng = this.getLatLngAlongRouteGeojson(displayedGeojson, ratio);
				if (!markerLatLng) {
					return;
				}

				const marker = window.L.marker(markerLatLng, {
					icon: this.createRouteAdditionalIcon(item, iconDefinition),
					zIndexOffset: (item.source === "transport" ? 920 : 120) + index
				});

				marker.bindPopup(this.buildPopupMarkup(item), {
					maxWidth: 320,
					className: "tccm-map__popup"
				});

				this.layers.addLayer(marker);
			});
		}

		trimGeoJson(geojson, startRatio, endRatio) {
			if (!geojson || (startRatio <= 0 && endRatio <= 0)) {
				return geojson;
			}

			const trimFeature = (feature) => {
				if (!feature || !feature.geometry) {
					return null;
				}

				const geometry = this.trimGeometry(feature.geometry, startRatio, endRatio);
				if (!geometry) {
					return null;
				}

				return {
					...feature,
					geometry
				};
			};

			if (geojson.type === "FeatureCollection" && Array.isArray(geojson.features)) {
				const features = geojson.features
					.map((feature) => trimFeature(feature))
					.filter((feature) => !!feature);

				return features.length ? {
					...geojson,
					features
				} : null;
			}

			if (geojson.type === "Feature") {
				return trimFeature(geojson);
			}

			return this.trimGeometry(geojson, startRatio, endRatio);
		}

		trimGeometry(geometry, startRatio, endRatio) {
			if (!geometry || !geometry.type) {
				return null;
			}

			if (geometry.type === "LineString") {
				const coordinates = this.trimLineCoordinates(geometry.coordinates || [], startRatio, endRatio);
				return coordinates.length >= 2 ? {
					...geometry,
					coordinates
				} : null;
			}

			if (geometry.type === "MultiLineString") {
				const coordinates = (geometry.coordinates || [])
					.map((line) => this.trimLineCoordinates(line || [], startRatio, endRatio))
					.filter((line) => Array.isArray(line) && line.length >= 2);

				return coordinates.length ? {
					...geometry,
					coordinates
				} : null;
			}

			return geometry;
		}

		extractCoordinatesFromGeometry(geometry) {
			const coordinates = [];
			const append = (value) => {
				if (!Array.isArray(value) || !value.length) {
					return;
				}

				if (typeof value[0] === "number" && typeof value[1] === "number") {
					coordinates.push([Number(value[0]), Number(value[1])]);
					return;
				}

				value.forEach((entry) => append(entry));
			};

			append(geometry && geometry.coordinates ? geometry.coordinates : []);

			return coordinates;
		}

		getMinimumCoordinateDistance(anchor, coordinates) {
			if (!Array.isArray(anchor) || anchor.length < 2 || !Array.isArray(coordinates) || !coordinates.length) {
				return Number.POSITIVE_INFINITY;
			}

			let bestDistance = Number.POSITIVE_INFINITY;
			for (const coordinate of coordinates) {
				const distance = this.measureCoordinateDistance(anchor, coordinate);
				if (distance < bestDistance) {
					bestDistance = distance;
				}
			}

			return bestDistance;
		}

		getCoordinateSequenceLength(coordinates) {
			if (!Array.isArray(coordinates) || coordinates.length < 2) {
				return 0;
			}

			let total = 0;
			for (let index = 1; index < coordinates.length; index += 1) {
				total += this.measureCoordinateDistance(coordinates[index - 1], coordinates[index]);
			}

			return total;
		}

		trimLineCoordinates(coordinates, startRatio, endRatio) {
			if (!Array.isArray(coordinates) || coordinates.length < 2) {
				return Array.isArray(coordinates) ? coordinates : [];
			}

			const points = coordinates
				.map((coordinate) => Array.isArray(coordinate) && coordinate.length >= 2 ? [Number(coordinate[0]), Number(coordinate[1])] : null)
				.filter((coordinate) => Array.isArray(coordinate) && Number.isFinite(coordinate[0]) && Number.isFinite(coordinate[1]));

			if (points.length < 2) {
				return [];
			}

			const segments = [];
			let totalDistance = 0;

			for (let index = 1; index < points.length; index += 1) {
				const start = points[index - 1];
				const end = points[index];
				const length = this.measureCoordinateDistance(start, end);

				if (length <= 0) {
					continue;
				}

				segments.push({ start, end, length });
				totalDistance += length;
			}

			if (!segments.length || totalDistance <= 0) {
				return points;
			}

			const startDistance = totalDistance * Math.max(0, Math.min(1, startRatio));
			const endDistance = totalDistance * Math.max(0, Math.min(1, 1 - endRatio));

			if (startDistance >= endDistance) {
				return [];
			}

			const trimmed = [];
			let coveredDistance = 0;

			for (const segment of segments) {
				const segmentStart = coveredDistance;
				const segmentEnd = coveredDistance + segment.length;

				if (segmentEnd <= startDistance) {
					coveredDistance = segmentEnd;
					continue;
				}

				if (segmentStart >= endDistance) {
					break;
				}

				const localStart = Math.max(startDistance, segmentStart);
				const localEnd = Math.min(endDistance, segmentEnd);

				if (localStart >= localEnd) {
					coveredDistance = segmentEnd;
					continue;
				}

				const startPoint = this.interpolateCoordinate(segment.start, segment.end, (localStart - segmentStart) / segment.length);
				const endPoint = this.interpolateCoordinate(segment.start, segment.end, (localEnd - segmentStart) / segment.length);

				if (!trimmed.length || !this.coordinatesEqual(trimmed[trimmed.length - 1], startPoint)) {
					trimmed.push(startPoint);
				}

				if (!this.coordinatesEqual(trimmed[trimmed.length - 1], endPoint)) {
					trimmed.push(endPoint);
				}

				coveredDistance = segmentEnd;
			}

			return trimmed.length >= 2 ? trimmed : [];
		}

		measureCoordinateDistance(start, end) {
			return window.L.latLng(start[1], start[0]).distanceTo(window.L.latLng(end[1], end[0]));
		}

		interpolateCoordinate(start, end, ratio) {
			const clampedRatio = Math.max(0, Math.min(1, ratio));

			return [
				start[0] + ((end[0] - start[0]) * clampedRatio),
				start[1] + ((end[1] - start[1]) * clampedRatio)
			];
		}

		coordinatesEqual(left, right) {
			if (!Array.isArray(left) || !Array.isArray(right) || left.length < 2 || right.length < 2) {
				return false;
			}

			return Math.abs(left[0] - right[0]) < 0.0000001 && Math.abs(left[1] - right[1]) < 0.0000001;
		}

		extractRouteLatLngs(geojson) {
			const coordinates = [];

			const appendCoordinates = (value) => {
				if (!Array.isArray(value) || !value.length) {
					return;
				}

				if (typeof value[0] === "number" && typeof value[1] === "number") {
					coordinates.push(window.L.latLng(value[1], value[0]));
					return;
				}

				value.forEach((entry) => appendCoordinates(entry));
			};

			if (geojson.type === "FeatureCollection" && Array.isArray(geojson.features)) {
				geojson.features.forEach((feature) => {
					if (feature && feature.geometry) {
						appendCoordinates(feature.geometry.coordinates);
					}
				});
			} else if (geojson.type === "Feature" && geojson.geometry) {
				appendCoordinates(geojson.geometry.coordinates);
			} else if (geojson.coordinates) {
				appendCoordinates(geojson.coordinates);
			}

			return coordinates;
		}

		getSvgIcon(iconType) {
			const icons = {
				beach: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 20h18"/><path d="M7 20v-7"/><path d="M7 13c0-2.5 2-4.5 4.5-4.5S16 10.5 16 13"/><path d="M7 13c0-1.7-1.3-3-3-3"/><path d="M16 13c0-1.7 1.3-3 3-3"/></svg>',
				church: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v5"/><path d="M10 5h4"/><path d="M6 20V10l6-3 6 3v10"/><path d="M10 20v-4h4v4"/></svg>',
				commerce: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9.5 4.4 4h15.2L21 9.5"/><path d="M4 9.5c0 1.4 1.1 2.5 2.5 2.5S9 10.9 9 9.5"/><path d="M9 9.5c0 1.4 1.1 2.5 2.5 2.5S14 10.9 14 9.5"/><path d="M14 9.5c0 1.4 1.1 2.5 2.5 2.5S19 10.9 19 9.5"/><path d="M5 12v8h14v-8"/><path d="M9 20v-4h6v4"/></svg>',
				restaurant: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3v8"/><path d="M4 3v4a2 2 0 0 0 4 0V3"/><path d="M6 11v10"/><path d="M14 3v18"/><path d="M14 3c2.5 0 4 2.2 4 5v3h-4"/></svg>',
				house: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h14v-9.5"/><path d="M10 20v-5h4v5"/></svg>',
				monument: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M7 20h10"/><path d="M9 20V9h6v11"/><path d="M12 4l4 5H8l4-5Z"/></svg>',
				viewpoint: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>',
				info: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 10v6"/><path d="M12 7h.01"/></svg>',
				camera: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8h4l2-2h4l2 2h4v10H4Z"/><circle cx="12" cy="13" r="3"/></svg>',
				bus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="4" width="14" height="13" rx="2"/><path d="M8 17v3"/><path d="M16 17v3"/><path d="M8 9h8"/><path d="M8 13h.01"/><path d="M16 13h.01"/></svg>',
				parking: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 20V4h6a4 4 0 1 1 0 8H8"/><path d="M8 12h6"/></svg>',
				bike: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6.5" cy="17.5" r="3.5"/><circle cx="17.5" cy="17.5" r="3.5"/><path d="M10 17.5 7.5 9H12l3 8.5"/><path d="M11 9h4.5"/><path d="M14 6h2"/></svg>',
				train: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="6" y="3" width="12" height="14" rx="2"/><path d="M9 17l-2 4"/><path d="M15 17l2 4"/><path d="M8.5 7h7"/><path d="M8.5 11h7"/><path d="M10 20h4"/></svg>',
				route: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="18" r="2"/><circle cx="18" cy="6" r="2"/><path d="M7.5 16.5 16 8"/><path d="M12 18h6V12"/></svg>'
			};

			return icons[iconType] || icons.info;
		}

		buildPopupMarkup(item) {
			const sourceLabel = item.sourceLabel ? '<p class="tccm-map__popup-section-title">' + this.escapeHtml(item.sourceLabel) + "</p>" : "";
			const image = item.imageUrl ? '<div class="tccm-map__popup-image"><img src="' + this.escapeAttr(item.imageUrl) + '" alt=""></div>' : "";
			const subtitle = item.subtitle ? '<p class="tccm-map__popup-subtitle">' + this.escapeHtml(item.subtitle) + "</p>" : "";
			const summary = item.summary ? '<p class="tccm-map__popup-summary">' + this.escapeHtml(item.summary) + "</p>" : "";
			const address = item.address ? '<p class="tccm-map__popup-meta">' + this.escapeHtml(item.address) + "</p>" : "";
			const primaryPosts = Array.isArray(item.primaryPosts) ? item.primaryPosts : [];
			const secondaryPosts = Array.isArray(item.secondaryPosts) ? item.secondaryPosts : [];
			const socialLinks = Array.isArray(item.socialLinks) ? item.socialLinks : [];
			const routeMeta = item.geometryType === "route"
				? '<div class="tccm-map__popup-tags">' +
					(item.distanceLabel ? '<span>' + this.escapeHtml(item.distanceLabel) + '</span>' : '') +
					(item.durationLabel ? '<span>' + this.escapeHtml(item.durationLabel) + '</span>' : '') +
					(item.difficulty ? '<span>' + this.escapeHtml(item.difficulty) + '</span>' : '') +
				  '</div>'
				: "";
			const transportMeta = item.source === "transport"
				? this.buildTransportMeta(item)
				: "";
			const accessibility = item.accessibilityNotes
				? '<p class="tccm-map__popup-accessibility">' + this.escapeHtml(item.accessibilityNotes) + "</p>"
				: (item.isAccessible ? '<p class="tccm-map__popup-accessibility">Accessible</p>' : "");
			const relatedPoints = item.geometryType === "route" && Array.isArray(item.relatedPoints) && item.relatedPoints.length
				? '<ul class="tccm-map__popup-list">' + item.relatedPoints.map((point) => '<li>' + this.escapeHtml(point.title) + '</li>').join("") + "</ul>"
				: "";
			const usefulLinks = (item.websiteUrl || socialLinks.length)
				? '<div class="tccm-map__popup-section"><p class="tccm-map__popup-section-title">Liens utiles</p><div class="tccm-map__popup-link-chips">' +
					(item.websiteUrl ? '<a class="tccm-map__popup-chip" href="' + this.escapeAttr(item.websiteUrl) + '">Site internet</a>' : '') +
					socialLinks.map((link) => '<a class="tccm-map__popup-chip" href="' + this.escapeAttr(link.url || "") + '">' + this.escapeHtml(link.label || link.network || "") + '</a>').join('') +
				  '</div></div>'
				: "";
			const primaryLinks = primaryPosts.length
				? '<div class="tccm-map__popup-section"><p class="tccm-map__popup-section-title">À découvrir</p><div class="tccm-map__popup-actions">' +
					primaryPosts.map((post) => '<a class="tccm-map__popup-cta tccm-map__popup-cta--related" href="' + this.escapeAttr(post.url || "") + '">' + this.escapeHtml(post.label || post.title || "Découvrir") + '</a>').join('') +
				  '</div></div>'
				: "";
			const secondaryLinks = secondaryPosts.length
				? '<div class="tccm-map__popup-section"><p class="tccm-map__popup-section-title">Articles liés</p><ul class="tccm-map__popup-link-list">' +
					secondaryPosts.map((post) => '<li><a href="' + this.escapeAttr(post.url || "") + '">' + this.escapeHtml(post.title || "") + '</a></li>').join('') +
				  '</ul></div>'
				: "";
			const linkUrl = item.ctaUrl || item.permalink || "";
			const linkLabel = item.ctaLabel || (item.geometryType === "route" ? "Voir le parcours" : "Voir le lieu");
			const cta = linkUrl ? '<a class="tccm-map__popup-cta tccm-map__popup-cta--primary" href="' + this.escapeAttr(linkUrl) + '">' + this.escapeHtml(linkLabel) + "</a>" : "";

			return (
				'<article class="tccm-map__popup-card">' +
					image +
					'<div class="tccm-map__popup-body">' +
						sourceLabel +
						'<h3 class="tccm-map__popup-title">' + this.escapeHtml(item.title || "") + "</h3>" +
						subtitle +
						summary +
						address +
						routeMeta +
						transportMeta +
						accessibility +
						usefulLinks +
						primaryLinks +
						relatedPoints +
						secondaryLinks +
						cta +
					"</div>" +
				"</article>"
			);
		}

		buildTransportMeta(item) {
			const modeTags = Array.isArray(item.transportModeLabels) && item.transportModeLabels.length
				? item.transportModeLabels.map((label) => '<span>' + this.escapeHtml(label) + '</span>').join("")
				: "";
			const lineTags = Array.isArray(item.relatedLineCodes) && item.relatedLineCodes.length
				? item.relatedLineCodes.map((code) => '<span>' + this.escapeHtml(code) + '</span>').join("")
				: "";
			const parkingStats = [];
			if (item.parkingType) {
				parkingStats.push('<li><strong>Type</strong> ' + this.escapeHtml(item.parkingType) + '</li>');
			}
			if (item.totalPlaces !== null && item.totalPlaces !== undefined) {
				parkingStats.push('<li><strong>Capacité</strong> ' + this.escapeHtml(String(item.totalPlaces)) + '</li>');
			}
			if (item.availablePlaces !== null && item.availablePlaces !== undefined) {
				parkingStats.push('<li><strong>Disponibles</strong> ' + this.escapeHtml(String(item.availablePlaces)) + '</li>');
			}
			if (item.bikesAvailable !== null && item.bikesAvailable !== undefined) {
				parkingStats.push('<li><strong>Vélos disponibles</strong> ' + this.escapeHtml(String(item.bikesAvailable)) + '</li>');
			}
			if (item.slotsTotal !== null && item.slotsTotal !== undefined) {
				parkingStats.push('<li><strong>Emplacements</strong> ' + this.escapeHtml(String(item.slotsTotal)) + '</li>');
			}
			if (item.electricBikes !== null && item.electricBikes !== undefined) {
				parkingStats.push('<li><strong>Vélos électriques</strong> ' + this.escapeHtml(String(item.electricBikes)) + '</li>');
			}

			const statsList = parkingStats.length
				? '<div class="tccm-map__popup-section"><p class="tccm-map__popup-section-title">Infos pratiques</p><ul class="tccm-map__popup-link-list">' + parkingStats.join("") + '</ul></div>'
				: "";
			const departures = Array.isArray(item.trainDepartures) && item.trainDepartures.length
				? '<div class="tccm-map__popup-section"><p class="tccm-map__popup-section-title">Départs</p><ul class="tccm-map__popup-link-list">' +
					item.trainDepartures.map((entry) => '<li>' + this.escapeHtml(entry) + '</li>').join("") +
				  '</ul></div>'
				: "";
			const info = Array.isArray(item.trainInformation) && item.trainInformation.length
				? '<div class="tccm-map__popup-section"><p class="tccm-map__popup-section-title">Informations</p><ul class="tccm-map__popup-link-list">' +
					item.trainInformation.map((entry) => '<li>' + this.escapeHtml(entry) + '</li>').join("") +
				  '</ul></div>'
				: "";

			return (
				(modeTags ? '<div class="tccm-map__popup-tags">' + modeTags + '</div>' : "") +
				(lineTags ? '<div class="tccm-map__popup-section"><p class="tccm-map__popup-section-title">Lignes liées</p><div class="tccm-map__popup-tags">' + lineTags + '</div></div>' : "") +
				statsList +
				departures +
				info
			);
		}

		escapeHtml(value) {
			return String(value || "")
				.replace(/&/g, "&amp;")
				.replace(/</g, "&lt;")
				.replace(/>/g, "&gt;")
				.replace(/\"/g, "&quot;")
				.replace(/'/g, "&#039;");
		}

		escapeAttr(value) {
			return this.escapeHtml(value);
		}
	}

	const resolveMapRoots = (scope) => {
		if (scope instanceof Element) {
			if (scope.matches(".tccm-map")) {
				return [scope];
			}

			return Array.from(scope.querySelectorAll(".tccm-map"));
		}

		return Array.from(document.querySelectorAll(".tccm-map"));
	};

	const resolveControlRoots = (scope) => {
		if (scope instanceof Element) {
			if (scope.matches("[data-map-controls]")) {
				return [scope];
			}

			return Array.from(scope.querySelectorAll("[data-map-controls]"));
		}

		return Array.from(document.querySelectorAll("[data-map-controls]"));
	};

	const isElementorEditorContext = () => {
		if (window.elementorFrontend && typeof window.elementorFrontend.isEditMode === "function") {
			try {
				if (window.elementorFrontend.isEditMode()) {
					return true;
				}
			} catch (error) {
				// noop
			}
		}

		if (window.location && typeof window.location.search === "string" && window.location.search.includes("elementor-preview=")) {
			return true;
		}

		if (document.body && document.body.classList.contains("elementor-editor-active")) {
			return true;
		}

		return false;
	};

	const initControls = (scope, force = false) => {
		const roots = resolveControlRoots(scope);
		roots.forEach((root) => {
			if (!root) {
				return;
			}

			if (root.__tccmMapControlsInstance) {
				if (force) {
					root.__tccmMapControlsInstance.refresh();
				}
				return;
			}

			const instance = new TheCoreCollectivityMapControls(root);
			root.__tccmMapControlsInstance = instance;
			instance.init();
		});
	};

	const initMaps = (scope, force = false) => {
		const roots = resolveMapRoots(scope);
		roots.forEach((root) => {
			if (!root) {
				return;
			}

			if (root.__tccmMapInstance) {
				if (!force) {
					return;
				}

				root.__tccmMapInstance.destroy();
			}

			const instance = new TheCoreCollectivityMap(root);
			root.__tccmMapInstance = instance;
			instance.init();
		});
	};

	const init = (scope, force = false) => {
		initControls(scope, force);
		initMaps(scope, force);
	};

	const startObserver = () => {
		if (!("MutationObserver" in window)) {
			return;
		}

		const pending = new Map();
		let frame = null;

		const schedule = (root, force = false) => {
			if (!(root instanceof Element)) {
				return;
			}

			const current = pending.get(root) || false;
			pending.set(root, current || force);

			if (frame !== null) {
				return;
			}

			frame = window.requestAnimationFrame(() => {
				pending.forEach((pendingForce, pendingRoot) => {
					init(pendingRoot, pendingForce);
				});
				pending.clear();
				frame = null;
			});
		};

		const observer = new MutationObserver((mutations) => {
			mutations.forEach((mutation) => {
				if (mutation.type === "childList") {
					mutation.addedNodes.forEach((node) => {
						if (!(node instanceof Element)) {
							return;
						}

						if (node.matches(".tccm-map") || node.matches(".tccm-map-filters") || node.matches("[data-map-controls]")) {
							schedule(node, true);
							return;
						}

						const nestedRoots = node.querySelectorAll ? node.querySelectorAll(".tccm-map, .tccm-map-filters, [data-map-controls]") : [];
						nestedRoots.forEach((nestedRoot) => schedule(nestedRoot, true));
					});
				}
			});
		});

		observer.observe(document.documentElement, {
			childList: true,
			subtree: true
		});
	};

	const startRetryLoop = () => {
		let attempts = 0;
		const maxAttempts = 10;
		const initialDelay = 800;
		const delay = 1200;

		const tick = () => {
			attempts += 1;

			const rootCount = resolveMapRoots(document).length + resolveControlRoots(document).length;
			if (rootCount > 0) {
				init(document);
				return;
			}

			if (attempts >= maxAttempts) {
				return;
			}

			window.setTimeout(tick, delay);
		};

		window.setTimeout(tick, initialDelay);
	};

	const hookElementorFrontendInit = () => {
		const attach = () => {
			if (!window.elementorFrontend || !window.elementorFrontend.hooks) {
				return false;
			}

			window.elementorFrontend.hooks.addAction("frontend/element_ready/tccm-map.default", ($scope) => {
				const node = $scope && $scope[0] ? $scope[0] : null;
				if (node) {
					init(node, true);
				}
			});

			window.elementorFrontend.hooks.addAction("frontend/element_ready/tccm-map-filters.default", ($scope) => {
				const node = $scope && $scope[0] ? $scope[0] : null;
				if (node) {
					init(node, true);
				}
			});

			return true;
		};

		if (attach()) {
			return;
		}

		if (window.jQuery) {
			window.jQuery(window).on("elementor/frontend/init", () => {
				attach();
				init(document, true);
			});
		}

		let attempts = 0;
		const maxAttempts = 10;
		const delay = 1200;

		const poll = () => {
			attempts += 1;
			if (attach() || attempts >= maxAttempts) {
				return;
			}

			window.setTimeout(poll, delay);
		};

		window.setTimeout(poll, delay);
	};

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", () => init(document));
	} else {
		init(document);
	}

	if (isElementorEditorContext()) {
		startObserver();
		startRetryLoop();
		hookElementorFrontendInit();
	}
})();
