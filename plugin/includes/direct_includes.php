<?php

/* This include is for functions and directives that need to be loaded outside class structure */


/* Runs when plugin is activated */
register_activation_hook($cb_p3->internal['plugin_path'].'index.php',array(&$cb_p3,'activate')); 

/* Runs on plugin deactivation*/
register_deactivation_hook( $cb_p3->internal['plugin_path'].'index.php', array(&$cb_p3,'deactivate'));




?>