<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2019 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce\StripeFacade;

use Stripe\PaymentIntent;

class StripePaymentIntentMock extends PaymentIntent
{

    public static $params;
    
    public $status = "succeeded";
    
    // Silences notices in tests
    public $payment_method = null;

    public static function create( $params = null, $options = null )
    {
        self::$params = $params;
        $paymentIntent = new StripePaymentIntentMock( "TestStripeID" );
        return $paymentIntent;
    }

    public function confirm($params = NULL, $options = NULL){
        return true;
    }
    
}
