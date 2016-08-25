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

use PatternSeek\ComponentView\Response;
use PatternSeek\ECommerce\Basket;
use PatternSeek\ECommerce\Transaction;
use PatternSeek\ECommerce\TransactionSuccessCallback;

class TestSuccess extends TransactionSuccessCallback
{

    function __invoke( Transaction $txnDetails, Basket $basket ){
        $successOutput = (array)$txnDetails;
        $successOutput[ 'time' ] = null;
        ksort( $successOutput );
        return new Response( "text/plain", var_export( $successOutput, true ) );
    }
}