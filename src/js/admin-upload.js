(() => {
	const args = window.iwArgsUpload || {};
	const allowedMimes = ['image/gif', 'image/jpeg', 'image/png', 'image/webp'];
	const state = { frame: null };
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

	const setPreviewImage = src => {
		const previewImg = document.querySelector('div#previewImg_imageDiv img#previewImg_image');

		if (!previewImg) {
			return;
		}

		previewImg.src = src;
		previewImg.style.display = src ? '' : 'none';
	};

	const setImageInfo = message => {
		const infoEl = document.querySelector('p#previewImageInfo');

		if (infoEl) {
			infoEl.innerHTML = message;
		}
	};

	const toggleDisableOffButton = disabled => {
		const offButton = document.querySelector('#iw_turn_off_image_button');

		if (offButton) {
			offButton.disabled = disabled;
		}
	};

	const handleSelect = () => {
		if (!state.frame) {
			return;
		}

		const selection = state.frame.state().get('selection');
		const attachment = selection && selection.first ? selection.first() : null;

		if (!attachment || !attachment.attributes) {
			return;
		}

		const { mime, url, id } = attachment.attributes;

		if (allowedMimes.indexOf(mime) !== -1) {
			const uploadInput = document.querySelector('#iw_upload_image');

			if (uploadInput) {
				uploadInput.value = id;
			}

			setPreviewImage(url);
			toggleDisableOffButton(false);

			const img = new Image();
			img.src = url;
			img.onload = function onLoad() {
				setImageInfo(`${args.originalSize}: ${this.width} ${args.px} / ${this.height} ${args.px}`);
			};
		} else {
			toggleDisableOffButton(true);
			const uploadInput = document.querySelector('#iw_upload_image');

			if (uploadInput) {
				uploadInput.value = 0;
			}

			setPreviewImage('');
			setImageInfo(`<strong>${args.notAllowedImg}</strong>`);
		}
	};

	const getFrame = () => {
		if (state.frame) {
			return state.frame;
		}

		state.frame = wp.media({
			title: args.title,
			frame: args.frame,
			button: args.button,
			multiple: args.multiple,
			library: { type: allowedMimes }
		});

		state.frame.on('select', handleSelect);

		return state.frame;
	};

	document.addEventListener('DOMContentLoaded', () => {
		const container = document.querySelector('#wpbody') || document;

		container.addEventListener('click', event => {
			const uploadButton = closest(event.target, 'input#iw_upload_image_button');

			if (uploadButton) {
				event.preventDefault();
				getFrame().open();
			}
		});

		document.addEventListener('click', event => {
			const offButton = closest(event.target, '#iw_turn_off_image_button');

			if (offButton) {
				event.preventDefault();
				offButton.disabled = true;
				const uploadInput = document.querySelector('#iw_upload_image');

				if (uploadInput) {
					uploadInput.value = 0;
				}

				setPreviewImage('');
				setImageInfo(args.noSelectedImg);
			}
		});
	});
})();
