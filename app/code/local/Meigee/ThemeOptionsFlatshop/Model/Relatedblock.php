<?php 
/**
 * Magento
 *
 * @author    Meigeeteam http://www.meaigeeteam.com <nick@meaigeeteam.com>
 * @copyright Copyright (C) 2010 - 2012 Meigeeteam
 *
 */
class Meigee_ThemeOptionsFlatshop_Model_Relatedblock
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'sidebar', 'label'=>Mage::helper('ThemeOptionsFlatshop')->__('Show in Sidebar')),
            array('value'=>'content', 'label'=>Mage::helper('ThemeOptionsFlatshop')->__('Show under Content'))
        );
    }

}