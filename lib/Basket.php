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

use PatternSeek\ComponentView\AbstractViewComponent;
use PatternSeek\ComponentView\TwigTemplate;
use PatternSeek\ComponentView\ViewComponentResponse;

/**
 * Class Basket
 * @package PatternSeek\ECommerce
 */
class Basket extends AbstractViewComponent
{
    /**
     * @var BasketConfig
     */
    protected $config;
    /**
     * @var LineItem[]
     */
    protected $lineItems;

    // Public methods

    /**
     * @param LineItem $item
     * @throws \Exception
     */
    public function addLineItem( LineItem $item )
    {
        // Require some fields
        if( ! ($item->description && $item->netPrice && $item->vatJurisdictionType )){
            throw new \Exception( "LineItem objects added to Basket must include description, net price and vat jurisdiction type." );
        }
        $this->state['lineItems'][] = $item;
    }

    /**
     * @param $id
     * @param LineItem $item
     */
    public function updateLineItem( $id, LineItem $item )
    {
        $this->state['lineItems'][$id] = $item;
    }

    /**
     * @param $id
     */
    public function removeLineItem( $id )
    {
        unset( $this->state['lineItems'][$id] );
    }

    /**
     * Called if a page needs to know whether
     */
    public function setInitialised(){
        $this->state['initialised'] = true;
    }

    public function getInitialised(){
        return ($this->state['initialised']===true);
    }

    public function setIntro( $html ){
        $this->state['intro'] = $html;
    }

    // HTTP accessible methods

    /**
     * Attempt to validate a european VAT
     * ** This method is called by exec() and is designed to be HTTP accessible **
     *
     * @param $args ['vatNumber'=>"...", 'countryCode'=>'...']
     * @return ViewComponentResponse
     * @throws \Exception
     */
    private function validateVatNumberHandler( $args ){
        $this->testInputs(
            [
                'vatNumber'=>['string'],
                'countryCode'=>['string']
            ],
            $args
        );
        $client = new \SoapClient("http://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl");
        $soapResponse = null;
        try{
            $soapResponse = $client->checkVat( array(
                'countryCode' => $args[ 'countryCode' ],
                'vatNumber' => $args[ 'vatNumber' ]
            ) );
            if( $soapResponse->valid ){
                // Valid
                $this->state['vatNumber'] = $args['vatNumber'];
                $this->state['vatNumberStatus'] = 'valid';
                $this->state['vatNumberCountryCode'] = $args['countryCode'];
            }else{
                // Invalid
                unset( $this->state['vatNumber'] );
                unset( $this->state['vatNumberCountryCode'] );
                $this->state['vatNumberStatus'] = 'invalid';
            }

        }catch ( \SoapFault $e ){
            // Unknown due to technical error
            // We allow the vat number but flag it for manual checking
            $this->state['vatNumber'] = $args['vatNumber'];
            $this->state['vatNumberStatus'] = 'unknown';
            $this->state['vatNumberCountryCode'] = $args['countryCode'];
        }
        // Render full component
        return $this->renderRoot();
    }

    // Protected methods

    /**
     * @param string $remoteCountryCode
     */
    protected function updateLineItemsVat( $remoteCountryCode )
    {

        $remoteRate = $this->getVatRate( $remoteCountryCode );
        /** @var LineItem $lineItem */
        foreach ($this->state['lineItems'] as $id => $lineItem) {
            $lineItem->remoteVatJusrisdictionCountryCode = $remoteCountryCode;
            if( $this->state['vatNumber'] ){
                $lineItem->isB2b = true;
                $lineItem->vatTypeCharged = 'b2b';
                $lineItem->vatPerItem = 0.0;
            }elseif( $lineItem->vatJurisdictionType == 'local' ){
                $lineItem->isB2b = false;
                $lineItem->vatPerItem = $lineItem->netPrice * $this->config->localVatRate;
                $lineItem->vatTypeCharged = 'local';
            }else{
                $lineItem->isB2b = false;
                $lineItem->vatPerItem = $lineItem->netPrice * $remoteRate;
                $lineItem->vatTypeCharged = 'remote';
            }

        }
    }

    /**
     * @param
     * @return double
     */
    protected function getVatRate( $countryCode )
    {
        // TODO
    }

    /**
     * @return string
     * @throws \Exception
     */
    protected function geoIPCountryCode()
    {
        if (!extension_loaded( 'geoip' )) {
            throw new \Exception( "There is a configuration error in the application. The GeoIP extension is not loaded." );
        }
        return geoip_country_code_by_name( $_SERVER[ $this->config->remoteIpKey ] );
    }


    /**
     * Using $props and $this->state, optionally update state, optionally create child components via addOrUpdateChild(), return template props
     * @param array $props
     * @return array Template props
     */
    protected function doUpdate( array $props )
    {
        $this->testInputs(
            [
                'config'=>['PatternSeek\ECommerce\BasketConfig'] // Required
            ],
            $props
        );
        $this->config = $props['config'];

        $prelimCountryCode = $this->geoIPCountryCode();
        $prelimRemoteVatRate = $this->getVatRate( $prelimCountryCode );

        $this->updateLineItemsVat( $prelimRemoteVatRate );


//        foreach( $this->state['lineItems'] as $id=>$itemConf ) {
//            $this->addOrUpdateChild(
//                "lineItems-{$id}",
//                "\\PatternSeek\\ECommerce\\LineItem",
//                $itemConf
//            );
//        }

        // Template properties
        // Everything needed is in this->state so easiest to just return a copy of that.
        // Bearing in mind objects in the array will be passed by ref.
        return $this->state;

    }

    /**
     * Load or configure the component's template as necessary
     *
     * @return void
     */
    protected function setupTemplate()
    {
        $tplTwig = <<<EOS
        {}
EOS;

        $this->template = new TwigTemplate( $this, $tplTwig );
    }
}