<?php
// File: inc/r2/class-r2-rewriter.php

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class Tuancele_R2_Rewriter {

    private $client;
    private $options;
    private $r2_baseurl = '';
    private $upload_baseurl = '';

    public function __construct() {
        $this->client = Tuancele_R2_Client::get_instance();
        $this->options = $this->client->get_options();
        
        if (!empty($this->options['public_url'])) {
            $this->r2_baseurl = rtrim($this->options['public_url'], '/');
            $this->upload_baseurl = wp_upload_dir()['baseurl'];
        }
    }

    public function rewrite_attachment_url($url, $attachment_id) {
        if (!$this->should_rewrite($attachment_id)) {
            return $url;
        }

        $metadata = wp_get_attachment_metadata($attachment_id);

        // Ưu tiên WebP cho ảnh gốc nếu được bật
        if ($this->client->is_webp_enabled() && isset($metadata['webp_original'])) {
            $original_filename = basename($metadata['file']);
            $webp_url = str_replace($original_filename, $metadata['webp_original'], $url);
            return str_replace($this->upload_baseurl, $this->r2_baseurl, $webp_url);
        }

        return str_replace($this->upload_baseurl, $this->r2_baseurl, $url);
    }
    
    public function rewrite_image_src($image, $attachment_id) {
        if (!$image || !$this->should_rewrite($attachment_id)) {
            return $image;
        }
        
        $image_url = $image[0];
        
        // Ưu tiên WebP cho ảnh con (thumbnail)
        if ($this->client->is_webp_enabled()) {
            $metadata = wp_get_attachment_metadata($attachment_id);
            $file_name = basename($image_url);
            
            if (isset($metadata['sizes']) && is_array($metadata['sizes'])) {
                foreach ($metadata['sizes'] as $size) {
                    if (isset($size['file']) && $size['file'] === $file_name && isset($size['file_webp'])) {
                        $image_url = str_replace($file_name, $size['file_webp'], $image_url);
                        break;
                    }
                }
            }
        }
        
        $image[0] = str_replace($this->upload_baseurl, $this->r2_baseurl, $image_url);
        return $image;
    }

    public function rewrite_srcset($sources, $size_array, $image_src, $image_meta, $attachment_id) {
        if (!$this->should_rewrite($attachment_id)) {
            return $sources;
        }

        $is_webp_enabled = $this->client->is_webp_enabled();
        $metadata = wp_get_attachment_metadata($attachment_id);

        foreach ($sources as &$source) {
            $source_url = $source['url'];
            $file_name = basename($source_url);

            // Ưu tiên thay đổi URL sang WebP nếu có
            if ($is_webp_enabled) {
                $webp_found = false;
                // Ảnh gốc (full size)
                if (isset($metadata['file']) && basename($metadata['file']) === $file_name && isset($metadata['webp_original'])) {
                     $source_url = str_replace($file_name, $metadata['webp_original'], $source_url);
                     $webp_found = true;
                }
                // Ảnh con (sizes)
                if (!$webp_found && isset($metadata['sizes'])) {
                    foreach($metadata['sizes'] as $size) {
                        if (isset($size['file']) && $size['file'] === $file_name && isset($size['file_webp'])) {
                            $source_url = str_replace($file_name, $size['file_webp'], $source_url);
                            break;
                        }
                    }
                }
            }
            
            $source['url'] = str_replace($this->upload_baseurl, $this->r2_baseurl, $source_url);
        }
        return $sources;
    }
    
    private function should_rewrite($attachment_id) {
        return $this->client->is_enabled() && !empty($this->r2_baseurl) && get_post_meta($attachment_id, '_tuancele_r2_offloaded', true);
    }
}