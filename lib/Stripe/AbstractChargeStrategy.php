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
use PatternSeek\ECommerce\Stripe\Facade\StripeFacade;

abstract class AbstractChargeStrategy
{

    abstract public function initialPaymentAttempt( $paymentMethodId, $amount, $currency, $description, $email,  StripeFacade $stripe );

    protected function generatePaymentResponse( $intent )
    {
        if ($intent->status == 'requires_action' &&
            $intent->next_action->type == 'use_stripe_sdk') {
            # Tell the client to handle the action
            $resJson = json_encode( [
                'requires_action' => true,
                'payment_intent_client_secret' => $intent->client_secret
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
                $resJson = json_encode( [ 'error' => "Invalid PaymentIntent status: {$intent->status}" ] );
                return new Response( "application/json", $resJson, 500 );
            }
        }
    }
}