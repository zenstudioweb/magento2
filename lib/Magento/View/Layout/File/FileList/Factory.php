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

namespace Magento\View\Layout\File\FileList;

use Magento\ObjectManager;

/**
 * Factory that produces layout file list instances
 */
class Factory
{
    /**
     * Default file list collator
     */
    const FILE_LIST_COLLATOR = 'Magento\View\Layout\File\FileList\Collator';

    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @param ObjectManager $objectManager
     */
    public function __construct(ObjectManager $objectManager)
    {
        $this->objectManager = $objectManager;
    }

    /**
     * Return newly created instance of a layout file list
     *
     * @param string $collator
     * @return \Magento\View\Layout\File\FileList
     */
    public function create($collator = self::FILE_LIST_COLLATOR)
    {
        return $this->objectManager->create(
            'Magento\View\Layout\File\FileList',
            array('collator' => $this->objectManager->get($collator))
        );
    }
}
