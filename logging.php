<?php

$isLogging = false;

if(!function_exists("bbp_unread_posts_clog")){
	function bbp_unread_posts_clog( $data ) {
		if($isLogging){
			if ( is_array( $data ) )
				$output = "<script>console.log( 'Debug Objects: " . implode( ',', $data) . "' );</script>";
			else
				$output = "<script>console.log( 'Debug Objects: " . $data . "' );</script>";
		
			echo $output;
		}
	}
}
?>