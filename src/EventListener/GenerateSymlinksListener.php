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

use Contao\CoreBundle\Event\GenerateSymlinksEvent;

class GenerateSymlinksListener
{
    public function onGenerateSymlinks(GenerateSymlinksEvent $event): void
    {
        $event->addSymlink(
            'vendor/contao/tcpdf-bundle/src/Resources/contao/config/tcpdf.php',
            'system/config/tcpdf.php'
        );
    }
}
