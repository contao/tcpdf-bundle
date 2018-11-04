<?php

declare(strict_types=1);

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\TcpdfBundle\EventListener;

use Contao\Config;
use Contao\ModuleArticle;
use Contao\StringUtil;

class PrintArticleAsPdfListener
{
    /**
     * @var string
     */
    private $rootDir;

    public function __construct(string $rootDir)
    {
        $this->rootDir = $rootDir;
    }

    public function onPrintArticleAsPdf($article, ModuleArticle $module): void
    {
        // URL decode image paths (see #6411)
        $article = preg_replace_callback(
            '@(src="[^"]+")@',
            function ($arg) {
                return rawurldecode($arg[0]);
            },
            $article
        );

        // Handle line breaks in preformatted text
        $article = preg_replace_callback(
            '@(<pre.*</pre>)@Us',
            function ($arg) {
                return str_replace("\n", '<br>', $arg[0]);
            },
            $article
        );

        // Default PDF export using TCPDF
        $search = [
            '@<span style="text-decoration: ?underline;?">(.*)</span>@Us',
            '@(<img[^>]+>)@',
            '@(<div[^>]+block[^>]+>)@',
            '@[\n\r\t]+@',
            '@<br( /)?><div class="mod_article@',
            '@href="([^"]+)(pdf=[0-9]*(&|&amp;)?)([^"]*)"@',
        ];

        $replace = [
            '<u>$1</u>',
            '<br>$1',
            '<br>$1',
            ' ',
            '<div class="mod_article',
            'href="$1$4"',
        ];

        $article = preg_replace($search, $replace, $article);

        // TCPDF configuration
        $l['a_meta_dir'] = 'ltr';
        $l['a_meta_charset'] = Config::get('characterSet');
        $l['a_meta_language'] = substr($GLOBALS['TL_LANGUAGE'], 0, 2);
        $l['w_page'] = 'page';

        // Include the config file
        include_once $this->rootDir.'/system/config/tcpdf.php';

        // Create new PDF document
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true);

        // Set document information
        $pdf->SetCreator(PDF_CREATOR);
        $pdf->SetAuthor(PDF_AUTHOR);
        $pdf->SetTitle($module->title);
        $pdf->SetSubject($module->title);
        $pdf->SetKeywords($module->keywords);

        // Prevent font subsetting (huge speed improvement)
        $pdf->setFontSubsetting(false);

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);

        // Set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // Set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        // Set some language-dependent strings
        $pdf->setLanguageArray($l);

        // Initialize document and add a page
        $pdf->AddPage();

        // Set font
        $pdf->SetFont(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN);

        // Write the HTML content
        $pdf->writeHTML($article, true, 0, true, 0);

        // Close and output PDF document
        $pdf->lastPage();
        $pdf->Output(StringUtil::standardize(preg_replace('/&(amp;)?/i', '&', $module->title)).'.pdf', 'D');

        // Stop script execution
        exit;
    }
}
