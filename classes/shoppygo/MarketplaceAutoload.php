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
    private $front_controller_shoppygo = 'controllers/front/shoppygo/marketplace/';
    private $classes_shoppygo_marketplace = 'classes/shoppygo/marketplace/';

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
        $classes = $this->replaceControllerCore($classes);

        $content = '<?php return '.var_export($classes, true).'; ?>';

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

    public function load($className)
    {
        // Retrocompatibility
        if (isset(static::$class_aliases[$className]) && !interface_exists($className, false) &&
            !class_exists($className, false)) {
            return $this->getEval($className);
        }

        // regenerate the class index if the requested file doesn't exists
        if ((isset($this->index[$className]) && $this->index[$className]['path'] &&
                !is_file($this->root_dir.$this->index[$className]['path'])) ||
            (isset($this->index[$className.'Core']) && $this->index[$className.'Core']['path'] &&
                !is_file($this->root_dir.$this->index[$className.'Core']['path'])) ||
            !file_exists(static::getNamespacedStubFileIndex())) {
            $this->generateIndex();
        }

        // If $classname has not core suffix (E.g. Shop, Product)
        if (substr($className, -4) != 'Core' && !class_exists($className, false)) {
            $classDir = (isset($this->index[$className]['override']) &&
                $this->index[$className]['override'] === true) ? $this->normalizeDirectory(
                _PS_ROOT_DIR_
            ) : $this->root_dir;

            // If requested class does not exist, load associated core class
            if (isset($this->index[$className]) && !$this->index[$className]['path']) {
                require_once $classDir.$this->index[$className.'Core']['path'];
                $this->loadMarketplaceCoreClass($className, $classDir);

                if ($this->index[$className.'Core']['type'] != 'interface') {
                    $marketplacePrefixClass = $this->getMarketplacePrefixClass($className);
                    $this->getEvalCore($className, $marketplacePrefixClass);
                }
            } else {
                // request a non Core Class load the associated Core class if exists
                if (isset($this->index[$className.'Core'])) {
                    require_once $this->root_dir.$this->index[$className.'Core']['path'];
                }

                if (isset($this->index[$className])) {
                    require_once $classDir.$this->index[$className]['path'];
                }
            }
        } elseif (isset($this->index[$className]['path']) && $this->index[$className]['path']) {
            // Call directly ProductCore, ShopCore class
            require_once $this->root_dir.$this->index[$className]['path'];
        }
        if (strpos($className, 'PrestaShop\PrestaShop\Adapter\Entity') !== false) {
            require_once static::getNamespacedStubFileIndex();
        }
    }

    private function getEval(string $className): mixed
    {
        return eval('class '.$className.' extends '.static::$class_aliases[$className].' {}');
    }

    private function getEvalCore(string $className, string $marketplacePrefixClass): void
    {
        eval(
            $this->index[$className.'Core']['type'].' '.$className.' extends '.$marketplacePrefixClass.$className.
            'Core {}'
        );
    }

    private function getMarketplacePrefixClass(string $className): string
    {
        $marketplacePrefixClass = '';
        if (isset($this->index[$className.'Core']['marketplace']) === true &&
            $this->index[$className.'Core']['marketplace'] === true) {
            $marketplacePrefixClass = '\Marketplace';
        }

        return $marketplacePrefixClass;
    }

    private function loadMarketplaceCoreClass(string $className, string $classDir): void
    {
        if ($this->isMarketplaceClass($className) === true) {
            if ($this->isControllerClass($className) === true) {
                require_once $classDir.$this->front_controller_shoppygo.'Marketplace'.$className.'.php';
            } else {
                require_once $classDir.$this->classes_shoppygo_marketplace.'Marketplace'.$className.'.php';
            }
        }
    }

    private function isControllerClass($classname)
    {
        if (isset($this->index[$classname.'Core']['controller']) === false) {
            return false;
        }

        return $this->index[$classname.'Core']['controller'] === true;
    }

    private function isMarketplaceClass($classname)
    {
        if (isset($this->index[$classname.'Core']['marketplace']) === false) {
            return false;
        }

        return $this->index[$classname.'Core']['marketplace'] === true;
    }

    private function normalizeDirectory($directory)
    {
        return rtrim($directory, '/\\').DIRECTORY_SEPARATOR;
    }

    private function replaceControllerCore(array $classes): array
    {
        $classes['HistoryControllerCore']['marketplace'] = true;
        $classes['HistoryControllerCore']['controller'] = true;

        $classes['OrderCore']['marketplace'] = true;
        $classes['OrderCore']['controller'] = false;

        return $classes;
    }
}

spl_autoload_register([MarketplaceAutoload::getInstance(), 'load']);

