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

use \Exception;
use PatternSeek\StructClass\StructClass;
use Symfony\Component\Validator\Constraints as Assert;

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
     * @Assert\Type(type="string")
     */
    public $description;

    /**
     * @var double
     * @Assert\Type(type="double")
     * @Assert\NotNull()
     */
    public $netPrice;

    /**
     * Amount of VAT for this item
     * @var double
     * @Assert\Type(type="double")
     * @Assert\NotNull()
     */
    public $vatPerItem;

    /**
     * One of vendor, customer, zero
     * @var string
     * @Assert\Choice(choices={"vendor","customer","zero"})
     * @Assert\NotBlank()
     */
    public $vatTypeCharged;

    /**
     * @var bool
     * @Assert\Type(type="boolean")
     * @Assert\NotNull()
     */
    public $isB2b;

    /**
     * @var int
     * @Assert\Type(type="integer")
     */
    public $quantity;

    /**
     * @var string
     * @Assert\Choice(choices = {"deliveredgoods", "normalservices", "electronicservices"})
     * @Assert\NotBlank()
     */
    public $productType;

    /**
     * @var string
     * @Assert\Choice(choices = {"local", "eu", "row"})
     * @Assert\NotBlank()
     */
    public $enjoyedInLocationType;

    /**
     * In the case of subscription products, an ID is required to subscribe the user to.
     * 
     * @Assert\Type(type="string")
     * @var string
     */
    public $subscriptionTypeId;

    /**
     * @Assert\Type(type="string")
     * @var string
     */
    public $couponCode;

    /**
     * The VAT rate for this item for the current customer
     * @var double
     */
    public $vatRate;

    /**
     * Array of arbitrary string key/value pairs associated with the line item
     * IMPORTANT. When passing multiple line items with metadata, no two line items may have metadata with the same key. Unless one of them is a subscription
     * 
     * @var array
     */
    public $metadata = [];

    /**
     * Work out which VAT type and amount per item are applicable to this line item.
     * @param $vendorVatRate
     * @param $customerVatRate
     * @throws \Exception
     */
    public function calculateVat( $vendorVatRate, $customerVatRate )
    {
        $this->vatTypeCharged =
            $this->getVatType(
                $this->productType,
                $this->isB2b,
                $this->enjoyedInLocationType
            );
        $this->vatPerItem =
            $this->getVat(
                $this->vatTypeCharged,
                $this->netPrice,
                $vendorVatRate,
                $customerVatRate );
    }

    /**
     * Get the total cost for the line item taking into account the number of items and any chargeable VAT
     * @return float
     */
    public function getTotal()
    {
        return
            ( $this->netPrice * $this->quantity )
                + $this->getTotalVAT() 
            ;
        
    }

    /**
     * @return float|int
     */
    public function getTotalVAT()
    {
        return ( $this->vatPerItem?$this->vatPerItem:0 ) * $this->quantity;
    }

    /**
     * Determine what type of VAT to charge. The rate in the vendor country, the customer country or zero.
     *
     * @param string $productType One of 'deliveredgoods','normalservices','electronicservices'
     * @param bool $isB2b B2B transaction if true, B2C if not.
     * @param string $enjoyedInLocationType Where the goods are sent or the service is consumed, one of 'local','eu','row'. 'row' being Rest Of World
     * @return string
     * @throws \Exception On invalid input
     */
    private function getVatType( $productType, $isB2b, $enjoyedInLocationType )
    {
        if (!in_array( $productType, [ 'deliveredgoods', 'normalservices', 'electronicservices' ] )) {
            throw new \Exception( "Invalid product type" );
        }
        if (!in_array( $enjoyedInLocationType, [ 'local', 'eu', 'row' ] )) {
            throw new \Exception( "Invalid location type '{$enjoyedInLocationType}'" );
        }
        $vatRules = [
            'deliveredgoods' => [ // Diff order to PDF version, b2b first for consistency
                'b2b' => [
                    'local' => 'vendor',
                    'eu' => 'zero',
                    'row' => 'zero'
                ],
                'b2c' => [
                    'local' => 'customer', // Same as vendor in this case
                    'eu' => 'customer',
                    'row' => 'zero'
                ]
            ],
            'normalservices' => [
                'b2b' => [
                    'local' => 'vendor',
                    'eu' => 'zero',
                    'row' => 'zero'
                ],
                'b2c' => [
                    'local' => 'vendor',
                    'eu' => 'vendor',
                    'row' => 'zero'

                ]
            ],
            'electronicservices' => [
                // same as normalservices['b2b']
                'b2b' => [
                    'local' => 'vendor',
                    'eu' => 'zero',
                    'row' => 'zero'
                ],
                // Services covered by VATMOSS
                'b2c' => [
                    'local' => 'customer',
                    'eu' => 'customer',
                    'row' => 'zero'
                ]
            ]
        ];
        $vatType = $vatRules[ $productType ][ ( $isB2b?'b2b':'b2c' ) ][ $enjoyedInLocationType ];
        return $vatType;
    }

    /**
     * Determine what how much VAT to charge.
     *
     * @param string $vatType One of "customer", "vendor", or "zero"
     * @param $itemPrice
     * @param double $vendorRate The vendor's VAT rate.
     * @param double $customerRate The customer's VAT rate
     * @return float The VAT rate to use
     */
    private function getVat( $vatType, $itemPrice, $vendorRate, $customerRate )
    {
        $rate = $this->getVatRate( $vatType, $vendorRate, $customerRate );
        $vat = round( $itemPrice * $rate, 2 );
        return $vat;
    }

    /**
     * Determine what rate of VAT to charge. The rate in the vendor country, the customer country or zero.
     *
     * @param string $vatType One of "customer", "vendor", or "zero"
     * @param double $vendorRate The vendor's VAT rate.
     * @param double $customerRate The customer's VAT rate
     * @return float The VAT rate to use
     */
    public function getVatRate( $vatType, $vendorRate, $customerRate ){
        $rate = 0.0;
        switch ($vatType) {
            case "customer":
                $rate = $customerRate;
                break;
            case "vendor":
                $rate = $vendorRate;
                break;
            case "zero":
                $rate = 0.0;
                break;
        }
        $this->vatRate = $rate;
        return $this->vatRate;
    }

    /**
     * Check metadata
     * 
     * @param LineItem[] $lineItems
     */
    public function checkForDuplicateMetadataKeys( $lineItems ){
        foreach ( $lineItems as $lineItem ){
            // Subscriptions aren't included, they can have duplicate keys
            if( $lineItem->subscriptionTypeId ){
                continue;
            }
            foreach ($lineItems as $lineItemCompare ){
                // Subscriptions aren't included, they can have duplicate keys
                if( $lineItemCompare->subscriptionTypeId ){
                    continue;
                }
                if( $lineItem === $lineItemCompare ){
                    continue;
                }
                $metaDataDiff = array_intersect_key( $lineItem->metadata, $lineItemCompare->metadata );
                if( count( $metaDataDiff ) > 0 ){
                    $firstKeyFound = array_keys( $metaDataDiff )[0];
                    throw new Exception( "Line items may not contain duplicate metadata keys. Found key '{$firstKeyFound}' in more than one line item" );
                }
            }
        }
    }

}
