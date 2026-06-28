<?php
/**
 * Uninstall cleanup.
 *
 * @package FaqAccordionBlock
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'faq-accordion-block_options' );
