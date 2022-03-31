<?php
/*
Plugin Name: WP Rocket Minify Selectively
Description: An extension to WP Rocket, to minify only whitelisted files
Author: Luca Castellone
Version: 1.0
*/

add_action('wp_rocket_loaded',function (){
    require_once __DIR__ . '/vendor/autoload.php';
    $wp_rocket_container = apply_filters( 'rocket_container', null );
    $event_manager = $wp_rocket_container->get( 'event_manager' );
    $options    = $wp_rocket_container->get( 'options' );
    $filesystem = rocket_direct_filesystem();
    $event_manager->add_subscriber( new \Kilbourne\WP_Rocket\Engine\Optimization\Minify\JS\Subscriber($options,$filesystem) );
    $event_manager->add_subscriber( new \Kilbourne\WP_Rocket\Engine\Optimization\Minify\CSS\Subscriber($options,$filesystem) );
});
