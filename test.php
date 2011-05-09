<?php 
/**
 * ì‚é‚Æ‚µ‚½‚çpublish htmlƒ‚ƒWƒ…[ƒ‹
 */

/**
* Implementation of hook_help
*/
function publish_html_help($section) {
  switch ($section) {
    case 'admin':
      return t("HTML Publish lets you export your drupal site to static HTML.");
  }
}

/**
* Implementation of hook_menu
*/
function publish_html_menu() {
  $items = array();
  $items['admin/settings/publish_html'] = array(
    'title' => 'HTML Publish',
    'description' => 'Publish your drupal site to static html page',
    'page callback' => 'drupal_get_form',
    'page arguments' => array('publish_html_settings'),
    'access arguments' => array('access administration pages'),
    'type' => MENU_NORMAL_ITEM,
   );
  return $items;
}
/**
* Implementation of hook_settings
*/
function publish_html_settings() {  
  $form["publish_html"] = array(
    '#type' => 'radios',
    '#title' => t("Publish site to HTML?"),
    '#default_value' => 0,
    '#options' => array(0 => 'No',1 => 'Yes'), 
    '#required' => true,
  );
  $form['#submit'] = array('publish_html_settings_submit');
  return system_settings_form($form);  
}
/**
* Implementation of hook_settings_submit
*/
function publish_html_settings_submit($form_id, $form_values) {
	if($form_values["values"]["publish_html"] == 1) {
		$clean = variable_get('clean_url',0);
		//turn clean URLs off temporarily if they are on
		if($clean){
			variable_set('clean_url',0);
		}
		$root = substr($_SERVER['HTTP_REFERER'],0,strpos($_SERVER['HTTP_REFERER'],$_GET['q']));
		//drupal_set_message($root);
		//remove the ?q= if clean URLs are off
		if(strpos($root,'?q=') != 0){
			$root = substr($root,0,strpos($root,'?q='));
		}
		//create a folder publish_html to put the directory in
		$dir = file_create_path(file_directory_path() . '/publish_html');
		file_check_directory($dir, 1);
		$export_path = $dir . '/export' . time();
		file_check_directory(file_create_path($export_path),1);
		file_check_directory(file_create_path($export_path . '/' . file_directory_path()),1);
		file_check_directory(file_create_path($export_path . '/sites'),1);
		file_check_directory(file_create_path($export_path . '/modules'),1);
		file_check_directory(file_create_path($export_path . '/themes'),1);
		file_check_directory(file_create_path($export_path . '/misc'),1);
		
		$export_path = str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . $export_path;
		
		//run the copyr function, modified to work with zip archive; copy the files,themes,sites,and misc directories
		//_publish_html_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . file_directory_path(),$export_path . '/' . file_directory_path());
		_publish_html_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'sites',$export_path . '/sites');
		_publish_html_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'modules',$export_path . '/modules');
		_publish_html_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'themes',$export_path . '/themes');
		_publish_html_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'misc',$export_path . '/misc');

		//grab all the nodes in the system that are published and then build out a list of url's to rename in the rendered code.
		//similar to url rewrite and will need to take that into account eventually
		$result = db_query("SELECT nid FROM {node} WHERE status=1 ORDER BY nid DESC");
		$nids = array();
		while($node = db_fetch_array($result)){
			$url = url('node/' . $node['nid']);
			if(strpos(' ' . $url,'/?q=') != 0){
				$url = substr($url,4 + strpos($url,'/?q='));
			}
			if($url == 'node/' . $node['nid']){
				$nids['node/' . $node['nid']] = 'page' . $node['nid'] . '.html';
			}else{
				$tmp_url = $url;
				//this removes the fake extension if one exists
				$tmp_url = str_replace(".html","",$tmp_url);
				$tmp_url = str_replace(".htm","",$tmp_url);
				$tmp_url = str_replace(".shtml","",$tmp_url);
				$tmp_url = str_replace(".php","",$tmp_url);
				$tmp_url = str_replace(".asp","",$tmp_url);
				//this will remove everything that isn't a letter or number and replace it with a dash
				//this will allow custom url paths to still remain yet be translated correctly
				$tmp_url=preg_replace('/[^0-9a-z ]+/i', '-', $tmp_url);
				$tmp_url=preg_replace('/[^\w\d\s]+/i', '-', $tmp_url);
				
				$nids[$url] = $tmp_url . '.html';
				$nids['node/' . $node['nid']] = $tmp_url . '.html';

			}
		}
		//run through all the nodes and render pages to add to the zip file
		$result = db_query("SELECT nid FROM {node} WHERE status=1 ORDER BY nid DESC");
		while($node = db_fetch_array($result)){
			$drupal_site = drupal_http_request($root . "index.php?q=node/" . $node['nid']);
			//drupal_set_message($root . "index.php?q=node/" . $node['nid']);
			$data = $drupal_site->data;
			//strip out file paths that have the full server in them
			$data = str_replace($root . base_path(),"",$data);
			$data = str_replace($root,"",$data);
			
			//strip out just the node/ if it's left over and replace it with the correct form of the link so that they actually find each other
			foreach($nids as $key => $nidpath){
				//get rid of a base path if there is one
				if(base_path() != '/'){
					$data = str_replace(base_path(),'',$data);
				}
				//account for links back to home where they are just a backslash cause it's at the root
				$data = str_replace('index.php/?q=' . $key,$nidpath,$data);
				$data = str_replace('index.php?q=' . $key,$nidpath,$data);
				$data = str_replace('/?q=' . $key,$nidpath,$data);
				$data = str_replace('?q=' . $key,$nidpath,$data);
			}
			$data = str_replace('?q=','',$data);
			$data = str_replace('<a href="/"','<a href="index.html"',$data);
			$data = str_replace('<a href=""','<a href="index.html"',$data);
			$file = fopen($export_path . "/" . $nids['node/' . $node['nid']],"w");
			fwrite($file,$data);
			fclose($file);
		}
		
		$drupal_site = drupal_http_request($root . "index.php");
		$data = $drupal_site->data;
		//strip out file paths that have the full server in them
		//$data = str_replace($root . base_path(),"",$data);
		//$data = str_replace($root,"",$data);
		//strip out just the node/ if it's left over and replace it with the correct form of the link so that they actually find each other 
		foreach($nids as $key => $nidpath){
			if(base_path() != '/'){
				$data = str_replace(base_path(),'',$data);
			}
			//account for links back to home where they are just a backslash cause it's at the root
			$data = str_replace('index.php/?q=' . $key,$nidpath,$data);
			$data = str_replace('index.php?q=' . $key,$nidpath,$data);
			$data = str_replace('/?q=' . $key,$nidpath,$data);
			$data = str_replace('?q=' . $key,$nidpath,$data);
		}
		$data = str_replace('?q=','',$data);
		//try to account for links to nowhere because they should point Home
		$data = str_replace('<a href="/"','<a href="index.html"',$data);
		$data = str_replace('<a href=""','<a href="index.html"',$data);
		$file = fopen($export_path . "/index.html","w");
		fwrite($file,$data);
		fclose($file);
		//turn clean URLs back on if it was off temporarily
		if($clean){
			variable_set('clean_url',1);
		}
		//need to generate a list of modules and themes to copy as well as files directory except for publish_html folder
		drupal_set_message("If you don't see any errors the site was exported successfully! <a href='" . base_path() . substr($export_path,strpos($export_path,$dir)) . "/index.html' target='_blank'>Click</a> here to access the export.");
  	}
}

function _publish_html_copyr($source, $dest){
	// Simple copy for a file
	if (is_file($source)) {
		return copy($source, $dest);
	}
	// Make destination directory
	if (!is_dir($dest)) {
		mkdir($dest);
	}
 
	// Loop through the folder
	$dir = dir($source);
	while (false !== $entry = $dir->read()) {
		//if this is the files folder then skip the pointers, the publish_html directory (server == dead), and .htaccess files
		//if not then Skip pointers to folders, .DS_Store, *.php, and .htaccess
		if ($entry == '.' || $entry == '..' || $entry == 'README.txt' || $entry == 'LICENSE.txt' || $entry == '.DS_Store' || $entry == '.htaccess' || $entry == 'Thumbs.db' || strpos($entry,'.engine') != 0 || strpos($entry,'.php') != 0 || strpos($entry,'.inc') != 0 || strpos($entry,'.include') != 0 || strpos($entry,'.info') != 0 || strpos($entry,'.install') != 0 || strpos($entry,'.module') != 0){
			continue;
		}
		// Deep copy directories, ignore the publish_html ones
		if ($dest !== "$source/$entry" && strpos($source,'publish_html') == 0 ) {
			_publish_html_copyr("$source/$entry", "$dest/$entry");
		}
	}
	// Clean up
	$dir->close();
	return true;
}
