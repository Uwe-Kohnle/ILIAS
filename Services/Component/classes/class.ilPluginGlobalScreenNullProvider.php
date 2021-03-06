<?php

use ILIAS\GlobalScreen\Scope\MainMenu\Provider\AbstractStaticPluginMainMenuProvider;
use ILIAS\GlobalScreen\Scope\MainMenu\Provider\StaticMainMenuProvider;

/**
 * Class ilPluginGlobalScreenNullProvider
 *
 * @author Fabian Schmid <fs@studer-raimann.ch>
 */
final class ilPluginGlobalScreenNullProvider extends AbstractStaticPluginMainMenuProvider implements StaticMainMenuProvider
{

    /**
     * @inheritDoc
     */
    public function getAllIdentifications() : array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getStaticTopItems() : array
    {
        return [];
    }


    /**
     * @inheritDoc
     */
    public function getStaticSubItems() : array
    {
        return [];
    }
}
