(() => {
	'use strict';

	const args = window.iwArgsImageActions || {};
	const allowedMimes = Array.isArray(args.allowed_mimes) && args.allowed_mimes.length ? args.allowed_mimes : null;

	const qs = (selector, context = document) => (context || document).querySelector(selector);
	const qsa = (selector, context = document) => Array.prototype.slice.call((context || document).querySelectorAll(selector));
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
	const isVisible = element => !!(element && (element.offsetWidth || element.offsetHeight || element.getClientRects().length));
	const clearNotices = () => qsa('.iw-notice').forEach(node => node.remove());
	const removeOverlays = () => qsa('.iw-overlay').forEach(node => node.remove());
	const encodeForm = data => Object.keys(data).map(key => `${encodeURIComponent(key)}=${encodeURIComponent(data[key])}`).join('&');
	const requestJson = (url, body) => new Promise((resolve, reject) => {
		if (window.fetch) {
			window.fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body
			}).then(response => response.json()).then(resolve).catch(reject);
			return;
		}

		const xhr = new XMLHttpRequest();
		xhr.open('POST', url, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
		xhr.onreadystatechange = function onReady() {
			if (xhr.readyState !== 4) {
				return;
			}

			if (xhr.status >= 200 && xhr.status < 300) {
				try {
					resolve(JSON.parse(xhr.responseText));
				} catch (error) {
					reject(error);
				}
			} else {
				reject(new Error('Request failed'));
			}
		};
		xhr.send(body);
	});

	const api = {
		running: false,
		actionLocation: '',
		action: '',
		response: '',
		selected: [],
		successCount: 0,
		skippedCount: 0,
		gridButtonsBound: false,
		gridFrame: null,
		gridButtons: [],
		gridDomInterval: null,
		updateAttachmentInfoActions: null,

		isGridSelectModeActive() {
			const toggle = qs('.select-mode-toggle-button');

			if (toggle) {
				const pressed = toggle.getAttribute('aria-pressed') || toggle.getAttribute('aria-checked');

				if (pressed === 'true') {
					return true;
				}

				if (toggle.classList && (toggle.classList.contains('active') || toggle.classList.contains('is-pressed'))) {
					return true;
				}
			}

			const browser = qs('.attachments-browser');

			if (browser && (browser.classList.contains('mode-select') || browser.classList.contains('is-attachment-select-mode') || browser.classList.contains('select-mode'))) {
				return true;
			}

			const frame = qs('.media-frame');

			if (frame && (frame.classList.contains('mode-select') || frame.classList.contains('select-mode'))) {
				return true;
			}

			// Fallback: if any grid items are selected, treat select mode as active.
			if (qsa('.attachments-browser .attachments .attachment.selected').length) {
				return true;
			}

			return false;
		},

		init() {
			// List mode bulk actions.
			document.addEventListener('click', event => {
				const trigger = closest(event.target, '.bulkactions input#doaction, .bulkactions input#doaction2');

				if (!trigger) {
					return;
				}

				const parentBulk = closest(trigger, '.bulkactions');
				const select = parentBulk ? parentBulk.querySelector('select') : null;
				const action = select ? select.value : '';

				if (!action || (!args.backup_image && action === 'removewatermark')) {
					return;
				}

				if (action === 'applywatermark' || action === 'removewatermark') {
					event.preventDefault();

					if (api.running) {
						api.notice('iw-notice error', args.__running, false);
						return;
					}

					api.running = true;
					api.action = action;
					api.actionLocation = 'upload-list';
					api.selected = qsa('.wp-list-table .check-column input[type="checkbox"]:checked').map(cb => cb.value);

					clearNotices();
					api.postLoop();
				}
			});

			// Notice dismiss.
			document.addEventListener('click', event => {
				const dismiss = closest(event.target, '.iw-notice.is-dismissible .notice-dismiss');

				if (dismiss) {
					const notice = closest(dismiss, '.iw-notice');
					if (notice) {
						notice.remove();
					}
				}
			});

			this.initGridMode();
		},

		initGridMode() {
			if (this.gridButtonsBound || typeof wp === 'undefined' || !wp.media || typeof window.iwArgsMedia === 'undefined') {
				return;
			}

			const bindFrame = frame => {
				this.gridButtonsBound = true;
				this.gridFrame = frame;

				frame.on('ready', this.renderGridButtons);
				frame.on('select:activate', this.renderGridButtons);
				frame.on('select:deactivate', this.hideGridButtons);
				frame.on('selection:toggle selection:action:done library:selection:add', this.updateGridButtonsState);
				frame.on('attachments:selected', this.updateAttachmentInfoActions);
				frame.on('selection:change', this.updateAttachmentInfoActions);
				frame.on('toolbar:render:details', this.updateAttachmentInfoActions);
				frame.on('content:render', this.updateAttachmentInfoActions);

				this.renderGridButtons();
				this.updateAttachmentInfoActions();
			};

			const attemptBind = () => {
				const frame = wp.media && (wp.media.frame || (wp.media.frames && (wp.media.frames.browse || wp.media.frames.manage)));

				if (frame && typeof frame.on === 'function') {
					bindFrame(frame);
					return true;
				}

				return false;
			};

			if (!attemptBind()) {
				const interval = setInterval(() => {
					if (attemptBind()) {
						clearInterval(interval);
					}
				}, 300);
			}

			// Add observer for media modal content
			const observer = new MutationObserver(mutations => {
				mutations.forEach(mutation => {
					mutation.addedNodes.forEach(node => {
						if (node.nodeType === Node.ELEMENT_NODE) {
							if (node.matches('.attachment-info')) {
								api.updateAttachmentInfoActions();
							} else if (node.querySelector('.attachment-info')) {
								api.updateAttachmentInfoActions();
							}
						}
					});
				});
			});
			observer.observe(document.body, { childList: true, subtree: true });

			document.addEventListener('click', event => {
				if (closest(event.target, '.select-mode-toggle-button')) {
					this.renderGridButtons();

					if (this.gridDomInterval) {
						clearInterval(this.gridDomInterval);
					}

					this.gridDomInterval = setInterval(() => {
						this.renderGridButtons();
					}, 400);

					setTimeout(() => {
						if (this.gridDomInterval) {
							clearInterval(this.gridDomInterval);
							this.gridDomInterval = null;
						}
					}, 3000);
				}
			});

			document.addEventListener('click', event => {
				if (closest(event.target, '.attachments-browser .attachment, .attachments-browser .attachment .check')) {
					setTimeout(this.updateGridButtonsState, 50);
				}
			});
		},

		ensureGridButtonsDom() {
			const findToolbarContainer = () => {
				const selectors = [
					'.media-frame.mode-grid .media-frame-toolbar .media-toolbar',
					'.media-frame.mode-grid .media-toolbar',
					'.media-frame .media-frame-toolbar .media-toolbar',
					'.attachments-browser .media-toolbar',
					'.attachments-browser .attachments-filters'
				];

				for (let i = 0; i < selectors.length; i++) {
					const candidates = qsa(selectors[i]);
					for (let j = 0; j < candidates.length; j++) {
						if (isVisible(candidates[j])) {
							return candidates[j];
						}
					}
				}

				return null;
			};

			const toolbar = findToolbarContainer();

			if (!toolbar) {
				return false;
			}

			const primary = (() => {
				const primaries = qsa('.media-toolbar-primary', toolbar);
				for (let i = 0; i < primaries.length; i++) {
					if (isVisible(primaries[i])) {
						return primaries[i];
					}
				}

				const secondary = qsa('.media-toolbar-secondary', toolbar);
				for (let j = 0; j < secondary.length; j++) {
					if (isVisible(secondary[j])) {
						return secondary[j];
					}
				}

				return toolbar;
			})();

			if (!primary) {
				return false;
			}

			primary.style.overflow = 'visible';
			qsa('.iw-grid-watermark-apply, .iw-grid-watermark-remove', primary).forEach(button => button.remove());

			const makeButton = (className, label, action) => {
				const button = document.createElement('button');
				button.type = 'button';
				button.className = `button media-button ${className}`;
				button.textContent = label;
				button.addEventListener('click', () => this.startGridAction(action));
				return button;
			};

			const selectToggle = (() => {
				const toggles = qsa('.select-mode-toggle-button', primary);
				for (let i = 0; i < toggles.length; i++) {
					if (isVisible(toggles[i])) {
						return toggles[i];
					}
				}
				return null;
			})();

			const applyButton = makeButton('iw-grid-watermark-apply', window.iwArgsMedia.applyWatermark, 'applywatermark');
			const anchor = selectToggle || primary.firstChild || null;
			primary.insertBefore(applyButton, anchor);

			const buttons = [applyButton];

			if (args.backup_image) {
				const removeButton = makeButton('iw-grid-watermark-remove', window.iwArgsMedia.removeWatermark, 'removewatermark');
				primary.insertBefore(removeButton, anchor);
				buttons.push(removeButton);
			}

			this.gridButtons = buttons;
			this.hideGridButtons();
			return true;
		},

		renderGridButtons: () => {
			if (!api.ensureGridButtonsDom()) {
				return;
			}

			if (!api.isGridSelectModeActive()) {
				api.hideGridButtons();
				return;
			}

			api.gridButtons.forEach(button => {
				button.style.display = '';
			});

			api.updateGridButtonsState();
		},

		hideGridButtons: () => {
			api.gridButtons.forEach(button => {
				button.disabled = true;
				button.style.display = 'none';
			});
		},

		updateGridButtonsState: () => {
			if (!api.gridButtons.length) {
				return;
			}

			if (!api.isGridSelectModeActive()) {
				api.hideGridButtons();
				return;
			}

			const selectionIds = api.collectSelectionIds();
			const shouldDisable = api.running || selectionIds.length === 0;

			api.gridButtons.forEach(button => {
				if (!button) {
					return;
				}

				button.disabled = shouldDisable || (button.classList.contains('iw-grid-watermark-remove') && !args.backup_image);
			});
		},

		runAttachmentAction: (action, id, statusEl, link) => {
			if (api.running) {
				return;
			}

			const links = qsa('.iw-attachment-action');
			links.forEach(l => l.classList.add('disabled'));

			if (statusEl) {
				statusEl.textContent = args.__running || 'Working…';
				statusEl.className = 'iw-attachment-status info';
			}

			const payload = encodeForm({
				_iw_nonce: args._nonce,
				action: 'iw_watermark_bulk_action',
				'iw-action': action,
				attachment_id: id
			});

			const onComplete = () => {
				links.forEach(l => l.classList.remove('disabled'));
				api.running = false;
			};

			api.running = true;

			requestJson(window.ajaxurl || '/wp-admin/admin-ajax.php', payload)
				.then(json => {
					if (json && json.success) {
						const msg = action === 'applywatermark' ? (args.__applied_one || 'Watermark applied.') : (args.__removed_one || 'Watermark removed.');
						if (statusEl) {
							statusEl.textContent = msg;
							statusEl.className = 'iw-attachment-status success';
						}
						api.refreshAttachmentThumb(id);
					} else {
						if (statusEl) {
							statusEl.textContent = (json && json.data) || args.__running || 'Failed';
							statusEl.className = 'iw-attachment-status error';
						}
					}
				})
				.catch(() => {
					if (statusEl) {
						statusEl.textContent = args.__running || 'Failed';
						statusEl.className = 'iw-attachment-status error';
					}
				})
				.then(onComplete);
		},

		updateAttachmentInfoActions: () => {
			// Remove any existing watermark actions first
			qsa('.media-modal .iw-attachment-action, .media-modal .iw-separator').forEach(node => node.remove());

			const compatButtons = qs('.media-modal #image_watermark_buttons');
			const selection = api.gridFrame && api.gridFrame.state ? api.gridFrame.state().get('selection') : null;
			const model = selection && selection.first ? selection.first() : null;

			const getId = () => {
				if (model && typeof model.get === 'function') {
					return model.get('id');
				}
				if (compatButtons) {
					return compatButtons.getAttribute('data-id');
				}
				return null;
			};

			const isAllowed = () => {
				if (!model) {
					return true;
				}
				return api.isSupportedModel(model);
			};

			const tryInject = (attempts = 0) => {
				const actionsEl = qs('.media-modal .attachment-info .actions');
				const id = getId();

				if (!id || !isAllowed()) {
					return;
				}

				if (!actionsEl) {
					if (attempts < 20) {
						setTimeout(() => tryInject(attempts + 1), 100);
					}
					return;
				}

				api.injectAttachmentActions(actionsEl, id);
			};

			tryInject();
		},

		injectAttachmentActions: (actionsEl, id) => {
			if (!id) {
				return;
			}

			// Create separator
			const sep1 = document.createElement('span');
			sep1.className = 'links-separator iw-separator';
			sep1.textContent = ' | ';

			const makeLink = (className, label, action) => {
				const link = document.createElement('a');
				link.href = '#';
				link.className = className + ' iw-attachment-action';
				link.textContent = label;
				link.addEventListener('click', event => {
					event.preventDefault();
					api.actionLocation = 'media-modal';
					api.action = action;
					api.runAttachmentAction(action, id, null, link);
				});
				return link;
			};

			const applyLabel = args.apply_label || 'Apply watermark';
			const removeLabel = args.remove_label || 'Remove watermark';

			const applyLink = makeLink('iw-attachment-apply', applyLabel, 'applywatermark');

			// Find the delete button (or trash button)
			const deleteLink = actionsEl.querySelector('.delete-attachment, .trash-attachment');

			if (deleteLink) {
				// Insert separator and our links before the delete button
				const prev = deleteLink.previousElementSibling;
				if (!prev || !prev.classList.contains('links-separator')) {
					actionsEl.insertBefore(sep1, deleteLink);
				}
				actionsEl.insertBefore(applyLink, deleteLink);

				if (args.backup_image) {
					const sep = document.createElement('span');
					sep.className = 'links-separator iw-separator';
					sep.textContent = ' | ';
					actionsEl.insertBefore(sep, deleteLink);
					const removeLink = makeLink('iw-attachment-remove', removeLabel, 'removewatermark');
					actionsEl.insertBefore(removeLink, deleteLink);
					const sep2 = document.createElement('span');
					sep2.className = 'links-separator iw-separator';
					sep2.textContent = ' | ';
					actionsEl.insertBefore(sep2, deleteLink);
				}
			} else {
				// No delete button found, append at the end
				actionsEl.appendChild(sep1);
				actionsEl.appendChild(applyLink);

				if (args.backup_image) {
					const sep = document.createElement('span');
					sep.className = 'links-separator iw-separator';
					sep.textContent = ' | ';
					actionsEl.appendChild(sep);
					const removeLink = makeLink('iw-attachment-remove', removeLabel, 'removewatermark');
					actionsEl.appendChild(removeLink);
				}
			}
		},

		collectSelectionIds() {
			const ids = [];
			const frame = this.gridFrame;

			if (frame && typeof frame.state === 'function') {
				const selection = frame.state().get('selection');

				if (selection && selection.models) {
					selection.models.forEach(model => {
						if (api.isSupportedModel(model)) {
							const modelId = typeof model.get === 'function' ? model.get('id') : model.id;
							if (modelId) {
								ids.push(modelId);
							}
						}
					});
				}
			}

			if (!ids.length) {
				qsa('.attachments-browser .attachments .attachment.selected').forEach(item => {
					if (api.isSupportedDom(item)) {
						const itemId = item.getAttribute('data-id');

						if (itemId) {
							ids.push(itemId);
						}
					}
				});
			}

			return ids;
		},

		startGridAction(action) {
			const ids = this.collectSelectionIds();

			if (!ids.length) {
				this.notice('iw-notice error', args.__running, false);
				return;
			}

			if (!args.backup_image && action === 'removewatermark') {
				return;
			}

			if (!this.isGridSelectModeActive()) {
				return;
			}

			this.running = true;
			this.action = action;
			this.actionLocation = 'grid';
			this.selected = ids.slice(0);

			clearNotices();
			this.updateGridButtonsState();
			this.postLoop();
		},

		async postLoop() {
			if (!this.selected.length) {
				this.reset();
				return;
			}

			const id = this.selected[0];
			const numericId = Number(id);

			if (Number.isNaN(numericId)) {
				this.selected.shift();
				this.postLoop();
				return;
			}

			this.rowImageFeedback(numericId);

			if (this.actionLocation === 'upload-list') {
				this.scrollTo(`#post-${numericId}`, 'bottom');
			}

			const payload = encodeForm({
				_iw_nonce: args._nonce,
				action: 'iw_watermark_bulk_action',
				'iw-action': this.action,
				attachment_id: numericId
			});

			requestJson(window.ajaxurl || '/wp-admin/admin-ajax.php', payload)
				.then(json => {
					this.result(json, numericId);
				}, error => {
					this.notice('iw-notice error', (error && error.message) || 'Request failed', false);
					this.rowImageFeedback(numericId);
				})
				.then(() => {
					this.selected.shift();
					this.postLoop();

					const overlay = qs('.iw-overlay');

					if (overlay) {
						const parentValue = qs('#image_watermark_buttons .value');

						if (parentValue && (this.response === 'watermarked' || this.response === 'watermarkremoved')) {
							const icon = document.createElement('span');
							icon.className = 'dashicons dashicons-yes';
							icon.style.cssText = 'font-size:24px;float:none;min-width:28px;padding:0;margin:0;display:none;';
							parentValue.appendChild(icon);
							icon.style.display = '';
							setTimeout(() => {
								icon.remove();
							}, 1500);
						}

						overlay.remove();
					}
				});

		},

		result(response, id) {
			if (response && response.success === true) {
				let type = false;
				let message = '';
				let overwrite = true;

				this.response = response.data;

				switch (response.data) {
					case 'watermarked':
						type = 'iw-notice updated iw-watermarked';
						this.successCount += 1;
						message = this.successCount > 1 ? args.__applied_multi.replace('%s', this.successCount) : args.__applied_one;
						this.rowImageFeedback(id);
						this.reloadImage(id);
						this.refreshAttachmentCache(id);
						break;

					case 'watermarkremoved':
						type = 'iw-notice updated iw-watermarkremoved';
						this.successCount += 1;
						message = this.successCount > 1 ? args.__removed_multi.replace('%s', this.successCount) : args.__removed_one;
						this.rowImageFeedback(id);
						this.reloadImage(id);
						this.refreshAttachmentCache(id);
						break;

					case 'skipped':
						type = 'iw-notice error iw-skipped';
						this.skippedCount += 1;
						message = `${args.__skipped}: ${this.skippedCount}`;
						this.rowImageFeedback(id);
						break;

					default:
						type = 'iw-notice error iw-message';
						message = response.data;
						this.rowImageFeedback(id);
						overwrite = false;
						break;
				}

				if (type) {
					this.notice(type, message, overwrite);
				}
			} else {
				this.notice('iw-notice error', response ? response.data : args.__running, false);
				this.rowImageFeedback(id);
			}
		},

		rowImageFeedback(id) {
			let selector = '';
			let css = {};
			let innerCss = {};

			switch (this.actionLocation) {
				case 'upload-list': {
					selector = `.wp-list-table #post-${id} .media-icon`;
					const dims = qs(selector);
					const rect = dims ? dims.getBoundingClientRect() : { width: 0, height: 0 };
					css = {
						display: 'table',
						width: `${rect.width || 0}px`,
						height: `${rect.height || 0}px`,
						top: '0',
						left: '0',
						position: 'absolute',
						font: 'normal normal normal dashicons',
						background: 'rgba(255,255,255,0.75)',
						content: ''
					};
					innerCss = {
						verticalAlign: 'middle',
						textAlign: 'center',
						display: 'table-cell',
						width: '100%',
						height: '100%'
					};
					break;
				}

				case 'grid': {
					selector = `.attachments-browser .attachments [data-id="${id}"] .attachment-preview`;
					const dims = qs(selector);
					const rect = dims ? dims.getBoundingClientRect() : { width: 0, height: 0 };
					css = {
						display: 'table',
						width: `${rect.width || 0}px`,
						height: `${rect.height || 0}px`,
						top: '0',
						left: '0',
						position: 'absolute',
						font: 'normal normal normal dashicons',
						background: 'rgba(255,255,255,0.75)',
						content: ''
					};
					innerCss = {
						verticalAlign: 'middle',
						textAlign: 'center',
						display: 'table-cell',
						width: '100%',
						height: '100%'
					};
					break;
				}

				case 'edit': {
					selector = `.wp_attachment_holder #thumbnail-head-${id}`;
					const imgEl = qs(`${selector} img`);
					const rect = imgEl ? imgEl.getBoundingClientRect() : { width: 0, height: 0 };
					css = {
						display: 'table',
						width: `${rect.width || 0}px`,
						height: `${rect.height || 0}px`,
						top: '0',
						left: '0',
						position: 'absolute',
						font: 'normal normal normal dashicons',
						background: 'rgba(255,255,255,0.75)',
						content: ''
					};
					innerCss = {
						verticalAlign: 'middle',
						textAlign: 'center',
						display: 'table-cell',
						width: '100%',
						height: '100%'
					};
					break;
				}

				default:
					return;
			}

			const container = qs(selector);

			if (!container) {
				return;
			}

			if (getComputedStyle(container).position === 'static') {
				container.style.position = 'relative';
			}

			let overlay = container.querySelector('.iw-overlay');

			if (!overlay) {
				overlay = document.createElement('span');
				overlay.className = 'iw-overlay';
				const inner = document.createElement('span');
				inner.className = 'iw-overlay-inner';
				overlay.appendChild(inner);
				container.appendChild(overlay);
			}

			for (const key in css) {
				if (Object.prototype.hasOwnProperty.call(css, key)) {
					overlay.style[key] = css[key];
				}
			}
			const inner = overlay.querySelector('.iw-overlay-inner');
			for (const key in innerCss) {
				if (Object.prototype.hasOwnProperty.call(innerCss, key)) {
					inner.style[key] = innerCss[key];
				}
			}
			inner.innerHTML = '<span class="spinner is-active"></span>';

			if (this.actionLocation === 'media-modal') {
				const spinner = inner.querySelector('.spinner');
				if (spinner) {
					spinner.style.cssText = 'float:none;padding:0;margin:-4px 0 0 10px;';
				}
			}
		},

		notice(type, message, overwrite) {
			if (this.actionLocation === 'media-modal') {
				return;
			}

			const noticeClass = `${type} notice is-dismissible`;
			let prefix = this.actionLocation === 'upload-list' ? '.wrap > h1' : '#image_watermark_buttons';
			let targetSelector = null;

			if (overwrite === true) {
				switch (this.response) {
					case 'watermarked':
						targetSelector = '.iw-notice.iw-watermarked';
						break;
					case 'watermarkremoved':
						targetSelector = '.iw-notice.iw-watermarkremoved';
						break;
					case 'skipped':
						targetSelector = '.iw-notice.iw-skipped';
						break;
					default:
						break;
				}

				if (targetSelector) {
					const prefixWithSpace = prefix.charAt(prefix.length - 1) === ' ' ? prefix : `${prefix} `;
					const notice = qs(prefixWithSpace + targetSelector + ' > p');

					if (notice) {
						notice.innerHTML = message;
						return;
					}

					prefix = this.actionLocation === 'upload-list' ? '.wrap ' : '#image_watermark_buttons ';
				}
			}

			const anchor = qs(prefix);

			if (!anchor || !anchor.parentElement) {
				return;
			}

			const wrapper = document.createElement('div');
			wrapper.className = noticeClass;
			wrapper.innerHTML = `<p>${message}</p><button type="button" class="notice-dismiss"><span class="screen-reader-text">${args.__dismiss}</span></button>`;
			anchor.insertAdjacentElement('afterend', wrapper);
		},

		reset() {
			this.running = false;
			this.action = '';
			this.response = '';
			this.selected = [];
			this.successCount = 0;
			this.skippedCount = 0;

			setTimeout(removeOverlays, 100);
			this.updateGridButtonsState();
		},

		reloadImage(id) {
			const time = Date.now();
			const selectors = [];

			switch (this.actionLocation) {
				case 'upload-list':
					selectors.push(`.wp-list-table #post-${id} .image-icon img`);
					break;
				case 'grid':
					selectors.push(`.attachments-browser .attachments [data-id="${id}"] img`);
					selectors.push('.attachment-details[data-id="' + id + '"] img, .attachment[data-id="' + id + '"] img, .attachment-info .thumbnail img, .attachment-media-view img');
					break;
				case 'media-modal':
					selectors.push('.attachment-details[data-id="' + id + '"] img, .attachment[data-id="' + id + '"] img, .attachment-info .thumbnail img, .attachment-media-view img');
					break;
				case 'edit':
					selectors.push('.attachment-info .thumbnail img, .attachment-media-view img, .wp_attachment_holder img');
					break;
				default:
					break;
			}

			const uniqueSelectors = selectors.filter(Boolean);

			if (!uniqueSelectors.length) {
				return;
			}

			qsa(uniqueSelectors.join(',')).forEach(img => {
				img.removeAttribute('srcset');
				img.removeAttribute('sizes');
				const currentSrc = img.getAttribute('src') || '';
				img.setAttribute('src', this.replaceUrlParam(currentSrc, 't', time));
			});
		},

		isSupportedModel(model) {
			if (!model) {
				return false;
			}

			const type = typeof model.get === 'function' ? model.get('type') : model.type;
			const mime = (typeof model.get === 'function' ? model.get('mime') : model.mime) || (type && typeof model.get === 'function' && model.get('subtype') ? `${type}/${model.get('subtype')}` : '');

			if (type !== 'image' && (!mime || mime.indexOf('image/') !== 0)) {
				return false;
			}

			if (allowedMimes) {
				return allowedMimes.indexOf(mime) !== -1;
			}

			return true;
		},

		isSupportedDom(el) {
			if (!el) {
				return false;
			}

			const type = el.getAttribute('data-type') || '';
			const subtype = el.getAttribute('data-subtype') || '';
			const mime = type && subtype ? `${type}/${subtype}` : '';

			if (type !== 'image' && (!mime || mime.indexOf('image/') !== 0)) {
				return false;
			}

			if (allowedMimes) {
				return allowedMimes.indexOf(mime) !== -1;
			}

			return true;
		},

		refreshAttachmentCache(id) {
			if (typeof wp === 'undefined' || !wp.media || !wp.media.attachment) {
				return;
			}

			const attachment = wp.media.attachment(id);

			if (attachment) {
				attachment.fetch({ cache: false }).then(() => {
					api.cacheBustAttachmentSources(attachment);
				});
			}
		},

		cacheBustAttachmentSources(attachment) {
			if (!attachment || typeof attachment.get !== 'function') {
				return;
			}

			const time = Date.now();
			const changed = {};

			if (attachment.get('url')) {
				changed.url = this.replaceUrlParam(attachment.get('url'), 't', time);
			}

			if (attachment.get('sizes')) {
				const sizes = attachment.get('sizes');
				const newSizes = {};

				Object.keys(sizes).forEach(key => {
					const value = sizes[key];
					if (value && value.url) {
						const cloned = {};
						Object.keys(value).forEach(prop => {
							cloned[prop] = value[prop];
						});
						cloned.url = this.replaceUrlParam(value.url, 't', time);
						newSizes[key] = cloned;
					} else {
						newSizes[key] = value;
					}
				});

				changed.sizes = newSizes;
			}

			if (attachment.get('icon')) {
				changed.icon = this.replaceUrlParam(attachment.get('icon'), 't', time);
			}

			if (Object.keys(changed).length) {
				attachment.set(changed);
			}
		},

		replaceUrlParam(url, paramName, paramValue) {
			const pattern = new RegExp(`\\b(${paramName}=).*?(&|$)`);

			if (pattern.test(url)) {
				return url.replace(pattern, `$1${paramValue}$2`);
			}

			return `${url}${url.indexOf('?') > 0 ? '&' : '?'}${paramName}=${paramValue}`;
		},

		scrollTo(selector, targetPosition) {
			const element = qs(selector);

			if (!element) {
				return;
			}

			const rect = element.getBoundingClientRect();
			const elementTop = rect.top + window.pageYOffset;
			const viewTop = window.pageYOffset;
			const viewBottom = viewTop + window.innerHeight;
			let destination = elementTop;

			if (rect.top + window.pageYOffset < viewTop) {
				destination = elementTop;
			} else if (targetPosition === 'bottom') {
				if (rect.top + window.pageYOffset < viewBottom) {
					return;
				}
				destination = elementTop - window.innerHeight + rect.height;
			} else if (targetPosition === 'center') {
				if (elementTop < viewBottom && elementTop >= viewTop) {
					return;
				}
				destination = elementTop - window.innerHeight / 2 + rect.height / 2;
			}

			window.scrollTo(0, destination);
		},

		refreshAttachmentThumb(id) {
			const selectors = [
				'.attachment-info .thumbnail img',
				'.attachment-media-view img',
				'.attachment-details[data-id="' + id + '"] img',
				'.attachment[data-id="' + id + '"] img'
			];

			const time = Date.now();

			qsa(selectors.join(',')).forEach(img => {
				const src = img.getAttribute('src') || '';
				img.removeAttribute('srcset');
				img.removeAttribute('sizes');
				img.setAttribute('src', api.replaceUrlParam(src, 't', time));
			});
		}
	};

	window.watermarkImageActions = api;

	if (typeof args._nonce === 'undefined') {
		return;
	}

	document.addEventListener('DOMContentLoaded', () => api.init());
})();
