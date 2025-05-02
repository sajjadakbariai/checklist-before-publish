<?php
/**
 * Checklist Seokar - Admin Settings Page Template v2.0.0
 *
 * Renders the HTML structure for the plugin's settings page,
 * utilizing the WordPress Settings API functions and basic tabbing via JS.
 *
 * @package Checklist_Seokar
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Security check: Ensure this file is loaded within WordPress admin context
if ( ! is_admin() ) {
    return;
}

?>

<div class="wrap csk-settings-wrap">

    <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

    <?php
    // Display any settings errors/update messages registered by Settings API
    settings_errors();
    ?>

    <?php
    // Determine active tab
    $possible_tabs = ['general', 'enabled_checks', 'content_types'];
    /**
     * Filters the array of possible setting tab slugs.
     * Allows adding new tabs.
     * @since 2.0.0
     * @param array $tabs Default tab slugs.
     */
    $all_tabs = apply_filters('csk_settings_tab_slugs', $possible_tabs);
    $active_tab = isset( $_GET['tab'] ) && in_array($_GET['tab'], $all_tabs, true) ? sanitize_key( $_GET['tab'] ) : 'general';
    ?>

    <!-- Navigation Tabs -->
    <h2 class="nav-tab-wrapper csk-nav-tabs" style="margin-bottom: 20px;">
        <a href="?page=<?php echo esc_attr( CSK_SETTINGS_SLUG ); ?>&tab=general" class="nav-tab <?php echo $active_tab == 'general' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'General', CSK_TEXT_DOMAIN ); ?></a>
        <a href="?page=<?php echo esc_attr( CSK_SETTINGS_SLUG ); ?>&tab=enabled_checks" class="nav-tab <?php echo $active_tab == 'enabled_checks' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Enabled Checks', CSK_TEXT_DOMAIN ); ?></a>
        <a href="?page=<?php echo esc_attr( CSK_SETTINGS_SLUG ); ?>&tab=content_types" class="nav-tab <?php echo $active_tab == 'content_types' ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Content Types', CSK_TEXT_DOMAIN ); ?></a>
         <?php
         /**
          * Action hook to render additional navigation tabs.
          * Use this to add links for custom tabs added via the 'csk_settings_tab_slugs' filter.
          *
          * @since 2.0.0
          * @param string $active_tab The currently active tab slug.
          */
         do_action('csk_settings_tabs', $active_tab);
         ?>
    </h2>

    <form method="post" action="options.php" id="csk-settings-form">
        <?php
        /**
         * Output necessary hidden fields for the 'csk_seokar_settings_group' setting group.
         */
        settings_fields( 'csk_seokar_settings_group' );
        ?>

        <div class="csk-settings-tab-content" id="tab-content-container">
            <?php
            /**
             * We will render all sections registered to the MAIN page slug (CSK_SETTINGS_SLUG).
             * Then, using JS, we will move the content of each section into the appropriate tab div based on its ID.
             * This is a workaround for the limitation of do_settings_sections showing all sections for a page.
             */

            // Render all sections registered to the main page slug invisibly first
            // This makes the field callbacks execute and allows JS to find them.
            echo '<div id="csk-all-sections-temp" style="display: none;">';
            do_settings_sections( CSK_SETTINGS_SLUG );
            echo '</div>';
            ?>

            <!-- Tab Content Containers -->
            <div id="tab-general" class="csk-tab-pane <?php echo $active_tab == 'general' ? 'active' : ''; ?>">
                <?php /* Content will be moved here by JS */ ?>
                <?php submit_button( __( 'Save General Settings', CSK_TEXT_DOMAIN ) ); ?>
            </div>
             <div id="tab-enabled_checks" class="csk-tab-pane <?php echo $active_tab == 'enabled_checks' ? 'active' : ''; ?>">
                <?php /* Content will be moved here by JS */ ?>
                <?php submit_button( __( 'Save Enabled Checks', CSK_TEXT_DOMAIN ) ); ?>
            </div>
             <div id="tab-content_types" class="csk-tab-pane <?php echo $active_tab == 'content_types' ? 'active' : ''; ?>">
                <?php /* Content will be moved here by JS */ ?>
                <?php submit_button( __( 'Save Content Type Settings', CSK_TEXT_DOMAIN ) ); ?>
            </div>

            <?php
            /**
             * Action hook to render additional tab content containers.
             * Use this to add divs for custom tabs added via the 'csk_settings_tab_slugs' filter.
             * The div ID should match the tab slug (e.g., id="tab-custom_tab_slug").
             *
             * @since 2.0.0
             * @param string $active_tab The currently active tab slug.
             */
            do_action('csk_settings_tab_content_containers', $active_tab);
            ?>

        </div> <?php // .csk-settings-tab-content ?>

    </form>

    <?php // Inline JS and CSS for tab functionality - Consider moving to separate files ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Move sections to their respective tabs
            var $tempContainer = $('#csk-all-sections-temp');

            // Function to find the content associated with a section title (H2)
            function getSectionContent($titleElement) {
                // Content is typically a form-table, but can include paragraphs before it
                 let $contentElements = $titleElement.nextUntil('h2, #submit'); // Select until next H2 or submit button
                 return $contentElements;
                 // A more robust way might involve wrapping sections in divs in the Settings API callbacks
            }

            $tempContainer.find('h2').each(function() {
                var $title = $(this);
                var $content = getSectionContent($title);
                var sectionId = $title.attr('id'); // Settings API often adds ID to h2

                var targetTabId = '';
                if (sectionId && sectionId.includes('csk_section_general')) { targetTabId = '#tab-general'; }
                else if (sectionId && sectionId.includes('csk_section_enabled_checks')) { targetTabId = '#tab-enabled_checks'; }
                else if (sectionId && sectionId.includes('csk_section_content_types')) { targetTabId = '#tab-content_types'; }
                else {
                    // Attempt to map based on known section IDs if title doesn't have ID
                    var $prevDiv = $title.prev('div[id^="csk_section_"]');
                    if ($prevDiv.length) sectionId = $prevDiv.attr('id');
                    if (sectionId && sectionId.includes('csk_section_general')) { targetTabId = '#tab-general'; }
                    else if (sectionId && sectionId.includes('csk_section_enabled_checks')) { targetTabId = '#tab-enabled_checks'; }
                    else if (sectionId && sectionId.includes('csk_section_content_types')) { targetTabId = '#tab-content_types'; }

                     // --- Allow filtering for custom tabs ---
                     <?php
                     /**
                      * Action hook to allow JS to map sections to custom tab containers.
                      * Use JS here to check sectionId or title and assign targetTabId.
                      * Example: $(document).trigger('csk_map_section_to_tab', [sectionId, $title, function(tabId) { targetTabId = tabId; }]);
                      * @since 2.0.0
                      */
                     do_action('csk_settings_map_section_js');
                     ?>
                }


                if (targetTabId) {
                    var $targetTab = $(targetTabId);
                    if ($targetTab.length) {
                        // Prepend title and content to the target tab container
                        // We prepend the submit button later or keep one global button
                        $targetTab.prepend($content);
                        $targetTab.prepend($title); // Add title before content
                    } else {
                         console.warn('CSK Settings: Target tab container not found:', targetTabId);
                    }
                } else {
                     console.warn('CSK Settings: Could not determine target tab for section:', $title.text());
                      // Optionally move unmapped sections to the general tab as a fallback
                     // $('#tab-general').prepend($content).prepend($title);
                }
            });

            // Remove the temporary container
            $tempContainer.remove();

            // Hide inactive tabs (initial state)
             $('.csk-tab-pane:not(.active)').hide();

             // Tab click handler (Optional - handled by page reload by default)
             $('.csk-nav-tabs a').on('click', function(e){
                 // Let the default link behavior (page reload with ?tab=...) happen
                 // If you wanted pure JS tabs without reload, you'd add preventDefault()
                 // and handle showing/hiding tabs here.
             });

        });
    </script>
    <style type="text/css">
        .csk-tab-pane { display: none; }
        .csk-tab-pane.active { display: block; }
        /* Add other styles from previous CSS file if needed */
         .csk-settings-wrap .form-table th { width: 220px; padding-right: 20px; }
         .csk-settings-wrap fieldset { border: 1px solid #ccd0d4; padding: 10px 15px 15px; margin-top: 5px; background-color: #fff; border-radius: 4px; }
         .csk-settings-wrap fieldset label { display: block; margin-bottom: 8px; font-weight: normal; }
         .csk-settings-wrap fieldset label input[type="checkbox"] { margin-right: 6px; vertical-align: middle; }
         body.rtl .csk-settings-wrap fieldset label input[type="checkbox"] { margin-left: 6px; margin-right: 0; }
         /* Ensure submit buttons are aligned nicely within tabs */
         .csk-tab-pane .submit { padding: 10px 0 0 0; margin: 15px 0 0 0; }
    </style>

</div><!-- .wrap -->
