<?php
/**
 * @ref https://github.com/firebase/php-jwt/blob/master/src/JWT.php
 */
defined( 'IN' ) || exit;

// require_once '/var/www/vendor/autoload.php';

// use Firebase\JWT\JWT;

class jwt extends instance {
	public static $supported_algs = array(
		'ES384' => array('openssl', 'SHA384'),
		'ES256' => array('openssl', 'SHA256'),
		'HS256' => array('hash_hmac', 'SHA256'),
		'HS384' => array('hash_hmac', 'SHA384'),
		'HS512' => array('hash_hmac', 'SHA512'),
		'RS256' => array('openssl', 'SHA256'),
		'RS384' => array('openssl', 'SHA384'),
		'RS512' => array('openssl', 'SHA512'),
		'EdDSA' => array('sodium_crypto', 'EdDSA'),
    );

    private static $header;
    private static $apikey;
    private static $payload;

	protected function __construct() {
	}

	public static function token() {
		self::$header = [
			'alg' => 'RS256',
			'typ' => 'JWT'
		];

		self::$apikey = _cfg( 'benzinga', 'apikey'  );

		self::$payload = [
			'iss' => _cfg( 'benzinga', 'iss' ),
			'kid' => self::$apikey,
			'sub' => TS,
			'nbf' => TS,
			'exp' => TS+3600,
		];

		$segments = self::b64encode( self::$header ) . '.' . self::b64encode( self::$payload );

		self::gen_pkey();
		// $key = openssl_pkey_get_private( '/var/www/key.pem' );
		$key = openssl_pkey_get_private( '/var/www/private_key.pem' );
		var_dump(error_get_last(),openssl_error_string());
		var_dump($key);
		$signature = self::sign( $segments, $key, self::$header[ 'alg' ] );
		$segments .= self::b64encode( $signature );

		return $segments;
	}

	private static function gen_pkey() {
		$new_key_pair = openssl_pkey_new(array(
			"private_key_bits" => 2048,
			"private_key_type" => OPENSSL_KEYTYPE_RSA,
		));
		openssl_pkey_export($new_key_pair, $private_key_pem);
		$details = openssl_pkey_get_details($new_key_pair);
		$public_key_pem = $details['key'];

		file_put_contents('/var/www/private_key.pem', $private_key_pem);
		file_put_contents('/var/www/public_key.pem', $public_key_pem);
		file_put_contents('/var/www/signature.dat', $signature);
	}

	private static function sign( $msg, $key, $alg ) {
		list( $function, $algorithm ) = static::$supported_algs[ $alg ];

		switch ($function) {
			case 'hash_hmac':
				return \hash_hmac($algorithm, $msg, $key, true);

			case 'openssl':
				$signature = '';
				$success = \openssl_sign( $msg, $signature, $key, $algorithm );
				if ( ! $success ) {
					b("OpenSSL unable to sign data");
				}
				return $signature;

			default:
				b( 'err in jwt algs' );
		}

		return $signature;
	}

	private static function signatureFromDER( $der, $keySize ) {
		// OpenSSL returns the ECDSA signatures as a binary ASN.1 DER SEQUENCE
		list($offset, $_) = self::readDER($der);
		list($offset, $r) = self::readDER($der, $offset);
		list($offset, $s) = self::readDER($der, $offset);

		// Convert r-value and s-value from signed two's compliment to unsigned
		// big-endian integers
		$r = \ltrim($r, "\x00");
		$s = \ltrim($s, "\x00");

		// Pad out r and s so that they are $keySize bits long
		$r = \str_pad($r, $keySize / 8, "\x00", STR_PAD_LEFT);
		$s = \str_pad($s, $keySize / 8, "\x00", STR_PAD_LEFT);

		return $r . $s;
    }

	private static function b64encode( $arr ) {
		return str_replace( '=', '', strtr( base64_encode( json_encode( $arr ) ), '+/', '-_' ) );
	}
}