(() => {
	const gridSelector =
		".wp-block-post-template.is-style-masonry, .wp-block-post-template.is-style-masonry-collage";
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

	function isCollageGrid(grid) {
		return grid.classList.contains("is-style-masonry-collage");
	}

	function getLengthInPixels(length) {
		const probe = document.createElement("div");

		probe.style.cssText = `inline-size:${length};position:absolute;visibility:hidden;`;
		document.body.append(probe);

		const width = probe.getBoundingClientRect().width;

		probe.remove();

		return width;
	}

	function getConfiguredMinimumColumnWidth(grid) {
		return window
			.getComputedStyle(grid)
			.getPropertyValue("--masonry-min-column-width")
			.trim();
	}

	function getTemplateMinimumColumnWidth(template) {
		const minTrackMatch = template.match(/minmax\(min\(([^,]+),\s*100%\)/);

		return minTrackMatch ? minTrackMatch[1].trim() : "";
	}

	function getPixelColumns(template) {
		return Array.from(template.matchAll(/(?:^|\s)([0-9.]+)px(?:\s|$)/g))
			.map((match) => Number.parseFloat(match[1]))
			.filter((track) => Number.isFinite(track) && track > 0);
	}

	function getEqualColumns(count, gridWidth, columnGap) {
		const columnWidth = Math.max(
			0,
			(gridWidth - columnGap * (count - 1)) / count,
		);

		return Array.from({ length: count }, () => columnWidth);
	}

	function getExplicitRepeatCount(template) {
		const repeatMatch = template.match(/^repeat\(\s*(\d+)\s*,/);

		return repeatMatch ? Number.parseInt(repeatMatch[1], 10) : null;
	}

	function getColumnCountFromMinimumWidth(
		minimumColumnWidth,
		gridWidth,
		columnGap,
	) {
		const width = getLengthInPixels(minimumColumnWidth);
		const clampedMinimum = Math.min(
			Number.isFinite(width) && width > 0 ? width : gridWidth,
			gridWidth,
		);

		return Math.max(
			1,
			Math.floor((gridWidth + columnGap) / (clampedMinimum + columnGap)),
		);
	}

	function getColumnCount(grid, template, gridWidth, columnGap) {
		if (gridWidth <= 0) {
			return 1;
		}

		const configuredMinimumColumnWidth = getConfiguredMinimumColumnWidth(grid);

		if (configuredMinimumColumnWidth) {
			return getColumnCountFromMinimumWidth(
				configuredMinimumColumnWidth,
				gridWidth,
				columnGap,
			);
		}

		const pixelColumns = getPixelColumns(template);
		const pixelColumnsWidth =
			pixelColumns.reduce((total, width) => total + width, 0) +
			columnGap * Math.max(0, pixelColumns.length - 1);

		if (
			pixelColumns.length > 0 &&
			pixelColumnsWidth <= gridWidth + 1 &&
			pixelColumnsWidth >= gridWidth * 0.75
		) {
			return pixelColumns.length;
		}

		const repeatCount = getExplicitRepeatCount(template);

		if (repeatCount) {
			return repeatCount;
		}

		return getColumnCountFromMinimumWidth(
			getTemplateMinimumColumnWidth(template) || "23rem",
			gridWidth,
			columnGap,
		);
	}

	function getColumnMetrics(grid) {
		const styles = window.getComputedStyle(grid);
		const columnGap = Number.parseFloat(styles.columnGap) || 0;
		const rowGap = Number.parseFloat(styles.rowGap) || 0;
		const gridWidth = grid.getBoundingClientRect().width;
		const gridTemplateColumns =
			styles.gridTemplateColumns === "none"
				? grid.style.gridTemplateColumns
				: styles.gridTemplateColumns;
		const columnCount = getColumnCount(
			grid,
			gridTemplateColumns,
			gridWidth,
			columnGap,
		);

		return {
			columnGap,
			columns: getEqualColumns(columnCount, gridWidth, columnGap),
			gridWidth,
			isRtl: styles.direction === "rtl",
			rowGap,
		};
	}

	function getItemSpan(item, columnCount, itemIndex) {
		if (columnCount <= 2 && itemIndex > 0) {
			return 1;
		}

		const span = Number.parseInt(
			window.getComputedStyle(item).getPropertyValue("--masonry-column-span"),
			10,
		);

		if (!Number.isFinite(span)) {
			return 1;
		}

		return Math.max(1, Math.min(span, columnCount));
	}

	function getPlacement(columnHeights, span) {
		let index = 0;
		let height = Infinity;

		for (let start = 0; start <= columnHeights.length - span; start++) {
			const rangeHeight = Math.max(...columnHeights.slice(start, start + span));

			if (rangeHeight < height) {
				index = start;
				height = rangeHeight;
			}
		}

		return { height, index };
	}

	function layoutGrid(grid) {
		if (!grid.isConnected || !grid.matches(gridSelector)) {
			destroyGrid(grid);
			return;
		}

		const items = getItems(grid);
		const { columnGap, columns, gridWidth, isRtl, rowGap } =
			getColumnMetrics(grid);
		const columnHeights = columns.map(() => 0);

		items.forEach((item, itemIndex) => {
			const span = getItemSpan(item, columns.length, itemIndex);
			const placement = getPlacement(columnHeights, span);
			const columnIndex = placement.index;
			const columnWidth = columns
				.slice(columnIndex, columnIndex + span)
				.reduce((total, width) => total + width, 0);
			const itemWidth = Math.min(
				columnWidth + columnGap * (span - 1),
				gridWidth,
			);
			const inlineOffset = columns
				.slice(0, columnIndex)
				.reduce((total, width) => total + width + columnGap, 0);
			const maxX = Math.max(0, gridWidth - itemWidth);
			const x = isRtl
				? Math.min(Math.max(0, gridWidth - inlineOffset - itemWidth), maxX)
				: Math.min(inlineOffset, maxX);
			const y = placement.height;

			item.style.setProperty("--masonry-column-width", `${itemWidth}px`);
			item.style.setProperty("--masonry-x", `${x}px`);
			item.style.setProperty("--masonry-y", `${y}px`);

			const itemBottom = y + item.getBoundingClientRect().height + rowGap;

			for (let i = columnIndex; i < columnIndex + span; i++) {
				columnHeights[i] = itemBottom;
			}
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

	function observeItems(grid, shouldSchedule = true) {
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

		if (shouldSchedule) {
			scheduleLayout(grid);
		}
	}

	function initGrid(grid) {
		const state = states.get(grid);
		const shouldBeNative =
			!shouldForceFallback() &&
			!isCollageGrid(grid) &&
			getNativeMasonrySupported();

		if (state) {
			if (state.native !== shouldBeNative) {
				destroyGrid(grid);
			} else {
				observeItems(grid);
				return;
			}
		}

		if (states.has(grid)) {
			observeItems(grid);
			return;
		}

		if (shouldBeNative) {
			grids.add(grid);
			grid.classList.add("is-native-masonry");
			states.set(grid, { frame: null, native: true });
			return;
		}

		const nextState = {
			frame: null,
			images: new WeakSet(),
			items: new WeakSet(),
			native: false,
			resizeObserver: null,
		};

		grids.add(grid);
		states.set(grid, nextState);
		grid.classList.add("is-js-masonry");

		if ("ResizeObserver" in window) {
			nextState.resizeObserver = new ResizeObserver(() => scheduleLayout(grid));
			nextState.resizeObserver.observe(grid);
		}

		observeItems(grid, false);
		layoutGrid(grid);
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
