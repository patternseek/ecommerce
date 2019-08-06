<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2019 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce\Stripe\Facade;

use Stripe\PaymentMethod;

class StripePaymentMethodMock extends PaymentMethod
{

    public static $params;
    
    /**
     * @var
     */
    public static $typeSetting;
    
    /**
     * @var
     */
    public static $cardCountrySetting;
    
    public static function create( $params = null, $options = NULL )
    {
        self::$params = $params;
        $paymentMethod = new StripePaymentMethodMock( "TestStripeID" );
        
        $paymentMethod->type = self::$typeSetting;
        $paymentMethod->card = self::$cardCountrySetting;
        return $paymentMethod;
        
        return $paymentMethod;
    }
    
    /**
     * @param string $stripeToken
     * @param array|null|string $opts
     * @return StripePaymentMethodMock
     */
    public static function retrieve( $stripeToken, $opts = null )
    {
        $pi = new StripePaymentMethodMock( "TestStripeID" );
        $pi->type = self::$typeSetting;
        $pi->card = self::$cardCountrySetting;
        return $pi;
    }
    
}
