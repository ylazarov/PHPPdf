<?php

/*
 * Copyright 2011 Piotr Śliwa <peter.pl7@gmail.com>
 *
 * License information is in LICENSE file
 */

namespace PHPPdf\Engine\ZF;

use PHPPdf\Util;

use PHPPdf\Exception\InvalidResourceException;

use PHPPdf\Engine\GraphicsContext as BaseGraphicsContext,
    PHPPdf\Engine\Engine as BaseEngine;

/**
 * @author Piotr Śliwa <peter.pl7@gmail.com>
 */
class Engine implements BaseEngine
{
    private static $loadedEngines = array();
    
    private $zendPdf = null;
    private $colors = array();
    private $images = array();
    private $graphicsContexts = array();
    
    public function __construct(\Zend_Pdf $zendPdf = null)
    {
        $this->zendPdf = $zendPdf ? : new \Zend_Pdf();
    }
    
    public function createGraphicsContext($graphicsContextSize)
    {
        $page = new \Zend_Pdf_Page($graphicsContextSize);
        
        $gc = new GraphicsContext($this, $page);
        
        return $gc;
    }
    
    public function attachGraphicsContext(BaseGraphicsContext $gc)
    {
        $this->zendPdf->pages[] = $gc->getPage();
        $this->graphicsContexts[] = $gc;
    }
    
    public function getAttachedGraphicsContexts()
    {
        return $this->graphicsContexts;
    }
    
    /**
     * @return Color
     */
    public function createColor($data)
    {
        $data = (string) $data;

        if(!isset($this->colors[$data]))
        {
            $this->colors[$data] = new Color($data);
        }
        
        return $this->colors[$data];
    }
    
    /**
     * @return Image
     */
    public function createImage($data)
    {
        $data = (string) $data;

        if(!isset($this->images[$data]))
        {
            $this->images[$data] = new Image($data);
        }
        
        return $this->images[$data];
    }
    
    /**
     * @return Font
     */
    public function createFont($fontData)
    {
        return new Font($fontData);
    }
    
    public function render()
    {
        $this->zendPdf->properties['Producer'] = sprintf('PHPPdf %s', \PHPPdf\Version::VERSION);

        return $this->zendPdf->render();
    }
    
    /**
     * @return \Zend_Pdf
     */
    public function getZendPdf()
    {
        return $this->zendPdf;
    }
    
    public function registerOutline($id, \Zend_Pdf_Outline $outline)
    {
        $this->outlines[$id] = $outline;
    }
    
    public function getOutline($id)
    {
        return $this->outlines[$id];
    }
    
    public function loadEngine($file)
    {
        if(isset(self::$loadedEngines[$file]))
        {
            return self::$loadedEngines[$file];
        }
        
        if(!is_readable($file))
        {
            InvalidResourceException::fileDosntExistException($file);
        }

        try
        {
            $pdf = \Zend_Pdf::load($file);
            $engine = new self($pdf);
            
            foreach($pdf->pages as $page)
            {
                $gc = new GraphicsContext($engine, $page);
                $engine->attachGraphicsContext($gc);
            }
            
            self::$loadedEngines[$file] = $engine;
            
            return $engine;
        }
        catch(\Zend_Pdf_Exception $e)
        {
            InvalidResourceException::invalidPdfFileException($file, $e);
        }
    }
    
    public function setMetadataValue($name, $value)
    {
        switch($name)
        {
            case 'Trapped':
                $value = $value === 'null' ? null : Util::convertBooleanValue($value);
                $this->zendPdf->properties[$name] = $value;
                break;
            case 'CreationDate':
            case 'ModDate':
                $value = \Zend_Pdf::pdfDate(strtotime($value));
                $this->zendPdf->properties[$name] = $value;
                break;
            case 'Title':
            case 'Author':
            case 'Subject':
            case 'Keywords':
            case 'Creator':
                $this->zendPdf->properties[$name] = $value;
                break;
        }
    }
}