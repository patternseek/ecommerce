<?php
/**
 *
 * © 2019 Tolan Blundell.  All rights reserved.
 * <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace PatternSeek\ECommerce\Stripe;

use Exception;
use PatternSeek\ECommerce\Stripe\Facade\StripeFacade;
use PatternSeek\ECommerce\Transaction;
use PatternSeek\ECommerce\ViewState\StripeState;

class SubscriptionChargeStrategy extends AbstractChargeStrategy
{

    public function initialPaymentAttempt(
        $paymentMethodId,
        $amount,
        $currency,
        $description,
        $email,
        StripeFacade $stripe,
        $subscriptionTypeId,
        $subscriptionVatRate,
        StripeState $state
    ){
        if( ( ! is_numeric( $subscriptionVatRate ) ) || null == $subscriptionTypeId ){
            throw new \Exception("Missing type id or vat rate for subscription");
        } 
        
        // Create customer
        
        $params = [
            "payment_method" => $paymentMethodId,
            "description" => $email
        ];
        $customer = $stripe->customerCreate( $params );

        // Create subcription
        
        // Only support one subscription in the basket
        $payload = [
            'customer' => $customer->id,
            'items' => [ [ 'plan' => $subscriptionTypeId ] ],
            'tax_percent' => round( $subscriptionVatRate * 100, 2 ),
            'default_payment_method' => $paymentMethodId,
            'expand' => [ "latest_invoice.payment_intent" ]
        ];
        $subscriptionRaw = $stripe->subscriptionCreate( $payload );
        
        if( $subscriptionRaw['error'] ){
            throw new Exception( "Sorry there was a problem creating your subscription. The payment provider said: {$subscriptionRaw['error']}" );
        }
        
        
        // Store subscription info
        $state->createdSubscriptionId = $subscriptionRaw['id'];
        
        
        return $this->generatePaymentResponse( (object)$subscriptionRaw['latest_invoice']['payment_intent'] );
        
      
    }
    
    /**
     * @param $paymentIntentId
     * @param $method
     * @param $currency
     * @param StripeFacade $stripe
     * @param StripeState $state
     * @return Transaction
     */
    public function createTransaction( $paymentIntentId, $method, $currency, $stripe, $state ){
        
        $txn = parent::createTransaction( $paymentIntentId, $method, $currency, $stripe, $state );
        
        $sub = $stripe->subscriptionRetrieve( $state->createdSubscriptionId );
        $customer = $stripe->customerRetrieve( $sub->customer );
        $latestInvoice = $stripe->invoiceRetrieve( $sub->latest_invoice );
        
        $subs = [];
        $subs[] = 
            ['providerRawResult'=>
                [
                    'customer' => (array)$customer->jsonSerialize(),
                    'subscription' => (array)$sub->jsonSerialize(),
                    'first_invoice' => (array)$latestInvoice->jsonSerialize()
                ]
            ];

        $txn->setSubscriptions( $subs );
        
        return $txn;
    }

}