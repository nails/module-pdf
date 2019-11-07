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

namespace Nails\Pdf\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Nails\Common\Exception\FactoryException;
use Nails\Common\Service\FileCache;
use Nails\Common\Traits\Caching;
use Nails\Common\Traits\ErrorHandling;
use Nails\Cdn;
use Nails\Environment;
use Nails\Factory;
use Nails\Pdf\Exception\PdfException;

class Pdf
{
    use ErrorHandling;
    use Caching;

    // --------------------------------------------------------------------------

    /**
     * The default name to give documents
     */
    const DEFAULT_FILE_NAME = 'document.pdf';

    /**
     * The default options, loaded on construction
     */
    const DEFAULT_OPTIONS = [
        'defaultPaperSize'     => 'A4',
        'isRemoteEnabled'      => true,
        'fontDir'              => null, //  Declared dynamically
        'fontCache'            => null, //  Declared dynamically
        'isHtml5ParserEnabled' => true,
    ];

    /**
     * The default paper size
     */
    const DEFAULT_PAPER_SIZE = 'A4';

    /**
     * The default paper orientation
     */
    const DEFAULT_PAPER_ORIENTATION = 'portrait';

    // --------------------------------------------------------------------------

    /**
     * Holds a reference to DomPDF
     *
     * @var Dompdf
     */
    protected $oDomPdf;

    /**
     * Stores any custom options as applied by the user so that they are maintained across instances
     *
     * @var array
     */
    protected $aCustomOptions = [];

    /**
     * Stores the current paper size
     *
     * @var string
     */
    protected $sPaperSize = '';

    /**
     * Stores the current paper orientation
     *
     * @var string
     */
    protected $sPaperOrientation = '';

    // --------------------------------------------------------------------------

    /**
     * Construct the PDF library
     *
     * @throws FactoryException
     */
    public function __construct()
    {
        $this->instantiate();
    }

    // --------------------------------------------------------------------------

    /**
     * Instantiate a new instance of DomPDF
     *
     * @return $this
     * @throws FactoryException
     */
    protected function instantiate()
    {
        $oOptions = new Options();
        $aOptions = [];

        /** @var FileCache $oFileCache */
        $oFileCache = Factory::service('FileCache');

        //  Default options
        foreach (self::DEFAULT_OPTIONS as $sOption => $mValue) {
            $aOptions[$sOption] = $mValue;
        }

        $sDirFont  = implode(DIRECTORY_SEPARATOR, ['dompdf', 'font', 'dir',]) . DIRECTORY_SEPARATOR;
        $sDirCache = implode(DIRECTORY_SEPARATOR, ['dompdf', 'font', 'cache',]) . DIRECTORY_SEPARATOR;

        $aOptions['fontDir']   = $oFileCache->getDir() . $sDirFont;
        $aOptions['fontCache'] = $oFileCache->getDir() . $sDirCache;

        //  Custom options override default options
        foreach ($this->aCustomOptions as $sOption => $mValue) {
            $aOptions[$sOption] = $mValue;
        }

        //  Set the options
        foreach ($aOptions as $sOption => $mValue) {
            $oOptions->set($sOption, $mValue);
        }

        $this->oDomPdf = new Dompdf($oOptions);
        $this->setPaperSize($this->sPaperSize, $this->sPaperOrientation);

        //  Define the HTTPContext, allow insecure on dev
        if (Environment::is(Environment::ENV_DEV)) {
            $this->oDomPdf->setHttpContext(
                stream_context_create([
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ])
            );
        }

        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Unset the instance of DomPDF and re-instantiate
     *
     * @return $this
     * @throws FactoryException
     */
    public function reset()
    {
        unset($this->oDomPdf);
        $this->instantiate();
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Defines a DomPDF constant, if not already defined
     *
     * @param string $sOption The name of the option
     * @param string $mValue  The value to give the option
     *
     * @return $this;
     */
    public function setOption($sOption, $mValue)
    {
        $this->aCustomOptions[$sOption] = $mValue;
        $this->oDomPdf->set_option($sOption, $mValue);
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Alias to setOption()
     *
     * @param string $sOption The name of the option
     * @param string $mValue  The value to give the option
     *
     * @return $this
     * @deprecated
     */
    public function setConfig($sOption, $mValue)
    {
        return $this->setOption($sOption, $mValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Alias for setting the paper size
     *
     * @param string $sSize        The paper size to use
     * @param string $sOrientation The paper orientation
     *
     * @return $this
     */
    public function setPaperSize($sSize = null, $sOrientation = null)
    {
        $this->sPaperSize        = $sSize ?: static::DEFAULT_PAPER_SIZE;
        $this->sPaperOrientation = $sOrientation ?: static::DEFAULT_PAPER_ORIENTATION;
        $this->oDomPdf->setPaper($this->sPaperSize, $this->sPaperOrientation);
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Alias for enabling or disabling remote resources in PDFs
     *
     * @param boolean $bValue Whether to enable remote resources
     *
     * @return $this
     */
    public function enableRemote($bValue = true)
    {
        $this->setOption('isRemoteEnabled', $bValue);
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Loads CI views and passes it as HTML to DomPDF
     *
     * @param mixed $mViews An array of views, or a single view as a string
     * @param array $aData  View variables to pass to the view
     *
     * @return void
     * @throws FactoryException
     */
    public function loadView($mViews, $aData = [])
    {
        $sHtml  = '';
        $aViews = array_filter((array) $mViews);
        $oView  = Factory::service('View');

        foreach ($aViews as $sView) {
            $sHtml .= $oView->load($sView, $aData, true);
        }

        $this->oDomPdf->loadHtml($sHtml);
    }

    // --------------------------------------------------------------------------

    /**
     * Renders the PDF and sends it to the browser as a download.
     *
     * @param string $sFilename The filename to give the PDF
     * @param array  $aOptions  An array of options to pass to DomPDF's stream() method
     *
     * @return void|bool
     */
    public function download($sFilename = '', $aOptions = [])
    {
        $sFilename = $sFilename ? $sFilename : self::DEFAULT_FILE_NAME;

        //  Set the content attachment, by default send to the browser
        $aOptions['Attachment'] = 1;

        $this->oDomPdf->render();
        $this->oDomPdf->stream($sFilename, $aOptions);
        exit();
    }

    // --------------------------------------------------------------------------

    /**
     * Renders the PDF and sends it to the browser as an inline PDF.
     *
     * @param string $sFilename The filename to give the PDF
     * @param array  $aOptions  An array of options to pass to DomPDF's stream() method
     *
     * @return void
     */
    public function stream($sFilename = '', $aOptions = [])
    {
        $sFilename = $sFilename ? $sFilename : self::DEFAULT_FILE_NAME;

        //  Set the content attachment, by default send to the browser
        $aOptions['Attachment'] = 0;

        $this->oDomPdf->render();
        $this->oDomPdf->stream($sFilename, $aOptions);
        exit();
    }

    // --------------------------------------------------------------------------

    /**
     * Saves a PDF to disk
     *
     * @param string $sPath     The path to save to
     * @param string $sFilename The filename to give the PDF
     *
     * @return boolean
     * @throws FactoryException
     */
    public function save($sPath, $sFilename)
    {
        if (!is_writable($sPath)) {
            $this->setError('Cannot write to ' . $sPath);
            return false;
        }

        $this->oDomPdf->render();
        $bResult = (bool) file_put_contents(
            rtrim($sPath, '/') . '/' . $sFilename,
            $this->oDomPdf->output()
        );

        $this->reset();

        return $bResult;
    }

    // --------------------------------------------------------------------------

    /**
     * Saves the PDF to the CDN
     *
     * @param string $sFilename The filename to give the object
     * @param string $sBucket   The name of the bucket to upload to
     *
     * @return mixed             stdClass on success, false on failure
     * @throws FactoryException
     */
    public function saveToCdn($sFilename, $sBucket = null)
    {
        //  Save temporary file
        $sCacheDir  = CACHE_PATH;
        $sCacheFile = 'TEMP-PDF-' . md5(uniqid() . microtime(true)) . '.pdf';
        if ($this->save($sCacheDir, $sCacheFile)) {

            $oCdn    = Factory::service('Cdn', Cdn\Constants::MODULE_SLUG);
            $oResult = $oCdn->objectCreate(
                $sCacheDir . $sCacheFile,
                $sBucket,
                ['filename_display' => $sFilename]
            );

            if ($oResult) {

                @unlink($sCacheDir . $sCacheFile);
                $this->reset();
                return $oResult;

            } else {
                $this->setError($oCdn->lastError());
                return false;
            }

        } else {
            return false;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * MagicMethod routes any method calls to this class to DomPDF if it exists
     *
     * @param string $sMethod    The method called
     * @param array  $aArguments Any arguments passed
     *
     * @return mixed
     * @throws PdfException
     */
    public function __call($sMethod, $aArguments = [])
    {
        if (method_exists($this->oDomPdf, $sMethod)) {
            return call_user_func_array([$this->oDomPdf, $sMethod], $aArguments);
        } else {
            throw new PdfException('Call to undefined method Pdf::' . $sMethod . '()');
        }
    }
}
