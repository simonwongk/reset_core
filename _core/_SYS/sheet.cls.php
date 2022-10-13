<?php
defined( 'IN' ) || exit( 'Access Denied' );

class sheet extends instance {
	private $_reader_xlsx;
	private $_reader_xls;
	private $_spreadsheet;

	protected function __construct() {
		require_once '/var/www/vendor/autoload.php';

		$this->_reader_xlsx = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
		$this->_reader_xlsx->setReadDataOnly(true);

		$this->_reader_xls = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
		$this->_reader_xls->setReadDataOnly(true);
	}

	/**
	 * Load xlsx file
	 */
	public function load_xlsx( $path ) {
		return $this->_reader_xlsx->load( $path );
	}

	/**
	 * Load xls file
	 */
	public function load_xls( $path ) {
		return $this->_reader_xls->load( $path );
	}

	/**
	 * Print all sheets
	 */
	public function show_all_sheets() {
		$count = $this->_spreadsheet->getSheetCount();

		foreach( $this->_spreadsheet->getSheetNames() as $k => $v ) {
			$worksheet = $this->_spreadsheet->getSheet( $k );

			echo '<table class="table">' . PHP_EOL;
			foreach ($worksheet->getRowIterator() as $row) {
				echo '<tr>' . PHP_EOL;
				$cellIterator = $row->getCellIterator();
				$cellIterator->setIterateOnlyExistingCells(FALSE);
				foreach ($cellIterator as $cell) {
					echo '<td>' . $cell->getValue() . '</td>' . PHP_EOL;
				}
				echo '</tr>' . PHP_EOL;
			}
			echo '</table>' . PHP_EOL;
		}

	}

}