<?php 
/**
 * Magento
 *
 * @author    Meigeeteam http://www.meaigeeteam.com <nick@meaigeeteam.com>
 * @copyright Copyright (C) 2010 - 2012 Meigeeteam
 *
 */
class Meigee_ThemeOptionsFlatshop_Helper_Images extends Mage_Core_Helper_Abstract
{
	public function getImg($_product, $imgType = "image", $w, $h, $file=NULL)
	{
		$url = "";
		$config = Mage::getStoreConfig('meigee_flatshop_general/productimages');
		$keepAspectRatio = $config['keepAspectRatio'];

		if ($config['reallyCustomAspectRatio']) {
			$customAspectRatio = $config['reallyCustomAspectRatio'];
		} else $customAspectRatio = $config['customAspectRatio'];
		
		$url = Mage::helper('catalog/image')
				->init($_product, $imgType, $file);

		if ($keepAspectRatio) {
			$url->keepAspectRatio(TRUE);
			$h = NULL;
		} else $url->keepAspectRatio(FALSE);

		$url->constrainOnly(TRUE)
			->keepFrame(FALSE)
			->resize($w, $w*$customAspectRatio);

		return $url;
	}
	
	public function getImgSources ($_product, $imgType = "image", $w, $h, $file = NULL) 
	{
		$html = "src=\"" . $this->getImg ($_product, $imgType, $w, $h, $file);
 		if (Mage::getStoreConfig('meigee_flatshop_general/appearance/retina')) {
 			$html .= "\" data-srcX2=\"";
 			$html .= $this->getImg ($_product, $imgType, $w*2, $h*2, $file);
 		}
		$html .= "\"";
		return $html;
	}
}