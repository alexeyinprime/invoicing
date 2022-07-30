<?php

defined('ABSPATH') || exit;

abstract class Getpaid_PayKeeper_Gateway extends GetPaid_Payment_Gateway{

    private $paykeeper_login = "demo";
    private $paykeeper_password = "demo";
    private $paykeeper_secret_word = "KaraKarPal";
    private $paykeeper_token = '';

    /**
     * Constructor
     */

     public function __construct(){
        //Init
        $this->id           = 'paykeeper';
        $this->title        = __( 'PayKeeper', 'my-domain' ); // Frontend name
        $this->method_title = __( 'PayKeeper Gateway', 'my-domain' ); // Admin name
        $this->description  = __( 'Pay using my PayKeeper payment gateway', 'my-domain' );
    

	//	$this->enabled = wpinv_is_gateway_active( $this->id );
        parent::__construct();

    }


    /**
	 * Processes ipns and marks payments as complete.
     * @docs https://docs.paykeeper.ru/metody-integratsii/priyom-post-opoveshhenij/
	 *
	 * @return void
	 */
	public function verify_ipn() {
        // проверяем наличие необходимых данных в массиве $_POST
		if ( empty( $_POST ) || empty( $_POST["id"]) || empty( $_POST["sum"]) || empty( $_POST["orderid"]) || empty($_POST["key"]) ) {
			wp_die( "Gateway IPN Request Failure", 500 );
		}
        
        $posted  = wp_unslash( $_POST );
        
        if (empty($posted['clientid'])){$posted['clientid']="";}
    
        // проверяем подпись и целостность запроса
    
    	if ($posted["key"] != md5(
            $posted["id"].$posted["sum"].$posted['clientid'].$posted["orderid"].$this->paykeeper_secret_word
        )) {
            wp_die( 'Gateway IPN Request Invalid hash', 500 );
        }
        // ищем инвойс по orderid
        $invoice = $this->get_ipn_invoice( $posted['orderid'] );

        if (empty($invoice)){
            wpinv_error_log( 'Aborting, Invoice was not found', false );
            wp_die( 'Invoice Not Found', 500 );
        }

        // Abort if it was not paid by our gateway.
        if ( $this->id != $invoice->get_gateway() ) {
            wpinv_error_log( 'Aborting, Invoice was not paid via PayKeeper', false );
            
        }


        //проверяем совпадение данных о инвойса с данными из _POST
        if ( number_format( $invoice->get_total(), 2, '.', '' ) !== number_format( $posted["sum"], 2, '.', '' ) ) {
			/* translators: %s: Amount. */
			wpinv_error_log( "Amounts do not match: {$posted['sum']} instead of {$invoice->get_total()}", 'IPN Error', false );
            wpinv_error_log( $posted["sum"], 'Validated IPN Amount', false );
            wp_die( 'Invoice not paid via PayKeeper', 500 );
	}

        if ( $invoice->is_paid() || $invoice->is_refunded() ) {
            // инвойс уже оплачен или возвращен
            wpinv_error_log( 'Aborting, Invoice was paid or refunded', false );
        }

        // меняем статус инвойса на УПЛОЧЕНО
        $invoice->mark_paid();
        wpinv_error_log( 'Wow, Invoice {$invoice->id}  was paid successfully', false );
        // пишем ответ Пайкиперу
       
	
	 echo "OK ".md5($posted["id"].$this->secret_word);


    }

   /**
	 * Retrieves IPN Invoice.
	 *
	 * @param array $posted
	 * @return WPInv_Invoice
	 */
	protected function get_ipn_invoice( $invoice_id) {

		wpinv_error_log( 'Retrieving PayKeeper IPN Response Invoice', false );

		
		$invoice = new WPInv_Invoice($invoice_id);

		if ( $invoice->exists() ) {
			wpinv_error_log( 'Found invoice #' . $invoice->get_number(), false );
			return $invoice;
		}
	    
		wpinv_error_log( 'Could not retrieve the associated invoice.', false );
		wp_die( 'Could not retrieve the associated invoice.', 200 );
    
	}







      
}