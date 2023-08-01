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

use PatternSeek\ECommerce\ViewState\AddressState;
use PatternSeek\StructClass\StructClass;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Defines configuration for a Basket
 *
 * @package PatternSeek\ECommerce
 */
class BasketConfig extends StructClass
{

    /**
     * The user's IP
     *
     * @var string
     *
     * @Assert\Type( type="string" )
     * @Assert\NotBlank
     */
    public $remoteIp;

    /**
     * The VAT rate for the country the business is based in. Used for products not affected by EU 2015 VAT changes.
     *
     * @var double
     *
     * @Assert\Type(type="double")
     * @Assert\NotBlank
     * @Assert\Range( min="0", max="1" )
     */
    public $localVatRate;

    /**
     * 2 letter country code for the vendor country
     * @var string
     *
     * @Assert\Type( type="string" )
     * @Assert\NotBlank
     */
    public $countryCode;

    /**
     * 3 letter currency code for the currency the basket should work in.
     * @var string
     *
     * @Assert\Type( type="string" )
     * @Assert\NotBlank
     */
    public $currencyCode;

    /**
     * Currency symbol for the currency the basket should work in.
     * @var string
     *
     * @Assert\Type( type="string" )
     * @Assert\NotBlank
     */
    public $currencySymbol;

    /**
     * Configuration for HMRC VAT API
     * @var HmrcVatApiConfig
     *
     * @Assert\Type( type="PatternSeek\ECommerce\Config\HmrcVatApiConfig" )
     */
    public $hmrcVatApiConfig;

    /**
     * Path to GeoLite 2 Countries DB
     * 
     * @var string
     *
     * @Assert\Type( type="string" )
     * @Assert\NotBlank
     */
    public $geoIpDbPath;
    
    /**
     * Configuration for payment system providers
     * @var PaymentProviderConfig[]
     *
     * @Assert\Type(type="array")
     * @Assert\All(
     *      @Assert\NotBlank(),
     *      @Assert\Type( type="PatternSeek\ECommerce\Config\PaymentProviderConfig" )
     * )
     */
    public $paymentProviders;

    /**
     * A brief description covering all the items in the basket
     *
     * @var string
     *
     * @Assert\Type(type="string")
     * @Assert\NotBlank
     */
    public $briefDescription;

    /**
     * Optional header
     *
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $intro = '';

    /**
     * Optional footer
     *
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $outro = '';

    /**
     * @var AddressState
     *
     * @Assert\Type(type="PatternSeek\ECommerce\ViewState\AddressState")
     */
    public $billingAddress;

    /**
     * @var string User's email address, optional
     * @Assert\Type(type="string")
     */
    public $email;
    
    /**
     * Optional Twig template to allow the caller to pass in a template for the Basket component
     * 
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $basketTemplate;
    
    /**
     * Optional Twig template as a string to override the default
     * 
     * @var string
     *
     * @Assert\Type(type="string")
     */
    public $addressTemplate;
    
    

    /**
     * Populate the StructClass's properties from an array
     * @param array $properties
     * @param bool $discardInvalidEntries If set to true, entries in $properties for which there is no corresponding class member will be discarded instead of generating an error
     * @return BasketConfig
     */
    public static function fromArray( array $properties, $discardInvalidEntries = false )
    {
        $paymentProviders = $properties[ 'paymentProviders' ];
        unset( $properties[ 'paymentProviders' ] );

        /** @var BasketConfig $base */
        $base = parent::fromArray( $properties );

        $base->paymentProviders = [ ];
        /** @var PaymentProviderConfig $provider */
        foreach ($paymentProviders as $provider) {
            $provConf = PaymentProviderConfig::fromArray( $provider );
            $base->paymentProviders[$provider['componentClass']] = $provConf;
        }
        
        $base->hmrcVatApiConfig = HmrcVatApiConfig::fromArray( $properties['hmrcVatApiConfig'] );

        if (!empty($properties[ 'billingAddress' ])) {
            $base->billingAddress = AddressState::fromArray( $properties[ 'billingAddress' ] );
        }

        return $base;
    }
}
