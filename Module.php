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
                'cacheStats' => false,
                'cachePieStats' => false,
				'pluginsStats' => false
             ),
	        'panels' => array(
	            'cacheObjects' => array(
	                'display'       => true,
	                'logo'          => 'logo.png',
	                'menuTitle' 	=> 'Cache Objects',
	                'panelTitle'	=> 'Cache Objects Data',
	                'resources'     => array(
	                    'chart' => 'chart.js'
	                )
	            ),
	            'plugins' => array(
	                'display'       => true,
	                'logo'          => 'logo.png',
	                'menuTitle' 	=> 'Plugins',
	                'panelTitle'	=> 'Plugins',
	                'resources'     => array(
	                    'chart' => 'chart.js'
	                )
	            ),
				'hooks' => array(
					'display'       => true,
	                'logo'          => 'logo.png',
	                'menuTitle' 	=> 'Hooks',
	                'panelTitle'	=> 'Hooks',
	                'searchId' 		=> 'hooks-summary-table-search',
	                'pagerId'		=> 'hooks-summary-table-pager',
				),
				'theme' => array(
					'display'       => true,
	                'logo'          => 'logo.png',
	                'menuTitle' 	=> 'Theme',
	                'panelTitle'	=> 'Theme',
				)
	         )
	    );
	}	
}