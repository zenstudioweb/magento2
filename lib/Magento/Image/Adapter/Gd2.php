<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category   Magento
 * @package    Magento_Image
 * @copyright  Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


namespace Magento\Image\Adapter;

class Gd2 extends \Magento\Image\Adapter\AbstractAdapter
{
    /**
     * Required extensions
     *
     * @var array
     */
    protected $_requiredExtensions = Array("gd");

    /**
     * Image output callbacks by type
     *
     * @var array
     */
    private static $_callbacks = array(
        IMAGETYPE_GIF  => array('output' => 'imagegif',  'create' => 'imagecreatefromgif'),
        IMAGETYPE_JPEG => array('output' => 'imagejpeg', 'create' => 'imagecreatefromjpeg'),
        IMAGETYPE_PNG  => array('output' => 'imagepng',  'create' => 'imagecreatefrompng'),
        IMAGETYPE_XBM  => array('output' => 'imagexbm',  'create' => 'imagecreatefromxbm'),
        IMAGETYPE_WBMP => array('output' => 'imagewbmp', 'create' => 'imagecreatefromxbm'),
    );

    /**
     * Whether image was resized or not
     *
     * @var bool
     */
    protected $_resized = false;

    /**
     * Open image for processing
     *
     * @param string $filename
     * @return void
     * @throws \OverflowException
     */
    public function open($filename)
    {
        $this->_fileName = $filename;
        $this->getMimeType();
        $this->_getFileAttributes();
        if ($this->_isMemoryLimitReached()) {
            throw new \OverflowException('Memory limit has been reached.');
        }
        $this->_imageHandler = call_user_func($this->_getCallback('create'), $this->_fileName);
    }

    /**
     * Checks whether memory limit is reached.
     *
     * @return bool
     */
    protected function _isMemoryLimitReached()
    {
        $limit = $this->_convertToByte(ini_get('memory_limit'));
        $requiredMemory = $this->_getImageNeedMemorySize($this->_fileName);
        return (memory_get_usage(true) + $requiredMemory) > $limit;
    }

    /**
     * Get image needed memory size
     *
     * @param string $file
     * @return float|int
     */
    protected function _getImageNeedMemorySize($file)
    {
        $imageInfo = getimagesize($file);
        if (!isset($imageInfo[0]) || !isset($imageInfo[1])) {
            return 0;
        }
        if (!isset($imageInfo['channels'])) {
            // if there is no info about this parameter lets set it for maximum
            $imageInfo['channels'] = 4;
        }
        if (!isset($imageInfo['bits'])) {
            // if there is no info about this parameter lets set it for maximum
            $imageInfo['bits'] = 8;
        }

        return round(
            ($imageInfo[0] * $imageInfo[1] * $imageInfo['bits'] * $imageInfo['channels'] / 8 + Pow(2, 16)) * 1.65
        );
    }

    /**
     * Converts memory value (e.g. 64M, 129KB) to bytes.
     * Case insensitive value might be used.
     *
     * @param string $memoryValue
     * @return int
     */
    protected function _convertToByte($memoryValue)
    {
        if (stripos($memoryValue, 'G') !== false) {
            return (int)$memoryValue * pow(1024, 3);
        } elseif (stripos($memoryValue, 'M') !== false) {
            return (int)$memoryValue * 1024 * 1024;
        } elseif (stripos($memoryValue, 'KB') !== false) {
            return (int)$memoryValue * 1024;
        }

        return (int)$memoryValue;
    }

    /**
     * Save image to specific path.
     * If some folders of path does not exist they will be created
     *
     * @param null|string $destination
     * @param null|string $newName
     * @return void
     * @throws \Exception  if destination path is not writable
     */
    public function save($destination = null, $newName = null)
    {
        $fileName = $this->_prepareDestination($destination, $newName);

        if (!$this->_resized) {
            // keep alpha transparency
            $isAlpha     = false;
            $isTrueColor = false;
            $this->_getTransparency($this->_imageHandler, $this->_fileType, $isAlpha, $isTrueColor);
            if ($isAlpha) {
                if ($isTrueColor) {
                    $newImage = imagecreatetruecolor($this->_imageSrcWidth, $this->_imageSrcHeight);
                } else {
                    $newImage = imagecreate($this->_imageSrcWidth, $this->_imageSrcHeight);
                }
                $this->_fillBackgroundColor($newImage);
                imagecopy(
                    $newImage,
                    $this->_imageHandler,
                    0,
                    0,
                    0,
                    0,
                    $this->_imageSrcWidth,
                    $this->_imageSrcHeight
                );
                $this->_imageHandler = $newImage;
            }
        }

        $functionParameters = array($this->_imageHandler, $fileName);

        $quality = $this->quality();
        if ($quality !== null) {
            if ($this->_fileType == IMAGETYPE_PNG) {
                // for PNG files quality param must be from 0 to 10
                $quality = ceil($quality/10);
                if ($quality > 10) {
                    $quality = 10;
                }
                $quality = 10 - $quality;
            }
            $functionParameters[] = $quality;
        }

        call_user_func_array($this->_getCallback('output'), $functionParameters);
    }

    /**
     * @see \Magento\Image\Adapter\AbstractAdapter::getImage
     * @return string
     */
    public function getImage()
    {
        ob_start();
        call_user_func($this->_getCallback('output'), $this->_imageHandler);
        return ob_get_clean();
    }

    /**
     * Obtain function name, basing on image type and callback type
     *
     * @param string $callbackType
     * @param null|int $fileType
     * @param string $unsupportedText
     * @return string
     * @throws \Exception
     */
    private function _getCallback($callbackType, $fileType = null, $unsupportedText = 'Unsupported image format.')
    {
        if (null === $fileType) {
            $fileType = $this->_fileType;
        }
        if (empty(self::$_callbacks[$fileType])) {
            throw new \Exception($unsupportedText);
        }
        if (empty(self::$_callbacks[$fileType][$callbackType])) {
            throw new \Exception('Callback not found.');
        }
        return self::$_callbacks[$fileType][$callbackType];
    }

    /**
     * Fill image with main background color.
     * Returns a color identifier.
     *
     * @throws \Exception
     * @param resource $imageResourceTo
     * @return int
     */
    private function _fillBackgroundColor(&$imageResourceTo)
    {
        // try to keep transparency, if any
        if ($this->_keepTransparency) {
            $isAlpha = false;
            $transparentIndex = $this->_getTransparency($this->_imageHandler, $this->_fileType, $isAlpha);
            try {
                // fill truecolor png with alpha transparency
                if ($isAlpha) {

                    if (!imagealphablending($imageResourceTo, false)) {
                        throw new \Exception('Failed to set alpha blending for PNG image.');
                    }
                    $transparentAlphaColor = imagecolorallocatealpha($imageResourceTo, 0, 0, 0, 127);
                    if (false === $transparentAlphaColor) {
                        throw new \Exception('Failed to allocate alpha transparency for PNG image.');
                    }
                    if (!imagefill($imageResourceTo, 0, 0, $transparentAlphaColor)) {
                        throw new \Exception('Failed to fill PNG image with alpha transparency.');
                    }
                    if (!imagesavealpha($imageResourceTo, true)) {
                        throw new \Exception('Failed to save alpha transparency into PNG image.');
                    }

                    return $transparentAlphaColor;
                }
                // fill image with indexed non-alpha transparency
                elseif (false !== $transparentIndex) {
                    $transparentColor = false;
                    if ($transparentIndex >=0 && $transparentIndex <= imagecolorstotal($this->_imageHandler)) {
                        list($r, $g, $b)  = array_values(imagecolorsforindex($this->_imageHandler, $transparentIndex));
                        $transparentColor = imagecolorallocate($imageResourceTo, $r, $g, $b);
                    }
                    if (false === $transparentColor) {
                        throw new \Exception('Failed to allocate transparent color for image.');
                    }
                    if (!imagefill($imageResourceTo, 0, 0, $transparentColor)) {
                        throw new \Exception('Failed to fill image with transparency.');
                    }
                    imagecolortransparent($imageResourceTo, $transparentColor);
                    return $transparentColor;
                }
            }
            catch (\Exception $e) {
                // fallback to default background color
            }
        }
        list($r, $g, $b) = $this->_backgroundColor;
        $color = imagecolorallocate($imageResourceTo, $r, $g, $b);
        if (!imagefill($imageResourceTo, 0, 0, $color)) {
            throw new \Exception("Failed to fill image background with color {$r} {$g} {$b}.");
        }

        return $color;
    }

    /**
     * Gives true for a PNG with alpha, false otherwise
     *
     * @param string $fileName
     * @return boolean
     */
    public function checkAlpha($fileName)
    {
        return ((ord(file_get_contents($fileName, false, null, 25, 1)) & 6) & 4) == 4;
    }

    /**
     * Checks if image has alpha transparency
     *
     * @param resource $imageResource
     * @param int $fileType one of the constants IMAGETYPE_*
     * @param bool $isAlpha
     * @param bool $isTrueColor
     * @return boolean
     */
    private function _getTransparency($imageResource, $fileType, &$isAlpha = false, &$isTrueColor = false)
    {
        $isAlpha     = false;
        $isTrueColor = false;
        // assume that transparency is supported by gif/png only
        if (IMAGETYPE_GIF === $fileType || IMAGETYPE_PNG === $fileType) {
            // check for specific transparent color
            $transparentIndex = imagecolortransparent($imageResource);
            if ($transparentIndex >= 0) {
                return $transparentIndex;
            }
            // assume that truecolor PNG has transparency
            elseif (IMAGETYPE_PNG === $fileType) {
                $isAlpha     = $this->checkAlpha($this->_fileName);
                $isTrueColor = true;
                return $transparentIndex; // -1
            }
        }
        if (IMAGETYPE_JPEG === $fileType) {
            $isTrueColor = true;
        }
        return false;
    }

    /**
     * Change the image size
     *
     * @param null|int $frameWidth
     * @param null|int $frameHeight
     * @return void
     */
    public function resize($frameWidth = null, $frameHeight = null)
    {
        $dims = $this->_adaptResizeValues($frameWidth, $frameHeight);

        // create new image
        $isAlpha     = false;
        $isTrueColor = false;
        $this->_getTransparency($this->_imageHandler, $this->_fileType, $isAlpha, $isTrueColor);
        if ($isTrueColor) {
            $newImage = imagecreatetruecolor($dims['frame']['width'], $dims['frame']['height']);
        } else {
            $newImage = imagecreate($dims['frame']['width'], $dims['frame']['height']);
        }

        // fill new image with required color
        $this->_fillBackgroundColor($newImage);

        if ($this->_imageHandler) {
            // resample source image and copy it into new frame
            imagecopyresampled(
                $newImage,
                $this->_imageHandler,
                $dims['dst']['x'], $dims['dst']['y'],
                $dims['src']['x'], $dims['src']['y'],
                $dims['dst']['width'], $dims['dst']['height'],
                $this->_imageSrcWidth, $this->_imageSrcHeight
            );
        }
        $this->_imageHandler = $newImage;
        $this->refreshImageDimensions();
        $this->_resized = true;
    }

    /**
     * Rotate image on specific angle
     *
     * @param int $angle
     * @return void
     */
    public function rotate($angle)
    {
        $this->_imageHandler = imagerotate($this->_imageHandler, $angle, $this->imageBackgroundColor);
        $this->refreshImageDimensions();
    }

    /**
     * Add watermark to image
     *
     * @param string $imagePath
     * @param int $positionX
     * @param int $positionY
     * @param int $opacity
     * @param bool $tile
     * @return void
     */
    public function watermark($imagePath, $positionX = 0, $positionY = 0, $opacity = 30, $tile = false)
    {
        list($watermarkSrcWidth, $watermarkSrcHeight, $watermarkFileType, ) = $this->_getImageOptions($imagePath);
        $this->_getFileAttributes();
        $watermark = call_user_func($this->_getCallback(
            'create',
            $watermarkFileType,
            'Unsupported watermark image format.'
        ), $imagePath);

        $merged = false;

        if ($this->getWatermarkWidth() &&
            $this->getWatermarkHeight() &&
            ($this->getWatermarkPosition() != self::POSITION_STRETCH)
        ) {
            $newWatermark = imagecreatetruecolor($this->getWatermarkWidth(), $this->getWatermarkHeight());
            imagealphablending($newWatermark, false);
            $col = imagecolorallocate($newWatermark, 255, 255, 255);
            imagecolortransparent($newWatermark, $col);
            imagefilledrectangle($newWatermark, 0, 0, $this->getWatermarkWidth(), $this->getWatermarkHeight(), $col);
            imagealphablending($newWatermark, true);
            imageSaveAlpha($newWatermark, true);
            imagecopyresampled(
                $newWatermark,
                $watermark,
                0, 0, 0, 0,
                $this->getWatermarkWidth(), $this->getWatermarkHeight(),
                imagesx($watermark), imagesy($watermark)
            );
            $watermark = $newWatermark;
        }

        if( $this->getWatermarkPosition() == self::POSITION_TILE ) {
            $tile = true;
        } elseif( $this->getWatermarkPosition() == self::POSITION_STRETCH ) {

            $newWatermark = imagecreatetruecolor($this->_imageSrcWidth, $this->_imageSrcHeight);
            imagealphablending($newWatermark, false);
            $col = imagecolorallocate($newWatermark, 255, 255, 255);
            imagecolortransparent($newWatermark, $col);
            imagefilledrectangle($newWatermark, 0, 0, $this->_imageSrcWidth, $this->_imageSrcHeight, $col);
            imagealphablending($newWatermark, true);
            imageSaveAlpha($newWatermark, true);
            imagecopyresampled(
                $newWatermark,
                $watermark,
                0, 0, 0, 0,
                $this->_imageSrcWidth, $this->_imageSrcHeight,
                imagesx($watermark), imagesy($watermark)
            );
            $watermark = $newWatermark;

        } elseif( $this->getWatermarkPosition() == self::POSITION_CENTER ) {
            $positionX = ($this->_imageSrcWidth/2 - imagesx($watermark)/2);
            $positionY = ($this->_imageSrcHeight/2 - imagesy($watermark)/2);
            imagecopymerge(
                $this->_imageHandler,
                $watermark,
                $positionX, $positionY,
                0, 0,
                imagesx($watermark), imagesy($watermark),
                $this->getWatermarkImageOpacity()
            );
        } elseif( $this->getWatermarkPosition() == self::POSITION_TOP_RIGHT ) {
            $positionX = ($this->_imageSrcWidth - imagesx($watermark));
            imagecopymerge(
                $this->_imageHandler,
                $watermark,
                $positionX, $positionY,
                0, 0,
                imagesx($watermark), imagesy($watermark),
                $this->getWatermarkImageOpacity()
            );
        } elseif( $this->getWatermarkPosition() == self::POSITION_TOP_LEFT  ) {
            imagecopymerge(
                $this->_imageHandler,
                $watermark,
                $positionX, $positionY,
                0, 0,
                imagesx($watermark), imagesy($watermark),
                $this->getWatermarkImageOpacity()
            );
        } elseif( $this->getWatermarkPosition() == self::POSITION_BOTTOM_RIGHT ) {
            $positionX = ($this->_imageSrcWidth - imagesx($watermark));
            $positionY = ($this->_imageSrcHeight - imagesy($watermark));
            imagecopymerge(
                $this->_imageHandler,
                $watermark,
                $positionX, $positionY,
                0, 0,
                imagesx($watermark), imagesy($watermark),
                $this->getWatermarkImageOpacity()
            );
        } elseif( $this->getWatermarkPosition() == self::POSITION_BOTTOM_LEFT ) {
            $positionY = ($this->_imageSrcHeight - imagesy($watermark));
            imagecopymerge(
                $this->_imageHandler,
                $watermark,
                $positionX, $positionY,
                0, 0,
                imagesx($watermark), imagesy($watermark),
                $this->getWatermarkImageOpacity()
            );
        }

        if( $tile === false && $merged === false ) {
            imagecopymerge(
                $this->_imageHandler,
                $watermark,
                $positionX, $positionY,
                0, 0,
                imagesx($watermark), imagesy($watermark),
                $this->getWatermarkImageOpacity()
            );
        } else {
            $offsetX = $positionX;
            $offsetY = $positionY;
            while( $offsetY <= ($this->_imageSrcHeight+imagesy($watermark)) ) {
                while( $offsetX <= ($this->_imageSrcWidth+imagesx($watermark)) ) {
                    imagecopymerge(
                        $this->_imageHandler,
                        $watermark,
                        $offsetX, $offsetY,
                        0, 0,
                        imagesx($watermark), imagesy($watermark),
                        $this->getWatermarkImageOpacity()
                    );
                    $offsetX += imagesx($watermark);
                }
                $offsetX = $positionX;
                $offsetY += imagesy($watermark);
            }
        }

        imagedestroy($watermark);
        $this->refreshImageDimensions();
    }

    /**
     * Crop image
     *
     * @param int $top
     * @param int $left
     * @param int $right
     * @param int $bottom
     * @return bool
     */
    public function crop($top = 0, $left = 0, $right = 0, $bottom = 0)
    {
        if( $left == 0 && $top == 0 && $right == 0 && $bottom == 0 ) {
            return false;
        }

        $newWidth = $this->_imageSrcWidth - $left - $right;
        $newHeight = $this->_imageSrcHeight - $top - $bottom;

        $canvas = imagecreatetruecolor($newWidth, $newHeight);

        if ($this->_fileType == IMAGETYPE_PNG) {
            $this->_saveAlpha($canvas);
        }

        imagecopyresampled(
            $canvas,
            $this->_imageHandler,
            0, 0, $left, $top,
            $newWidth, $newHeight,
            $newWidth, $newHeight
        );

        $this->_imageHandler = $canvas;
        $this->refreshImageDimensions();
        return true;
    }

    /**
     * Checks required dependencies
     *
     * @return void
     * @throws \Exception if some of dependencies are missing
     */
    public function checkDependencies()
    {
        foreach( $this->_requiredExtensions as $value ) {
            if( !extension_loaded($value) ) {
                throw new \Exception("Required PHP extension '{$value}' was not loaded.");
            }
        }
    }

    /**
     * Reassign image dimensions
     */
    public function refreshImageDimensions()
    {
        $this->_imageSrcWidth = imagesx($this->_imageHandler);
        $this->_imageSrcHeight = imagesy($this->_imageHandler);
    }

    /**
     * Standard destructor. Destroy stored information about image
     */
    public function __destruct()
    {
        if (is_resource($this->_imageHandler)) {
            imagedestroy($this->_imageHandler);
        }
    }

    /*
     * Fixes saving PNG alpha channel
     *
     * @param resource $imageHandler
     */
    private function _saveAlpha($imageHandler)
    {
        $background = imagecolorallocate($imageHandler, 0, 0, 0);
        ImageColorTransparent($imageHandler, $background);
        imagealphablending($imageHandler, false);
        imagesavealpha($imageHandler, true);
    }

    /**
     * Returns rgba array of the specified pixel
     *
     * @param int $x
     * @param int $y
     * @return array
     */
    public function getColorAt($x, $y)
    {
        $colorIndex = imagecolorat($this->_imageHandler, $x, $y);
        return imagecolorsforindex($this->_imageHandler, $colorIndex);
    }

    /**
     * Create Image from string
     *
     * @param string $text
     * @param string $font
     * @return \Magento\Image\Adapter\AbstractAdapter
     */
    public function createPngFromString($text, $font = '')
    {
        $error = false;
        $this->_resized = true;
        try {
            $this->_createImageFromTtfText($text, $font);
        } catch (\Exception $e) {
            $error = true;
        }

        if ($error || empty($this->_imageHandler)) {
            $this->_createImageFromText($text);
        }

        return $this;
    }

    /**
     * Create Image using standard font
     *
     * @param $text
     */
    protected function _createImageFromText($text)
    {
        $width = imagefontwidth($this->_fontSize) * strlen($text);
        $height = imagefontheight($this->_fontSize);

        $this->_createEmptyImage($width, $height);

        $black = imagecolorallocate($this->_imageHandler, 0, 0, 0);
        imagestring($this->_imageHandler, $this->_fontSize, 0, 0, $text, $black);
    }

    /**
     * Create Image using ttf font
     * Note: This function requires both the GD library and the FreeType library
     *
     * @param string $text
     * @param string $font
     * @throws \Exception
     */
    protected function _createImageFromTtfText($text, $font)
    {
        $boundingBox = imagettfbbox($this->_fontSize, 0, $font, $text);
        $width = abs($boundingBox[4]) + abs($boundingBox[0]);
        $height = abs($boundingBox[5]) + abs($boundingBox[1]);

        $this->_createEmptyImage($width, $height);

        $black = imagecolorallocate($this->_imageHandler, 0, 0, 0);
        $result = imagettftext($this->_imageHandler, $this->_fontSize, 0, 0, $height - abs($boundingBox[1]),
            $black, $font, $text);
        if ($result === false) {
            throw new \Exception('Unable to create TTF text');
        }
    }

    /**
     * Create empty image with transparent background
     *
     * @param $width
     * @param $height
     */
    protected function _createEmptyImage($width, $height)
    {
        $this->_fileType = IMAGETYPE_PNG;
        $image = imagecreatetruecolor($width, $height);
        $colorWhite = imagecolorallocatealpha($image, 255, 255, 255, 127);

        imagealphablending($image, true);
        imagesavealpha($image, true);

        imagefill($image, 0, 0, $colorWhite);
        $this->_imageHandler = $image;
    }
}
