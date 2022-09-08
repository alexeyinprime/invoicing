<?php

defined('ABSPATH') || exit;

class Getpaid_PayKeeper_Gateway extends GetPaid_Payment_Gateway{

    private $paykeeper_login = "demo";
    private $paykeeper_password = "demo";
    private $paykeeper_secret_word = "KaraKarPal";
    private $paykeeper_token = '';
    private $paykeeper_token_url = 'https://demo.paykeeper.ru/info/settings/token/';
    public $id = "paykeeper";
    /**
     * Constructor
     */

     public function __construct(){
        //Init
    //    $this->id           = 'paykeeper';
   //     $this->title        = __( 'PayKeeper'); // Frontend name
   //     $this->method_title = __( 'PayKeeper Gateway'); // Admin name
   //     $this->description  = __( 'Pay using my PayKeeper payment gateway');
    
        $this->paykeeper_secret_word = wpinv_get_option("paykeeper_secret_word");
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
            wpinv_error_log( 'Aborting, Invoice was paid or refunded early', false );
        }

        // меняем статус инвойса на УПЛОЧЕНО
        $invoice->mark_paid();
        wpinv_error_log( 'Wow, Invoice {$invoice->id}  was paid successfully', false );
        // пишем ответ Пайкиперу
        wp_die("OK ".md5($posted["id"].$this->paykeeper_secret_word), 200);
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


/**
       * Process Payment
       */
      public function process_payment( $invoice, $submission_data, $submission ) {

        // Get redirect url.
        $paykeeper_redirect = $this->get_request_url( $invoice );

        // Add a note about the request url.
        $invoice->add_note(
            sprintf(
                __( 'Redirecting to PayKeeper: %s', 'invoicing' ),
                esc_url( $paykeeper_redirect )
            ),
            false,
            false,
            true
        );
        // set invoce processing status
        $invoice->set_status( 'wpi-processing' );
        // Add note.
        $invoice->add_note( __( 'Mark invoice status on processing'), false, false, true );
        // ... then save it...
        $invoice->save();
        

        // Redirect to PayKeeper
        wp_redirect( $paykeeper_redirect );
        exit;
    }

  /**
     * Get the PayKeeper request URL for an invoice.
     *
     * @param  WPInv_Invoice $invoice Invoice object.
     * @return string
     */
    public function get_request_url( $invoice ) {
        return wpinv_get_option("paykeeper_success_url");
    }

 /**
	 * Filters the gateway settings.
	 *
	 * @param array $admin_settings
	 */
	public function admin_settings( $admin_settings ) {

        $admin_settings['paykeeper_secret_word'] = array(
            'type' => 'text',
            'id'   => 'paykeeper_secret_word',
            'name' => __( 'PayKeeper Secert Word', 'invoicing' ),
            'desc' => __( 'Optionally enter your secret word here', 'invoicing' ),
        );
        $admin_settings['paykeeper_success_url'] = array(
            'type' => 'text',
            'id'   => 'paykeeper_success_url',
            'name' => __( 'PayKeeper Success Url', 'invoicing' ),
            'desc' => __( 'Optionally enter your succes url here', 'invoicing' ),
        );


        return $admin_settings;
    }


    /**
     * Функция для получения токена от Paykeeper
     * @return string
     */
    protected function get_transaction_token(){
        // если токен уже был ранее запрошен в хоте текущей операции, то используем его
        // @todo возможно этот токен вообще не меняется (надо проверить), тогда его можно хранить в настройках плагина
        if (!empty($this->paykeeper_token)){
            return $this->paykeeper_token;
        }
        $headers = [];
        array_push($headers,'Content-Type: application/x-www-form-urlencoded');      
        array_push($headers,'Authorization: Basic '.$base64_encode("{$this->paykeeper_login}:{$this->paykeeper_password}")); 


        $curl = curl_init();
        curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl,CURLOPT_URL,$this->paykeeper_token_url);
        curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'GET');
        curl_setopt($curl,CURLOPT_HTTPHEADER,$headers);
        curl_setopt($curl,CURLOPT_HEADER,false);
 
        /**
         *  Инициируем запрос к API
         * предполагается, что в случае успеха сервер Paykeeper возвращает нам JSON 
         * с заполненной переменной tokem
        */
        $response=curl_exec($curl);                       
        $php_array=json_decode($response,true);
 
        # В ответе должно быть заполнено поле token, иначе - ошибка
        if (isset($php_array['token'])){
                $this->paykeeper_token = $php_array['token'];
                return $this->paykeeper_token;
            } else {
                wpinv_error_log( 'Could not retrieve the paykeeper tocken.', false );
                wp_die( 'Could not retrieve the token from PayKeeper.', 200 );
            }

    }

}