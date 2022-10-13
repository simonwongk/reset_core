<?php
defined( 'IN' ) || exit( 'Access Denied' );

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require _SYS . 'mailer/Exception.php';
require _SYS . 'mailer/PHPMailer.php';
require _SYS . 'mailer/SMTP.php';

class mailer {
	const HOST = 'mail.ntdtv.com';
	const PORT = 994;

	/**
	 * Send email
	 * @param $sender = [ email, name, pswd ]
	 */
	public static function send( $receiver, $title, $content, $attach_files = false, $replyto = false, $cc = false, $sender = false ) {
		$_sender = _cfg( 'mailer' );
		if ( ! empty( $sender[ 'email'] ) ) {
			$_sender[ 'email' ] = $sender[ 'email' ];
		}
		if ( ! empty( $sender[ 'name'] ) ) {
			$_sender[ 'name' ] = $sender[ 'name' ];
		}
		if ( ! empty( $sender[ 'pswd'] ) ) {
			$_sender[ 'pswd' ] = $sender[ 'pswd' ];
		}

		$mail = new PHPMailer( true );
		try {
			//Server settings
			$mail->SMTPDebug = SMTP::DEBUG_OFF; // Enable verbose debug output
			$mail->isSMTP(); // Send using SMTP
			$mail->Host = self::HOST; // Set the SMTP server to send through
			$mail->SMTPAuth = true; // Enable SMTP authentication
			$mail->Timeout 	= 10;
			$mail->Username   = $_sender[ 'email' ]; // SMTP username
			$mail->Password   = $_sender[ 'pswd' ]; // SMTP password
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
			$mail->Port       = self::PORT; // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above
			$mail->CharSet = 'UTF-8';

			//Recipients
			if ( strpos( $_sender[ 'email' ], 'epochtimes.com' ) ) {
				$mail->Host = 'mail.epochtimes.com';
			}
			$mail->setFrom( $_sender[ 'email' ], $_sender[ 'name' ] );
			// $mail->addBCC( $_sender[ 'email' ], $_sender[ 'name' ] );

			foreach ( $receiver as $v ) {
				$receiver_name = '';
				if ( is_array( $v ) ) {
					list( $v, $receiver_name ) = $v;
				}
				$mail->addAddress( $v, $receiver_name ); // Add a recipient
			}

			if ( ! $replyto && ! empty( S[ 'acp_id' ] ) ) {
				$admin = \sys\admin::cls()->row_by_id( S[ 'acp_id' ] );
				if ( ! empty( $admin[ 'email' ] ) ) {
					$replyto = [ $admin[ 'email' ], $admin[ 'truename' ] ];
				}
			}
			if ( $replyto ) {
				if ( ! is_array( $replyto[ 0 ] ) ) {
					$replyto = [ $replyto ];
				}
				foreach ( $replyto as $v ) {
					$mail->addReplyTo( $v[ 0 ], $v[ 1 ] );
					// $mail->addBCC( $v[ 0 ], $v[ 1 ] );
				}
			}

			if ( $cc ) {
				foreach ( $cc as $v ) {
					$mail->addCC( $v[ 0 ], $v[ 1 ] );
				}
			}

			// Attachments
			if ( $attach_files ) {
				if ( ! is_array( $attach_files ) ) {
					$attach_files = [ $attach_files ];
				}
				foreach ( $attach_files as $v ) {
					if ( is_array( $v ) ) {
						$mail->addAttachment( $v[ 0 ], $v[ 1 ] ); // Add attachments
					}
					else {
						$mail->addAttachment( $v ); // Add attachments
					}
				}
			}
			// $mail->addAttachment('/tmp/image.jpg', 'new.jpg');    // Optional name

			// Content
			$mail->isHTML( true ); // Set email format to HTML
			$mail->Subject = $title;
			$mail->Body    = $content;
			$mail->AltBody = strip_tags( $content );

			$mail->send();

			return 'ok';
		} catch ( Exception $e ) {
			return 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
		}
	}

	/**
	 * Send calendar
	 */
	public static function send_calendar( $receiver, $title, $content, $calendar, $replyto ) {
		$receiver_name = '';
		if ( is_array( $receiver ) ) {
			list( $receiver, $receiver_name ) = $receiver;
		}

		$mail = new PHPMailer( true );
		try {
			//Server settings
			$mail->SMTPDebug = SMTP::DEBUG_OFF; // Enable verbose debug output
			$mail->isSMTP(); // Send using SMTP
			$mail->Host = self::HOST; // Set the SMTP server to send through
			$mail->SMTPAuth = true; // Enable SMTP authentication
			$mail->Timeout 	= 10;
			$mail->Username   = _cfg( 'mailer', 'email' ); // SMTP username
			$mail->Password   = _cfg( 'mailer', 'pswd' ); // SMTP password
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Enable TLS encryption; `PHPMailer::ENCRYPTION_SMTPS` encouraged
			$mail->Port       = self::PORT; // TCP port to connect to, use 465 for `PHPMailer::ENCRYPTION_SMTPS` above

			//Recipients
			$mail->setFrom( _cfg( 'mailer', 'email' ), _cfg( 'mailer', 'name' ) );
			// $mail->addBCC( _cfg( 'mailer', 'email' ), _cfg( 'mailer', 'name' ) );
			$mail->addAddress( $receiver, $receiver_name ); // Add a recipient
			$mail->addBCC( 'test@5ea.com', 'Hai' ); // Add a recipient

			if ( $replyto ) {
				$mail->addReplyTo( $replyto[ 0 ], $replyto[ 1 ] );
				$mail->addBCC( $replyto[ 0 ], $replyto[ 1 ] );
			}

			// Content
			$mail->isHTML( true ); // Set email format to HTML
			$mail->Subject = $title;
			$mail->Body    = $content;
			$mail->AltBody = $calendar;
			$mail->Ical    = $calendar;

			$mail->send();

			return 'ok';
		} catch ( Exception $e ) {
			return 'Message could not be sent. Mailer Error: ' . $mail->ErrorInfo;
		}
	}
}