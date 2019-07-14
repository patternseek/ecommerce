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

class ImmediateChargeStrategy extends AbstractChargeStrategy
{

    public function initialPaymentAttempt($paymentMethodId, $amount, $currency, $description, $email,  StripeFacade $stripe)
    {
        # Create the PaymentIntent
        $params = [
            'payment_method' => $paymentMethodId,
            "amount" => $amount, // amount in cents/pence etc, again
            "currency" => $currency,
            "description" => $description,
            'confirmation_method' => 'manual',
            'confirm' => true,
        ];
        if ( $email !== null) {
            $params[ 'receipt_email' ] = $email;
        }
        $intent = $stripe->paymentIntentCreate( $params );
        
        return $this->generatePaymentResponse( $intent );
    }

}