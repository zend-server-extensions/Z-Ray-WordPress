<?php


class Wordpress {
	private $zre;
	private $_profilePlugins = array();
	private $_profileThemes = array();
	private $_hooks = array();
	private $_filters = array();
	private $_cache_hits = array();
	private $_cache_misses = array();
	private $_cache_pie_size_statistics = array();
	
	public function __construct(&$zre){
		$this->zre = $zre;
	}
	
	public function wpCacheGetExit($context, &$storage) {
	    
        $group = $context['locals']['group'];
        $key = $context["functionArgs"][0];
        
	    if ($context['locals']["found"]) {
	        if (isset($this->_cache_hits[$group]) && isset($this->_cache_hits[$group][$key])) {
	           $this->_cache_hits[$group][$key]++;
	        } else {
	           $this->_cache_hits[$group][$key] = 1;
	        }
	   } else {
	       if (isset($this->_cache_misses[$group]) && isset($this->_cache_misses[$group][$key])) {
	           $this->_cache_misses[$group][$key]++;
	       } else {
	           $this->_cache_misses[$group][$key] = 1;
	       }
	   }
	}
	
	public function startMeasureTime(){
		$this->_startTime=microtime(true);
	}
	
	private function stopMeasureTime(){
		$this->_wpRunTime=(microtime(true)-$this->_startTime)*1000;
	}
	
	public function wpRunExit($context, &$storage){
		$this->stopMeasureTime();
		global $wp_object_cache,$wp_version;
		
	    $this->storeCacheObjects($wp_object_cache, $storage);
		$this->storeHitsStatistics($wp_object_cache, $storage);
		$this->storeCachePieStatistics($storage);
		
		$schedules=array();
		foreach(wp_get_schedules() as $key => $schedule){
			$schedules[$key]=$schedule['display'];
		}
		
		//Crons
		$doing_cron=get_transient( 'doing_cron' );
		if(is_array(_get_cron_array())){
			foreach( _get_cron_array() as $time=>$crons ){
				foreach($crons as $name=>$cron){
					foreach($cron as $subcron){
						$storage['crons'][] = array(
							'hook'=>$name,
							'schedule'=>empty($schedules[$subcron['schedule']]) ? $subcron['schedule'] : $schedules[$subcron['schedule']],
							'nextExecution'=>human_time_diff( $time ) . (time() > $time  ? ' ago' : ''),
							'arguments'=>count($subcron['args'])>0 ? print_r($subcron['args'],true) : ''
						);
					}
				}
			}
		}
		
		
		
		//Plugins List
		$this->plugins=array();
		try{
			if ( ! function_exists( 'get_plugins' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			$apl=get_option('active_plugins');
			$plugins=get_plugins();
			$state_plugins=array();
			if(is_array($apl) && count($plugins)>0){
				foreach ($apl as $p){           
					if(isset($plugins[$p])){
						 array_push($state_plugins, $p);
					}           
				}
			}
			//Multisite plugins
			$mupl=get_mu_plugins();
			if(is_array($mupl) && count($plugins)>0){
				foreach ($mupl as $p => $v){
					$plugins[$p]=$v;
					array_push($state_plugins, $p);
				}
			}
			
			$swplugs=get_site_option('active_sitewide_plugins');
			if(is_array($swplugs) && count($plugins)>0){
				foreach ($swplugs as $p => $v){           
					if(isset($plugins[$p])){
						 array_push($state_plugins, $p);
					}
				}
			}
			if(count($plugins)>0){
				foreach($plugins as $p=>$plugin){
					$state='Inactive';
					if(in_array($p,$state_plugins)){
						$state='Active'; 
					}
					$this->plugins[] = array('name'=>$plugin['Name'],'version'=>$plugin['Version'],'state'=>$state,'path'=>$p,'loadtime'=>'0');
				}
			}
		}catch(Exception $e){
		}
		$pluginsTime=0;
		if (count($this->_profilePlugins)>0) {
			foreach($this->_profilePlugins as $name => $time){
				$found=false;
				$pluginsTime+=$time;
				foreach($this->plugins as $key => $plugin){
					if(strpos($plugin['path'] . DIRECTORY_SEPARATOR,$name)!==false){
						$this->plugins[$key]['loadtime']=$time;
						$found=true;
					}
				}
				if(!$found){
					$this->plugins[]=array('name'=>$name,'version'=>'?','state'=>'On','loadtime'=>$time);
				}
			}
		}
		$storage['plugins']=$this->plugins;
		// Store Plugins Stats
		$pluginsOtherChart=0;
		$pluginsArr=array();
		$others=array();
		if($pluginsTime>0){
			foreach($this->plugins as $plugin){
				if($plugin['loadtime']>=$pluginsTime*0.15){
					$pluginsArr[]=$plugin;
				}else{
					$others[]=$plugin;
					$pluginsOtherChart += $plugin['loadtime'];
				}
			}
		}
		
		$count=3;
		if(count($pluginsArr)<$count&&count($others)>0){
			usort($others, function($a, $b){
				return strcmp($b['loadtime'], $a['loadtime']);
			});
			if(count($others)<$count){
				$count=count($others);
			}
			foreach(array_slice($others, 0, $count) as $item){
				$pluginsArr[] = $item;
				$pluginsOtherChart-=$item['loadtime'];
			}
		}
		
		$storage['pluginsStats'] = $pluginsArr;
		
		if($pluginsOtherChart>0){
			$storage['pluginsStats'][]=array('name'=>'Others','loadtime'=>$pluginsOtherChart);
		}
		
		$incId=0;
		//Hooks List
		$hookers=array();
		$core_hookers=array();
		if(count($this->_hooks)>0){
			foreach($this->_hooks as $hookName => $hook){
				foreach($hook as $hooker){
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
					$filename=explode(DIRECTORY_SEPARATOR,$hooker['file']);
					if(!$hooker['hookCore']){
						$hookers[]=array(
							'id'=>++$incId,
							'function'=>$hookKey,
							'file'=>$hooker['file'],
							'line'=>$hooker['line'],
							'filename'=>end($filename),
							'hookName'=>$hookName,
							'hookType'=>$hooker['hookType'],
							'executionTime'=>$hooker['executionTime'],
							'hookSource'=>$hooker['hookSource'],
							'priority'=>$hooker['priority'],
							'hookCore'=>$hooker['hookCore']
						);
					}else{
						$core_hookers[]=array(
							'id'=>++$incId,
							'function'=>$hookKey,
							'file'=>$hooker['file'],
							'line'=>$hooker['line'],
							'filename'=>end($filename),
							'hookName'=>$hookName,
							'hookType'=>$hooker['hookType'],
							'executionTime'=>$hooker['executionTime'],
							'hookSource'=>$hooker['hookSource'],
							'priority'=>$hooker['priority'],
							'hookCore'=>$hooker['hookCore']
						);
					}
				}
			}
		}
		$storage['hooks']=$hookers;
		$storage['core_hooks']=$core_hookers;
		
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
		
		//Theme Functions
		if(isset($this->_themeFuncs)){
			$theme_root_array = explode(DIRECTORY_SEPARATOR,realpath(get_template_directory()));
			$theme_dir_name = end($theme_root_array);
			$storage['theme'][]=array('data'=>$this->_themeFuncs,
										'theme'=>$theme_dir_name);
		}
		
		//General Info
		$storage['generalInfo'][] = array('name'=>'Wordpress Version','value'=>$wp_version);
		$storage['generalInfo'][] = array('name'=>'Debug Mode (WP_DEBUG)','value'=>WP_DEBUG ? 'On' : 'Off');
		$storage['generalInfo'][] = array('name'=>'Debug Log (WP_DEBUG_LOG)','value'=>WP_DEBUG_LOG ? 'On' : 'Off');
		$storage['generalInfo'][] = array('name'=>'Script Debug (SCRIPT_DEBUG)','value'=>SCRIPT_DEBUG ? 'On' : 'Off');
		$storage['generalInfo'][] = array('name'=>'Template','value'=>get_template());
		$storage['generalInfo'][] = array('name'=>'Doing Crons','value'=>$doing_cron ? 'Yes' : 'No');
		$storage['generalInfo'][] = array('name'=>'Plugins Count','value'=>count($storage['plugins']));
		if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES ) {
		    $storage['generalInfo'][] = array('name'=>'Save Queries (SAVEQUERIES)','value'=>SAVEQUERIES ? 'On' : 'Off');
		}
		
		//Dashboard
		$chart = array();
		$chart[] = array('name'=>'Plugins','loadtime'=>$pluginsTime);
		if(isset($this->_themeTime)){
			$chart[] = array('name'=>'Theme','loadtime'=>$this->_themeTime);
		}
		$storage['dashboard'][] = array(
										'chart'=>$chart
									);
		//WP_Query
		if($this->loadWPQueryPane()){
			$storage['wp_query'][]=$this->_wpquery;
		}
	}
	public function loadWPQueryPane(){
		global $wp_query;
		$this->_wpquery=array();
		if(!empty($wp_query->query)){
			$this->_wpquery['Query']=http_build_query( $wp_query->query );
		}
		if(!empty($wp_query->request)){
			$this->_wpquery['Request']=$wp_query->request;
		}
		if(!empty(get_queried_object())){
			$this->_wpquery['Object']=get_queried_object();
		
		}
		$this->_wpquery['Object ID']=get_queried_object_id();
		if(count($this->_wpquery)==0){
			return false;
		}
		return true;
	}
	public function pluginsFuncEnd($context,&$storage,$filename){
		if(preg_match('/'.preg_quote($this->plugins_dir_name,'/').'\/(.*?)\//',$filename,$match)||preg_match('/'.preg_quote($this->muplugins_dir_name,'/').'\/(.*?)\//',$filename,$match)){
			$plugin=$match[1];
			if(!isset($this->_profilePlugins[$plugin])){
				$this->_profilePlugins[$plugin]=0;
			}
			$this->_profilePlugins[$plugin]+=number_format($context['durationExclusive']/1000,2)*1;
		}
	}
	public function themesFuncEnd($context,&$storage,$filename){
	    $template_directory=realpath(get_template_directory());
		$theme_root_array = explode(DIRECTORY_SEPARATOR,$template_directory);
		$theme_dir_name = end($theme_root_array);
		if(!isset($this->_themeFuncs)){
			$this->_themeFuncs=array();
		}
		if(!isset($this->_themeTime)){
			$this->_themeTime=0;
		}
		if(preg_match('/'.preg_quote($theme_dir_name, '/').'\/(.*?)\//',$filename,$match)){
			$this->_themeTime+=$context['durationExclusive'];
			$this->_themeFuncs[]=array(
				'name'=>$context['functionName'],
				'time'=>$context['durationExclusive'],
				'file'=>$filename,
				'filename'=>str_replace($template_directory,'',$theme_dir_name. realpath($filename)),
				'line'=>$context['calledFromLine']
			);
		}
	}
	public function initProfiler($type='plugins'){
		$plugin_dir_array = explode('\\',realpath(WP_PLUGIN_DIR));
		$this->plugins_dir_name = array_pop($plugin_dir_array);
		$muplugins_dir_array = explode('\\',realpath(WPMU_PLUGIN_DIR));
		$this->muplugins_dir_name = array_pop($muplugins_dir_array);
		switch($type){
			case 'themes':
				$func='themesFuncEnd';
				$path = realpath(get_template_directory());
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
		$type=str_replace('add_','',$type);
		$type=ucfirst($type);
		$hookCore=false;
		if(defined('WP_PLUGIN_DIR')&&strpos($context['calledFromFile'],realpath(WP_PLUGIN_DIR))!==false){
			$matches=explode(DIRECTORY_SEPARATOR,str_replace(realpath(WP_PLUGIN_DIR),'',$context['calledFromFile']));
			$hookSource=$matches[1];
		}elseif(defined('WPMU_PLUGIN_DIR')&&strpos($context['calledFromFile'],realpath(WPMU_PLUGIN_DIR))!==false){
			$matches=explode(DIRECTORY_SEPARATOR,str_replace(realpath(WP_PLUGIN_DIR),'',$context['calledFromFile']));
			$hookSource=$matches[1];
		}elseif(function_exists('get_theme_root')&&strpos($context['calledFromFile'],realpath(get_theme_root())!==false)){
			$matches=explode(DIRECTORY_SEPARATOR,str_replace(realpath(WP_PLUGIN_DIR),'',$context['calledFromFile']));
			$hookSource=$matches[1];
		}else{
			$hookSource='Core';
			$type.=' (Core)';
			$hookCore=true;
		}
		
		if(!isset($this->_hooks[$context['functionArgs'][0]])){
			$this->_hooks[$context['functionArgs'][0]]=array();
		}
		$this->_hooks[$context['functionArgs'][0]][] = array(
			'hookFunction'=>$context['functionArgs'][1],
			'file'=>$context['calledFromFile'],
			'line'=>$context['calledFromLine'],
			'executionTime'=>$context['durationExclusive'],
			'hookSource'=>$hookSource,
			'hookType'=>$type,
			'hookCore'=>$hookCore,
			'priority'=>isset($context['functionArgs'][2]) ? $context['functionArgs'][2] : '10',
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
	
	private function storeCacheObjects($wp_object_cache, &$storage) {
	     
	    $data_array=array();
	    foreach ($wp_object_cache->cache as $group => $group_items) {
	        $group_size = 0;
	        $group_hits = 0;
	        $group_item_array=array();		
	        foreach($group_items as $group_item_name => $group_item) {
	    
	            $item_size =  number_format( strlen( serialize( $group_item ) ) / 1024, 2 );
	            $group_size += $item_size;
	    
	            $hits = 0;
	            if (isset($this->_cache_hits[$group][$group_item_name])) {
	                $hits = $this->_cache_hits[$group][$group_item_name];
	                $group_hits += $hits;
	            }
				if(count($group_items)==1){
					$group_item_array = $hits;
				}else{
					$group_item_array[] = array('name' => $group_item_name, 'size' => $item_size , 'hits' => $hits);
				}
	            
	        }
			$this->_cache_pie_size_statistics[$group] = floatval($group_size);
	        // we lose temprorally $group_hits
	        $data_array[] = array('name' => $group, 'size' => $group_size , 'hits' => $group_item_array);
	    }
	    $storage['cacheObjects'] = $data_array;
	}
	
	private function storeHitsStatistics($wp_object_cache, &$storage) {
	    $total = 0;
	    foreach ($this->_cache_pie_size_statistics as $count) {
	        $total += $count;
	    }
	    // General hits/misses data
	    $storage['cacheStats'] = array('hits' => $wp_object_cache->cache_hits, 'misses' => $wp_object_cache->cache_misses, 'totalSize' => $total);
	}
	
	private function storeCachePieStatistics(&$storage) {
	    $total = 0;
	    foreach ($this->_cache_pie_size_statistics as $count) {
	        $total += $count;
	    }
	    $percent15 = $total * 0.15;
	    $cachePieStats = array();
	    $otherCount = 0;
		$others = array();
	    foreach ($this->_cache_pie_size_statistics as $name => $value) {
	        if ($value >= $percent15) {
	           $cachePieStats[] = array('name' => $name, 'count' => $value);
	        } else {
				$others[] = array('name' => $name, 'count' => $value);
	            $otherCount += $value;
	        }
	    }
		$count=3;
		if(count($cachePieStats)<$count&&count($others)>0){
			usort($others, function($a, $b){
				return strcmp($b['count'], $a['count']);
			});
			if(count($others)<$count){
				$count=count($others);
			}
			foreach(array_slice($others, 0, $count) as $item){
				$cachePieStats[] = $item;
				$otherCount-=$item['count'];
			}
		}
	    if ($otherCount > 0) {
			
	       $cachePieStats[] = array('name' => 'Other', 'count' => $otherCount);
	    }
	    
	    $storage['cachePieStats'] = $cachePieStats;
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
	$zrayWordpress->startMeasureTime();
	$zrayWordpress->initHookCatcher();
 }, function(){});
 
// stores the cache hits/misses
$zre->traceFunction('wp_cache_get', function(){}, array($zrayWordpress, 'wpCacheGetExit'));

$zre->traceFunction('wp_cache_close', function(){}, array($zrayWordpress, 'wpRunExit'));

