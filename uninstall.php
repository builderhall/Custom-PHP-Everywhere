<?php

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
  exit();
}

delete_option( 'custom_php_everywhere_scripts' );
