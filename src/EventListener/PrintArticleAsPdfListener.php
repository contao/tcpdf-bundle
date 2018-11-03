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

use Contao\ModuleArticle;
use Symfony\Component\HttpFoundation\RequestStack;

class PrintArticleAsPdfListener
{
    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var string
     */
    private $rootDir;

    public function __construct(RequestStack $requestStack, string $rootDir)
    {
        $this->requestStack = $requestStack;
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
        $l['a_meta_charset'] = \Config::get('characterSet');
        $l['a_meta_language'] = substr($GLOBALS['TL_LANGUAGE'], 0, 2);
        $l['w_page'] = 'page';

        $this->defineTcpdfConstants();

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
        $pdf->Output(\StringUtil::standardize(ampersand($module->title, false)).'.pdf', 'D');

        // Stop script execution
        exit;
    }

    private function defineTcpdfConstants(): void
    {
        if (\defined('K_TCPDF_EXTERNAL_CONFIG')) {
            return;
        }

        if (!$request = $this->requestStack->getCurrentRequest()) {
            throw new \RuntimeException('The request stack did not contain a request');
        }

        $baseUrl = $request->getHttpHost().$request->getBasePath();

        \define('K_TCPDF_EXTERNAL_CONFIG', true);
        \define('K_PATH_MAIN', $this->rootDir.'/vendor/tecnickcom/tcpdf/');
        \define('K_PATH_URL', $baseUrl.'vendor/tecnickcom/tcpdf/');
        \define('K_PATH_FONTS', K_PATH_MAIN.'fonts/');
        \define('K_PATH_CACHE', $this->rootDir.'/system/tmp/');
        \define('K_PATH_URL_CACHE', TL_ROOT.'/system/tmp/');
        \define('K_PATH_IMAGES', K_PATH_MAIN.'images/');
        \define('K_BLANK_IMAGE', K_PATH_IMAGES.'_blank.png');
        \define('PDF_PAGE_FORMAT', 'A4');
        \define('PDF_PAGE_ORIENTATION', 'P');
        \define('PDF_CREATOR', 'Contao Open Source CMS');
        \define('PDF_AUTHOR', $baseUrl);
        \define('PDF_HEADER_TITLE', '');
        \define('PDF_HEADER_STRING', '');
        \define('PDF_HEADER_LOGO', '');
        \define('PDF_HEADER_LOGO_WIDTH', 30);
        \define('PDF_UNIT', 'mm');
        \define('PDF_MARGIN_HEADER', 0);
        \define('PDF_MARGIN_FOOTER', 0);
        \define('PDF_MARGIN_TOP', 10);
        \define('PDF_MARGIN_BOTTOM', 10);
        \define('PDF_MARGIN_LEFT', 15);
        \define('PDF_MARGIN_RIGHT', 15);
        \define('PDF_FONT_NAME_MAIN', 'freeserif');
        \define('PDF_FONT_SIZE_MAIN', 12);
        \define('PDF_FONT_NAME_DATA', 'freeserif');
        \define('PDF_FONT_SIZE_DATA', 12);
        \define('PDF_FONT_MONOSPACED', 'freemono');
        \define('PDF_FONT_SIZE_MONOSPACED', 10); // PATCH
        \define('PDF_IMAGE_SCALE_RATIO', 1.25);
        \define('HEAD_MAGNIFICATION', 1.1);
        \define('K_CELL_HEIGHT_RATIO', 1.25);
        \define('K_TITLE_MAGNIFICATION', 1.3);
        \define('K_SMALL_RATIO', 2 / 3);
        \define('K_THAI_TOPCHARS', false);
        \define('K_TCPDF_CALLS_IN_HTML', false);
    }
}
