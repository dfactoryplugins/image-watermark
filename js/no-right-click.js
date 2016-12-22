/*
 This javascript is used by the no-right-click-images plugin for wordpress.
 Version 2.2
 Please give credit as no-right-click-images.js by Keith P. Graham
 http://www.blogseye.com
 */

var IwNRCtargImg = null;
var IwNRCtargSrc = null;
var IwNRCinContext = false;
var IwNRCnotimage = new Image();
var IwNRClimit = 0;
var IwNRCextra = IwNRCargs.rightclick;
var IwNRCdrag = IwNRCargs.draganddrop;

function IwNRCdragdropAll( event ) {
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
		IwNRCinContext = false;
		return false;
	    }
	}
	if ( targ.tagName.toUpperCase() != "IMG" )
	    return true;
	ev.returnValue = false;
	if ( ev.preventDefault ) {
	    ev.preventDefault();
	}
	IwNRCinContext = false;
	return false;
    } catch ( er ) {
	// alert(er);
    }
    return true;
}

function IwNRCdragdrop( event ) {
    // I am beginning to doubt if this event ever fires
    try {
	var ev = event || window.event;
	var targ = ev.srcElement || ev.target;
	ev.returnValue = false;
	if ( ev.preventDefault ) {
	    ev.preventDefault();
	}
	ev.returnValue = false;
	IwNRCinContext = false;
	return false;
    } catch ( er ) {
	// alert(er);
    }
    return true;
}

function IwNRCcontext( event ) {
    try {
	IwNRCinContext = true;
	var ev = event || window.event;
	var targ = ev.srcElement || ev.target;
	IwNRCreplace( targ );
	ev.returnValue = false;
	if ( ev.preventDefault ) {
	    ev.preventDefault();
	}
	ev.returnValue = false;
	IwNRCtargImg = targ;
    } catch ( er ) {
	// alert(er);
    }
    return false;
}

function IwNRCcontextAll( event ) {
    try {
	if ( IwNRCtargImg == null ) {
	    return true;
	}
	IwNRCinContext = true;
	var ev = event || window.event;
	var targ = ev.srcElement || ev.target;
	if ( targ.tagName.toUpperCase() == "IMG" ) {
	    ev.returnValue = false;
	    if ( ev.preventDefault ) {
		ev.preventDefault();
	    }
	    ev.returnValue = false;
	    IwNRCreplace( targ );
	    return false;
	}
	return true;
    } catch ( er ) {
	// alert(er);
    }
    return false;
}

function IwNRCmousedown( event ) {
    try {
	IwNRCinContext = false;
	var ev = event || window.event;
	var targ = ev.srcElement || ev.target;
	if ( ev.button == 2 ) {
	    IwNRCreplace( targ );
	    return false;
	}
	IwNRCtargImg = targ;
	if ( IwNRCdrag == 'Y' ) {
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

function IwNRCmousedownAll( event ) {
    try {
	IwNRCinContext = false;
	var ev = event || window.event;
	var targ = ev.srcElement || ev.target;
	if ( targ.style.backgroundImage != '' && ev.button == 2 ) {
	    targ.oncontextmenu = function ( event ) {
		return false;
	    } // iffy - might not work
	}
	if ( targ.tagName.toUpperCase() == "IMG" ) {
	    if ( ev.button == 2 ) {
		IwNRCreplace( targ );
		return false;
	    }
	    if ( IwNRCdrag == 'Y' ) {
		if ( ev.preventDefault ) {
		    ev.preventDefault();
		}
	    }
	    IwNRCtargImg = targ;
	}
	return true;
    } catch ( er ) {
	// alert(er);
    }
    return true;
}

function IwNRCreplace( targ ) {
    return false;
    if ( IwNRCtargImg != null && IwNRCtargImg.src == IwNRCnotimage.src ) {
	// restore the old image before hiding this one
	IwNRCtargImg.src = IwNRCtargSrc;
	IwNRCtargImg = null;
	IwNRCtargSrc = null;
    }
    IwNRCtargImg = targ;
    if ( IwNRCextra != 'Y' )
	return;
    var w = targ.width + '';
    var h = targ.height + '';
    if ( w.indexOf( 'px' ) <= 0 )
	w = w + 'px';
    if ( h.indexOf( 'px' ) <= 0 )
	h = h + 'px';
    IwNRCtargSrc = targ.src;
    targ.src = IwNRCnotimage.src;
    targ.style.width = w;
    targ.style.height = h;
    IwNRClimit = 0;
    var t = setTimeout( "IwNRCrestore()", 500 );
    return false;
}

function IwNRCrestore() {
    if ( IwNRCinContext ) {
	if ( IwNRClimit <= 20 ) {
	    IwNRClimit++;
	    var t = setTimeout( "IwNRCrestore()", 500 );
	    return;
	}
    }
    IwNRClimit = 0;
    if ( IwNRCtargImg == null )
	return;
    if ( IwNRCtargSrc == null )
	return;
    IwNRCtargImg.src = IwNRCtargSrc;
    IwNRCtargImg = null;
    IwNRCtargSrc = null;
    return;
}

// set the image onclick event
// need to check for dblclick to see if there is a right double click in IE
function IwNRCaction( event ) {
    try {
	document.onmousedown = function ( event ) {
	    return IwNRCmousedownAll( event );
	}
	document.oncontextmenu = function ( event ) {
	    return IwNRCcontextAll( event );
	}
	document.oncopy = function ( event ) {
	    return IwNRCcontextAll( event );
	}
	if ( IwNRCdrag == 'Y' )
	    document.ondragstart = function ( event ) {
		return IwNRCdragdropAll( event );
	    }
	var b = document.getElementsByTagName( "IMG" );
	for ( var i = 0; i < b.length; i++ ) {
	    b[i].oncontextmenu = function ( event ) {
		return IwNRCcontext( event );
	    }
	    b[i].oncopy = function ( event ) {
		return IwNRCcontext( event );
	    }
	    b[i].onmousedown = function ( event ) {
		return IwNRCmousedown( event );
	    }
	    if ( IwNRCdrag == 'Y' )
		b[i].ondragstart = function ( event ) {
		    return IwNRCdragdrop( event );
		}
	}
    } catch ( er ) {
	return false;
    }
}

if ( document.addEventListener ) {
    document.addEventListener( "DOMContentLoaded", function ( event ) {
	IwNRCaction( event );
    }, false );
} else if ( window.attachEvent ) {
    window.attachEvent( "onload", function ( event ) {
	IwNRCaction( event );
    } );
} else {
    var oldFunc = window.onload;
    window.onload = function () {
	if ( oldFunc ) {
	    oldFunc();
	}
	IwNRCaction( 'load' );
    };
}