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

use PatternSeek\ComponentView\Response;
use PatternSeek\ECommerce\LineItem;
use PatternSeek\ECommerce\Stripe\Facade\StripeFacade;
use PatternSeek\ECommerce\Transaction;
use PatternSeek\ECommerce\ViewState\StripeState;
use Stripe\PaymentIntent;

abstract class AbstractChargeStrategy
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
    abstract public function initialPaymentAttempt(
        $uid,
        $paymentMethodId,
        $amount,
        $currency,
        $description,
        $email,
        StripeFacade $stripe,
        $lineItems,
        StripeState $state
    );

    protected function generatePaymentResponse( $intent )
    {
        if ($intent->status == 'requires_action' &&
            $intent->next_action->type == 'use_stripe_sdk') {
            # Tell the client to handle the action
            $resJson = json_encode( [
                'requires_action' => true,
                'payment_intent_client_secret' => $intent->client_secret,
                'confirmation_method' => $intent->confirmation_method
            ] );
            return new Response( "application/json", $resJson );
            
        // It seems it's possible for card validation to happen later in the flow in which case a failure will
        // return the PaymentIntent status to return to "requires_payment_method". This has only been seen
        // with a Mastercard which had insufficient funds in production. Can't reproduce in  testing. However,
        // this https://stripe.com/docs/payments/intents#intent-statuses seems to support that things can fail at this
        // point, so testing for it here.
        }elseif($intent->status == 'requires_payment_method') {
            $resJson = json_encode( [
                'error' => "We're sorry, there was an error processing your payment. Please try again with different payment method."
            ] );
            return new Response( "application/json", $resJson );
        }else {
            if ($intent->status == 'succeeded') {
                # The payment didn’t need any additional actions and completed!
                // Here we reply back to the calling JS to tell it we
                // succeeded and it will then forward the user to the completionHandler
                $resJson = json_encode( [
                    "success" => true,
                    'paymentIntentId' => $intent->id
                ] );
                return new Response( "application/json", $resJson );
            }else {
                # Invalid status
                $resJson = json_encode( [ 'error' => "Invalid PaymentIntent status(1): {$intent->status}" ] );
                return new Response( "application/json", $resJson, 500 );
            }
        }
    }
    
    /**
     * @param $paymentIntentId
     * @param $method
     * @param $currency
     * @param StripeFacade $stripe
     * @param StripeState $state
     * @return Transaction
     */
    public function createTransaction( $paymentIntentId, $method, $currency, $stripe, $state )
    {
        $txn = new Transaction();
        $txn->chargeID = $paymentIntentId;
        $txn->paymentCountryCode = $method->card->country;
        $txn->paymentType = 'card';
        $txn->transactionCurrency = $currency;
        return $txn;
    }
    
}