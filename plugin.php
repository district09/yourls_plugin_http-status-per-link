<?php
/*
Plugin Name: HTTP status per link
Plugin URI: https://github.com/Jelle-S/YOURLS-http-status-per-link
Description: Set the HTTP redirect status per link.
Version: 1.0
Author: Jelle Sebreghts
Author URI: https://github.com/Jelle-S
*/
// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();
include_once 'HTTPStatusPlugin.php';
new HTTPStatusPlugin(basename(__DIR__));
