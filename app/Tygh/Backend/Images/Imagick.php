<?php
/***************************************************************************
*                                                                          *
*   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************
* PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
* "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
****************************************************************************/

namespace Tygh\Backend\Images;

class Imagick implements IBackend
{
    static private $image;

    /**
     * Returns list of supported formats
     * @return array formats
     */
    public static function supportedFormats()
    {
        if (empty(self::$image)) {
            self::$image = new \Imagick();
        }
        $formats = self::$image->queryFormats();

        return array(
            'png' => in_array('PNG', $formats),
            'jpg' => in_array('JPG', $formats),
            'gif' => in_array('GIF', $formats),
        );
    }

    /**
     * Resizes image
     * @param string $src path to image
     * @param array $params resize parameters
     * @return array content and format
     */
    public static function resize($src, $params)
    {
        $img_functions = self::supportedFormats();

        $ext = $params['ext'];
        $new_width = $params['new_width'];
        $new_height = $params['new_height'];

        $dst_width = $params['dst_width'];
        $dst_height = $params['dst_height'];

        $width = $params['width'];
        $height = $params['height'];

        $bg_color = $params['bg_color'];
        $convert_to = $params['convert_to'];

        $jpeg_quality = $params['jpeg_quality'];

        $x = $params['x'];
        $y = $params['y'];

        self::$image->readImage($src);
        self::$image->scaleImage($new_width, $new_height, true);

        if ($convert_to == 'original') {
            $convert_to = $ext;
        } elseif (!empty($img_functions[$convert_to])) {
            $convert_to = $convert_to;
        } else {
            $convert_to = key($img_functions);
        }

        $canvas = new \Imagick();
        $canvas->newImage($dst_width, $dst_height, $bg_color);

        // Convert images with CMYK colorspace to sRGB
        if (self::$image->getImageColorspace() == \Imagick::COLORSPACE_CMYK && method_exists(self::$image, 'transformimagecolorspace')) {
            self::$image->transformimagecolorspace(\Imagick::COLORSPACE_SRGB);
        }

        $canvas->compositeImage(self::$image, \Imagick::COMPOSITE_OVER, $x, $y );

        if ($convert_to == 'jpg') {
            $canvas->setImageCompressionQuality($jpeg_quality);
        }

        $canvas->setImageFormat($convert_to);
        $content = $canvas->getImageBlob();

        $canvas->clear();
        self::$image->clear();

        return array($content, $convert_to);
    }
}
