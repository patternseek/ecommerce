<?php
/**
 *
 * © 2016 Tolan Blundell.  All rights reserved.
 * <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace PatternSeek\ECommerce;

/**
 * Stored information for an authorised transaction, which can 
 * 
 * @package PatternSeek\ECommerce
 */
/**
 * Class DelayedOrRepeatTransaction
 * @package PatternSeek\ECommerce
 */
class DelayedOrRepeatTransaction extends Transaction
{

    /**
     * @var string
     */
    public $storedToken;
    /**
     * @var string
     */
    public $providerClass;

    /**
     * @param array $credentials
     * @return mixed
     * @throws \Exception If there is a problem with the charge then an Exception will be thrown.
     */
    public function charge( array $credentials ){
        $myCreds = $credentials[$this->providerClass];
        $class = $this->providerClass;
        return call_user_func( $class.'::chargeDelayedOrRepeatPaymentTransaction', $myCreds, $this );
    }



}