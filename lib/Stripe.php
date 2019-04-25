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
use PatternSeek\StructClass\StructClass;

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
    
    protected function getStripeFacade( ){
        $c = (object)$this->state->config;
        $apiPrivKey = $this->state->testMode?$c->testApiPrivKey:$c->liveApiPrivKey;
        $stripe = new StripeFacade( $apiPrivKey );
        return $stripe;
    }

    public function confirmPaymentHandler( $args )
    {

        $stripe = $this->getStripeFacade();
        $c = (object)$this->state->config;

        # retrieve json from POST body
        $json_str = file_get_contents( 'php://input' );
        $json_obj = json_decode( $json_str );

        $amount = $this->state->amount;
        $currency = $c->currency;
        $description = $this->state->description;

        $intent = null;
        try{
            if (isset( $json_obj->payment_method_id )) {

                $method = $stripe->paymentMethodRetrieve( $json_obj->payment_method_id );
                $paymentCountryCode = $method->card->country;

                // Do we require VAT location proof, and if so do we have
                // enough and does it match the information used
                // to calculate the original VAT?
                if (!$this->parent->confirmValidTxnFunc( $paymentCountryCode )) {
                    $resJson = json_encode(
                        [
                            'error' =>
                                "Sorry but we can't collect enough information about your location to comply with EU VAT legislation with the information we have available. You have not been charged. Please contact us to arrange a manual payment."
                        ]
                    );
                    return new Response( "application/json", $resJson );
                }

                # Create the PaymentIntent
                $params = [
                    'payment_method' => $json_obj->payment_method_id,
                    "amount" => $amount, // amount in cents/pence etc, again
                    "currency" => $currency,
                    "description" => $description,
                    'confirmation_method' => 'manual',
                    'confirm' => true,
                ];
                if ($this->state->email !== null) {
                    $params[ 'receipt_email' ] = $this->state->email;
                }
                $intent = $stripe->paymentIntentCreate( $params );
            }

            return $this->generatePaymentResponse( $intent );
        }catch( Exception $e ){
            # Display error on client
            $resJson = json_encode( [
                'error' => $e->getMessage()
            ] );
            return new Response( "application/json", $resJson );
        }
    }

    function generatePaymentResponse( $intent )
    {
        if ($intent->status == 'requires_action' &&
            $intent->next_action->type == 'use_stripe_sdk') {
            # Tell the client to handle the action
            $resJson = json_encode( [
                'requires_action' => true,
                'payment_intent_client_secret' => $intent->client_secret
            ] );
            return new Response( "application/json", $resJson );
        }else {
            if ($intent->status == 'succeeded') {
                # The payment didn’t need any additional actions and completed!
                // Here we reply back to the calling JS to tell it we
                // succeeded and it will then forward the user to the completionHandler
                $resJson = json_encode( [
                    "success" => true,
                    'paymentIntentId' => $intent->id
                ] );
                return new Response( "application/json", $resJson );
            }else {
                # Invalid status
                $resJson = json_encode( [ 'error' => "Invalid PaymentIntent status: {$intent->status}" ] );
                return new Response( "application/json", $resJson, 500 );
            }
        }
    }

    /**
     * HTTP accessible method
     * @param $args
     * @return Response
     * @throws Exception
     */
    public function completionHandler( $args )
    {

        $this->testInputs(
            [
                'paymentIntentId' => [ "string" ] // Required
            ],
            $args
        );
        $paymentIntentId = $args['paymentIntentId'];
        
        // Is the basket ready for a transaction? Or has the transaction
        // already occurred? If not then refuse to process
        // This is just a backup as the basket won't show the stripe form
        // if it's not ready or the transaction is complete.
        if (( !$this->state->ready ) || ( $this->state->complete )) {
            $this->parent->setFlashError( $this->state->trans->fill_all_fields );

            $root = $this->getRootComponent();
            $root->updateState();
            return $root->render();
        }

        $stripe = $this->getStripeFacade();
        $c = (object)$this->state->config;
        $currency = $c->currency;

        $intent = $stripe->paymentIntentRetrieve( $paymentIntentId );
        $method = $stripe->paymentMethodRetrieve( $intent->payment_method );

        // Do we require VAT location proof, and if so do we have
        // enough and does it match the information used
        // to calculate the original VAT?
        $paymentCountryCode = $method->card->country;
        if (!$this->parent->confirmValidTxnFunc( $paymentCountryCode )) {
            $this->parent->setFlashError( $this->state->trans->not_enough_vat_info );

            $root = $this->getRootComponent();
            $root->updateState();
            return $root->render();
        }
        
        // Charge if not done already (because the user bounced to extended authorisation)
        if ($intent->status != 'succeeded') {
            $intent->confirm();
        }
        
        if ($intent->status == 'succeeded') {
            // The payment is complete
            $txn = new Transaction();
            $txn->chargeID = $paymentIntentId;
            $txn->paymentCountryCode = $method->card->country;
            $txn->paymentType = 'card';
            $txn->transactionCurrency = $currency;
    
            $this->state->complete = true;
            $ret = $this->parent->transactionSuccess( $txn );
            return $ret;
        }else {
            # Invalid status
            throw new Exception( "Invalid PaymentIntent status: {$intent->status}" );
        }
        
//
//
//        try{
//            switch ($this->state->chargeMode) {
//                case "immediate":
//                    // Create the charge on Stripe's servers - this will charge the user's card
//                    $ret =
//                        $this->chargeCard(
//                            $stripe,
//                            $stripeToken,
//                            $amount,
//                            $currency,
//                            $description,
//                            $paymentCountryCode,
//                            $paymentType );
//                    break;
//                case "delayed":
//                    // Generate token for later/repeat charge
//                    $ret =
//                        $this->getDelayedOrRepeatPaymentTransaction( $stripe, $stripeToken, $paymentCountryCode,
//                            $paymentType );
//                    break;
//                case "subscription":
//                    // Subscribe user
//                    $ret =
//                        $this->createUserAndSubscribe( $stripe, $stripeToken, $this->state->lineItems,
//                            $paymentCountryCode, $paymentType );
//                    break;
//                default:
//                    throw new Exception( "Sorry there was an internal error: 'Unknown chargeMode {$this->state->chargeMode}'" );
//            }
//        }catch( Exception $e ){
//            $this->parent->setFlashError( "Sorry but there was a problem authorising your transaction. The payment provider said: '{$e->getMessage()}'" );
//
//            $root = $this->getRootComponent();
//            $root->updateState();
//            $ret = $root->render();
//        }
//
//        return $ret;
    }


    /**
     * @param StripeFacade $stripe
     * @param $stripeToken
     * @param $paymentCountryCode
     * @param string $paymentType
     * @return Response
     * @throws Exception
     */
    private function getDelayedOrRepeatPaymentTransaction(
        StripeFacade $stripe,
        $stripeToken,
        $paymentCountryCode,
        $paymentType
    ){

        $params = [
            "source" => $stripeToken
        ];
        if (null !== $this->state->email) {
            $params[ 'description' ] = $this->state->email;
            $params[ 'email' ] = $this->state->email;
        }
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
     * @param $paymentCountryCode
     * @param $paymentType
     * @return Response
     * @throws Exception
     */
    private function createUserAndSubscribe(
        StripeFacade $stripe,
        $stripeToken,
        $lineItems,
        $paymentCountryCode,
        $paymentType
    ){
        $params = [
            "source" => $stripeToken,
            "description" => $this->state->email
        ];
        $customer = $stripe->customerCreate( $params );

        $txn = new Transaction();
        $txn->paymentCountryCode = $paymentCountryCode;
        $txn->paymentType = $paymentType;

        $subs = [];
        foreach ($lineItems as $lineItem) {
            $subscriptionRaw = $stripe->subscriptionCreate( [
                'customer' => $customer->id,
                'items' => [ [ 'plan' => $lineItem->subscriptionTypeId ] ],
                'tax_percent' => round( $lineItem->vatRate * 100, 2 ),
            ] );
            $subs[] =
                [
                    'providerRawResult' =>
                        [
                            'customer' => (array)$customer->jsonSerialize(),
                            'subscription' => (array)$subscriptionRaw->jsonSerialize()
                        ]
                ];
        }
        $txn->setSubscriptions( $subs );
        $ret = $this->parent->subscriptionSuccess( $txn );
        return $ret;
    }

    /**
     * @param array $credentials
     * @param DelayedOrRepeatTransaction $delayedTxn
     * @return Transaction
     */
    public static function chargeDelayedOrRepeatPaymentTransaction(
        $credentials,
        DelayedOrRepeatTransaction $delayedTxn
    ){
        $stripe = new StripeFacade( $credentials[ 'apiPrivKey' ] );

        $charge = $stripe->chargeCreate( [
            "amount" => $delayedTxn->transactionAmount * 100,
            // Stripe wants amount in pence/cents etc. In an instance, $this->state->amount has already been multiplied.
            "currency" => $delayedTxn->transactionCurrency,
            "description" => $delayedTxn->transactionDescription,
            "customer" => $delayedTxn->storedToken
        ] );

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
                'email' => ['string', null ],
                'translations' => [StripeTranslations::class, new StripeTranslations() ]
            ],
            $props
        );
        $this->state->amount = $props[ 'amount' ] * 100; // Stripe wants amount in cents
        $this->state->description = $props[ 'description' ];
        $this->state->ready = $props[ 'basketReady' ];
        $this->state->complete = $props[ 'transactionComplete' ];
        $this->state->address = $props[ 'address' ];
        $this->state->lineItems = $props[ 'lineItems' ];
        $this->state->email = $props[ 'email' ];
        $this->state->trans = $props['translations'];

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
