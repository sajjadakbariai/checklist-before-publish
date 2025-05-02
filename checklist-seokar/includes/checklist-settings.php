<?php
/**
 * Checklist Seokar - Settings API & Meta Data Handling
 *
 * This file manages:
 * 1. Registration of plugin settings using the WordPress Settings API.
 * 2. Rendering of fields on the settings page.
 * 3. Sanitization and validation of submitted settings.
 * 4. Saving and retrieving of per-post/per-term meta data.
 *
 * @package Checklist_Seokar
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// --- Meta Key Constants (Ensure these match names used in checklist-box.php template) ---
define( 'CSK_MAIN_KEYWORD_META_KEY', '_csk_main_keyword' );
define( 'CSK_RELATED_KEYWORDS_META_KEY', '_csk_related_keywords' );
define( 'CSK_GOOGLE_TITLE_META_KEY', '_csk_google_title' );
define( 'CSK_GOOGLE_DESC_META_KEY', '_csk_google_description' );


// ==========================================================================
// Settings API Registration & Handling
// ==========================================================================

/**
 * Register settings, sections, and fields for the plugin options page.
 * Hooked to 'admin_init'.
 */
function csk_register_settings() {

	// Register the main setting group and option name
	register_setting(
		'csk_seokar_settings_group',       // Option group (used in settings_fields())
		CSK_SETTINGS_OPTION_NAME,          // Option name (where all settings are stored as an array)
		'csk_sanitize_options'             // Sanitization callback function
	);

	// --- Settings Sections ---

	add_settings_section(
		'csk_section_general',             // Section ID
		__( 'General Settings', CSK_TEXT_DOMAIN ), // Section Title
		'csk_section_general_callback',    // Callback for introductory text (optional)
		CSK_SETTINGS_SLUG                  // Page slug where this section appears
	);

    add_settings_section(
		'csk_section_enabled_checks',      // Section ID
		__( 'Enabled Checklist Items', CSK_TEXT_DOMAIN ), // Section Title
		'csk_section_enabled_checks_callback', // Callback for introductory text
		CSK_SETTINGS_SLUG                  // Page slug
	);

    add_settings_section(
		'csk_section_content_types',       // Section ID
		__( 'Enabled Content Types', CSK_TEXT_DOMAIN ), // Section Title
		'csk_section_content_types_callback', // Callback for introductory text
		CSK_SETTINGS_SLUG                  // Page slug
	);


	// --- Settings Fields ---

    // General Section Fields
    add_settings_field(
        'min_content_length',
        __( 'Minimum Content Length', CSK_TEXT_DOMAIN ),
        'csk_render_number_field', // Callback to render the field
        CSK_SETTINGS_SLUG,
        'csk_section_general',
        [
            'label_for'   => 'min_content_length', // Associates label with input
            'description' => sprintf(__( 'Minimum recommended characters for main content (Default: %d).', CSK_TEXT_DOMAIN ), CSK_MIN_CONTENT_LENGTH_DEFAULT),
            'min'         => 0,
            'step'        => 10,
        ]
    );
     add_settings_field(
        'keyword_density_min',
        __( 'Minimum Keyword Density (%)', CSK_TEXT_DOMAIN ),
        'csk_render_number_field',
        CSK_SETTINGS_SLUG,
        'csk_section_general',
        [
            'label_for'   => 'keyword_density_min',
            'description' => sprintf(__( 'Minimum recommended keyword density (Default: %s%%).', CSK_TEXT_DOMAIN ), CSK_DENSITY_MIN_DEFAULT),
            'min'         => 0,
            'max'         => 10,
            'step'        => 0.1,
        ]
    );
     add_settings_field(
        'keyword_density_max',
        __( 'Maximum Keyword Density (%)', CSK_TEXT_DOMAIN ),
        'csk_render_number_field',
        CSK_SETTINGS_SLUG,
        'csk_section_general',
        [
            'label_for'   => 'keyword_density_max',
            'description' => sprintf(__( 'Maximum recommended keyword density (Default: %s%%). Avoid keyword stuffing.', CSK_TEXT_DOMAIN ), CSK_DENSITY_MAX_DEFAULT),
            'min'         => 0.1,
            'max'         => 15, // Allow a bit higher max setting
            'step'        => 0.1,
        ]
    );

    // Enabled Checks Section Field (Renders all checkboxes)
     add_settings_field(
        'enabled_checks',
        __( 'Select Checks to Enable', CSK_TEXT_DOMAIN ),
        'csk_render_enabled_checks_field', // Callback to render the checkbox group
        CSK_SETTINGS_SLUG,
        'csk_section_enabled_checks'
        // No 'label_for' needed as it's a group
    );

     // Enabled Content Types Section Fields
     add_settings_field(
        'enabled_post_types',
        __( 'Enable for Post Types', CSK_TEXT_DOMAIN ),
        'csk_render_enabled_post_types_field', // Callback to render post type checkboxes
        CSK_SETTINGS_SLUG,
        'csk_section_content_types'
    );
     add_settings_field(
        'enabled_taxonomies',
        __( 'Enable for Taxonomies', CSK_TEXT_DOMAIN ),
        'csk_render_enabled_taxonomies_field', // Callback to render taxonomy checkboxes
        CSK_SETTINGS_SLUG,
        'csk_section_content_types'
    );

}
add_action( 'admin_init', 'csk_register_settings' );


// --- Section Callback Functions ---

function csk_section_general_callback() {
	echo '<p>' . esc_html__( 'Configure general parameters for the checklist analysis.', CSK_TEXT_DOMAIN ) . '</p>';
}

function csk_section_enabled_checks_callback() {
    echo '<p>' . esc_html__( 'Choose which SEO and content checks should be performed and displayed in the checklist.', CSK_TEXT_DOMAIN ) . '</p>';
}

function csk_section_content_types_callback() {
     echo '<p>' . esc_html__( 'Select the content types where the Checklist Seokar metabox or fields should appear.', CSK_TEXT_DOMAIN ) . '</p>';
}


// --- Field Rendering Callback Functions ---

/**
 * Renders a generic number input field for settings.
 * Uses 'args' passed from add_settings_field.
 *
 * @param array $args Field arguments (label_for, description, min, max, step).
 */
function csk_render_number_field( $args ) {
    $options = csk_get_options();
    $field_id = esc_attr( $args['label_for'] );
    $value = isset( $options[ $field_id ] ) ? esc_attr( $options[ $field_id ] ) : '';
    $min = isset($args['min']) ? 'min="' . esc_attr($args['min']) . '"' : '';
    $max = isset($args['max']) ? 'max="' . esc_attr($args['max']) . '"' : '';
    $step = isset($args['step']) ? 'step="' . esc_attr($args['step']) . '"' : '1';

    printf(
        '<input type="number" id="%1$s" name="%2$s[%1$s]" value="%3$s" %4$s %5$s %6$s class="small-text" />',
        $field_id,
        esc_attr( CSK_SETTINGS_OPTION_NAME ), // option_name[field_id]
        $value,
        $min,
        $max,
        $step
    );

    if ( ! empty( $args['description'] ) ) {
        printf( '<p class="description">%s</p>', esc_html( $args['description'] ) );
    }
}

/**
 * Renders checkboxes for enabling/disabling specific checklist items.
 */
function csk_render_enabled_checks_field() {
    $options = csk_get_options();
    $enabled_checks = isset( $options['enabled_checks'] ) && is_array( $options['enabled_checks'] ) ? $options['enabled_checks'] : [];
    $all_checks = csk_get_checklist_items_config(); // Get the configuration array

    if (empty($all_checks)) {
        echo '<p>' . esc_html__('Error: Could not load checklist items configuration.', CSK_TEXT_DOMAIN) . '</p>';
        return;
    }

    echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html__( 'Select Checks to Enable', CSK_TEXT_DOMAIN ) . '</span></legend>';
    foreach ( $all_checks as $key => $config ) {
        $checkbox_id = 'csk_enabled_check_' . esc_attr( $key );
        $checked = !empty( $enabled_checks[ $key ] ); // Check if the key exists and is truthy

        printf(
            '<label for="%1$s" style="display: block; margin-bottom: 5px;">',
            $checkbox_id
        );
        printf(
            '<input type="checkbox" id="%1$s" name="%2$s[enabled_checks][%3$s]" value="1" %4$s /> ',
            $checkbox_id,
            esc_attr( CSK_SETTINGS_OPTION_NAME ),
            esc_attr( $key ),
            checked( $checked, true, false ) // Use checked() helper
        );
        echo esc_html( $config['label'] ) . '</label>';
    }
     echo '</fieldset>';
     echo '<p class="description">' . esc_html__('Unchecking an item will hide it from the checklist and exclude it from the overall score calculation.', CSK_TEXT_DOMAIN) . '</p>';
}


/**
 * Renders checkboxes for enabling the checklist on specific post types.
 */
function csk_render_enabled_post_types_field() {
    $options = csk_get_options();
    $enabled_types = isset( $options['enabled_post_types'] ) && is_array( $options['enabled_post_types'] ) ? $options['enabled_post_types'] : [];
    $post_types = get_post_types( [ 'public' => true ], 'objects' );

    echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html__( 'Enable for Post Types', CSK_TEXT_DOMAIN ) . '</span></legend>';
    foreach ( $post_types as $post_type ) {
        // Skip attachments or other non-relevant types if needed
        if ( $post_type->name === 'attachment' ) continue;

        $checkbox_id = 'csk_enabled_post_type_' . esc_attr( $post_type->name );
        $checked = in_array( $post_type->name, $enabled_types );

        printf(
            '<label for="%1$s" style="display: block; margin-bottom: 5px;">',
             $checkbox_id
        );
        printf(
            '<input type="checkbox" id="%1$s" name="%2$s[enabled_post_types][]" value="%3$s" %4$s /> ',
             $checkbox_id,
            esc_attr( CSK_SETTINGS_OPTION_NAME ),
            esc_attr( $post_type->name ),
            checked( $checked, true, false )
        );
         echo esc_html( $post_type->label ) . ' (<code>' . esc_html($post_type->name) . '</code>)</label>';
    }
     echo '</fieldset>';
}

/**
 * Renders checkboxes for enabling the checklist on specific taxonomies.
 */
function csk_render_enabled_taxonomies_field() {
     $options = csk_get_options();
    $enabled_taxonomies = isset( $options['enabled_taxonomies'] ) && is_array( $options['enabled_taxonomies'] ) ? $options['enabled_taxonomies'] : [];
    $taxonomies = get_taxonomies( [ 'public' => true, 'show_ui' => true ], 'objects' ); // Get taxonomies shown in admin UI

    echo '<fieldset><legend class="screen-reader-text"><span>' . esc_html__( 'Enable for Taxonomies', CSK_TEXT_DOMAIN ) . '</span></legend>';
    foreach ( $taxonomies as $taxonomy ) {
        // You might want to exclude specific taxonomies like post_format
         if ( $taxonomy->name === 'post_format' ) continue;

        $checkbox_id = 'csk_enabled_taxonomy_' . esc_attr( $taxonomy->name );
        $checked = in_array( $taxonomy->name, $enabled_taxonomies );

        printf(
            '<label for="%1$s" style="display: block; margin-bottom: 5px;">',
             $checkbox_id
        );
        printf(
            '<input type="checkbox" id="%1$s" name="%2$s[enabled_taxonomies][]" value="%3$s" %4$s /> ',
             $checkbox_id,
            esc_attr( CSK_SETTINGS_OPTION_NAME ),
            esc_attr( $taxonomy->name ),
            checked( $checked, true, false )
        );
         echo esc_html( $taxonomy->label ) . ' (<code>' . esc_html($taxonomy->name) . '</code>)</label>';
    }
     echo '</fieldset>';
}

// --- Sanitization Callback Function ---

/**
 * Sanitize and validate the plugin options array before saving.
 *
 * @param array $input The submitted options data.
 * @return array The sanitized options array.
 */
function csk_sanitize_options( $input ) {
	$sanitized_options = [];
    $default_options = csk_get_default_options(); // Get defaults for comparison and structure
    $all_checks_keys = array_keys(csk_get_checklist_items_config()); // Allowed check keys

	// Sanitize General Settings
    $sanitized_options['min_content_length'] = isset( $input['min_content_length'] ) ? absint( $input['min_content_length'] ) : $default_options['min_content_length'];
    // Ensure non-negative
    if ($sanitized_options['min_content_length'] < 0) $sanitized_options['min_content_length'] = 0;

    $sanitized_options['keyword_density_min'] = isset( $input['keyword_density_min'] ) ? max(0, floatval( $input['keyword_density_min'] )) : $default_options['keyword_density_min'];
    $sanitized_options['keyword_density_max'] = isset( $input['keyword_density_max'] ) ? max(0.1, floatval( $input['keyword_density_max'] )) : $default_options['keyword_density_max'];
    // Ensure min is not greater than max
    if ($sanitized_options['keyword_density_min'] > $sanitized_options['keyword_density_max']) {
        // Reset to defaults or swap values - resetting is safer
         $sanitized_options['keyword_density_min'] = $default_options['keyword_density_min'];
         $sanitized_options['keyword_density_max'] = $default_options['keyword_density_max'];
         add_settings_error( // Add an admin notice
            'csk_density_error',
            'density_error',
            __('Minimum keyword density cannot be greater than maximum density. Values reset to default.', CSK_TEXT_DOMAIN),
            'error'
        );
    }


	// Sanitize Enabled Checks
    $sanitized_options['enabled_checks'] = [];
    if ( isset( $input['enabled_checks'] ) && is_array( $input['enabled_checks'] ) ) {
        foreach ( $input['enabled_checks'] as $key => $value ) {
            // Only accept keys that are defined in our config
            if ( in_array( $key, $all_checks_keys, true ) && $value === '1' ) {
                $sanitized_options['enabled_checks'][ sanitize_key( $key ) ] = 1; // Store as 1 if checked
            }
        }
    }

    // Sanitize Enabled Post Types
    $sanitized_options['enabled_post_types'] = [];
    if ( isset( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] ) ) {
         $available_post_types = array_keys( get_post_types( [ 'public' => true ], 'names' ) );
         foreach ( $input['enabled_post_types'] as $post_type ) {
            // Only accept valid, public post types
            if ( in_array( $post_type, $available_post_types, true ) ) {
                 $sanitized_options['enabled_post_types'][] = sanitize_key( $post_type );
            }
        }
    }

    // Sanitize Enabled Taxonomies
    $sanitized_options['enabled_taxonomies'] = [];
    if ( isset( $input['enabled_taxonomies'] ) && is_array( $input['enabled_taxonomies'] ) ) {
        $available_taxonomies = array_keys( get_taxonomies( [ 'public' => true, 'show_ui' => true ], 'names' ) );
         foreach ( $input['enabled_taxonomies'] as $taxonomy ) {
             // Only accept valid, public taxonomies shown in UI
            if ( in_array( $taxonomy, $available_taxonomies, true ) ) {
                 $sanitized_options['enabled_taxonomies'][] = sanitize_key( $taxonomy );
            }
        }
    }

	// Add more sanitization rules for future settings here...

	return $sanitized_options;
}

// --- Helper Functions for Settings ---

/**
 * Get the configuration array for all available checklist items.
 * This is used to build the settings page and check against allowed keys.
 *
 * @return array Associative array where keys are check IDs and values have 'label'.
 */
function csk_get_checklist_items_config() {
    // This function defines the 'master list' of all possible checks
    $checks = [
        'content_length'        => ['label' => __( 'Content Length', CSK_TEXT_DOMAIN )],
        'headings'              => ['label' => __( 'Heading Usage (H2-H6)', CSK_TEXT_DOMAIN )],
        'main_keyword_presence' => ['label' => __( 'Main Keyword Entered', CSK_TEXT_DOMAIN )],
        'google_title'          => ['label' => __( 'SEO Title Length', CSK_TEXT_DOMAIN )],
        'google_description'    => ['label' => __( 'Meta Description Length', CSK_TEXT_DOMAIN )],
        'featured_image'        => ['label' => __( 'Featured Image Set (Posts only)', CSK_TEXT_DOMAIN )],
        'internal_links'        => ['label' => __( 'Internal Links', CSK_TEXT_DOMAIN )],
        'external_links'        => ['label' => __( 'External Links', CSK_TEXT_DOMAIN )],
        'keyword_title'         => ['label' => __( 'Keyword in SEO Title', CSK_TEXT_DOMAIN )],
        'keyword_description'   => ['label' => __( 'Keyword in Meta Description', CSK_TEXT_DOMAIN )],
        'keyword_headings'      => ['label' => __( 'Keyword in Headings', CSK_TEXT_DOMAIN )],
        'keyword_density'       => ['label' => __( 'Keyword Density', CSK_TEXT_DOMAIN )],
        'keyword_slug'          => ['label' => __( 'Keyword in URL (Slug)', CSK_TEXT_DOMAIN )],
        'image_alt_texts'       => ['label' => __( 'Image Alt Attributes', CSK_TEXT_DOMAIN )],
        'text_structure'        => ['label' => __( 'Text Structure (Sentence/Paragraph Length)', CSK_TEXT_DOMAIN )],
        'readability'           => ['label' => __( 'Readability Score (Flesch)', CSK_TEXT_DOMAIN )],
    ];
    /**
	 * Filters the configuration array for checklist items.
	 * Allows adding/removing/modifying checks via other plugins/themes.
	 *
	 * @since 2.0.0
	 *
	 * @param array $checks The default checklist configuration.
	 */
    return apply_filters('csk_checklist_items_config', $checks);
}


/**
 * Defines the default options for the plugin.
 * Used when the option is not yet set or for resetting.
 *
 * @return array Default options array.
 */
function csk_get_default_options() {
     $default_checks = [];
     $all_checks = csk_get_checklist_items_config();
     // Enable most checks by default, you can adjust this
     foreach (array_keys($all_checks) as $key) {
         // Example: disable readability by default as it's experimental/intensive
         if ($key !== 'readability') {
             $default_checks[$key] = 1;
         } else {
              $default_checks[$key] = 0; // Disabled by default
         }
     }

	$defaults = [
		'min_content_length'   => CSK_MIN_CONTENT_LENGTH_DEFAULT,
		'keyword_density_min'  => CSK_DENSITY_MIN_DEFAULT,
		'keyword_density_max'  => CSK_DENSITY_MAX_DEFAULT,
        'enabled_checks'       => $default_checks,
        'enabled_post_types'   => ['post', 'page'], // Default enabled post types
        'enabled_taxonomies'   => ['category', 'post_tag'], // Default enabled taxonomies
	];
    /**
	 * Filters the default plugin options.
	 *
	 * @since 2.0.0
	 *
	 * @param array $defaults The default options array.
	 */
    return apply_filters('csk_default_options', $defaults);
}


// ==========================================================================
// Meta Data Saving and Retrieval (Moved from main file, prefixed with csk_)
// ==========================================================================

/**
 * Get meta value for a post or term. (Renamed from cbp_get_meta)
 *
 * @param int|null $object_id Post ID or Term ID.
 * @param string   $key       Meta key (should start with _csk_).
 * @param bool     $is_term   Whether it's a term.
 * @return mixed             Meta value or empty string.
 */
function csk_get_meta( $object_id = null, $key = '', $is_term = false ) {
    if ( empty( $object_id ) || empty( $key ) ) {
        return '';
    }
    // Basic security check on key format
    if (strpos($key, '_csk_') !== 0) {
        return ''; // Only allow keys starting with our prefix
    }

    if ( $is_term ) {
        return get_term_meta( $object_id, $key, true );
    } else {
        return get_post_meta( $object_id, $key, true );
    }
}

/**
 * Save meta data for posts. (Renamed from cbp_save_post_meta_data)
 * Attached to 'save_post' hook.
 *
 * @param int     $post_id The ID of the post being saved.
 * @param WP_Post $post    The post object.
 */
function csk_save_post_meta_data( $post_id, $post ) {
    // --- Security Checks ---
    // Check if nonce is set and valid.
    if ( ! isset( $_POST['csk_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key($_POST['csk_meta_nonce']), 'csk_save_meta_data' ) ) {
        return;
    }
    // Check if the user has permissions to save data.
    if (isset($post->post_type)) {
        $post_type_object = get_post_type_object( $post->post_type );
        if ( ! $post_type_object || ! current_user_can( $post_type_object->cap->edit_post, $post_id ) ) {
            return;
        }
    } else {
        // Could not determine post type, bail for safety
        return;
    }
    // Check if it's not an autosave or revision.
    if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
        return;
    }
    // Check if checklist is enabled for this post type (optional extra check)
    // $options = csk_get_options();
    // $enabled_post_types = isset($options['enabled_post_types']) ? $options['enabled_post_types'] : [];
    // if (!in_array($post->post_type, $enabled_post_types)) return;

    // --- Sanitize and Save Meta Fields ---
    $meta_fields_to_save = [
        CSK_MAIN_KEYWORD_META_KEY       => 'sanitize_text_field',
        CSK_RELATED_KEYWORDS_META_KEY   => 'sanitize_text_field', // Still text field, even if comma-separated
        CSK_GOOGLE_TITLE_META_KEY       => 'sanitize_text_field', // Titles are single line
        CSK_GOOGLE_DESC_META_KEY        => 'sanitize_textarea_field', // Descriptions can be multi-line potentially
    ];

    foreach ($meta_fields_to_save as $key => $sanitize_callback) {
         // Field names in the form should match the meta keys without the leading underscore
         $field_name = substr($key, 1); // Remove leading underscore
        if ( isset( $_POST[ $field_name ] ) ) {
            $value = call_user_func( $sanitize_callback, $_POST[ $field_name ] );
            update_post_meta( $post_id, $key, $value );
        } else {
            // If field is not sent (e.g., user cleared it), delete or update with empty string
             update_post_meta( $post_id, $key, '' ); // Update with empty is usually safer than delete
            // delete_post_meta( $post_id, $key );
        }
    }
}

/**
 * Save meta data for terms. (Renamed from cbp_save_term_meta_data)
 * Attached to 'created_{taxonomy}' and 'edited_{taxonomy}' hooks conditionally.
 *
 * @param int $term_id Term ID.
 * @param int $tt_id   Term taxonomy ID. (Only for 'edited_' hook, not always needed).
 */
function csk_save_term_meta_data( $term_id, $tt_id = null ) {
    // --- Security Checks ---
     if ( ! isset( $_POST['csk_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key($_POST['csk_meta_nonce']), 'csk_save_meta_data' ) ) {
        error_log('Checklist Seokar: Nonce check failed for term save: ' . $term_id);
        return;
    }
    // Get taxonomy from POST data
    $taxonomy = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : null;
    if (!$taxonomy) {
        $term = get_term($term_id);
        if ($term && !is_wp_error($term)) $taxonomy = $term->taxonomy;
    }
    if (!$taxonomy) {
        error_log('Checklist Seokar: Could not determine taxonomy for term save: ' . $term_id);
        return; // Cannot check capabilities without taxonomy
    }
    // Check capabilities for the specific taxonomy
    $tax_object = get_taxonomy($taxonomy);
    if (!$tax_object || !current_user_can( $tax_object->cap->edit_terms )) {
         error_log('Checklist Seokar: Capability check failed for term save: ' . $term_id . ' Taxonomy: ' . $taxonomy);
        return;
    }
    // Check if checklist is enabled for this taxonomy (optional extra check)
    // $options = csk_get_options();
    // $enabled_taxonomies = isset($options['enabled_taxonomies']) ? $options['enabled_taxonomies'] : [];
    // if (!in_array($taxonomy, $enabled_taxonomies)) return;


    // --- Sanitize and Save Meta Fields ---
    $meta_fields_to_save = [
        CSK_MAIN_KEYWORD_META_KEY       => 'sanitize_text_field',
        CSK_RELATED_KEYWORDS_META_KEY   => 'sanitize_text_field',
        CSK_GOOGLE_TITLE_META_KEY       => 'sanitize_text_field',
        CSK_GOOGLE_DESC_META_KEY        => 'sanitize_textarea_field',
    ];

     foreach ($meta_fields_to_save as $key => $sanitize_callback) {
        $field_name = substr($key, 1); // Remove leading underscore
        if ( isset( $_POST[ $field_name ] ) ) {
            $value = call_user_func( $sanitize_callback, $_POST[ $field_name ] );
            update_term_meta( $term_id, $key, $value );
        } else {
             update_term_meta( $term_id, $key, '' ); // Update with empty if field is cleared/not sent
            // delete_term_meta( $term_id, $key );
        }
    }
}

?>
