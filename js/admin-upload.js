jQuery( document ).ready( function ( $ ) {

    watermarkFileUpload = {
	frame: function () {
	    if ( this._frameWatermark )
		return this._frameWatermark;

	    this._frameWatermark = wp.media( {
		title: iwUploadArgs.title,
		frame: iwUploadArgs.frame,
		button: iwUploadArgs.button,
		multiple: iwUploadArgs.multiple,
		library: {
		    type: 'image'
		}
	    } );

	    this._frameWatermark.on( 'open', this.updateFrame ).state( 'library' ).on( 'select', this.select );
	    return this._frameWatermark;
	},
	select: function () {
	    var attachment = this.frame.state().get( 'selection' ).first();

	    if ( $.inArray( attachment.attributes.mime, [ 'image/gif', 'image/jpg', 'image/jpeg', 'image/png' ] ) !== -1 ) {

		$( '#iw_upload_image' ).val( attachment.attributes.id );

		if ( $( 'div#previewImg_imageDiv img#previewImg_image' ).attr( 'src' ) !== '' ) {
		    $( 'div#previewImg_imageDiv img#previewImg_image' ).replaceWith( '<img id="previewImg_image" src="' + attachment.attributes.url + '" alt="" width="300" />' );
		} else {
		    $( 'div#previewImg_imageDiv img#previewImg_image' ).attr( 'src', attachment.attributes.url );
		}

		$( '#iw_turn_off_image_button' ).removeAttr( 'disabled' );
		$( 'div#previewImg_imageDiv img#previewImg_image' ).show();

		var img = new Image();
		img.src = attachment.attributes.url;

		img.onload = function () {
		    $( 'p#previewImageInfo' ).html( iwUploadArgs.originalSize + ': ' + this.width + ' ' + iwUploadArgs.px + ' / ' + this.height + ' ' + iwUploadArgs.px );
		}

	    } else {

		$( '#iw_turn_off_image_button' ).attr( 'disabled', 'true' );
		$( '#iw_upload_image' ).val( 0 );
		$( 'div#previewImg_imageDiv img#previewImg_image' ).attr( 'src', '' ).hide();
		$( 'p#previewImageInfo' ).html( '<strong>' + iwUploadArgs.notAllowedImg + '</strong>' );

	    }
	},
	init: function () {
	    $( '#wpbody' ).on( 'click', 'input#iw_upload_image_button', function ( e ) {
		e.preventDefault();
		watermarkFileUpload.frame().open();
	    } );
	}
    };

    watermarkFileUpload.init();

    $( document ).on( 'click', '#iw_turn_off_image_button', function ( event ) {
	$( this ).attr( 'disabled', 'true' );
	$( '#iw_upload_image' ).val( 0 );
	$( 'div#previewImg_imageDiv img#previewImg_image' ).attr( 'src', '' ).hide();
	$( 'p#previewImageInfo' ).html( iwUploadArgs.noSelectedImg );
    } );

} );