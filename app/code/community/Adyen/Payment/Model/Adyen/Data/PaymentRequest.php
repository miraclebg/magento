<?php

/**
 * Adyen Payment Module
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
 * @category    Adyen
 * @package    Adyen_Payment
 * @copyright    Copyright (c) 2011 Adyen (http://www.adyen.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Payment Gateway
 * @package    Adyen_Payment
 * @author     Adyen
 * @property   Adyen B.V
 * @copyright  Copyright (c) 2014 Adyen BV (http://www.adyen.com)
 */
class Adyen_Payment_Model_Adyen_Data_PaymentRequest extends Adyen_Payment_Model_Adyen_Data_Abstract
{

    public $additionalAmount;
    public $amount;
    public $bankAccount;
    public $browserInfo;
    public $card;
    public $dccQuote;
    public $deliveryAddress;
    public $deliveryDate;
    public $elv;
    public $fraudOffset;
    public $merchantAccount;
    public $mpiData;
    public $orderReference;
    public $recurring;
    public $selectedBrand;
    public $selectedRecurringDetailReference;
    public $shopperEmail;
    public $shopperIP;
    public $shopperInteraction;
    public $shopperReference;
    public $shopperStatement;
    public $additionalData;

    // added for boleto
    public $shopperName;
    public $socialSecurityNumber;
    const GUEST_ID = 'customer_';

    public function __construct()
    {
        $this->browserInfo = new Adyen_Payment_Model_Adyen_Data_BrowserInfo();
        $this->card = new Adyen_Payment_Model_Adyen_Data_Card();
        $this->amount = new Adyen_Payment_Model_Adyen_Data_Amount();
        $this->elv = new Adyen_Payment_Model_Adyen_Data_Elv();
        $this->additionalData = new Adyen_Payment_Model_Adyen_Data_AdditionalData();
        $this->shopperName = new Adyen_Payment_Model_Adyen_Data_ShopperName(); // for boleto
        $this->bankAccount = new Adyen_Payment_Model_Adyen_Data_BankAccount(); // for SEPA
    }

    public function create(Varien_Object $payment, $amount, $order, $paymentMethod = null, $merchantAccount = null, $recurringType = null, $enableMoto = null)
    {
        $incrementId = $order->getIncrementId();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        // override amount because this amount uses the right currency
        $amount = $order->getGrandTotal();
        $customerId = $order->getCustomerId();
        $realOrderId = $order->getRealOrderId();

        $this->reference = $incrementId;
        $this->merchantAccount = $merchantAccount;
        $this->amount->currency = $orderCurrencyCode;
        $this->amount->value = Mage::helper('adyen')->formatAmount($amount, $orderCurrencyCode);

        //shopper data
        $customerEmail = $order->getCustomerEmail();
        $this->shopperEmail = $customerEmail;
        $this->shopperIP = $order->getRemoteIp();
        $this->shopperReference = (!empty($customerId)) ? $customerId : self::GUEST_ID . $realOrderId;

        // add recurring type for oneclick and recurring
        if ($recurringType) {
            $this->recurring = new Adyen_Payment_Model_Adyen_Data_Recurring();
            $this->recurring->contract = $recurringType;
        }

        /**
         * Browser info
         * @var unknown_type
         */
        $this->browserInfo->acceptHeader = $_SERVER['HTTP_ACCEPT'];
        $this->browserInfo->userAgent = $_SERVER['HTTP_USER_AGENT'];

        switch ($paymentMethod) {
            case "elv":
                $elv = unserialize($payment->getPoNumber());
                $this->card = null;
                $this->shopperName = null;
                $this->bankAccount = null;
                $this->elv->accountHolderName = $elv['account_owner'];
                $this->elv->bankAccountNumber = $elv['account_number'];
                $this->elv->bankLocation = $elv['bank_location'];
                $this->elv->bankLocationId = $elv['bank_location'];
                $this->elv->bankName = $elv['bank_name'];
                break;
            case "cc":
            case "oneclick":

                $this->shopperName = null;
                $this->elv = null;
                $this->bankAccount = null;

                $recurringDetailReference = $payment->getAdditionalInformation("recurring_detail_reference");

                // set shopperInteraction
                if ($recurringType == "RECURRING") {
                    $this->shopperInteraction = "ContAuth";
                } else {
                    $this->shopperInteraction = "Ecommerce";
                }


                if (Mage::app()->getStore()->isAdmin() && $enableMoto != null && $enableMoto == 1) {
                    $this->shopperInteraction = "Moto";
                }

                if (Mage::getModel('adyen/adyen_cc')->isCseEnabled()) {

                    if ($recurringDetailReference && $recurringDetailReference != "") {
                        $this->selectedRecurringDetailReference = $recurringDetailReference;
                    }

                    $this->card = null;
                    $kv = new Adyen_Payment_Model_Adyen_Data_AdditionalDataKVPair();
                    $kv->key = new SoapVar("card.encrypted.json", XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");
                    $kv->value = new SoapVar($payment->getAdditionalInformation("encrypted_data"), XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");

                    // TULONJA CUSTOMIZATION
                    //                    $this->additionalData->entry = $kv;
                    $this->card = null;
                    $kv = new Adyen_Payment_Model_Adyen_Data_AdditionalDataKVPair();
                    $kv->key = new SoapVar("card.encrypted.json", XSD_STRING, "string",
                        "http://www.w3.org/2001/XMLSchema");
                    $kv->value = new
                    SoapVar($payment->getAdditionalInformation("encrypted_data"),
                        XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");


                    $acquirer = new
                    Adyen_Payment_Model_Adyen_Data_AdditionalDataKVPair();
//
                    $acquirer->key = new SoapVar("acquirerCode",
                        XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");
                    $acquirerValue = "BancoSabadellRedsys";
                    $acquirer->value = new SoapVar($acquirerValue,
                        XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");

                    $mid = new
                    Adyen_Payment_Model_Adyen_Data_AdditionalDataKVPair();
                    $mid->key = new SoapVar("authorisationMid",
                        XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");
                    $midValue = "327707980";
                    $mid->value = new SoapVar($midValue, XSD_STRING,
                        "string", "http://www.w3.org/2001/XMLSchema");


                    $array = array();
                    $array[] = $kv;
                    $array[] = $acquirer;
                    $array[] = $mid;

                    $this->additionalData->entry = $array;
                    // END OF TULONJA CUSTOMIZATION
                } else {

                    if ($recurringDetailReference && $recurringDetailReference != "") {
                        $this->selectedRecurringDetailReference = $recurringDetailReference;

                        if ($recurringType != "RECURRING") {
                            $this->card->cvc = $payment->getCcCid();
                        }

                        // TODO: check if expirymonth and year is changed if so add this in the card object
                        $this->card->expiryMonth = $payment->getCcExpMonth();
                        $this->card->expiryYear = $payment->getCcExpYear();

                    } else {
                        $this->card->cvc = $payment->getCcCid();
                        $this->card->expiryMonth = $payment->getCcExpMonth();
                        $this->card->expiryYear = $payment->getCcExpYear();
                        $this->card->holderName = $payment->getCcOwner();
                        $this->card->number = $payment->getCcNumber();
                    }
                }

                // installments
                if (Mage::helper('adyen/installments')->isInstallmentsEnabled() && $payment->getAdditionalInformation('number_of_installments') > 0) {
                    $this->installments = new Adyen_Payment_Model_Adyen_Data_Installments();
                    $this->installments->value = $payment->getAdditionalInformation('number_of_installments');
                }
                break;
            case "boleto":
                $boleto = unserialize($payment->getPoNumber());
                $this->card = null;
                $this->elv = null;
                $this->bankAccount = null;
                $this->socialSecurityNumber = $boleto['social_security_number'];
                $this->selectedBrand = $boleto['selected_brand'];
                $this->shopperName->firstName = $boleto['firstname'];
                $this->shopperName->lastName = $boleto['lastname'];
                $this->deliveryDate = $boleto['delivery_date'];
                break;
            case "sepa":
                $sepa = unserialize($payment->getPoNumber());
                $this->card = null;
                $this->elv = null;
                $this->shopperName = null;
                $this->bankAccount->iban = $sepa['iban'];
                $this->bankAccount->ownerName = $sepa['account_name'];
                $this->bankAccount->countryCode = $sepa['country'];
                $this->selectedBrand = "sepadirectdebit";
                break;
        }
        return $this;
    }

}
