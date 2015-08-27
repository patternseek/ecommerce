<?php
/**
 *
 * © 2015 Tolan Blundell.  All rights reserved.
 * <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 */

namespace PatternSeek\ECommerce\Test;

use PatternSeek\ComponentView\ViewComponentResponse;
use PatternSeek\ECommerce\Basket;
use PatternSeek\ECommerce\Transaction;
use PatternSeek\ECommerce\TransactionSuccessCallback;

class TestSuccess extends TransactionSuccessCallback
{

    function __invoke( Transaction $txnDetails, Basket $basket ){
        $successOutput = (array)$txnDetails;
        unset( $successOutput[ 'validationError' ] );
        $successOutput[ 'time' ] = null;
        ksort( $successOutput );
        return new ViewComponentResponse( "text/plain", var_export( $successOutput, true ) );
    }
}