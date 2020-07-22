<?php
/**
 * This class contains the logic for signing URLs and verifying them.
 */
if ( !class_exists( 'Signed_Url' ) ) {
  class Signed_Url {

    private $url;

    private $signing_key;

    private $query_params = [];

    public function __construct($signing_key) {
      $this->raw_url = $this->build_url_from_server();
      $this->url = parse_url( $this->raw_url );
      if (array_key_exists('query', $this->url) ) {
        parse_str( $this->url['query'], $this->query_params );
      }
      $this->signing_key = $signing_key;
    }

    public function is_valid() {
      return $this->has_valid_timestamp() && $this->has_valid_signature();
    }

    public function has_valid_timestamp() {
      // If there is no expires timestamp, it's valid
      if ( !array_key_exists('expiresAt', $this->query_params) ){
        return true;
      }

      $expires = new DateTime( $this->query_params['expiresAt'] );
      $now = new DateTime( 'now', new DateTimeZone('UTC') );
      return $expires > $now ? true : false;
    }

    public function has_valid_signature() {
      if (! array_key_exists('signature', $this->query_params) ) {
        return false;
      }
      $generated_sig = $this->generate_signature();
      $given_sig = $this->query_params['signature'];
      return strtolower($generated_sig) === strtolower($given_sig);
    }

    public function generate_signature() {
      return $this->hash_with_signing_key( $this->url_without_signature_param() );
    }

    public function url_without_signature_param() {
      return preg_replace('/[\?\&]signature=[A-F0-9]+/i', '', $this->raw_url);
    }

    private function hash_with_signing_key($string) {
      return hash('sha256', $string . $this->signing_key);
    }

    /**
     * Builds and returns the full URL to the requested page
     */
    private function build_url_from_server() {
      $s = $_SERVER;
      $use_forwarded_host = isset( $s['HTTP_X_FORWARDED_HOST'] ) && !empty( $s['HTTP_X_FORWARDED_HOST'] ) ? true : false;
      $ssl      = ( ! empty( $s['HTTPS'] ) && $s['HTTPS'] == 'on' );
      $sp       = strtolower( $s['SERVER_PROTOCOL'] );
      $protocol = substr( $sp, 0, strpos( $sp, '/' ) ) . ( ( $ssl ) ? 's' : '' );
      $port     = $s['SERVER_PORT'];
      $port     = ( ( ! $ssl && $port=='80' ) || ( $ssl && $port=='443' ) ) ? '' : ':'.$port;
      $host     = $use_forwarded_host ? $s['HTTP_X_FORWARDED_HOST'] : ( isset( $s['HTTP_HOST'] ) ? $s['HTTP_HOST'] : null );
      $host     = isset( $host ) ? $host : $s['SERVER_NAME'] . $port;
      return $protocol . '://' . $host . $s['REQUEST_URI'];
    }
  }
}
