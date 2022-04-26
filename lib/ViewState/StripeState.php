<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 - 2021 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce\ViewState;

use PatternSeek\ComponentView\ViewState\ViewState;
use PatternSeek\ECommerce\Stripe\StripeTranslations;
use PatternSeek\ECommerce\LineItem;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class ViewState
 * @package PatternSeek\ComponentView
 */
class StripeState extends ViewState
{

    /**
     * @var array
     *
     * @Assert\Type(type="array")
     */
    public $config;
    
    /**
     * Translated strings. 
     * 
     * @var \PatternSeek\ECommerce\Stripe\StripeTranslations
     *
     * @Assert\Type(type="PatternSeek\ECommerce\Stripe\StripeTranslations")
     */
    public $trans;

    /**
     * @var string
     *
     * @Assert\Email()
     */
    public $email;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $emailHTML;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $description;

    /**
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $testMode;

    /**
     * @var string
     *
     * @Assert\Choice(choices = {"immediate", "delayed", "subscription"})
     */
    public $chargeMode;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $apiPubKey;


    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $buttonLabel;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $buttonLabelHTML;

    /**
     * @var integer
     *
     * @Assert\Type(type="integer")
     */
    public $amountCents;

    /**
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $ready = false;

    /**
     * @var boolean
     *
     * @Assert\Type(type="boolean")
     */
    public $complete = false;

    /**
     * @var \Patternseek\Ecommerce\ViewState\AddressState
     *
     * @Assert\Type(type="\Patternseek\Ecommerce\ViewState\AddressState")
     */
    public $address;

    /**
     * @var LineItem[]
     * @Assert\Type(type="array")
     */
    public $lineItems;

    /**
     * @var string
     */
    public $createdSubscriptionId;
    
    /**
     * An optional Twig template to override the default
     * 
     * @var string
     */
    public $passedTemplate;

}
