/**
 * Thin client over the administrator-only tracking code REST endpoint.
 */
export class TrackingCodeClient {

	constructor( restUrl, nonce ) {
		this.restUrl = restUrl;
		this.nonce = nonce;
	}

	/**
	 * Read the configuration the admin screen printed into the page.
	 */
	static fromPage( root = document ) {
		const node = root.getElementById( 'aiplugin5055-admin-config' );

		if ( ! node ) {
			return null;
		}

		try {
			const config = JSON.parse( node.textContent );
			return new TrackingCodeClient( config.restUrl, config.nonce );
		} catch ( error ) {
			return null;
		}
	}

	async generate( campaignCode, emails ) {
		const response = await fetch( this.restUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': this.nonce,
			},
			body: JSON.stringify( { campaign_code: campaignCode, emails } ),
		} );

		const payload = await response.json();

		if ( ! response.ok ) {
			throw new Error( payload && payload.message ? payload.message : 'Request failed' );
		}

		return payload;
	}
}
