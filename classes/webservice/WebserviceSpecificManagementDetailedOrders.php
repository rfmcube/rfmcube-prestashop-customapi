<?php
/**
 * 2007-2018 PrestaShop.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2018 PrestaShop SA
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */
class WebserviceSpecificManagementDetailedOrders implements WebserviceSpecificManagementInterface
{
    /** @var WebserviceOutputBuilder */
    protected $objOutput;
    protected $output;

    /** @var WebserviceRequest */
    protected $wsObject;

    /* ------------------------------------------------
     * GETTERS & SETTERS
     * ------------------------------------------------ */

    /**
     * @param WebserviceOutputBuilderCore $obj
     *
     * @return WebserviceSpecificManagementInterface
     */
    public function setObjectOutput(WebserviceOutputBuilderCore $obj)
    {
        $this->objOutput = $obj;

        return $this;
    }

    public function setWsObject(WebserviceRequestCore $obj)
    {
        $this->wsObject = $obj;

        return $this;
    }

    public function getWsObject()
    {
        return $this->wsObject;
    }

    public function getObjectOutput()
    {
        return $this->objOutput;
    }

    public function setUrlSegment($segments)
    {
        $this->urlSegment = $segments;

        return $this;
    }

    public function getUrlSegment()
    {
        return $this->urlSegment;
    }

    /**
     * Management of search.
     */
    public function manage()
    {
        if (!isset($this->wsObject->urlFragments['filter']) && (!isset($this->wsObject->urlFragments['filter']["date_add"]) || !isset($this->wsObject->urlFragments['filter']['date_upd']) || !isset($this->wsObject->urlFragments["filter"]["id"]))) {
            throw new WebserviceException('You have to set an order \'filter[id]\' or \'filter[date_upd]\' or \'filter[date_add]\'', array(100, 400));
        }

        if(isset($this->wsObject->urlFragments['filter']['date_upd'])){
          $dates = explode(",",str_replace(["[","]"],"",$this->wsObject->urlFragments['filter']['date_upd']));
          $ids = Order::getOrdersIdByDate($dates[0],$dates[1]);
        }else if(isset($this->wsObject->urlFragments['filter']['date_add'])){
          $dates = explode(",",str_replace(["[","]"],"",$this->wsObject->urlFragments['filter']['date_add']));
          $ids = $this->getOrdersIdByAddDate($dates[0],$dates[1]);
        }else{
          $ids = explode("|",str_replace(["[","]"],"",$this->wsObject->urlFragments['filter']['id']));
        }

        $orders = [];

        /**
         * Loop for every order id
         */
        foreach($ids as $order_id){
          $order = new Order($order_id);
          $order->items = $order->getCartProducts();

          /**
           * Loop for every product in order
           */
          foreach($order->items as &$product){
            $product["brand"] = (new Manufacturer($product["id_manufacturer"]))->name ?: "";
            $product["categories"] = array_values(Product::getProductCategoriesFull($product["product_id"]));
            
            /**
             * Build the category tree
             */
            foreach($product["categories"] as $index => &$category){
              $category_id = $category["id_category"];
              $category["tree"][] = $category_id;

              $this->getCategoryProductTree($category_id,$category);

              if($index==count($product["categories"])-1) $category["tree"] = array_reverse($category["tree"]);
            }
          }
          array_push($orders,$order);
        }

        if(isset($this->wsObject->urlFragments['sort'])){
          $sort = str_replace(["[","]"],"",$this->wsObject->urlFragments['sort']);
          $order = array_reverse(explode("_",$sort))[0];
          $sort = str_replace("_{$order}","",$sort);
          usort($orders,function($orderA,$orderB) use ($order,$sort){
            if($order=="ASC"){
              return $orderA->{$sort}>=$orderB->{$sort};
            }

            return $orderA->{$sort}<=$orderB->{$sort};
          });
        }

        $this->output = json_encode(["orders" => $orders]);
    }

    /**
     * This must be return a string with specific values as WebserviceRequest expects.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->output;
    }

    /**
     * Returns all the product category ids tree
     */
    private function getCategoryProductTree($category_id,&$category){
      do{
        $id_parent = (new Category($category_id))->id_parent;
        $category["tree"][] = $id_parent;
        $category_id = $id_parent;
      }while($id_parent!=1);
    }

    private function getOrdersIdByAddDate($date_from, $date_to, $id_customer = null, $type = null)
    {
        $sql = 'SELECT `id_order`
                FROM `' . _DB_PREFIX_ . 'orders`
                WHERE DATE_ADD(date_upd, INTERVAL -1 DAY) <= \'' . pSQL($date_to) . '\' AND date_upd >= \'' . pSQL($date_from) . '\'
                    ' . Shop::addSqlRestriction()
                    . ($type ? ' AND `' . bqSQL($type) . '_number` != 0' : '')
                    . ($id_customer ? ' AND id_customer = ' . (int) $id_customer : '');
        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $orders = array();
        foreach ($result as $order) {
            $orders[] = (int) $order['id_order'];
        }

        return $orders;
    }
}
