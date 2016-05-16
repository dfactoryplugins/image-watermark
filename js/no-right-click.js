/*
 This javascript is used by the no-right-click-images plugin for wordpress.
 Version 2.2
 Please give credit as no-right-click-images.js by Keith P. Graham
 http://www.blogseye.com
 */

var df_nrc_targImg = null;
var df_nrc_targSrc = null;
var df_nrc_inContext = false;
var df_nrc_notimage = new Image();
var df_nrc_limit = 0;
var df_nrc_extra = norightclick_args.rightclick;
var df_nrc_drag = norightclick_args.draganddrop;

function df_nrc_dragdropAll( event ) {
	try {
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		if ( targ.tagName.toUpperCase() == "A" ) {
			// is this IE and are we dragging a link to the image?
			var hr = targ.href;
			hr = hr.toUpperCase();
			if ( hr.indexOf( '.JPG' ) || hr.indexOf( '.PNG' ) || hr.indexOf( '.GIF' ) ) {
				ev.returnValue = false;
				if ( ev.preventDefault ) {
					ev.preventDefault();
				}
				df_nrc_inContext = false;
				return false;
			}
		}
		if ( targ.tagName.toUpperCase() != "IMG" )
			return true;
		ev.returnValue = false;
		if ( ev.preventDefault ) {
			ev.preventDefault();
		}
		df_nrc_inContext = false;
		return false;
	} catch ( er ) {
		// alert(er);
	}
	return true;
}

function df_nrc_dragdrop( event ) {
	// I am beginning to doubt if this event ever fires
	try {
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		ev.returnValue = false;
		if ( ev.preventDefault ) {
			ev.preventDefault();
		}
		ev.returnValue = false;
		df_nrc_inContext = false;
		return false;
	} catch ( er ) {
		// alert(er);
	}
	return true;
}

function df_nrc_context( event ) {
	try {
		df_nrc_inContext = true;
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		df_nrc_replace( targ );
		ev.returnValue = false;
		if ( ev.preventDefault ) {
			ev.preventDefault();
		}
		ev.returnValue = false;
		df_nrc_targImg = targ;
	} catch ( er ) {
		// alert(er);
	}
	return false;
}

function df_nrc_contextAll( event ) {
	try {
		if ( df_nrc_targImg == null ) {
			return true;
		}
		df_nrc_inContext = true;
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		if ( targ.tagName.toUpperCase() == "IMG" ) {
			ev.returnValue = false;
			if ( ev.preventDefault ) {
				ev.preventDefault();
			}
			ev.returnValue = false;
			df_nrc_replace( targ );
			return false;
		}
		return true;
	} catch ( er ) {
		// alert(er);
	}
	return false;
}

function kpg_nrc1_mousedown( event ) {
	try {
		df_nrc_inContext = false;
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		if ( ev.button == 2 ) {
			df_nrc_replace( targ );
			return false;
		}
		df_nrc_targImg = targ;
		if ( df_nrc_drag == 'Y' ) {
			if ( ev.preventDefault ) {
				ev.preventDefault();
			}
		}
		return true;
	} catch ( er ) {
		// alert(er);
	}
	return true;
}

function kpg_nrc1_mousedownAll( event ) {
	try {
		df_nrc_inContext = false;
		var ev = event || window.event;
		var targ = ev.srcElement || ev.target;
		if ( targ.style.backgroundImage != '' && ev.button == 2 ) {
			targ.oncontextmenu = function ( event ) {
				return false;
			} // iffy - might not work
		}
		if ( targ.tagName.toUpperCase() == "IMG" ) {
			if ( ev.button == 2 ) {
				df_nrc_replace( targ );
				return false;
			}
			if ( df_nrc_drag == 'Y' ) {
				if ( ev.preventDefault ) {
					ev.preventDefault();
				}
			}
			df_nrc_targImg = targ;
		}
		return true;
	} catch ( er ) {
		// alert(er);
	}
	return true;
}

function df_nrc_replace( targ ) {
	return false;
	if ( df_nrc_targImg != null && df_nrc_targImg.src == df_nrc_notimage.src ) {
		// restore the old image before hiding this one
		df_nrc_targImg.src = df_nrc_targSrc;
		df_nrc_targImg = null;
		df_nrc_targSrc = null;
	}
	df_nrc_targImg = targ;
	if ( df_nrc_extra != 'Y' )
		return;
	var w = targ.width + '';
	var h = targ.height + '';
	if ( w.indexOf( 'px' ) <= 0 )
		w = w + 'px';
	if ( h.indexOf( 'px' ) <= 0 )
		h = h + 'px';
	df_nrc_targSrc = targ.src;
	targ.src = df_nrc_notimage.src;
	targ.style.width = w;
	targ.style.height = h;
	df_nrc_limit = 0;
	var t = setTimeout( "df_nrc_restore()", 500 );
	return false;
}

function df_nrc_restore() {
	if ( df_nrc_inContext ) {
		if ( df_nrc_limit <= 20 ) {
			df_nrc_limit++;
			var t = setTimeout( "df_nrc_restore()", 500 );
			return;
		}
	}
	df_nrc_limit = 0;
	if ( df_nrc_targImg == null )
		return;
	if ( df_nrc_targSrc == null )
		return;
	df_nrc_targImg.src = df_nrc_targSrc;
	df_nrc_targImg = null;
	df_nrc_targSrc = null;
	return;
}

// set the image onclick event
// need to check for dblclick to see if there is a right double click in IE
function df_nrc_action( event ) {
	try {
		document.onmousedown = function ( event ) {
			return kpg_nrc1_mousedownAll( event );
		}
		document.oncontextmenu = function ( event ) {
			return df_nrc_contextAll( event );
		}
		document.oncopy = function ( event ) {
			return df_nrc_contextAll( event );
		}
		if ( df_nrc_drag == 'Y' )
			document.ondragstart = function ( event ) {
				return df_nrc_dragdropAll( event );
			}
		var b = document.getElementsByTagName( "IMG" );
		for ( var i = 0; i < b.length; i++ ) {
			b[i].oncontextmenu = function ( event ) {
				return df_nrc_context( event );
			}
			b[i].oncopy = function ( event ) {
				return df_nrc_context( event );
			}
			b[i].onmousedown = function ( event ) {
				return kpg_nrc1_mousedown( event );
			}
			if ( df_nrc_drag == 'Y' )
				b[i].ondragstart = function ( event ) {
					return df_nrc_dragdrop( event );
				}
		}
	} catch ( er ) {
		return false;
	}
}

if ( document.addEventListener ) {
	document.addEventListener( "DOMContentLoaded", function ( event ) {
		df_nrc_action( event );
	}, false );
} else if ( window.attachEvent ) {
	window.attachEvent( "onload", function ( event ) {
		df_nrc_action( event );
	} );
} else {
	var oldFunc = window.onload;
	window.onload = function () {
		if ( oldFunc ) {
			oldFunc();
		}
		df_nrc_action( 'load' );
	};
}