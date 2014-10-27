<?php


class Wordpress {
	private $zre;
	private $_profilePlugins = array();
	private $_profileThemes = array();
	private $_hooks = array();
	private $_filters = array();
	
	public function __construct(&$zre){
		$this->zre = $zre;
	}
	
	public function wpRunExit($context, &$storage){
		global $wp_object_cache,$wp_version;	
		//Cache Objects
		$data_array=array();
		foreach ($wp_object_cache->cache as $group => $group_items) {
			foreach($group_items as $group_item_name => $group_item){			
				$data_array[$group][$group_item_name] = number_format( strlen( serialize( $group_item ) ) / 1024, 2 ) .'k';
			}
		}
		$storage['cacheObjects'][] = $data_array;
		
		//Crons
		$doing_cron=get_transient( 'doing_cron' );
		if(is_array(_get_cron_array())){
			foreach( _get_cron_array() as $time=>$crons ){
				foreach($crons as $name=>$cron){
					foreach($cron as $subcron){
						$storage['crons'][] = array(
							'Hook'=>$name,
							'Schedule'=>$subcron['schedule'],
							'Arguments'=>count($subcron['args'])>0 ? print_r($subcron['args'],true) : '',
							'Next Execution'=>date( 'Y-m-d H:i:s', $time ) . ' (' . human_time_diff( $time ) . (time() > $time  ? ' ago' : '').')'
						);
					}
				}
			}
		}
		
		//General Info
		$storage['generalInfo'][] = array('Name'=>'Wordpress Version','Value'=>$wp_version);
		$storage['generalInfo'][] = array('Name'=>'Debug Mode (WP_DEBUG)','Value'=>WP_DEBUG ? 'On' : 'Off');
		$storage['generalInfo'][] = array('Name'=>'Debug Log (WP_DEBUG_LOG)','Value'=>WP_DEBUG_LOG ? 'On' : 'Off');
		$storage['generalInfo'][] = array('Name'=>'Script Debug (SCRIPT_DEBUG)','Value'=>SCRIPT_DEBUG ? 'On' : 'Off');
		$storage['generalInfo'][] = array('Name'=>'Template','Value'=>get_template());
		$storage['generalInfo'][] = array('Name'=>'Doing Crons','Value'=>$doing_cron ? 'Yes' : 'No');
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
		    $storage['generalInfo'][] = array('Name'=>'Save Queries (SAVEQUERIES)','Value'=>SAVEQUERIES ? 'On' : 'Off');
		}
		
		//Plugins List
		try{
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$apl=get_option('active_plugins');
			$plugins=get_plugins();
			
			$activated_plugins=array();
			if(is_array($apl) && count($plugins)>0){
				foreach ($apl as $p){           
					if(isset($plugins[$p])){
						 array_push($activated_plugins, $p);
					}           
				}
			}
			//Multisite plugins
			$mupl=get_mu_plugins();
			if(is_array($mupl) && count($plugins)>0){
				foreach ($mupl as $p => $v){
					$plugins[$p]=$v;
					array_push($activated_plugins, $p);
				}
			}
			
			$swplugs=get_site_option('active_sitewide_plugins');
			if(is_array($swplugs) && count($plugins)>0){
				foreach ($swplugs as $p => $v){           
					if(isset($plugins[$p])){
						 array_push($activated_plugins, $p);
					}
				}
			}
			if(count($plugins)>0){
				foreach($plugins as $p=>$plugin){
					$active='No';
					if(in_array($p,$activated_plugins)){
						$active='Yes'; 
					}
					$storage['plugins'][] = array('Name'=>$plugin['Name'],'Version'=>$plugin['Version'],'Activated'=>$active);
				}
			}
		}catch(Exception $e){
		}
		
		if (count($this->_profilePlugins)>0) {
			foreach($this->_profilePlugins as $name => $time){
				$storage['pluginsProfiler'][] = array('Name'=>$name,'Load Time (microseconds)'=>$time);
			}
		}
		if (count($this->_profileThemes)>0) {
			$storage['themeProfiler'][] = $this->_profileThemes;
		}
		//Hooks List
		$hookers=array();
		if(count($this->_hooks)>0){
			foreach($this->_hooks as $hookName => $hook){
				if(!array_key_exists($hookName,$hookers)){
					$hookers[$hookName]=array();
				}
				foreach($hook as $hooker){
					//retrieve the function (maybe closure / object) name
					if(is_string($hooker['hookFunction'])){
						$hookKey = $hooker['hookFunction'];
					}elseif(is_array($hooker['hookFunction'])){
						if(is_string(is_array($hooker['hookFunction'][0]))){
							$hookKey = $hooker['hookFunction'][0];
						}elseif(is_object($hooker['hookFunction'][0])){
							$hookKey = get_class($hooker['hookFunction'][0]) . '->' . $hooker['hookFunction'][1];
						}
					}else{
						
					}
					/*if(is_array($hooker['hookFunction']) && !is_object($hooker['hookFunction'][0])){
						var_dump($hooker['hookFunction'][0]);die;
					}*/
					$hookers[$hookName][$hookKey]=array(
						'File (Execution Time)'=>$hooker['file'].' ('.$hooker['executionTime'].'ms)',
						'Hook Type'=>$hooker['hookType']
					);
				}
			}
		}
		$storage['hooks'][]=$hookers;
		//var_dump($storage['hooks']);die;
		
		//Filters List
		if(count($this->_filters)>0){
			foreach($this->_filters as $filterName => $filter){
				$filterers=array();
				foreach($filter as $filterer){
					$filterers[]=array(
						'name'=>$filterer['filterFunction'],
						'File (Execution Time)'=>$filterer['file'].' ('.$filterer['executionTime'].'ms)',
						'filter Type'=>$filterer['filterType']
					);
				}
				$storage['filters'][]=array(
					'name'=>$filterName,
					'File (Execution Time)'=>$filterers,
					'filter Type'=>''
				);
			}
		}
	}
	public function pluginsFuncEnd($context,&$storage,$filename){
	    $plugin_dir_array = explode('\\',realpath(WP_PLUGIN_DIR));
		$plugins_dir_name = array_pop($plugin_dir_array);
		$muplugins_dir_array = explode('\\',realpath(WPMU_PLUGIN_DIR));
		$muplugins_dir_name = array_pop($muplugins_dir_array);
		if(preg_match('/'.$plugins_dir_name.'\/(.*?)\//',$filename,$match)||preg_match('/'.$muplugins_dir_name.'\/(.*?)\//',$filename,$match)){
			$plugin=$match[1];
			if(!isset($this->_profilePlugins[$plugin])){
				$this->_profilePlugins[$plugin]=0;
			}
			$this->_profilePlugins[$plugin]+=$context['durationExclusive'];
		}
	}
	public function themesFuncEnd($context,&$storage,$filename){
	    $theme_root_array = explode('\\',realpath(get_theme_root()));
		$theme_dir_name = array_pop($theme_root_array);
		if(preg_match('/'.preg_quote($theme_dir_name, '/').'\/(.*?)\//',$filename,$match)){
			$theme=$match[1];
			if(!isset($this->_profileThemes[$theme])){
				$this->_profileThemes[$theme]=array();
			}
			$this->_profileThemes[$theme][$context['functionName']]=$context['durationExclusive'].'ms';
		}
	}
	public function initProfiler($type='plugins'){
		switch($type){
			case 'themes':
				$func='themesFuncEnd';
				$path = realpath(get_theme_root());
				break;
			case 'mu-plugins':
				$func='pluginsFuncEnd';
				$path = realpath(WPMU_PLUGIN_DIR);
				break;
			default:
				$func='pluginsFuncEnd';
				$path = realpath(WP_PLUGIN_DIR);
				break;
		}
		try{
			$objects = new RegexIterator(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path), RecursiveIteratorIterator::SELF_FIRST), '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
			foreach($objects as $filename => $object){
				$this->zre->traceFile($filename ,function(){},function($context,&$storage) use ($func,$filename){
					$filename = str_replace(DIRECTORY_SEPARATOR,'/',$filename); //Standardize the path as unix path
					$this->$func($context,$storage,$filename);
				});
			}
		}catch(Exception $e){
		}
	}
	public function registerHook($context,$type){
		if(!isset($this->_hooks[$context['functionArgs'][0]])){
			$this->_hooks[$context['functionArgs'][0]]=array();
		}
		$this->_hooks[$context['functionArgs'][0]][] = array(
			'hookFunction'=>$context['functionArgs'][1],
			'file'=>$context['calledFromFile'].':'.$context['calledFromLine'],
			'executionTime'=>$context['durationExclusive'],
			'hookType'=>$type
		);
	}
	public function registerFilter($context,$type){
		if(!preg_match('/plugins\/(.*?)\//',$context['calledFromFile'])){
			return; //we want only plugins apply_filters
		}
		if(!isset($this->_filters[$context['functionArgs'][0]])){
			$this->_filters[$context['functionArgs'][0]]=array();
		}
		$this->_filters[$context['functionArgs'][0]][] = array(
			'filterFunction'=>$context['functionArgs'][1],
			'file'=>$context['calledFromFile'].':'.$context['calledFromLine'],
			'executionTime'=>$context['durationExclusive'],
			'arguments'=>$context['functionArgs'],
			'filterType'=>$type
		);
	}
	public function initHookCatcher(){
		$this->zre->traceFunction('add_action',function(){}, function($context) { 
			$this->registerHook($context,'add_action');
		});
		$this->zre->traceFunction('add_filter',function(){}, function($context) { 
			$this->registerHook($context,'add_filter');
		});
		/*$this->zre->traceFunction('apply_filters',function(){}, function($context) { 
			$this->registerFilter($context,'apply_filters');
		});*/
	}
}

$zre = new ZRayExtension('wordpress');

$zrayWordpress = new Wordpress($zre);

$zre->setMetadata(array(
	'logo' => __DIR__ . DIRECTORY_SEPARATOR . 'logo.png',
));

$zre->setEnabledAfter('wp_initial_constants');

$zre->traceFunction('wp', function() use ($zre,$zrayWordpress){ 
	$zrayWordpress->initProfiler('plugins');
	$zrayWordpress->initProfiler('mu-plugins');
	$zrayWordpress->initProfiler('themes');

 }, function(){});

$zre->traceFunction('wp_initial_constants', function() use ($zre,$zrayWordpress){ 
	$zrayWordpress->initHookCatcher();
 }, function(){});
 
$zre->traceFunction('wp_cache_close', function(){}, array($zrayWordpress, 'wpRunExit'));
