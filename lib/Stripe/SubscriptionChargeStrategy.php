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

use PatternSeek\ECommerce\LineItem;
use PatternSeek\ECommerce\Stripe\Facade\StripeFacade;
use PatternSeek\ECommerce\Transaction;
use PatternSeek\ECommerce\ViewState\StripeState;

class SubscriptionChargeStrategy extends AbstractChargeStrategy
{

    /**
     * @param $uid
     * @param $paymentMethodId
     * @param $amount
     * @param $currency
     * @param $description
     * @param $email
     * @param StripeFacade $stripe
     * @param LineItem[] $lineItems
     * @param StripeState $state
     * @return \PatternSeek\ComponentView\Response
     * @throws \Exception
     */
    public function initialPaymentAttempt(
        $uid,
        $paymentMethodId,
        $amount,
        $currency,
        $description,
        $email,
        StripeFacade $stripe,
        $lineItems,
        StripeState $state
    ){
        $subscription = null;
        $numSubs = 0;
        /** @var LineItem[] $nonSubscriptions */
        $nonSubscriptions = [];
        $lastVatRate = $lineItems[0]->vatRate;
        foreach( $lineItems as $lineItem ){
            if( $lineItem->vatRate !== $lastVatRate ){
                // Can't set tax_percent on InvoiceItems. Will have to port to TaxRates at some point
                throw new \Exception( "When creating a subscription all items in the basket must be of the same VAT rate." );
            }
            if( $lineItem->subscriptionTypeId !== null ){
                if( $numSubs > 0 ){
                    throw new \Exception( "Only one subscription can be added to the basket at a time" );
                }
                $subscription = $lineItem;
                $numSubs++;
            }else{
                $nonSubscriptions[] = $lineItem;
            }
        }
        if( $numSubs > 1 ){
            throw new \Exception( "Currently only one subscription per basket is supported." );
        }
        if( null === $subscription ){
            throw new \Exception( "Expected subscription but none found in basket." );
        }
        if( ! is_numeric( $subscription->vatRate ) ){
            throw new \Exception("Missing vat rate for subscription");
        } 
        
        // Create customer
        
        $params = [
            "payment_method" => $paymentMethodId,
            "description" => $email
        ];
        $customer = $stripe->customerCreate( $params );

        // We only support one subscription in the basket currently but additional single charge items can be included alongside it.
        // It's possible to have more than one subscription but I'm not implementing it now. See  https://stripe.com/docs/billing/subscriptions/multiplan and https://stripe.com/docs/billing/subscriptions/multiple
        if( count( $nonSubscriptions ) > 0  ){
            foreach ( $nonSubscriptions as $nonSub ){
                // https://stripe.com/docs/billing/invoices/subscription#adding-upcoming-invoice-items
                $invoiceItemPayload = [
                    'amount' => ( $nonSub->netPrice * 100 ),
                    'currency' => $currency,
                    'customer' => $customer->id,
                    'description' => $nonSub->description,
                ];
                // Attach metadata if present
                if( is_array( $nonSub->metadata ) && count( $nonSub->metadata ) > 0 ){
                    $invoiceItemPayload['metadata'] = $nonSub->metadata;
                }
                $stripe->invoiceItemCreate( $invoiceItemPayload );
            }
        }
        // Create subcription
        $payload = [
            'customer' => $customer->id,
            'items' => [ [ 'plan' => $subscription->subscriptionTypeId ] ],
            'tax_percent' => round( $subscription->vatRate * 100, 2 ),
            'default_payment_method' => $paymentMethodId,
            'expand' => [ "latest_invoice.payment_intent" ],
            'metadata' => ['uid'=>$uid]
        ];
        if( $subscription->couponCode ){
            $payload['coupon'] = $subscription->couponCode;
        }
        // Attach metadata if present
        if( is_array( $subscription->metadata ) && count( $subscription->metadata ) > 0  ){
            $payload['metadata'] = array_merge( $payload['metadata'], $subscription->metadata );
        }
        $subscriptionRaw = $stripe->subscriptionCreate( $payload );
        
        if( $subscriptionRaw['error'] ){
            throw new \Exception( "Sorry there was a problem creating your subscription. The payment provider said: {$subscriptionRaw['error']}" );
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