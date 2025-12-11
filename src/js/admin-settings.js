(() => {
	const args = window.iwArgsSettings || {};
	const matches = (element, selector) => {
		if (!element) {
			return false;
		}

		const matcher = element.matches || element.msMatchesSelector || element.webkitMatchesSelector;
		return matcher ? matcher.call(element, selector) : false;
	};
	const closest = (element, selector) => {
		let node = element;

		while (node) {
			if (matches(node, selector)) {
				return node;
			}

			node = node.parentElement;
		}

		return null;
	};

	const updateRangeValue = range => {
		if (!range) {
			return;
		}

		const display = document.querySelector(`.iw-range-value[data-for="${range.id}"]`);

		if (display) {
			display.textContent = range.value;
		}
	};

	const toggleCptSelect = () => {
		const selected = document.querySelector('#cpt-specific input[type=radio]:checked');
		const container = document.querySelector('#cpt-select');

		if (!container || !selected) {
			return;
		}

		if (selected.value === 'specific') {
			container.style.display = '';
		} else {
			container.style.display = 'none';
		}
	};

	document.addEventListener('DOMContentLoaded', () => {
		document.addEventListener('change', event => {
			if (event.target && (matches(event.target, '#df_option_everywhere') || matches(event.target, '#df_option_cpt'))) {
				toggleCptSelect();
			}
		});

		document.addEventListener('click', event => {
			const resetButton = closest(event.target, '#reset_image_watermark_options');

			if (resetButton) {
				if (!window.confirm(args.resetToDefaults)) {
					event.preventDefault();
				}
			}
		});

		document.addEventListener('input', event => {
			if (matches(event.target, '.iw-range')) {
				updateRangeValue(event.target);
			}
		});

		Array.prototype.slice.call(document.querySelectorAll('.iw-range')).forEach(updateRangeValue);

		toggleCptSelect();
	});
})();
