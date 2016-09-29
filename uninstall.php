<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if(defined('WP_UNINSTALL_PLUGIN') ){
	global $wpdb;

	$wpdb->query( 
		$wpdb->prepare( 
			"DELETE FROM $wpdb->usermeta WHERE meta_key = %s", "_ld_quiz_retake_wrong_q"
        )
	);

}