<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS
 * @Project URI: http://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * @package engine.modules
 * @since   1.0
 */
class ModuleViewerAsset_EntityPackageCss extends ModuleViewerAsset_EntityPackage {

    protected $sOutType = 'css';

    public function Init() {

        $this->aHtmlLinkParams = array(
            'tag'  => 'link',
            'attr' => array(
                'type' => 'text/css',
                'rel'  => 'stylesheet',
                'href' => '@link',
            ),
            'pair' => false,
        );
    }

    /**
     * Создает css-компрессор и инициализирует его конфигурацию
     *
     * @return bool
     */
    protected function InitCompressor() {

        if (Config::Get('compress.css.use')) {
            F::IncludeLib('CSSTidy-1.3/class.csstidy.php');
            // * Получаем параметры из конфигурации
            $this->oCompressor = new csstidy();

            if ($this->oCompressor) {
                $aParams = Config::Get('compress.css.csstidy');
                // * Устанавливаем параметры
                foreach ($aParams as $sKey => $sVal) {
                    if ($sKey == 'template') {
                        $this->oCompressor->load_template($sVal);
                    } else {
                        $this->oCompressor->set_cfg('case_properties', $sVal);
                    }
                }
                return true;
            }
        }
        return false;
    }

    public function Compress($sContents) {

        $this->oCompressor->parse($sContents);
        $sContents = $this->oCompressor->print->plain();
        return $sContents;
    }

    /**
     * @param string $sDestination
     *
     * @return bool
     */
    public function CheckDestination($sDestination) {

        if (Config::Get('compress.css.force')) {
            return false;
        }
        return parent::CheckDestination($sDestination);
    }

    public function PreProcess() {

        if ($this->aFiles) {
            $this->InitCompressor();
        }
        parent::PreProcess();
    }

    public function Process() {

        foreach ($this->aLinks as $nIdx => $aLinkData) {
            if (isset($aLinkData['compress']) && $aLinkData['compress']) {
                $sFile = $aLinkData['file'];
                $sExtension = 'min.' . F::File_GetExtension($sFile);
                $sCompressedFile = F::File_SetExtension($sFile, $sExtension);
                if (!$this->CheckDestination($sCompressedFile)) {
                    if (($sContents = F::File_GetContents($sFile))) {
                        $sContents = $this->Compress($sContents);
                        if (F::File_PutContents($sCompressedFile, $sContents)) {
                            F::File_Delete($sFile);
                            $this->aLinks[$nIdx]['link'] = F::File_SetExtension($this->aLinks[$nIdx]['link'], $sExtension);
                        }
                    }
                } else {
                    $this->aLinks[$nIdx]['link'] = F::File_SetExtension($this->aLinks[$nIdx]['link'], $sExtension);
                }
            }
        }
    }

    public function PrepareFile($sFile, $sDestination) {

        $sContents = F::File_GetContents($sFile);
        if ($sContents !== false) {
            $sContents = $this->PrepareContents($sContents, $sFile, $sDestination);
            if (F::File_PutContents($sDestination, $sContents) !== false) {
                return $sDestination;
            }
        }
        F::SysWarning('Can not prepare asset file "' . $sFile . '"');
    }

    public function PrepareContents($sContents, $sSource) {

        if ($sContents) {
            $sContents = $this->_convertUrlsInCss($sContents, dirname($sSource) . '/');
        }
        return $sContents;
    }

    protected function _convertUrlsInCss($sContent, $sSourceDir) {

        // Есть ли в файле URLs
        if (!preg_match_all('/(?<src>src:)?url\((?<url>.*?)\)/is', $sContent, $aMatchedUrl, PREG_OFFSET_CAPTURE)) {
            return $sContent;
        }

        // * Обрабатываем список URLs
        $aUrls = array();
        foreach ($aMatchedUrl['url'] as $nIdx => $aPart) {
            $sPath = $aPart[0];
            //$nPos = $aPart[1];

            // * Don't touch data URIs
            if (strstr($sPath, 'data:')) {
                continue;
            }
            $sPath = str_replace(array('\'', '"'), '', $sPath);

            // * Если путь является абсолютным, то не обрабатываем
            if (substr($sPath, 0, 1) == "/" || substr($sPath, 0, 5) == 'http:' || substr($sPath, 0, 6) == 'https:') {
                continue;
            }

            if ($n = strpos($sPath, '?')) {
                $sPath = substr($sPath, 0, $n);
                $sFileParam = substr($sPath, $n);
            } else {
                $sFileParam = '';
            }
            $sRealPath = realpath($sSourceDir . $sPath);
            $sDestination = $this->Viewer_GetAssetDir() . $this->_crc(dirname($sRealPath)) . '/' . basename($sRealPath);
            $aUrls[$sPath] = array(
                'source'      => $sRealPath,
                'destination' => $sDestination,
                'url'         => F::File_Dir2Url($sDestination) . $sFileParam,
            );
            F::File_Copy($sRealPath, $sDestination);
        }
        if ($aUrls) {
            $sContent = str_replace(array_keys($aUrls), F::Array_Column($aUrls, 'url'), $sContent);
        }

        return $sContent;
    }

}

// EOF