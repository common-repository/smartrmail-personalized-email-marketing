<?php

namespace SmartrMail\Controllers;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminController {
    
    protected $registerUrl;
    protected $optionsName;
    protected $uri;
    
    public function __construct( $registerUrl, $optionsName, $uri ) {
        $this->registerUrl = $registerUrl;
        $this->optionsName = $optionsName;
        $this->uri = $uri;
        
        $this->AddOptionsPage();
        $this->RegisterAssets();
        $this->RegisterAjaxFunctions();
        $this->RegisterEndpoints();
    }
    
    public function Init() {
        
    }
    
    public function AddOptionsPage() {
        add_action( 'admin_menu', function() {
            add_menu_page(
                __( 'SmartrMail API key generation', 'smartrmail' ),
                __( 'SmartrMail', 'smartrmail', 'smartrmail' ),
                'manage_options',
                'smartrmail-api',
                [$this, 'PrintOptionsContent']
            );
        } );
    }

    public function RegisterAssets() {
        add_action( 'admin_enqueue_scripts', function() {
            wp_register_script( 'SWI-js', $this->uri . 'assets/js/main.js', array('jquery'), '3.2.1', true );
            wp_localize_script( 'SWI-js', 'Ajax', [ 'adminAjax' => admin_url( 'admin-ajax.php' ) ] );
            wp_enqueue_script( 'SWI-js' );
            wp_enqueue_style( 'SWI-css', $this->uri . 'assets/css/style.css', false, '1.0.0' );
        } );
    }

    public function RegisterAjaxFunctions() {
        add_action( 'wp_ajax_SendJSON', [ $this, 'SendJSON' ] );
        add_action( 'wp_ajax_nopriv_SendJSON', [ $this, 'SendJSON' ] );
    }

    public function RegisterEndpoints() {
        add_action( 'rest_api_init', function () {
            register_rest_route( 'swi-api/v1', '/javascript', [
                'methods' => 'PUT',
                'callback' => [$this, 'AddJsToDB'],
            ] );
        } );
    }

    public function SendJSON() {

        $url = $this->registerUrl;
        $headers = array("X-Requested-With" => "XMLHttpRequest");
        $response = wp_remote_post( $url,
                                    array('body' => $this->BuildJSON(),
                                          'headers' => $headers)
                                  );

        if ( is_wp_error( $response ) ) {
            wp_send_json( [ 'status' => 'error', 'data' => '' ] );
        } else {
            $response_body = $response['body'];
            $json = json_decode($response_body);
             if( $this->SaveData( $json->url ) ) {
                wp_send_json( [ 'status' => 'success', 'data' => $json->url ] );
            } else {
                wp_send_json( [ 'status' => 'error', 'data' => '' ] );
            }
        }
    }


    public function BuildJSON() {
      $url = parse_url( get_home_url( ) );
      $address = implode(', ', array(
        get_option('woocommerce_store_address'),
        get_option('woocommerce_store_address_2'),
        get_option('woocommerce_default_country'),
        get_option('woocommerce_store_postcode')
      ));

      return [
        'uid'       => $url['host'],
        'data'      => [
          'email'         => esc_html( get_option('admin_email') ),
            'name'          => esc_html( get_option('blogname') ),
            'domain'        => $url['host'],
            'currency'      => get_option('woocommerce_currency'),
            'weight_unit'   => get_option('woocommerce_weight_unit'),
            'store_url'             => $url['scheme'] . '://' . $url['host'] . $url['path'],
            'woocommerce_version'   => $this->GetWooVersionNumber(),
            'base_location'         => wc_get_base_location(),
            'base_address'          => $address
          ]
        ];
    }


    public function SaveData( $url ) {
        $urlParams = $this->GetDataFromURL( $url );

        if( isset($urlParams['user_id']) && isset($urlParams['return_url']) ) {
            update_option($this->optionsName['id'], $urlParams['user_id']);
            update_option($this->optionsName['return.url'], $urlParams['return_url']);

            return true;
        }
        return false;
    }

    public function GetDataFromURL( $url ) {
        $query = parse_url( $url, PHP_URL_QUERY );
        parse_str( $query, $params );

        return $params;
    }

    public function GetWooVersionNumber() {
        if ( ! function_exists( 'get_plugins' ) ) {
            require_once( ABSPATH . 'wp-admin/includes/plugin.php' );
        }

        $plugin_folder = get_plugins( '/' . 'woocommerce' );
        $plugin_file = 'woocommerce.php';

        if ( isset( $plugin_folder[$plugin_file]['Version'] ) ) {
            return $plugin_folder[$plugin_file]['Version'];
        } else {
            return NULL;
        }
    }

    public function AddJsToDB( $data ) {
        $js = serialize($data['javascript']);

        $secureHash = $data['secure_hash'];
        $createHash = hash_hmac('sha256', get_option($this->optionsName['id']), $this->GetConsumerSecret());

        if(!$this->HashCompare($secureHash, $createHash)) {
            return 'Hash not matched';
        }

        if(!isset($js) || $js == serialize('')) {
            update_option($this->optionsName['javascript'], '');
            return 'disabled';
        }

        update_option($this->optionsName['javascript'], $js);

        return 'enabled';
    }

    function GetConsumerSecret() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'woocommerce_api_keys';
        $data = $wpdb->get_results( "SELECT description, consumer_secret FROM " . $table_name );
        $consumerSecret = '';

        foreach($data as $d) {
            if( sizeof(explode('SmartrMail', $d->description)) != 1) {
                $consumerSecret = $d->consumer_secret;
                break;
            }
        }
        return $consumerSecret;
    }

    function HashCompare($a, $b) { 
        if (!is_string($a) || !is_string($b)) { 
            return false; 
        } 

        $len = strlen($a); 
        if ($len !== strlen($b)) { 
            return false; 
        } 

        $status = 0; 
        for ($i = 0; $i < $len; $i++) { 
            $status |= ord($a[$i]) ^ ord($b[$i]); 
        } 
        return $status === 0; 
    }

    public function PrintOptionsContent() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'woocommerce_api_keys';
        $data = $wpdb->get_results( "SELECT description FROM " . $table_name );
        $bool = false;

        foreach($data as $d) {
            if( sizeof(explode('SmartrMail', $d->description)) != 1) {
                $bool = true;
                break;
            }
        }

        $bool = ($bool && get_option( $this->optionsName['id'] ) && get_option( $this->optionsName['return.url'] ));
?>
        <h3><?php echo  __( 'SmartrMail API key generation', 'smartrmail' ); ?></h3>
        <p>
            <?php echo __('You are ', 'smartrmail') ?>
            <span class="swi-connections <?php echo ($bool) ? 'swi-connected' : 'swi-not-connected'; ?>">
                <?php echo ($bool) ? __('connected', 'smartrmail') : __('not connected', 'smartrmail'); ?>
            </span>
        </p>

        <?php if(!$bool) : ?>
        <button class='button button-primary swi-button-register'><?php echo __( 'Register', 'smartrmail' ) ?></button>
        <?php else : ?>
        <a href="<?php echo get_option( $this->optionsName['return.url'] ); ?>?user_id=<?php echo get_option( $this->optionsName['id'] ); ?>" class='button button-primary swi-button-login'><?php echo __( 'Log in', 'smartrmail' ); ?></a>
        <?php endif; ?>
<?php
    }
}
