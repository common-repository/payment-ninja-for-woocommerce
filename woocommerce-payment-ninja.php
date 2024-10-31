<?php
/**
 * Plugin Name: Payment.Ninja for WooCommerce
 * Plugin URI: https://payment.ninja/
 * Description: Save up to 50% on payment fees with Payment.Ninja!
 * Author: Payment.Ninja Inc.
 * Author URI: https://payment.ninja/
 * Version: 1.0.1
 *
 * Save up to 50% on payment fees with Payment.Ninja
 */


defined( 'ABSPATH' ) or exit;
// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}

/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_pn_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Payment_Ninja';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_pn_add_to_gateways' );
/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_payment_ninja_gateway_plugin_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_payment_ninja_gateway' ) . '">' . __( 'Configure' ) . '</a>'
	);
	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_payment_ninja_gateway_plugin_links' );
/**
 * @class 		WC_Gateway_Payment_Ninja
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 */
add_action( 'plugins_loaded', 'wc_payment_ninja_gateway_init', 11 );
function wc_payment_ninja_gateway_init() {
	class WC_Gateway_Payment_Ninja extends WC_Payment_Gateway {
		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {

			$this->id                 = 'wc_payment_ninja_gateway';
			$this->method_title       = __( 'Payment.Ninja' );
            $this->method_description = __( 'Pay with credit cards online via Payment.Ninja' );
            $this->order_button_text  = __( 'Place Order' );
			$this->has_fields         = true;
			$this->version			  = '1.0.0';

			// API EndPoint URL
			$this->payment_url = 'https://api.payment.ninja/api/payments';

			// Load the settings.
			$this->init_form_fields();
            $this->init_settings();

			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->api_key        = $this->get_option( 'key' );
			$this->sandbox		= $this->get_option( 'sandbox' );
			$this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );

			$this->supports     = array('products');

			add_action( 'wp_enqueue_scripts', array( $this, 'wc_payment_ninja_payment_scripts' ) );
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_wc_gateway_payment_ninja', array( $this, 'process_refund_notification_from_payment_ninja' ) );
        }

		/**
		 * Initialize Gateway Settings Form Fields
		 */
		public function init_form_fields() {

			$this->form_fields = array(

				'enabled' => array(
					'title'   => __( 'Enable/Disable' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Payment.Ninja' ),
					'default' => ''
				),

				'sandbox' => array(
					'title'   => __( 'Enable/Disable' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Sandbox Mode for testing.' ),
					'default' => ''
				),

				'key' => array(
					'title'       => __( 'Enter Your Merchant Key' ),
					'type'        => 'text',
					'description' => __( 'Please find your Merchant Key at Payment.Ninja Dashboard' ),
					'default'     => '',
					'desc_tip'    => true,
				),

				'title' => array(
					'title'       => __( 'Title' ),
					'type'        => 'text',
					'description' => __( 'This will be the title of Payment Option customer will see on checkout page.' ),
					'default'     => __( '' ),
					'desc_tip'    => true,
				),

				'description' => array(
					'title'       => __( 'Description' ),
					'type'        => 'textarea',
					'description' => __( 'Description about the payment gateway customer will see on checkout page.' ),
					'default'     => __( 'Pay with your Credit Cards via Payment.ninja' ),
					'desc_tip'    => true,
				)
			);
        }

		/**
		 * Get gateway icon.
		 * @return string
		 */
		// public function get_icon() {
		// 	$icon_html = '<img src="'. plugins_url( "assets/wc-payment-ninja.png", __FILE__ ) .'" alt="'. esc_attr( $this->id ) .'" />';
		// 	return $icon_html;
		// }

		/**
		 * Payment form on checkout page.
		 */
		public function payment_fields() {
			$description = $this->get_description();

			if ( 'yes' == $this->sandbox ) {
				$description .= ' <br/>' . __( 'TEST MODE ENABLED. You can use any card for testing, it will not be charged.', 'woocommerce' );
			}

			// if ( $description ) {
			// 	echo wpautop( wptexturize( trim( $description ) ) );
			// }

			// Show Payment Logos
			echo '<img src="'. plugins_url( "assets/card-brands.png", __FILE__ ) .'" title="Accepted Cards"/>';
			?>
			<script type="text/javascript">jQuery(document).ready(function() {
      	Payment.formatCardNumber(document.querySelector('#wc_payment_ninja_gateway-card-number'));
        Payment.formatCardExpiry(
          document.querySelector('#wc_payment_ninja_gateway-card-expiry')
        );
        Payment.formatCardCVC(
          document.querySelector('#wc_payment_ninja_gateway-card-cvc')
        );
      });</script>
			<fieldset id="wc-wc_payment_ninja_gateway-cc-form" class="wc-credit-card-form wc-payment-form">
				<p class="form-row form-row-wide">
					<label for="wc_payment_ninja_gateway-card-number">Card number&nbsp;<span class="required">*</span></label>
					<!-- <iframe id="tokenframe" name="tokenframe" src="https://fts.cardconnect.com:6443/itoke/ajax-tokenizer.html?invalidinputevent=true&tokenizewheninactive=true&css=body%7Bpadding%3A0%3Bmargin%3A0%3B%7Dinput%7Bwidth%3A95%25%3Bline-height%3A45px%3Bpadding%3A0+10px%3Bfont-size%3A1rem%3B%7D%2Eerror%7Bcolor%3Ared%3Bborder-color%3Ared%3B%7D" frameborder="0" scrolling="no" width="100%" height="50px" style="margin-bottom: 0;"></iframe> -->
					<!-- <input type="hidden" name="wc_pn_mytoken" id="wc_pn_mytoken"> -->
          <input id="wc_payment_ninja_gateway-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="•••• •••• •••• ••••" name="wc_payment_ninja_gateway-card-number">
					<!-- <input id="wc_payment_ninja_gateway-card-number" class="input-text wc-credit-card-form-card-number" inputmode="numeric" autocomplete="cc-number" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="•••• •••• •••• ••••" name="wc_payment_ninja_gateway-card-number"> -->
				</p>
				<p class="form-row form-row-first">
					<label for="wc_payment_ninja_gateway-card-expiry">Expiry (MM/YYYY)&nbsp;<span class="required">*</span></label>
					<input id="wc_payment_ninja_gateway-card-expiry" class="input-text wc-credit-card-form-card-expiry" inputmode="numeric" autocomplete="cc-exp" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="MM/YYYY" name="wc_payment_ninja_gateway-card-expiry">
				</p>
				<p class="form-row form-row-last">
					<label for="wc_payment_ninja_gateway-card-cvc">CVC&nbsp;<span class="required">*</span></label>
					<input id="wc_payment_ninja_gateway-card-cvc" class="input-text wc-credit-card-form-card-cvc" inputmode="numeric" autocomplete="off" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" maxlength="4" placeholder="CVC" name="wc_payment_ninja_gateway-card-cvc" style="width:100px">
				</p>
				<div class="clear"></div>
			</fieldset>
			<?php
		}

		/**
		 * Validate Payment Fields to make sure all Card Details are available for processing payment.
		 */
		public function wc_payment_ninja_payment_scripts(){
			$load_scripts = false;

			if ( is_checkout() ) {
				$load_scripts = true;
			}
			if ( $this->is_available() ) {
				$load_scripts = true;
			}

			if ( false === $load_scripts ) {
				return;
			}

			wp_enqueue_script( 'wc-pn-checkout-field-validation', plugins_url( "assets/payment.js", __FILE__ ) , array( 'jquery' ), $this->version, false );
		}

		/**
		 * Validate Payment Fields to make sure all Card Details are available for processing payment.
		 */
		public function validate_fields() {
			$valid_fields = true;
			if(!isset($_POST['wc_payment_ninja_gateway-card-number']) || $_POST['wc_payment_ninja_gateway-card-number'] == ''){
				wc_add_notice( __( "Please enter a Valid <strong>Credit Card Number</strong>" ), 'error');
				$valid_fields = false;
			}

			if(!isset($_POST['wc_payment_ninja_gateway-card-expiry']) || $_POST['wc_payment_ninja_gateway-card-expiry'] == ''){
				wc_add_notice( __( "Please enter <strong>Card Expiry Date</strong>" ), 'error');
				$valid_fields = false;
			} elseif(count(explode("/", $_POST['wc_payment_ninja_gateway-card-expiry'])) != 2){
				wc_add_notice( __( "Entered <strong>Card Expiry Date</strong> are not valid. Valid format is 'MM/YYYY'" ), 'error');
				$valid_fields = false;
			}

			if(!isset($_POST['wc_payment_ninja_gateway-card-cvc']) || $_POST['wc_payment_ninja_gateway-card-cvc'] == '' || strlen($_POST['wc_payment_ninja_gateway-card-cvc']) < 2){
				wc_add_notice( __( "Please enter valid <strong>CVC</strong>" ), 'error');
				$valid_fields = false;
			}

			return $valid_fields;
		}

		/**
		 * Processed Payment Order need to be confirmed with API and update order status
		 */
		public function process_payment($order_id) {

      $order = wc_get_order( $order_id );
			$api_key = $this->api_key;
			$user_info = get_userdata($order->get_customer_id());
			$user_email = $user_info->user_email;
			$total_amount = $order->get_total();
			$currency = get_woocommerce_currency();
			// Get Posted Details
			$cc_number_token = wc_clean( $_POST['wc_payment_ninja_gateway-card-number'] );
			$cc_expiry = wc_clean( $_POST['wc_payment_ninja_gateway-card-expiry'] );
			$cc_expiry_array = explode("/", $cc_expiry);
			$cc_month = $cc_expiry_array[0];
			$cc_year = $cc_expiry_array[1];
			$cc_cvc = wc_clean( $_POST['wc_payment_ninja_gateway-card-cvc'] );
			// Refund Notification URL
			$reund_notify_url = add_query_arg( array('wc-api' => 'WC_Gateway_Payment_Ninja', 'id' => $order_id), home_url( '/' ) );

			// Prepare Array to be sent to API for Transaction
      $valid_amount = $total_amount;

			$api_data = array(
					'key' => $api_key,
					'email' => $user_email,
					'refundnotifyuri' => $reund_notify_url,
					'amount' => $valid_amount,
					'currency' => $currency,
					'card' => $cc_number_token, // token
					'expMM' => $cc_month,
					'expYYYY' => $cc_year,
					'cvc' => $cc_cvc
			);

			if ( 'yes' == $this->sandbox ) {
				$api_data = array(
						'key' => 'bjsBPTYsRoLFucZxqZVDZu',
						'email' => 'test@test.com',
						'refundnotifyuri' => $reund_notify_url,
						'amount' => $valid_amount,
						'currency' => $currency,
						'card' => '9526724594484736', // token
						'expMM' => '12',
						'expYYYY' => '2018',
						'cvc' => '123'
				);
			}

			$request = wp_remote_post($this->payment_url, array(
				'headers'   => array('Content-Type' => 'application/json'),
				'body'      => json_encode($api_data),
				'method'    => 'POST'
			));
			$error = false;
			$response_code = wp_remote_retrieve_response_code( $request );
			if( 200 != $response_code){
				$error = __( 'Unable to process your payment at the moment. Please contact us.');
				$error_note = 'Incorrect response code from API (' . $response_code . ')';
			} else{
				$transaction_details = json_decode( wp_remote_retrieve_body( $request ) );
				if(!property_exists($transaction_details, 'status') || $transaction_details->status != 'approved'){
					if(property_exists($transaction_details, 'status')){
						$error = __('Payment processing failed. Please contact Administrator for same.');
						$error_note = 'Payment Failed with status '. $transaction_details->status;
					} else {
						// Can be any other validation error, but display "Payment Declined" message to customer for Safety
						$error = __('Payment Declined. Please try again using another card.');
						if(property_exists($transaction_details, 'error')){
							$error_note = 'Payment Failed with Error Message '. $transaction_details->error;
						} else {
							$error_note = 'Payment Failed with Error Message (error message not found)';
						}
					}
				} else{
					// Status is Approved
					$payment_ninja_transaction_id = $transaction_details->_id;
				}
			}

            if ($error) {
                $order->add_order_note('Payment Processing Failed. Error: '.$error_note);
                wc_add_notice($error, 'error');
				return;
            } else {
				// Save Transaction ID to order meta and add order note too
				update_post_meta($order_id, '_payment_ninja_payment_id', $payment_ninja_transaction_id);
				$note = __('Payment Processed: Payment.Ninja ID is:');
				$order->add_order_note( $note ." ". $payment_ninja_transaction_id );

				// Mark payment as approved from API and complete the order
				$order->payment_complete();

				// Remove cart
				WC()->cart->empty_cart();

				// Return, and redirect to thank you page
				return array(
					'result' 	=> 'success',
					'redirect'	=> $this->get_return_url( $order )
				);
			}
		}

		/**
		 * Update Order status on Notification from Payment.Ninja about Refund of transaction
		 */
		public function process_refund_notification_from_payment_ninja(){
			if(isset($_REQUEST['id']) && $_REQUEST['id'] != ''){
				$order = wc_get_order($_REQUEST['id']);
				if($order){
					$order->update_status('refunded', 'As per Notification from Payment.Ninja ');
				}
			} else {
				// ID not found, can write log here if required for deep logging of process
			}
			exit(0);
		}
  } // end \WC_Gateway_Payment_Ninja class
}
