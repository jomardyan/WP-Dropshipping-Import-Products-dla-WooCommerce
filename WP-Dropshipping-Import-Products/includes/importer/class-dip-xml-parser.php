<?php
defined( 'ABSPATH' ) || exit;

/**
 * Memory-efficient XML feed parser using XMLReader streaming.
 * Yields one product record at a time as an associative array.
 */
class DIP_XML_Parser {

	private string $file_path;
	private string $item_node;

	/**
	 * @param string $file_path Path to the local XML file.
	 * @param string $item_node XML element name that represents one product record.
	 */
	public function __construct( string $file_path, string $item_node = 'product' ) {
		$this->file_path = $file_path;
		$this->item_node = $item_node;
	}

	/**
	 * Parse the XML file and yield records as associative arrays.
	 *
	 * @return \Generator<int, array<string,mixed>>
	 */
	public function parse(): \Generator {
		$reader = new \XMLReader();
		if ( ! $reader->open( $this->file_path ) ) {
			return;
		}

		$index = 0;
		while ( $reader->read() ) {
			if ( \XMLReader::ELEMENT === $reader->nodeType && $reader->localName === $this->item_node ) {
				$dom  = new \DOMDocument( '1.0', 'UTF-8' );
				$node = $reader->expand( $dom );
				if ( $node ) {
					$dom->appendChild( $node );
					yield $index => $this->node_to_array( $node );
					$index++;
				}
			}
		}
		$reader->close();
	}

	/**
	 * Heuristically detect the repeating item node name by sampling 300 elements.
	 * Returns the element name at depth-1 that appears most frequently.
	 */
	public static function detect_item_node( string $file_path ): string {
		$reader = new \XMLReader();
		if ( ! $reader->open( $file_path ) ) {
			return 'product';
		}

		/** @var array<int, array<string,int>> $depth_count */
		$depth_count = [];
		$max_depth   = 0;
		$i           = 0;

		while ( $reader->read() && $i < 300 ) {
			if ( \XMLReader::ELEMENT === $reader->nodeType ) {
				$depth = $reader->depth;
				$name  = $reader->localName;
				if ( $depth > $max_depth ) {
					$max_depth = $depth;
				}
				$depth_count[ $depth ][ $name ] = ( $depth_count[ $depth ][ $name ] ?? 0 ) + 1;
			}
			$i++;
		}
		$reader->close();

		// Repeated element at (max_depth - 1) is the item container
		$target_depth = max( 0, $max_depth - 1 );
		if ( ! empty( $depth_count[ $target_depth ] ) ) {
			arsort( $depth_count[ $target_depth ] );
			return (string) array_key_first( $depth_count[ $target_depth ] );
		}

		return 'product';
	}

	/**
	 * Return a flat list of field names (dot-notation) from the first record.
	 *
	 * @return list<string>
	 */
	public static function get_field_names( string $file_path, string $item_node ): array {
		$parser = new self( $file_path, $item_node );
		foreach ( $parser->parse() as $record ) {
			return array_keys( self::flatten( $record ) );
		}
		return [];
	}

	/**
	 * Return first N records as preview.
	 *
	 * @return list<array<string,mixed>>
	 */
	public static function preview( string $file_path, string $item_node, int $limit = 5 ): array {
		$parser  = new self( $file_path, $item_node );
		$records = [];
		foreach ( $parser->parse() as $record ) {
			$records[] = $record;
			if ( count( $records ) >= $limit ) {
				break;
			}
		}
		return $records;
	}

	// ── Private helpers ───────────────────────────────────────────────────────

	/**
	 * Convert a DOMNode into a nested associative array.
	 *
	 * @return array<string,mixed>|string
	 */
	private function node_to_array( \DOMNode $node ) {
		$result = [];

		// Node attributes
		if ( $node->hasAttributes() && $node->attributes ) {
			foreach ( $node->attributes as $attr ) {
				$result[ '@' . $attr->name ] = $attr->value;
			}
		}

		if ( ! $node->hasChildNodes() ) {
			return $result ?: '';
		}

		// Detect whether node has element children
		$has_element_child = false;
		foreach ( $node->childNodes as $child ) {
			if ( \XML_ELEMENT_NODE === $child->nodeType ) {
				$has_element_child = true;
				break;
			}
		}

		foreach ( $node->childNodes as $child ) {
			if ( \XML_TEXT_NODE === $child->nodeType || \XML_CDATA_SECTION_NODE === $child->nodeType ) {
				$text = trim( $child->nodeValue ?? '' );
				if ( '' !== $text ) {
					if ( ! $has_element_child && empty( $result ) ) {
						return $text;
					}
					$result['#text'] = $text;
				}
			} elseif ( \XML_ELEMENT_NODE === $child->nodeType ) {
				$name  = $child->localName;
				$value = $this->node_to_array( $child );
				if ( isset( $result[ $name ] ) ) {
					if ( ! is_array( $result[ $name ] ) || ! array_is_list( $result[ $name ] ) ) {
						$result[ $name ] = [ $result[ $name ] ];
					}
					$result[ $name ][] = $value;
				} else {
					$result[ $name ] = $value;
				}
			}
		}

		return $result;
	}

	/**
	 * Flatten nested array to dot-notation keys.
	 *
	 * @param array<string,mixed> $array
	 * @return array<string,mixed>
	 */
	private static function flatten( array $array, string $prefix = '' ): array {
		$result = [];
		foreach ( $array as $key => $value ) {
			$new_key = $prefix ? "{$prefix}.{$key}" : (string) $key;
			if ( is_array( $value ) ) {
				$result = array_merge( $result, self::flatten( $value, $new_key ) );
			} else {
				$result[ $new_key ] = $value;
			}
		}
		return $result;
	}
}
