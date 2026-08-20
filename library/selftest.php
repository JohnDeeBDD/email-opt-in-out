<?php
/**
 * Self-test for the shared action-code library.
 *
 * Runnable two ways, with the same checks either way:
 *
 *     php library/selftest.php            # on the server, no test framework
 *     bin/codecept run phpunit            # tests/phpunit/LibraryCest.php
 *
 * The vectors below were produced by `ActionCode::build()` before the plugin
 * and the Gmail Campaign Manager were merged onto one implementation, and the
 * campaign manager's own test suite pins the same values from the other side.
 * They are what stands between a change to this library and ten thousand
 * mailed links that no longer decode. The alphabet and secret are test values
 * generated for this file — no site uses them.
 *
 * @package aiplugin5055\Library
 */

require_once __DIR__ . '/autoload.php';

use aiplugin5055\Library\ActionCode;
use aiplugin5055\Library\ActionUrls;
use aiplugin5055\Library\CampaignCode;
use aiplugin5055\Library\EmailCodec;
use aiplugin5055\Library\SiteSettings;

/**
 * Run every check.
 *
 * @return array[] One entry per check: array{name: string, ok: bool, detail: string}.
 */
if ( ! function_exists( 'aiplugin5055_library_selftest' ) ) :

function aiplugin5055_library_selftest() {
	$results = array();

	$record = function ( $name, $ok, $detail = '' ) use ( &$results ) {
		$results[] = array(
			'name'   => $name,
			'ok'     => (bool) $ok,
			'detail' => (string) $detail,
		);
	};

	$shuffled_alphabet = 'P3KQ7SAZ5XW9MHTGDR2NJ4BCUEF6YV8L';
	$shuffled_secret   = 'Ht7#pZq2$Lm9!Wv4&Xc8*Nb6@Kd3^Rf5';
	$identity_secret   = 'another-secret-value-9876';

	$shuffled = new SiteSettings( $shuffled_alphabet, $shuffled_secret, 3 );
	$identity = new SiteSettings( SiteSettings::BASE_ALPHABET, $identity_secret, 3 );

	$vectors = array(
		array( $shuffled, 'A7K2Q', 'john@example.com', 'A7K2QHXCBD62PM4YAK69DHR22YU6GHJU8N' ),
		array( $shuffled, 'A7K2Q', 'JOHN@EXAMPLE.COM', 'A7K2QHXCBD62PM4YAK69DHR22YU6GHJU8N' ),
		array( $shuffled, 'a7k2q', '  John@Example.Com  ', 'A7K2QHXCBD62PM4YAK69DHR22YU6GHJU8N' ),
		array( $shuffled, 'BCDEF', 'a@b.co', 'BCDEFMSPA79NQHYYNN' ),
		array( $shuffled, 'ZZZZZ', 'first.last+tag@sub.domain.example', 'ZZZZZMEJC7Y6JSEBAKY6JSHFAKEFPTHFB79N7HVBBKF9TSE2CDU9HT3BAWQ4A' ),
		array( $shuffled, '23456', 'x@y.z', '23456G3PZ29NF2JE' ),
		array( $shuffled, 'A7K2Q', 'johndeebdd@gmail.com', 'A7K2QHXCBD6N7M42B7EQ753NBFU9XHDCAA66HLSQ' ),
		array( $identity, 'A7K2Q', 'john@example.com', 'A7K2QPKZYS5UANX6GC5MSPTUU625RPWP74' ),
		array( $identity, 'BCDEF', 'a@b.co', 'BCDEFNFAGEMVDP6W3N' ),
		array( $identity, 'ZZZZZ', 'first.last+tag@sub.domain.example', 'ZZZZZN3WZE65WF3YGC65WFP4GC34AQP4YEMVEP7YYC4MQF3UZS2MPQBYGLZT4' ),
		array( $identity, '23456', 'x@y.z', '23456RBAHUMV422T' ),
		array( $identity, 'A7K2Q', 'johndeebdd@gmail.com', 'A7K2QPKZYS5VENXUYE3DEJBVY42MKPSZGG55PUDT' ),
	);

	foreach ( $vectors as $index => $vector ) {
		list( $settings, $campaign_code, $email, $expected ) = $vector;

		$built = ActionCode::build( $campaign_code, $email, $settings );
		$record( "vector {$index}: build {$campaign_code} / {$email}", $expected === $built, $built );

		// Every code this library builds is handed straight back to the parser
		// the site runs on it.
		$parsed = ActionCode::parse( $built, $settings );
		$record(
			"vector {$index}: round trip",
			$parsed['ok']
				&& strtoupper( $campaign_code ) === $parsed['campaign_code']
				&& EmailCodec::canonicalize( $email ) === $parsed['email'],
			(string) json_encode( $parsed )
		);
	}

	// Determinism: the same inputs, over and over.
	$repeated = array();
	for ( $i = 0; $i < 100; $i++ ) {
		$repeated[ ActionCode::build( 'A7K2Q', 'john@example.com', $shuffled ) ] = true;
	}
	$record( 'determinism: 100 builds produce one code', 1 === count( $repeated ) );

	// The payload is packed, not padded.
	foreach ( array( 'a@b.co', 'x@y.z', 'john@example.com' ) as $email ) {
		$payload = substr( ActionCode::build( 'A7K2Q', $email, $shuffled ), 5, -3 );
		$record(
			"payload length for {$email}",
			strlen( $payload ) === (int) ceil( strlen( $email ) * 8 / 5 )
		);
	}

	// A check value of zero appends nothing, and the payload is unchanged.
	$without = ActionCode::build( 'A7K2Q', 'john@example.com', $shuffled->with_check_value_length( 0 ) );
	$record(
		'check_value_length 0 appends nothing',
		'A7K2QHXCBD62PM4YAK69DHR22YU6GHJ' === $without
	);

	// A code built at one check length is refused by a site using another.
	$mismatched = ActionCode::parse(
		ActionCode::build( 'A7K2Q', 'john@example.com', $shuffled ),
		$shuffled->with_check_value_length( 4 )
	);
	$record( 'a mismatched check length is refused', ! $mismatched['ok'], $mismatched['reason'] );

	// Mail clients mangle links; the parser is expected to cope.
	$code    = ActionCode::build( 'A7K2Q', 'john@example.com', $shuffled );
	$mangled = ActionCode::parse( strtolower( substr( $code, 0, 10 ) ) . "\r\n " . strtolower( substr( $code, 10 ) ), $shuffled );
	$record( 'a soft-wrapped, lowercased code still parses', $mangled['ok'] && 'john@example.com' === $mangled['email'] );

	// Tampering is not.
	foreach (
		array(
			'truncated'      => substr( $code, 0, -2 ),
			'swapped prefix' => 'BCDEF' . substr( $code, 5 ),
			'empty'          => '',
			'payload only'   => 'A7K2Q',
		) as $what => $bad
	) {
		$rejected = ActionCode::parse( $bad, $shuffled );
		$record( "rejects a {$what} code", ! $rejected['ok'], $rejected['reason'] );
	}

	// Decoding refuses what it cannot decode unambiguously.
	$record( 'decode refuses a character outside the alphabet', null === EmailCodec::decode( 'ABC!DEF', $shuffled_alphabet ) );
	$record( 'decode refuses an empty payload', null === EmailCodec::decode( '', $shuffled_alphabet ) );

	// Canonicalization.
	$record(
		'canonicalization lowercases and trims',
		'john@example.com' === EmailCodec::canonicalize( "  JOHN@Example.COM \n" )
	);

	// Settings validation.
	foreach (
		array(
			'an alphabet with a repeated character' => array( str_repeat( 'A', 32 ), 'secret', 3 ),
			'an alphabet of the wrong length'       => array( 'ABC', 'secret', 3 ),
			'an empty secret with a check value'    => array( SiteSettings::BASE_ALPHABET, '', 3 ),
			'a check value that is too long'        => array( SiteSettings::BASE_ALPHABET, 'secret', 9 ),
		) as $what => $arguments
	) {
		$refused = false;
		try {
			new SiteSettings( $arguments[0], $arguments[1], $arguments[2] );
		} catch ( InvalidArgumentException $e ) {
			$refused = true;
		}
		$record( "refuses {$what}", $refused );
	}

	$record(
		'the base alphabet is a permutation of itself but a typo is not',
		SiteSettings::is_base_permutation( $shuffled_alphabet )
			&& SiteSettings::is_base_permutation( SiteSettings::BASE_ALPHABET )
			&& ! SiteSettings::is_base_permutation( 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567' )
	);

	// Campaign codes.
	$random = CampaignCode::random();
	$record( 'a minted campaign code is well formed', CampaignCode::is_well_formed( $random ), $random );
	foreach ( array( '', 'ABCD', 'ABCDEF', 'ABCDI', 'ABCD0', 'AB-DE' ) as $bad ) {
		$record( "'{$bad}' is not a campaign code", ! CampaignCode::is_well_formed( $bad ) );
	}

	// A malformed campaign code never reaches a recipient.
	$refused = false;
	try {
		ActionCode::build( 'ABCDI', 'john@example.com', $shuffled );
	} catch ( InvalidArgumentException $e ) {
		$refused = true;
	}
	$record( 'refuses to build against a malformed campaign code', $refused );

	// URLs. The opt-out link must name the action whichever endpoint style the
	// site uses, or the plugin does not see an unsubscribe request at all.
	$record(
		'the opt-in URL carries only the code',
		'https://example.com/?c=' . $code === ActionUrls::opt_in_url( 'https://example.com/', $code )
	);
	$record(
		'the opt-out URL names the action',
		'https://example.com/email-unsubscribe/?aiplugin5055_action=opt_out&c=' . $code
			=== ActionUrls::opt_out_url( 'https://example.com/email-unsubscribe/', $code )
	);
	$record(
		'the action parameter is not duplicated',
		'https://example.com/?aiplugin5055_action=opt_out&c=' . $code
			=== ActionUrls::opt_out_url( 'https://example.com/?aiplugin5055_action=opt_out', $code )
	);
	$record(
		'an existing query parameter survives',
		'https://example.com/?page_id=42&aiplugin5055_action=opt_out&c=' . $code
			=== ActionUrls::opt_out_url( 'https://example.com/?page_id=42', $code )
	);
	$record(
		'a fragment survives',
		'https://example.com/?c=' . $code . '#top' === ActionUrls::opt_in_url( 'https://example.com/#top', $code )
	);

	$refused = false;
	try {
		ActionUrls::opt_out_url( 'https://example.com/', '' );
	} catch ( InvalidArgumentException $e ) {
		$refused = true;
	}
	$record( 'refuses to build a link with no code', $refused );

	return $results;
}

endif;

// Run the checks when this file is executed directly.
if ( PHP_SAPI === 'cli' && isset( $argv[0] ) && realpath( $argv[0] ) === realpath( __FILE__ ) ) {
	$results = aiplugin5055_library_selftest();
	$failed  = 0;

	foreach ( $results as $result ) {
		if ( ! $result['ok'] ) {
			$failed++;
		}

		printf(
			"%-6s %s%s\n",
			$result['ok'] ? 'ok' : 'FAIL',
			$result['name'],
			$result['ok'] || '' === $result['detail'] ? '' : '  -- ' . $result['detail']
		);
	}

	printf(
		"\n%d checks, %d failed (library %s)\n",
		count( $results ),
		$failed,
		AIPLUGIN5055_LIBRARY_VERSION
	);

	exit( 0 === $failed ? 0 : 1 );
}
