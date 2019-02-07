<?php

Mage::getSingleton('core/session', array('name' => 'frontend'));  
$enableorDisable = Mage::getStoreConfig('mobileapi/mobileapi/enabled',Mage::app()->getStore()); 
if($enableorDisable){

class Evince_Mobileapi_IndexController extends Mage_Core_Controller_Front_Action {
    /* public $value = json_decode(file_get_contents('php://input')); */
     
    public function loginAction() {
        // $this->check_method('POST');
        $value = json_decode(file_get_contents('php://input'));
        // $this->_redirect('home'); //this is my home page redirection
        Mage::getSingleton("core/session", array("name" => "frontend"));
        $session = Mage::getSingleton("customer/session");

        $email = $value->email;
        $password = $value->pass;
        $session = Mage::getSingleton('customer/session');

        try {
            $session->login($email, $password);
            $custs = $session->getCustomer()->getData();

            if ($custs) {
                $response = array('status' => 1, 'message' => 'Login successfully!', 'customer' => $custs);
            }
        } catch (Mage_Core_Exception $e) {
            switch ($e->getCode()) {
                case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_NOT_CONFIRMED:
                    $response = array('status' => 0, 'message' => 'Your email not confirmed!');
                    break;
                case Mage_Customer_Model_Customer::EXCEPTION_INVALID_EMAIL_OR_PASSWORD:
                    //echo "Email or password invalid actions here";
                    $response = array('status' => 0, 'message' => 'Login Fail!');
                    //$message = $e->getMessage();//echo the error message
                    break;
                default:
                //$message = $e->getMessage(); //Display other error messages
            }
        }
        // If invalid inputs "Bad Request" status message and reason        
        //  $this->response($this->json($response), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    // Forgot Password
    // Sign UP Customer / Front End User
    public function signUpAction() {
        Mage::app();
        umask(0);
        Mage::app("default");
        $value = json_decode(file_get_contents('php://input'));
        Mage::getSingleton("core/session", array("name" => "frontend"));
        $session = Mage::getSingleton("customer/session");
        $websiteId = Mage::app()->getWebsite()->getId();
        $customer = Mage::getModel('customer/customer');
        //$customer  = new Mage_Customer_Model_Customer();
        $email = $value->email;
        $password = $value->pass; //$_POST['pass'];
        $firstname = $value->firstname; //$_POST['firstname'];
        $lastname = $value->lastname; //$_POST['lastname'];

        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        $customer->loadByEmail($email);
        //Zend_Debug::dump($customer->debug()); exit;
        if ($customer->getId()) {
            $response = array('status' => 0, 'message' => 'Customer Already Exits!');
        } else {
            if (!$customer->getId()) {
                $customer->setEmail($email);
                $customer->setFirstname($firstname);
                $customer->setLastname($lastname);
                $customer->setPassword($password);
            }

            try {
                $customer->save();
                $customer->setConfirmation(null);
                $customer->save();

                //Make a "login" of new customer
                Mage::getSingleton('customer/session')->loginById($customer->getId());
                $custs = $session->getCustomer()->getData();

                $response = array('status' => 1, 'customer' => $custs);
                $response = array('status' => 1, 'message' => 'Customer Created Successfully!', 'customer' => $custs);
            } catch (Exception $ex) {
                $response = array('status' => 0, 'message' => 'Customer Not Created!',);
                //Zend_Debug::dump($ex->getMessage());
            }
        }
        // $this->response($this->json($response), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    //Check availability based on ZIPCODE
    public function zipcodeAction() {
        Mage::app();
        umask(0);
        Mage::app("default");
        $model = Mage::getModel('zipcode/zipcode');
        $collection = $model->getCollection();
        $custs = $collection->getData();
        $items = count($custs);
        $value = json_decode(file_get_contents('php://input'));
        $zeep = $value->zip;
        $i = 0;
        foreach ($collection as $items) {
            if ($zeep >= $items->getZipcodeFrom() && $zeep <= $items->getZipcodeTo()) {
                $response1[] = array('values' => 1, 'message' => $items->getMessage());
                $i = 0;
            } else {
                if ($i == 0) {
                    $response1[] = array('status' => 0, 'message' => 'Not available in your location yet!');
                }
                $response1[] = array('values' => 0, 'message' => $items->getMessage());
            }
            $i++;
        }
        // $this->response($this->json($response1), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    // Reset Password
    public function resetPasswordAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');
        $customerId = $value->customerid;
        $currpass = $value->currpass;
        $password = $value->password;
        $passwordconfirmation = $this->_request['passwordconfirmation'];

        $customer = Mage::getModel('customer/customer')->load($customerId);

        $oldPass = $customer->getData("password_hash");
        if ($oldPass) {
            list($_salt, $salt) = explode(':', $oldPass);
        } else {
            $salt = false;
        }

        if ($customer->hashPassword($currpass, $salt) == $oldPass) {
            if (strlen($password)) {
                /**
                 * Set entered password and its confirmation - they
                 * will be validated later to match each other and be of right length
                 */
                $customer->setPassword($password);
                $customer->setConfirmation($passwordconfirmation);
            } else {
                $response = array('status' => 0, 'message' => 'New password field cannot be empty.');
                //$this->response($this->json($response), 200);
                $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
                exit;
            }
        } else {
            $response = array('status' => 0, 'message' => 'Invalid current password');
            //$this->response($this->json($response), 200);
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
            exit;
        }

        $validationErrorMessages = $customer->validate();
        if (is_array($validationErrorMessages)) {
            $response = array('status' => 0, 'message' => $validationErrorMessages[0]);
            //$this->response($this->json($response), 200);
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
            exit;
        }

        try {
            // Empty current reset password token i.e. invalidate it
            $customer->setConfirmation(null);
            $customer->save();
            $response = array('status' => 1, 'message' => 'Password changed successfully!');
        } catch (Exception $exception) {
            $response = array('status' => 0, 'message' => 'Cannot save a new password. Please try again!');
        }

        //$this->response($this->json($response), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    // Edit Customer Profile 
    public function editProfileAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');
        $customerId = $value->customerid;
        $email = $value->email;
        $firstname = $value->firstname;
        $lastname = $value->lastname;
        $telephone = $value->telephone;
        $company = $value->company;

        $customer = Mage::getModel('customer/customer')->load($customerId);
        if ($customer->getId()) {
            if ($email) {
                $customer->setEmail($email);
            }
            if ($firstname) {
                $customer->setFirstname($firstname);
            }
            if ($lastname) {
                $customer->setLastname($lastname);
            }
            if ($password) {
                $customer->setTelephone($password);
            }
            if ($company) {
                $customer->setCompany($company);
            }
            try {
                $customer->save();
                $response = array('status' => 1, 'message' => 'Profile save successfully!');
            } catch (Exception $e) {
                switch ($e->getCode()) {
                    case Mage_Customer_Model_Customer::EXCEPTION_EMAIL_EXISTS:
                        $response = array('status' => 0, 'message' => 'This customer email already exists!');
                        break;
                    default:
                    //$message = $e->getMessage(); //Display other error messages
                }
            }
        } else {
            $response = array('status' => 0, 'message' => 'Profile cannot save!');
        }
        //$this->response($this->json($response), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    // Get Products based on category
    function getCategoryProductsAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');
        $catid = $value->catid;
        $sort = $value->sort;
        $page = $value->page;

        if ($page == "") {
            $page = 1;
        } else {
            $page = $page;
        }
        //$catid = '293';
        //$sort = 'nameasc';

        if ($sort == 'nameasc') {
            $sorta = 'name';
            $sortb = 'asc';
        } else if ($sort == 'namedesc') {
            $sorta = 'name';
            $sortb = 'desc';
        } else if ($sort == 'priceasc') {
            $sorta = 'price';
            $sortb = 'asc';
        } else if ($sort == 'pricedesc') {
            $sorta = 'price';
            $sortb = 'desc';
        } else {
            $sorta = 'name';
            $sortb = 'asc';
        }

        $categoryid = $catid;
        $category = new Mage_Catalog_Model_Category();
        $category->load($categoryid);
        $products = $category->getProductCollection();
        $products->addAttributeToSelect('*');
        $products->joinField(
                        'is_in_stock', 'cataloginventory/stock_item', 'is_in_stock', 'product_id=entity_id', '{{table}}.stock_id=1', 'left'
                )
                ->addAttributeToFilter('is_in_stock', array('neq' => 0));
        $products->setPage($page);
        $products->setPageSize(20);
        $products->setOrder($sorta, $sortb);
        //echo count($products);
        /* count */
        $category1 = new Mage_Catalog_Model_Category();
        $category1->load($categoryid);
        $products1 = $category1->getProductCollection();
        $products1->addAttributeToSelect('*');
        $products1->joinField(
                        'is_in_stock', 'cataloginventory/stock_item', 'is_in_stock', 'product_id=entity_id', '{{table}}.stock_id=1', 'left'
                )
                ->addAttributeToFilter('is_in_stock', array('neq' => 0));
        //echo ' = '.count($products1);
        $imgurl = Mage::getBaseUrl('media');
        if (strstr($imgurl, "?SID")) {
            $u = explode("?SID", $imgurl);
            $imgurl = $u[0];
        }
        if (count($products) > 0) {
            $prods = array();
            $pi = 0;
            foreach ($products as $p) {
                //print_r($p->getData());
                //exit;
                $prods[$pi]['id'] = $p->getId();
                $prods[$pi]['name'] = $p->getName();
                $prods[$pi]['sku'] = $p->getSku();
                $prods[$pi]['thumbnail'] = $imgurl . 'catalog/product' . $p->getThumbnail();
                $prods[$pi]['price'] = $p->getPrice();
                $prods[$pi]['specialprice'] = $p->getSpecialPrice();
                $prods[$pi]['isStock'] = $p->getIsSalable();
                $prods[$pi]['hasOption'] = $p->getHasOptions();
                $pi++;
            }
            $res = array("totalproducts" => count($products), "products" => $prods);
            //$this->response($this->json($res), 200);
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($res));
        } else {
            $response = array('message' => 'No product found!', 'status' => 0, 400);
            //$response = array('message'=>'No product found!','message'=>'Profile save successfully!');
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        }
    }

    // Get Product URL
    function getFullProductUrlAction($product) {
        if (is_object($product) && $product->getSku()) {
            $proArr = $product->getCategoryIds();
            // first try SQL approach
            try {
                $query = "SELECT `request_path`
			FROM `core_url_rewrite`
			WHERE `product_id`='" . $product->getEntityId() . "'
			" . ((end($proArr)) ? 'AND `category_id`=' . end($proArr) : '') . "
			AND `store_id`='" . Mage::app()->getStore()->getId() . "';
			";
                $read = Mage::getSingleton('core/resource')->getConnection('core_read');
                $result = $read->fetchRow($query);
                return Mage::getStoreConfig('web/unsecure/base_url') . $result['request_path'];
            }
            // if it fails, than use failsafe way with category object loading
            catch (Exception $e) {
                $allCategoryIds = $product->getCategoryIds();
                $lastCategoryId = end($allCategoryIds);
                $lastCategory = Mage::getModel('catalog/category')->load($lastCategoryId);
                $lastCategoryUrl = $lastCategory->getUrl();
                $fullProductUrl = str_replace(Mage::getStoreConfig('catalog/seo/category_url_suffix'), '/', $lastCategoryUrl) . basename($product->getUrlKey()) . Mage::getStoreConfig('catalog/seo/product_url_suffix');
                return $fullProductUrl;
            }
        }
        return false;
    }

    // Get Product Details
    public function getProductDetailsAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');
        $prodid = $value->prodid;
        $productdata = Mage::getModel('catalog/product')->load($prodid)->getData();
        if ($productdata['special_price'] == "" || $productdata['special_price'] == "null") {
            $productdata['special_price'] = "";
        }
        $product = Mage::getModel('catalog/product')->load($prodid);

        $attributes = $product->getAttributes();
        $specification = array();
        $a = 0;
        foreach ($attributes as $attribute) {
            if ($attribute->getIsVisibleOnFront()) {
                //$specification[$a]['code'] = $attribute->getAttributeCode();
                $specification[$a]['label'] = $attribute->getFrontend()->getLabel($product);
                $specification[$a]['value'] = $attribute->getFrontend()->getValue($product);
                $a++;
            }
        }

        // Custom options array
        $i = 1;
        $resdata = array();
        $s = 0;
        foreach ($product->getOptions() as $o) {
            $tmpval = array();
            //$resdata[$s]['Custom Option']=$i;
            $resdata[$s]['opt_type'] = $o->getType();
            $resdata[$s]['opt_title'] = $o->getTitle();
            $resdata[$s]['opt_is_required'] = $o->getIsRequire();
            $resdata[$s]['opt_id'] = $o->getOptionId();
            if ($o->getPrice() == "" || $o->getPrice() == "null") {
                $resdata[$s]['opt_price'] = "";
            } else {
                $resdata[$s]['opt_price'] = $o->getPrice();
            }
            $values = $o->getValues();
            foreach ($values as $v) {
                $tmpval[] = $v->getData();
            }
            $resdata[$s]['opt_values'] = $tmpval;
            $i++;
            $s++;
        }

        $spe = array('Specification' => $specification, 'alldata' => $productdata, 'producturl' => $this->getFullProductUrlAction($product), "custom_options" => $resdata);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($spe));
        //$this->response($this->json($spe), 200);
    }

    // Get Order Details
    public function getOrderDetailsAction() {
        //$this->check_method('POST');
        $value = json_decode(file_get_contents('php://input'));
        $customerId = $value->customerid;
        //$customerId = 20;
        #load customer object
        $customer = Mage::getModel('customer/customer')->load($customerId);

        /* Get the customer's email address */
        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        //  $customer->getId(); 
        //  $customer->getFirstName(); 
        $customer_email = $customer->getEmail();

        $customer->loadByEmail($customer_email); //load customer by email id 

        $collection = Mage::getModel('sales/order')->getCollection()->addAttributeToFilter('customer_email', array('like' => $customer_email));

        $array = array();
        $array1 = array();
        // $b=0;
        $a = 0;

        foreach ($collection as $order) {
            //do something
            $order_id = $order->getId();
            $array[$a]['OrderId'] = $order_id; //Order Id
            $array[$a]['OrderIncreametnId'] = $order->getIncrementId(); //Order increametn Id
            $array[$a]['ShippingAmount'] = $order->getShippingAmount(); //Shipping Amount
            $array[$a]['GrandTotal'] = $order->getGrandTotal(); //Grand Total
            $array[$a]['CreatedAt'] = $order->getCreatedAt(); //Grand Total [created_at] => 2015-02-18 10:12:02
            $array[$a]['UpdateAt'] = $order->getUpdatedAt(); //Gran[updated_at] => 2015-02-18 10:12:06

            $order = Mage::getModel("sales/order")->load($order_id); //load order by order id 

            $array[$a]['OrderStatus'] = $order->getStatus(); //order Status

            $ordered_items = $order->getAllItems();

            // $array[] =  $ordered_items;
            $array1 = array();
            $b = 0;
            foreach ($ordered_items as $item) {     //item detail     
                $pro = $item->getData();
                $products = Mage::getModel('catalog/product')->load($pro['product_id']); //Product ID

                $array1[$b]['image'] = Mage::getStoreConfig('web/unsecure/base_url') . 'media/catalog/product' . $products->getImage(); //product Image 
                $array1[$b]['productid'] = $pro['product_id']; //product id 
                //$array1[$b]['productid'] = $item->getItemId(); //product id 
                $array1[$b]['ProductsName'] = $item->getName();   //Products sku 
                $array1[$b]['ProductsSku'] = $item->getSku();   //Products sku 
                $array1[$b]['QtyOrdered'] = $item->getQtyOrdered(); //ordered qty of item
                $array1[$b]['ProductsPrice'] = $item->getPrice(); //ordered qty of item                     
                //$array1[$b]['OrderStatus'] =  $item->getStatus(); //order Status
                $b++;
            }

            $array[$a][] = array('orders' => $array1);
            //$spe = array('o'=>$array[$a],'orders'=>$array1);

            $a++;
        }

        //$this->response($this->json($array), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($array));
    }

    private function cancelOrderAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');
        $prodid = $value->OrderIncreametnId;
        try {
            $order = Mage::getModel('sales/order')->loadByIncrementId($prodid);
            $order->setState(Mage_Sales_Model_Order::STATE_CANCELED, true)->save();
            $state = array('status' => 1, 'message' => "Order Cancel Successfully");
        }
        // if it fails, than use failsafe way with category object loading
        catch (Exception $e) {
            $state = array('status' => 0, 'message' => "Please try again!");
        }

        //$this->response($this->json($state), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($state));
    }

    // Get Customer WishList	
    public function getWishListAction() {
        $value = json_decode(file_get_contents('php://input'));
        // $this->check_method('POST');
        $customerId = $value->customerid;

        #load customer object
        $customer = Mage::getModel('customer/customer')->load($customerId);

        if ($customer->getId()) {
            $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customer, true);
            $wishListItemCollection = $wishlist->getItemCollection();

            $imgurl = Mage::getBaseUrl('media');
            if (strstr($imgurl, "?SID")) {
                $u = explode("?SID", $imgurl);
                $imgurl = $u[0];
            }
            $wistlist = array();
            $a = 0;
            foreach ($wishListItemCollection as $item) {
                $pdata = $item->getData();
                $_product = Mage::getModel('catalog/product')->load($item->getProductId());

                $wistlist[$a]['Id11'] = $item->getId(); // Wishlist ID
                $wistlist[$a]['ProductName'] = $_product->getName();
                $wistlist[$a]['ProductSku'] = $_product->getSku();
                $wistlist[$a]['ProductPrice'] = $_product->getPrice();
                $wishlist[$a]['IsinStock'] = $_product->getIsInStock();
                $wistlist[$a]['has_options'] = $_product->getHasOptions();
                if ($_product->getSpecialPrice() == NULL)
                    $wistlist[$a]['ProductSpecialPrice'] = '';
                else
                    $wistlist[$a]['ProductSpecialPrice'] = $_product->getSpecialPrice();
                $wistlist[$a]['ProductId'] = $item->getProductId();

                $item = Mage::getModel('catalog/product')->setStoreId($item->getStoreId())->load($item->getProductId());
                if ($item->getId()) {

                    $wistlist[$a]['ThumbnailImage'] = $imgurl . 'catalog/product' . $item->getThumbnail();
                }
                $a++;
            }
        }
        $wish = array('WishList' => $wistlist);
        //$this->response($this->json($wish), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($wish), 200);
    }

    // Add WishList
    public function addWishListAction() {
        $value = json_decode(file_get_contents('php://input'));
        // $this->check_method('POST');
        $customerId = $value->customerid;
        $customer = Mage::getModel('customer/customer')->load($customerId);
        $productId = $value->prodid;
        $wishlist = Mage::getModel('wishlist/wishlist')->loadByCustomer($customerId, true);
        $product = Mage::getModel('catalog/product')->load($productId);

        try {
            $buyRequest = new Varien_Object(array()); // any possible options that are configurable and you want to save with the product
            $result = $wishlist->addNewItem($product, $buyRequest);
            $wishlist->save();
            $wishmsg = array('status' => 1, 'message' => 'Products Added successfully!');
        } catch (Mage_Core_Exception $e) {
            $wishmsg = array('status' => 0, 'message' => 'Products not added!');
        }
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($wishmsg));
        //  $this->response($this->json($wishmsg), 200);       
    }

    // Delete WishList
    public function deleteWishListAction() {
        $value = json_decode(file_get_contents('php://input'));
        // $this->check_method('POST');
        $customerId = $value->customerid;
        $productId = $value->prodid;
        $itemCollection = Mage::getModel('wishlist/item')->getCollection()->addCustomerIdFilter($customerId);
        $w = 0;
        foreach ($itemCollection as $item) {
            $Itemid = $item->getId();

            if ($Itemid == $productId) {
                $Delete = Mage::getModel('wishlist/item')->load($productId)->delete();
                $w++;
            }
        }
        if ($w > 0) {
            $response = array('status' => 1, 'message' => 'Products Deleted successfully!');
        } else {
            $response = array('status' => 0, 'message' => 'Products Not Deletedd successfully!');
        }
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));

        //  $this->response($this->json($response), 200);
    }

    // Get Categories	
    public function getCategoriesAction() {
        $rootcatId = Mage::app()->getStore()->getRootCategoryId(); // get default store root category id
        $categories = Mage::getModel('catalog/category')->getCategories($rootcatId); // else use default category id =2
        //$this->response($this->json($this->show_categories_tree($categories)), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($this->show_categories_tree($categories)));
    }

    function show_categories_tree($categories) {
        $i = 0;
        $array = array();
        foreach ($categories as $category) {
            $cat = Mage::getModel('catalog/category')->load($category->getId());
            $count = $cat->getProductCount();
            $array[$i]['name'] = $category->getName();
            $array[$i]['id'] = $category->getId();

            if ($category->hasChildren()) {
                //$children = Mage::getModel('catalog/category')->getCategories($category->getId());
                $children = Mage::getModel('catalog/category')->load($category->getId())->getChildrenCategories();
                $array[$i]['cats'] = $this->show_categories_tree($children);
            }
            $i++;
        }
        return $array;
    }

    // Get selected product based on Product Ids neeeed to test
    public function getSelectedProductsAction() {
        $value = json_decode(file_get_contents('php://input'), true);
        //$this->check_method('POST');
        $prodids = $value['prodids'][0];

        for ($p = 0; $p < count($prodids); $p++) {
            $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct(Mage::getModel('catalog/product')->load($prodids[$p]))->getQty();
            $productdatas[] = Mage::getModel('catalog/product')->load($prodids[$p])->getData();
            $productdatas[$p]['qty'] = $stock;
        }
        $this->response($this->json($productdatas), 200);
    }

    // Get selected product based on Product Ids
    public function getCmsPageAction() {
        $value = json_decode(file_get_contents('php://input'));
        //  $this->check_method('POST');
        $pageIdentifier = $value->cmsid;
        $page = Mage::getModel('cms/page');
        $page->setStoreId(Mage::app()->getStore()->getId());
        $page->load($pageIdentifier, 'identifier');
        $page->load($pageIdentifier);
        $helper = Mage::helper('cms');
        $processor = $helper->getPageTemplateProcessor();
        $html = $processor->filter($page->getContent());


        $response = array('status' => 1, 'message' => $html);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        //$this->response($this->json( $response), 200);
    }

    //Add Address in Customer Account
    public function addAddressAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');
        $customerid = $value->customerid;
        $country = $value->country;
        $zipcode = $value->zipcode;
        $city = $value->city;
        $telephone = $value->telephone;
        $fax = $value->fax;
        $company = $value->company;
        $street = $value->street;
        $state = $value->state;
        $default_billing = $value->default_billing;
        $default_shipping = $value->default_shipping;

        $customer = Mage::getModel('customer/customer')->load($customerid);

        $address = Mage::getModel("customer/address");
        $address->setCustomerId($customer->getId())
                ->setFirstname($customer->getFirstname())
                ->setMiddleName($customer->getMiddlename())
                ->setLastname($customer->getLastname())
                ->setCountryId($country)
                ->setPostcode($zipcode)
                ->setCity($city)
                ->setRegion($state)
                ->setTelephone($telephone)
                ->setFax($fax)
                ->setCompany($company)
                ->setStreet($street)
                ->setSaveInAddressBook('1');

        if ($default_billing == 'Yes') {
            $address->setIsDefaultBilling('1');
        }
        if ($default_shipping == 'Yes') {
            $address->setIsDefaultShipping('1');
        }

        try {
            $address->save();
            $response = array('status' => 1, 'message' => 'Address added successfully!');
        } catch (Exception $e) {
            //Zend_Debug::dump($e->getMessage());
            $response = array('status' => 0, 'message' => $e->getMessage());
        }
        //	$this->response($this->json( $response), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    // Get Countries
    public function getCountriesAction() {
        $countryList = Mage::getModel('directory/country')->getResourceCollection()->loadByStore()->toOptionArray();
        //$this->response($this->json($countryList), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($countryList));
    }

    // Get Customer Address
    public function getAddressAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');
        $customerid = $value->customerid;
        $customer = Mage::getModel('customer/customer')->load($customerid);

        $billaddress = $customer->getDefaultBilling();
        $shipaddress = $customer->getDefaultShipping();
        if ($customer->getAddresses()) {
            foreach ($customer->getAddresses() as $address) {
                $data = $address->getData();
                if ($data['entity_id'] == $shipaddress) {
                    $data['default_shipping'] = 'Yes';
                } else {
                    $data['default_shipping'] = 'No';
                }
                if ($data['entity_id'] == $billaddress) {
                    $data['default_billing'] = 'Yes';
                } else {
                    $data['default_billing'] = 'No';
                }

                $datas[] = $data;
            }
        } else {
            $datas = array("status" => 0, "message" => "Address not available!");
        }
        //  $this->response($this->json($datas), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($datas));
    }

    public function deleteAddressAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');
        $addressid = $value->addressid;
        $address = Mage::getModel('customer/address')->load($addressid);
        try {
            $address->delete();
            $response = array('status' => 1, 'message' => 'Address deleted successfully!');
        } catch (Exception $e) {
            $response = array('status' => 0, 'message' => 'Address not deleted!');
        }
        // $this->response($this->json($response), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    public function editAddressAction() {
        $value = json_decode(file_get_contents('php://input'));
        // $this->check_method('POST');        
        $addressid = $value->addressid;
        $customerid = $value->customerid;
        $country = $value->country;
        $zipcode = $value->zipcode;
        $city = $value->city;
        $telephone = $value->telephone;
        $fax = $value->fax;
        $company = $value->company;
        $street = $value->street;
        $state = $value->state;
        $default_billing = $value->default_billing;
        $default_shipping = $value->default_shipping;

        $customer = Mage::getModel('customer/customer')->load($customerid);
        $address = Mage::getModel("customer/address")->load($addressid);

        $address->setCustomerId($customer->getId())
                ->setFirstname($customer->getFirstname())
                ->setMiddleName($customer->getMiddlename())
                ->setLastname($customer->getLastname())
                ->setCountryId($country)
                ->setPostcode($zipcode)
                ->setCity($city)
                ->setTelephone($telephone)
                ->setFax($fax)
                ->setCompany($company)
                ->setStreet($street)
                ->setRegion($state)
                ->setSaveInAddressBook('1');

        if ($default_billing == 'Yes') {
            $address->setIsDefaultBilling('1');
        }
        if ($default_shipping == 'Yes') {
            $address->setIsDefaultShipping('1');
        }

        try {
            $address->save();
            $response = array('status' => 1, 'message' => 'Address edited successfully!');
        } catch (Exception $e) {
            //Zend_Debug::dump($e->getMessage());
            $response = array('status' => 0, 'message' => 'Address not updated! Please try again!');
        }

        // $this->response($this->json($response), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
    }

    public function productSearchAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');
        $searchText = $value->searchkeyword;
        $query = Mage::getModel('catalogsearch/query')->loadByQueryText($searchText);
        $query = Mage::getModel('catalogsearch/query')->setQueryText($searchText)->prepare();
        $fulltextResource = Mage::getResourceModel('catalogsearch/fulltext')->prepareResult(
                Mage::getModel('catalogsearch/fulltext'), $searchText, $query
        );
        $collection = Mage::getResourceModel('catalog/product_collection');
        Mage::getSingleton('cataloginventory/stock')->addInStockFilterToCollection($collection);

        $collection->getSelect()->joinInner(
                array('search_result' => $collection->getTable('catalogsearch/result')), $collection->getConnection()->quoteInto(
                        'search_result.product_id=e.entity_id AND search_result.query_id=?', $query->getId()
                ), array('relevance' => 'relevance')
        );
        if (count($collection) > 0) {
            $resdata = array();
            $s = 0;
            foreach ($collection as $collections) {
                $productIds = $collections->getData('entity_id');
                $collection1 = Mage::getModel('catalog/product')->load($productIds);

                $resdata[$s]['entity_id'] = $collection1->getData('entity_id');
                $resdata[$s]['name'] = $collection1->getData('name');
                $resdata[$s]['sku'] = $collection1->getData('sku');
                $resdata[$s]['price'] = $collection1->getData('price');
                $resdata[$s]['description'] = $collection1->getData('description');
                $resdata[$s]['thumbnail'] = $collection1->getData('thumbnail');
                $resdata[$s]['isStock'] = $collection1->getData('is_in_stock');
                $resdata[$s]['hasOption'] = $collection1->getData('has_options');

                $resdata[$s]['special_price'] = $collection1->getData('special_price');
                $s++;
            }
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($resdata));
            //$this->response($this->json($resdata), 200);
        } else {
            //response=array('message'=>'No result found!','status'=>0,400);
            $response = array('message' => 'No product found!', 'status' => 0, 400);
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
        }
    }

    public function getSpecialProductsAction() {
        $value = json_decode(file_get_contents('php://input'));
        // $this->check_method('POST');
        $page = $value->page;
        $pricesort = $value->pricesort;
        if ($page == "") {
            $page = 1;
        } else {
            $page = $page;
        }
        if ($pricesort == "") {
            $pricesort = 'asc';
        } else {
            $pricesort = $pricesort;
        }

        $_productCollection = Mage::getModel('catalog/product')->getCollection();
        $_productCollection->addAttributeToSelect(array(
                    'image',
                    'name',
                    'short_description'
                ))
                ->addFieldToFilter('visibility', array(
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                )) //showing just products visible in catalog or both search and catalog
                ->addAttributeToFilter('status', 1)
                ->addFinalPrice()
                ->addAttributeToSort('price', $pricesort) //in case we would like to sort products by price
                ->setPage($page)
                ->setPageSize(20)
                ->getSelect()
                ->where('price_index.final_price < price_index.price');
        //*******************************************************************************************
        $productCollectionCount = Mage::getModel('catalog/product')->getCollection();
        $productCollectionCount->addAttributeToSelect(array(
                    'image',
                    'name',
                    'short_description'
                ))
                ->addFieldToFilter('visibility', array(
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG
                )) //showing just products visible in catalog or both search and catalog
                ->addFinalPrice()
                ->addAttributeToFilter('status', 1)
                ->getSelect()
                ->where('price_index.final_price < price_index.price');

        foreach ($_productCollection as $product) {
            $searchresult[] = $product->getData();
        }
        $res = array("totalproducts" => count($productCollectionCount), "products" => $searchresult);

        //  $this->response($this->json($res), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($res));
    }

    public function checkout123Action() {
        $value = json_decode(file_get_contents('php://input'), true);
        //  $this->check_method('POST');
        $jsonary = $value['str'];
        Mage::getSingleton('core/session', array('name' => 'frontend'));
        $session = Mage::getSingleton('customer/session');
        $session->start();
        $uid = $jsonary['uid'];
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        Mage::getSingleton('customer/session')->loginById($uid);
        $session->setData("device", 'app');

        $cart = Mage::getSingleton('checkout/cart');
        $quoteItems = Mage::getSingleton('checkout/session')
                ->getQuote()
                ->getItemsCollection();

        foreach ($quoteItems as $item) {
            $cart->removeItem($item->getId());
        }
        $cart->save();
        Mage::getSingleton('checkout/session')->setCartWasUpdated(true);

        // For Looop for add to cart
        for ($c = 0; $c < count($jsonary['prod']); $c++) {
            $id = $jsonary['prod'][$c]['p']; // Replace id with your product id
            $qty = $jsonary['prod'][$c]['q'];
            ; // Replace qty with your qty
            $_product = Mage::getModel('catalog/product')->load($id);
            //$cart = Mage::getModel('checkout/cart');
            $cart->init();
            $params = array(
                'product' => $id,
                'qty' => $qty
            );
            $stockStatus = Mage::getModel('cataloginventory/stock_item')
                    ->loadByProduct($_product)
                    ->getIsInStock();
            //echo '--> '.$_product->getStockItem()->getQty().'<br>';
            if ($stockStatus) {
                try {
                    $request = new Varien_Object();
                    $request->setData($params);
                    $cart->addProduct($_product, $request);
                    $cart->save();
                    Mage::getSingleton('checkout/session')->setCartWasUpdated(true);
                } catch (Mage_Core_Exception $e) {
                    //print_r($e);
                    //header('Location: http://192.168.1.2:82/axomart/axomartapi_cartempty.php');
                    //exit;
                }
            }
        }
        $cartitems = Mage::getSingleton('checkout/session')->getQuote()->getItemsCollection();

        if (count($cartitems) > 0) {
            //header('Location: http://192.168.1.2:82/axomart/onepage?device=app');
            $urlret = Mage::getStoreConfig('web/unsecure/base_url') . "onepage?device=app";
            $statuss = 1;
        } else {
            //header('Location: http://192.168.1.2:82/axomart/axomartapi_cartempty.php');
            $urlret = Mage::getStoreConfig('web/unsecure/base_url') . "axomartapi_cartempty.php";
            $statuss = 0;
        }
        $res = array('url' => $urlret, "status" => $statuss);
        // $this->response($this->json($res), 200);
    }

    function checkoutsuccessAction() {
        $value = json_decode(file_get_contents('php://input'));
        $res = array("status" => "Success", "orderid" => $value->orderid);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($res));
        //$this->response($this->json($res), 200);
    }

    // Images for Home page
    function imageSliderAction() {
        $sliders = Mage::getResourceModel('slider/slider_collection')->addFilter('status', 1);
        $sliders_data = $sliders->getData();
        $imgpath = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_MEDIA) . 'slider/image';
        //Title = Product or Category
        //URL = Product ID or Category ID
        // Image = image path
        $res = array("images" => $sliders_data, "imgpath" => $imgpath);
        // $this->response($this->json($res), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($res));
    }

    public function registerAction() {
        $value = json_decode(file_get_contents('php://input'));
        // $this->check_method('POST');        
        $device_id = @$value->device_id;
        $device_type = @$value->device_type;
        if (!empty($device_id)) {
            $token = md5(uniqid($device_id, true));
            // Select query
            $read = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sqlcheck = $read->query("SELECT * FROM tbl_register");

            $ext_deviceId = array();
            while ($exists = $sqlcheck->fetch()) {
                $ext_deviceId[] = $exists['device_id'];
            }
            if (in_array($device_id, $ext_deviceId)) {
                $success = array('status' => 1, "message" => "Last visit time updated.");
                //$update = mysql_query("UPDATE tbl_register SET last_visit=now() WHERE device_id=".$device_id);							
                $write = Mage::getSingleton('core/resource')->getConnection('core_write');
                $write->query("UPDATE tbl_register SET last_visit=now() WHERE device_id=" . $device_id);
                $this->response($this->json($success), 200);
            }
            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
            $sqlq = "INSERT INTO tbl_register " . "(`device_type`, `device_id`, `last_visit`) " . "VALUES ('{$device_type}', '{$device_id}',now());";
            $sql = $connection->query($sqlq);
            if ($sql) {
                $succss = array('status' => 1, "message" => "Register Succssfully.");
                //  $this->response($this->json($succss), 200);
                $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
            }
        }
        $error = array('status' => 0, "message" => "Faild to insert record");
        //$this->response($this->json($error), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
    }

    public function send_notificationAction() {
        // Select query
        $read = Mage::getSingleton('core/resource')->getConnection('core_read');
        $sqlQuery = $read->query("SELECT * FROM tbl_register");

        //$simple_message = "Easytask Test notification";
        $message_pass = array("msg" => 'Notification', "type" => "product", "id" => "1"); // product / category / home
        $iosmessage = "Iphone Notification";
        while ($exequery = $sqlQuery->fetch()) {
            if ($exequery['device_type'] == "Android") {
                $deviceIds[] = $exequery['device_id'];
                $this->sendPushnotificationToGCM($deviceIds, $message_pass);
            }
            if ($exequery['device_type'] == "Iphone") {
                $deviceIds = $exequery['device_id'];
                $this->iphonePushnotification($deviceIds, $iosmessage);
            }
        }
        $succss = array('status' => 1, "message" => "Notification Sended Succssfully.");
        // $this->response($this->json($succss), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
    }

    //generic php function to send GCM push notification
    function sendPushnotificationToGCMAction($registatoin_ids, $message) {


        //Google cloud messaging GCM-API url
        $url = 'https://android.googleapis.com/gcm/send';
        $fields = array(
            'registration_ids' => $registatoin_ids,
            'data' => $message
        );

        // Google Cloud Messaging GCM API Key
        define("GOOGLE_API_KEY", "AIzaSyCtlVRmuEBTUkYk1j1VMDs8Fc5LnfS_j5k");
        $headers = array(
            'Authorization: key=' . GOOGLE_API_KEY,
            'Content-Type: application/json'
        );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));
        $result = curl_exec($ch);
        if ($result === FALSE) {
            die('Curl failed: ' . curl_error($ch));
        }
        curl_close($ch);
        return $result;
    }

    function iphonePushnotificationAction($registatoin_ids, $message) {
        //echo $registatoin_ids; exit;
        //echo "iphonePushnotification";
        //exit;
        // Put your device token here (without spaces):
        //$deviceToken = '5b4125778b72e52ee0bee3b58ff3d45b5a0d2cedd0687164810037ecdb26c039';
        $deviceToken = $registatoin_ids;

        // Put yourpublic key's passphrase here:
        $passphrase = 'pushchat';
        // Put your alert message here:
        //$message = 'Easy Task Push notification!';
        ////////////////////////////////////////////////////////////////////////////////
        $ctx = stream_context_create();
        stream_context_set_option($ctx, 'ssl', 'local_cert', 'pushcert.pem');
        stream_context_set_option($ctx, 'ssl', 'passphrase', $passphrase);
        // Open a connection to the APNS server
        $fp = stream_socket_client(
                'ssl://gateway.sandbox.push.apple.com:2195', $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $ctx);
        if (!$fp)
            exit("Failed to connect: $err $errstr" . PHP_EOL);
        //echo 'Connected to APNS' . PHP_EOL;
        // Create the payload body
        $body['aps'] = array(
            'alert' => $message,
            'sound' => 'default',
            'url' => 'http://www.magentosupport.in'
        );
        // Encode the payload as JSON
        $payload = json_encode($body);

        // Build the binary notification
        $msg = chr(0) . pack('n', 32) . pack('H*', $deviceToken) . pack('n', strlen($payload)) . $payload;

        // Send it to the server
        $result = fwrite($fp, $msg, strlen($msg));
        if (!$result) {
            $error = array('status' => 0, "message" => "Faild to send notification");
            // $this->response($this->json($error), 200);
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
        }
        /* else{
          $succss = array('status' => 1, "message" => "Success");
          $this->response($this->json($succss), 200);
          } */
        fclose($fp);
        return $result;
    }

    public function savetempcartAction() {
        $value = json_decode(file_get_contents('php://input'));
        // $this->check_method('POST');
        $customerId = $value->customerid;
        $productId = $value->prodid;
        $qty = $value->qty;
        $flag = $value->flag;
        if (!empty($customerId)) {
            $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
            if ($flag == "deleteall") {
                $sqlq = "DELETE FROM tbl_save_cart WHERE user_id='" . $customerId . "'";
                $sql = $connection->query($sqlq);
                if ($sql) {
                    $succss = array('status' => 1, "message" => "All Item deleted succssfully.");
                    //  $this->response($this->json($succss), 200);
                    $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
                }
            } else if ($flag == "delete") {
                $sqlq = "DELETE FROM tbl_save_cart WHERE user_id='" . $customerId . "' AND product_id='" . $productId . "'";
                $sql = $connection->query($sqlq);
                if ($sql) {
                    $succss = array('status' => 1, "message" => "Item deleted succssfully.");
                    //  $this->response($this->json($succss), 200);
                    $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
                }
            } else {
                $read = Mage::getSingleton('core/resource')->getConnection('core_read');
                $sqlcheck1 = $read->query("SELECT * FROM tbl_save_cart WHERE user_id='" . $customerId . "' and product_id = '" . $productId . "'");

                if ($sqlcheck1->fetch()) {
                    $sqlq = "UPDATE tbl_save_cart SET user_id='" . $customerId . "' , product_id = '" . $productId . "', qty='" . $qty . "'  WHERE  user_id='" . $customerId . "' AND product_id = '" . $productId . "'";
                    $sql = $connection->query($sqlq);

                    if ($sql) {
                        $succss = array('status' => 1, "message" => "Update item in cart succssfully.");
                        // $this->response($this->json($succss), 200);
                        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
                    }
                } else {
                    $sqlq = "INSERT INTO tbl_save_cart " . "(`user_id`, `product_id`,`qty`) " . "VALUES ('{$customerId}', '{$productId}','{$qty}');";
                    $sql = $connection->query($sqlq);
                    if ($sql) {
                        $succss = array('status' => 1, "message" => "Added item in cart succssfully.");
                        //   $this->response($this->json($succss), 200);
                        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
                    }
                }
            }
        }
        $error = array('status' => 0, "message" => "Faild to save cart");
        // $this->response($this->json($error), 400);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
    }

    public function getTempcartproductAction() {
        $value = json_decode(file_get_contents('php://input'));
        $this->check_method('POST');
        $customerId = $value->customerid;
        if (!empty($customerId)) {
            $read = Mage::getSingleton('core/resource')->getConnection('core_read');
            $sqlcheck = $read->query("SELECT * FROM tbl_save_cart WHERE user_id='" . $customerId . "'");
            $data = array();
            while ($rows = $sqlcheck->fetch()) {
                $data[] = $rows;
            }
            if (!empty($data)) {
                $succss = array('status' => 1, "message" => "Cart product listed successfully", "productlist" => $data);
                // $this->response($this->json($succss), 200);	
                $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
            } else {
                $succss = array('status' => 2, "message" => "Cart data not available");
                // $this->response($this->json($succss), 200);
                $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
            }
        }
    }

    // Multiple save
    private function multisavetempcartAction() {
        $value = json_decode(file_get_contents('php://input'));
        $this->check_method('POST');
        $customerId = $value->uid;
        $products = $value->prod;
        if (!empty($customerId)) {
            for ($p = 0; $p < count($products); $p++) {
                $read = Mage::getSingleton('core/resource')->getConnection('core_read');
                $sqlcheck = $read->query("SELECT * FROM tbl_save_cart WHERE user_id='" . $customerId . "' and product_id = '" . $products[$p]['p'] . "'");
                $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
                if (!$sqlcheck->fetch()) {
                    $sqlq = "INSERT INTO tbl_save_cart " . "(`user_id`, `product_id`,`qty`) " . "VALUES ('{$customerId}', '{$products[$p][p]}','{$products[$p][q]}');";
                    $sql = $connection->query($sqlq);
                    $error = array('status' => 1, "message" => "Cart sync successfully!");
                    $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
                    //$this->response($this->json($error), 200);		
                }
            }
        } else {
            $error = array('status' => 0, "message" => "Error to sync cart!");
            // $this->response($this->json($error), 400);
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
        }
    }

    public function getCartfromWebAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');

        $customerId = $value->customerid; //$this->getRequest()->getParams('customerid');//$this->_request['customerid']


        if (!empty($customerId)) {

            $quote = Mage::getModel('sales/quote')->loadByCustomer($customerId);
            $session = Mage::getSingleton('core/session', array('name' => 'frontend'));
            $session = Mage::getSingleton('customer/session');
            $customer = Mage::getModel('customer/customer')->load($customerId);

            // load quote by customer
            $quote = Mage::getModel('sales/quote')->loadByCustomer($customerId);
            $quote->assignCustomer($customer);
            $cart = $quote->getAllVisibleItems();
            $productData = array();
            $p = 0;
            foreach ($cart as $item) {

                $productData[$p]['prodId'] = $item->getProductId();
                $productData[$p]['prodName'] = $item->getName();
                $productData[$p]['sku'] = $item->getSku();
                $productData[$p]['qty'] = $item->getQty();
                $productData[$p]['itemId'] = $item->getItemId();

                $productData[$p]['stock'] = $item->getIsInStock();
                $productData[$p]['price'] = $item->getPrice();
                $products = Mage::getModel('catalog/product')->load($item->getProductId());
                $productData[$p]['thumbnail'] = $products->getThumbnail();
                $productData[$p]['stock'] = $products->getStockItem()->getQty();


                //For getting cart product options
                $cartProductsOptions = array();
                $_customOptions = $item->getProduct()->getTypeInstance(true)->getOrderOptions($item->getProduct());
                foreach ($_customOptions['options'] as $_option) {
                    $cartProductsOptions[] = $_option;
                }
                $productData[$p]['customOptions'] = $cartProductsOptions;
                $p++;
            }
            //exit;     		
            if (!empty($productData)) {
                $succss = array('status' => 1, "message" => "Cart product listed successfully", "productlist" => $productData);

                //json encoding happens here
                $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
                //  $this->response($this->json($succss), 200);	        
            } else {
                $succss = array('status' => 2, "message" => "Cart data not available");
                $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
                // $this->response($this->json($succss), 200);	        
            }
        }
    }

    // Delete cart data from web 3-JUN-2015
    public function deleteProductswebCartAction() {
        $value = json_decode(file_get_contents('php://input'));
        $websiteId = Mage::app()->getWebsite()->getId();
        Mage::getSingleton('core/session', array('name' => 'frontend'));
        ////////$this->check_method('POST');		
        $customerId = $value->customerid;
        $itemId = $value->itemid;

        $customer = Mage::getModel('customer/customer')->load($customerId);
        // load quote by customer
        $quote = Mage::getModel('sales/quote')->loadByCustomer($customerId);

        if (!empty($customerId)) {
            if ($quote) {
                $collection = $quote->getItemsCollection(false);
                if ($collection->count() > 0) {
                    foreach ($collection as $item) {
                        if ($item->getId() == $itemId) {
                            $quote->removeItem($item->getId());
                            $quote->collectTotals()->save();
                            $succss = array('status' => 1, "message" => "Cart Item deleted successfully");
                            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
                            //  	$this->response($this->json($succss), 200);
                        }
                    }
                }
            }
        } else {
            $error = array('status' => 0, "message" => "Please put valid data");
            // $this->response($this->json($error), 200);
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
        }
    }

    // Add Product to cart on Web web 4-JUN-2015
    public function addProductToCartAction() {
        $value = json_decode(file_get_contents('php://input'), true);
        //$this->check_method('POST');
        $productId = $value['prodid'];
        $customerid = $value['customerid'];
        $qty = $value['qty'];
        $Custom_Options = $value['custom_options'];

        if (!empty($Custom_Options)) {
            $cOptions = array();
            for ($c = 0; $c < count($Custom_Options); $c++) {

                if ($Custom_Options[$c]['option_type_id'] != "") {
                    $cOptions[$Custom_Options[$c]['opt_id']] = $Custom_Options[$c]['option_type_id'];
                } else {
                    $cOptions[$Custom_Options[$c]['opt_id']] = $Custom_Options[$c]['opt_values'];
                }
            }
        }
        $quote = Mage::getModel('sales/quote')->loadByCustomer($customerid);
        $customer = Mage::getModel('customer/customer')->load($customerid);
        if ($quote->getCustomerId() == '') {
            $quote = Mage::getModel('sales/quote')->setStoreId(Mage::app()->getStore('default')->getId());
            $quote->assignCustomer($customer);
        }
        $product = Mage::getModel('catalog/product')
                ->setStoreId(1)
                ->load($productId);

        $cartItems = $quote->getAllVisibleItems();
        $Stock = $product->getStockItem()->getQty();


        foreach ($cartItems as $item) {
            if ($productId == $item->getProductId()) {
                if ($item->getQty() >= $Stock) {
                    $error = array('status' => 0, "message" => "The requested quantity is not available");

                    $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
                    exit;
                }
            }
        }
        $Stock = $product->getStockItem()->getQty();

        if ($cOptions) {
            //echo "custom option"; exit;
            $param = array(
                'product' => $product->getId(),
                'qty' => $qty,
                'options' => $cOptions
            );
        } else {
            $param = array(
                'product' => $product->getId(),
                'qty' => $qty
            );
        }
        //echo $item1->getQty(); exit;

        if (Stock >= $qty) {

            // Add Product to Quote
            $quote->addProduct($product, new Varien_Object($param));

            try {
                if ($quote->collectTotals()->save()) {
                    $succss = array('status' => 1, "message" => "Item Added to Cart");
                    //$this->response($this->json($succss), 200);
                    $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
                }
            } catch (Mage_Core_Exception $e) {
                $error = array('status' => 0, "message" => "Failed to Added to Cart");
                //$this->response($this->json($error), 200);
                $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
            }
        } else {
            $error = array('status' => 0, "message" => "The requested quantity is not available");
            // $this->response($this->json($error), 200);
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
        }
    }

    public function updateProductQuantityAction() {
        $value = json_decode(file_get_contents('php://input'));
        //$this->check_method('POST');		
        // single product id for test 3288		
        // custom options product id 3302
        $itemId = $value->itemid;
        $customerid = $value->customerid;
        $qty = $value->qty;
        $quote = Mage::getModel('sales/quote')->loadByCustomer($customerid);
        $customer = Mage::getModel('customer/customer')->load($customerid);
        $customer = Mage::getModel('customer/customer')->load($customerid);
        //$product = Mage::getModel('catalog/product')->load($pid);
        //get Item
        $cartItems = $quote->getAllVisibleItems();
        foreach ($cartItems as $item) {
            if ($item->getItemId() == $itemId) {
                $item->setQty($qty);
                $quote->save();
                $quote->save();
                if ($quote->save()) {
                    $succss = array('status' => 1, "message" => "Item Updated to Cart");
                    //$this->response($this->json($succss), 200);
                    $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
                }
            }
        }
    }

    public function checkoutAction() {
        $strg = $_REQUEST['str'];
//$strg = '{"uid": 34,"prod":[{"p": "4944","q": "1"}]}';
        $stt = base64_decode($_REQUEST['str']);
        $jsonary = json_decode($stt, true);

        Mage::getSingleton('core/session', array('name' => 'frontend'));
        $session = Mage::getSingleton('customer/session');
        $session->start();
        $uid = $jsonary['uid'];
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        Mage::getSingleton('customer/session')->loginById($uid);
        $session->setData("device", 'app');
        ?>
        <!--<div align="center" style="width:100%; text-align:center; height:500px;">-->
        <iframe src="<?php echo Mage::getStoreConfig('web/unsecure/base_url'); ?>/Mobileapi/index/customapi?str=<?= base64_encode($strg) ?>" width="100%" height="90%" style="border:0px; frameborder="0"  scrolling="yes"></iframe>
        <?php
    }

    public function customapiAction() {
        $stt = base64_decode($_REQUEST['str']);
        $jsonary = json_decode($stt, true);
        Mage::getSingleton('core/session', array('name' => 'frontend'));
        $session = Mage::getSingleton('customer/session');
        $session->start();
        $uid = $jsonary['uid'];
        $customer = Mage::getModel('customer/customer');
        $customer->setWebsiteId(Mage::app()->getWebsite()->getId());
        Mage::getSingleton('customer/session')->loginById($uid);
        $session->setData("device", 'app');



        $cartitems = Mage::getSingleton('checkout/session')->getQuote()->getItemsCollection();

        if (count($cartitems) > 0) {
            header('Location: ' . Mage::getStoreConfig('web/unsecure/base_url') . 'onepage?device=app');
            exit;
        } else {
            header('Location: ' . Mage::getStoreConfig('web/unsecure/base_url') . 'Mobileapi/index/customapi_cartempty');
            exit;
        }
    }

    public function social_shareAction() {

        $block = Mage::getModel('cms/block')->load('footer-social-share');
		//$block->getTitle();
        $succss = array('status' => 1, "message" => "Item Updated to Cart","content"=> $block->getContent());
                    //$this->response($this->json($succss), 200);
                    $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
    }
	 public function customapi_cartemptyAction() {

        echo '<h3>Error</h3>';
        echo '<p>Added item(s) are out of stock. Please try again!</p>';
    }

    public function customapi_successAction() {
        // $orderID= Mage::getSingleton('checkout/session')->getLastOrderId();
        $orderId = Mage::getSingleton('checkout/session')->getLastRealOrderId();
        $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);

        echo '<h2>Thank you for your purchase!</h2>';
        echo '<p>Your Order has been successfully placed. Your Order ID:' . $order->getIncrementId() . '</p>';
    }

    public function getProductReviewAction() {
        // $orderID= Mage::getSingleton('checkout/session')->getLastOrderId();
        $value = json_decode(file_get_contents('php://input'));
        $productId = $value->prodid;
        $reviews = Mage::getModel('review/review')
                ->getResourceCollection()
                ->addStoreFilter(Mage::app()->getStore()->getId())
                ->addEntityFilter('product', $productId)
                ->addStatusFilter(Mage_Review_Model_Review::STATUS_APPROVED)
                ->setDateOrder()
                ->addRateVotes();
        $reviewsdata = array();
        $a = 0;
        foreach ($reviews->getItems() as $review) {
            //$review->getData()
            $reviewsdata[$a]['ReviewId'] = $review->getReviewId();
            $reviewsdata[$a]['Title'] = $review->getTitle();
            $reviewsdata[$a]['CreatedAt'] = $review->getCreatedAt();
            $reviewsdata[$a]['EntityPkValue'] = $review->getEntityPkValue();
            $reviewsdata[$a]['Detail'] = $review->getDetail();
            $reviewsdata[$a]['Nickname'] = $review->getNickname();
            $reviewsdata[$a]['CustomerId'] = $review->getCustomerId();

            $a++;
        }
        $avg = 0;
        $ratings = array();
        if (count($reviews) > 0) {
            foreach ($reviews->getItems() as $review) {
                foreach ($review->getRatingVotes() as $vote) {
                    $ratings[] = $vote->getPercent();
                }
            }
            $avg = array_sum($ratings) / count($ratings);
        }





        if ($avg):
            ?>
            <div class="rating-box" style="float:left;">
                <div class="rating" style="width: <?php echo ceil($avg); ?>%; background:#FF0000;"></div>
            </div>
      
	    <?php
        endif;

        $succss = array('status' => 1, "revies" => $reviewsdata, "rating" => $ratings, "Avg" => $avg);
        //$this->response($this->json($succss), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
    }

    public function addReviewAction() {
        //
        $value = json_decode(file_get_contents('php://input'), true);
        // print_r($value);
        $productId = $value['prodid'];
        $customerId = $value['customerid'];
        $title = $value['title'];
        $details = $value['details'];
        $nicknane = $value['nickname'];
        $rating_options = $value['rating_options'];

        Mage::getSingleton("core/session", array("name" => "frontend"));
        $session = Mage::getSingleton('customer/session')->loginById($customerId);
        $customer = Mage::getModel('customer/customer')->load($customerId);
        $session = Mage::getSingleton('customer/session')->setCustomer($customer)->setCustomerAsLoggedIn($customer);
        // print_r($customer->getData()); exit;
        //$_session = Mage::getSingleton('customer/session')->loginById($customerId);

        $_review = Mage::getModel('review/review')
                ->setEntityPkValue($productId)
                ->setStatusId(2)
                ->setTitle($title)
                ->setDetail($details)
                ->setEntityId(1)
                ->setStoreId(Mage::app()->getStore()->getId())
                ->setStores(array(Mage::app()->getStore()->getId()))
                ->setCustomerId($customer->getEntityId())
                ->setNickname($nicknane)
                ->save();
        $succss = array('status' => 1, "message" => "Product Reviews Added");
        //$this->response($this->json($succss), 200);
        $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
        foreach ($rating_options as $rating_option) {
            //echo $rating_option['qty']; exit;
            $rating_options = array(
                1 => $rating_option['qty'], // <== Look at your database table `rating_option` for these vals
                2 => $rating_option['value'],
                3 => $rating_option['price']
            );
        }

// Now save the ratings
        foreach ($rating_options as $rating_id => $option_id):
            try {
                $_rating = Mage::getModel('rating/rating')
                        ->setRatingId($rating_id)
                        ->setReviewId($_review->getId())
                        ->setCustomerId($customer->getEntityId())
                        ->addOptionVote($option_id, $productId);
            } catch (Exception $e) {

                die(var_dump($e));
            }
        endforeach;
        $_review->aggregate();
    }

    public function contactUsAction() {
        $value = json_decode(file_get_contents('php://input'), true);
        // print_r($value);
       
		$emailTemplate  = Mage::getModel('core/email_template')->loadByCode('Contact_Us');
		//print_r($emailTemplate);
       // $emailTemplate = Mage::getModel('core/email_template')->load($emailTemplateId);

        $storeId = Mage::app()->getStore()->getId();
        //Variables for Confirmation Mail.
        $emailTemplateVariables = array();
        $emailTemplateVariables['data_name'] = $value['name'];
        $emailTemplateVariables['data_email'] = $value['email'];
        $emailTemplateVariables['data_telephone'] = $value['telephone'];
        $emailTemplateVariables['data_comment'] = $value['comment'];
        //$emailTemplate =  str_replace('{{var data.name}}', 'Banko',$emailTemplate);
        //Appending the Custom Variables to Template.
        $toemail = Mage::getStoreConfig('contacts/email/recipient_email'); //Mage::getStoreConfig('trans_email/ident_general/email', $storeId); 

        $toname = Mage::getStoreConfig('contacts/email/sender_email_identity'); // Mage::getStoreConfig('trans_email/ident_general/name',                           $storeId);contacts_email_sender_email_identi

        $processedTemplate = $emailTemplate->getProcessedTemplate($emailTemplateVariables);

        //Sending E-Mail to Customers.21

        $mail = Mage::getModel('core/email')
                ->setToName($toname)
                ->setToEmail($toemail)
                ->setBody($processedTemplate)
                ->setSubject($toname)
                ->setFromEmail($value['email'])
                ->setFromName($value['name'])
                ->setType('html');

        try {

            //Confimation E-Mail Send
            $mail->send();
            if ($mail->send()) {
                $succss = array('status' => 1, "message" => 'Thank you for contacting us.');
                //$this->response($this->json($succss), 200);
                $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($succss));
            }
        } catch (Exception $error) {
            $error = array('status' => 0, "message" => "Email could not be sent");
            // $this->response($this->json($error), 200);
            $jsonData = $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($error));
            //Mage::getSingleton('core/session')->addError($error->getMessage());
            //echo $error->getMessage();
        }
    }

}

}else{
     echo "Please Enable Evince Mobileapi Extension"; exit;
} 