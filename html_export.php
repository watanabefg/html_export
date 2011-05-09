<?php 
//ELMS: HTML Export - Export your drupal site to HTML
//Copyright (C) 2008  The Pennsylvania State University
//
//Bryan Ollendyke
//bto108@psu.edu
//
//Keith D. Bailey
//kdb163@psu.edu
//
//12 Borland
//University Park, PA 16802
//
//This program is free software; you can redistribute it and/or modify
//it under the terms of the GNU General Public License as published by
//the Free Software Foundation; either version 2 of the License, or
//(at your option) any later version.
//
//This program is distributed in the hope that it will be useful,
//but WITHOUT ANY WARRANTY; without even the implied warranty of
//MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//GNU General Public License for more details.
//
//You should have received a copy of the GNU General Public License along
//with this program; if not, write to the Free Software Foundation, Inc.,
//51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.

/**
 * Implementation of hook_help
 */
function html_export_help($section) {
    switch ($section) {
    case 'admin':
        //return t("HTML Export lets you export your drupal site to static HTML.");
        return t("HTMLエクスポートはあなたのdrupalサイトを静的html出力します.");
    }
}
/*
 * Implementation of hook_cron
 */
function html_export_cron() {
    if( (time() - variable_get('html_export_last', time())) > variable_set('html_export_cron', 0) ){
        _html_export_export();
    }
}

/**
 * Implementation of hook_menu
 */
function html_export_menu() {
    $items = array();
    $items['admin/settings/html_export'] = array(
        'title' => 'HTML Export',
        'description' => 'Export your drupal site to static html page',
        'page callback' => 'drupal_get_form',
        'page arguments' => array('html_export_settings'),
        'access arguments' => array('access administration pages'),
        'type' => MENU_NORMAL_ITEM,
    );
    return $items;
}
/**
 * Implementation of hook_settings
 */
function html_export_settings() {  
    $form["html_export_now"] = array(
        '#type' => 'radios',
        '#title' => t("Publish site to HTML now?"),
        '#description' => t("This action could take a couple of minutes. Please wait untill the page is loaded."),
        '#default_value' => 0,
        '#options' => array(0 => t('No'),1 => t('Yes')), 
        '#required' => true,
    );
    $form["html_export_folder"] = array(
        '#type' => 'textfield',
        '#title' => t("Folder name"),
        '#description' => t("Name of the export folder."),
        '#default_value' => variable_get('html_export_folder', 'export'),
        '#required' => false,
    );
    $form["html_export_timestamp"] = array(
        '#type' => 'radios',
        '#title' => t("Add timestamp to folder name"),
        '#default_value' => variable_get('html_export_timestamp', 0),
        '#options' => array(0 => t('No'),1 => t('Yes')), 
        '#required' => false,
    );
    $form["html_export_domain"] = array(
        '#type' => 'textfield',
        '#title' => t("Domain name"),
        '#description' => t("Enter the domain used to visit the pages. Leave blank to use the current domain."),
        '#default_value' => variable_get('html_export_domain', ''),
        '#required' => false,
    );
    $form["html_export_pages"] = array(
        '#type' => 'textarea',
        '#title' => t("Export additional URL's"),
        '#description' => t("Enter one page per line as Drupal paths. Wildcards are not allowed. Nodes and views are automatically exported."),
        '#default_value' => variable_get('html_export_pages', ''),
        '#required' => false,
    );
    $form["html_export_replace"] = array(
        '#type' => 'textarea',
        '#title' => t("Replace strings in output"),
        '#description' => t("Enter one replacement per line as < string >|< replacement >."),
        '#default_value' => variable_get('html_export_replace', ''),
        '#required' => false,
    );
    $form["html_export_cron"] = array(
        '#type' => 'select',
        '#title' => t("Export interval"),
        '#default_value' => variable_get('html_export_cron', 0),
        '#options' => array(0 => t('Every cron run'),3600 => t('Hourly'),86400 => t('Daily'),604800 => t('Weekly')), 
        '#required' => false,
    );
    $form['#submit'] = array('html_export_settings_submit');
    return system_settings_form($form);  
}

/*
 * Implementation of hook_settings_validate
 */
function html_export_settings_validate($form, &$form_state) {
    // Set pages in var already, because of export running directly
    variable_set('html_export_replace', $form_state["values"]["html_export_replace"]);
    variable_set('html_export_pages', $form_state["values"]["html_export_pages"]);
    variable_set('html_export_domain', $form_state["values"]["html_export_domain"]);
}


/**
 * Implementation of hook_settings_submit
 */
function html_export_settings_submit($form_id, $form_values) {
    if($form_values["values"]["html_export_now"] == 1) {
        _html_export_export();

    }
}

function _html_export_copyr($source, $dest){
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
        //if this is the files folder then skip the pointers, the html_export directory (server == dead), and .htaccess files
        //if not then Skip pointers to folders, .DS_Store, *.php, and .htaccess
        if ($entry == '.' || $entry == '..' || $entry == 'README.txt' || $entry == 'LICENSE.txt' || $entry == '.DS_Store' || $entry == '.htaccess' || $entry == 'Thumbs.db' || strpos($entry,'.engine') != 0 || strpos($entry,'.php') != 0 || strpos($entry,'.inc') != 0 || strpos($entry,'.include') != 0 || strpos($entry,'.info') != 0 || strpos($entry,'.install') != 0 || strpos($entry,'.module') != 0){
            continue;
        }
        // Deep copy directories, ignore the html_export ones
        if ($dest !== "$source/$entry" && strpos($source,'html_export') == 0 ) {
            _html_export_copyr("$source/$entry", "$dest/$entry");
        }
    }
    // Clean up
    $dir->close();
    return true;
}




/**
 * Export all nodes / views etc
 */
function _html_export_export(){
    set_time_limit(500000);
    $clean = variable_get('clean_url',0);
    //turn clean URLs off temporarily if they are on
    if($clean){
        variable_set('clean_url',0);
    }
    $root = _html_export_root_domain();
    //create a folder html_export to put the directory in
    $dir = file_create_path(file_directory_path() . '/html_export');
    file_check_directory($dir, 1);
    $export_path = $dir . '/' . variable_get('html_export_folder', '');
    if(variable_get('html_export_timestamp', 0)) $export_path .= time();

    file_check_directory(file_create_path($export_path),1);
    file_check_directory(file_create_path($export_path . '/' . file_directory_path()),1);
    file_check_directory(file_create_path($export_path . '/sites'),1);
    file_check_directory(file_create_path($export_path . '/modules'),1);
    file_check_directory(file_create_path($export_path . '/themes'),1);
    file_check_directory(file_create_path($export_path . '/misc'),1);

    // index.phpはsites/default/files/html_export/html_export**********/に展開される
    $export_path = str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . $export_path;

    //run the copyr function, modified to work with zip archive; copy the files,themes,sites,and misc directories
    //_html_export_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . file_directory_path(),$export_path . '/' . file_directory_path());
    // drupalのサイト内容が先にコピーされる
    _html_export_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'sites',$export_path . '/sites');
    _html_export_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'modules',$export_path . '/modules');
    _html_export_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'themes',$export_path . '/themes');
    _html_export_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'misc',$export_path . '/misc');

    //grab all the nodes in the system that are published and then build out a list of url's to rename in the rendered code.
    //similar to url rewrite and will need to take that into account eventually
    // ノードがコピーされる
    $result_node = db_query("SELECT nid FROM {node} WHERE status=1 ORDER BY nid DESC");
    $nids = array();
    while($node = db_fetch_array($result_node)){
        $url = url('node/' . $node['nid']);
        if(strpos(' ' . $url,'/?q=') != 0){
            $url = substr($url,4 + strpos($url,'/?q='));
        }
        if($url == 'node/' . $node['nid']){
            $nids['node/' . $node['nid']] = 'page' . $node['nid'] . '.html';
        }else{
            $tmp_url = $url;
            //this removes the fake extension if one exists
            $tmp_url = _html_export_remove_ext($tmp_url);
            // Add url to array
            $nids[$url] = $tmp_url . '.html';
            $nids['node/' . $node['nid']] = $tmp_url . '.html';
        }
    }

    // 今回はなし
    if (module_exists('views')) {
        //grab all the views in the system that are published and then build out a list of url's to rename in the rendered code.
        //similar to url rewrite and will need to take that into account eventually
        $result = db_query("SELECT * FROM views_display WHERE display_plugin = 'page'");
        while($view = db_fetch_array($result)){
            $view_vars = unserialize($view['display_options']);
            if(strpos($view_vars['path'],'admin/') === false && strpos($view_vars['path'],'front') === false){
                $url = url($view_vars['path']);
                if(strpos(' ' . $url,'/?q=') != 0){
                    $url = substr($url,4 + strpos($url,'/?q='));
                }
                $tmp_url = $url;
                //this removes the fake extension if one exists
                $tmp_url = _html_export_remove_ext($tmp_url);

                // Add url to array
                $nids[$url] = $tmp_url . '.html';
            }
        }
    }

    // Export term pages
    // 追加
    $result = db_query("SELECT * FROM {term_data} ORDER BY tid");
    while ($term = db_fetch_array($result)){
        _html_export_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . $term['name'],$export_path . '/misc');
        $url = url('taxonomy/term/'. $term['tid']);
        if (strpos(' '. $url, '/?q=') != 0){
            $url = substr($url, 4+strpos($url, '/?q='));
        }
        if ($url == 'taxonomy/term/'. $term['tid']){
            $nids['taxonomy/term/'. $term['tid']] = 'term'. $term['tid']. '.html';
        }
    }

    // Export custom pages
    $pages = explode(',',_html_export_make_list(variable_get('html_export_pages', '')));
    foreach ($pages as $page) {
        $nids[$page] = $page . '.html';
    }

    //run through all the nodes and render pages to add to the zip file
    $result = db_query("SELECT nid FROM {node} WHERE status=1 ORDER BY nid DESC");
    while($node = db_fetch_array($result)){
        $drupal_site = drupal_http_request($root . "index.php?q=node/" . $node['nid']);
        //drupal_set_message($root . "index.php?q=node/" . $node['nid']);
        $data = $drupal_site->data;
        //Rewrite all links
        //TODO:_html_export_rewrite_urlsはバグがあるので修正する必要有。blogとtaxonomy/term関連
        $data = _html_export_rewrite_urls($data,$nids);
        // Write HTML to file
        $file = fopen($export_path . "/" . $nids['node/' . $node['nid']],"w");
        fwrite($file,$data);
        fclose($file);
    }

    // 今回はなし
    if (module_exists('views')) {
        //run through all the views and render pages to add to the zip file
        $result = db_query("SELECT * FROM views_display WHERE display_plugin = 'page'");
        while($view = db_fetch_array($result)){
            $view_vars = unserialize($view['display_options']);
            if(strpos($view_vars['path'],'admin/') === false && strpos($view_vars['path'],'front') === false){
                $drupal_site = drupal_http_request($root . $view_vars['path']);
                //drupal_set_message($root . "index.php?q=node/" . $node['nid']);
                $data = $drupal_site->data;
                //Rewrite all links
                $data = _html_export_rewrite_urls($data,$nids);
                // Write HTML to file
                $file = fopen($export_path . "/" . $nids[$view_vars['path']],"w");
                fwrite($file,$data);
                fclose($file);
            }
        }
    }

    // Export term pages
    // 今回追加
    $result = db_query("SELECT tid FROM {term_data} ORDER BY tid");
    while ($term = db_fetch_array($result)){
        $url = url('taxonomy/term/'. $term['tid']);
        $drupal_site = drupal_http_request($root . "index.php" . $url);
        $data = $drupal_site->data;
        // Rewrite all links
        $data = _html_export_rewrite_urls($data,$nids);
        // Write HTML to file
        $file = fopen($export_path . "/" . $nids['taxonomy/term/'. $term['tid']],"w");
        fwrite($file,$data);
        fclose($file);
    }

    // Export custom pages
    $pages = explode(',',_html_export_make_list(variable_get('html_export_pages', '')));
    foreach ($pages as $page) {
        $drupal_site = drupal_http_request($root . $page);
        $data = $drupal_site->data;
        //Rewrite all links
        $data = _html_export_rewrite_urls($data,$nids);
        // Write HTML to file
        $file = fopen($export_path . "/" . $page . ".html","w");
        fwrite($file,$data);
        fclose($file);
    }

    // Export homepage
    $drupal_site = drupal_http_request($root . "index.php");
    $data = $drupal_site->data;
    //Rewrite all links
    // TODO:これを直す必要がある
    $data = _html_export_rewrite_urls($data,$nids);
    // Write HTML to file
    $file = fopen($export_path . "/index.html","w");
    fwrite($file,$data);
    fclose($file);

    //turn clean URLs back on if it was off temporarily
    if($clean){
        variable_set('clean_url',1);
    }
    // Save time last run
    variable_set('html_export_last', time());
    //need to generate a list of modules and themes to copy as well as files directory except for html_export folder
    drupal_set_message("If you don't see any errors the site was exported successfully! <a href='" . base_path() . substr($export_path,strpos($export_path,$dir)) . "/index.html' target='_blank'>Click</a> here to access the export.");

    return true;
}

/**
 * Helper function to rewrite URLs.
 */
function _html_export_remove_ext($url){
    //this removes the fake extension if one exists
    $tmp_url = str_replace(".html","",$url);
    $tmp_url = str_replace(".htm","",$tmp_url);
    $tmp_url = str_replace(".shtml","",$tmp_url);
    $tmp_url = str_replace(".php","",$tmp_url);
    $tmp_url = str_replace(".asp","",$tmp_url);
    //this will remove everything that isn't a letter or number and replace it with a dash
    //this will allow custom url paths to still remain yet be translated correctly
    $tmp_url=preg_replace('/[^0-9a-z ]+/i', '-', $tmp_url);
    $tmp_url=preg_replace('/[^\w\d\s]+/i', '-', $tmp_url);

    return $tmp_url;
}

/**
 * Helper function to rewrite URLs to internal links.
 */
function _html_export_rewrite_urls($html,$nids){
    $root = _html_export_root_domain(false);
    //strip out file paths that have the full server in them
    $html = str_replace($root . base_path(),"",$html);
    $html = str_replace($root,"",$html);
    $html = str_replace(_html_export_urlencode($root . base_path()),"",$html);
    $html = str_replace(_html_export_urlencode($root),"",$html);

    // Custom replacements
    $replacements = explode(',',_html_export_make_list(variable_get('html_export_replace', '')));
    foreach ($replacements as $replacement) {
        $keys = explode('|',$replacement);
        $html = str_replace($keys[0],$keys[1],$html);
    }

    //strip out just the node/ if it's left over and replace it with the correct form of the link so that they actually find each other
    foreach($nids as $key => $nidpath){
        //get rid of a base path if there is one
        if(base_path() != '/'){
            $html = str_replace(base_path(),'',$html);
        }
        //account for links back to home where they are just a backslash cause it's at the root
        $html = str_replace('index.php/?q=' . $key,$nidpath,$html);
        $html = str_replace('index.php?q=' . $key,$nidpath,$html);
        $html = str_replace('/?q=' . $key,$nidpath,$html);
        $html = str_replace('?q=' . $key,$nidpath,$html);
    }
    $html = str_replace('?q=','',$html);
    $html = str_replace('<a href="/"','<a href="index.html"',$html);
    $html = str_replace('href="/','href="',$html);
    $html = str_replace('src="/','src="',$html);
    $html = str_replace('<a href=""','<a href="index.html"',$html);

    return $html;
}

/**
 * Helper function to Convert a list to a comma seperated list.
 */
function _html_export_make_list($text) {
    $cslist = str_replace(array("\r\n", "\n", "\r"), '', $text);
    $cslist = str_replace(' ', '', $cslist);
    $cslist = str_replace(';', ',', $cslist);
    if(substr($cslist, -1) == ',') $cslist = substr_replace($cslist,'',-1);

    return $cslist;
}

/**
 * Helper function to Convert a list to a comma seperated list.
 */
function _html_export_root_domain($visit = true) {
    //TODO:????H
    //$root = $_SERVER['HTTP_HOST'];
    $root = substr($_SERVER['HTTP_REFERER'],0,strpos($_SERVER['HTTP_REFERER'],$_GET['q']));
    if(strpos($root,'?q=') != 0){
        $root = substr($root,0,strpos($root,'?q='));
    }

/*
  if(variable_get('html_export_domain', ''))  $root = variable_get('html_export_domain', '');
  //drupal_set_message($root);
  //remove the ?q= if clean URLs are off
  if(strpos($root,'?q=') != 0){
    $root = substr($root,0,strpos($root,'?q='));
  }
  if(!$visit){
    $aDomain = explode('@',$root);
    $root = $aDomain[1];
  }
  if(substr($root, 0, 7)!='http://') $root= 'http://'.$root;
  if(substr($root, -1) != "/") $root = $root.'/';
 */
/*
    if($root=='localhost'){
        $root='http://localhost/drupal/';
    } 
*/
    //drupal_set_message($root);
    return $root;
}

/**
 * Helper function to Convert a list to a comma seperated list.
 */
function _html_export_urlencode($string) {
    $string = urlencode($string);
    $string = str_replace('%3A', ':', $string);
    return $string;
}