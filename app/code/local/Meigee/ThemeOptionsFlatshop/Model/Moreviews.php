<?php 
/**
 * Magento
 *
 * @author    Meigeeteam http://www.meaigeeteam.com <nick@meaigeeteam.com>
 * @copyright Copyright (C) 2010 - 2012 Meigeeteam
 *
 */
class Meigee_ThemeOptionsFlatshop_Model_Moreviews
{
    public function toOptionArray()
    {
        return array(
            array('value'=>'moreviews', 'label'=>Mage::helper('ThemeOptionsFlatshop')->__('Simple List')),
            array('value'=>'moreviews_slider', 'label'=>Mage::helper('ThemeOptionsFlatshop')->__('Slider'))          
        );
    }

}