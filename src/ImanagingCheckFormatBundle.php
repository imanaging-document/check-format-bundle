<?php


namespace Imanaging\CheckFormatBundle;

use Imanaging\CheckFormatBundle\DependencyInjection\ImanagingCheckFormatExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class ImanagingCheckFormatBundle extends Bundle
{
  /**
   * Overridden to allow for the custom extension alias.
   */
  public function getContainerExtension() : ?ImanagingCheckFormatExtension
  {
    if (null === $this->extension) {
      $this->extension = new ImanagingCheckFormatExtension();
    }
    return $this->extension;
  }
}