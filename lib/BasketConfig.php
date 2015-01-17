<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce;

use PatternSeek\StructClass;

/**
 * Defines configuration for a Basket
 *
 * @package PatternSeek\ECommerce
 */
class BasketConfig extends StructClass\StructClass{

    /**
     * Specifies which array key to use to fetch the user's remote IP from the $_SERVER superglobal
     * @var string
     */
    public $remoteIpKey;

    /**
     * The VAT rate for the country the business is based in. Used for products not affected by EU 2015 VAT changes.
     * @var double
     */
    public $localVatRate;

    /**
     * @var array JSON decoded version of https://euvatrates.com/rates.json.
     * This isn't handled by this library as caching behaviour is better
     * left to the host app.
     */
    public $vatRates;

}