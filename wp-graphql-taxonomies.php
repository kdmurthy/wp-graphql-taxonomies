<?php
/**
 * Plugin Name: WPGraphQL Extensions for Taxonomy
 * Plugin URI: https://github.com/wp-graphql/wp-graphql-tax-query
 * Description: Support for tax_query & custom, customId, customAnd, customIn, customNotIn, customSlugAnd and customSlugIn for custom taxonomies
 * Author: Dakshinamurthy Karra
 * Author URI: https://marathontesting.com
 * Version: 0.2.0
 * Text Domain: wp-graphql-taxonomies
 * Requires at least: 4.7.0
 * Tested up to: 4.7.1
 *
 * @package extensions
 */

namespace WPGraphQL\Extensions\Taxonomies;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPGRAPHQL_TAXONOMIES_VERSION', '0.2.0' );
define( 'WPGRAPHQL_TAXONOMIES_WPGRAPHQL_MINIMUM_VERSION', '1.3.5' );


/*
 * Boot the plugin
 */
add_action(
	'plugins_loaded',
	function () {
		if ( class_exists( 'WPGraphQL' ) && \version_compare( WPGRAPHQL_VERSION, WPGRAPHQL_TAXONOMIES_WPGRAPHQL_MINIMUM_VERSION ) >= 0 ) {
			require_once \dirname( __FILE__ ) . '/class-taxquery.php';
		} else {
			add_action(
				'admin_notices',
				function() {
					?>
					<div class="error fade">
						<p>
							<strong><?php esc_html_e( 'NOTICE', 'wp-graphql-taxonomies' ); ?>:</strong> WPGraphQL Extensions for Taxonomy <?php echo esc_html( WPGRAPHQL_TAXONOMIES_VERSION ); ?> <?php esc_html_e( 'requires a minimum of', 'wp-graphql-taxonomies' ); ?>
							<strong>WP GraphQL <?php echo esc_html( WPGRAPHQL_TAXONOMIES_WPGRAPHQL_MINIMUM_VERSION ); ?>+</strong> <?php esc_html_e( 'to function. Please install and activate the plugin', 'wp-graphql-taxonomies' ); ?>
						</p>
					</div>
					<?php
				}
			);
		}
	}
);
