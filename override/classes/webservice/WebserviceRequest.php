<?php

/**
 * Registers the resource on PrestaShop 1.6
 */
class WebserviceRequest extends WebserviceRequestCore
{
  function __construct(){
    include_once(_PS_MODULE_DIR_.'rfmcubeapi/classes/webservice/WebserviceSpecificManagementDetailedOrders.php');
  }

  public static function getResources()
  {
    $resources = parent::getResources();
    $resources['detailedorders'] = array('description' => 'Rfmcube API', 'specific_management' => true);
    
    return $resources;
  }
}
