<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Framework\View\Layout\Reader;

use Magento\Framework\View\Layout\ScheduledStructure\Helper;
use Magento\Framework\View\Layout\ReaderInterface;
use Magento\Framework\View\Layout\Element;
use Magento\Framework\View\Layout\Reader\Visibility\Condition;
use Magento\Framework\View\Layout\ReaderPool;
use Magento\Framework\Config\DataInterfaceFactory;

/**
 * Class UiComponent
 */
class UiComponent implements ReaderInterface
{
    /**
     * Supported types.
     */
    const TYPE_UI_COMPONENT = 'uiComponent';

    /**
     * List of supported attributes
     *
     * @var array
     */
    protected $attributes = ['group', 'component', 'aclResource'];

    /**
     * @var Helper
     */
    protected $layoutHelper;

    /**
     * @var Condition
     */
    private $conditionReader;

    /**
     * @var DataInterfaceFactory
     */
    private $uiConfigFactory;

    /**
     * @var ReaderPool
     */
    private $readerPool;

    /**
     * Constructor
     *
     * @param Helper $helper
     * @param Condition $conditionReader
     * @param DataInterfaceFactory $uiConfigFactory
     * @param ReaderPool $readerPool
     */
    public function __construct(
        Helper $helper,
        Condition $conditionReader,
        DataInterfaceFactory $uiConfigFactory,
        ReaderPool $readerPool
    ) {
        $this->layoutHelper = $helper;
        $this->conditionReader = $conditionReader;
        $this->uiConfigFactory = $uiConfigFactory;
        $this->readerPool = $readerPool;
    }

    /**
     * {@inheritdoc}
     */
    public function getSupportedNodes()
    {
        return [self::TYPE_UI_COMPONENT];
    }

    /**
     * {@inheritdoc}
     */
    public function interpret(Context $readerContext, Element $currentElement)
    {
        $attributes = $this->getAttributes($currentElement);
        $scheduledStructure = $readerContext->getScheduledStructure();
        $referenceName = $this->layoutHelper->scheduleStructure(
            $scheduledStructure,
            $currentElement,
            $currentElement->getParent(),
            ['attributes' => $attributes]
        );
        $attributes = array_merge(
            $attributes,
            ['visibilityConditions' => $this->conditionReader->parseConditions($currentElement)]
        );
        $scheduledStructure->setStructureElementData($referenceName, ['attributes' => $attributes]);

        foreach ($this->getLayoutElementsFromUiConfiguration($referenceName) as $layoutElement) {
            $layoutElement = simplexml_load_string(
                $layoutElement,
                Element::class
            );
            $this->readerPool->interpret($readerContext, $layoutElement);
        }

        return $this;
    }

    /**
     * Find layout elements in UI configuration for correct layout generation
     *
     * @param string $uiConfigName
     * @return array
     */
    private function getLayoutElementsFromUiConfiguration($uiConfigName)
    {
        $elements = [];
        $config = $this->uiConfigFactory->create(['componentName' => $uiConfigName])->get($uiConfigName);
        foreach ($config['children'] as $name => $data) {
            if (isset($data['arguments']['block']['layout'])) {
                $elements[$name] = $data['arguments']['block']['layout'];
            }
        }
        return $elements;
    }

    /**
     * Get ui component attributes
     *
     * @param Element $element
     * @return array
     */
    protected function getAttributes(Element $element)
    {
        $attributes = [];
        foreach ($this->attributes as $attributeName) {
            $attributes[$attributeName] = (string)$element->getAttribute($attributeName);
        }

        return $attributes;
    }
}
