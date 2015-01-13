<?php defined('BASEPATH') OR exit('No direct script access allowed');

class Pdf
{
	//	Class traits
	use NAILS_COMMON_TRAIT_ERROR_HANDLING;
	use NAILS_COMMON_TRAIT_CACHING;

	private $_ci;
	private $_dompdf;
	private $_default_filename;
	protected $_errors;
	protected $_instantiate_count;


	// --------------------------------------------------------------------------


	/**
	 * Construct the PDF library
	 */
	public function __construct()
	{
		$this->_ci =& get_instance();

		// --------------------------------------------------------------------------

		$this->_default_filename	= 'document.pdf';
		$this->_instantiate_count	= 0;
	}


	// --------------------------------------------------------------------------


	/**
	 * Defines a DOMPDF constant, if not already defined
	 * @param string $constant The name of the constant
	 * @param string $value    The value to give the constant
	 */
	static function set_config( $constant, $value )
	{
		if ( ! defined( $constant ) ) :

			define( $constant, $value );

		else :

			return FALSE;

		endif;
	}

	// --------------------------------------------------------------------------


	/**
	 * Alias for setting paper size
	 * @param string $value The size of paper to use
	 */
	public function set_paper_size( $size = 'A4', $orientation = 'portrait' )
	{
		if ( $this->_instantiate_count == 0 ) :

			$this->_instantiate();

		endif;

		// --------------------------------------------------------------------------

		$this->_dompdf->set_paper( $size, $orientation );
	}


	// --------------------------------------------------------------------------


	/**
	 * Alias for enabling or disabling remote resources in PDFs
	 * @param string $value The size of paper to use
	 */
	static function enable_remote( $value = TRUE )
	{
		return self::set_config( 'DOMPDF_ENABLE_REMOTE', $value );
	}


	// --------------------------------------------------------------------------


	/**
	 * Instantiates a new instance of DOMPDF
	 * @return void
	 */
	protected function _instantiate()
	{
		if ( $this->_instantiate_count == 0 ) :

			//	Load and configure DOMPDF
			self::set_config( 'DOMPDF_ENABLE_AUTOLOAD',		FALSE );
			self::set_config( 'DOMPDF_DEFAULT_PAPER_SIZE',	'A4' );
			self::set_config( 'DOMPDF_ENABLE_REMOTE',		TRUE );
			self::set_config( 'DOMPDF_FONT_DIR',			DEPLOY_CACHE_DIR . 'dompdf/font/dir/' );
			self::set_config( 'DOMPDF_FONT_CACHE',			DEPLOY_CACHE_DIR . 'dompdf/font/cache/' );

			// --------------------------------------------------------------------------

			/**
			 * Test the cache dirs exists and are writable.
			 * ============================================
			 * If the caches aren't there or writable log this as an error,
			 * no need to halt execution as the cache *might* not be used. If
			 * on a production environment errors will be muted and shouldn't affect
			 * anything; but make a not in the logs regardless
			 */

			if ( ! is_dir( DOMPDF_FONT_DIR ) ) :

				//	Not a directory, attempt to create
				if ( ! @mkdir( DOMPDF_FONT_DIR, 0777, TRUE ) ) :

					//	Couldn't create. Sad Panda
					log_message( 'error', 'DOMPDF\'s cache folder doesn\'t exist, and I couldn\'t create it: ' . DOMPDF_FONT_DIR );

				endif;

			elseif ( ! is_really_writable( DOMPDF_FONT_DIR ) ) :

				//	Couldn't write. Sadder Panda
				log_message( 'error', 'DOMPDF\'s cache folder exists, but I couldn\'t write to it: ' . DOMPDF_FONT_DIR );

			endif;

			if ( ! is_dir( DOMPDF_FONT_CACHE ) ) :

				//	Not a directory, attempt to create
				if ( ! @mkdir( DOMPDF_FONT_CACHE, 0777, TRUE ) ) :

					//	Couldn't create. Sad Panda
					log_message( 'error', 'DOMPDF\'s cache folder doesn\'t exist, and I couldn\'t create it: ' . DOMPDF_FONT_CACHE );

				endif;

			elseif ( ! is_really_writable( DOMPDF_FONT_CACHE ) ) :

				//	Couldn't write. Sadder Panda
				log_message( 'error', 'DOMPDF\'s cache folder exists, but I couldn\'t write to it: ' . DOMPDF_FONT_CACHE );

			endif;

			// --------------------------------------------------------------------------

			require_once FCPATH . '/vendor/dompdf/dompdf/dompdf_config.inc.php';

		endif;

		$this->_dompdf = new DOMPDF();
		$this->_instantiate_count++;
	}


	// --------------------------------------------------------------------------


	/**
	 * Loads CI views and passes it as HTML to DOMPDF
	 * @param  mixed $views An array of views, or a single view as a string
	 * @param  array  $data  View variables to pass to the view
	 * @return void
	 */
	public function load_view( $views, $data = array() )
	{
		$_html	= '';
		$views	= (array) $views;
		$views	= array_filter( $views );

		foreach ( $views AS $view ) :

			$_html .= $this->_ci->load->view( $view, $data, TRUE );

		endforeach;

		// --------------------------------------------------------------------------

		if ( $this->_instantiate_count == 0 ) :

			$this->_instantiate();

		endif;

		// --------------------------------------------------------------------------

		$this->_dompdf->load_html( $_html );
	}


	// --------------------------------------------------------------------------


	/**
	 * Renders the PDF and sends it to the browser as a download.
	 * @param  string $filename The filename to give the PDF
	 * @param  array $options  An array of options to pass to DOMPDF's stream() method
	 * @return void
	 */
	public function download( $filename = '', $options = NULL )
	{
		$filename = $filename ? $filename : $this->_default_filename;

		//	Set the content attachment, by default send to the browser
		if ( is_null( $options ) ) :

			$options = array();

		endif;

		$options['Attachment'] = 1;

		// --------------------------------------------------------------------------

		if ( $this->_instantiate_count == 0 ) :

			$this->_instantiate();

		endif;

		// --------------------------------------------------------------------------

		$this->_dompdf->render();
		$this->_dompdf->stream( $filename, $options );
		exit();
	}


	// --------------------------------------------------------------------------


	/**
	 * Renders the PDF and sends it to the browser as an inline PDF.
	 * @param  string $filename The filename to give the PDF
	 * @param  array $options  An array of options to pass to DOMPDF's stream() method
	 * @return void
	 */
	public function stream( $filename = '', $options = NULL )
	{
		$filename = $filename ? $filename : $this->_default_filename;

		//	Set the content attachment, by default send to the browser
		if ( is_null( $options ) ) :

			$options = array();

		endif;

		$options['Attachment'] = 0;

		// --------------------------------------------------------------------------

		if ( $this->_instantiate_count == 0 ) :

			$this->_instantiate();

		endif;

		// --------------------------------------------------------------------------

		$this->_dompdf->render();
		$this->_dompdf->stream( $filename, $options );
		exit();
	}


	// --------------------------------------------------------------------------


	/**
	 * Saves a PDF to disk
	 * @param  string $path     The path to save to
	 * @param  string $filename The filename to give the PDF
	 * @return boolean
	 */
	public function save( $path, $filename )
	{
		if ( ! is_writable( $path ) ) :

			$this->_set_error( 'Cannot write to ' . $path );
			return FALSE;

		endif;

		// --------------------------------------------------------------------------

		if ( $this->_instantiate_count == 0 ) :

			$this->_instantiate();

		endif;

		// --------------------------------------------------------------------------

		$this->_dompdf->render();
		return (bool) file_put_contents( $filename, $this->_dompdf->output() );
	}


	// --------------------------------------------------------------------------


	/**
	 * Unsets the instance of DOMPDF and reinstanciates
	 * @return void
	 */
	public function reset()
	{
		unset( $this->_dompdf );
		$this->_instantiate();
	}


	// --------------------------------------------------------------------------


	/**
	 * MagicMethod routes any method calls to this class to DOMPDF if it exists
	 * @param  string $method    The method called
	 * @param  array  $arguments Any arguments passed
	 * @return mixed
	 */
	public function __call( $method, $arguments = array() )
	{
		if ( method_exists( $this->_dompdf, $method ) ) :

			return call_user_func_array( array( $this->_dompdf, $method ), $arguments );

		else :

			throw new exception( 'Call to undefined method Pdf::' . $method . '()' );

		endif;
	}
}

/* End of file Pdf.php */
/* Location: ./module-pdf/pdf/libraries/Pdf.php */