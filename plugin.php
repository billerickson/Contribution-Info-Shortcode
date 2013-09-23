<?php
/*
Plugin Name: Contribution Info Shortcode
Plugin URI: http://www.billerickson.net/
Description: Display information about plugins, tutorials, code snippets, and core contributions
Version: 1.0
Author: Bill Erickson
Author URI: http://www.billerickson.net
License: GPLv2
*/

class BE_Contribution_Info {
	var $instance;
	
	function __construct() {
		$this->instance =& $this;
		add_shortcode( 'contribution', array( $this, 'contribution_info_shortcode' ) );
		add_shortcode( 'contribution-info', array( $this, 'contribution_info_shortcode' ) );
	}
	
	/**
	 * Contribution Info Shortcode
	 *
	 * @param array $atts
	 * @return string $output
	 */
	function contribution_info_shortcode( $atts ) {
		extract(shortcode_atts(array(
			'display' => false,
			'type' => 'plugin',
		), $atts));
		
	
		$contribution_info = get_transient( 'be_contribution_info' );
		if( false === $contribution_info ) {
			$contribution_info = $this->get_contribution_info();
			set_transient( 'be_contribution_info', $contribution_info, 86400 );
		}
	    
	    $output = 'many';
	    if( $display && isset( $contribution_info[$type][$display] ) ) 
	   		$output = number_format( $contribution_info[$type][$display] );
	   	elseif( isset( $contribution_info[$type] ) )
	   		$output = number_format( $contribution_info[$type] );
	   		
	   	return $output;
	}
	
	/**
	 * Get Contribution Information
	 *
	 * Uses various APIs to gather info and stores in transient
	 */
	function get_contribution_info() {
		$output = array();
		
		// Plugin
		$payload = array(
		  'action' => 'query_plugins',
		  'request' => serialize(
		    (object)array(
		        'author' => 'billerickson',
		        'fields' => array('downloaded' => true)
		     )
		   )
		);
		$body = wp_remote_post( 'http://api.wordpress.org/plugins/info/1.0/', array( 'body' => $payload) );
		
		$plugins = unserialize( $body['body'] )->plugins;
		$plugin_info = array( 'plugin_downloads' => 0, 'number_of_plugins' => 0 );
		foreach( $plugins as $plugin ) {
			$plugin_info['plugin_downloads'] += $plugin->downloaded;
			$plugin_info['number_of_plugins']++;
		}
 		$output['plugin'] = $plugin_info;
 		
 		// Tutorial
		$args = array(
			'posts_per_page' => -1,
			'category_name' => 'tutorial',
			'no_found_rows' => true, // counts posts, remove if pagination required
			'update_post_term_cache' => false, // grabs terms, remove if terms required (category, tag...)
			'update_post_meta_cache' => false, // grabs post meta, remove if post meta required
		);
		$loop = new WP_Query( $args );
		$total = $loop->post_count;
		wp_reset_postdata();
		$output['tutorial'] = $total;
 
 		// Code Snippets
		$args = array(
			'posts_per_page' => -1,
			'post_type' => 'code',
			'no_found_rows' => true, // counts posts, remove if pagination required
			'update_post_term_cache' => false, // grabs terms, remove if terms required (category, tag...)
			'update_post_meta_cache' => false, // grabs post meta, remove if post meta required
		);
		$loop = new WP_Query( $args );
		$total = $loop->post_count;
		wp_reset_postdata();
		$output['code-snippet'] = $total;
	
		// Core Contributions
		$results_url = add_query_arg( array(
				'q'				=>	'props+' . 'billerickson',
				'noquickjump'	=>	'1',
				'changeset'		=>	'on'
		), 'https://core.trac.wordpress.org/search' );
		$results = wp_remote_retrieve_body( wp_remote_get( $results_url, array( 'sslverify' => false ) ) );

		$pattern = '/<dt><a href="(.*?)" class="searchable">\[(.*?)\]: ((?s).*?)<\/a><\/dt>\n\s*(<dd class="searchable">.*\n?.*(?:ixes|ee) #(.*?)\n?<\/dd>)?/';

		preg_match_all( $pattern, $results, $matches, PREG_SET_ORDER );

		$formatted = array();

		foreach ( $matches as $match ) {
			array_shift( $match );
			$new_match = array(
				'link'			=> 'https://core.trac.wordpress.org' . $match[0],
				'changeset'		=> intval($match[1]),
				'description'	=> $match[2],
				'ticket'		=> isset( $match[3] ) ? intval($match[4]) : '',
			);
			array_push( $formatted, $new_match );
		}
		
		$output['core'] = count( $formatted );
		
		
		return $output;
	
	}
}
	
new BE_Contribution_Info;