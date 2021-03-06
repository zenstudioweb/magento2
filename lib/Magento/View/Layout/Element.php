<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\View\Layout;

/**
 * Class Element
 */
class Element extends \Magento\Simplexml\Element
{
    /**#@+
     * Supported layout directives
     */
    const TYPE_RENDERER = 'renderer';
    const TYPE_TEMPLATE = 'template';
    const TYPE_DATA = 'data';
    const TYPE_BLOCK = 'block';
    const TYPE_CONTAINER = 'container';
    const TYPE_ACTION = 'action';
    const TYPE_ARGUMENTS = 'arguments';
    const TYPE_ARGUMENT = 'argument';
    const TYPE_REFERENCE_BLOCK = 'referenceBlock';
    const TYPE_REFERENCE_CONTAINER = 'referenceContainer';
    const TYPE_REMOVE = 'remove';
    const TYPE_MOVE = 'move';
    /**#@-*/

    /**#@+
     * Names of container options in layout
     */
    const CONTAINER_OPT_HTML_TAG = 'htmlTag';
    const CONTAINER_OPT_HTML_CLASS = 'htmlClass';
    const CONTAINER_OPT_HTML_ID = 'htmlId';
    const CONTAINER_OPT_LABEL = 'label';
    /**#@-*/

    /**
     * @return Element
     *
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function prepare()
    {
        switch ($this->getName()) {
            case self::TYPE_BLOCK:
            case self::TYPE_RENDERER:
            case self::TYPE_TEMPLATE:
            case self::TYPE_DATA:
                $this->prepareBlock();
                break;
            case self::TYPE_REFERENCE_BLOCK:
            case self::TYPE_REFERENCE_CONTAINER:
                $this->prepareReference();
                break;
            case self::TYPE_ACTION:
                $this->prepareAction();
                break;
            case self::TYPE_ARGUMENT:
                $this->prepareActionArgument();
                break;
            default:
                break;
        }
        foreach ($this as $child) {
            /** @var Element $child */
            $child->prepare();
        }
        return $this;
    }

    /**
     * @return bool|string
     */
    public function getBlockName()
    {
        $tagName = (string)$this->getName();
        $isThisBlock = empty($this['name'])
            || !in_array($tagName, array(self::TYPE_BLOCK, self::TYPE_REFERENCE_BLOCK));

        if ($isThisBlock) {
            return false;
        }
        return (string)$this['name'];
    }

    /**
     * Get element name
     *
     * Advanced version of getBlockName() method: gets name for container as well as for block
     *
     * @return string|bool
     */
    public function getElementName()
    {
        $tagName = $this->getName();
        $isThisContainer = !in_array(
            $tagName,
            array(
                self::TYPE_BLOCK,
                self::TYPE_REFERENCE_BLOCK,
                self::TYPE_CONTAINER,
                self::TYPE_REFERENCE_CONTAINER
            )
        );

        if ($isThisContainer) {
            return false;
        }
        return $this->getAttribute('name');
    }

    /**
     * Extracts sibling from 'before' and 'after' attributes
     *
     * @return string
     */
    public function getSibling()
    {
        $sibling = null;
        if ($this->getAttribute('before')) {
            $sibling = $this->getAttribute('before');
        } elseif ($this->getAttribute('after')) {
            $sibling = $this->getAttribute('after');
        }

        return $sibling;
    }

    /**
     * Add parent element name to parent attribute
     *
     * @return Element
     */
    public function prepareBlock()
    {
        $parent = $this->getParent();
        if (isset($parent['name']) && !isset($this['parent'])) {
            $this->addAttribute('parent', (string)$parent['name']);
        }

        return $this;
    }

    /**
     * @return Element
     */
    public function prepareReference()
    {
        return $this;
    }

    /**
     * Add parent element name to block attribute
     *
     * @return Element
     */
    public function prepareAction()
    {
        $parent = $this->getParent();
        $this->addAttribute('block', (string)$parent['name']);

        return $this;
    }

    /**
     * @return Element
     */
    public function prepareActionArgument()
    {
        return $this;
    }

    /**
     * Returns information is this element allows caching
     *
     * @return bool
     */
    public function isCacheable()
    {
        return !(boolean)count($this->xpath('//' . self::TYPE_BLOCK . '[@cacheable="false"]'));
    }
}
