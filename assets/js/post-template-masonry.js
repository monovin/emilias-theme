(() => {
	const gridSelector = ".wp-block-post-template.is-style-masonry";
	const itemSelector = "li.wp-block-post";
	let allFrame = null;
	let nativeMasonrySupported = null;
	const grids = new Set();
	const states = new WeakMap();

	function getItems(grid) {
		return Array.from(grid.children).filter((child) =>
			child.matches(itemSelector),
		);
	}

	function supportsNativeMasonryLayout() {
		if (
			!window.CSS ||
			typeof CSS.supports !== "function" ||
			!CSS.supports("grid-template-rows", "masonry")
		) {
			return false;
		}

		const testGrid = document.createElement("div");
		const first = document.createElement("div");
		const second = document.createElement("div");
		const third = document.createElement("div");

		testGrid.style.cssText =
			"position:absolute;visibility:hidden;pointer-events:none;left:-9999px;top:0;display:grid;grid-template-columns:100px 100px;grid-template-rows:masonry;gap:0;width:200px;";
		first.style.height = "100px";
		second.style.height = "40px";
		third.style.height = "40px";

		testGrid.append(first, second, third);
		document.body.append(testGrid);

		const firstBottom = first.getBoundingClientRect().bottom;
		const thirdTop = third.getBoundingClientRect().top;

		testGrid.remove();

		return thirdTop < firstBottom - 1;
	}

	function getNativeMasonrySupported() {
		if (nativeMasonrySupported === null) {
			nativeMasonrySupported = supportsNativeMasonryLayout();
		}

		return nativeMasonrySupported;
	}

	function shouldForceFallback() {
		try {
			return (
				window.sessionStorage?.getItem("emiliasThemeForceMasonryFallback") ===
				"1"
			);
		} catch {
			return false;
		}
	}

	function getColumnMetrics(grid) {
		const styles = window.getComputedStyle(grid);
		const columnGap = Number.parseFloat(styles.columnGap) || 0;
		const rowGap = Number.parseFloat(styles.rowGap) || 0;
		const columns = styles.gridTemplateColumns
			.split(" ")
			.map((track) => Number.parseFloat(track))
			.filter((track) => Number.isFinite(track) && track > 0);
		const fallbackWidth = grid.getBoundingClientRect().width;

		if (columns.length > 0) {
			return {
				columnGap,
				columns,
				isRtl: styles.direction === "rtl",
				rowGap,
			};
		}

		return {
			columnGap,
			columns: [fallbackWidth],
			isRtl: styles.direction === "rtl",
			rowGap,
		};
	}

	function layoutGrid(grid) {
		if (!grid.isConnected || !grid.matches(gridSelector)) {
			destroyGrid(grid);
			return;
		}

		const items = getItems(grid);
		const { columnGap, columns, isRtl, rowGap } = getColumnMetrics(grid);
		const columnHeights = columns.map(() => 0);
		const gridWidth = grid.getBoundingClientRect().width;

		items.forEach((item) => {
			const columnIndex = columnHeights.indexOf(Math.min(...columnHeights));
			const columnWidth = columns[columnIndex] || gridWidth;
			const inlineOffset = columns
				.slice(0, columnIndex)
				.reduce((total, width) => total + width + columnGap, 0);
			const x = isRtl
				? Math.max(0, gridWidth - inlineOffset - columnWidth)
				: inlineOffset;
			const y = columnHeights[columnIndex];

			item.style.setProperty("--masonry-column-width", `${columnWidth}px`);
			item.style.setProperty("--masonry-x", `${x}px`);
			item.style.setProperty("--masonry-y", `${y}px`);

			columnHeights[columnIndex] +=
				item.getBoundingClientRect().height + rowGap;
		});

		grid.style.height = `${Math.max(0, Math.max(...columnHeights) - rowGap)}px`;
	}

	function resetItem(item) {
		item.style.removeProperty("--masonry-column-width");
		item.style.removeProperty("--masonry-x");
		item.style.removeProperty("--masonry-y");
	}

	function destroyGrid(grid) {
		const state = states.get(grid);

		if (state?.frame) {
			window.cancelAnimationFrame(state.frame);
		}

		state?.resizeObserver?.disconnect();
		grid.classList.remove("is-js-masonry", "is-native-masonry");
		grid.style.removeProperty("height");
		getItems(grid).forEach(resetItem);
		states.delete(grid);
		grids.delete(grid);
	}

	function scheduleLayout(grid) {
		const state = states.get(grid);

		if (!state || state.native || state.frame) {
			return;
		}

		state.frame = window.requestAnimationFrame(() => {
			state.frame = null;
			layoutGrid(grid);
		});
	}

	function observeImage(grid, image, state) {
		if (state.images.has(image)) {
			return;
		}

		state.images.add(image);

		if (!image.complete) {
			image.addEventListener("load", () => scheduleLayout(grid), {
				once: true,
			});
		}
	}

	function observeItems(grid) {
		const state = states.get(grid);

		if (!state?.items) {
			return;
		}

		getItems(grid).forEach((item) => {
			if (!state.items.has(item)) {
				state.items.add(item);
				state.resizeObserver?.observe(item);
			}

			item.querySelectorAll("img").forEach((image) => {
				observeImage(grid, image, state);
			});
		});

		scheduleLayout(grid);
	}

	function initGrid(grid) {
		if (states.has(grid)) {
			observeItems(grid);
			return;
		}

		if (!shouldForceFallback() && getNativeMasonrySupported()) {
			grids.add(grid);
			grid.classList.add("is-native-masonry");
			states.set(grid, { frame: null, native: true });
			return;
		}

		const state = {
			frame: null,
			images: new WeakSet(),
			items: new WeakSet(),
			native: false,
			resizeObserver: null,
		};

		grids.add(grid);
		states.set(grid, state);
		grid.classList.add("is-js-masonry");

		if ("ResizeObserver" in window) {
			state.resizeObserver = new ResizeObserver(() => scheduleLayout(grid));
			state.resizeObserver.observe(grid);
		}

		observeItems(grid);
	}

	function initMasonry() {
		document.querySelectorAll(gridSelector).forEach(initGrid);
	}

	function scheduleAll() {
		if (allFrame) {
			return;
		}

		allFrame = window.requestAnimationFrame(() => {
			allFrame = null;
			initMasonry();
			grids.forEach((grid) => {
				if (!grid.isConnected || !grid.matches(gridSelector)) {
					destroyGrid(grid);
					return;
				}

				observeItems(grid);
				scheduleLayout(grid);
			});
		});
	}

	function nodeTouchesMasonry(node) {
		if (node.nodeType !== Node.ELEMENT_NODE) {
			return false;
		}

		return (
			grids.has(node) ||
			node.matches(gridSelector) ||
			node.closest(gridSelector) ||
			node.querySelector(gridSelector)
		);
	}

	function nodeIsStyleUpdate(node) {
		if (node.nodeType === Node.TEXT_NODE) {
			return node.parentElement?.tagName === "STYLE";
		}

		if (node.nodeType !== Node.ELEMENT_NODE) {
			return false;
		}

		return node.tagName === "STYLE" || node.querySelector("style");
	}

	function mutationsTouchMasonry(mutations) {
		return mutations.some((mutation) => {
			if (nodeIsStyleUpdate(mutation.target)) {
				return true;
			}

			if (mutation.type === "attributes") {
				return nodeTouchesMasonry(mutation.target);
			}

			return Array.from(mutation.addedNodes)
				.concat(Array.from(mutation.removedNodes))
				.some((node) => nodeTouchesMasonry(node) || nodeIsStyleUpdate(node));
		});
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initMasonry);
	} else {
		initMasonry();
	}

	window.addEventListener("resize", scheduleAll);

	if (document.fonts?.ready) {
		document.fonts.ready.then(scheduleAll);
	}

	if ("MutationObserver" in window) {
		const observer = new MutationObserver((mutations) => {
			if (mutationsTouchMasonry(mutations)) {
				scheduleAll();
			}
		});
		observer.observe(document.documentElement, {
			attributeFilter: ["class", "style"],
			attributes: true,
			characterData: true,
			childList: true,
			subtree: true,
		});
	}
})();
