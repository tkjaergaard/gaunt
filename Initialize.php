<?php

use Gaunt\Gaunt;

if( !defined('STYLESHEETPATH') )
    define('STYLESHEETPATH' , get_stylesheet_directory());

if( !defined('TEMPLATEPATH') )
    define('TEMPLATEPATH', get_template_directory());

// Capture the wordpress include and return cached path
add_filter( 'template_include', function($template){

    return Gaunt::make( $template );

},10,1);