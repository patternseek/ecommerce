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

use PatternSeek\StructClass\StructClass;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Defines configuration for the HMRC VAT API
 *
 * @package PatternSeek\ECommerce
 */
class HmrcVatApiConfig extends StructClass
{
  
    /**
     * HMRC VAT API URL
     * @var string
     *
     * @Assert\Type( type="string" )
     * @Assert\NotBlank
     */
    public $vatUrl;

}
