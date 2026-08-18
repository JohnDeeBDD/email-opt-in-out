/**
 * A first, in-page guard on the delete links. The authoritative confirmation
 * is the checkbox on the server-rendered delete screen.
 */
export class DeleteConfirmation {

	constructor( element ) {
		this.element = element;
	}

	static initAll( root = document ) {
		return Array.from( root.querySelectorAll( '.aiplugin5055-delete-link' ) )
			.map( ( element ) => new DeleteConfirmation( element ) )
			.map( ( guard ) => {
				guard.init();
				return guard;
			} );
	}

	init() {
		this.element.addEventListener( 'click', ( event ) => {
			const message = 'Deleting removes the campaign record. Recorded opt-in and opt-out metadata is kept. Continue?';

			if ( ! window.confirm( message ) ) {
				event.preventDefault();
			}
		} );
	}
}
