/**
 * The public opt-in / opt-out form.
 *
 * The state change is a server-side POST; this only stops an impatient double
 * submission from producing a second request.
 */
export class ActionForm {

	constructor( form ) {
		this.form = form;
		this.submitted = false;
	}

	static initAll( root = document ) {
		return Array.from( root.querySelectorAll( '[data-aiplugin5055-action-form]' ) )
			.map( ( form ) => new ActionForm( form ) )
			.map( ( actionForm ) => {
				actionForm.init();
				return actionForm;
			} );
	}

	init() {
		this.form.addEventListener( 'submit', ( event ) => {
			if ( this.submitted ) {
				event.preventDefault();
				return;
			}

			this.submitted = true;

			const button = this.form.querySelector( 'button[type="submit"]' );

			if ( button ) {
				button.setAttribute( 'aria-busy', 'true' );
			}
		} );
	}
}
