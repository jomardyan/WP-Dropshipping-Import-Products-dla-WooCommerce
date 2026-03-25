<?php
defined( 'ABSPATH' ) || exit;

/**
 * Line-by-line CSV feed parser. Yields one product record at a time.
 * Handles BOM, auto-delimiter detection, and header mapping.
 */
class DIP_CSV_Parser {

	private string $file_path;
	private string $delimiter;
	private string $enclosure;
	private bool   $has_header;

	public function __construct(
		string $file_path,
		string $delimiter  = ',',
		string $enclosure  = '"',
		bool   $has_header = true
	) {
		$this->file_path  = $file_path;
		$this->delimiter  = $delimiter;
		$this->enclosure  = $enclosure;
		$this->has_header = $has_header;
	}

	/**
	 * Parse the CSV and yield records as associative (keyed by header) or indexed arrays.
	 *
	 * @return \Generator<int, array<string,string>>
	 */
	public function parse(): \Generator {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $this->file_path, 'r' );
		if ( ! $handle ) {
			return;
		}

		self::strip_bom( $handle );

		$headers = null;
		$index   = 0;

		while ( false !== ( $row = fgetcsv( $handle, 0, $this->delimiter, $this->enclosure ) ) ) {
			if ( null === $row ) {
				continue;
			}

			if ( $this->has_header && null === $headers ) {
				$headers = array_map( 'trim', $row );
				continue;
			}

			if ( $headers ) {
				$record = [];
				foreach ( $headers as $i => $header ) {
					$record[ $header ] = $row[ $i ] ?? '';
				}
			} else {
				$record = $row;
			}

			yield $index => $record;
			$index++;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );
	}

	/**
	 * Detect delimiter by counting common candidates in the first line.
	 */
	public static function detect_delimiter( string $file_path ): string {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return ',';
		}
		self::strip_bom( $handle );
		$line = fgets( $handle );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );
		if ( ! $line ) {
			return ',';
		}
		$counts = [
			','  => substr_count( $line, ',' ),
			';'  => substr_count( $line, ';' ),
			"\t" => substr_count( $line, "\t" ),
			'|'  => substr_count( $line, '|' ),
		];
		arsort( $counts );
		return (string) array_key_first( $counts );
	}

	/**
	 * Return header row field names.
	 *
	 * @return list<string>
	 */
	public static function get_field_names( string $file_path, string $delimiter = ',' ): array {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return [];
		}
		self::strip_bom( $handle );
		$row = fgetcsv( $handle, 0, $delimiter );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $handle );
		return $row ? array_map( 'trim', $row ) : [];
	}

	/**
	 * Return the first N records as a preview.
	 *
	 * @return list<array<string,string>>
	 */
	public static function preview( string $file_path, int $limit = 5, string $delimiter = ',' ): array {
		$parser  = new self( $file_path, $delimiter );
		$records = [];
		foreach ( $parser->parse() as $record ) {
			$records[] = $record;
			if ( count( $records ) >= $limit ) {
				break;
			}
		}
		return $records;
	}

	/**
	 * Skip UTF-8 BOM if present.
	 *
	 * @param resource $handle
	 */
	private static function strip_bom( $handle ): void {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		$bom = fread( $handle, 3 );
		if ( "\xEF\xBB\xBF" !== $bom ) {
			fseek( $handle, 0 );
		}
	}
}
