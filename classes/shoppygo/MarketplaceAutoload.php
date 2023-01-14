<?php
/**
 * Copyright since 2022 Bwlab of Luigi Massa and Contributors
 * Bwlab of Luigi Massa is an Italy Company
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@shoppygo.io so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade ShoppyGo to newer
 * versions in the future. If you wish to customize ShoppyGo for your
 * needs please refer to https://docs.shoppygo.io/ for more information.
 *
 * @author    Bwlab and Contributors <contact@shoppygo.io>
 * @copyright Since 2022 Bwlab of Luigi Massa and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */
class MarketplaceAutoload extends PrestaShopAutoload
{
    public function generateIndex()
    {
        if (class_exists('Configuration') && defined('_PS_CREATION_DATE_')) {
            $creationDate = _PS_CREATION_DATE_;
            if (!empty($creationDate) && Configuration::get('PS_DISABLE_OVERRIDES')) {
                $this->_include_override_path = false;
            } else {
                $this->_include_override_path = true;
            }
        }

        $coreClasses = $this->getClassesFromDir('classes/');

        $classes = array_merge(
            $coreClasses,
            $this->getClassesFromDir('controllers/')
        );

        $contentNamespacedStub = '<?php '."\n".'namespace PrestaShop\\PrestaShop\\Adapter\\Entity;'."\n\n";

        foreach ($coreClasses as $coreClassName => $coreClass) {
            if (substr($coreClassName, -4) == 'Core') {
                $coreClassName = substr($coreClassName, 0, -4);
                if ($coreClass['type'] != 'interface') {
                    $contentNamespacedStub .= $coreClass['type'].' '.$coreClassName.' extends \\'.$coreClassName.' {};'.
                        "\n";
                }
            }
        }

        if ($this->_include_override_path) {
            $coreOverrideClasses = $this->getClassesFromDir('override/classes/');
            $coreClassesWOOverrides = array_diff_key($coreClasses, $coreOverrideClasses);

            $classes = array_merge(
                $classes,
                $coreOverrideClasses,
                $this->getClassesFromDir('override/controllers/')
            );
        } else {
            $coreClassesWOOverrides = $coreClasses;
        }

        $contentStub = '<?php'."\n\n";

        foreach ($coreClassesWOOverrides as $coreClassName => $coreClass) {
            if (substr($coreClassName, -4) == 'Core') {
                $coreClassNameNoCore = substr($coreClassName, 0, -4);
                if ($coreClass['type'] != 'interface') {
                    $contentStub .= $coreClass['type'].' '.$coreClassNameNoCore.' extends '.$coreClassName.' {};'."\n";
                }
            }
        }

        ksort($classes);

        $content = '<?php return '.var_export($this->replaceControllerCore($classes), true).'; ?>';

        // Write classes index on disc to cache it
        $filename = static::getCacheFileIndex();
        @mkdir(_PS_CACHE_DIR_, 0777, true);

        if (!$this->dumpFile($filename, $content)) {
            Tools::error_log('Cannot write temporary file '.$filename);
        }

        $stubFilename = static::getStubFileIndex();
        if (!$this->dumpFile($stubFilename, $contentStub)) {
            Tools::error_log('Cannot write temporary file '.$stubFilename);
        }

        $namespacedStubFilename = static::getNamespacedStubFileIndex();
        if (!$this->dumpFile($namespacedStubFilename, $contentNamespacedStub)) {
            Tools::error_log('Cannot write temporary file '.$namespacedStubFilename);
        }

        $this->index = $classes;
    }

    private function replaceControllerCore(array $classes): array
    {
        $classes['HistoryControllerCore']['path'] = 'controllers/front/marketplace/MarketplaceHistoryController.php';
        $classes['OrderCore']['path'] = 'classes/shoppygo/markeplace/MarketplaceOrderCore.php';
        return $classes;
    }
}

spl_autoload_register([MarketplaceAutoload::getInstance(), 'load']);

