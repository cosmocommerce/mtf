<?php
/**
 * Object manager definition factory
 *
 * {license_notice}
 *
 * @copyright {@copyright}
 * @license   {@license_link}
 *
 */
namespace Magento\Framework\ObjectManager;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\ObjectManager\Definition\Runtime;
use Magento\Framework\ObjectManager\Relations;
use Magento\Framework\ObjectManager\Code\Generator;
use Magento\Interception\Code\Generator as InterceptionGenerator;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class DefinitionFactory
{
    /**
     * Directory containing compiled class metadata
     *
     * @var string
     */
    protected $_definitionDir;

    /**
     * Class generation dir
     *
     * @var string
     */
    protected $_generationDir;

    /**
     * Format of definitions
     *
     * @var string
     */
    protected $_definitionFormat;

    /**
     * Filesystem Driver
     *
     * @var DriverInterface
     */
    protected $_filesystemDriver;

    /**
     * List of definition models
     *
     * @var array
     */
    protected $_definitionClasses = array(
        'igbinary' => 'Magento\Framework\ObjectManager\Definition\Compiled\Binary',
        'serialized' => 'Magento\Framework\ObjectManager\Definition\Compiled\Serialized'
    );

    /**
     * @param DriverInterface $filesystemDriver
     * @param string $definitionDir
     * @param string $generationDir
     * @param string $definitionFormat
     */
    public function __construct(DriverInterface $filesystemDriver, $definitionDir, $generationDir, $definitionFormat)
    {
        $this->_filesystemDriver = $filesystemDriver;
        $this->_definitionDir = $definitionDir;
        $this->_generationDir = $generationDir;
        $this->_definitionFormat = $definitionFormat;
    }

    /**
     * Create class definition based
     *
     * @param mixed $definitions
     * @return Runtime
     */
    public function createClassDefinition($definitions)
    {
        if (!$definitions) {
            $path = $this->_definitionDir . '/definitions.php';
            if ($this->_filesystemDriver->isReadable($path)) {
                $definitions = $this->_filesystemDriver->fileGetContents($path);
            }
        }
        if ($definitions) {
            if (is_string($definitions)) {
                $definitions = $this->_unpack($definitions);
            }
            $definitionModel = $this->_definitionClasses[$this->_definitionFormat];
            $result = new $definitionModel($definitions);
        } else {
            $autoloader = new \Magento\Framework\Autoload\IncludePath();
            $generatorIo = new \Magento\Framework\Code\Generator\Io(
                $this->_filesystemDriver,
                $autoloader,
                $this->_generationDir
            );
            $generator = new \Magento\Framework\Code\Generator(
                $autoloader,
                $generatorIo,
                array(
                    Generator\Factory::ENTITY_TYPE => '\Magento\Framework\ObjectManager\Code\Generator\Factory',
                    Generator\Proxy::ENTITY_TYPE => '\Magento\Framework\ObjectManager\Code\Generator\Proxy',
                    InterceptionGenerator\Interceptor::ENTITY_TYPE => '\Magento\Interception\Code\Generator\Interceptor'
                )
            );
            $autoloader = new \Magento\Framework\Code\Generator\Autoloader($generator);
            spl_autoload_register(array($autoloader, 'load'));

            $result = new Runtime();
        }
        return $result;
    }

    /**
     * Create plugin definitions
     *
     * @return \Magento\Interception\Definition
     */
    public function createPluginDefinition()
    {
        $path = $this->_definitionDir . '/plugins.php';
        if ($this->_filesystemDriver->isReadable($path)) {
            return new \Magento\Interception\Definition\Compiled(
                $this->_unpack($this->_filesystemDriver->fileGetContents($path))
            );
        } else {
            return new \Magento\Interception\Definition\Runtime();
        }
    }

    /**
     * Create relations
     *
     * @return Relations
     */
    public function createRelations()
    {
        $path = $this->_definitionDir . '/' . 'relations.php';
        if ($this->_filesystemDriver->isReadable($path)) {
            return new \Magento\Framework\ObjectManager\Relations\Compiled(
                $this->_unpack($this->_filesystemDriver->fileGetContents($path))
            );
        } else {
            return new \Magento\Framework\ObjectManager\Relations\Runtime();
        }
    }

    /**
     * Un-compress definitions
     *
     * @param string $definitions
     * @return mixed
     */
    protected function _unpack($definitions)
    {
        $extractor = $this->_definitionFormat == 'igbinary' ? 'igbinary_unserialize' : 'unserialize';
        return $extractor($definitions);
    }
}
