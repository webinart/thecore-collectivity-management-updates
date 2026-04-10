(function () {
	"use strict";

	const DEFAULT_CENTER = [48.1173, -1.6778];
	const ALL_MAP_TYPES = ["bus", "parking", "velo", "train"];

	function escapeHtml(value) {
		return String(value || "")
			.replace(/&/g, "&amp;")
			.replace(/</g, "&lt;")
			.replace(/>/g, "&gt;")
			.replace(/\"/g, "&quot;")
			.replace(/'/g, "&#039;");
	}

	function normalizeText(value) {
		return String(value || "")
			.toLowerCase()
			.normalize("NFD")
			.replace(/[\u0300-\u036f]/g, "");
	}

	function matchesQuery(text, query) {
		const haystack = normalizeText(text);
		const needles = normalizeText(query)
			.split(/\s+/)
			.filter(Boolean);

		if (!needles.length) {
			return true;
		}

		return needles.every(function (term) {
			return haystack.indexOf(term) !== -1;
		});
	}

	function getIconMarkup(icon) {
		switch (icon) {
			case "bus":
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6v6"></path><path d="M15 6v6"></path><path d="M2 12h19.6"></path><path d="M18 18h3s.5-1.7.8-2.8c.1-.4.2-.8.2-1.2 0-.4-.1-.8-.2-1.2l-1.4-5C20.1 6.8 19.1 6 18 6H4a2 2 0 0 0-2 2v10h3"></path><circle cx="7" cy="18" r="2"></circle><path d="M9 18h5"></path><circle cx="16" cy="18" r="2"></circle></svg>';
			case "parking":
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><path d="M9 17V7h4a3 3 0 0 1 0 6H9"></path></svg>';
			case "bike":
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18.5" cy="17.5" r="3.5"></circle><circle cx="5.5" cy="17.5" r="3.5"></circle><circle cx="15" cy="5" r="1"></circle><path d="M12 17.5V14l-3-3 4-3 2 3h2"></path></svg>';
			case "train":
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="3" width="16" height="16" rx="2"></rect><path d="M4 11h16"></path><path d="M8 15h.01"></path><path d="M16 15h.01"></path><path d="M8 19 6 21"></path><path d="M18 21 16 19"></path></svg>';
			case "clock":
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>';
			case "calendar":
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2v4"></path><path d="M16 2v4"></path><rect width="18" height="18" x="3" y="4" rx="2"></rect><path d="M3 10h18"></path></svg>';
			case "accessible":
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="16" cy="4" r="1"></circle><path d="m18 19 1-7-6 1"></path><path d="m5 8 3-3 5.5 3-2.36 3.5"></path><path d="M4 20h4"></path><path d="m16 20 3-4"></path></svg>';
			case "pin":
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"></path><circle cx="12" cy="10" r="3"></circle></svg>';
			case "arrow":
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path><path d="m13 5 7 7-7 7"></path></svg>';
			case "leaf":
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 4 13C4 7 11 4 20 4c0 9-3 16-9 16Z"></path><path d="M11 20C7 16 9 12 15 8"></path></svg>';
			case "search":
			default:
				return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg>';
		}
	}

	function getModeLabel(mode) {
		switch (mode) {
			case "bus":
				return "Bus";
			case "parking":
				return "Parking";
			case "velo":
				return "Parking velos";
			case "train":
				return "Train";
			default:
				return mode;
		}
	}

	function sanitizeThemeHex(value) {
		const hex = String(value || "").trim();
		return /^#?[0-9a-f]{6}$/i.test(hex) ? (hex.charAt(0) === "#" ? hex : "#" + hex) : "";
	}

	function hexToRgba(hex, alpha) {
		const normalized = sanitizeThemeHex(hex);
		if (!normalized) {
			return "";
		}

		const color = normalized.slice(1);
		const red = parseInt(color.slice(0, 2), 16);
		const green = parseInt(color.slice(2, 4), 16);
		const blue = parseInt(color.slice(4, 6), 16);
		return "rgba(" + red + ", " + green + ", " + blue + ", " + alpha + ")";
	}

	function parseCssPx(value, fallback) {
		const parsed = parseFloat(String(value || "").trim());
		return Number.isFinite(parsed) && parsed > 0 ? parsed : fallback;
	}

		class BellevueTransportExplorer {
			constructor(root) {
				this.root = root;
				this.root.__bteInstance = this;
				this.showAllRouteVehiclesOnMap = this.root.getAttribute("data-show-all-route-vehicles-on-map") !== "0";
				this.realtimeRefreshEnabled = this.root.getAttribute("data-realtime-refresh-enabled") !== "0";
				this.realtimeRefreshInterval = Math.max(15, parseInt(this.root.getAttribute("data-realtime-refresh-interval") || "30", 10) || 30);
				this.realtimeRefreshEndpoint = String(this.root.getAttribute("data-realtime-refresh-endpoint") || "");
				this.realtimeRefreshTimer = null;
				this.realtimeRefreshInFlight = false;
				this.cacheDom();
				this.data = this.getPayload();
				this.state = {
					query: "",
					selectedTypes: [],
					accessibleOnly: false,
					freeOnly: false,
					day: "tous",
					busSort: "name",
					parkingSort: "disponibilite",
					lineSelections: {},
					activeBusLineId: ""
				};
			this.map = null;
			this.routesLayer = null;
			this.markersLayer = null;
			this.markerIndex = {};
			this.hasFittedBounds = false;
			this.bindEvents();
			this.initMap();
			this.render();
			this.initRealtimeRefresh();
		}

			cacheDom() {
			this.payloadNode = this.root.querySelector(".bte__data");
			this.searchInput = this.root.querySelector(".bte__search-input");
			this.searchButton = this.root.querySelector(".bte__search-button");
			this.typeButtons = Array.from(this.root.querySelectorAll(".bte__type-filter"));
				this.accessibleInput = this.root.querySelector('[data-filter="accessible"]');
				this.freeInput = this.root.querySelector('[data-filter="free"]');
				this.daySelect = this.root.querySelector('[data-filter="day"]');
				this.resetButton = this.root.querySelector('[data-action="reset"]');
				this.mapElement = this.root.querySelector("[data-map]");
				this.mapEmpty = this.root.querySelector("[data-map-empty]");
				this.legendElement = this.root.querySelector("[data-legend]");
				this.realtimeAlertsElement = this.root.querySelector("[data-realtime-alerts]");
				this.sectionElements = {
					bus: this.root.querySelector('[data-panel="bus"]'),
					parking: this.root.querySelector('[data-panel="parking"]'),
					velo: this.root.querySelector('[data-panel="velo"]'),
					train: this.root.querySelector('[data-panel="train"]')
				};
				this.listElements = {
					bus: this.root.querySelector('[data-list="bus"]'),
					parking: this.root.querySelector('[data-list="parking"]'),
					velo: this.root.querySelector('[data-list="velo"]'),
					train: this.root.querySelector('[data-list="train"]')
				};
				this.busDetailElement = this.root.querySelector('[data-line-detail="bus"]');
				this.emptyElements = {
					bus: this.root.querySelector('[data-empty="bus"]'),
					parking: this.root.querySelector('[data-empty="parking"]'),
					velo: this.root.querySelector('[data-empty="velo"]'),
					train: this.root.querySelector('[data-empty="train"]'),
					results: this.root.querySelector('[data-empty="results"]')
				};
				this.panelCountElements = {
					bus: this.root.querySelector('[data-count-label="bus"]'),
					parking: this.root.querySelector('[data-count-label="parking"]'),
					velo: this.root.querySelector('[data-count-label="velo"]'),
					train: this.root.querySelector('[data-count-label="train"]')
				};
				this.busSortSelect = this.root.querySelector('[data-sort="bus"]');
				this.parkingSortSelect = this.root.querySelector('[data-sort="parking"]');
			}

		getPayload() {
			if (!this.payloadNode) {
				this.payloadNode = this.root.querySelector(".bte__data");
			}

			if (!this.payloadNode) {
				return this.normalizePayload(null);
			}

			try {
				return this.normalizePayload(JSON.parse(this.payloadNode.textContent || "{}"));
			} catch (error) {
				return this.normalizePayload(null);
			}
		}

		normalizePayload(parsed) {
			return {
				lines: Array.isArray(parsed && parsed.lines) ? parsed.lines : [],
				places: Array.isArray(parsed && parsed.places) ? parsed.places : [],
				realtime: parsed && parsed.realtime && typeof parsed.realtime === "object" ? {
					alerts: Array.isArray(parsed.realtime.alerts) ? parsed.realtime.alerts : [],
					vehicles: Array.isArray(parsed.realtime.vehicles) ? parsed.realtime.vehicles : []
				} : { alerts: [], vehicles: [] }
			};
		}

		initRealtimeRefresh() {
			if (!this.realtimeRefreshEnabled || !this.realtimeRefreshEndpoint || typeof window.fetch !== "function") {
				return;
			}

			document.addEventListener("visibilitychange", () => {
				if (document.hidden) {
					return;
				}
				this.scheduleRealtimeRefresh(1000);
			});

			this.scheduleRealtimeRefresh(this.realtimeRefreshInterval * 1000);
		}

		scheduleRealtimeRefresh(delay) {
			if (!this.realtimeRefreshEnabled || !this.realtimeRefreshEndpoint) {
				return;
			}

			if (this.realtimeRefreshTimer) {
				window.clearTimeout(this.realtimeRefreshTimer);
			}

			this.realtimeRefreshTimer = window.setTimeout(() => {
				this.refreshRealtimePayload();
			}, Math.max(1000, parseInt(delay || 0, 10) || (this.realtimeRefreshInterval * 1000)));
		}

		captureMapView() {
			if (!this.map) {
				return null;
			}

			const center = this.map.getCenter();
			return {
				lat: center.lat,
				lng: center.lng,
				zoom: this.map.getZoom()
			};
		}

		restoreMapView(view) {
			if (!this.map || !view || typeof view.lat !== "number" || typeof view.lng !== "number" || !Number.isFinite(view.zoom)) {
				return;
			}

			this.map.setView([view.lat, view.lng], view.zoom, { animate: false });
		}

		refreshRealtimePayload() {
			if (!this.realtimeRefreshEnabled || !this.realtimeRefreshEndpoint) {
				return Promise.resolve(false);
			}

			if (this.realtimeRefreshInFlight) {
				this.scheduleRealtimeRefresh(this.realtimeRefreshInterval * 1000);
				return Promise.resolve(false);
			}

			if (document.hidden) {
				this.scheduleRealtimeRefresh(this.realtimeRefreshInterval * 1000);
				return Promise.resolve(false);
			}

			this.realtimeRefreshInFlight = true;

			return window.fetch(this.realtimeRefreshEndpoint, {
				method: "GET",
				credentials: "same-origin",
				cache: "no-store",
				headers: {
					Accept: "application/json"
				}
			})
				.then((response) => {
					if (!response.ok) {
						throw new Error("Transport refresh failed");
					}

					return response.json();
				})
				.then((response) => {
					const payload = response && typeof response === "object" && response.payload ? response.payload : response;
					const nextData = this.normalizePayload(payload);
					const preservedView = this.captureMapView();
					this.data = nextData;
					if (this.payloadNode) {
						this.payloadNode.textContent = JSON.stringify(nextData);
					}
					this.render();
					this.restoreMapView(preservedView);
					return true;
				})
				.catch(() => false)
				.finally(() => {
					this.realtimeRefreshInFlight = false;
					this.scheduleRealtimeRefresh(this.realtimeRefreshInterval * 1000);
				});
		}

		bindEvents() {
			if (this.searchInput) {
				this.searchInput.addEventListener("input", () => {
					this.state.query = this.searchInput.value || "";
					this.render();
				});
				this.searchInput.addEventListener("keydown", (event) => {
					if (event.key === "Enter") {
						event.preventDefault();
						this.render();
					}
				});
			}

			if (this.searchButton) {
				this.searchButton.addEventListener("click", () => {
					if (this.searchInput) {
						this.state.query = this.searchInput.value || "";
					}
					this.render();
				});
			}

			this.typeButtons.forEach((button) => {
				button.addEventListener("click", () => {
					this.toggleType(button.getAttribute("data-filter-type") || "");
				});
			});

			if (this.accessibleInput) {
				this.accessibleInput.addEventListener("change", () => {
					this.state.accessibleOnly = !!this.accessibleInput.checked;
					this.render();
				});
			}

			if (this.freeInput) {
				this.freeInput.addEventListener("change", () => {
					this.state.freeOnly = !!this.freeInput.checked;
					this.render();
				});
			}

			if (this.daySelect) {
				this.daySelect.addEventListener("change", () => {
					this.state.day = this.daySelect.value || "tous";
					this.render();
				});
			}

				if (this.resetButton) {
					this.resetButton.addEventListener("click", () => {
						this.resetFilters();
					});
				}

				if (this.busSortSelect) {
					this.busSortSelect.addEventListener("change", () => {
						this.state.busSort = this.busSortSelect.value || "name";
						this.render();
				});
			}

			if (this.parkingSortSelect) {
				this.parkingSortSelect.addEventListener("change", () => {
					this.state.parkingSort = this.parkingSortSelect.value || "disponibilite";
					this.render();
				});
			}

				this.root.addEventListener("click", (event) => {
					const openLineButton = event.target.closest("[data-open-line]");
					if (openLineButton && this.root.contains(openLineButton)) {
						event.preventDefault();
						this.toggleBusLineDetail(openLineButton.getAttribute("data-open-line"));
						return;
					}

					const closeLineButton = event.target.closest("[data-close-line]");
					if (closeLineButton && this.root.contains(closeLineButton)) {
						event.preventDefault();
						this.state.activeBusLineId = "";
						this.render();
						return;
					}

					const directionButton = event.target.closest("[data-line-direction]");
					if (directionButton && this.root.contains(directionButton)) {
						event.preventDefault();
						this.updateLineSelection(directionButton.getAttribute("data-line-id"), {
							directionKey: directionButton.getAttribute("data-line-direction") || "",
							stopId: ""
						});
						return;
					}

					const focusButton = event.target.closest("[data-focus-place]");
					if (focusButton && this.root.contains(focusButton)) {
						event.preventDefault();
						this.focusPlace(focusButton.getAttribute("data-focus-place"));
					}
				});

				this.root.addEventListener("change", (event) => {
					const stopSelect = event.target.closest("[data-line-stop]");
					if (!stopSelect || !this.root.contains(stopSelect)) {
						return;
					}

					this.updateLineSelection(stopSelect.getAttribute("data-line-id"), {
						stopId: stopSelect.value || ""
					});
				});
			}

			resetFilters() {
				this.state.query = "";
				this.state.selectedTypes = [];
				this.state.accessibleOnly = false;
				this.state.freeOnly = false;
				this.state.day = "tous";
				this.state.lineSelections = {};
				this.state.activeBusLineId = "";
				if (this.searchInput) {
					this.searchInput.value = "";
				}
				if (this.accessibleInput) {
					this.accessibleInput.checked = false;
				}
				if (this.freeInput) {
					this.freeInput.checked = false;
				}
				if (this.daySelect) {
					this.daySelect.value = "tous";
				}
				this.render();
			}

		toggleType(type) {
			if (!type) {
				return;
			}

			if (this.state.selectedTypes.indexOf(type) !== -1) {
				this.state.selectedTypes = this.state.selectedTypes.filter((item) => item !== type);
			} else {
				this.state.selectedTypes = this.state.selectedTypes.concat(type);
			}

			this.render();
		}

		initMap() {
			if (!this.mapElement || !window.L || this.map) {
				return;
			}

			this.map = window.L.map(this.mapElement, {
				center: DEFAULT_CENTER,
				zoom: 14,
				scrollWheelZoom: true
			});

			window.L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
				attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
			}).addTo(this.map);

			this.routesLayer = window.L.layerGroup().addTo(this.map);
			this.markersLayer = window.L.layerGroup().addTo(this.map);
		}

			render() {
				const filtered = this.getFilteredCollections();
				this.renderFilterState();
				this.renderCounts(filtered);
				this.renderPanels(filtered);
				this.renderPanelsVisibility(filtered);
				this.renderRealtimeAlerts(filtered);
				this.renderMap(filtered);
			}

			renderFilterState() {
				const visibleMapTypes = this.getVisibleMapTypes();
				this.typeButtons.forEach((button) => {
					const type = button.getAttribute("data-filter-type") || "";
					const isActive = visibleMapTypes.indexOf(type) !== -1;
					button.classList.toggle("is-active", isActive);
					button.setAttribute("aria-pressed", isActive ? "true" : "false");
				});

				const hasActiveFilters = this.state.query.trim() || this.state.selectedTypes.length || this.state.accessibleOnly || this.state.freeOnly || this.state.day !== "tous";
				if (this.resetButton) {
					this.resetButton.hidden = !hasActiveFilters;
				}
			}

			renderCounts(filtered) {
				const counts = {
					bus: filtered.lines.length,
					parking: filtered.parkings.length,
				velo: filtered.bikes.length,
				train: filtered.trains.length
				};

				Object.keys(counts).forEach((key) => {
					if (this.panelCountElements[key]) {
						this.panelCountElements[key].textContent = String(counts[key]);
					}
				});
			}

			renderPanels(filtered) {
				this.renderList("bus", filtered.lines.map((line) => this.renderBusCard(line)));
				this.renderBusDetail(filtered.lines);
				this.renderList("parking", filtered.parkings.map((parking) => this.renderParkingCard(parking)));
				this.renderList("velo", filtered.bikes.map((bike) => this.renderBikeCard(bike)));
				this.renderList("train", filtered.trains.map((train) => this.renderTrainCard(train)));
			}

			renderList(key, items) {
			if (!this.listElements[key] || !this.emptyElements[key]) {
				return;
			}

				this.listElements[key].innerHTML = items.join("");
				this.emptyElements[key].hidden = items.length > 0;
			}

			renderBusDetail(lines) {
				if (!this.busDetailElement) {
					return;
				}

				const activeLineId = String(this.state.activeBusLineId || "");
				if (!activeLineId) {
					this.busDetailElement.innerHTML = "";
					this.busDetailElement.hidden = true;
					return;
				}

				const activeLine = (Array.isArray(lines) ? lines : []).find((line) => String(line.id || "") === activeLineId);
				if (!activeLine) {
					this.busDetailElement.innerHTML = "";
					this.busDetailElement.hidden = true;
					return;
				}

				this.busDetailElement.innerHTML = this.renderBusLineDetail(activeLine);
				this.busDetailElement.hidden = false;
			}

			renderPanelsVisibility(filtered) {
				const counts = {
					bus: filtered.lines.length,
					parking: filtered.parkings.length,
					velo: filtered.bikes.length,
					train: filtered.trains.length
				};
				let hasVisibleSection = false;

				Object.keys(this.sectionElements).forEach((key) => {
					const section = this.sectionElements[key];
					if (!section) {
						return;
					}

					const isVisible = counts[key] > 0;
					section.hidden = !isVisible;
					if (isVisible) {
						hasVisibleSection = true;
					}
				});

				if (this.emptyElements.results) {
					this.emptyElements.results.hidden = hasVisibleSection;
				}
			}

		renderMap(filtered) {
			if (!this.map || !this.markersLayer || !this.routesLayer || !window.L) {
				return;
			}

			this.routesLayer.clearLayers();
			this.markersLayer.clearLayers();
			this.markerIndex = {};

			const visibleTypes = this.getVisibleMapTypes();
			const markerPlaces = [];

			if (visibleTypes.indexOf("bus") !== -1) {
				markerPlaces.push.apply(markerPlaces, filtered.busStops);
			}
			if (visibleTypes.indexOf("parking") !== -1) {
				markerPlaces.push.apply(markerPlaces, filtered.parkings);
			}
			if (visibleTypes.indexOf("velo") !== -1) {
				markerPlaces.push.apply(markerPlaces, filtered.bikes);
			}
			if (visibleTypes.indexOf("train") !== -1) {
				markerPlaces.push.apply(markerPlaces, filtered.trains);
			}

			const bounds = [];
			filtered.lines.forEach((line) => {
				const lineMode = Array.isArray(line.modes) && line.modes.length ? line.modes[0] : (line.mode || "bus");
				if (visibleTypes.indexOf(lineMode) === -1 || !line.geojson) {
					return;
				}

				const routeLayer = window.L.geoJSON(line.geojson, {
					style: () => this.getLineRouteStyle(line)
				});
				routeLayer.bindPopup(this.getLinePopupMarkup(line));
				routeLayer.addTo(this.routesLayer);

				const routeBounds = routeLayer.getBounds();
				if (routeBounds && routeBounds.isValid()) {
					bounds.push(routeBounds.getSouthWest());
					bounds.push(routeBounds.getNorthEast());
				}
			});

			markerPlaces.forEach((place) => {
				if (typeof place.latitude !== "number" || typeof place.longitude !== "number") {
					return;
				}

				const marker = window.L.marker([place.latitude, place.longitude], {
					icon: this.createMarkerIcon(place.mode || "bus")
				});
				marker.bindPopup(this.getPopupMarkup(place));
				marker.addTo(this.markersLayer);
				this.markerIndex[String(place.id)] = marker;
				(Array.isArray(place.childPlaceIds) ? place.childPlaceIds : []).forEach((childPlaceId) => {
					this.markerIndex[String(childPlaceId)] = marker;
				});
				bounds.push([place.latitude, place.longitude]);
			});

			(filtered.vehicles || []).forEach((vehicle) => {
				if (typeof vehicle.latitude !== "number" || typeof vehicle.longitude !== "number") {
					return;
				}

				const marker = window.L.marker([vehicle.latitude, vehicle.longitude], {
					icon: this.createVehicleMarkerIcon(vehicle),
					zIndexOffset: 120
				});
				marker.bindPopup(this.getVehiclePopupMarkup(vehicle));
				marker.addTo(this.markersLayer);
				bounds.push([vehicle.latitude, vehicle.longitude]);
			});

			if (this.mapEmpty) {
				this.mapEmpty.hidden = bounds.length > 0;
			}

			if (bounds.length && !this.hasFittedBounds) {
				this.map.fitBounds(bounds, { padding: [32, 32] });
				this.hasFittedBounds = true;
			}

			this.renderLegend(visibleTypes);
		}

		getLineRouteStyle(line) {
			const theme = line && line.theme ? line.theme : {};
			const color = sanitizeThemeHex(theme.color) || "#1d4ed8";
			return {
				color: color,
				weight: 4,
				opacity: 0.85,
				lineCap: "round",
				lineJoin: "round"
			};
		}

		renderLegend(visibleTypes) {
			if (!this.legendElement) {
				return;
			}

			const items = visibleTypes.map((type) => {
				return '<span class="bte__legend-item bte__legend-item--' + escapeHtml(type) + '">' +
					'<span class="bte__legend-dot">' + getIconMarkup(type === "velo" ? "bike" : type) + '</span>' +
					'<span>' + escapeHtml(getModeLabel(type)) + '</span>' +
				'</span>';
			});

			this.legendElement.innerHTML = items.join("");
		}

			focusPlace(placeId) {
			const marker = this.markerIndex[String(placeId)];
			if (!marker || !this.map) {
				return;
			}

			const latLng = marker.getLatLng();
			this.map.flyTo(latLng, Math.max(this.map.getZoom(), 15), { duration: 0.35 });
				marker.openPopup();
			}

			toggleBusLineDetail(lineId) {
				const targetId = String(lineId || "");
				if (!targetId) {
					return;
				}

				const shouldOpen = this.state.activeBusLineId !== targetId;
				this.state.activeBusLineId = shouldOpen ? targetId : "";
				this.render();

				if (shouldOpen) {
					window.requestAnimationFrame(() => {
						if (this.busDetailElement && !this.busDetailElement.hidden) {
							this.busDetailElement.scrollIntoView({
								behavior: "smooth",
								block: "start"
							});
						}
					});
				}
			}

			getLineTheme(line) {
				const theme = line && typeof line.theme === "object" && line.theme ? line.theme : {};
				const color = sanitizeThemeHex(theme.color) || "#2F855A";
				const textColor = sanitizeThemeHex(theme.textColor) || "#FFFFFF";

				return {
					color: color,
					textColor: textColor,
					borderColor: hexToRgba(color, 0.5) || color,
					softColor: hexToRgba(color, 0.08) || "rgba(47, 133, 90, 0.08)",
					softColorStrong: hexToRgba(color, 0.14) || "rgba(47, 133, 90, 0.14)"
				};
			}

			getLineThemeStyle(line) {
				const theme = this.getLineTheme(line);
				return '--bte-line-color:' + theme.color + ';' +
					'--bte-line-text:' + theme.textColor + ';' +
					'--bte-line-border:' + theme.borderColor + ';' +
					'--bte-line-soft:' + theme.softColor + ';' +
					'--bte-line-soft-strong:' + theme.softColorStrong + ';';
			}

		createMarkerIcon(mode) {
			const colors = {
				bus: { background: "#2563eb", border: "#1d4ed8" },
				parking: { background: "#475569", border: "#334155" },
				velo: { background: "#16a34a", border: "#15803d" },
				train: { background: "#7c3aed", border: "#6d28d9" }
			};
			const current = colors[mode] || colors.bus;
			const iconKey = mode === "velo" ? "bike" : mode;
			return window.L.divIcon({
				className: "bte__marker",
				html: '<span class="bte__marker-inner" style="background:' + current.background + ';border-color:' + current.border + ';">' + getIconMarkup(iconKey) + '</span>',
				iconSize: [36, 36],
				iconAnchor: [18, 18],
				popupAnchor: [0, -18]
			});
		}

		createVehicleMarkerIcon(vehicle) {
			const theme = vehicle && vehicle.theme ? vehicle.theme : {};
			const styles = window.getComputedStyle(this.root);
			const size = parseCssPx(styles.getPropertyValue("--bte-vehicle-marker-size"), 30);
			const background = sanitizeThemeHex(theme.color) || "#0f766e";
			const border = sanitizeThemeHex(theme.textColor) || "#ffffff";
			return window.L.divIcon({
				className: "bte__marker bte__marker--vehicle",
				html: '<span class="bte__marker-inner bte__marker-inner--vehicle" style="--bte-vehicle-marker-theme-bg:' + background + ';--bte-vehicle-marker-theme-border:' + border + ';--bte-vehicle-marker-theme-icon:' + border + ';">' + getIconMarkup("bus") + '</span>',
				iconSize: [size, size],
				iconAnchor: [size / 2, size / 2],
				popupAnchor: [0, (-1 * size / 2) - 1]
			});
		}

		getPopupMarkup(place) {
			const lines = Array.isArray(place.relatedLineCodes) && place.relatedLineCodes.length ? '<p class="bte__popup-lines">Lignes: ' + escapeHtml(place.relatedLineCodes.join(", ")) + '</p>' : "";
			const subtitle = place.subtitle ? '<p class="bte__popup-subtitle">' + escapeHtml(place.subtitle) + '</p>' : "";
			const address = place.address ? '<p class="bte__popup-address">' + escapeHtml(place.address) + '</p>' : "";
			const directions = Array.isArray(place.destinationLabels) && place.destinationLabels.length ? place.destinationLabels : this.getPlaceDirectionLabels(place);
			const directionMarkup = directions.length ? '<p class="bte__popup-lines">Directions: ' + escapeHtml(directions.join(" · ")) + '</p>' : "";
			return '<div class="bte__popup">' +
				'<p class="bte__popup-title">' + escapeHtml(place.title) + '</p>' +
				subtitle +
				address +
				lines +
				directionMarkup +
			'</div>';
		}

		getLinePopupMarkup(line) {
			const title = line.title || (line.lineCode ? "Ligne " + line.lineCode : "Ligne");
			const subtitle = line.routeLabel ? '<p class="bte__popup-subtitle">' + escapeHtml(line.routeLabel) + '</p>' : "";
			const summary = line.timetableSummary ? '<p class="bte__popup-lines">' + escapeHtml(line.timetableSummary) + '</p>' : "";
			const provider = line.providerLabel ? '<p class="bte__popup-lines">Opérateur: ' + escapeHtml(line.providerLabel) + '</p>' : "";

			return '<div class="bte__popup">' +
				'<p class="bte__popup-title">' + escapeHtml(title) + '</p>' +
				subtitle +
				summary +
				provider +
			'</div>';
		}

		getVehiclePopupMarkup(vehicle) {
			const title = vehicle.lineCode ? 'Ligne ' + vehicle.lineCode : (vehicle.lineTitle || "Véhicule");
			const label = vehicle.vehicleLabel || vehicle.vehicleId || "";
			const direction = this.getVehicleDirectionLabel(vehicle);
			const status = this.getVehicleStatusLabel(vehicle.currentStatus);
			const time = vehicle.timestamp ? this.formatRealtimeTimestamp(vehicle.timestamp) : "";
			const provider = vehicle.providerLabel ? '<p class="bte__popup-subtitle">' + escapeHtml(vehicle.providerLabel) + '</p>' : "";
			const meta = [
				label ? '<p class="bte__popup-lines">Véhicule: ' + escapeHtml(label) + '</p>' : "",
				direction ? '<p class="bte__popup-lines">Direction: ' + escapeHtml(direction) + '</p>' : "",
				status ? '<p class="bte__popup-lines">Statut: ' + escapeHtml(status) + '</p>' : "",
				time ? '<p class="bte__popup-lines">Position: ' + escapeHtml(time) + '</p>' : ""
			].filter(Boolean).join("");
			return '<div class="bte__popup bte__popup--vehicle">' +
				'<p class="bte__popup-title">' + escapeHtml(title) + '</p>' +
				provider +
				meta +
			'</div>';
		}

		getVisibleMapTypes() {
			return this.state.selectedTypes.length ? this.state.selectedTypes.slice() : ALL_MAP_TYPES.slice();
		}

			getFilteredCollections() {
				const query = this.state.query;
				const visibleTypes = this.getVisibleMapTypes();
				let lines = this.data.lines.filter((line) => {
					if (!matchesQuery(line.searchText, query)) {
						return false;
					}
				if (this.state.accessibleOnly && !line.isAccessible) {
					return false;
				}
					if (!this.matchesServiceDay(line.serviceDays)) {
						return false;
					}
						return true;
					});
				if (visibleTypes.indexOf("bus") === -1) {
					lines = [];
				}
				lines = this.sortLines(lines);

			const visibleLineIds = new Set(lines.map((line) => line.id));
			let parkings = [];
			let bikes = [];
			let trains = [];
			let busStops = [];

			this.data.places.forEach((place) => {
				if (!matchesQuery(place.searchText, query)) {
					return;
				}
				if (this.state.accessibleOnly && !place.isAccessible) {
					return;
				}

					if (place.mode === "parking") {
						if (visibleTypes.indexOf("parking") === -1) {
							return;
						}
						if (this.state.freeOnly && !place.isFree) {
							return;
						}
					parkings.push(place);
					return;
				}

					if (place.mode === "velo") {
						if (visibleTypes.indexOf("velo") === -1) {
							return;
						}
						bikes.push(place);
						return;
					}

					if (place.mode === "train") {
						if (visibleTypes.indexOf("train") === -1) {
							return;
						}
						trains.push(place);
						return;
					}

					if (place.mode === "bus") {
						if (visibleTypes.indexOf("bus") === -1) {
							return;
						}
						if (this.state.day !== "tous" && Array.isArray(place.relatedLineIds) && place.relatedLineIds.length) {
							const hasVisibleLine = place.relatedLineIds.some((lineId) => visibleLineIds.has(lineId));
						if (!hasVisibleLine) {
							return;
						}
					}
					busStops.push(place);
				}
			});

			parkings = this.sortParkings(parkings);
			bikes = bikes.slice().sort((a, b) => this.compareByTitle(a, b));
			trains = trains.slice().sort((a, b) => this.compareByTitle(a, b));
			busStops = this.groupBusStops(busStops).sort((a, b) => this.compareByTitle(a, b));
			const vehicles = (this.data.realtime && Array.isArray(this.data.realtime.vehicles) ? this.data.realtime.vehicles : [])
				.filter((vehicle) => visibleTypes.indexOf("bus") !== -1 && visibleLineIds.has(vehicle.lineId))
				.filter((vehicle) => this.showAllRouteVehiclesOnMap || vehicle.servesTrackedStops !== false)
				.filter((vehicle) => matchesQuery(vehicle.searchText || "", query))
				.sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0));
			const realtimeAlerts = (this.data.realtime && Array.isArray(this.data.realtime.alerts) ? this.data.realtime.alerts : [])
				.filter(() => visibleTypes.indexOf("bus") !== -1)
				.filter((alert) => !Array.isArray(alert.lineIds) || !alert.lineIds.length || alert.lineIds.some((lineId) => visibleLineIds.has(lineId)))
				.filter((alert) => matchesQuery(alert.searchText || "", query));

			return {
				lines: lines,
				parkings: parkings,
				bikes: bikes,
				trains: trains,
				busStops: busStops,
				vehicles: vehicles,
				realtimeAlerts: realtimeAlerts
			};
		}

		matchesServiceDay(serviceDays) {
			if (this.state.day === "tous") {
				return true;
			}
			if (!Array.isArray(serviceDays) || !serviceDays.length) {
				return true;
			}
			return serviceDays.indexOf(this.state.day) !== -1;
		}

		sortLines(lines) {
			const items = lines.slice();
			if (this.state.busSort === "frequency") {
				return items.sort((a, b) => this.parseFrequencyValue(a.frequencyLabel) - this.parseFrequencyValue(b.frequencyLabel) || this.compareByTitle(a, b));
			}
			return items.sort((a, b) => this.compareByTitle(a, b));
		}

		sortParkings(parkings) {
			const items = parkings.slice();
			if (this.state.parkingSort === "name") {
				return items.sort((a, b) => this.compareByTitle(a, b));
			}
			if (this.state.parkingSort === "capacite") {
				return items.sort((a, b) => (b.totalPlaces || 0) - (a.totalPlaces || 0) || this.compareByTitle(a, b));
			}
			return items.sort((a, b) => (b.availablePlaces || 0) - (a.availablePlaces || 0) || this.compareByTitle(a, b));
		}

		compareByTitle(a, b) {
			return String(a.title || "").localeCompare(String(b.title || ""), "fr", { sensitivity: "base" });
		}

		getTransportBaseTitle(title) {
			return String(title || "").replace(/\s*\([^)]*\)\s*$/, "").trim();
		}

		canGroupBusPlaces(left, right) {
			const leftTitle = this.getTransportBaseTitle(left && left.title);
			const rightTitle = this.getTransportBaseTitle(right && right.title);
			if (!leftTitle || !rightTitle || leftTitle.localeCompare(rightTitle, "fr", { sensitivity: "base" }) !== 0) {
				return false;
			}

			const leftProvider = String((left && left.providerKey) || "");
			const rightProvider = String((right && right.providerKey) || "");
			if (leftProvider !== rightProvider) {
				return false;
			}

			if (String((left && left.mode) || "") !== "bus" || String((right && right.mode) || "") !== "bus") {
				return false;
			}

			const leftLat = typeof left.latitude === "number" ? left.latitude : null;
			const leftLng = typeof left.longitude === "number" ? left.longitude : null;
			const rightLat = typeof right.latitude === "number" ? right.latitude : null;
			const rightLng = typeof right.longitude === "number" ? right.longitude : null;
			if (leftLat === null || leftLng === null || rightLat === null || rightLng === null) {
				return false;
			}

			const distance = Math.sqrt(Math.pow(leftLat - rightLat, 2) + Math.pow(leftLng - rightLng, 2));
			return distance <= 0.0002;
		}

		groupBusStops(places) {
			if (!Array.isArray(places) || places.length < 2) {
				return Array.isArray(places) ? places.slice() : [];
			}

			const groups = [];

			places.forEach((place) => {
				let assigned = false;

				for (let index = 0; index < groups.length; index += 1) {
					if (this.canGroupBusPlaces(groups[index][0], place)) {
						groups[index].push(place);
						assigned = true;
						break;
					}
				}

				if (!assigned) {
					groups.push([place]);
				}
			});

			return groups.map((group) => this.normalizeGroupedBusStop(group)).filter(Boolean);
		}

		mergeUniquePlaceList(places, key) {
			return Array.from(new Set((Array.isArray(places) ? places : []).reduce((values, place) => {
				const items = Array.isArray(place && place[key]) ? place[key] : [];
				return values.concat(items);
			}, [])));
		}

		normalizeGroupedBusStop(group) {
			if (!Array.isArray(group) || !group.length) {
				return null;
			}

			if (group.length === 1) {
				const place = group[0];
				return Object.assign({}, place, {
					childPlaceIds: [String(place.id || "")]
				});
			}

			const referencePlaces = group.filter((place) => {
				return (Array.isArray(place.relatedLineIds) && place.relatedLineIds.length) || (Array.isArray(place.relatedLineCodes) && place.relatedLineCodes.length);
			});
			const effectivePlaces = referencePlaces.length ? referencePlaces : group;
			const primaryPlace = effectivePlaces[0];
			const baseTitle = this.getTransportBaseTitle(primaryPlace.title) || String(primaryPlace.title || "");
			const childPlaceIds = group.map((place) => String(place.id || "")).filter(Boolean);
			const coordinates = effectivePlaces.reduce((accumulator, place) => {
				if (typeof place.latitude === "number" && typeof place.longitude === "number") {
					accumulator.lat += place.latitude;
					accumulator.lng += place.longitude;
					accumulator.count += 1;
				}
				return accumulator;
			}, { lat: 0, lng: 0, count: 0 });

			return Object.assign({}, primaryPlace, {
				id: "transport-hub-" + childPlaceIds.join("-"),
				title: baseTitle,
				subtitle: primaryPlace.subtitle || (group.length > 1 ? group.length + " quais" : ""),
				latitude: coordinates.count ? coordinates.lat / coordinates.count : primaryPlace.latitude,
				longitude: coordinates.count ? coordinates.lng / coordinates.count : primaryPlace.longitude,
				isAccessible: group.some((place) => !!place.isAccessible),
				gtfsStopIds: this.mergeUniquePlaceList(effectivePlaces, "gtfsStopIds"),
				destinationLabels: this.mergeUniquePlaceList(effectivePlaces, "destinationLabels"),
				relatedLineIds: this.mergeUniquePlaceList(effectivePlaces, "relatedLineIds"),
				relatedLineCodes: this.mergeUniquePlaceList(effectivePlaces, "relatedLineCodes"),
				relatedLineTitles: this.mergeUniquePlaceList(effectivePlaces, "relatedLineTitles"),
				searchText: group.map((place) => String(place.searchText || "")).filter(Boolean).join(" "),
				childPlaceIds: childPlaceIds
			});
		}

		parseFrequencyValue(label) {
			const match = String(label || "").match(/(\d+)/);
			return match ? parseInt(match[1], 10) : 9999;
		}

			getGroupedScheduleStops(line) {
			if (!line || !line.schedule || !Array.isArray(line.schedule.stops)) {
				return [];
			}

			const stopMap = new Map();

			line.schedule.stops.forEach((stop) => {
				const stopName = String(stop.stopName || stop.stopId || "").trim();
				if (!stopName) {
					return;
				}

				if (!stopMap.has(stopName)) {
					stopMap.set(stopName, {
						stopName: stopName,
						stopIds: [],
						directionMap: new Map()
					});
				}

				const stopGroup = stopMap.get(stopName);
				if (stop.stopId && stopGroup.stopIds.indexOf(stop.stopId) === -1) {
					stopGroup.stopIds.push(stop.stopId);
				}

				(Array.isArray(stop.directions) ? stop.directions : []).forEach((direction) => {
					const directionId = String(direction.directionId || "");
					const headsign = String(direction.headsign || "");
					const directionKey = directionId + "|" + headsign;

					if (!stopGroup.directionMap.has(directionKey)) {
						stopGroup.directionMap.set(directionKey, {
							directionId: directionId,
							headsign: headsign,
							dayTypes: {}
						});
					}

					const directionGroup = stopGroup.directionMap.get(directionKey);
					const dayTypes = direction && direction.dayTypes ? direction.dayTypes : {};

					Object.keys(dayTypes).forEach((dayKey) => {
						const day = dayTypes[dayKey];
						if (!day || !Array.isArray(day.departures) || !day.departures.length) {
							return;
						}

						if (!directionGroup.dayTypes[dayKey]) {
							directionGroup.dayTypes[dayKey] = {
								key: day.key || dayKey,
								label: day.label || dayKey,
								referenceDate: day.referenceDate || "",
								departures: [],
								count: 0,
								truncated: false
							};
						}

						const target = directionGroup.dayTypes[dayKey];
						target.departures = Array.from(new Set(target.departures.concat(day.departures))).sort();
						target.count = target.departures.length;
					});
				});
			});

			return Array.from(stopMap.values())
				.map((stopGroup) => {
					return {
						stopName: stopGroup.stopName,
						stopIds: stopGroup.stopIds.slice(),
						directions: Array.from(stopGroup.directionMap.values())
					};
				})
				.sort((a, b) => String(a.stopName || "").localeCompare(String(b.stopName || ""), "fr", { sensitivity: "base" }));
		}

			getLineSelection(lineId) {
				const key = String(lineId || "");
				if (!key) {
					return { directionKey: "", stopId: "" };
				}

				if (!this.state.lineSelections[key]) {
					this.state.lineSelections[key] = {
						directionKey: "",
						stopId: ""
					};
				}

				return this.state.lineSelections[key];
			}

			updateLineSelection(lineId, updates) {
				const selection = this.getLineSelection(lineId);
				this.state.lineSelections[String(lineId || "")] = Object.assign({}, selection, updates || {});
				this.render();
			}

			getDirectionKey(direction) {
				return String(direction && direction.directionId ? direction.directionId : "") + "|" + String(direction && direction.headsign ? direction.headsign : "");
			}

			getDirectionLabel(direction) {
				if (direction && direction.headsign) {
					return String(direction.headsign);
				}
				if (direction && direction.directionId) {
					return "Direction " + String(direction.directionId);
				}
				return "Direction";
			}

			getPlaceDirectionLabels(place) {
				if (!place || !Array.isArray(this.data.lines) || !this.data.lines.length) {
					return [];
				}

				const stopIds = Array.isArray(place.gtfsStopIds) ? place.gtfsStopIds.map((stopId) => String(stopId || "")).filter(Boolean) : [];
				const relatedLineIds = Array.isArray(place.relatedLineIds) ? place.relatedLineIds.map((lineId) => String(lineId || "")).filter(Boolean) : [];
				if (!stopIds.length || !relatedLineIds.length) {
					return [];
				}

				const directionLabels = new Set();

				this.data.lines.forEach((line) => {
					if (!line || !line.schedule || !Array.isArray(line.schedule.stops) || !relatedLineIds.includes(String(line.id || ""))) {
						return;
					}

					line.schedule.stops.forEach((stop) => {
						if (!stopIds.includes(String(stop && stop.stopId ? stop.stopId : ""))) {
							return;
						}

						(Array.isArray(stop.directions) ? stop.directions : []).forEach((direction) => {
							const label = this.getDirectionLabel(direction);
							if (label) {
								directionLabels.add(label);
							}
						});
					});
				});

				return Array.from(directionLabels).sort((left, right) => left.localeCompare(right, "fr", { sensitivity: "base" }));
			}

			getDirectionForStop(stop, directionKey) {
				if (!stop || !Array.isArray(stop.directions)) {
					return null;
				}

				return stop.directions.find((direction) => this.getDirectionKey(direction) === directionKey) || null;
			}

			getLineDirections(line) {
				if (!line || !line.schedule || !Array.isArray(line.schedule.stops)) {
					return [];
				}

				const directionMap = new Map();
				line.schedule.stops.forEach((stop) => {
					(Array.isArray(stop.directions) ? stop.directions : []).forEach((direction) => {
						const key = this.getDirectionKey(direction);
						if (!key || directionMap.has(key)) {
							return;
						}

						directionMap.set(key, {
							key: key,
							label: this.getDirectionLabel(direction),
							directionId: String(direction.directionId || ""),
							headsign: String(direction.headsign || "")
						});
					});
				});

				return Array.from(directionMap.values()).sort((a, b) => String(a.label || "").localeCompare(String(b.label || ""), "fr", { sensitivity: "base" }));
			}

			getDepartureCountForDirection(direction) {
				if (!direction || !direction.dayTypes) {
					return 0;
				}

				return Object.keys(direction.dayTypes).reduce((count, dayKey) => {
					const day = direction.dayTypes[dayKey];
					return count + (day && typeof day.count === "number" ? day.count : (Array.isArray(day && day.departures) ? day.departures.length : 0));
				}, 0);
			}

			getStopOptionLabel(stopId, stopName, isDuplicateName) {
				const baseLabel = String(stopName || stopId || "");
				if (!isDuplicateName) {
					return baseLabel;
				}

				const suffix = String(stopId || "").split(":").pop();
				return baseLabel + " (" + suffix + ")";
			}

			getStopsForDirection(line, directionKey) {
				if (!line || !line.schedule || !Array.isArray(line.schedule.stops) || !directionKey) {
					return [];
				}

				const matchingStops = [];
				line.schedule.stops.forEach((stop) => {
					const direction = this.getDirectionForStop(stop, directionKey);
					if (!direction) {
						return;
					}

					matchingStops.push({
						stopId: String(stop.stopId || ""),
						stopName: String(stop.stopName || stop.stopId || ""),
						direction: direction
					});
				});

				const nameCounts = matchingStops.reduce((counts, stop) => {
					const key = String(stop.stopName || "");
					if (!key) {
						return counts;
					}
					counts[key] = (counts[key] || 0) + 1;
					return counts;
				}, {});

				const stopMap = new Map();
				matchingStops.forEach((stop) => {
					if (!stop.stopId || stopMap.has(stop.stopId)) {
						return;
					}

					stopMap.set(stop.stopId, {
						stopId: stop.stopId,
						stopName: stop.stopName,
						label: this.getStopOptionLabel(stop.stopId, stop.stopName, (nameCounts[stop.stopName] || 0) > 1),
						direction: stop.direction,
						departureCount: this.getDepartureCountForDirection(stop.direction)
					});
				});

				return Array.from(stopMap.values()).sort((a, b) => String(a.label || "").localeCompare(String(b.label || ""), "fr", { sensitivity: "base" }));
			}

			getDefaultDirectionKey(line) {
				const directions = this.getLineDirections(line);
				if (!directions.length) {
					return "";
				}

				const selection = this.getLineSelection(line.id);
				if (selection.directionKey && directions.some((direction) => direction.key === selection.directionKey)) {
					return selection.directionKey;
				}

				const referenceStops = line && line.referenceStops ? line.referenceStops : {};
				const referencedDirection = directions.find((direction) => {
					const referenceStopId = referenceStops[direction.key];
					if (!referenceStopId) {
						return false;
					}
					return this.getStopsForDirection(line, direction.key).some((stop) => stop.stopId === referenceStopId);
				});

				if (referencedDirection) {
					return referencedDirection.key;
				}

				return directions[0].key;
			}

			getDefaultStopId(line, directionKey) {
				const stops = this.getStopsForDirection(line, directionKey);
				if (!stops.length) {
					return "";
				}

				const referenceStops = line && line.referenceStops ? line.referenceStops : {};
				const configuredStopId = referenceStops[directionKey];
				if (configuredStopId && stops.some((stop) => stop.stopId === configuredStopId)) {
					return configuredStopId;
				}

				const gareStop = stops.find((stop) => /gare/i.test(String(stop.stopName || "")));
				if (gareStop) {
					return gareStop.stopId;
				}

				const rankedStops = stops.slice().sort((a, b) => (b.departureCount || 0) - (a.departureCount || 0) || String(a.label || "").localeCompare(String(b.label || ""), "fr", { sensitivity: "base" }));
				return rankedStops[0] ? rankedStops[0].stopId : "";
			}

			getCurrentLineScheduleSelection(line) {
				const directions = this.getLineDirections(line);
				if (!directions.length) {
					return null;
				}

				const selection = this.getLineSelection(line.id);
				const directionKey = this.getDefaultDirectionKey(line);
				const stops = this.getStopsForDirection(line, directionKey);
				if (!stops.length) {
					return null;
				}

				let stopId = selection.stopId;
				if (!stopId || !stops.some((stop) => stop.stopId === stopId)) {
					stopId = this.getDefaultStopId(line, directionKey);
				}

				const selectedStop = stops.find((stop) => stop.stopId === stopId) || stops[0];
				if (!selectedStop) {
					return null;
				}

				return {
					directions: directions,
					directionKey: directionKey,
					stops: stops,
					stopId: selectedStop.stopId,
					selectedStop: selectedStop,
					selectedDirection: selectedStop.direction
				};
			}

			renderLineDirectionPreview(line) {
				const directions = this.getLineDirections(line);
				if (!directions.length) {
					return '<p class="bte-card__tile-empty">Horaires détaillés indisponibles.</p>';
				}

				const previewItems = directions.slice(0, 2).map((direction) => {
					return '<span class="bte-card__tile-direction">' + escapeHtml(direction.label) + '</span>';
				});

				if (directions.length > 2) {
					previewItems.push('<span class="bte-card__tile-direction bte-card__tile-direction--more">+' + escapeHtml(directions.length - 2) + '</span>');
				}

				return '<div class="bte-card__tile-directions">' + previewItems.join("") + '</div>';
			}

			renderScheduleControls(line, selection) {
				if (!selection) {
					return "";
				}

				return '<div class="bte-card__schedule-controls">' +
					'<div class="bte-card__schedule-control">' +
						'<span class="bte-card__schedule-control-label">Direction</span>' +
						'<div class="bte-card__direction-tabs">' +
							selection.directions.map((direction) => '<button type="button" class="bte-card__direction-tab' + (direction.key === selection.directionKey ? ' is-active' : '') + '" data-line-id="' + escapeHtml(line.id) + '" data-line-direction="' + escapeHtml(direction.key) + '">' + escapeHtml(direction.label) + '</button>').join("") +
						'</div>' +
					'</div>' +
					'<label class="bte-card__schedule-control">' +
						'<span class="bte-card__schedule-control-label">Arrêt affiché</span>' +
						'<select class="bte-card__schedule-select" data-line-id="' + escapeHtml(line.id) + '" data-line-stop>' +
							selection.stops.map((stop) => '<option value="' + escapeHtml(stop.stopId) + '"' + (stop.stopId === selection.stopId ? ' selected' : '') + '>' + escapeHtml(stop.label) + '</option>').join("") +
						'</select>' +
					'</label>' +
				'</div>';
			}

			renderScheduleDayType(day) {
			if (!day || !Array.isArray(day.departures) || !day.departures.length) {
				return "";
			}

			return '<div class="bte-card__schedule-day">' +
				'<p class="bte-card__schedule-day-label">' + escapeHtml(day.label) + '<span class="bte-card__schedule-day-count">' + escapeHtml(day.count || day.departures.length) + ' départs</span></p>' +
				'<ul class="bte-card__schedule-times-list">' +
					day.departures.map((departure) => '<li class="bte-card__schedule-time-pill">' + escapeHtml(departure) + '</li>').join("") +
				'</ul>' +
			'</div>';
		}

			getRealtimeStatusLabel(item) {
				if (!item || !item.status) {
					return "";
				}

				if (item.status === "cancelled") {
					return "Supprimé";
				}
				if (item.status === "skipped") {
					return "Arrêt sauté";
				}
				if (typeof item.delayMinutes === "number" && item.delayMinutes !== 0) {
					return (item.delayMinutes > 0 ? "+" : "") + String(item.delayMinutes) + " min";
				}
				if (item.isRealtime) {
					return "Temps réel";
				}
				return "Théorique";
			}

			getVehicleStatusLabel(status) {
				switch (String(status || "")) {
					case "incoming_at":
						return "À l'approche";
					case "stopped_at":
						return "À l'arrêt";
					case "in_transit_to":
						return "En trajet";
					default:
						return "";
				}
			}

			getVehicleDirectionLabel(vehicle) {
				if (!vehicle) {
					return "";
				}

				if (vehicle.headsign) {
					return String(vehicle.headsign);
				}

				if (vehicle.terminalLocality) {
					return String(vehicle.terminalLocality);
				}

				if (vehicle.terminalStopName) {
					return String(vehicle.terminalStopName);
				}

				return "";
			}

			formatRealtimeTimestamp(timestamp) {
				if (!timestamp) {
					return "";
				}

				try {
					return new Date(Number(timestamp) * 1000).toLocaleTimeString("fr-FR", {
						hour: "2-digit",
						minute: "2-digit"
					});
				} catch (error) {
					return "";
				}
			}

			renderRealtimeAlertCard(alert) {
				if (!alert) {
					return "";
				}

				const effect = alert.effectLabel ? '<span class="bte-rt-alert__badge bte-rt-alert__badge--' + escapeHtml(String(alert.severity || "info")) + '">' + escapeHtml(alert.effectLabel) + '</span>' : "";
				const cause = alert.causeLabel ? '<span class="bte-rt-alert__meta">' + escapeHtml(alert.causeLabel) + '</span>' : "";
				const period = alert.startLabel || alert.endLabel ? '<span class="bte-rt-alert__meta">' + escapeHtml([alert.startLabel || "", alert.endLabel || ""].filter(Boolean).join(" → ")) + '</span>' : "";
				const lines = Array.isArray(alert.lineCodes) && alert.lineCodes.length ? '<span class="bte-rt-alert__meta">Lignes ' + escapeHtml(alert.lineCodes.join(", ")) + '</span>' : "";
				const action = alert.url ? '<a class="bte-rt-alert__link" href="' + escapeHtml(alert.url) + '">Source officielle ' + getIconMarkup("arrow") + '</a>' : "";
				return '<article class="bte-rt-alert bte-rt-alert--' + escapeHtml(String(alert.severity || "info")) + '">' +
					'<div class="bte-rt-alert__head">' +
						'<p class="bte-rt-alert__title">' + escapeHtml(alert.headerText || "Perturbation transport") + '</p>' +
						effect +
					'</div>' +
					(alert.descriptionText ? '<p class="bte-rt-alert__body">' + escapeHtml(alert.descriptionText) + '</p>' : '') +
					'<div class="bte-rt-alert__meta-row">' + [cause, period, lines].filter(Boolean).join("") + '</div>' +
					action +
				'</article>';
			}

			renderRealtimeAlerts(filtered) {
				if (!this.realtimeAlertsElement) {
					return;
				}

				const alerts = filtered && Array.isArray(filtered.realtimeAlerts) ? filtered.realtimeAlerts : [];
				if (!alerts.length) {
					this.realtimeAlertsElement.innerHTML = "";
					this.realtimeAlertsElement.hidden = true;
					return;
				}

				this.realtimeAlertsElement.innerHTML = '<div class="bte-rt-alerts__head">' +
					'<p class="bte-rt-alerts__kicker">Temps réel</p>' +
					'<h3 class="bte-rt-alerts__title">Perturbations transport</h3>' +
				'</div>' +
				'<div class="bte-rt-alerts__grid">' +
					alerts.slice(0, 6).map((alert) => this.renderRealtimeAlertCard(alert)).join("") +
				'</div>';
				this.realtimeAlertsElement.hidden = false;
			}

			renderRealtimeVehicleItem(vehicle) {
				if (!vehicle) {
					return "";
				}

				const label = vehicle.vehicleLabel || vehicle.vehicleId || "Véhicule";
				const direction = this.getVehicleDirectionLabel(vehicle);
				const timestamp = this.formatRealtimeTimestamp(vehicle.timestamp);
				const status = this.getVehicleStatusLabel(vehicle.currentStatus);
				return '<li class="bte-card__vehicle-item">' +
					'<span class="bte-card__vehicle-name">' + escapeHtml(label) + '</span>' +
					(direction ? '<span class="bte-card__vehicle-meta">' + escapeHtml(direction) + '</span>' : '') +
					(status ? '<span class="bte-card__vehicle-meta">' + escapeHtml(status) + '</span>' : '') +
					(timestamp ? '<span class="bte-card__vehicle-meta">' + escapeHtml(timestamp) + '</span>' : '') +
				'</li>';
			}

			getRealtimeVehiclesForDirection(line, direction) {
				const realtime = line && line.schedule && line.schedule.realtime ? line.schedule.realtime : null;
				const vehicles = realtime && Array.isArray(realtime.vehicles) ? realtime.vehicles : [];
				if (!vehicles.length) {
					return [];
				}

				if (!direction) {
					return vehicles;
				}

				const selectedKey = this.getDirectionKey(direction);
				const selectedDirectionId = String(direction.directionId || "");
				const selectedHeadsign = normalizeText(direction.headsign || "");

				return vehicles.filter((vehicle) => {
					const vehicleKey = this.getDirectionKey(vehicle);
					if (vehicleKey && selectedKey) {
						return vehicleKey === selectedKey;
					}

					const vehicleDirectionId = String(vehicle && vehicle.directionId ? vehicle.directionId : "");
					if (selectedDirectionId && vehicleDirectionId) {
						return vehicleDirectionId === selectedDirectionId;
					}

					const vehicleHeadsign = normalizeText(vehicle && vehicle.headsign ? vehicle.headsign : "");
					if (selectedHeadsign && vehicleHeadsign) {
						return vehicleHeadsign === selectedHeadsign;
					}

					return true;
				});
			}

			renderRealtimeVehiclesForLine(line, direction) {
				const vehicles = this.getRealtimeVehiclesForDirection(line, direction);
				if (!vehicles.length) {
					return "";
				}

				return '<section class="bte-card__vehicles">' +
					'<div class="bte-card__vehicles-head">' +
						'<p class="bte-card__vehicles-title">Véhicules en circulation</p>' +
					'</div>' +
					'<ul class="bte-card__vehicle-list">' +
						vehicles.slice(0, 5).map((vehicle) => this.renderRealtimeVehicleItem(vehicle)).join("") +
					'</ul>' +
				'</section>';
			}

			getLastPassedVehicleForSelection(line, selection) {
				if (!line || !selection || !selection.selectedDirection || !selection.selectedStop) {
					return null;
				}

				const vehicles = this.getRealtimeVehiclesForDirection(line, selection.selectedDirection);
				const todayTrips = Array.isArray(selection.selectedDirection.todayTrips) ? selection.selectedDirection.todayTrips : [];
				if (!vehicles.length || !todayTrips.length) {
					return null;
				}

				const tripIndex = new Map();
				todayTrips.forEach((item) => {
					const tripId = String(item && item.tripId ? item.tripId : "");
					if (!tripId || tripIndex.has(tripId)) {
						return;
					}
					tripIndex.set(tripId, item);
				});

				const candidates = vehicles.map((vehicle) => {
					const tripId = String(vehicle && vehicle.tripId ? vehicle.tripId : "");
					if (!tripId || !tripIndex.has(tripId)) {
						return null;
					}

					const passage = tripIndex.get(tripId);
					const vehicleSequence = parseInt(vehicle && vehicle.currentStopSequence ? vehicle.currentStopSequence : 0, 10);
					const stopSequence = parseInt(passage && passage.stopSequence ? passage.stopSequence : 0, 10);
					if (!vehicleSequence || !stopSequence || vehicleSequence <= stopSequence) {
						return null;
					}

					return {
						vehicle,
						passage
					};
				}).filter(Boolean);

				if (!candidates.length) {
					return null;
				}

				candidates.sort((left, right) => {
					const leftTime = parseInt(left.passage && left.passage.displayTimestamp ? left.passage.displayTimestamp : 0, 10);
					const rightTime = parseInt(right.passage && right.passage.displayTimestamp ? right.passage.displayTimestamp : 0, 10);
					if (rightTime !== leftTime) {
						return rightTime - leftTime;
					}

					const leftVehicleTs = parseInt(left.vehicle && left.vehicle.timestamp ? left.vehicle.timestamp : 0, 10);
					const rightVehicleTs = parseInt(right.vehicle && right.vehicle.timestamp ? right.vehicle.timestamp : 0, 10);
					return rightVehicleTs - leftVehicleTs;
				});

				return candidates[0];
			}

			renderLastPassedForSelection(line, selection) {
				if (!selection || !selection.selectedDirection) {
					return "";
				}

				const futureItems = selection.selectedDirection.liveDepartures && Array.isArray(selection.selectedDirection.liveDepartures.items)
					? selection.selectedDirection.liveDepartures.items
					: [];
				if (futureItems.length) {
					return "";
				}

				const match = this.getLastPassedVehicleForSelection(line, selection);
				if (!match || !match.passage) {
					return "";
				}

				const time = match.passage.displayTime || match.passage.realtime || match.passage.scheduled || "";
				const vehicleLabel = match.vehicle && (match.vehicle.vehicleLabel || match.vehicle.vehicleId) ? String(match.vehicle.vehicleLabel || match.vehicle.vehicleId) : "";
				const direction = this.getVehicleDirectionLabel(match.vehicle);
				const updatedAt = line && line.schedule && line.schedule.realtime && line.schedule.realtime.updatedAt ? '<span class="bte-card__realtime-updated">Mis à jour ' + escapeHtml(line.schedule.realtime.updatedAt) + '</span>' : "";

				return '<section class="bte-card__realtime bte-card__realtime--last-passed">' +
					'<div class="bte-card__realtime-head">' +
						'<p class="bte-card__realtime-title">Dernier passage</p>' +
						updatedAt +
					'</div>' +
					'<ul class="bte-card__realtime-list">' +
						'<li class="bte-card__realtime-item bte-card__realtime-item--passed">' +
							(time ? '<span class="bte-card__realtime-time">' + escapeHtml(time) + '</span>' : '') +
							'<span class="bte-card__realtime-secondary">Déjà passé à cet arrêt' + (vehicleLabel ? ' · véhicule ' + escapeHtml(vehicleLabel) : '') + '</span>' +
							(direction ? '<span class="bte-card__realtime-badge">' + escapeHtml(direction) + '</span>' : '') +
						'</li>' +
					'</ul>' +
				'</section>';
			}

			renderLiveDepartureItem(item) {
				if (!item) {
					return "";
				}

				const status = String(item.status || "scheduled");
				const label = this.getRealtimeStatusLabel(item);
				let mainTime = item.displayTime || item.realtime || item.scheduled || "";
				let secondaryTime = "";

				if (status === "cancelled" || status === "skipped") {
					mainTime = item.scheduled || mainTime;
				} else if (item.isRealtime && item.realtime && item.scheduled && item.realtime !== item.scheduled) {
					secondaryTime = item.scheduled;
				}

				return '<li class="bte-card__realtime-item bte-card__realtime-item--' + escapeHtml(status) + '">' +
					'<span class="bte-card__realtime-time">' + escapeHtml(mainTime) + '</span>' +
					(secondaryTime ? '<span class="bte-card__realtime-secondary">' + escapeHtml(secondaryTime) + '</span>' : '') +
					(label ? '<span class="bte-card__realtime-badge">' + escapeHtml(label) + '</span>' : '') +
				'</li>';
			}

			renderLiveDepartures(line, direction, realtimeMeta) {
				if (!direction || !direction.liveDepartures || !Array.isArray(direction.liveDepartures.items) || !direction.liveDepartures.items.length) {
					return "";
				}

				const updatedAt = realtimeMeta && realtimeMeta.updatedAt ? '<span class="bte-card__realtime-updated">Mis à jour ' + escapeHtml(realtimeMeta.updatedAt) + '</span>' : "";

				return '<section class="bte-card__realtime">' +
					'<div class="bte-card__realtime-head">' +
						'<p class="bte-card__realtime-title">Temps réel</p>' +
						updatedAt +
					'</div>' +
					'<ul class="bte-card__realtime-list">' +
						direction.liveDepartures.items.map((item) => this.renderLiveDepartureItem(item)).join("") +
					'</ul>' +
				'</section>';
			}

			renderBusCard(line) {
				const lineCode = String(line.lineCode || line.title || "");
				const isActive = String(this.state.activeBusLineId || "") === String(line.id || "");
				const ariaLabel = ["Ligne", lineCode, line.routeLabel || line.title || ""].filter(Boolean).join(" - ");
				return '<button type="button" class="bte-line-tile' + (isActive ? ' is-active' : '') + '" data-open-line="' + escapeHtml(line.id) + '" aria-pressed="' + (isActive ? "true" : "false") + '" aria-label="' + escapeHtml(ariaLabel) + '" style="' + escapeHtml(this.getLineThemeStyle(line)) + '">' +
					'<span class="bte-line-tile__code">' + escapeHtml(lineCode) + '</span>' +
				'</button>';
			}

			renderBusLineDetail(line) {
				const trackedStops = this.getGroupedScheduleStops(line);
				const selection = this.getCurrentLineScheduleSelection(line);
				const transportUrl = String(line.transportUrl || line.externalUrl || "");
				const action = transportUrl ? '<a class="bte-card__action" href="' + escapeHtml(transportUrl) + '">Source officielle ' + getIconMarkup("arrow") + '</a>' : "";
				const detailTitle = line.routeLabel || line.title || line.lineCode || "";
				const detailSubtitleParts = [];
				if (line.routeLabel && line.title && line.title !== line.routeLabel) {
					detailSubtitleParts.push(line.title);
				}
				if (line.providerLabel) {
					detailSubtitleParts.push(line.providerLabel);
				}
				const detailSubtitle = detailSubtitleParts.join(" · ");

				if (!selection || !selection.selectedDirection) {
					return '<article class="bte__line-detail-card" style="' + escapeHtml(this.getLineThemeStyle(line)) + '">' +
						'<div class="bte__line-detail-head">' +
							'<div class="bte__line-detail-main">' +
								'<div class="bte-card__code">' + escapeHtml(line.lineCode || line.title) + '</div>' +
								'<div class="bte-card__content">' +
									'<h4 class="bte-card__title">' + escapeHtml(detailTitle) + '</h4>' +
									(detailSubtitle ? '<p class="bte-card__subtitle">' + escapeHtml(detailSubtitle) + '</p>' : '') +
								'</div>' +
							'</div>' +
							'<button type="button" class="bte__line-detail-close" data-close-line>Fermer</button>' +
						'</div>' +
						'<p class="bte-card__schedule-empty">Aucun horaire détaillé disponible pour cette ligne.</p>' +
						(action ? '<div class="bte-card__actions">' + action + '</div>' : '') +
					'</article>';
				}

				const dayTypes = selection.selectedDirection.dayTypes || {};
				let dayKeys = Object.keys(dayTypes);
				if (this.state.day !== "tous") {
					dayKeys = dayKeys.filter((dayKey) => dayKey === this.state.day);
				}

				const dayMarkup = dayKeys.map((dayKey) => this.renderScheduleDayType(dayTypes[dayKey])).join("");
				const emptyMarkup = dayMarkup ? "" : '<p class="bte-card__schedule-empty">Aucun horaire disponible pour les filtres actuels.</p>';
				const realtimeMarkup = this.renderLiveDepartures(line, selection.selectedDirection, line.schedule ? line.schedule.realtime : null);
				const lastPassedMarkup = this.renderLastPassedForSelection(line, selection);
				const alertMarkup = line && line.schedule && line.schedule.realtime && Array.isArray(line.schedule.realtime.alerts) && line.schedule.realtime.alerts.length
					? '<section class="bte-card__line-alerts"><div class="bte-card__vehicles-head"><p class="bte-card__vehicles-title">Perturbations</p></div>' + line.schedule.realtime.alerts.slice(0, 4).map((alert) => this.renderRealtimeAlertCard(alert)).join("") + '</section>'
					: "";
				const vehiclesMarkup = this.renderRealtimeVehiclesForLine(line, selection.selectedDirection);
				const metaItems = [
					line.frequencyLabel ? '<span class="bte-card__meta">' + getIconMarkup("clock") + '<span>' + escapeHtml(line.frequencyLabel) + '</span></span>' : "",
					trackedStops.length ? '<span class="bte-card__meta">' + getIconMarkup("pin") + '<span>' + escapeHtml(trackedStops.length) + ' arrêt(s) suivis</span></span>' : "",
					line.isAccessible ? '<span class="bte-card__badge bte-card__badge--outline"><span>PMR</span></span>' : ""
				].filter(Boolean).join("");

				return '<article class="bte__line-detail-card" style="' + escapeHtml(this.getLineThemeStyle(line)) + '">' +
					'<div class="bte__line-detail-head">' +
						'<div class="bte__line-detail-main">' +
								'<div class="bte-card__code">' + escapeHtml(line.lineCode || line.title) + '</div>' +
								'<div class="bte-card__content">' +
									'<p class="bte-card__tile-kicker">Horaires détaillés</p>' +
									'<h4 class="bte-card__title">' + escapeHtml(detailTitle) + '</h4>' +
									(detailSubtitle ? '<p class="bte-card__subtitle">' + escapeHtml(detailSubtitle) + '</p>' : '') +
								'</div>' +
							'</div>' +
							'<button type="button" class="bte__line-detail-close" data-close-line>Fermer</button>' +
					'</div>' +
					(metaItems ? '<div class="bte__line-detail-meta">' + metaItems + '</div>' : '') +
					this.renderScheduleControls(line, selection) +
					'<section class="bte__line-detail-body">' +
						'<div class="bte__line-detail-stop">' +
							'<h5 class="bte-card__schedule-stop-title">' + escapeHtml(selection.selectedStop.label) + '</h5>' +
							(selection.selectedDirection.headsign ? '<p class="bte-card__schedule-headsign">' + escapeHtml(selection.selectedDirection.headsign) + '</p>' : '') +
						'</div>' +
						alertMarkup +
						vehiclesMarkup +
						realtimeMarkup +
						lastPassedMarkup +
						dayMarkup +
						emptyMarkup +
					'</section>' +
					(action ? '<div class="bte-card__actions">' + action + '</div>' : '') +
				'</article>';
			}

		renderParkingCard(place) {
			const available = typeof place.availablePlaces === "number" ? place.availablePlaces : 0;
			const total = typeof place.totalPlaces === "number" ? Math.max(place.totalPlaces, 1) : 1;
			const ratio = Math.max(0, Math.min(100, Math.round((available / total) * 100)));
			const localizeAction = typeof place.latitude === "number" && typeof place.longitude === "number" ? '<button type="button" class="bte-card__secondary" data-focus-place="' + escapeHtml(place.id) + '">' + getIconMarkup("pin") + '<span>Voir sur la carte</span></button>' : "";
				return '<article class="bte-card bte-card--parking">' +
					'<div class="bte-card__head">' +
						'<div class="bte-card__main"><span class="bte-card__mode-token">' + getIconMarkup("parking") + '</span><div class="bte-card__content"><h4 class="bte-card__title">' + escapeHtml(place.title) + '</h4>' +
						(place.parkingType ? '<p class="bte-card__subtitle">' + escapeHtml(place.parkingType) + '</p>' : '') + '</div></div>' +
						(place.isFree ? '<span class="bte-card__badge">Gratuit</span>' : '') +
					'</div>' +
					'<div class="bte-card__meter-label"><span>Places disponibles</span><strong>' + escapeHtml(available) + ' / ' + escapeHtml(place.totalPlaces || 0) + '</strong></div>' +
				'<div class="bte-card__meter"><span class="bte-card__meter-fill" style="width:' + ratio + '%"></span></div>' +
				'<div class="bte-card__meta-row">' +
					(typeof place.pmrPlaces === "number" ? '<span class="bte-card__meta">' + getIconMarkup("accessible") + '<span>' + escapeHtml(place.pmrPlaces) + ' places PMR</span></span>' : '') +
				'</div>' +
				localizeAction +
			'</article>';
		}

		renderBikeCard(place) {
			const freeSlots = typeof place.slotsTotal === "number" && typeof place.bikesAvailable === "number" ? Math.max(place.slotsTotal - place.bikesAvailable, 0) : null;
			const hasExternalUrl = /^https?:\/\//i.test(String(place.externalUrl || ""));
			const badge = typeof place.slotsTotal === "number" ? '<span class="bte-card__badge">' + escapeHtml(place.slotsTotal) + ' places</span>' : "";
			const stats = [];

			if (typeof place.slotsTotal === "number") {
				stats.push('<div><span>Capacite</span><strong>' + escapeHtml(place.slotsTotal) + ' places</strong></div>');
			}

			if (typeof place.bikesAvailable === "number") {
				stats.push('<div><span>Velos presents</span><strong>' + escapeHtml(place.bikesAvailable) + '</strong></div>');
			}

			if (typeof freeSlots === "number") {
				stats.push('<div><span>Places libres</span><strong>' + escapeHtml(freeSlots) + '</strong></div>');
			}

			if (typeof place.electricBikes === "number") {
				stats.push('<div><span>Velos electriques</span><strong>' + escapeHtml(place.electricBikes) + '</strong></div>');
			}

			const meta = place.address ? '<div class="bte-card__meta-row"><span class="bte-card__meta">' + getIconMarkup("pin") + '<span>' + escapeHtml(place.address) + '</span></span></div>' : "";
			const actions = [
				hasExternalUrl ? '<a class="bte-card__action" href="' + escapeHtml(place.externalUrl) + '">Infos et abonnement ' + getIconMarkup("arrow") + '</a>' : "",
				(typeof place.latitude === "number" && typeof place.longitude === "number") ? '<button type="button" class="bte-card__secondary" data-focus-place="' + escapeHtml(place.id) + '">' + getIconMarkup("pin") + '<span>Voir sur la carte</span></button>' : ""
			].filter(Boolean).join("");

				return '<article class="bte-card bte-card--bike">' +
					'<div class="bte-card__head">' +
						'<div class="bte-card__main"><span class="bte-card__mode-token">' + getIconMarkup("bike") + '</span><div class="bte-card__content"><h4 class="bte-card__title">' + escapeHtml(place.title) + '</h4>' +
						(place.subtitle ? '<p class="bte-card__subtitle">' + escapeHtml(place.subtitle) + '</p>' : '') + '</div></div>' +
						badge +
					'</div>' +
					meta +
					(stats.length ? '<div class="bte-card__stats">' + stats.join("") + '</div>' : '') +
					(actions ? '<div class="bte-card__actions">' + actions + '</div>' : '') +
				'</article>';
			}

		renderTrainCard(place) {
			const departures = Array.isArray(place.trainDepartures) && place.trainDepartures.length ? '<ul class="bte-card__list">' + place.trainDepartures.map((item) => '<li>' + escapeHtml(item) + '</li>').join("") + '</ul>' : '';
			const information = Array.isArray(place.trainInformation) && place.trainInformation.length ? '<ul class="bte-card__list bte-card__list--muted">' + place.trainInformation.map((item) => '<li>' + escapeHtml(item) + '</li>').join("") + '</ul>' : '';
			const actions = [
				(place.externalUrl ? '<a class="bte-card__action" href="' + escapeHtml(place.externalUrl) + '">Consulter les horaires ' + getIconMarkup("arrow") + '</a>' : ''),
				(typeof place.latitude === "number" && typeof place.longitude === "number" ? '<button type="button" class="bte-card__secondary" data-focus-place="' + escapeHtml(place.id) + '">' + getIconMarkup("pin") + '<span>Voir sur la carte</span></button>' : '')
			].filter(Boolean).join("");
				return '<article class="bte-card bte-card--train">' +
					'<div class="bte-card__head">' +
						'<div class="bte-card__main"><span class="bte-card__mode-token">' + getIconMarkup("train") + '</span><div class="bte-card__content"><h4 class="bte-card__title">' + escapeHtml(place.title) + '</h4>' +
						((place.subtitle || place.address) ? '<p class="bte-card__subtitle">' + escapeHtml(place.subtitle || place.address) + '</p>' : '') + '</div></div>' +
					'</div>' +
					departures +
					information +
				(actions ? '<div class="bte-card__actions">' + actions + '</div>' : '') +
			'</article>';
		}
	}

	function init(scope) {
		const context = scope || document;
		context.querySelectorAll(".bte").forEach(function (root) {
			if (!root.__bteInstance) {
				new BellevueTransportExplorer(root);
			}
		});
	}

	function bindElementorHook(attempt) {
		const currentAttempt = attempt || 0;
		if (window.elementorFrontend && window.elementorFrontend.hooks) {
			window.elementorFrontend.hooks.addAction("frontend/element_ready/bellevue-transport-explorer.default", function ($scope) {
				const scopeNode = $scope && $scope[0] ? $scope[0] : $scope;
				if (scopeNode && typeof scopeNode.querySelectorAll === "function") {
					init(scopeNode);
				}
			});
			return;
		}

		if (currentAttempt < 20) {
			window.setTimeout(function () {
				bindElementorHook(currentAttempt + 1);
			}, 300);
		}
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", function () {
			init(document);
		});
	} else {
		init(document);
	}

	bindElementorHook();
})();
