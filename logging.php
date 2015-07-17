<?php

$isLogging = false;

if(!function_exists("bbp_unread_posts_clog")){
	function bbp_unread_posts_clog( $data ) {
		global $isLogging;
		if($isLogging){
			if ( is_array( $data ) )
				$output = "<script>console.log('".implode( ',', $data) . "');</script>";
			else
				$output = "<script>console.log('" . $data . "');</script>";
		
			echo $output;
		}
	}
}
?>