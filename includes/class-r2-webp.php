<?php
// File: inc/r2/class-r2-webp.php

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Tuancele_R2_WebP {

    public static function convert($source_path) {
        if (!extension_loaded('gd') && !extension_loaded('imagick')) {
            error_log('WebP conversion failed: Neither GD nor Imagick extension is available.');
            return false;
        }

        $file_info = pathinfo($source_path);
        if (!isset($file_info['extension']) || !in_array(strtolower($file_info['extension']), ['jpg', 'jpeg', 'png'])) {
            return false;
        }

        $destination_path = $file_info['dirname'] . '/' . $file_info['filename'] . '.webp';

        if (extension_loaded('imagick') && class_exists('Imagick')) {
            try {
                $imagick = new Imagick($source_path);
                $imagick->setImageFormat('webp');
                $imagick->setOption('webp:lossless', 'true');
                if ($imagick->writeImage($destination_path)) {
                    return $destination_path;
                }
            } catch (Exception $e) {
                error_log('WebP Imagick failed for ' . basename($source_path) . ': ' . $e->getMessage());
            }
        }
        
        if (extension_loaded('gd')) {
            $image = false;
            if (strtolower($file_info['extension']) === 'png') {
                $image = @imagecreatefrompng($source_path);
            } else {
                $image = @imagecreatefromjpeg($source_path);
            }

            if ($image) {
                // Bật alpha blending cho ảnh PNG trong suốt
                if (strtolower($file_info['extension']) === 'png') {
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
                
                if (imagewebp($image, $destination_path, 80)) {
                    imagedestroy($image);
                    return $destination_path;
                }
                imagedestroy($image);
            }
        }
        return false;
    }
}