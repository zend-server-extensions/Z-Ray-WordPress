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
				'pluginsStats' => false,
				'core_hooks' => false,
				'generalInfo' => false
             ),
	        'panels' => array(
				'crons' => array(
					'display'       => true,
	                'logo'          => 'logo.png',
	                'menuTitle' 	=> 'Crons',
	                'panelTitle'	=> 'Crons',
				),
				'wp_query' => array(
					'display'       => true,
	                'logo'          => 'logo.png',
	                'menuTitle' 	=> 'WP Query',
	                'panelTitle'	=> 'WP Query',
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
	            'cacheObjects' => array(
	                'display'       => true,
	                'logo'          => 'logo.png',
	                'menuTitle' 	=> 'Cache Objects',
	                'panelTitle'	=> 'Cache Objects',
					'searchId' 		=> 'cache-table-search',
	                'resources'     => array(
	                    'chart' => 'chart.js'
	                )
	            ),
				'dashboard' => array(
					'display'       => true,
	                'logo'          => 'logo.png',
	                'menuTitle' 	=> 'Dashboard',
	                'panelTitle'	=> 'Dashboard',
				)
	         )
	    );
	}	
}