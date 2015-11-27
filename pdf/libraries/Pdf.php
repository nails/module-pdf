<?php

/**
 * This class brings PDF Generation functionality to Nails
 *
 * @package     Nails
 * @subpackage  module-pdf
 * @category    Library
 * @author      Nails Dev Team
 * @link
 */

use Nails\Factory;

class Pdf
{
    use \Nails\Common\Traits\ErrorHandling;
    use \Nails\Common\Traits\Caching;

    // --------------------------------------------------------------------------

    private $oCi;
    private $oDomPdf;
    private $sDefaultFilename;
    protected $iInstantiateCount;

    // --------------------------------------------------------------------------

    /**
     * Construct the PDF library
     */
    public function __construct()
    {
        $this->oCi               =& get_instance();
        $this->sDefaultFilename  = 'document.pdf';
        $this->iInstantiateCount = 0;
    }

    // --------------------------------------------------------------------------

    /**
     * Defines a DOMPDF constant, if not already defined
     * @param  string $sConstant The name of the constant
     * @param  string $mValue    The value to give the constant
     * @return boolean
     */
    static function setConfig($sConstant, $mValue)
    {
        if (!defined($sConstant)) {

            define($sConstant, $mValue);
            return true;

        } else {

            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Alias for setting the paper size
     * @param  string $sSize        The paper size to use
     * @param  string $sOrientation The paper orientation
     * @return void
     */
    public function setPaperSize($sSize = 'A4', $sOrientation = 'portrait')
    {
        if ($this->iInstantiateCount == 0) {

            $this->instantiate();
        }

        $this->oDomPdf->set_paper($sSize, $sOrientation);
    }

    // --------------------------------------------------------------------------

    /**
     * Alias for enabling or disabling remote resources in PDFs
     * @param  boolean $bValue whether to enable remote resources
     * @return boolean
     */
    static function enableRemote($bValue = true)
    {
        return self::setConfig('DOMPDF_ENABLE_REMOTE', $bValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Instantiates a new instance of DOMPDF
     * @return void
     */
    protected function instantiate()
    {
        if ($this->iInstantiateCount == 0) {

            //  Load and configure DOMPDF
            self::setConfig('DOMPDF_ENABLE_AUTOLOAD', false);
            self::setConfig('DOMPDF_DEFAULT_PAPER_SIZE', 'A4');
            self::setConfig('DOMPDF_ENABLE_REMOTE', true);
            self::setConfig('DOMPDF_FONT_DIR', DEPLOY_CACHE_DIR . 'dompdf/font/dir/');
            self::setConfig('DOMPDF_FONT_CACHE', DEPLOY_CACHE_DIR . 'dompdf/font/cache/');

            // --------------------------------------------------------------------------

            /**
             * Test the cache dirs exists and are writable.
             * ============================================
             * If the caches aren't there or writable log this as an error,
             * no need to halt execution as the cache *might* not be used. If
             * on a production environment errors will be muted and shouldn't affect
             * anything; but make a not in the logs regardless
             */

            if (!is_dir(DOMPDF_FONT_DIR)) {

                //  Not a directory, attempt to create
                if (!@mkdir(DOMPDF_FONT_DIR, 0777, true)) {

                    //  Couldn't create. Sad Panda
                    log_message(
                        'error',
                        'DOMPDF\'s cache folder doesn\'t exist, and I couldn\'t create it: ' . DOMPDF_FONT_DIR
                    );
                }

            } elseif (!is_really_writable(DOMPDF_FONT_DIR)) {

                //  Couldn't write. Sadder Panda
                log_message(
                    'error',
                    'DOMPDF\'s cache folder exists, but I couldn\'t write to it: ' . DOMPDF_FONT_DIR
                );
            }

            if (!is_dir(DOMPDF_FONT_CACHE)) {

                //  Not a directory, attempt to create
                if (!@mkdir(DOMPDF_FONT_CACHE, 0777, true)) {

                    //  Couldn't create. Sad Panda
                    log_message(
                        'error',
                        'DOMPDF\'s cache folder doesn\'t exist, and I couldn\'t create it: ' . DOMPDF_FONT_CACHE
                    );
                }

            } elseif (!is_really_writable(DOMPDF_FONT_CACHE)) {

                //  Couldn't write. Sadder Panda
                log_message(
                    'error',
                    'DOMPDF\'s cache folder exists, but I couldn\'t write to it: ' . DOMPDF_FONT_CACHE
                );
            }

            require_once FCPATH . '/vendor/dompdf/dompdf/dompdf_config.inc.php';
        }

        $this->oDomPdf = new DOMPDF();
        $this->iInstantiateCount++;
    }

    // --------------------------------------------------------------------------

    /**
     * Loads CI views and passes it as HTML to DOMPDF
     * @param  mixed $mViews An array of views, or a single view as a string
     * @param  array $aData  View variables to pass to the view
     * @return void
     */
    public function loadView($mViews, $aData = array())
    {
        $sHtml  = '';
        $aViews = (array) $mViews;
        $aViews = array_filter($aViews);

        foreach ($aViews as $sView) {

            $sHtml .= $this->oCi->load->view($sView, $aData, true);
        }

        if ($this->iInstantiateCount == 0) {
            $this->instantiate();
        }

        $this->oDomPdf->load_html($sHtml);
    }

    // --------------------------------------------------------------------------

    /**
     * Renders the PDF and sends it to the browser as a download.
     * @param  string $sFilename The filename to give the PDF
     * @param  array  $aOptions  An array of options to pass to DOMPDF's stream() method
     * @return void
     */
    public function download($sFilename = '', $aOptions = array())
    {
        $sFilename = $sFilename ? $sFilename : $this->sDefaultFilename;

        //  Set the content attachment, by default send to the browser
        $aOptions['Attachment'] = 1;

        if ($this->iInstantiateCount == 0) {

            $this->instantiate();
        }

        try {

            $this->oDomPdf->render();
            $this->oDomPdf->stream($sFilename, $aOptions);
            exit();

        } catch(Exception $e) {

            $this->setError($e->getMessage());
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Renders the PDF and sends it to the browser as an inline PDF.
     * @param  string $sFilename The filename to give the PDF
     * @param  array  $aOptions  An array of options to pass to DOMPDF's stream() method
     * @return void
     */
    public function stream($sFilename = '', $aOptions = array())
    {
        $sFilename = $sFilename ? $sFilename : $this->sDefaultFilename;

        //  Set the content attachment, by default send to the browser
        $aOptions['Attachment'] = 0;

        if ($this->iInstantiateCount == 0) {

            $this->instantiate();
        }

        $this->oDomPdf->render();
        $this->oDomPdf->stream($sFilename, $aOptions);
        exit();
    }

    // --------------------------------------------------------------------------

    /**
     * Saves a PDF to disk
     * @param  string $sPath     The path to save to
     * @param  string $sFilename The filename to give the PDF
     * @return boolean
     */
    public function save($sPath, $sFilename)
    {
        if (!is_writable($sPath)) {

            $this->setError('Cannot write to ' . $sPath);
            return false;
        }

        if ($this->iInstantiateCount == 0) {

            $this->instantiate();
        }

        $sPath = rtrim($sPath, '/') . '/';

        $this->oDomPdf->render();
        return (bool) file_put_contents($sPath . $sFilename, $this->oDomPdf->output());
    }

    // --------------------------------------------------------------------------

    /**
     * Saves the PDF to the CDN
     * @param  string $sFilename The filename to give the object
     * @param  string $sBucket   The name of the bucket to upload to
     * @return mixed             stdClass on success, false on failure
     */
    public function saveToCdn($sFilename, $sBucket = null)
    {
        if (isModuleEnabled('nailsapp/module-cdn')) {

            //  Save temporary file
            $sCacheFile = 'TEMP-PDF-' . md5(uniqid() . microtime(true)) . '.pdf';
            if ($this->save(DEPLOY_CACHE_DIR, $sCacheFile)) {

                $oCdn = Factory::service('Cdn', 'nailsapp/module-cdn');
                $oResult = $oCdn->objectCreate(
                    DEPLOY_CACHE_DIR . $sCacheFile,
                    $sBucket,
                    array('filename_display' => $sFilename)
                );

                if ($oResult) {

                	@unlink(DEPLOY_CACHE_DIR . $sCacheFile);
                    return $oResult;

                } else {

                    $this->setError($oCdn->lastError());
                    return false;
                }

            } else {
                return false;
            }

        } else {

            $this->setError('CDN module is not available');
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Unsets the instance of DOMPDF and reinstanciates
     * @return void
     */
    public function reset()
    {
        unset($this->oDomPdf);
        $this->instantiate();
    }

    // --------------------------------------------------------------------------

    /**
     * MagicMethod routes any method calls to this class to DOMPDF if it exists
     * @param  string $sMethod    The method called
     * @param  array  $aArguments Any arguments passed
     * @return mixed
     */
    public function __call($sMethod, $aArguments = array())
    {
        if (method_exists($this->oDomPdf, $sMethod)) {

            return call_user_func_array(array($this->oDomPdf, $sMethod), $aArguments);

        } else {

            throw new exception('Call to undefined method Pdf::' . $sMethod . '()');
        }
    }
}
