<?php
/**
 * @since  Dec/28/2020 One time dynamic pswd. Dropped md5md5 pswd.
 */
defined( 'IN' ) || exit;

/**

--------- Database: acp -----------

CREATE TABLE `admin_session` (
  `sid` char(32) NOT NULL,
  `acp_id` int(11) NOT NULL,
  `acp_name` varchar(255) NOT NULL,
  `acp_truename` varchar(255) NOT NULL,
  `privilege` mediumtext NOT NULL COMMENT '',
  `priv_tags` mediumtext NOT NULL COMMENT 'Special privileges',
  `root` tinyint(4) NOT NULL,
  `dateline` int(11) NOT NULL,
  `ip` varchar(255) NOT NULL,
  `koffice_id` varchar(255) NOT NULL DEFAULT '',
  `cols_disabled` text NOT NULL DEFAULT '',
  UNIQUE KEY `sid` (`sid`),
  KEY `acp_id` (`acp_id`),
  KEY `lastdateline` (`dateline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Online sessions';

CREATE TABLE `dynamic_pswd` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `acp_id` int(11) NOT NULL,
  `acp_truename` varchar(255) NOT NULL,
  `code` varchar(255) NOT NULL,
  `dateline` int(11) NOT NULL,
  `ip` varchar(255) DEFAULT NULL,
  `valid` tinyint(4) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acp_id` (`acp_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14712 DEFAULT CHARSET=utf8 COMMENT='acp的动态密码表';

CREATE TABLE `admin_failure` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(255) NOT NULL DEFAULT '',
  `dateline` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ip` (`ip`),
  KEY `dateline` (`dateline`)
) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4;

--------- Database: log -----------

CREATE TABLE `log_ip_auth` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `acp_id` int(11) NOT NULL,
  `ip` varchar(255) NOT NULL DEFAULT '',
  `dateline` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `acp_id_2` (`acp_id`,`ip`),
  KEY `acp_id` (`acp_id`),
  KEY `ip` (`ip`),
  KEY `dateline` (`dateline`)
) ENGINE=InnoDB AUTO_INCREMENT=8015 DEFAULT CHARSET=utf8 COMMENT='Trusted IPs';

 */

use \sys\admin;

class auth extends instance {
	const TB_DYNAMIC_PSWD = 'acp.dynamic_pswd';
	const TB_ID_IP_AUTH = 'log.log_ip_auth';
	const TB_FAILURE = 'acp.admin_failure';
	const ACTIONS = [ 'list', 'smsLogin', 'login', 'profile', 'pswd', 'logoff', 'logo_random', 'logo_upload', 'enable_2fa', 'disable_2fa' ];

	/**
	 * Display login GUI
	 */
	protected function List() {
		$this->acp_init();

		if ( S ) j( './' );

		_header( 1 );
		$t = new t( 'login', _SYS );
		$t->assign( 'ref', _get_post( 'ref' ) );
		$t->output();
		_footer( 1 );
	}

	/**
	 * Show profile page
	 */
	protected function Profile() {
		$this->acp_init();
		if ( ! S ) b( 'No logged in info: ' . SID );

		$acp = db::s( admin::TB, [ S[ 'acp_id' ], 'active' => 1 ] );

		_header();
		$t = new t( 'login.profile', _SYS );
		$t->assign( [
			'acp' => $acp,
			'lang' => LANG,
		] );
		$t->output();
		_footer();
	}

	/**
	 * Change pswd
	 */
	protected function Pswd() {
		$this->acp_init();
		if ( ! S ) b( 'No logged in info: ' . SID );

		$acp = db::s( admin::TB, [ S[ 'acp_id' ], 'active' => 1 ] );

		$oldpswd = _post( 'oldpswd' );
		$pswd = _post( 'pswd' );

		if ( ! $acp ) b( 'No info' );
		if ( ! admin::cls()->verify_pswd( $oldpswd, $acp[ 'pswd' ] ) ) b( 'wrong old password' );
		if ( ! $pswd ) b( 'no new password' );
		if ( strlen( $pswd ) < 4 ) b( 'password needs to be longer than 4 characters' );
		$pswd_hashed = admin::cls()->hash_pswd( $pswd );

		//Update pswd
		admin::cls()->update_pswd( $acp[ 'id' ], $pswd );

		//Update cookie
		cookie( 'acp_sess', [ $acp[ 'id' ], md5( $pswd_hashed ) ], 0, '/' );
		jx();
	}

	/**
	 * Random generate a logo
	 */
	protected function Logo_random() {
		$this->acp_init();
		if ( ! S ) b( 'No logged in info: ' . SID );

		$this->avatar_random( S[ 'acp_id' ], S[ 'acp_truename' ] );

		j( '/login?action=profile&modal_parent=refresh&modal=1' );
	}

	/**
	 * Disable 2fa login
	 */
	protected function Disable_2fa() {
		$this->acp_init();
		if ( ! S ) b( 'No logged in info: ' . SID );

		db::u( admin::TB, [ 'secret_2fa' => '' ], S[ 'acp_id' ] );

		j( '/login?action=profile&modal_parent=refresh&modal=1' );
	}

	/**
	 * Enable 2fa
	 */
	protected function Enable_2fa() {
		$this->acp_init();
		if ( ! S ) b( 'No logged in info: ' . SID );

		require_once '/var/www/vendor/autoload.php';
		$authenticator = new \PHPGangsta_GoogleAuthenticator();

		if ( SUBMIT ) {
			$secret = f::read( '/tmp/2fa/' . S[ 'acp_id' ] );
			if ( ! $authenticator->verifyCode( $secret, _post( 'code' ), 1 ) ) {
				b( __( '2FA code is wrong' ) );
			}

			db::u( admin::TB, [ 'secret_2fa' => $secret ], S[ 'acp_id' ] );
			msg( __( 'Set 2FA successfully.' ) );
		}
		$secret = $authenticator->createSecret();
		f::write( '/tmp/2fa/' . S[ 'acp_id' ], $secret );

		$qrCodeUrl = $authenticator->getQRCodeGoogleUrl( _cfg( 'secret_2fa', 'title' ), $secret, _cfg( 'secret_2fa', 'website' ) );

		_header();
		$t = new t( 'login.enable_2fa', _SYS );
		$t->assign( [
			'qrCodeUrl' => $qrCodeUrl,
		] );
		$t->output();
		_footer();
	}

	/**
	 * Upload logo
	 */
	protected function Logo_upload() {
		$this->acp_init();
		if ( ! S ) b( 'No logged in info: ' . SID );

		$this->avatar_upload( S[ 'acp_id' ] );

		j( '/login?action=profile&modal_parent=refresh&modal=1' );
	}

	/**
	 * Random generate an avatar
	 */
	public function avatar_random( $id, $name ) {
		if ( ! is_dir( ROOT . 'upload/avatars/' ) ) {
			f::mmkdir( ROOT . 'upload/avatars/', 0775 );
		}

		$tmp_file = ROOT . 'upload/avatars/' . $id . '.png';

		$color1 = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
		$color2 = sprintf('#%06X', mt_rand(0, 0xFFFFFF));

		$image = new \Imagick();
		$image->newImage(50, 50, $color1);
		$draw = new \ImagickDraw();
		$draw->setFontSize( 25 );
		$draw->setFillColor( '#fff' );
		$draw->setGravity(Imagick::GRAVITY_CENTER);
		$text = substr( str_replace( ' ', '', $name ), 0, 3 );
		$image->annotateImage($draw, 0, 3, 0, ucwords( $text ) );
		$image->setImageFormat("png");
		$image->writeImage($tmp_file);
		$image->destroy();

		$data = [ 'avatar' => $id . '.png?ver=' . TS ];
		if ( defined( 'P' ) ) { // Backend menu visit, log last dateline
			$data = self::ll( $data );
		}
		db::u( admin::TB, $data, $id );
	}

	/**
	 * Upload avatar
	 */
	public function avatar_upload( $id ) {
		if ( ! is_dir( ROOT . 'upload/avatars/' ) ) {
			f::mmkdir( ROOT . 'upload/avatars/', 0775 );
		}

		// Upload tmp file for crop purpose
		if ( ACTION_STEP == 'tmp' ) {
			$data = _post( 'data' );
			$name = _post( 'name' );

			list($type, $data) = explode(';', $data);
			list(, $data)      = explode(',', $data);
			$data = base64_decode($data);
			$ext = substr( $name, strrpos( $name, '.' ) + 1 );
			$ext = strtolower( $ext );
			if ( ! in_array( $ext, [ 'png', 'jpg' ] ) ) {
				b( 'jpg/png only' );
			}
			if ( strlen( $data ) > 2000000 ) {
				b( 'Filesize max 2M' );
			}

			$tmp_file = 'upload/avatars/' . $id . '.tmp.' . $ext;
			f::write( ROOT . $tmp_file, $data );
			image::resize( ROOT . $tmp_file, 800, 450 );

			json( [ 'path' => '/' . $tmp_file ] );
		}

		if ( empty( $_FILES[ 'attach' ][ 'name' ] ) ) b( 'No File' );
		$file = $_FILES[ 'attach' ];
		$size = filesize( $file[ 'tmp_name' ] );
		if ( $size > 2000000 ) b( 'Filesize max 2M' );
		if ( ! $size ) {
			b( 'Upload error' );
		}
		$ext = substr( $file[ 'name' ], strrpos( $file[ 'name' ], '.' ) + 1 );
		$ext = strtolower( $ext );
		if ( ! in_array( $ext, [ 'png', 'jpg' ] ) ) {
			b( 'jpg/png only' );
		}

		$tmp_file = ROOT . 'upload/avatars/' . $id . '.' . $ext;
		move_uploaded_file( $file[ 'tmp_name' ], $tmp_file );

		// Crop image
		$x = _post( 'x' );
		$y = _post( 'y' );
		$w = _post( 'w' );
		$h = _post( 'h' );
		if ( $w && $h ) {
			// Resize to height=400 to match the preview window
			image::resize( $tmp_file, 800, 450 );
			image::crop( $tmp_file, $x, $y, $w, $h );
		}

		// Resize to 25x25
		image::resize( $tmp_file, 50, 50, ROOT . 'upload/avatars/' . $id . '.png' );
		if ( $ext == 'jpg' ) {
			unlink( $tmp_file );
		}

		$data = [ 'avatar' => $id . '.png?ver=' . TS ];
		if ( defined( 'P' ) ) { // Backend menu visit, log last dateline
			$data = self::ll( $data );
		}
		db::u( admin::TB, $data, $id );
	}

	/**
	 * SMS validation
	 */
	protected function SmsLogin() {
		if ( $this->_check_failure() ) {
			b( 'Please try after 5 minutes. ' );
		}

		debug( 'loginSMScheck' );
		$oaSessTmp = _cookie( 'oaSessTmp' );
		if ( empty( $oaSessTmp[ 0 ] ) ) b( 'Login info err' );

		$smscode = _post( 'smscode' );

		$acp = admin::cls()->row_by_id( $oaSessTmp[ 0 ] );
		if ( $acp[ 'secret_2fa' ] ) { // Check 2FA code
			require_once '/var/www/vendor/autoload.php';
			$authenticator = new \PHPGangsta_GoogleAuthenticator();
			$res = $authenticator->verifyCode( $acp[ 'secret_2fa' ], $smscode, 1 );
			if ( ! $res ) {
				debug( 'sms code :' . $smscode . ' - 2FA failure' );
				$this->_log_failure();
				b( __( '2FA code is wrong' ) );
			}
		}
		else { // Check mobile dynamic code
			$row = db::s( self::TB_DYNAMIC_PSWD, [ 'acp_id' => $oaSessTmp[ 0 ] ] );
			if ( $row ) {
				if ( $row[ 'valid' ] < 1 ) {
					db::d( self::TB_DYNAMIC_PSWD, $row[ 'id' ] );
				}
				else {
					db::u( self::TB_DYNAMIC_PSWD, [ 'valid=valid-1' ], $row[ 'id' ] );
				}
			}
			else {
				debug( 'Err: No row for smslogin' );
			}

			if ( ! $smscode || ! $row || $row[ 'code' ] != $smscode ) {
				debug( 'sms code :' . $smscode . ' [code in db] ' . $row[ 'code' ] );
				$this->_log_failure();
				b( 'Dynamic code error 60' );
			}
		}


		//Check pswd
		if ( ! $acp = db::s( admin::TB, [ $oaSessTmp[ 0 ], 'active' => 1 ] ) ) b( 'user error 63' );
		if ( md5( $acp[ 'pswd' ] ) != $oaSessTmp[ 1 ] ) b( 'Password is wrong 64' );

		debug( $oaSessTmp[ 0 ] . ' loginSMScheck passed' );

		if ( ! $acp[ 'secret_2fa' ] ) { // Check 2FA code
			db::d( self::TB_DYNAMIC_PSWD, $row[ 'id' ] );
		}

		//Login
		// global $_cookieDomain;
		cookie( 'acp_sess', $oaSessTmp, 86400 * 30, '/' );
		cookie( 'oaSessTmp', false, 0, '/' );

		//记录ID认证IP
		$this->_oaIpAuth( $oaSessTmp[ 0 ] );

		if ( _post( 'ref' ) && _post( 'ref' ) != '/' ) {
			j( _post( 'ref' ) );
		}
		j( '/' );
	}

	/**
	 * Login submit
	 */
	protected function Login() {
		debug( 'LoginFirstSubmit started' );
		$name = _post( 'name' );
		$pswd = _post( 'pswd' );
		if ( ! $name || ! $pswd ) b( 'empty name or password' );

		if ( ! $acp = db::s( admin::TB, [ 'or' => [ 'name' => $name, $name, 'email' => $name ], 'active' => 1 ] ) ) b( 'No this user 77' );
		// IP access validation
		if ( $acp[ 'accessip' ] && ! ipAccess( $acp[ 'accessip' ] ) ) b( 'IP forbidden: ' . IP );

		if ( $this->_check_failure() ) {
			b( 'Please try after 5 minutes. ' );
		}

		if ( ! admin::cls()->verify_pswd( $pswd, $acp[ 'pswd' ] ) ) {
			// check md5
			if ( md5( md5( $pswd ) ) == $acp[ 'pswd' ] ) {
				admin::cls()->update_pswd( $acp[ 'id' ], $pswd );
			}
			else {
				$this->_log_failure();
				b( 'Password is wrong 92' );
			}
		}

		// if ( oa::inip() ) { // Direct login
		// 	global $_cookieDomain;
		// 	cookie( 'acp_sess', array( $acp[ 'id' ], $pswd ), 86400 * 30, $_cookieDomain );
		// 	j( _post( 'ref' ) ?: './' );
		// }

		if ( strlen( $acp[ 'mobile' ] ) < 10 ) b( 'Mobile setup error' );

		// Check freq
		$last_sent = db::s( self::TB_DYNAMIC_PSWD, [ 'acp_id' => $acp[ 'id' ] ], 'id desc' );
		if ( $last_sent && TS - $last_sent[ 'dateline' ] < 15 ) {
			b( 'Please try after 15s.' );
		}

		cookie( 'oaSessTmp', [ $acp[ 'id' ], md5( $acp[ 'pswd' ] ) ], 86400, '/' );

		if ( $acp[ 'secret_2fa' ] ) {
			$tpl_data = [
				'security_type' => '2fa',
				'ref'	=> _post('ref'),
			];
		}
		else {
			$smso = s::rrand( 4, 1 );

			// Update current dynamic code
			$s = [
				'acp_id'	=> $acp[ 'id' ],
				'acp_truename'	=> $acp[ 'truename' ],
				'code'	=> $smso,
				'dateline' => TS,
				'ip'	=> IP,
				'valid'	=> 3,
			];
			$id = db::r( self::TB_DYNAMIC_PSWD, $s );
			debug( 'Inserted id ' . $id );

			if ( ! $id ) {
				b( 'save dynamic password error. Please contact admin' );
			}

			$rid = s::rrand( 2, 1 );
			$info = "Dynamic Code:$smso.(Tag:$rid) IP: " . IP . ' (text STOP to unsubscribe)';
			$sms_result = sms::twilio( $acp[ 'mobile' ], $info );
			if ( $sms_result !== 'queued' ) {
				// b( 'text sending error: ' . $sms_result ); // fix 401 err
			}

			$tpl_data = [
				'security_type' => 'mobile',
				'ref'	=> _post('ref'),
				'tag'	=> $rid,
				'mobile'	=> substr( $acp[ 'mobile' ], -4 ),
			];
		}

		_header( 1 );
		$t = new t( 'login.authcode', _SYS );
		$t->assign( $tpl_data );
		$t->output();
		_footer( 1 );
	}

	/**
	 * Log failed login IP
	 */
	private function _log_failure() {
		db::i( self::TB_FAILURE, [ 'ip' => IP, 'dateline' => TS ] );
	}

	private function _check_failure() {
		$failures = db::c( self::TB_FAILURE, [ 'ip' => IP, 'dateline' => [ '>=', TS - 300 ] ] );
		return $failures >= 5;
	}

	// 后台登录IP和ID关联
	private function _oaIpAuth( $acp_id ) {
		db::r( self::TB_ID_IP_AUTH, [ 'acp_id' => $acp_id, 'ip' => IP, 'dateline' => TS ] );
	}

	/**
	 * Check if this IP is authorized before or not
	 */
	public function is_auth_ip() {
		$log = db::s( self::TB_ID_IP_AUTH, [ 'ip' => IP ] );
		return $log;
	}

	/**
	 * admin init with cookie login info
	 */
	public function acp_init() {
		global $_S;
		//	session和cookie登录检测
		if ( ! SID ) b( 'unknown sid' );
		$_S = db::s( admin::TB_SESSION, [ 'sid' => SID ] ); //session表的acp信息
		if ( $_S ) {
			if ( TS - $_S[ 'dateline' ] > 300 ) { //清理acp session过期
				$_S = false;
				db::d( admin::TB_SESSION, [ 'dateline' => [ '<', TS - 300 ] ] );
			}
			else {//更新最后活动信息
				db::u( admin::TB_SESSION, [ 'ip' => IP , 'dateline' => TS ], [ 'sid'=> SID ] );
			}
		}

		if ( ! $_S ) { // No session
			$acpinfo = _cookie( 'acp_sess' );
			if ( $acpinfo && count( $acpinfo ) == 2 ) { // valid cookie
				$_t = db::s( admin::TB, [ 'active' => 1, 'id' => $acpinfo[ 0 ] ] );
				if ( $_t ) {
					if ( md5( $_t[ 'pswd' ] ) != $acpinfo[ 1 ] ) {
						// global $_cookieDomain;
						cookie( 'acp_sess', false, false, '/' );
						b( 'password error' );
					}

					$_S = [
						'sid'			=>	SID,
						'acp_id'		=>	$_t[ 'id' ],
						'acp_name'		=>	$_t[ 'name' ],
						'acp_truename'	=>	$_t[ 'truename' ],
						'privilege'		=>	$_t[ 'privilege' ],
						'priv_tags'		=>	$_t[ 'priv_tags' ],
						'root'			=>	$_t[ 'root' ],
						'dateline'		=>	TS,
						'ip'			=>	IP,
						'cols_disabled'	=>	$_t[ 'cols_disabled' ],
					];
					// Append extra fields if have
					if ( defined( 'EXTRA_AUTH_FIELDS' ) ) foreach ( EXTRA_AUTH_FIELDS as $v ) {
						$_S[ $v ] = $_t[ $v ];
					}
					db::r( admin::TB_SESSION, $_S );
					db::u( admin::TB, [ 'lastip' => IP, 'last_act_dateline' => TS ], $_S[ 'acp_id' ] );
				}
			}
		}

		// 初始化追加操作
		if ( $_S ) {
			$_S[ 'privilege' ] = str2arr( $_S[ 'privilege' ] );
			$_S[ 'priv_tags' ] = str2arr( $_S[ 'priv_tags' ] );
			$_S[ 'cols_disabled' ] = str2arr( $_S[ 'cols_disabled' ] );
		}

		defined( 'S' ) || define( 'S', $_S );

		global $_assign;
		$_assign[ '_S' ] = $_S;
	}

	/**
	 * Logoff
	 */
	protected function Logoff() {
		// global $_cookieDomain;
		db::d( admin::TB_SESSION, [ 'sid' => SID ] );
		cookie( 'acp_sess', false, false, '/' );
		j( '/admin' );
	}
}