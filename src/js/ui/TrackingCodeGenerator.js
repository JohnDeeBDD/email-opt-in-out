/**
 * Drives the "Generate tracking codes" box: addresses in, mail-merge CSV out.
 */
export class TrackingCodeGenerator {

	constructor( form, client ) {
		this.form = form;
		this.client = client;
		this.campaign = form.querySelector( '[data-aiplugin5055-generator-campaign]' );
		this.emails = form.querySelector( '[data-aiplugin5055-generator-emails]' );
		this.output = form.querySelector( '[data-aiplugin5055-generator-output]' );
		this.status = form.querySelector( '[data-aiplugin5055-generator-status]' );
		this.submit = form.querySelector( '[data-aiplugin5055-generator-submit]' );
	}

	static initAll( client, root = document ) {
		if ( ! client ) {
			return [];
		}

		return Array.from( root.querySelectorAll( '[data-aiplugin5055-generator]' ) )
			.map( ( form ) => new TrackingCodeGenerator( form, client ) )
			.map( ( generator ) => {
				generator.init();
				return generator;
			} );
	}

	init() {
		this.submit.addEventListener( 'click', ( event ) => {
			event.preventDefault();
			this.run();
		} );
	}

	async run() {
		const emails = this.emails.value
			.split( /[\s,;]+/ )
			.map( ( value ) => value.trim() )
			.filter( ( value ) => value.length > 0 );

		if ( emails.length === 0 ) {
			this.setStatus( 'Add at least one address.' );
			return;
		}

		this.submit.disabled = true;
		this.setStatus( 'Generating…' );

		try {
			const result = await this.client.generate( this.campaign.value, emails );
			this.output.value = TrackingCodeGenerator.toCsv( result.codes );
			this.setStatus( `${ result.codes.length } generated, ${ result.rejected.length } rejected.` );
		} catch ( error ) {
			this.output.value = '';
			this.setStatus( error.message );
		} finally {
			this.submit.disabled = false;
		}
	}

	setStatus( message ) {
		this.status.textContent = message;
	}

	static toCsv( codes ) {
		const rows = [ [ 'email', 'campaign_code', 'tracking_code', 'opt_in_url', 'opt_out_url' ] ];

		codes.forEach( ( code ) => {
			rows.push( [ code.email, code.campaign_code, code.tracking_code, code.opt_in_url, code.opt_out_url ] );
		} );

		return rows.map( ( row ) => row.map( TrackingCodeGenerator.quote ).join( ',' ) ).join( '\n' );
	}

	static quote( value ) {
		const text = String( value );

		return /[",\n]/.test( text ) ? `"${ text.replace( /"/g, '""' ) }"` : text;
	}
}
