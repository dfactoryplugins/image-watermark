(() => {
	const args = window.iwArgsMedia || {};

	const addOption = (value, label) => {
		const option = document.createElement('option');
		option.value = value;
		option.textContent = label;

		document.querySelectorAll('select[name="action"], select[name="action2"]').forEach(select => {
			select.appendChild(option.cloneNode(true));
		});
	};

	document.addEventListener('DOMContentLoaded', () => {
		if (!args.applyWatermark) {
			return;
		}

		addOption('applywatermark', args.applyWatermark);

		if (args.backupImage) {
			addOption('removewatermark', args.removeWatermark);
		}
	});
})();
