<?php

class PensoPay_Payment_PaymentController extends Mage_Core_Controller_Front_Action
{
    public function emailAction()
    {
        /** @var Mage_Core_Controller_Request_Http $request */
        $request = $this->getRequest();

        $hash = $this->getRequest()->getParam('hash');
        if (!empty($hash)) {
            /** @var PensoPay_Payment_Model_Payment $payment */
            $payment = Mage::getModel('pensopay/payment');
            $payment->load($hash, 'hash');
            if ($payment->getId()) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($payment->getOrderId());
                if ($order->getId()) {
                    /** @var Mage_Checkout_Model_Session $checkoutSession */
                    $checkoutSession = Mage::getSingleton('checkout/session');
                    $checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
                    $checkoutSession->setLastQuoteId($order->getQuoteId());
                    $checkoutSession->setLastOrderId($order->getId());
                    return $this->getResponse()->setRedirect($payment->getLink());
                }
            }
        }

        //Non descriptive messages for these things are best, I believe.
        Mage::getSingleton('core/session')->addError($this->__('Error with the link.'));
        return $this->_redirect('/');
    }



    /**
     * Redirect to gateway
     */
    public function redirectAction()
    {
        /** @var PensoPay_Payment_Helper_Checkout $pensopayCheckoutHelper */
        $pensopayCheckoutHelper = Mage::helper('pensopay/checkout');

        /** @var PensoPay_Payment_Model_Api $api */
        $api = Mage::getModel('pensopay/api');

        $order = $pensopayCheckoutHelper->getCheckoutSession()->getLastRealOrder();
        $isCheckoutIframe = $pensopayCheckoutHelper->isCheckoutIframe();
        try {

            $payment = $api->createPayment($order);

            /**
             * Because this is an iframe, we need tell the api to generate a link that after payment is complete
             * it won't redirect the user (within the iframe) anywhere. our code will handle that.
             */
            if ($isCheckoutIframe) {
                $order->setNoRedirects(true);
            }

            $paymentLink = $api->createPaymentLink($order, $payment->id);

            /** @var PensoPay_Payment_Model_Payment $newPayment */
            $newPayment = Mage::getModel('pensopay/payment');

            $newPayment->importFromRemotePayment($payment);
            $newPayment->setLink($paymentLink);
            $newPayment->setIsVirtualterminal(false);
            $newPayment->save();
        } catch (Exception $e) {
            //Restore quote and redirect to cart
            $pensopayCheckoutHelper->restoreQuote();

            $pensopayCheckoutHelper->getCoreSession()->addError($e->getMessage());
            $this->_redirect('checkout/cart');
        }

        if ($isCheckoutIframe) {
            $pensopayCheckoutHelper->getCoreSession()->setPaymentWindowUrl($paymentLink);
            $this->_redirect('*/*/iframe');
        } else {
            $this->_redirectUrl($paymentLink);
        }
    }

    /**
     *
     */
    public function successAction()
    {
        /** @var PensoPay_Payment_Helper_Checkout $pensopayCheckoutHelper */
        $pensopayCheckoutHelper = Mage::helper('pensopay/checkout');

        $order = $pensopayCheckoutHelper->getCheckoutSession()->getLastRealOrder();

        /** @var PensoPay_Payment_Model_Method $paymentMethod */
        $paymentMethod = Mage::getModel('pensopay/method');

        $quoteID = Mage::getSingleton('checkout/cart')->getQuote()->getId();

        if ($quoteID) {
            $quote = Mage::getModel('sales/quote')->load($quoteID);
            $quote->setIsActive(false)->save();
        }

        if ((int)$paymentMethod->getConfigData('auto_capture') == 1) {
            if ($order->canInvoice()) {
                $invoice = $order->prepareInvoice();
                $invoice->register();
                $invoice->setEmailSent(true);
                $invoice->getOrder()->setCustomerNoteNotify(true);
                $invoice->sendEmail(true, '');
                Mage::getModel('core/resource_transaction')->addObject($invoice)->addObject($invoice->getOrder())->save();

                $order->addStatusToHistory(Mage_Sales_Model_Order::STATE_COMPLETE);
                $order->save();
            }
        }

        $this->_redirect('checkout/onepage/success');
    }

    /**
     * Handle callback
     *
     * @return $this
     */
    public function callbackAction()
    {
        $requestBody = $this->getRequest()->getRawBody();
        $request = json_decode($requestBody);

        $checksum = hash_hmac("sha256", $requestBody, $this->getPrivateKey());

        if ($checksum === $this->getRequest()->getServer('HTTP_QUICKPAY_CHECKSUM_SHA256')) {
            $operation = end($request->operations);
            /** @var Mage_Sales_Model_Order $order */
            $order = Mage::getModel('sales/order')->loadByIncrementId($request->order_id);

            if (! Mage::getStoreConfigFlag(PensoPay_Payment_Model_Config::XML_PATH_TESTMODE_ENABLED) && $request->test_mode) {
                //Cancel order
                if ($order->canCancel()) {
                    try {
                        $order->cancel();
                        $order->addStatusToHistory($order->getStatus(), "Order placed with test card.");
                        $order->save();
                    } catch (Exception $e) {
                        Mage::log('Failed to cancel testmode order #' . $order->getIncrementId(), null, 'qp_debug.log');
                    }
                }

                $this->getResponse()->setBody(json_encode([
                    'error' => 'Attempted to pay with test card but testmode is disabled',
                ]));

                return $this;
            }

            if ($request->accepted && $operation->type == 'authorize') {
                $metadata = $request->metadata;
                $fraudSuspected = $metadata->fraud_suspected;

                $fraudProbability = PensoPay_Payment_Model_Payment::FRAUD_PROBABILITY_HIGH;

                if ($fraudSuspected) {
                    $fraudProbability = PensoPay_Payment_Model_Payment::FRAUD_PROBABILITY_HIGH;
                } else {
                    $fraudProbability = PensoPay_Payment_Model_Payment::FRAUD_PROBABILITY_NONE;
                }
            }
        }
//        $payment = $order->getPayment();
//        $txnId = $transactionResponse->transaction->id;
//        $payment->setTransactionId($txnId);
//        $payment->setIsTransactionClosed(false);
//        $payment->setAdditionalInformation(Bambora_Online_Model_Checkout_Payment::PSP_REFERENCE, $txnId);
//        $payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
//        $payment->setCcType($transactionResponse->transaction->information->paymentTypes[0]->displayName);
    }

    /**
     * Show payment iframe
     */
    public function iframeAction()
    {
        $this->loadLayout();
        $this->renderLayout();
    }

    public function pollPaymentAction()
    {
        /** @var Mage_Checkout_Model_Session $checkoutSession */
        $checkoutSession = Mage::getSingleton('checkout/session');

        /** @var Mage_sales_Model_Order $order */
        $order = $checkoutSession->getLastRealOrder();

        /** @var PensoPay_Payment_Model_Payment $payment */
        $payment = Mage::getModel('pensopay/payment');

        $payment->load($order->getIncrementId(), 'order_id');
        if ($payment->getId()) {
            try {
                $payment->updatePaymentRemote();

                if (in_array($payment->getLastType(), [
                        PensoPay_Payment_Model_Payment::OPERATION_AUTHORIZE,
                        PensoPay_Payment_Model_Payment::OPERATION_CAPTURE])
                ) {
                    if ($payment->getLastCode() == PensoPay_Payment_Model_Payment::STATUS_APPROVED) {
                        $this->getResponse()->setBody(json_encode(
                            [
                                'repeat' => 0,
                                'error' => 0,
                                'success' => 1,
                                'redirect' => Mage::app()->getStore()->getUrl('pensopay/payment/success')
                            ]
                        ));
                    } else {
                        Mage::getSingleton('checkout/session')->addError($this->__('There was a problem with the payment. Please try again.'));
                        $this->getResponse()->setBody(json_encode(
                            [
                                'repeat' => 0,
                                'error' => 1,
                                'success' => 0,
                                'redirect' => Mage::app()->getStore()->getUrl('pensopay/payment/cancel')
                            ]
                        ));
                    }
                    return;
                }

                $this->getResponse()->setBody(json_encode(
                    [
                        'repeat' => 1,
                        'error' => 0,
                        'success' => 0,
                        'redirect' => ''
                    ]
                ));
            } catch (Exception $e) {}
            return;
        }
        $this->getResponse()->setBody(json_encode(
            [
                'repeat' => 0,
                'error' => 1,
                'success' => 0,
                'redirect' => Mage::app()->getStore()->getUrl('/')
            ]
        ));
    }

    /**
     * Handle customer cancelling payment
     */
    public function cancelAction()
    {
        /** @var PensoPay_Payment_Helper_Checkout $pensopayCheckoutHelper */
        $pensopayCheckoutHelper = Mage::helper('pensopay/checkout');

        //Read quote id from session and attempt to restore

        /** @var Mage_Checkout_Model_Session $session */
        $session = $pensopayCheckoutHelper->getCheckoutSession();

        if ($session->getLastRealOrderId()) {
            $order = Mage::getModel('sales/order')->loadByIncrementId($session->getLastRealOrderId());
            if ($order->getId()) {
                $order->cancel()->save();
            }

            Mage::helper('pensopay/checkout')->restoreQuote();
        }

        $this->_redirect('checkout/cart');
    }

    /**
     * Get private key
     *
     * @return mixed
     */
    private function getPrivateKey()
    {
        return Mage::getStoreConfig(PensoPay_Payment_Model_Config::XML_PATH_PRIVATE_KEY);
    }
}