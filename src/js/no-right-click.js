; ( function () {
	'use strict';

	const args = window.iwArgsNoRightClick || {};
	const shouldBlockContext = args.rightclick === 'Y';
	const shouldBlockDrag = args.draganddrop === 'Y';

	if ( ! shouldBlockContext && ! shouldBlockDrag ) {
		return;
	}

	const getTarget = ( event ) => event.target || event.srcElement || null;
	const isImageElement = ( element ) => element && element.nodeType === 1 && element.tagName === 'IMG';
	const hasBackgroundImage = ( element ) => {
		if ( ! element || element.nodeType !== 1 ) {
			return false;
		}
		const backgroundImage = window.getComputedStyle( element ).backgroundImage;
		return typeof backgroundImage === 'string' && backgroundImage !== 'none';
	};
	const isAnchorWithImage = ( element ) =>
		element && element.nodeType === 1 && element.tagName === 'A' && element.querySelector( 'img' );
	const shouldProtectElement = ( element ) =>
		isImageElement( element ) || hasBackgroundImage( element ) || isAnchorWithImage( element );

	const preventInteraction = ( event ) => {
		const target = getTarget( event );
		if ( shouldProtectElement( target ) ) {
			event.preventDefault();
		}
	};

	const preventDrag = ( event ) => {
		const target = getTarget( event );
		if ( shouldProtectElement( target ) ) {
			event.preventDefault();
		}
	};

	if ( shouldBlockContext ) {
		document.addEventListener( 'contextmenu', preventInteraction, true );
		document.addEventListener( 'copy', preventInteraction, true );
	}

	if ( shouldBlockDrag ) {
		document.addEventListener( 'dragstart', preventDrag, true );
	}
} )();
