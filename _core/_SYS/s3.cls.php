<?php
/**
 *	S3 Operator
 *
 * @since  20180516 Added exceptions
 *
 */
defined( 'IN' ) || exit;

require_once '/var/www/vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class s3 extends instance {
	private $_s3;
	private $_bucket = false;

	/**
	 * Init
	 *
	 * @since  1.6
	 * @access private
	 */
	protected function __construct() {
		$cfg = _cfg( 'aws' );

		$cfg[ 'credentials' ] = _cfg( 'awskey' );

		$_awsSdk = new Aws\Sdk( $cfg );

		if ( ! $cfg[ 'bucket' ] ) {
			exit( 'no s3 bucket' );
		}

		$this->_bucket = $cfg[ 'bucket' ];

		$this->_s3 = $_awsSdk->createS3();
	}

	public static function url( $name, $bucket = false, $expire = false ) {
		if ( ! $name || strlen( $name ) < 2 ) {
			return $name;
		}

		$instance = self::cls();

		$bucket = $bucket ?: $instance->_bucket;

		$expire = $expire ?: '+1 day';

		try {
			$cmd = $instance->_s3->getCommand( 'GetObject', array( 'Bucket' => $bucket, 'Key' => $name ) );
			$request = $instance->_s3->createPresignedRequest( $cmd, $expire );
			$presignedUrl = (string) $request->getUri();
		}
		catch ( S3Exception $e ) {
			exit( $e->getMessage() );
		}

		return $presignedUrl;
	}

	public static function read( $name, $bucket = false ) {
		$instance = self::cls();

		$bucket = $bucket ?: $instance->_bucket;

		if ( ! self::exist( $name, $bucket ) ) {
			exit( "File not exist: $name" );
		}

		try {
			$info = $instance->_s3->getObject( array(
			    'Bucket'       => $bucket,
			    'Key'          => $name,
			) );
		}
		catch ( S3Exception $e ) {
			exit( $e->getMessage() );
		}

		return $info[ 'Body' ];
	}

	public static function del( $name, $bucket = false ) {
		$instance = self::cls();

		$bucket = $bucket ?: $instance->_bucket;

		try {
			$info = $instance->_s3->deleteObject( array(
			    'Bucket'       => $bucket,
			    'Key'          => $name,
			) );
		}
		catch ( S3Exception $e ) {
			exit( $e->getMessage() );
		}

		return $info;
	}

	public static function exist( $name, $bucket = false ) {
		$instance = self::cls();

		$bucket = $bucket ?: $instance->_bucket;

		try {
			$info = $instance->_s3->doesObjectExist( $bucket, $name );
		}
		catch ( S3Exception $e ) {
			exit( $e->getMessage() );
		}

		return $info;
	}

	public static function upload( $name, $file, $bucket = false ) {
		if ( ! file_exists( $file ) ) {
			return false;
		}

		$instance = self::cls();

		$bucket = $bucket ?: $instance->_bucket;

		try {
			$result = $instance->_s3->putObject( array(
			    'Bucket'       => $bucket,
			    'Key'          => $name,
			    'SourceFile'   => $file,
			    //'StorageClass' => 'REDUCED_REDUNDANCY',
			) );

			$instance->_s3->waitUntil( 'ObjectExists', array(
			    'Bucket' => $bucket,
			    'Key'    => $name,
			) );
		}
		catch ( S3Exception $e ) {
			exit( $e->getMessage() );
		}

		return self::exist( $name, $bucket );
	}

	public static function write( $name, $info, $bucket = false ) {
		$instance = self::cls();

		$bucket = $bucket ?: $instance->_bucket;

		try {
			$result = $instance->_s3->upload( $bucket, $name, $info );
		}
		catch ( S3Exception $e ) {
			exit( $e->getMessage() );
		}

		return $result;
	}

	public static function cp( $src, $target, $bucket = false ) {
		$instance = self::cls();

		$bucket = $bucket ?: $instance->_bucket;

		try {
			$cmd = $instance->_s3->getCommand( 'copyObject', array(
			    'Bucket'       => $bucket,
			    'CopySource'   => $bucket . '/' . $src,
			    'Key'          => $target,
			) );

			$info = $instance->_s3->execute( $cmd );
		}
		catch ( S3Exception $e ) {
			exit( $e->getMessage() );
		}

		return $info;
	}

	public static function mv( $src, $target, $bucket = false ) {
		try {
			self::cp( $src, $target, $bucket );
			self::del( $src, $bucket );
		}
		catch( S3Exception $e ) {
			return false;
		}
	}

}