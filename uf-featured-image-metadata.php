<?php
/**
 * Plugin Name: UF Featured Image Metadata Injector
 * Description: Automatically injects Title, Caption, and Description from the Media Library into the featured image <img> tag on single post pages.
 * Version: 1.0
 * Author: Peter Mosier
 * License: GPLv2 or later
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('post_thumbnail_html', 'uf_metadata_injector_add_attributes', 10, 5);

/**
 * Inject metadata (title, caption, description) into the featured image <img> tag
 *
 * @param string $html                The HTML for the post thumbnail.
 * @param int    $post_id             The post ID.
 * @param int    $post_thumbnail_id   The thumbnail (attachment) ID.
 * @param string|array $size          The size requested.
 * @param string|array $attr          Attributes passed to get_the_post_thumbnail().
 *
 * @return string
 */
function uf_metadata_injector_add_attributes($html, $post_id, $post_thumbnail_id, $size, $attr)
{
    // Only run on front-end single post pages
    if (is_admin() || wp_doing_ajax()) {
        return $html;
    }
    if (!is_singular('post')) {
        return $html;
    }
    if (!$post_thumbnail_id || empty($html)) {
        return $html;
    }

    // Get metadata from Media Library
    $title       = get_the_title($post_thumbnail_id);
    $caption     = wp_get_attachment_caption($post_thumbnail_id);
    $description = get_post_field('post_content', $post_thumbnail_id);

    // If all metadata is empty, bail
    if (!$title && !$caption && !$description) {
        return $html;
    }

    // Escape values for safe HTML output
    $title_attr       = $title       ? esc_attr($title)       : '';
    $caption_attr     = $caption     ? esc_attr($caption)     : '';
    $description_attr = $description ? esc_attr($description) : '';

    // Replace the first <img ...> tag in the featured image HTML
    $html = preg_replace_callback('/<img\b[^>]*\/?>/i', function ($matches) use ($title_attr, $caption_attr, $description_attr) {
        $tag = $matches[0];

        // Helper: replace empty attribute values if attribute exists but is empty
        $replace_empty_attr = function ($tag, $attr_name, $value) {
            if ($value === '') {
                return $tag; // nothing to add
            }
            // If attribute exists and is empty, fill it
            $pattern_empty = '/\b' . preg_quote($attr_name, '/') . '\s*=\s*([\'"])\s*\1/i';
            if (preg_match($pattern_empty, $tag)) {
                return preg_replace($pattern_empty, $attr_name . '="$1' . $value . '$1"', $tag, 1);
            }
            return $tag;
        };

        // 1) If attributes exist but are empty, fill them
        $tag = $replace_empty_attr($tag, 'title', $title_attr);
        $tag = $replace_empty_attr($tag, 'data-caption', $caption_attr);
        $tag = $replace_empty_attr($tag, 'data-description', $description_attr);

        // 2) If attributes do not exist, add them
        if ($title_attr !== '' && stripos($tag, 'title=') === false) {
            $tag = preg_replace('/^<img\b/i', '<img title="' . $title_attr . '"', $tag, 1);
        }
        if ($caption_attr !== '' && stripos($tag, 'data-caption=') === false) {
            $tag = preg_replace('/^<img\b/i', '<img data-caption="' . $caption_attr . '"', $tag, 1);
        }
        if ($description_attr !== '' && stripos($tag, 'data-description=') === false) {
            $tag = preg_replace('/^<img\b/i', '<img data-description="' . $description_attr . '"', $tag, 1);
        }

        return $tag;
    }, $html, 1); // limit to first match

    return $html;
}
