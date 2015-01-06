<?php
/**
 * Ak  NotifyNewRegistration
 * This module is Notify to  admin at new Registration .
 * @category    Ak
 * @package     Ak_NotifyNewRegistration
 */

/**
 * Ak  NotifyNewRegistration
 *
 * @category   Ak
 * @package    Ak_NotifyNewRegistration
 * @author     Adesh Kumar <adeshsuryan2005@gmail.com>
 */
 
class Ak_NotifyNewRegistration_Model_Observer {
    protected $_email;
    protected $_name;
    protected $_identity;
    protected $_template;
    protected $_copyTo;
    protected $_copyMethod;
    protected $_enabled;

    public function __construct() {
        $this->_enabled = Mage::getStoreConfig('customer/registration_notification/enabled');
        $this->_identity = Mage::getStoreConfig('customer/registration_notification/identity');
        $this->_email = Mage::getStoreConfig('trans_email/ident_'.$this->_identity.'/email');
        $this->_name = Mage::getStoreConfig('trans_email/ident_'.$this->_identity.'/name');
        $this->_template = Mage::getStoreConfig('customer/registration_notification/template');
        $this->_copyTo = Mage::getStoreConfig('customer/registration_notification/copy_to');
        if(!empty($this->_copyTo)) {
            $this->_copyTo = explode(',', $this->_copyTo);
        }
        $this->_copyMethod = Mage::getStoreConfig('customer/registration_notification/copy_method');
    }

    /**
     * Normal registration
     * @param Varien_Event_Observer $observer
     */
    public function customerRegisterSuccess(Varien_Event_Observer $observer) {
        /* @var Mage_Customer_Model_Customer $customer */
        $customer = $observer->getEvent()->getCustomer();
        $this->sendCustomerRegistrationNotification($this->getCustomerData($customer->getEntityId()), $customer->getStore()->getId());
    }

    /**
     * Registration during onepage checkout
     *
     * @param Varien_Event_Observer $observer
     */
    public function checkoutTypeOnepageSaveOrderAfter(Varien_Event_Observer $observer) {
        if($observer->getEvent()->getQuote()->getCheckoutMethod(true) == Mage_Sales_Model_Quote::CHECKOUT_METHOD_REGISTER) {
            /* @var Mage_Customer_Model_Customer $customer */
            $customer = $observer->getEvent()->getOrder()->getCustomer();
            $this->sendCustomerRegistrationNotification($this->getCustomerData($customer->getEntityId()), $customer->getStore()->getId());
        }
    }

    /**
     * Helper method to send the email
     *
     * @param Varien_Object $customerData All needed customer data in a cleaned Varien_Object.
     * @param int $storeId The store id of the customer.
     */
    protected function sendCustomerRegistrationNotification($customerData, $storeId) {
        if(!$this->_enabled) {
            return;
        }
        /* @var Mage_Core_Model_Email_Template_Mailer $mailer */
        $mailer = Mage::getModel('core/email_template_mailer');
        /* @var Mage_Core_Model_Email_Info $emailInfo */
        $emailInfo = Mage::getModel('core/email_info');

        $emailInfo->addTo($this->_email, $this->_name);
        if($this->_copyTo && $this->_copyMethod == 'bcc') {
            // Add bcc to customer email
            foreach($this->_copyTo as $email) {
                $emailInfo->addBcc($email);
            }
        }
        $mailer->addEmailInfo($emailInfo);

        // Email copies are sent as separated emails if their copy method is 'copy'
        if($this->_copyTo && $this->_copyMethod == 'copy') {
            foreach($this->_copyTo as $email) {
                $emailInfo = Mage::getModel('core/email_info');
                $emailInfo->addTo($email);
                $mailer->addEmailInfo($emailInfo);
            }
        }

        // Set all required params and send emails
        $mailer->setSender($this->_identity);
        $mailer->setStoreId($storeId);
        $mailer->setTemplateId($this->_template);
        $mailer->setTemplateParams($customerData);
        $mailer->send();
    }

    /**
     * @param int|string $customer_id
     * @return Varien_Object
     */
    protected function getCustomerData($customer_id) {
        $customer = Mage::getModel('customer/customer')->load($customer_id);

        $customerArr = array();
        $customerArr['email'] = $customer->getEmail();
        $customerArr['prefix'] = $customer->getPrefix();
        $customerArr['firstname'] = $customer->getFirstname();
        $customerArr['middlename'] = $customer->getMiddlename();
        $customerArr['lastname'] = $customer->getLastname();
        $customerArr['suffix'] = $customer->getSuffix();
        $customerArr['taxvat'] = $customer->getTaxvat();

        foreach($customer->getAddresses() as $address) {
            /* @var Mage_Customer_Model_Address $address */
            $addressArr = array();
            $addressArr['prefix'] = $address->getPrefix();
            $addressArr['firstname'] = $address->getFirstname();
            $addressArr['middlename'] = $address->getMiddlename();
            $addressArr['lastname'] = $address->getLastname();
            $addressArr['suffix'] = $address->getSuffix();
            $addressArr['company'] = $address->getCompany();
            $addressArr['city'] = $address->getCity();
            $addressArr['country_id'] = $address->getCountryId();
            $addressArr['region'] = $address->getRegion();
            $addressArr['postcode'] = $address->getPostcode();
            $addressArr['telephone'] = $address->getTelephone();
            $addressArr['fax'] = $address->getFax();
            $addressArr['street'] = $address->getStreet();
            if(count($addressArr['street']) == 1) {
                $addressArr['street'] = $addressArr['street'][0];
            }

            $customerArr['address'] = new Varien_Object($addressArr);

            return array('customer' => new Varien_Object($customerArr));
        }

        return array('customer' => new Varien_Object($customerArr));
    }
}
