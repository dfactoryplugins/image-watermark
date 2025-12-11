(() => {
	'use strict';

	if (typeof window.iwArgsClassic === 'undefined') {
		return;
	}

	const args = window.iwArgsClassic || {};
	const container = document.querySelector('.iw-classic-actions');

	if (!container) {
		return;
	}


	const status = container.querySelector('.iw-classic-status');
	const applyBtn = container.querySelector('.iw-classic-apply');
	const removeBtn = container.querySelector('.iw-classic-remove');
	let running = false;

	const setStatus = (message, type) => {
		if (!status) {
			return;
		}

		status.textContent = message || '';
		status.className = 'iw-classic-status';

		if (type) {
			status.classList.add(type);
		}
	};

	const disableButtons = disabled => {
		if (applyBtn) {
			applyBtn.disabled = disabled;
		}

		if (removeBtn) {
			removeBtn.disabled = disabled;
		}
	};

	const encodeForm = data => Object.keys(data).map(key => `${encodeURIComponent(key)}=${encodeURIComponent(data[key])}`).join('&');

	const refreshPreview = () => {
		const selectors = [
			'#attachment-preview img',
			'.attachment-info .thumbnail img',
			'.attachment-media-view img',
			'.attachment-info img',
			'.wp_attachment_holder img'
		];

		selectors.forEach(sel => {
			const img = document.querySelector(sel);
			if (img && img.src) {
				const bust = Date.now();
				const sep = img.src.indexOf('?') === -1 ? '?' : '&';
				img.src = `${img.src}${sep}t=${bust}`;
			}
		});
	};

	const sendAction = action => {
		if (running) {
			return;
		}

		if (!args.attachmentId) {
			return;
		}

		running = true;
		disableButtons(true);
		setStatus(args.strings.running || '', 'info');

		const payload = encodeForm({
			action: 'iw_watermark_bulk_action',
			'iw-action': action,
			attachment_id: args.attachmentId,
			_iw_nonce: args.nonce
		});

		const onDone = () => {
			running = false;
			disableButtons(false);
		};

		if (window.fetch) {
			window.fetch(args.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: payload
			}).then(response => response.json())
				.then(json => {
					if (json && json.success) {
						const msgKey = action === 'applywatermark' ? 'applied' : 'removed';
						setStatus(args.strings[msgKey], 'success');
						refreshPreview();
					} else {
						setStatus((json && json.data) || args.strings.error, 'error');
					}
				})
				.catch(() => {
					setStatus(args.strings.error, 'error');
				})
				.finally(onDone);
		} else {
			const xhr = new XMLHttpRequest();
			xhr.open('POST', args.ajaxUrl, true);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
			xhr.onreadystatechange = function onReady() {
				if (xhr.readyState !== 4) {
					return;
				}

				if (xhr.status >= 200 && xhr.status < 300) {
					try {
						const json = JSON.parse(xhr.responseText);
						if (json && json.success) {
							const msgKey = action === 'applywatermark' ? 'applied' : 'removed';
							setStatus(args.strings[msgKey], 'success');
							refreshPreview();
						} else {
							setStatus((json && json.data) || args.strings.error, 'error');
						}
					} catch (e) {
						setStatus(args.strings.error, 'error');
					}
				} else {
					setStatus(args.strings.error, 'error');
				}

				onDone();
			};
			xhr.send(payload);
		}
	};

	if (applyBtn) {
		applyBtn.addEventListener('click', event => {
			event.preventDefault();
			sendAction('applywatermark');
		});
	}

	if (removeBtn) {
		removeBtn.addEventListener('click', event => {
			event.preventDefault();
			sendAction('removewatermark');
		});
	}
})();
