<?php
/**
 *
 */
defined( 'IN' ) || exit( 'Denied' );

/**
 * Use try {} catch (\Stripe\Exception\ApiErrorException $e) { b($e->getError()->message); } to handle errors
 */
class pay_stripe extends instance {
	private $stripe;

	/**
	 * Init
	 */
	protected function __construct() {
		require_once '/var/www/vendor/autoload.php';

		// \Stripe\Stripe::setApiKey( _cfg( 'stripe', 'key' ) );
		$this->stripe = new \Stripe\StripeClient( _cfg( 'stripe', 'key' ) );
	}

	/**
	 * Create a new product
	 */
	public function product_create( $title, $product_id ) {
		$resp = $this->stripe->products->create( [ 'name' => $title, 'metadata' => [ 'pid' => $product_id ] ] );
		$resp = $this->obj2arr( $resp );
		return $resp[ 'id' ];
	}

	/**
	 * Update a product
	 */
	public function product_update( $stripe_product_id, $title, $product_id ) {
		$this->stripe->products->update( $stripe_product_id, [ 'name' => $title, 'metadata' => [ 'pid' => $product_id ] ] );
	}

	/**
	 * Create a price for later subscription
	 */
	public function price_create( $title, $product_id, $price, $interval = 'month' ) {
		$resp = $this->stripe->prices->create( [
			'nickname' => $title,
			'product' => $product_id,
			'unit_amount' => $price * 100,
			'currency' => 'usd',
			'recurring' => [
				'interval' => $interval,
			],
		] );
		$resp = $this->obj2arr( $resp );
		return $resp[ 'id' ];
	}

	/**
	 * Find an existing price for one product, if can't find, add it
	 * Note: this is used only for recurring prices
	 */
	public function price_maybe_create( $title, $product_id, $price, $interval = 'month' ) {
		// Try to find exising matching price
		$prices = $this->stripe->prices->all( [ 'limit' => 100, 'type' => 'recurring', 'product' => $product_id ] );
		foreach ( $prices[ 'data' ] as $v ) {
			if ( $v[ 'unit_amount' ] == $price * 100 ) {
				return $v[ 'id' ];
			}
		}

		return $this->price_create( $title, $product_id, $price, $interval );
	}

	/**
	 * Delete a price
	 */
	public function price_del( $stripe_price_id ) {
		$this->stripe->prices->update( $stripe_price_id, [
			'active' => false,
		] );
	}

	/**
	 * Setup a payment intent
	 */
	public function setup_payment( $customer_id ) {
		return $this->stripe->setupIntents->create( [ 'customer' => $customer_id, 'payment_method_types' => [ 'card' ] ] );
	}

	/**
	 * Retrieve payment method ID by setup intent
	 */
	public function retrieve_pm_id_by_setupintent( $setup_intent ) {
		$si = $this->stripe->setupIntents->retrieve( $setup_intent );
		if ( $si->status !== 'succeeded' ) {
			return false;
		}
		return $si->payment_method;
	}

	/**
	 * Retrieve a payment info
	 */
	public function payment_retrieve( $payment_id, $field = false ) {
		$resp = $this->stripe->paymentMethods->retrieve( $payment_id );
		$resp = $this->obj2arr( $resp );
		if ( $field ) {
			return $resp[ $field ];
		}
		return $resp;
	}

	/**
	 * Delete a payment
	 */
	public function payment_del( $payment_id ) {
		$customer = $this->payment_retrieve( $payment_id, 'customer' );
		if ( ! $customer ) {
			return;
		}
		$this->stripe->paymentMethods->detach( $payment_id );
	}

	/**
	 * Check if a customer has valid payment or not
	 */
	public function customer_valid_payment( $customer_id ) {
		$resp = $this->stripe->paymentMethods->all( [
			'customer' => $customer_id,
			'type' => 'card',
		] );
		$resp = $this->obj2arr( $resp );
		return ! empty( $resp[ 'data' ][ 0 ] ) ? $resp[ 'data' ][ 0 ] : false;
	}

	/**
	 * Create a customer
	 */
	public function customer_create( $name ) {
		$resp = $this->stripe->customers->create( [
			'name' => $name,
		] );
		$resp = $this->obj2arr( $resp );
		return $resp[ 'id' ];
	}

	/**
	 * Update a customer payment way
	 */
	public function customer_update_payment( $customer_id, $payment_id ) {
		// $this->stripe->paymentMethods->attach( $payment_id, [
		// 	'customer' => $customer_id,
		// ] );
		$this->stripe->customers->update( $customer_id, [
			'invoice_settings' => [ 'default_payment_method' => $payment_id ],
		] );
	}

	/**
	 * Create a subscription
	 */
	public function subscription_create( $customer_id, $price_id ) {
		$resp = $this->stripe->subscriptions->create( [
			'customer' => $customer_id,
			'items' => [
				[ 'price' => $price_id ],
			],
		] );
		$resp = $this->obj2arr( $resp );
		return $resp[ 'id' ];
	}

	/**
	 * Cancel a subscription
	 */
	public function subscription_del( $sub_id ) {
		$this->stripe->subscriptions->cancel( $sub_id );
	}

	/**
	 * Sub details
	 */
	public function customer_valid_sub( $sub_id ) {
		$sub = $this->stripe->subscriptions->retrieve( $sub_id );
		return $sub[ 'status' ] == 'active';
	}

	/**
	 * Charge a source
	 */
	public function charge( $price, $customer_id, $desc ) {
		$customer = $this->stripe->customers->retrieve( $customer_id );

		return $this->stripe->paymentIntents->create( [
			'amount' => $price * 100,
			'currency' => 'usd',
			'confirm' => true,
			'customer' => $customer_id,
			'payment_method' => $customer->invoice_settings->default_payment_method,
			'description' => $desc,
		] );
	}


	/**
	 * Create an alipay source
	 * @deprecated old func
	 */
	public function alipay_source( $price, $return_url ) {
		try {
			$source = \Stripe\Source::create( [
				'type' => 'alipay',
				'amount'	=> $price * 100,
				'currency'	=> 'usd',
				'redirect'	=> [ 'return_url' => $return_url ],
			] ) ;

		} catch ( \Exception $e ) {
			b( 'err pay ' . $e->getMessage() ) ;
		}

		return $source ;
	}
}