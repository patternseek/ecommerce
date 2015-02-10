<?php
/*
 * This file is part of the Patternseek ECommerce library.
 *
 * (c)2015 Tolan Blundell <tolan@patternseek.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PatternSeek\ECommerce\ViewState;

use PatternSeek\ComponentView\ViewState\ViewState;
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
     * @Assert\Type(type="string")
     */
    public $apiPubKey;

    /**
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\Length(min="2", max="2")
     */
    public $cardMustMatchCountryCode;

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
     * @var double
     *
     * @Assert\Type(type="double")
     */
    public $amount;

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

}