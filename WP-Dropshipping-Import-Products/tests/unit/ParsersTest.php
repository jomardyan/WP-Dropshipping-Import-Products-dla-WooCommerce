<?php

declare( strict_types=1 );

namespace DIP\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for CSV and XML parsers.
 */
class ParsersTest extends TestCase {

	private string $tmp_dir;

	protected function setUp(): void {
		$this->tmp_dir = sys_get_temp_dir();
	}

	private function write_tmp( string $filename, string $content ): string {
		$path = $this->tmp_dir . '/' . $filename;
		file_put_contents( $path, $content );
		return $path;
	}

	protected function tearDown(): void {
		// Clean up any remaining temp test files.
		array_map( 'unlink', glob( $this->tmp_dir . '/dip_test_*' ) ?: [] );
	}

	// ── CSV Parser ───────────────────────────────────────────────────────────

	public function test_csv_parses_header_and_rows(): void {
		$content = "name,sku,price\nWidget,W-001,9.99\nGadget,G-002,14.99\n";
		$path    = $this->write_tmp( 'dip_test_csv.csv', $content );

		$parser  = new \DIP_CSV_Parser( $path );
		$records = iterator_to_array( $parser->parse() );

		$this->assertCount( 2, $records );
		$this->assertSame( 'Widget', $records[0]['name'] );
		$this->assertSame( 'W-001', $records[0]['sku'] );
		$this->assertSame( '9.99', $records[0]['price'] );
		$this->assertSame( 'Gadget', $records[1]['name'] );
	}

	public function test_csv_strips_utf8_bom(): void {
		$bom     = "\xEF\xBB\xBF";
		$content = $bom . "name,sku\nProduct,P-001\n";
		$path    = $this->write_tmp( 'dip_test_bom.csv', $content );

		$parser  = new \DIP_CSV_Parser( $path );
		$records = iterator_to_array( $parser->parse() );

		$this->assertCount( 1, $records );
		// BOM should not pollute the first header key.
		$this->assertArrayHasKey( 'name', $records[0] );
	}

	public function test_csv_detect_delimiter_comma(): void {
		$content = "a,b,c\n1,2,3\n";
		$path    = $this->write_tmp( 'dip_test_delim_comma.csv', $content );
		$this->assertSame( ',', \DIP_CSV_Parser::detect_delimiter( $path ) );
	}

	public function test_csv_detect_delimiter_semicolon(): void {
		$content = "a;b;c\n1;2;3\n";
		$path    = $this->write_tmp( 'dip_test_delim_semi.csv', $content );
		$this->assertSame( ';', \DIP_CSV_Parser::detect_delimiter( $path ) );
	}

	public function test_csv_detect_delimiter_tab(): void {
		$content = "a\tb\tc\n1\t2\t3\n";
		$path    = $this->write_tmp( 'dip_test_delim_tab.csv', $content );
		$this->assertSame( "\t", \DIP_CSV_Parser::detect_delimiter( $path ) );
	}

	public function test_csv_get_field_names(): void {
		$content = "name,sku,price\nWidget,W-001,9.99\n";
		$path    = $this->write_tmp( 'dip_test_fields.csv', $content );
		$fields  = \DIP_CSV_Parser::get_field_names( $path );
		$this->assertSame( [ 'name', 'sku', 'price' ], $fields );
	}

	public function test_csv_preview_returns_limited_rows(): void {
		$lines   = "name,sku\n";
		for ( $i = 1; $i <= 10; $i++ ) {
			$lines .= "Product {$i},P-{$i}\n";
		}
		$path    = $this->write_tmp( 'dip_test_preview.csv', $lines );
		$records = \DIP_CSV_Parser::preview( $path, 3 );
		$this->assertCount( 3, $records );
	}

	public function test_csv_splits_newline_multivalue_cells(): void {
		// IOF-style: cell contains multiple values separated by \n.
		$content = "name,image\n" . 'Widget,"https://a.com/1.jpg' . "\nhttps://a.com/2.jpg\"\n";
		$path    = $this->write_tmp( 'dip_test_multivalue.csv', $content );

		$parser  = new \DIP_CSV_Parser( $path );
		$records = iterator_to_array( $parser->parse() );

		$this->assertCount( 1, $records );
		$this->assertIsArray( $records[0]['image'] );
		$this->assertCount( 2, $records[0]['image'] );
	}

	// ── XML Parser ───────────────────────────────────────────────────────────

	public function test_xml_parses_simple_records(): void {
		$xml = <<<XML
		<?xml version="1.0" encoding="UTF-8"?>
		<products>
			<product>
				<name>Widget</name>
				<sku>W-001</sku>
				<price>9.99</price>
			</product>
			<product>
				<name>Gadget</name>
				<sku>G-002</sku>
				<price>14.99</price>
			</product>
		</products>
		XML;
		$path    = $this->write_tmp( 'dip_test_xml.xml', $xml );

		$parser  = new \DIP_XML_Parser( $path, 'product' );
		$records = iterator_to_array( $parser->parse() );

		$this->assertCount( 2, $records );
		$this->assertSame( 'Widget', $records[0]['name'] );
		$this->assertSame( 'W-001', $records[0]['sku'] );
		$this->assertSame( 'Gadget', $records[1]['name'] );
	}

	public function test_xml_detect_item_node(): void {
		$xml = <<<XML
		<?xml version="1.0" encoding="UTF-8"?>
		<catalog>
			<product><id>1</id></product>
			<product><id>2</id></product>
			<product><id>3</id></product>
		</catalog>
		XML;
		$path = $this->write_tmp( 'dip_test_detect.xml', $xml );
		$node = \DIP_XML_Parser::detect_item_node( $path );
		$this->assertSame( 'product', $node );
	}

	public function test_xml_get_field_names(): void {
		$xml = <<<XML
		<?xml version="1.0" encoding="UTF-8"?>
		<products>
			<product>
				<name>Widget</name>
				<sku>W-001</sku>
			</product>
		</products>
		XML;
		$path   = $this->write_tmp( 'dip_test_fieldnames.xml', $xml );
		$fields = \DIP_XML_Parser::get_field_names( $path, 'product' );
		$this->assertContains( 'name', $fields );
		$this->assertContains( 'sku', $fields );
	}

	public function test_xml_preview_returns_limited_records(): void {
		$xml = '<?xml version="1.0" encoding="UTF-8"?><products>';
		for ( $i = 1; $i <= 10; $i++ ) {
			$xml .= "<product><id>{$i}</id></product>";
		}
		$xml .= '</products>';

		$path    = $this->write_tmp( 'dip_test_xmlpreview.xml', $xml );
		$records = \DIP_XML_Parser::preview( $path, 'product', 3 );
		$this->assertCount( 3, $records );
	}

	public function test_xml_parses_nested_elements(): void {
		$xml = <<<XML
		<?xml version="1.0" encoding="UTF-8"?>
		<products>
			<product>
				<name>Nested Widget</name>
				<stock>
					<qty>25</qty>
					<warehouse>A</warehouse>
				</stock>
			</product>
		</products>
		XML;
		$path    = $this->write_tmp( 'dip_test_nested.xml', $xml );
		$parser  = new \DIP_XML_Parser( $path, 'product' );
		$records = iterator_to_array( $parser->parse() );

		$this->assertCount( 1, $records );
		$this->assertSame( 'Nested Widget', $records[0]['name'] );
		// Nested structure accessible via array
		$this->assertIsArray( $records[0]['stock'] );
		$this->assertSame( '25', $records[0]['stock']['qty'] );
	}

	public function test_xml_handles_empty_file_gracefully(): void {
		$path    = $this->write_tmp( 'dip_test_empty.xml', '' );
		$parser  = new \DIP_XML_Parser( $path, 'product' );
		$records = iterator_to_array( $parser->parse() );
		$this->assertCount( 0, $records );
	}

	public function test_csv_handles_empty_file_gracefully(): void {
		$path    = $this->write_tmp( 'dip_test_empty.csv', '' );
		$parser  = new \DIP_CSV_Parser( $path );
		$records = iterator_to_array( $parser->parse() );
		$this->assertCount( 0, $records );
	}
}
