<?php
/**
 * Plugin Name: WPGraphQL Extensions for Taxonomy
 * Plugin URI: https://github.com/wp-graphql/wp-graphql-tax-query
 * Description: Support for tax_query & custom, customId, customAnd, customIn, customNotIn, customSlugAnd and customSlugIn for custom taxonomies
 * Author: Dakshinamurthy Karra
 * Author URI: https://marathontesting.com
 * Version: 0.1.0
 * Text Domain: wp-graphql-taxonomies
 * Requires at least: 4.7.0
 * Tested up to: 4.7.1
 *
 * @package extensions
 */

namespace WPGraphQL\Extensions\Taxonomies;

use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Type\WPEnumType;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once \dirname( __FILE__ ) . '/class-taxquery.php';
