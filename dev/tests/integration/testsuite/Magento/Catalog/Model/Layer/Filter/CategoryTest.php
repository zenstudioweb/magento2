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
 * @category    Magento
 * @package     Magento_Catalog
 * @subpackage  integration_tests
 * @copyright   Copyright (c) 2014 X.commerce, Inc. (http://www.magentocommerce.com)
 * @license     http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Magento\Catalog\Model\Layer\Filter;

/**
 * Test class for \Magento\Catalog\Model\Layer\Filter\Category.
 *
 * @magentoDataFixture Magento/Catalog/_files/categories.php
 */
class CategoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Magento\Catalog\Model\Layer\Filter\Category
     */
    protected $_model;

    /**
     * @var \Magento\Catalog\Model\Category
     */
    protected $_category;

    protected function setUp()
    {
        $this->_category = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\Catalog\Model\Category');
        $this->_category->load(5);
        $this->_model = \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\Catalog\Model\Layer\Filter\Category');
        $this->_model->setData(array(
            'layer' => \Magento\TestFramework\Helper\Bootstrap::getObjectManager()
            ->create('Magento\Catalog\Model\Layer', array(
                'data' => array('current_category' => $this->_category)
            )),
        ));
    }

    public function testGetResetValue()
    {
        $this->assertNull($this->_model->getResetValue());
    }

    public function testApplyNothing()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->_model->apply(
            $objectManager->get('Magento\TestFramework\Request'),
            \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get('Magento\View\LayoutInterface')
                ->createBlock('Magento\View\Element\Text')
        );
        /** @var $objectManager \Magento\TestFramework\ObjectManager */
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $this->assertNull($objectManager->get('Magento\Core\Model\Registry')->registry('current_category_filter'));
    }

    public function testApply()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $request = $objectManager->get('Magento\TestFramework\Request');
        $request->setParam('cat', 3);
        $this->_model->apply(
            $request,
            \Magento\TestFramework\Helper\Bootstrap::getObjectManager()->get('Magento\View\LayoutInterface')
                ->createBlock('Magento\View\Element\Text')
        );

        /** @var $category \Magento\Catalog\Model\Category */
        $category = $objectManager->get('Magento\Core\Model\Registry')->registry('current_category_filter');
        $this->assertInstanceOf('Magento\Catalog\Model\Category', $category);
        $this->assertEquals(3, $category->getId());

        return $this->_model;
    }

    /**
     * @depends testApply
     */
    public function testGetResetValueApplied(\Magento\Catalog\Model\Layer\Filter\Category $modelApplied)
    {
        $this->assertEquals(2, $modelApplied->getResetValue());
    }

    public function testGetName()
    {
        $this->assertEquals('Category', $this->_model->getName());
    }

    public function testGetCategory()
    {
        $this->assertSame($this->_category, $this->_model->getCategory());
    }

    /**
     * @depends testApply
     */
    public function testGetCategoryApplied(\Magento\Catalog\Model\Layer\Filter\Category $modelApplied)
    {
        $category = $modelApplied->getCategory();
        $this->assertInstanceOf('Magento\Catalog\Model\Category', $category);
        $this->assertEquals(3, $category->getId());
    }

    /**
     * @depends testApply
     */
    public function testGetItems(\Magento\Catalog\Model\Layer\Filter\Category $modelApplied)
    {
        $items = $modelApplied->getItems();

        $this->assertInternalType('array', $items);
        $this->assertEquals(1, count($items));

        /** @var $item \Magento\Catalog\Model\Layer\Filter\Item */
        $item = $items[0];

        $this->assertInstanceOf('Magento\Catalog\Model\Layer\Filter\Item', $item);
        $this->assertSame($modelApplied, $item->getFilter());
        $this->assertEquals('Category 1.1', $item->getLabel());
        $this->assertEquals(4, $item->getValue());
        $this->assertEquals(1, $item->getCount());
    }
}
