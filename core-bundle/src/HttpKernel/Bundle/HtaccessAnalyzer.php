<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @license LGPL-3.0+
 */

namespace Contao\CoreBundle\HttpKernel\Bundle;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Analyzes an .htaccess file.
 *
 * @author Leo Feyer <https://contao.org>
 */
class HtaccessAnalyzer
{
    /**
     * @var SplFileInfo
     */
    protected $file;

    /**
     * Stores the file object.
     *
     * @param SplFileInfo $file The file object
     *
     * @throws \RuntimeException If the file is not readable
     */
    public function __construct(SplFileInfo $file)
    {
        if (!$file->isReadable()) {
            throw new \RuntimeException("File $file not readable");
        }

        $this->file = $file;
    }

    /**
     * Checks whether the .htaccess file grants access via HTTP.
     *
     * @param SplFileInfo $file The file object
     *
     * @return bool True if the .htaccess file grants access via HTTP
     */
    public function grantsAcces()
    {
        $content = array_filter(file($this->file));

        foreach ($content as $line) {
            if ($this->hasRequireGranted($line)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scans a line for an access definition.
     *
     * @param string $line The line
     *
     * @return bool True if the line has an access definition
     */
    protected function hasRequireGranted($line)
    {
        // Ignore comments
        if (0 === strncmp('#', trim($line), 1)) {
            return false;
        }

        if (false !== stripos($line, 'Allow from all')) {
            return true;
        }

        if (false !== stripos($line, 'Require all granted')) {
            return true;
        }

        return false;
    }
}
