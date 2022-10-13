<?php
/**
 *	SMS send mobile msg
 */
defined('IN') || exit('Access Denied');

/**

CREATE TABLE `log_sms` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dst` varchar(1000) NOT NULL,
  `sms` varchar(255) NOT NULL COMMENT 'dynamic code',
  `dateline` int(11) NOT NULL,
  `result` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dst` (`dst`(255)),
  KEY `dateline` (`dateline`)
) ENGINE=InnoDB AUTO_INCREMENT=53370 DEFAULT CHARSET=utf8 COMMENT='sms log';

 */

class sms {
	const TB = 'log.log_sms';
	/**
	 *	SMS手机短信发送
	 *
	 */
	public static function send($mobile, $info){
		$dst = s::len($mobile) == 10 ? "1$mobile" : $mobile;
		//检查是否短时间内已经发送过
		$tmp = db::s( self::TB, ['dst'=>$dst], 'id desc');
		$terminate = false;
		$result = 'waiting';
		if($tmp && TS-$tmp['dateline'] < 60){
			$result = 'Too frequent';
			$terminate = true;
		}
		//记录短信发送
		$s = [
			'sms'	=> $info,
			'dst'	=> $dst,
			'dateline'	=> TS,
			'result'	=> $result,
		];
		$logid = db::i( self::TB, $s);
		if($terminate) return $result;//终止发送

		if(substr($dst, 0, 1) != '+') $dst = '+'.$dst;
		$result = sns::send($dst, $info);

		// get result code
		//$responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$s = [
			'result'	=> $result,
		];
		db::u( self::TB, $s, $logid);

		$result2 = json_decode($result, 1);
		if(!empty($result2['error'])) return $result2['error'];
		if(!empty($result2['message']) && $result2['message'] == 'message(s) queued') return 1;
		return $result;
	}

	public static function doapi( $mobile, $info, $vip = 'dfdz', $callback = false, $app = 'epochnyc' ) {
		$dst = s::len($mobile) == 10 ? "1$mobile" : $mobile;
		$result = 'waiting';
		$s = [
			'sms'	=> $info,
			'dst'	=> $dst,
			'dateline'	=> TS,
			'result'	=> $result,
		];
		$logid = db::i( self::TB, $s);

		// Send
		$res = f::post( 'https://doapi.us/text?format=json', [ 'phone' => $dst, 'content' => $info, 'app' => $app, 'vip' => $vip, 'callback' => $callback ] );
		db::u( self::TB, [ 'result' => $res ], $logid );

		return $res;
	}

	public static function twilio_exit( $msg ) {
		header( 'Content-Type: text/xml' );
		exit( '<Response/>' );
	}

	public static function twilio( $mobile, $info, $from_number = false, $callback_url = false ) {
		$dst = s::len( $mobile ) == 10 ? '+1' . $mobile : $mobile;
		$tmp = db::s( self::TB, [ 'dst' => $dst ], 'id desc' );
		if ( $tmp && TS - $tmp[ 'dateline' ] < 5 ) {
			b( 'too often for one number: ' . $mobile );
		}

		$result = 'waiting';
		$s = [
			'sms'	=> $info,
			'dst'	=> $dst,
			'dateline'	=> TS,
			'result'	=> $result,
		];
		$logid = db::i( self::TB, $s);

		$auth_key = _cfg( 'twilio', 'key' );
		$auth_secret = _cfg( 'twilio', 'secret' );
		$num_from = _cfg( 'twilio', 'from' . $from_number );

		$url = 'https://api.twilio.com/2010-04-01/Accounts/' . $auth_key . '/Messages.json';
		$data = [
			'Body' => $info,
			'From' => $num_from,
			'To' => $dst,
		];
		if ( $callback_url ) {
			$data[ 'StatusCallback' ] = $callback_url;
			debug( 'twilio cb url ' . $callback_url );
		}
		$data = http_build_query( $data );

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_USERPWD, $auth_key . ':' . $auth_secret );
		$result = curl_exec($curl);
		if ( ! $result ) exit( 'SMS Failed with error ' . curl_error( $curl ) );

		$s = [
			'result'	=> $result,
		];
		db::u( self::TB, $s, $logid );

		$result = json_decode( $result, true );
		if ( ! empty( $result[ 'status' ] ) ) {
			debug( 'result: ' . $result[ 'status' ] );
		}
		else {
			debug( 'res', $result );
		}

		if ( ! empty( $result[ 'error_message' ] ) ) {
			b( $result[ 'error_message' ] );
		}

		if ( ! empty( $result[ 'status' ] ) ) {
			return $result[ 'status' ];
		}

		return 'unknown status';
	}

	public static function plivo($mobile, $info){
		$dst = s::len($mobile) == 10 ? "1$mobile" : $mobile;
		//检查是否短时间内已经发送过
		$tmp = db::s( self::TB, ['dst'=>$dst], 'id desc');
		$terminate = false;
		$result = 'waiting';
		if($tmp && TS-$tmp['dateline'] < 60){
			$result = 'Too frequent';
			$terminate = true;
		}
		//记录短信发送
		$s = [
			'sms'	=> $info,
			'dst'	=> $dst,
			'dateline'	=> TS,
			'result'	=> $result,
		];
		$logid = db::i( self::TB, $s);
		if($terminate) return $result;//终止发送

		// 發送
		$authid = _cfg('plivo', 'id');
		$authkey = _cfg('plivo', 'key');
		$data = array(
			'src'	=> _cfg('plivo', 'mobile'),
			'dst'	=> $dst,
			'text'	=> $info,
		);

		$url = "https://api.plivo.com/v1/Account/$authid/Message/";
		$data = json_encode($data);

		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($curl, CURLOPT_POST, TRUE);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		curl_setopt($curl, CURLOPT_USERPWD, $pwd = "$authid:$authkey");
		curl_setopt($curl, CURLOPT_HTTPHEADER, [
		    'Content-Type: application/json',
		    'Content-Length: '.strlen($data)
		] );
		$result = curl_exec($curl);
		if(FALSE === $result) throw(new PlivoException("SMS Failed with error " . curl_error($curl)));

		// get result code
		//$responseCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$s = [
			'result'	=> $result,
		];
		db::u( self::TB, $s, $logid);

		$result2 = json_decode($result, 1);
		if(!empty($result2['error'])) return $result2['error'];
		if(!empty($result2['message']) && $result2['message'] == 'message(s) queued') return 1;
		return $result;
	}
}