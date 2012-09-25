<?php

namespace Guzzle\Service\Description;

use Guzzle\Common\Exception\InvalidArgumentException;

/**
 * A ServiceDescription stores service information based on a service document
 */
class ServiceDescription implements ServiceDescriptionInterface
{
    /**
     * @var array Array of {@see OperationInterface} objects
     */
    protected $operations = array();

    /**
     * @var array Array of API models
     */
    protected $models = array();

    /**
     * @var string Name of the API
     */
    protected $name;

    /**
     * @var string API version
     */
    protected $apiVersion;

    /**
     * @var string Summary of the API
     */
    protected $description;

    /**
     * @var array Any extra API data
     */
    protected $extraData = array();

    /**
     * @var ServiceDescriptionLoader Factory used in factory method
     */
    protected static $descriptionLoader;

    /**
     * {@inheritdoc}
     * @param string|array $config  File to build or array of operation information
     * @param array        $options Service description factory options
     *
     * @return self
     */
    public static function factory($config, array $options = array())
    {
        // @codeCoverageIgnoreStart
        if (!self::$descriptionLoader) {
            self::$descriptionLoader = new ServiceDescriptionLoader();
        }
        // @codeCoverageIgnoreEnd

        return self::$descriptionLoader->load($config, $options);
    }

    /**
     * Create a new ServiceDescription
     *
     * @param array $config Array of configuration data
     */
    public function __construct(array $config = array())
    {
        $this->fromArray($config);
    }

    /**
     * Serialize the service description
     *
     * @return string
     */
    public function serialize()
    {
        $result = $this->extraData + array(
            'name'        => $this->name,
            'apiVersion'  => $this->apiVersion,
            'description' => $this->description,
            'operations'  => array(),
        );
        foreach ($this->getOperations() as $name => $operation) {
            $result['operations'][$name] = $operation->toArray();
        }
        if (!empty($this->models)) {
            $result['models'] = array();
            foreach ($this->models as $id => $model) {
                $result['models'][$id] = $model instanceof Parameter ? $model->toArray(): $model;
            }
        }

        return json_encode(array_filter($result));
    }

    /**
     * Unserialize the service description
     *
     * @param string|array $json JSON data
     */
    public function unserialize($json)
    {
        $this->operations = array();
        $this->fromArray(json_decode($json, true));
    }

    /**
     * {@inheritdoc}
     */
    public function getOperations()
    {
        foreach ($this->operations as &$operation) {
            if (!($operation instanceof Operation)) {
                $operation = new Operation($operation, $this);
            }
        }

        return $this->operations;
    }

    /**
     * {@inheritdoc}
     */
    public function hasOperation($name)
    {
        return isset($this->operations[$name]);
    }

    /**
     * {@inheritdoc}
     */
    public function getOperation($name)
    {
        // Lazily retrieve and build operations
        if (!isset($this->operations[$name])) {
            return null;
        }

        if (!($this->operations[$name] instanceof Operation)) {
            $this->operations[$name] = new Operation($this->operations[$name], $this);
        }

        return $this->operations[$name];
    }

    /**
     * Add a operation to the service description
     *
     * @param OperationInterface $operation Operation to add
     *
     * @return self
     */
    public function addOperation(OperationInterface $operation)
    {
        $this->operations[$operation->getName()] = $operation->setServiceDescription($this);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getModel($id)
    {
        if (!isset($this->models[$id])) {
            return null;
        }

        if (!($this->models[$id] instanceof Parameter)) {
            $this->models[$id] = new Parameter($this->models[$id], $this);
        }

        return $this->models[$id];
    }

    /**
     * {@inheritdoc}
     */
    public function hasModel($id)
    {
        return isset($this->models[$id]);
    }

    /**
     * Add a model to the service description
     *
     * @param Parameter $model Model to add
     *
     * @return self
     */
    public function addModel(Parameter $model)
    {
        $this->models[$model->getName()] = $model;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Get an extra API data from the service description
     *
     * @param string $key Data key to retrieve
     *
     * @return null|mixed
     */
    public function getData($key)
    {
        return isset($this->extraData[$key]) ? $this->extraData[$key] : null;
    }

    /**
     * Initialize the state from an array
     *
     * @param array $config Configuration data
     */
    protected function fromArray(array $config)
    {
        // Keep a list of default keys used in service descriptions that is later used to determine extra data keys
        $defaultKeys = array('name', 'models', 'apiVersion', 'description');
        // Pull in the default configuration values
        foreach ($defaultKeys as $key) {
            if (isset($config[$key])) {
                $this->{$key} = $config[$key];
            }
        }

        // Ensure that the models and operations properties are always arrays
        $this->models = (array) $this->models;
        $this->operations = (array) $this->operations;

        // We want to add operations differently than adding the other properties
        $defaultKeys[] = 'operations';

        // Create operations for each operation
        if (isset($config['operations'])) {
            foreach ($config['operations'] as $name => $operation) {
                if (!($operation instanceof Operation) && !is_array($operation)) {
                    throw new InvalidArgumentException('Invalid operation in service description: '
                        . gettype($operation));
                }
                $this->operations[$name] = $operation;
            }
        }

        // Get all of the additional properties of the service description and store them in a data array
        foreach (array_diff(array_keys($config), $defaultKeys) as $key) {
            $this->extraData[$key] = $config[$key];
        }
    }
}
