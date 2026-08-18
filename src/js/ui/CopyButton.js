/**
 * Copy-to-clipboard for campaign codes on the Tools screen.
 */
export class CopyButton {

	constructor( element ) {
		this.element = element;
		this.value = element.getAttribute( 'data-aiplugin5055-copy' ) || '';
		this.originalLabel = element.textContent;
	}

	static initAll( root = document ) {
		return Array.from( root.querySelectorAll( '[data-aiplugin5055-copy]' ) )
			.map( ( element ) => new CopyButton( element ) )
			.map( ( button ) => {
				button.init();
				return button;
			} );
	}

	init() {
		this.element.addEventListener( 'click', () => this.copy() );
	}

	async copy() {
		try {
			await navigator.clipboard.writeText( this.value );
			this.flash( 'Copied' );
		} catch ( error ) {
			// Clipboard access can be refused; the code is on screen anyway.
			this.flash( 'Press Ctrl+C' );
		}
	}

	flash( label ) {
		this.element.textContent = label;

		window.setTimeout( () => {
			this.element.textContent = this.originalLabel;
		}, 1500 );
	}
}
