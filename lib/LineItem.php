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

use PatternSeek\StructClass\StructClass;

/**
 * Class LineItem
 * @package PatternSeek\ECommerce
 */
class LineItem extends StructClass
{

    /**
     * A description for the item.
     * This should be HTML and can potentially be reasonably long and complex, even multi-line,
     * allowing calling code a degree of flexibility in the appearance of the form.
     * @var string
     */
    public $description;

    /**
     * @var double
     */
    public $netPrice;

    /**
     * Amount of VAT for this item
     * @var double
     */
    public $vatPerItem;

    /**
     * One of local, remote, b2b
     * @var string
     */
    public $vatTypeCharged;

    /**
     * @var bool
     */
    public $isB2b;

    /**
     * Charge the VAT rate of the local country not remote (i.e EU 2015 VAT law does not apply to this type of product)
     * @var string local or remote
     */
    public $vatJurisdictionType;

    /**
     * @var double
     */
    public $remoteVatJusrisdictionCountryCode;

    /**
     * @var int
     */
    public $quantity;

}