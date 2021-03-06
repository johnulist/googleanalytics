<?php
/**
* 2007-2014 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
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
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2014 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
**/

if (!defined('_PS_VERSION_'))
	exit;

class Googleanalytics extends Module
{
	protected $_js_state = 0;

	protected $_eligible = 0;

	protected $_filterable = 1;

	/**
	* initiate Google Analytics module
	*/
	public function __construct()
	{
		$this->name = 'googleanalytics';
		$this->tab = 'analytics_stats';
		$this->version = '0.9.7';
		$this->author = 'MageBinary';
		$this->ps_versions_compliancy = array('min' => '1.5', 'max' => '1.6');
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Google Analytics');
		$this->description = $this->l('This is the GoogleAnalytics extension for Prestashop, using enhanced e-commerce Google Analytics API');

		$this->confirmUninstall = $this->l('Are you sure you want to uninstall Google Analytics? You will lose all the data related to this module.');

	}

	/**
	* Module's installation
	* @return bool
	*/
	public function install()
	{
		if (Shop::isFeatureActive())
			Shop::setContext(Shop::CONTEXT_ALL);

		if (!parent::install() || !$this->registerHook('header') || !$this->registerHook('adminOrder') || !$this->registerHook('footer') || !$this->registerHook('home') || !$this->registerHook('productfooter') || !$this->registerHook('shoppingCart') || !$this->registerHook('top') || !$this->registerHook('backOfficeHeader') || !$this->registerHook('displayBackOfficeHeader') || !$this->registerHook('actionProductCancel') || !$this->registerHook('actionCartSave') || !$this->registerHook('displayShoppingCart'))
		return false;

		//drop transaction table
		Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'googleanalytics`');
		//create transaction table
		$query = 'CREATE TABLE `'._DB_PREFIX_.'googleanalytics` (
			`id_google_analytics` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`id_order` INT NOT NULL ,
			`sent` Boolean,
			`date_add` DateTime
			)';

		if (!Db::getInstance()->Execute($query))
			return $this->uninstall();

		return true;
	}

	/**
	* Module's uninstall
	* @return bool
	*/
	public function uninstall()
	{
		if (!parent::uninstall())
			return false;
		//drop transaction table
		return Db::getInstance()->Execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'googleanalytics`');
	}




	/**
	* back office return configuration form
	* @return mixed
	*/
	public function displayForm()
	{
		// Get default Language
		$default_lang = (int)Configuration::get('PS_LANG_DEFAULT');

		$helper = new HelperForm();

		// Module, t    oken and currentIndex
		$helper->module = $this;
		$helper->name_controller = $this->name;
		$helper->token = Tools::getAdminTokenLite('AdminModules');
		$helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;

		// Language
		$helper->default_form_language = $default_lang;
		$helper->allow_employee_form_lang = $default_lang;

		// Title and toolbar
		$helper->title = $this->displayName;
		$helper->show_toolbar = true;        // false -> remove toolbar
		$helper->toolbar_scroll = true;      // yes - > Toolbar is always visible on the top of the screen.
		$helper->submit_action = 'submit'.$this->name;
		$helper->toolbar_btn = array(
			'save' =>
				array(
					'desc' => $this->l('Save'),
					'href' => AdminController::$currentIndex.'&configure='.$this->name.'&save'.$this->name.
					'&token='.Tools::getAdminTokenLite('AdminModules'),
				),
			'back' => array(
				'href' => AdminController::$currentIndex.'&token='.Tools::getAdminTokenLite('AdminModules'),
				'desc' => $this->l('Back to list')
			)
		);

		$fields_form = array();
		// Init Fields form array
		$fields_form[0]['form'] = array(
			'legend' => array(
				'title' => $this->l('Google Analytics General Settings'),
			),
			'input' => array(
				//add enable&disable switch
				array(
					'type' => 'switch',
					'label' => $this->l('Enable Google Analytics Module'),
					'name' => 'googleanalytics_enable',
					'is_bool' => true,
					'required' => true,
					'values' => array(
						array(
						'id' => 'googleanalytics_enable_yes',
						'value' => 1,
						'label' => $this->l('Yes'),
						),
						array(
						'id' => 'googleanalytics_enable_no',
						'value' => 0,
						'label' => $this->l('No')
						)
					),
					'hint' => $this->l('Enable or disable Google Analytics')
				),
				//add google analytics
				array(
					'type' => 'text',
					'label' => $this->l('Google Analytics Tracking ID'),
					'name' => 'GA_ACCOUNT_ID',
					'size' => 20,
					'required' => true,
					'hint'=> $this->l('Get tracking Id from google')
				),

			),
			'submit' => array(
				'title' => $this->l('Save'),
				'class' => 'button'
			)
		);
		// Load current value
		$helper->fields_value['googleanalytics_enable'] = Configuration::get('googleanalytics_enable');
		$helper->fields_value['GA_ACCOUNT_ID'] = Configuration::get('GA_ACCOUNT_ID');

		return $helper->generateForm($fields_form);
	}

	/**
	* back office module configuration page content
	*/
	public function getContent()
	{
		$output = '';
		if (Tools::isSubmit('submit'.$this->name))
		{
			$error = true;
			$googleanalytics_enable = Tools::getValue('googleanalytics_enable');
			$ga_account_id = Tools::getValue('GA_ACCOUNT_ID');

			if ($error == true)
			{
				Configuration::updateValue('googleanalytics_enable', $googleanalytics_enable);
				Configuration::updateValue('GA_ACCOUNT_ID', $ga_account_id);
				$output .= $this->displayConfirmation($this->l('Settings updated'));
			}
		}
		return $output.$this->displayForm();
	}


	/**
	* hook page header to add CSS and JS files
	*/
	public function hookDisplayHeader()
	{
		if (Configuration::get('googleanalytics_enable') != '' && Configuration::get('GA_ACCOUNT_ID') != '')
		{
			$this->context->smarty->assign(
				array(
					'GA_ACCOUNT_ID' => Configuration::get('GA_ACCOUNT_ID'),
				)
			);
			return $this->display(__FILE__, 'hookDisplayHeader.tpl');
		}
	}


	/**
	* return a detailed transaction for google analytics
	*/
	public function wrapOrder($id_order)
	{
		$order = new Order((int)$id_order);

		if (isset($order))
		{
			$transaction = array(
				'orderid' => $id_order,
				'storename' => $this->context->shop->name,
				'grandtotal' => $order->total_paid,
				'shipping' => $order->total_shipping,
				'tax' => $order->total_paid_tax_incl,
				'url' => $this->context->link->getModuleLink('googleanalytics', 'ajax'),
			);
			return $transaction;
		}

		return null;
	}


	/**
	* hook top to track transactions
	*/
	public function hookDisplayTop()
	{
		$this->context->controller->addJs($this->_path.'js/'.'GoogleAnalyticActionLib.js');

		$controller_name = Tools::getValue('controller');

		//add google analytics order
		if ($controller_name == 'orderconfirmation')
		{
			//ORDERID OR ORDER REFERNCE?
			$id_order = $this->context->controller->id_order;
			$order = new Order($id_order);
			$order_products = array();

			foreach ($order->getProducts() as $order_product)
				$order_products[] = $this->wrapProduct((int)$order_product['product_id'], array('qty'=>$order_product['product_quantity']));

			$ga_order_record = Db::getInstance()->getRow('SELECT sent FROM  `'._DB_PREFIX_.'googleanalytics` WHERE id_order = '.(int)$id_order);

			if ($ga_order_record == null)
			{
				Db::getInstance()->execute('INSERT INTO  `'._DB_PREFIX_.'googleanalytics` (id_order, sent, date_add) VALUES ('.$id_order.',false,'.time().') ');
				$ga_order_record = Db::getInstance()->getRow('SELECT sent FROM  `'._DB_PREFIX_.'googleanalytics` WHERE id_order = '.(int)$id_order);
			}

			if ($ga_order_record['sent'] != true)
			{
				$transaction = array(
						'orderid' => $order->reference,
						'affiliation' => $this->context->shop->name,
						'revenue' => $order->total_paid,
						'shipping' => $order->total_shipping,
						'tax' => $order->total_paid_tax_incl - $order->total_paid_tax_excl,
						'url' => $this->context->link->getModuleLink('googleanalytics', 'ajax'),
				);
				$ga_scripts = $this->addTransaction($order_products, $transaction);
				return $this->runJS($ga_scripts);
			}
		}
	}

	/**
	* hook footer to load JS script for standards actions such as product clicks
	*/

	public function hookDisplayFooter()
	{
		$ga_scripts = '';
		//ProductSale::getBestSalesLight((int)$params['cookie']->id_lang, 0, 8)
		$controller_name = Tools::getValue('controller');

		$products = $this->wrapProducts($this->context->smarty->getTemplateVars('products'));

		// hook add remove from cart start
		$cart_products = $this->wrapProducts($this->context->cart->getProducts());

		$this->context->smarty->assign(
			array(
				'remove_cart_products' => $cart_products,
			)
		);

		$cart_actions = $this->context->cookie->__get('ga_cart');
		//print_r($cart_actions);
		if (isset($cart_actions))
		{
			$this->_filterable = 0;
			$ga_scripts .= $cart_actions;
			$this->context->cookie->__unset('ga_cart');
		}

		if ($controller_name == 'order')
		{
			$this->_eligible = 1;
			$step = Tools::getValue('step');
			if (empty($step))
				$step = 0;
			$ga_scripts .= $this->addProductFromCheckout($products, $step);
			$ga_scripts .= "MBG.addCheckout('".$step."');";
		}

		if ($controller_name == 'order-confirmation')
		{
			$this->_eligible = 1;
			$ga_scripts .= $this->addCheckout($this->l('Order Confirmation'));
		}

		if (count($products) > 0)
		{
			if ($this->_eligible == 0)
			$ga_scripts .= $this->addProductImpression($products);
			$ga_scripts .= $this->addProductClick($products);
		}

		return $this->runJS($ga_scripts);

	}

	protected function filter($ga_scripts) 
	{
		if ($this->_filterable = 1) 
		{
			$fliter = explode(';', $ga_scripts);
			$fliter = array_unique($fliter);
			$fliter = implode(';', $fliter);
			$ga_scripts = $fliter;
		}
		return $ga_scripts;
	}

	/**
	* hook home to display generate the product list associated to home featured, news products and best sellers Modules
	*/
	public function hookDisplayHome()
	{
		$ga_scripts = '';

		//add home featured products
		if (!isset(HomeFeatured::$cache_products))
		{
			$category = new Category($this->context->shop->getCategory(), $this->context->language->id);
			$nb = (int)Configuration::get('HOME_FEATURED_NBR');
			$home_featured_products = $this->wrapProducts($category->getProducts((int)Context::getContext()->language->id, 1, ($nb ? $nb : 8), 'position'));

			$ga_scripts .= $this->addProductImpression($home_featured_products);
			$ga_scripts .= $this->addProductClick($home_featured_products);
		}

		//add new products list
		if (Configuration::get('NEW_PRODUCTS_NBR'))
		{
			if (Configuration::get('PS_NB_DAYS_NEW_PRODUCT') || Configuration::get('PS_BLOCK_NEWPRODUCTS_DISPLAY'))
			{
				$newProducts = Product::getNewProducts((int)$this->context->language->id, 0, (int)Configuration::get('NEW_PRODUCTS_NBR'));

				$ga_homenew_product_list = $this->wrapProducts($newProducts);

				$ga_scripts .= $this->addProductImpression($ga_homenew_product_list);
				$ga_scripts .= $this->addProductClick($ga_homenew_product_list);
			}
		}

		// add best sell product list
		if (!Configuration::get('PS_CATALOG_MODE') || Configuration::get('PS_BLOCK_BESTSELLERS_DISPLAY'))
		{
			$bestsell_products = ProductSale::getBestSalesLight((int)$this->context->language->id, 0, 8);
			$currency = new Currency($this->context->cart->id_currency);
			$usetax = (Product::getTaxCalculationMethod((int)$this->context->customer->id) != PS_TAX_EXC);
			foreach ($bestsell_products as &$row)
				$row['price'] = Tools::displayPrice(Product::getPriceStatic((int)$row['id_product'], $usetax), $currency);

			$ga_homebestsell_product_list = $this->wrapProducts($bestsell_products);

			$ga_scripts .= $this->addProductImpression($ga_homebestsell_product_list);
			$ga_scripts .= $this->addProductClick($ga_homebestsell_product_list);
		}

		return $this->runJS($this->filter($ga_scripts));
	}

	/**
	* wrap products to provide a standard products information for google analytics script
	*/
	public function wrapProducts($products, $extras = array())
	{
		$result_products = array();
		if (!is_array($products))
			return;
		foreach ($products as $index => $product)
			$result_products[] = $this->wrapProduct($product, $extras, $index);

		return $result_products;
	}

	/**
	* wrap product to provide a standard product information for google analytics script
	*/
	public function wrapProduct($product, $extras, $index = 0)
	{
		$cache_id = '';
		$ga_product = '';

		if (isset($product->id))
		{
			$cache_id = $product->id;

		}
		elseif (isset($product['product_id']))
		{
			$cache_id = $product['product_id'];

		}
		elseif (isset($product['id_product']))
		{
			$cache_id = $product['id_product'];

		}
		elseif (is_int($product))
		{
			$cache_id  = $product;

		}

		$cache_id = 'GoogleAnalytics_product_'.$cache_id;

		if (Cache::isStored($cache_id))
		{
			$ga_product = Cache::retrieve($cache_id);
			return $ga_product;
		}

		$position = $index ? $index :'0';
		$product_qty = 1;
		$variant = null;

		if (isset($product['attributes_small']))
		{
			$variant = $product['attributes_small'];

		}

		if (isset($extras['attributes_small']))
		{
			$variant = $extras['attributes_small'];

		}
		/** Product Qty ***/
		if (isset($extras['qty']))
		{
			//
			$product_qty = $extras['qty'];
		}

		elseif (isset($product['cart_quantity']))
		{
			$product_qty = $product['cart_quantity'];

		}

		if (isset($product->id))
		{
			$product = new Product($product->id, true, $this->context->language->id, $this->context->shop->id);

		}
		elseif (is_int($product))
		{
			//
			$product = new Product($product, true, $this->context->language->id, $this->context->shop->id);
		}

		elseif (isset($product['product_id']) && is_int($product['product_id']))
		{
			$product = new Product($product['product_id'], true, $this->context->language->id, $this->context->shop->id);
			if ($product['product_quantity'])
				$product_qty = $product['product_quantity'];
		}

		elseif (!empty($product['id_product']))
		{
			/** Product Link ***/
			if (isset($product['link']))
				$product_link = $product['link'] ? :'';

			$product = new Product($product['id_product'], true, $this->context->language->id, $this->context->shop->id);

			if (isset($product->link))
				$product_link = $product->link;
		}

		if (Validate::isLoadedObject($product))
		{
			$category = new Category($product->id_category_default);
			$manufacturer = Manufacturer::getNameById((int)$product->id_manufacturer);

			$producttype = 'typical';
			if (isset($product->pack) && $product->pack == 1)
				$producttype = 'pack';
			if (isset($product->vitural) && $product->vitural == 1)
				$producttype = 'vitural';

			$ga_product = array(
				'id'=>$product->reference,
				'name'=>Product::getProductName($product->id),
				'category'=>$category->getName(),
				'brand'=>$manufacturer,
				'variant'=>$variant,
				'type'=>$producttype,
				'position'=>$position,
				'quantity'=>$product_qty,
				'list'=>Tools::getValue('controller'),
				'url'=>isset($product_link) ? $product_link : '',
				'price'=>number_format($product->price, '2')
			);

			Cache::store($cache_id, $ga_product);

		}

		return $ga_product;

	}

	/**
	* add order transaction
	*/
	public function addTransaction($products, $order)
	{
		$js = '';
		if (!is_array($products))
			return;
		foreach ($products as $product)
			$js .= 'MBG.add('.Tools::jsonEncode($product).');';
		$js .= 'MBG.addTransaction('.Tools::jsonEncode($order).');';
		return $js;
	}

	/**
	* add product impression js and product click js
	*/
	public function addProductImpression($products)
	{
		$js = '';
		if (!is_array($products))
			return;
		foreach ($products as $product)
			$js .= 'MBG.add('.Tools::jsonEncode($product).",'',true);";

		return $js;
	}

	public function addProductClick($products)
	{
		$js = '';
		if (!is_array($products))
			return;
		foreach ($products as $product)
			$js .= 'MBG.addProductClick('.Tools::jsonEncode($product).');';

		return $js;
	}

	public function addProductClickByHttpReferal($products)
	{
		$js = '';
		if (!is_array($products))
			return;
		foreach ($products as $product)
			$js .= 'MBG.addProductClickByHttpReferal('.Tools::jsonEncode($product).');';
		return $js;
	}


	/**
	* add product checkout info
	*/

	public function addProductFromCheckout($products)
	{
		$js = '';
		if (!is_array($products))
			return;
		foreach ($products as $product)
			$js .= 'MBG.add('.Tools::jsonEncode($product).');';
		return $js;
	}

	/**
	* hook product page footer to load JS for product details view
	*/
	public function hookDisplayFooterProduct()
	{
		$controller_name = Tools::getValue('controller');
		if ($controller_name == 'product')
		{
			//add product view
			$js = '';
			$id_product = (int)Tools::getValue('id_product');
			$ga_product = $this->wrapProduct($id_product, null);
			$js .= 'MBG.addProductDetailView('.Tools::jsonEncode($ga_product).');';

				if (isset($_SERVER['HTTP_REFERER']))
				{
					if (strpos($_SERVER['HTTP_REFERER'], $_SERVER['HTTP_HOST']) > 0)
					{
						//
						$js .= $this->addProductClickByHttpReferal(array($ga_product));
					}
				}

			return $this->runJS($js);
		}

		return null;
	}

	/**
	* hook shopping cart footer to send the checkout details
	*/
	public function hookDisplayShoppingCartFooter()
	{
		// hook add remove from cart
		$cart_products = $this->wrapProducts($this->context->cart->getProducts());
		$this->context->smarty->assign(
			array(
				'remove_cart_products' => $cart_products,
			)
		);
	}

	/**
	* generate google analytics js
	*/
	public function runJS($jscode)
	{
		if ($this->_js_state != 1 && $this->context->controller->controller_type != 'admin')
		{
			//
			$jscode .= 'ga("send", "pageview");';
		}
		$currency = $this->context->currency->iso_code;
		$js = "
			<script>
				jQuery(document).ready(function(){
					var MBG = GoogleAnalyticEnhancedECommerce;
					MBG.setCurrency('$currency');
					".
					$jscode
					.'
				});

			</script>
		';

		$this->_js_state = 1;

		return $js;
	}


	/**
	* hook admin order to send transactions and refunds details
	*/
	public function hookDisplayAdminOrder()
	{
		$ga_scripts = $this->context->cookie->__get('ga_admin_refund');

		echo $this->runJS($ga_scripts);

		$this->context->cookie->__unset('ga_admin_refund');
	}

	/**
	 *  admin office header to add google analytics js
	 */
	public function hookDisplayBackOfficeHeader()
	{
		$this->context->controller->addJs($this->_path.'js/'.'GoogleAnalyticActionLib.js');

		if (Configuration::get('googleanalytics_enable') != '' && Configuration::get('GA_ACCOUNT_ID') != '')
		{
			$this->context->smarty->assign('GA_ACCOUNT_ID', Configuration::get('GA_ACCOUNT_ID'));

			$ga_scripts = '';
			$ga_order_records = Db::getInstance()->ExecuteS('select * from  `'._DB_PREFIX_.'googleanalytics` where sent=0');

			foreach ($ga_order_records as $row)
			{
				$transaction = $this->wrapOrder($row['id_order']);
				if (isset($transaction))
				{
					$transaction = Tools::jsonEncode($transaction);
					$ga_scripts .= 'MBG.addTransaction('.$transaction.');';
				}
			}

			return $this->display(__FILE__, 'hookDisplayBackOfficeHeader.tpl').$this->runJS($ga_scripts);
		}
	}


	/**
	 * hook admin office header to add google analytics js
	*/
	public function hookActionProductCancel($params)
	{
		$qty_refunded = Tools::getValue('cancelQuantity');

		$order = array(
			'id' => $params['order']->reference,
		);

		$ga_scripts = '';
		foreach ($qty_refunded as $orderdetail_id => $qty)
		{
			$orderdetail = new OrderDetail($orderdetail_id);

			$product = array(
				//'id' => $orderdetail->product_id,
				'id' => $orderdetail->product_reference,
				'quantity' => $qty,
			);
			//display ga refund product
			$ga_scripts .= 'MBG.add('.Tools::jsonEncode($product).');';
		}
			$ga_scripts .= 'MBG.refundByProduct('.Tools::jsonEncode($order).');';

		$this->context->cookie->__set('ga_admin_refund', $ga_scripts);
	}


	/**
	 * hook save cart event to implement addtocart and remove from cart functionality
	*/
	public function hookActionCartSave()
	{
		$ga_scripts  = '';
		if (!isset($this->context->cart))
			return;
		//Prestashop Bugs with post action wrong returning add with true.
		$cart = array(
			'controller' => Tools::getValue('controller'),
			'addAction' => Tools::getValue('add') ? 'add':'',
			'removeAction' => Tools::getValue('delete') ? 'delete':'',
			'extraAction' => Tools::getValue('op'),
			'qty'=> Tools::getValue('qty') ? :'1'
			);
		try {
			$cart_products = $this->context->cart->getProducts();
		} catch (Exception $e)
		{
			$ga_scripts .= '';
		}

		if (isset($cart_products))
		{
			foreach ($cart_products as $cart_product)
			{
				if ($cart_product['id_product'] == Tools::getValue('id_product'))
				{
					//
					$cart['attributes_small'] = $cart_product['attributes_small'];
				}

			}
		}

		$ga_products = $this->wrapProduct((int)Tools::getValue('id_product'), $cart);

		if ($cart['removeAction'] == 'delete' || $cart['extraAction'] == 'down')
		{
			//
			$ga_scripts .= 'MBG.removeFromCart('.Tools::jsonEncode($ga_products).');';
		}
		else
		{
			//Sometime cartsave is called in checkout
			if (Tools::getValue('step') <= 0)
			{
				//
				$ga_scripts .= 'MBG.addToCart('.Tools::jsonEncode($ga_products).');';
			}

		}

		$this->context->cookie->__set('ga_cart', $this->context->cookie->__get('ga_cart').$ga_scripts);
	}


}
