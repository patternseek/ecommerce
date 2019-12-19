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
use PatternSeek\ECommerce\ViewState\StripeState;

class ImmediateChargeStrategy extends AbstractChargeStrategy
{

    public function initialPaymentAttempt(
        $paymentMethodId,
        $amount,
        $currency,
        $description,
        $email,
        StripeFacade $stripe,
        $lineItems,
        StripeState $state
    ){
        # Create the PaymentIntent
        $params = [
            'payment_method' => $paymentMethodId,
            "amount" => $amount, // amount in cents/pence etc, again
            "currency" => $currency,
            "description" => $description,
            'confirmation_method' => 'manual',
            'confirm' => true,
            'metadata' => []
        ];
        // Attach metadata if present. The Basket enforces that multiple non-subscription LineItems will not have duplicate metadata keys
        /** @var LineItem $lineItem */
        foreach ($lineItems as $lineItem){
            if( is_array( $lineItem->metadata && count( $lineItem->metadata ) > 0 ) ){
                $params['metadata'] = array_merge( $params['metadata'], $lineItem->metadata ); 
            }
        }
        
        if ($email !== null) {
            $params[ 'receipt_email' ] = $email;
        }
        $intent = $stripe->paymentIntentCreate( $params );

        return $this->generatePaymentResponse( $intent );
    }

}