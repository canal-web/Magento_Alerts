<?php
class Canalweb_Alert_Helper_Data extends Mage_Core_Helper_Abstract{
    
    function getAttributeOptions($name, $slug = false) {
        // Gt options for an attribute.
        $attributeInfo = Mage::getResourceModel('eav/entity_attribute_collection')->setCodeFilter($name)->getFirstItem();
        $attributeId = $attributeInfo->getAttributeId();
        $attribute = Mage::getModel('catalog/resource_eav_attribute')->load($attributeId);
        $attributeOptions = $attribute ->getSource()->getAllOptions(false);

        $productUrlModel = Mage::getModel('catalog/product_url');

        $options = array();
        foreach ($attributeOptions as $values) {
          $options[$values['value']] = ($slug)
            ? $productUrlModel->formatUrlKey($values['label'])
            : $values['label'];
        }

        return $options;
    }
}
