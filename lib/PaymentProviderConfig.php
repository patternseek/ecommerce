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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Defines configuration for a payment provider
 *
 * @package PatternSeek\ECommerce
 */
class PaymentProviderConfig extends StructClass\StructClass
{

    /**
     * Handle for the provider
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\NotBlank()
     */
    public $name;

    /**
     * ComponentView subclass that renders this payment provider
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\NotBlank()
     */
    public $componentClass;

    /**
     * Provider-type specific config
     * @var array
     *
     * @Assert\Type(type="array")
     * @Assert\NotBlank()
     */
    public $conf;
    
    /**
     * Provider-type specific translated strings. 
     * 
     * @var StructClass\StructClass
     */
    public $translations;

}
