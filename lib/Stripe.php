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

use Exception;
use PatternSeek\ComponentView\AbstractViewComponent;
use PatternSeek\ComponentView\Response;
use PatternSeek\ComponentView\Template\TwigTemplate;
use PatternSeek\ECommerce\StripeFacade\StripeFacade;
use PatternSeek\ECommerce\ViewState\AddressState;
use PatternSeek\ECommerce\ViewState\StripeState;

/**
 * A ViewComponent for rendering Stripe checkout within a ViewComponents
 *
 */
class Stripe extends AbstractViewComponent
{

    /**
     * @var \PatternSeek\ECommerce\Basket
     */
    protected $parent;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var StripeState
     */
    protected $state;

    /**
     * HTTP accessible method
     * @param $args
     * @return array
     * @throws \Exception
     * @throws \Stripe\Error\Card
     */
    public function submitFormHandler( $args )
    {

        $stripe = new StripeFacade();

        // Is the basket ready for a transaction? Or has the transaction
        // already occurred? If not then refuse to process
        // This is just a backup as the basket won't show the stripe button
        // if it's not ready or the transaction is complete.
        if (( !$this->state->ready ) || ( $this->state->complete )) {
            $this->parent->setFlashError( "The basket is not ready yet. Please ensure you've filled in all required fields." );

            $root = $this->getRootComponent();
            $root->updateState();
            return $root->render();
        }

        $this->testInputs(
            [
                'stripeToken' => [ "string" ] // Required
            ],
            $args
        );

        $c = (object)$this->state->config;
        $stripeToken = $args[ 'stripeToken' ];
        $apiPrivKey = $this->state->testMode?$c->testApiPrivKey:$c->liveApiPrivKey;
        $stripe->setApiKey( $apiPrivKey );
        $tok = $stripe->tokenRetrieve( $stripeToken, $apiPrivKey );
        $paymentCountryCode = '';
        $paymentType = "";
        if ($tok->type == 'card') {
            $paymentCountryCode = $tok->card->country;
            $paymentType = "card";
        }
        if ($tok->type == 'bank_account') {
            $paymentCountryCode = $tok->bank_account->country;
            $paymentType = "bank_account";
        }

        // Do we require VAT location proof, and if so do we have
        // enough and does it match the information used
        // to calculate the original VAT?
        if (!$this->parent->confirmValidTxnFunc( $paymentCountryCode )) {
            $this->parent->setFlashError( "Sorry but we can't collect enough information about your location to comply with EU VAT legislation with the information we have available. You have not been charged. Please contact us to arrange a manual payment." );

            $root = $this->getRootComponent();
            $root->updateState();
            return $root->render();
        }

        /*
         Stripe_Token Object
        (
            [_apiKey:protected] => sk_test_xxx
            [_values:protected] => Array
            (
                [id] => tok_xxx
                [livemode] =>
                [created] => 141xxx
                [used] =>
                [object] => token
                [type] => card
                [card] => Stripe_Card Object
                    (
                    [_apiKey:protected] => sk_test_xxx
                    [_values:protected] => Array
                        (
                        [id] => card_xxx
                        [object] => card
                        [last4] => xxx
                        [brand] => Visa
                        [funding] => credit
                        [exp_month] => 1
                        [exp_year] => 2016
                        [fingerprint] => xxx
                        [country] => US
                        [name] => xxx@xxx.org
                        [address_line1] =>
                        [address_line2] =>
                        [address_city] =>
                        [address_state] =>
                        [address_zip] =>
                        [address_country] =>
                        [cvc_check] =>
                        [address_line1_check] =>
                        [address_zip_check] =>
                        [dynamic_last4] =>
                        [customer] =>

         */

        $amount = $this->state->amount;
        $currency = $c->currency;
        $description = $this->state->description;
        try{
            switch ( $this->state->chargeMode ){
                case "immediate":
                    // Create the charge on Stripe's servers - this will charge the user's card
                    $ret =
                        $this->chargeCard(
                            $stripe,
                            $stripeToken,
                            $amount,
                            $currency,
                            $description,
                            $paymentCountryCode,
                            $paymentType );
                    break;
                case "delayed":
                    // Generate token for later/repeat charge
                    $ret =
                        $this->getDelayedOrRepeatPaymentTransaction( $stripe, $stripeToken, $paymentCountryCode, $paymentType );
                    break;
                case "subscription":
                    // Subscribe user
                    $ret =
                        $this->createUserAndSubscribe( $stripe, $stripeToken, $this->state->lineItems, $paymentCountryCode, $paymentType  );
                    break;
                default:
                    throw new Exception("Sorry there was an internal error: 'Unknown chargeMode {$this->state->chargeMode}'");
            }

        }catch( Exception $e ){
            $this->parent->setFlashError( "Sorry but there was a problem authorising your transaction. The payment provider said: '{$e->getMessage()}'" );

            $root = $this->getRootComponent();
            $root->updateState();
            $ret = $root->render();
        }

        
        return $ret;
    }

    /**
     * @param StripeFacade $stripe
     * @param $card
     * @param $amount
     * @param $currency
     * @param $description
     * @param $paymentCountryCode
     * @param string $type
     * @return Response
     * @throws Exception
     */
    private function chargeCard( StripeFacade $stripe, $card, $amount, $currency, $description, $paymentCountryCode, $type = "card" )
    {

        $params = [
            "amount" => $amount, // amount in cents/pence etc, again
            "currency" => $currency,
            "card" => $card,
            "description" => $description
        ];
        if ($this->state->email) {
            $params[ 'receipt_email' ] = $this->state->email;
        }
        $charge = $stripe->chargeCreate( $params );

        $txn = new Transaction();
        $txn->chargeID = $charge->id;
        $txn->paymentCountryCode = $paymentCountryCode;
        $txn->paymentType = $type;
        $txn->transactionCurrency = $currency;

        $this->state->complete = true;
        $ret = $this->parent->transactionSuccess( $txn );
        return $ret;
    }

    /**
     * @param StripeFacade $stripe
     * @param $stripeToken
     * @param $paymentCountryCode
     * @param string $paymentType
     * @return Response
     * @throws Exception
     */
    private function getDelayedOrRepeatPaymentTransaction( StripeFacade $stripe, $stripeToken, $paymentCountryCode, $paymentType )
    {

        $params = [
            "source" => $stripeToken,
            "description" => $this->state->email
        ];
        $customer = $stripe->customerCreate( $params );
        
        if ($this->state->email) {
            $params[ 'receipt_email' ] = $this->state->email;
        }
        
        $futureTxn = new DelayedOrRepeatTransaction();
        $futureTxn->paymentCountryCode = $paymentCountryCode;
        $futureTxn->paymentType = $paymentType;
        $this->parent->populateTransactionDetails( $futureTxn );        
        $futureTxn->storedToken = $customer->id;
        $futureTxn->providerClass = Stripe::class;
        
        $this->state->complete = true;
        $ret = $this->parent->delayedTransactionSuccess( $futureTxn );
        return $ret;
    }

    /**
     * @param StripeFacade $stripe
     * @param string $stripeToken
     * @param LineItem[] $lineItems
     * @return Response
     * @throws Exception
     */
    private function createUserAndSubscribe( StripeFacade $stripe, $stripeToken, $lineItems, $paymentCountryCode, $paymentType )
    {
        $params = [
            "source" => $stripeToken,
            "description" => $this->state->email
        ];
        $customer = $stripe->customerCreate( $params );

        $txn = new Transaction();
        $txn->paymentCountryCode = $paymentCountryCode;
        $txn->paymentType = $paymentType;
        
        foreach( $lineItems as $lineItem ){
            $subscriptionRaw = $stripe->subscriptionCreate([
                'customer' => $customer->id,
                'items' => [['plan' => $lineItem->subscriptionTypeId]],
                'tax_percent' => $lineItem->vatRate,
            ]);
            $txn->subscriptions[] = 
                ['providerRawResult'=>
                    [
                        'customer' => (array)$customer->jsonSerialize(),
                        'subscription' => (array)$subscriptionRaw->jsonSerialize()
                    ]
                ];

        }
        
        $ret = $this->parent->subscriptionSuccess( $txn );
        return $ret;
        
    }

    /**
     * @param array $credentials
     * @param DelayedOrRepeatTransaction $delayedTxn
     * @return Transaction
     */
    public static function chargeDelayedOrRepeatPaymentTransaction( $credentials, DelayedOrRepeatTransaction $delayedTxn )
    {
        $stripe = new StripeFacade();
        $stripe->setApiKey( $credentials[ 'apiPrivKey' ] );
        
        $charge = $stripe->chargeCreate( [ 
            "amount"   => $delayedTxn->transactionAmount * 100, // Stripe wants amount in pence/cents etc. In an instance, $this->state->amount has already been multiplied.
            "currency" => $delayedTxn->transactionCurrency,
            "description" => $delayedTxn->transactionDescription,
            "customer" => $delayedTxn->storedToken
        ]);

        /** @var Transaction $finalTxn */
        $finalTxn = Transaction::fromArray( $delayedTxn->toArray(), true );
        $finalTxn->chargeID = $charge->id;
        $finalTxn->time = time();
        try{
            $finalTxn->validate();
        }catch( \Exception $e ){
            $finalTxn->validationError = $e->getMessage();
        }
        
        return $finalTxn;
    }

    /**
     * Initialise $this->state with either a new ViewState or an appropriate subclass
     * @return void
     */
    protected function initState()
    {
        // initialised in update
    }

    /**
     * Load or configure the component's template as necessary
     *
     * @return void
     */
    protected function initTemplate()
    {
        $tplTwig = file_get_contents( __DIR__ . "/../twigTemplates/Stripe.twig" );
        $this->template = new TwigTemplate( $this, null, $tplTwig );
    }

    /**
     * @return void
     * @throws \Exception
     */
    protected function updateState()
    {
        $props = $this->props;

        if (null === $this->state) {
            $this->init( $props );
        }
        
        $this->testInputs(
            [
                'amount' => [ 'double' ],
                'description' => [ "string" ],
                'basketReady' => [ 'boolean' ],
                'transactionComplete' => [ 'boolean' ],
                'address' => [ AddressState::class ],
                'lineItems' => [ 'array' ],
            ],
            $props
        );
        $this->state->amount = $props[ 'amount' ] * 100; // Stripe wants amount in cents
        $this->state->description = $props[ 'description' ];
        $this->state->ready = $props[ 'basketReady' ];
        $this->state->complete = $props[ 'transactionComplete' ];
        $this->state->address = $props[ 'address' ];
        $this->state->lineItems = $props['lineItems'];

    }

    /**
     * @param $props
     * @throws \Exception
     */
    private function init( $props )
    {
        $this->testInputs(
            [
                'config' => [ "array" ],  // Required, entries should be PatternSeek\ECommerce\PaymentProviderConfig
                'buttonLabel' => [ 'string', null ],                                 // Optional, default null
                'email' => [ 'string', null ],                                       // Optional, default null
                'testMode' => [ 'boolean' ],                                        // Required
                'chargeMode' => [ 'string' ]                                        // Required
            ],
            $props
        );

        $c = (object)$props[ 'config' ];

        if (null !== $props[ 'buttonLabel' ]) {
            $props[ 'buttonLabelHTML' ] = "data-label=\"{$c->buttonLabel}\"";
        }

        $props[ 'apiPubKey' ] = $props[ 'testMode' ]?$c->testApiPubKey:$c->liveApiPubKey;

        $this->state = StripeState::fromArray( $props, true );
    }
}
