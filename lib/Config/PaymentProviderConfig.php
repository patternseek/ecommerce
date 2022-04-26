<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 - 2021 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce\Config;

use PatternSeek\ECommerce\TemplateConfig;
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
     * Optional Twig template as a string to allow the caller to pass in a template for the payment provider instead of using the default.
     * 
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $template;

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
