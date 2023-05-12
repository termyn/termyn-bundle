<?php

declare(strict_types=1);

namespace Termyn\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;

final class TermynBundle extends Bundle implements BundleInterface
{
    public function getContainerExtension(): TermynExtension
    {
        return new TermynExtension();
    }
}
