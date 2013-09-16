<?php
/**
 * @ingroup Maintenance
 */
if ( getenv( 'MW_INSTALL_PATH' ) ) {
	$IP = getenv( 'MW_INSTALL_PATH' );
} else {
	$IP = dirname(__FILE__).'/../../..';
}

require( __DIR__ . '/../lib/OAuth.php' );
require_once( "$IP/maintenance/Maintenance.php" );

class TestOAuthConsumer extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->mDescription = "Test an OAuth consumer";
		$this->addOption( 'consumerKey', 'Consumer key', true, true );
		$this->addOption( 'consumerSecret', 'Consumer secret', true, true );
		$this->addOption( 'useSSL', 'Use SSL' );
	}

	public function execute() {
		global $wgServer, $wgScriptPath;

		$consumerKey = $this->getOption( 'consumerKey' );
		$consumerSecret = $this->getOption( 'consumerSecret' );
		$baseurl = "{$wgServer}{$wgScriptPath}/index.php?title=Special:MWOAuth";
		$endpoint = "{$baseurl}/initiate&format=json&oauth_callback=oob";

		$endpoint_acc = "{$baseurl}/token&format=json";

		$c = new OAuthConsumer( $consumerKey, $consumerSecret );
		$parsed = parse_url( $endpoint );
		$params = array();
		parse_str( $parsed['query'], $params );
		$req_req = OAuthRequest::from_consumer_and_token( $c, NULL, "GET", $endpoint, $params );
		$hmac_method = new OAuthSignatureMethod_HMAC_SHA1();
		$sig_method = $hmac_method;
		$req_req->sign_request( $sig_method, $c, NULL );

		$this->output( "Calling: $req_req\n" );

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, (string) $req_req );
		if ( $this->hasOption( 'useSSL' ) ) {
			curl_setopt( $ch, CURLOPT_PORT , 443 );
		}
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$data = curl_exec( $ch );

		if ( !$data ) {
			$this->output( 'Curl error: ' . curl_error( $ch ) );
		}

		$this->output( "Returned: $data\n\n" );

		$token = json_decode( $data );
		if ( !$token || !isset( $token->key ) ) {
			$this->error( 'Could not fetch token', 1 );
		}

		$this->output( "Visit $baseurl/authorize" .
			"&oauth_token={$token->key}&oauth_consumer_key=$consumerKey\n" );

		// ACCESS TOKEN
		$this->output( "Enter the verification code:\n" );
		$fh = fopen( "php://stdin", "r" );
		$line = fgets( $fh );

		$rc = new OAuthConsumer( $token->key, $token->secret );
		$parsed = parse_url( $endpoint_acc );
		parse_str( $parsed['query'], $params );
		$params['oauth_verifier'] = trim( $line );

		$acc_req = OAuthRequest::from_consumer_and_token( $c, $rc, "GET", $endpoint_acc, $params );
		$acc_req->sign_request( $sig_method, $c, $rc );

		$this->output( "Calling: $acc_req\n" );

		unset( $ch );
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, (string) $acc_req );
		if ( $this->hasOption( 'useSSL' ) ) {
			curl_setopt( $ch, CURLOPT_PORT , 443 );
		}
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		curl_setopt( $ch, CURLOPT_HEADER, 0 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		$data = curl_exec( $ch );
		if ( !$data ) {
			$this->output( 'Curl error: ' . curl_error( $ch ) );
		}

		$this->output( "Returned: $data\n\n" );
	}
}

$maintClass = "TestOAuthConsumer";
require_once( RUN_MAINTENANCE_IF_MAIN );