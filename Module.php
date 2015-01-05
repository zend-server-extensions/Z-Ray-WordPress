<?php

namespace Wordpress;

class Module extends \ZRay\ZRayModule {
	
	public function config() {
	    return array(
	        'extension' => array(
				'name' => 'wordpress',
			),
	        // configure  custom panels
            'defaultPanels' => array(
//                 'crons' => false,
//                 'generalInfo' => false,
//                 'hooks' => false,
//                 'plugins' => false,
             ),
	        'panels' => array(
	            'cacheObjects' => array(
	                'display'       => true,
	                'logo'          => 'logo.png',
	                'menuTitle' 	=> 'Cache Objects',
	                'panelTitle'	=> 'Cache Objects Tree Table',
	            ),
	         )
	    );
	}	
}