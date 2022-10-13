<?php
/**
 *	图片处理函数库
 *
 *	@author Hai
 */
if(!defined('IN')) exit('Access Denied');

class image
{

	// pdf轉png
	public static function pdf2png( $file, $target, $dpi = 200 )
	{
		$im = new imagick();
		$im->setResolution($dpi, $dpi);
		$im->readImage($file.'[0]');
		// $im->setImageFormat('png');
		$im->writeImage($target);
	}

	/**
	 * 剪裁图片
	 * @param  [string]  $srcFile [description]
	 * @param  [type]  $x       [description]
	 * @param  [type]  $y       [description]
	 * @param  [type]  $w       [description]
	 * @param  [type]  $h       [description]
	 * @param  [int]  $toW     [目标存储宽度]
	 * @param  [int]  $toH     [description]
	 * @param  [string] $newFile [description]
	 * @return void
	 */
	public static function crop($srcFile, $x, $y, $w, $h, $toW = false, $toH = false, $newFile = false){
		if(!$toW) $toW = $w;
		if(!$toH) $toH = $h;
		if(!$newFile) $newFile = $srcFile;
		$img = new Imagick($srcFile);
		$img->cropImage($w, $h, $x, $y);
		$img->adaptiveResizeImage($toW, $toH);
		$img->writeImage($newFile);
		$img->destroy();
	}
	/**
	 *	图片后缀名
	 */
	public static function suf($pic){
		if(!file_exists($pic)) return false;
		$suf = @exif_imagetype($pic);
		if($suf == 1) Return 'gif';
		if($suf == 2) Return 'jpg';
		if($suf == 3) Return 'png';
		Return false;
	}


	/** 图片加水印 (图片水印)
	 * @param string $pic		 // 原图片
	 * @param string $logoFile	// 水印图片地址
	 * @return boolean
	 */
	public static function logo( $pic, $logoFile = false, $center_pos = false ) {
		if(!$logoFile) $logoFile = ROOT.'inc/watermark.png';
		//非jpg jpeg gif文件直接跳过
		$file_ext = self::suf($pic);
		if(!in_array($file_ext, array('gif', 'jpg'))) Return false;
		if($file_ext == 'gif' && file_exists(ROOT.'inc/watermark.gif')) $logoFile = ROOT.'inc/watermark.gif';

		//文件名規範化 备份原文件
		// copy($pic, "$pic.bak");

		list($width, $height) = getImageSize($pic);
		if($file_ext != 'gif' || !defined('_LOGO')) {
			list($w, $h) = getImageSize($logoFile);

			if ( $center_pos ) {
				$posX = ( $width - $w ) / 2;
				$posY = ( $height - $h ) / 2;
				// debug( "logo [image w&h] $width x $height [logo w&h] $w x $h [posX] $posX [posY] $posY" );
			}
			else {
				$posX = $width - $w - 8;
				$posY = $height - $h - 7;
			}
		}

		if($file_ext == 'gif') {
			// 如果定义过水印文字, 就向gif图片中加文字水印
			if(defined('_LOGO')) {
				set_time_limit(300);
				$water_text = _LOGO;
				$canvas = new Imagick();
				$image  = new Imagick($pic);
				// 水印字处理
				$draw   = self::text_draw($image, $water_text , $width, $height);
				if ($draw) {
					// gif动画图片加水印文字
					$unitl = $image->getNumberImages();
					for ($i=0; $i<$unitl; $i++) {
						$image->setIteratorIndex($i);
						$img = new Imagick();
						$img->readImageBlob($image);
						$delay = $img->getImageDelay();
						$img->annotateImage($draw, 0, 0, 0, $water_text);
						$canvas->addImage($img);
						$row = $canvas->setImageDelay( $delay );
					}
					f::write($pic, $canvas->getimagesblob(), false);
					$image->destroy();
					$canvas->destroy();
					$draw->destroy();
					$img->destroy();
				}
				return true;
			} else {
				// gif动画图片加水印图片
				$draw = new ImagickDraw();
				$watermark = new Imagick($logoFile);
				$draw->composite($watermark->getImageCompose(), $posX, $posY, $watermark->getImageWidth(), $watermark->getimageheight(), $watermark);
				$image = new Imagick($pic);
				$canvas = new Imagick();
				$images = $image->coalesceImages();
				foreach($image as $frame) {
					$img = new Imagick();
					$img->readImageBlob($frame);
					$img->drawImage($draw);

					$canvas->addImage( $img );
					$canvas->setImageDelay( $img->getImageDelay() );
				}
				f::write($pic, $canvas->getimagesblob());
				//$canvas->writeImages($pic, true);
				$image->destroy();
				$canvas->destroy();
				$draw->destroy();
				$img->destroy();
			}
		} else {
			$im = ImageCreateFromJPEG($pic);
			$logo = ImageCreateFromPNG($logoFile);

			ImageCopy($im, $logo, $posX, $posY, 0, 0, $w, $h);
			ImageJPEG($im, $pic);
			ImageDestroy($im);
		}
	}


	/** 水印字处理
	 * @param object  $image		// 原图片的imagick的对象
	 * @param string  $water_text   // 要水印的字
	 * @param int	 $width		// 原图片的宽
	 * @param int	 $height	   // 原图片的高
	 * @return multitype:|ImagickDraw
	 */
	protected static function text_draw($image, $water_text , $width, $height) {
		$font = _SYS.'chinese.ttf'; // 字符集
		$font_size = 13;		 // 文字大小
		$fill_color = "#CCCCCC"; // 文字颜色
		$alpha = 0.9;			// 透明度

		$draw = new ImagickDraw();
		$draw->setFont($font);			// 设置字符集
		$draw->setFontSize($font_size);   // 设置字体大小
		//$draw->setFillAlpha($alpha);	  // 设置透明度
		$draw->setFillColor(new ImagickPixel($fill_color)); // 设置字体颜色
		//设置水印位置 （现在的9号位，如想改变要传其他的预定义常量）
		$draw->setGravity(Imagick::GRAVITY_SOUTHEAST);

		$metrics = $image->queryFontMetrics($draw, $water_text);	 // 获得设置后字的各种信息
		$w = ceil($metrics['textWidth']);
		$h = ceil($metrics['textHeight']);
		//如果背景图的高宽小于水印图的高宽则不加水印
		if($width < ($w + 8) || $height < ($h +7) ) {
			return array();
		}else {
			return $draw;
		}
	}

	public static function rotate($file, $direction = false){
		//$direction false默认为顺时针转
		$thumb = new Imagick($file);
		$orientation = $thumb->getImageOrientation();
		$rotated = false;
		$thumb->rotateimage("#000", $direction ? -90 : 90); // rotate 90 degrees CW
		$thumb->setImageOrientation(imagick::ORIENTATION_TOPLEFT);
		$thumb->writeImage($file);
		$thumb->destroy();
	}


	/**
	 *	Resize image
	 *
	 */
	public static function resize( $srcfile, $newWidth, $newHeight, $newfile = '' ) {
		if ( ! $newfile ) { // New filename
			$newfile = $srcfile;
			// $newfile = substr($srcfile, 0, strrpos($srcfile, '.')).".thumbnail.jpg";
		}
		else {
			// if(substr($newfile, -4, 1) != '.') b('error file type:'.$newfile);
		}
		// 先把源文件正过来
		$thumb = new Imagick($srcfile);
		$orientation = $thumb->getImageOrientation();
		$rotated = false;
		switch($orientation) {
			case imagick::ORIENTATION_BOTTOMRIGHT:
				$thumb->rotateimage("#000", 180); // rotate 180 degrees
				break;

			case imagick::ORIENTATION_RIGHTTOP:
				$rotated = true;
				$thumb->rotateimage("#000", 90); // rotate 90 degrees CW
				break;

			case imagick::ORIENTATION_LEFTBOTTOM:
				$rotated = true;
				$thumb->rotateimage("#000", -90); // rotate 90 degrees CCW
				break;
		}
		// Now that it's auto-rotated, make sure the EXIF data is correct in case the EXIF gets saved with the image!
		$thumb->setImageOrientation(imagick::ORIENTATION_TOPLEFT);

		if($rotated){
			list($srcH, $srcW) = getimagesize($srcfile);
		}else{
			list($srcW, $srcH) = getimagesize($srcfile);
		}

		$newWH = $srcWH = $srcW / $srcH;
		if($newHeight) $newWH = $newWidth / $newHeight;
		try{
			if($srcW > $newWidth || ($newHeight && $srcH > $newHeight)){
				if($newWH <= $srcWH){
					$ftoW = $newWidth;
					$ftoH = $ftoW * ($srcH / $srcW);
				}else{
					$ftoH = $newHeight;
					$ftoW = $ftoH * ($srcW / $srcH);
				}

				$thumb->resizeImage($ftoW, $ftoH, substr($srcfile, -3) == 'gif' ? Imagick::FILTER_CUBIC : imagick::FILTER_BOX, 1);
				$thumb->setCompression(Imagick::COMPRESSION_JPEG);
				$thumb->setCompressionQuality(50);
				$thumb->writeImage($newfile);
				$thumb->destroy();
				Return array($ftoW, $ftoH);
			}else{

				if(substr($srcfile, -3) == 'gif') $thumb->resizeImage($srcW, $srcH, Imagick::FILTER_CUBIC, 1);
				//$thumb->setCompression(Imagick::COMPRESSION_JPEG);
				//$thumb->setCompressionQuality(50);
				$thumb->writeImage($newfile);
				$thumb->destroy();
				Return array($srcW, $srcH);
			}
		}catch( \ImagickException $e ){
			debug( 'Failed to resize ' . $e->getMessage() );
			$srcfile = _SYS.'404.png';
			$thumb = new Imagick($srcfile);
			$thumb->resizeImage($newWidth, $newHeight, Imagick::FILTER_CUBIC, 1);
			$thumb->writeImage($newfile);
			$thumb->destroy();
			Return array($newWidth, $newHeight);
		}
	}

}