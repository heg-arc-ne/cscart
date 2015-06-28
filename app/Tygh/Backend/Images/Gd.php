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

class Gd implements IBackend
{
    /**
     * Returns list of supported formats
     * @return array formats
     */    
    public static function supportedFormats()
    {
        return array(
            'png' => function_exists('imagepng'),
            'jpg' => function_exists('imagejpeg'),
            'gif' => function_exists('imagegif'),
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

        $dst = imagecreatetruecolor($dst_width, $dst_height);

        if (function_exists('imageantialias')) {
            imageantialias($dst, true);
        }

        if ($ext == 'gif') {
            $new = imagecreatefromgif($src);
        } elseif ($ext == 'jpg') {
            $new = imagecreatefromjpeg($src);
        } elseif ($ext == 'png') {
            $new = imagecreatefrompng($src);
        }

        list($r, $g, $b) = (empty($bg_color)) ? fn_parse_rgb('#ffffff') : fn_parse_rgb($bg_color);
        $c = imagecolorallocate($dst, $r, $g, $b);

        if (empty($bg_color) && ($ext == 'png' || $ext == 'gif')) {
            if (function_exists('imagecolorallocatealpha') && function_exists('imagecolortransparent') && function_exists('imagesavealpha') && function_exists('imagealphablending')) {
                $c = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                imagecolortransparent($dst, $c);
                imagesavealpha($dst, true);
                imagealphablending($dst, false);
            }
        }

        imagefilledrectangle($dst, 0, 0, $dst_width, $dst_height, $c);
        imagecopyresampled($dst, $new, $x, $y, 0, 0, $new_width, $new_height, $width, $height);

        // Free memory from image
        imagedestroy($new);

        if ($convert_to == 'original') {
            $convert_to = $ext;
        } elseif (!empty($img_functions[$convert_to])) {
            $convert_to = $convert_to;
        } else {
            $convert_to = key($img_functions);
        }

        ob_start();
        if ($convert_to == 'gif') {
            imagegif($dst);
        } elseif ($convert_to == 'jpg') {
            imagejpeg($dst, null, $jpeg_quality);
        } elseif ($convert_to == 'png') {
            imagepng($dst);
        }
        $content = ob_get_clean();
        imagedestroy($dst);    

        return array($content, $convert_to);
    }
}
