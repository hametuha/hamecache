/**
 * Purge Controller.
 */

const $ = jQuery;
const { __, sprintf } = wp.i18n;


/**
 * Display message.
 *
 * @param {String} message
 * @param {String} type
 */
const displayMessage = ( message, type = '' ) => {
	const $existing = $( '.hamecache-message' );
	if ( $existing.length ) {
		$existing.remove();
	}
	const $html     = $( '<div class="hamecache-message"><p><span class="dashicons dashicons-cloud"></span> <span></span></p></div>' );
	if ( type ) {
		$html.addClass( 'hamecache-message-' + type );
	}
	$html.find( 'p span:nth-child(2)' ).text( message );
	$( 'body' ).append( $html ).effect( 'highlight' );
	setTimeout( function() {
		$html.fadeOut( 500, function() {
			$html.remove();
		});
	}, 5000 );
};

$( document ).ready( () => {

	$( '.hamecache-ab-btn a' ).click( function( e ) {
		e.preventDefault();
		let target = $( this ).attr( 'href' );
		let message = '';
		if ( '#all' === target ) {
			target = 'cache/everything';
			message = __( 'Removing all caches...', 'hamecache' );
		} else {
			const postId = target.replace( '#post-', '' );
			target = 'cache/post/' + postId;
			message = sprintf( __( 'Removing caches of post #%d...', 'hamecache' ), postId );
		}
		displayMessage( message );
		wp.apiFetch({
			path: '/hamecache/v1/' + target,
			method: 'DELETE'
		}).then( res => {
			displayMessage( res.message, 'success' );
		}).catch( res => {
			displayMessage( res.message || __( 'Error occurred. Please try again later.', 'hamecache' ), 'error' );
		});
	});
});
