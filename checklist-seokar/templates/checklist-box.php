<?php
/**
 * Checklist Seokar - Meta Box/Term Field Template
 *
 * Renders the checklist interface within the post/term editor.
 * Conditionally displays items based on plugin settings.
 *
 * @package Checklist_Seokar
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Get necessary data and settings ---
$options = csk_get_options(); // Get plugin settings
$enabled_checks = isset($options['enabled_checks']) && is_array($options['enabled_checks']) ? $options['enabled_checks'] : [];

// Determine context (passed from calling function or detected)
$is_term_context = isset($is_term) ? $is_term : false;
$object_id = null;
$current_content = '';
$current_title = '';
$current_url = '';
$current_excerpt = '';
$current_slug = '';
$object_type = 'unknown';

// --- Object Data Retrieval ---
// This logic determines the current object ID, type, and retrieves initial data
// based on whether we're in a post or term context.
if ($is_term_context) {
    global $tag, $term; // Access global term vars if needed
    $current_term_object = $term ?? $tag ?? null; // $term is usually set on edit, $tag on add maybe?
    $object_id = isset($current_term_object->term_id) ? $current_term_object->term_id : null;

    // Try getting ID from URL on edit screen if global object isn't set
    if (!$object_id && isset($_GET['tag_ID'])) {
        $object_id = absint($_GET['tag_ID']);
        $current_term_object = get_term($object_id);
    }

    $is_new_term = isset($is_new_term) ? $is_new_term : !$object_id; // Assume new if no ID
    $taxonomy = isset($_GET['taxonomy']) ? sanitize_key($_GET['taxonomy']) : ($current_term_object->taxonomy ?? null);
    $object_type = $taxonomy ?? 'term';

    if ($object_id && $current_term_object && !is_wp_error($current_term_object)) {
         $current_title = $current_term_object->name;
         $current_content = $current_term_object->description;
         $current_url = get_term_link($current_term_object);
         $current_slug = $current_term_object->slug;
         $current_excerpt = '';
    } else {
        // New term screen - get potential initial values from POST if available (less reliable)
        $current_title = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $current_content = isset($_POST['description']) ? wp_kses_post($_POST['description']) : '';
        $current_slug = isset($_POST['slug']) ? sanitize_title($_POST['slug']) : '';
        $current_url = '';
        $current_excerpt = '';
    }

} else { // Post context
    global $post;
    $current_post_object = $post;
    $object_id = $current_post_object ? $current_post_object->ID : (isset($_GET['post']) ? absint($_GET['post']) : null);

    if ($object_id) {
        $current_post_object = get_post($object_id); // Ensure we have the correct post object
        if ($current_post_object) {
            $object_type = $current_post_object->post_type;
            $current_title = $current_post_object->post_title;
            // Use saved content primarily, maybe check $_POST as fallback? JS handles real-time best.
            $current_content = $current_post_object->post_content;
            $current_url = get_permalink($object_id);
            $current_slug = $current_post_object->post_name;
            $current_excerpt = $current_post_object->post_excerpt;
        }
    } else {
         // New post screen
         $object_type = get_current_screen()->post_type ?? 'post';
         $current_title = '';
         $current_content = '';
         $current_url = '';
         $current_slug = '';
         $current_excerpt = '';
    }
}

// --- Get Saved Meta & Initial Status ---
$main_keyword = csk_get_meta( $object_id, CSK_MAIN_KEYWORD_META_KEY, $is_term_context );
$related_keywords = csk_get_meta( $object_id, CSK_RELATED_KEYWORDS_META_KEY, $is_term_context );
$google_title = csk_get_meta( $object_id, CSK_GOOGLE_TITLE_META_KEY, $is_term_context );
$google_description = csk_get_meta( $object_id, CSK_GOOGLE_DESC_META_KEY, $is_term_context );

// Get initial checklist statuses calculated by PHP based on saved data and enabled checks
$initial_statuses = csk_get_checklist_statuses($object_id, $is_term_context);

// --- Helper for Status Class ---
if (!function_exists('csk_get_status_class')) {
    function csk_get_status_class($status) {
        switch ($status) {
            case 'success': return 'csk-status-success';
            case 'warning': return 'csk-status-warning';
            case 'error': return 'csk-status-error';
            default: return 'csk-status-pending';
        }
    }
}

// --- Checklist Item Configuration (for labels in this template) ---
$checklist_items_config = csk_get_checklist_items_config();

?>
<div class="csk-checklist-wrapper"
     data-object-id="<?php echo esc_attr( $object_id ); ?>"
     data-object-type="<?php echo esc_attr( $object_type ); ?>"
     data-is-term="<?php echo esc_attr( $is_term_context ? '1' : '0' ); ?>"
     data-is-new="<?php echo esc_attr( (!$object_id) ? '1' : '0' ); ?>"
     data-default-title="<?php echo esc_attr( $current_title ); ?>"
     data-default-url="<?php echo esc_url( $current_url ); ?>"
     data-default-slug="<?php echo esc_attr( $current_slug ); ?>"
     data-default-description="<?php echo esc_attr( wp_strip_all_tags( $current_excerpt ?: mb_substr( wp_strip_all_tags($current_content), 0, 160 ) ) ); ?>"
     data-nonce="<?php echo wp_create_nonce( 'csk_ajax_nonce' ); // General AJAX nonce for JS ?>"
     >

    <?php // Security nonce for saving meta is added by the calling functions ?>

    <!-- Keyword Inputs -->
    <div class="csk-field-group">
        <label for="csk_main_keyword"><strong><?php esc_html_e( 'Main Keyword:', CSK_TEXT_DOMAIN ); ?></strong></label>
        <input type="text" id="csk_main_keyword" name="csk_main_keyword" value="<?php echo esc_attr( $main_keyword ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Enter the main target keyword', CSK_TEXT_DOMAIN ); ?>" aria-describedby="csk_main_keyword_desc"/>
        <p id="csk_main_keyword_desc" class="description"><?php esc_html_e( 'The primary keyword or phrase this content targets.', CSK_TEXT_DOMAIN ); ?></p>
    </div>
    <div class="csk-field-group">
        <label for="csk_related_keywords"><?php esc_html_e( 'Related Keywords:', CSK_TEXT_DOMAIN ); ?></label>
        <input type="text" id="csk_related_keywords" name="csk_related_keywords" value="<?php echo esc_attr( $related_keywords ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'keyword 1, phrase 2, keyword 3', CSK_TEXT_DOMAIN ); ?>" aria-describedby="csk_related_keywords_desc"/>
         <p id="csk_related_keywords_desc" class="description"><?php esc_html_e( 'Comma-separated related terms or LSI keywords.', CSK_TEXT_DOMAIN ); ?></p>
    </div>

    <hr>

    <!-- SERP Preview -->
    <h4><?php esc_html_e( 'Google Preview', CSK_TEXT_DOMAIN ); ?></h4>
    <div id="csk-serp-preview">
        <div class="csk-serp-title"><?php /* Populated by JS */ ?></div>
        <div class="csk-serp-url"><?php /* Populated by JS */ ?></div>
        <div class="csk-serp-desc"><?php /* Populated by JS */ ?></div>
    </div>
     <div class="csk-field-group">
        <label for="csk_google_title"><?php esc_html_e( 'SEO Title:', CSK_TEXT_DOMAIN ); ?></label>
        <input type="text" id="csk_google_title" name="csk_google_title" value="<?php echo esc_attr( $google_title ); ?>" class="widefat" placeholder="<?php esc_attr_e( 'Optional, defaults to content title', CSK_TEXT_DOMAIN ); ?>" aria-describedby="csk_google_title_desc"/>
        <p id="csk_google_title_desc" class="description"><?php printf(esc_html__('Optimal length: %d-%d chars. Uses main title if empty.', CSK_TEXT_DOMAIN), 50, 60); ?></p>
    </div>
     <div class="csk-field-group">
        <label for="csk_google_description"><?php esc_html_e( 'Meta Description:', CSK_TEXT_DOMAIN ); ?></label>
        <textarea id="csk_google_description" name="csk_google_description" class="widefat" rows="3" placeholder="<?php esc_attr_e( 'Optional, defaults to excerpt or content start', CSK_TEXT_DOMAIN ); ?>" aria-describedby="csk_google_description_desc"><?php echo esc_textarea( $google_description ); ?></textarea>
         <p id="csk_google_description_desc" class="description"><?php printf(esc_html__('Optimal length: %d-%d chars. Google generates one if empty.', CSK_TEXT_DOMAIN), 120, 160); ?></p>
    </div>

    <hr>

    <!-- Checklist -->
    <h4>
        <?php esc_html_e( 'SEO & Content Checklist', CSK_TEXT_DOMAIN ); ?>
        <span class="csk-loader csk-checklist-loader" style="display: none;"></span> <?php // Loader for updates ?>
    </h4>
    <ul class="csk-checklist">
        <?php foreach ($checklist_items_config as $key => $config): ?>
            <?php
                // Check if this item is enabled in settings
                if (empty($enabled_checks[$key])) continue;

                // Specific condition for featured image (only for non-terms)
                if ($key === 'featured_image' && $is_term_context) continue;

                // Get status and message safely using null coalescing
                $status = $initial_statuses[$key]['status'] ?? 'pending';
                $message = $initial_statuses[$key]['message'] ?? __('Status pending.', CSK_TEXT_DOMAIN);
            ?>
            <li data-check="<?php echo esc_attr($key); ?>">
                <span class="csk-status-icon <?php echo esc_attr(csk_get_status_class($status)); ?>"></span>
                <span class="csk-check-label"><?php echo esc_html($config['label']); ?></span>
                <small class="csk-check-message"><?php echo esc_html($message); ?></small>
                <span class="csk-loader csk-item-loader" style="display: none;"></span> <?php // Per-item loader if needed ?>
            </li>
        <?php endforeach; ?>
    </ul>

    <hr>

    <!-- Overall Status -->
     <div id="csk-overall-status" class="csk-overall-status-<?php echo esc_attr($initial_statuses['overall']['status'] ?? 'pending'); ?>">
         <strong><?php esc_html_e( 'Overall Status:', CSK_TEXT_DOMAIN ); ?></strong>
         <span class="csk-overall-message"><?php echo esc_html($initial_statuses['overall']['message'] ?? __('Calculating...', CSK_TEXT_DOMAIN)); ?></span>
         <span class="csk-overall-score" style="display:none;"><?php echo esc_html($initial_statuses['overall']['score'] ?? '0'); ?></span>
     </div>

     <!-- Help Link -->
     <div class="csk-help-link" style="margin-top: 15px; text-align: right; font-size: 12px;">
         <a href="<?php echo esc_url(admin_url('options-general.php?page=' . CSK_SETTINGS_SLUG)); ?>" target="_blank">
             <?php esc_html_e('Configure Checklist', CSK_TEXT_DOMAIN); ?> <span aria-hidden="true" class="dashicons dashicons-external"></span>
         </a>
          <?php
             /**
              * Action hook to add more links or info at the bottom of the metabox.
              * @since 2.0.0
              */
             do_action('csk_metabox_footer');
          ?>
     </div>

</div>
