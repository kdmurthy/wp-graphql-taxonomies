<?php
/**
 * Class TaxQuery
 * Main entry point into the plugin. Hooks on to graphql_input_fields and graphql_map_input_fields_to_wp_query
 * to handle custom taxonomies.
 *
 * @package WPGraphQL\Extensions\Taxonomies
 */

namespace WPGraphQL\Extensions\Taxonomies;

use WPGraphQL\Registry\TypeRegistry;
use WPGraphQL\Type\WPEnumType;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * TaxQuery - Taxonomy query support for WPGraphQL
 */
class TaxQuery {

	/**
	 * Map graphQL input variable names to function returning tax_query array
	 *
	 * @var array(string => (mixed))
	 */
	private $tax_queries;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->tax_queries = array();
		add_filter( 'graphql_input_fields', array( $this, 'add_input_fields' ), 10, 4 );
		add_filter( 'graphql_map_input_fields_to_wp_query', array( $this, 'map_input_fields' ), 10, 2 );
	}

	/**
	 * Add input fields to the `where` clause.
	 * - tax_query - a recursive structure to build complicated queries
	 * - For each custom taxonomy, add fields to filter the attached post types
	 *
	 * @param array        $fields - Input fields.
	 * @param string       $type_name - The type name.
	 * @param array        $config - The config object containing current context.
	 * @param TypeRegistry $type_registry - The global type registry.
	 * @return array - modified $fields.
	 */
	public function add_input_fields( $fields, $type_name, $config, $type_registry ) {
		if ( ! $this->is_query_type( $config ) ) {
			return $fields;
		}
		$parsed = $this->parse_type_name( $type_name );
		if ( ! $parsed ) {
			// If not a custom post type, use WPGraphQL TaxonomyEnum.
			$fields['taxQuery'] = array( 'type' => $this->register_types( $type_name, $type_registry, 'TaxonomyEnum' ) );
			return $fields;
		}
		// phpcs:ignore
		extract( $parsed );
		$post_taxonomies = wp_filter_object_list( get_object_taxonomies( $post_type->name, 'objects' ), array( 'show_in_graphql' => true ) );
		if ( empty( $post_taxonomies ) ) {
			return $fields;
		}
		// Create a new TaxonomyEnum specific for this type.
		$taxonomy_enum          = $this->create_taxonomy_enum( $to_type, array_values( $post_taxonomies ), $type_registry );
		$fields['taxQuery']     = array( 'type' => $this->register_types( $type_name, $type_registry, $taxonomy_enum ) );
		$custom_post_taxonomies = wp_filter_object_list( $post_taxonomies, array( '_builtin' => false ) );
		if ( ! empty( $custom_post_taxonomies ) ) {
			foreach ( $custom_post_taxonomies as $tax_name => $tax ) {
				$this->register_tax_fields( $tax, $fields );
			}
		}
		return $fields;
	}

	/**
	 * Register taxonomy fields for the given taxonomy
	 *
	 * @param WP_Taxonomy $tax - The custom taxonomy.
	 * @param array       $fields - The fields (reference).
	 * @return void
	 */
	private function register_tax_fields( $tax, &$fields ) {
		$fields[ $tax->graphql_single_name ]                          = array( 'type' => 'String' );
		$this->tax_queries[ $tax->graphql_single_name ]               = function ( $value ) use ( $tax ) {
			return array(
				'taxonomy' => $tax->name,
				'operator' => 'IN',
				'field'    => 'slug',
				'terms'    => array( $value ),
			);
		};
		$fields[ $tax->graphql_single_name . 'Id' ]                   = array( 'type' => 'Int' );
		$this->tax_queries[ $tax->graphql_single_name . 'Id' ]        = function ( $value ) use ( $tax ) {
			return array(
				'taxonomy' => $tax->name,
				'operator' => 'IN',
				'field'    => 'term_id',
				'terms'    => array( $value ),
			);
		};
		$fields[ $tax->graphql_single_name . 'And' ]                  = array( 'type' => array( 'list_of' => 'Int' ) );
		$this->tax_queries[ $tax->graphql_single_name . 'And' ]       = function ( $value ) use ( $tax ) {
			return array(
				'taxonomy' => $tax->name,
				'operator' => 'AND',
				'field'    => 'term_id',
				'terms'    => $value,
			);
		};
		$fields[ $tax->graphql_single_name . 'In' ]                   = array( 'type' => array( 'list_of' => 'Int' ) );
		$this->tax_queries[ $tax->graphql_single_name . 'In' ]        = function ( $value ) use ( $tax ) {
			return array(
				'taxonomy' => $tax->name,
				'operator' => 'IN',
				'field'    => 'term_id',
				'terms'    => $value,
			);
		};
		$fields[ $tax->graphql_single_name . 'NotIn' ]                = array( 'type' => array( 'list_of' => 'Int' ) );
		$this->tax_queries[ $tax->graphql_single_name . 'NotIn' ]     = function ( $value ) use ( $tax ) {
			return array(
				'taxonomy' => $tax->name,
				'operator' => 'NOT IN',
				'field'    => 'term_id',
				'terms'    => $value,
			);
		};
		$fields[ $tax->graphql_single_name . 'SlugAnd' ]              = array( 'type' => array( 'list_of' => 'String' ) );
		$this->tax_queries[ $tax->graphql_single_name . 'SlugAnd' ]   = function ( $value ) use ( $tax ) {
			return array(
				'taxonomy' => $tax->name,
				'operator' => 'AND',
				'field'    => 'slug',
				'terms'    => $value,
			);
		};
		$fields[ $tax->graphql_single_name . 'SlugIn' ]               = array( 'type' => array( 'list_of' => 'String' ) );
		$this->tax_queries[ $tax->graphql_single_name . 'SlugIn' ]    = function ( $value ) use ( $tax ) {
			return array(
				'taxonomy' => $tax->name,
				'operator' => 'IN',
				'field'    => 'slug',
				'terms'    => $value,
			);
		};
		$fields[ $tax->graphql_single_name . 'SlugNotIn' ]            = array( 'type' => array( 'list_of' => 'String' ) );
		$this->tax_queries[ $tax->graphql_single_name . 'SlugNotIn' ] = function ( $value ) use ( $tax ) {
			return array(
				'taxonomy' => $tax->name,
				'operator' => 'NOT IN',
				'field'    => 'slug',
				'terms'    => $value,
			);
		};
	}

	/**
	 * Parse type name and return the post_type, graphql from_type and graphql to_type
	 *
	 * @param string $type_name - GraphQL Input Type.
	 * @return false|array - if the parse is successful an array of (post_type, to_type, from_type).
	 */
	private function parse_type_name( $type_name ) {
		if ( preg_match( '/(.*)To(.*)ConnectionWhereArgs/', $type_name, $matches ) ) {
			$post_type = $this->get_post_type( $matches[2] );
			if ( $post_type ) {
				return array(
					'post_type' => $post_type,
					'to_type'   => $matches[2],
					'from_type' => $matches[1],
				);
			}
		}
		return false;
	}

	/**
	 * Check whether the current request uses WP_Query
	 *
	 * @param array $config - The configuration.
	 * @return boolean - true if WP_Query is used.
	 */
	private function is_query_type( $config ) {
		return isset( $config['queryClass'] ) && 'WP_Query' === $config['queryClass'];
	}

	/**
	 * Create and register enum for the allowed taxonomies for the given type.
	 *
	 * @param string       $to_type - The to_type from the request.
	 * @param array        $allowed_taxonomies - The list of allowed_taxonomies for the post_type.
	 * @param TypeRegistry $type_registry - The type registry.
	 * @return string - The registered enum name.
	 */
	private function create_taxonomy_enum( $to_type, $allowed_taxonomies, $type_registry ) {
		$type_name = $to_type . 'TaxonomyEnum';
		if ( $type_registry->get_type( $type_name ) === null ) {
			$values = array();

			if ( ! empty( $allowed_taxonomies ) && is_array( $allowed_taxonomies ) ) {
				foreach ( $allowed_taxonomies as $taxonomy_object ) {
					if ( ! isset( $values[ WPEnumType::get_safe_name( $taxonomy_object->graphql_single_name ) ] ) ) {
						$values[ WPEnumType::get_safe_name( $taxonomy_object->graphql_single_name ) ] = array(
							'value'       => $taxonomy_object->name,
							// translators: the name of the taxonomy.
							'description' => sprintf( __( 'Taxonomy enum %s', 'wp-graphql' ), $taxonomy_object->name ),
						);
					}
				}
			}

			$type_registry->register_enum_type(
				$type_name,
				array(
					'description' => __( 'Allowed taxonomies', 'wp-graphql' ),
					'values'      => $values,
				)
			);
		}
		return $type_name;
	}

	/**
	 * Given the GraphQL Type name retrieve the \WP_Post object
	 *
	 * @param string $to_type - The GraphQL Type name.
	 * @return false|WP_Post
	 */
	private function get_post_type( $to_type ) {
		$post_types = get_post_types( array(), 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( isset( $post_type->graphql_single_name ) && \strcasecmp( $to_type, $post_type->graphql_single_name ) === 0 ) {
				return $post_type;
			}
		}
		return false;
	}

	/**
	 * Register query field type
	 *
	 * @param TypeRegistry $type_registry - The type registry.
	 * @return [String] - The type name
	 */
	private function register_query_field_type( $type_registry ) {
		$query_field_type = 'TaxQueryField';
		if ( $type_registry->get_type( $query_field_type ) === null ) {
			$type_registry->register_enum_type(
				$query_field_type,
				array(
					'description' => __( 'Which field to select taxonomy term by. Default value is "term_id"', 'wp-graphql' ),
					'values'      => array(
						'ID'          => array(
							'name'  => 'ID',
							'value' => 'term_id',
						),
						'NAME'        => array(
							'name'  => 'NAME',
							'value' => 'name',
						),
						'SLUG'        => array(
							'name'  => 'SLUG',
							'value' => 'slug',
						),
						'TAXONOMY_ID' => array(
							'name'  => 'TAXONOMY_ID',
							'value' => 'term_taxonomy_id',
						),
					),
				)
			);
		}
		return $query_field_type;
	}

	/**
	 * Register Operator field type
	 *
	 * @param TypeRegistry $type_registry - The type registry.
	 * @return String - the type name.
	 */
	private function register_operator_type( $type_registry ) {
		$operator_type = 'TaxQueryOperator';
		if ( $type_registry->get_type( $operator_type ) === null ) {
			$type_registry->register_enum_type(
				$operator_type,
				array(
					'values' => array(
						'IN'         => array(
							'name'  => 'IN',
							'value' => 'IN',
						),
						'NOT_IN'     => array(
							'name'  => 'NOT_IN',
							'value' => 'NOT IN',
						),
						'AND'        => array(
							'name'  => 'AND',
							'value' => 'AND',
						),
						'EXISTS'     => array(
							'name'  => 'EXISTS',
							'value' => 'EXISTS',
						),
						'NOT_EXISTS' => array(
							'name'  => 'NOT_EXISTS',
							'value' => 'NOT EXISTS',
						),
					),
				)
			);
		}
		return $operator_type;
	}

	/**
	 * Register types required for supporting tax_query
	 *
	 * @param String       $type_name - The GraphQL type name.
	 * @param TypeRegistry $type_registry - The type registry.
	 * @param String       $taxonomy_enum - The taxonomy enum to be used for the type.
	 * @return String - the registered type name
	 */
	private function register_types( $type_name, TypeRegistry $type_registry, $taxonomy_enum ) {
		$query_field_type = $this->register_query_field_type( $type_registry );
		$operator_type    = $this->register_operator_type( $type_registry );

		$type_registry->register_input_type(
			$type_name . 'Parameters',
			array(
				'fields' => array(
					'taxonomy'        => array(
						'type' => $taxonomy_enum,
					),
					'field'           => array(
						'type' => $query_field_type,
					),
					'terms'           => array(
						'type' => array( 'list_of' => 'String' ),
					),
					'includeChildren' => array(
						'type' => 'Boolean',
					),
					'operator'        => array(
						'type' => $operator_type,
					),
				),
			)
		);

		$tax_query_type = $type_name . 'TaxQuery';

		$type_registry->register_input_type(
			$tax_query_type,
			array(
				'fields' => array(
					'relation'         => array(
						'type' => 'RelationEnum',
					),
					'relationOperands' => array(
						'type' => array( 'list_of' => $tax_query_type ),
					),
					'parameters'       => array(
						'type' => $type_name . 'Parameters',
					),
				),
			)
		);

		return $tax_query_type;
	}

	/**
	 * Map the input fields to WP_Query
	 *
	 * @param Array $query_args - The incoming query arguments.
	 * @param Array $input_args - The incoming input arguments.
	 * @return Array - modified query arguments.
	 */
	public function map_input_fields( $query_args, $input_args ) {
		$tax_queries = array();
		if ( ! empty( $this->tax_queries ) ) {
			foreach ( $this->tax_queries as $field_name => $field_fun ) {
				if ( ! empty( $input_args[ $field_name ] ) ) {
					$tax_queries[] = $field_fun( $input_args[ $field_name ] );
				}
			}
		}
		if ( ! empty( $input_args['taxQuery'] ) ) {
			$tax_query = $this->create_tax_query( $input_args['taxQuery'] );
			if ( ! empty( $tax_query ) ) {
				unset( $query_args['taxQuery'] );
				$tax_queries[] = $tax_query;
			}
		}

		if ( ! empty( $tax_queries ) ) {
			if ( \count( $tax_queries ) > 1 ) {
				$tax_queries['relation'] = 'AND';
			}
			$query_args['tax_query'] = $tax_queries; // phpcs:ignore
			\graphql_debug( array( 'query' => $tax_queries ) );
		}
		return $query_args;
	}

	/**
	 * Create tax_query element for WP_Query
	 *
	 * @param Array $input - The input arguments.
	 * @param Array $query - The built query.
	 * @return Array - the created query
	 */
	private function create_tax_query( $input, &$query = array() ) {
		if ( isset( $input['relation'] ) ) {
			$query['relation'] = $input['relation'];
			if ( isset( $input['relationOperands'] ) ) {
				foreach ( $input['relationOperands'] as $k => $op ) {
					\array_push( $query, $this->create_tax_query( $op ) );
				}
			}
		} elseif ( isset( $input['parameters'] ) ) {
			$value = $input['parameters'];
			if ( ! empty( $value['terms'] ) ) {
				if ( ! empty( $value['field'] ) && ( 'term_id' === $value['field'] || 'term_taxonomy_id' === $value['field'] ) ) {
					$formatted_terms = array();
					foreach ( $value['terms'] as $term ) {
						\array_push( $formatted_terms, intval( $term ) );
					}
					$value['terms'] = $formatted_terms;
				}
			}

			\array_push( $query, $value );
		}
		return $query;
	}
}

add_action(
	'graphql_register_types',
	function () {
		new TaxQuery();
	}
);
