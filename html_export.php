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

define("_TIME_LIMIT", 600000);
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
/**
 * hook_action_info()の実装
 */
function html_export_action_info(){
  $info['html_export_action'] = array(
    'type' => 'system', 
    'description' => t('記事を再構築する'), 
    'configurable' => FALSE, 
    'hooks' => array(
      'nodeapi' => array('view', 'insert', 'update', 'delete'), 
      'comment' => array('view', 'insert', 'update', 'delete'), 
      'user' => array('view', 'insert', 'update', 'delete', 'login'), 
      'taxonomy' => array('view', 'insert', 'update', 'delete'), 
    ), 
  );

  return $info;
}
/**
 * Drupalアクション
 */
function html_export_action(){
  _html_export_export(false);
}

/*
 * Implementation of hook_cron
 */
function html_export_cron() {
  if ( (time() - variable_get('html_export_last', time())) > variable_set('html_export_cron', 0) ){
    _html_export_export(true);
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
  if ($form_values["values"]["html_export_now"] == 1) {
    _html_export_export(true);
  }
}

function _html_export_copyr($source, $dest){
  // Simple copy for a file
  if (is_file($source)) {
    return copy($source, $dest);
  }
  // Make destination directory
  _make_dest_dir($dest);
  // Loop through the folder
  $dir = dir($source);
  while (false !== $entry = $dir->read()) {
    if (_skip_files($entry)){
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
 * ディレクトリを作成する
 */
function _make_dest_dir($dest){
  if (!is_dir($dest)) {
    mkdir($dest);
  }
}

/**
 * _skip_files
 */
function _skip_files($entry){
  //if this is the files folder then skip the pointers, the html_export directory (server == dead), and .htaccess files
  //if not then Skip pointers to folders, .DS_Store, *.php, and .htaccess
  switch ((string) $entry){
    case '.'  :
    case '..' :
    case 'README.txt' :
    case 'LICENSE.txt' :
    case '.DS_Store' :
    case '.htaccess' :
    case 'Thumbs.db' :
      return true;
      break;
  }
  $skip_exts = array('.engine',
    '.php',
    '.inc',
    '.include',
    '.info',
    '.install',
    '.module');
  foreach ($skip_exts as $skip_ext){ 
    if (strpos($entry, $skip_ext) != 0){
      return true;
    }
  }
  return false;
}

/**
 * Export all nodes / views etc
 */
function _html_export_export($all){
  set_time_limit(_TIME_LIMIT);

  //クリーンURLの設定を保存
  $clean = variable_get('clean_url',0);
  _clean_url_off($clean);

  $root = _html_export_root_domain();

  // 既存のコンテンツを保存した場合
  if (!$all){
    $edit_node = _trim_url_query($_SERVER['REQUEST_URI']); // ex.) node/1
    $edit_node_id = substr($edit_node, 5); // 1以降が取れる
  }

  //create a folder html_export to put the directory in
  $dir = file_create_path(file_directory_path() . '/html_export');
  file_check_directory($dir, 1);

  $export_path = $dir. '/'. variable_get('html_export_folder', 'html_export');
  if (variable_get('html_export_timestamp', 0)) $export_path .= time();

  //check&mkdir drupal setting directories
  _file_check_drupal_directories($export_path);

  // index.phpはsites/default/files/html_export/html_export**********/に展開される
  $export_path = str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . $export_path;

  //run the copyr function, modified to work with zip archive; copy the files,themes,sites,and misc directories
  _html_export_copy_drupal_directories($export_path);

  $nids = array();
  //grab all the nodes in the system that are published and then build out a list of url's to rename in the rendered code.
  //similar to url rewrite and will need to take that into account eventually
  // ノードを登録する
  $nids = _set_nids_for_nodes($nids);
  // フロントページ用にnodepagenationを登録する
  $nodecount = _get_index_pages_having_pagenation();
  $nids = _set_nids_for_nodepagenation($nids, $nodecount, $all);
  // views用にnodeを登録する
  $nids = _set_nids_for_views($nids, $all);

  // pagenationが必要なtidを求める
  $tids_need_pagenation = _get_tids_having_pagenation();
  $tids_page = array();
  // termを登録する
  $result = db_query("SELECT * FROM {term_data} ORDER BY tid");
  while ($term = db_fetch_array($result)){
    // termを元にディレクトリを作成する
    file_check_directory(file_create_path($export_path . '/term'. $term['tid']),1);

    if (in_array($term['tid'], $tids_need_pagenation)){
      // pagenationが必要であれば
      $result_pages = db_query("SELECT truncate(count(*)/". variable_get('default_nodes_main', 10). ", 0) num FROM (".
        "SELECT DISTINCT nid, tid FROM {term_node}".
        ") AS temp WHERE temp.tid = ". $term['tid']);
      // 必要なページ数
      $pagenumber = db_fetch_array($result_pages);
      $tids_page[$term['tid']] = $pagenumber['num'];
      // ex.) tids_page = array('2' => 2, '4' => 1) タームID => ページ数
      $nids = _set_nids_for_termpagenation($nids, $term['tid'], $pagenumber['num']);
    }
    $nids = _set_nids_for_term($nids, $term['tid']);
  }

  // blogを登録する
  $ret = _set_nids_for_blogs($export_path, $nids);
  $nids = $ret[0];
  $blogs_page = $ret[1]; // uid => maxpages

  // フロントページ(記事一覧)のrss.xmlの登録
  $nids = _set_nids_for_xml($nids);
  // カスタムページ用にnodeを登録する
  $nids = _set_nids_for_custom_pages($nids);

  //run through all the nodes and render pages to add to the zip file
  _export_nodes($root, $nids, $export_path, $edit_node_id);
  _export_views($root, $nids, $export_path);
  // Export term pages
  _export_terms($root, $tids_need_pagenation, $tids_page, $nids, $export_path, $edit_node_id);
  // Export blogs
  _export_blogs($root, $nids, $export_path, $blogs_page);
  /* Export custom pages */
  _export_custom_pages($root, $nids, $export_path);
  /* Export homepage */
  _export_front_page($root, $nodecount, $nids, $export_path);
  /* Export blog front page */
  _export_blogfront_page($root, $nids, $export_path, $blogs_page);

  //turn clean URLs back on if it was off temporarily
  _clean_url_on($clean);
  // Save time last run
  variable_set('html_export_last', time());

  //need to generate a list of modules and themes to copy as well as files directory except for html_export folder
  $success_message = "If you don't see any errors the site was exported successfully! <a href='".
                      base_path() . substr($export_path,strpos($export_path,$dir)) . 
                      "/index.html' target='_blank'>Click</a> here to access the export.";
  drupal_set_message($success_message);

  return true;
}

/**
 * turn clean url off
 */
function _clean_url_off($clean){
  if ($clean){
    variable_set('clean_url',0);
  }
}

/**
 * turn clean url on
 */
function _clean_url_on($clean){
  if ($clean){
    variable_set('clean_url',1);
  }
}

/**
 * _file_check_drupal_directories
 *
 * check&mkdir drupal setting directories
 */
function _file_check_drupal_directories($export_path){
  file_check_directory(file_create_path($export_path),1);
  file_check_directory(file_create_path($export_path . '/' . file_directory_path()),1);
  file_check_directory(file_create_path($export_path . '/../sites'),1);
  file_check_directory(file_create_path($export_path . '/../modules'),1);
  file_check_directory(file_create_path($export_path . '/../themes'),1);
  file_check_directory(file_create_path($export_path . '/../misc'),1);
}

/**
 * _html_export_copy_drupal_directories
 * export&copy drupal setting directories
 */
function _html_export_copy_drupal_directories($export_path){
  _html_export_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'sites',$export_path . '/../sites');
  _html_export_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'modules',$export_path . '/../modules');
  _html_export_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'themes',$export_path . '/../themes');
  _html_export_copyr(str_replace('index.php','',$_SERVER['PATH_TRANSLATED']) . 'misc',$export_path . '/../misc');
}

/**
 * ノードのnidsをセットする
 * _set_nids_for_nodes()
 */
function _set_nids_for_nodes($nids){
  $result = db_query("SELECT nid FROM {node} WHERE status=1 ORDER BY nid DESC");
  while($node = db_fetch_array($result)){
    $url = url('node/' . $node['nid']);
    $url = _trim_url_query($url);
    if ($url == 'node/' . $node['nid']){
      // 命名規則の変更
      // $nids['node/' . $node['nid']] = 'page' . $node['nid'] . '.html';
      $nids['node/'. $node['nid']] = 'node'. $node['nid']. '.html';
    }else{
      //this removes the fake extension if one exists
      $tmp_url = _html_export_remove_ext($url);
      // Add url to array
      $nids[$url] = $tmp_url . '.html';
      $nids['node/' . $node['nid']] = $tmp_url . '.html';
    }
  }
  return $nids;
}

/**
 * フロントページのnodeのpagenation用にnidsにマッピング
 */
function _set_nids_for_nodepagenation($nids, $nodecount){
  for ($i = $nodecount; $i > 0; $i--){
    $nids['node&amp;page='.$i] = 'page' . $i . '.html';
  }
  // フロントページ用のマッピング
  $nids["node"] = "index.html";
  return $nids;
}

/**
 * views用にnidsにマッピング
 */
function _set_nids_for_views($nids){
  if (module_exists('views')) {
    //grab all the views in the system that are published and then build out a list of url's to rename in the rendered code.
    //similar to url rewrite and will need to take that into account eventually
    $result = db_query("SELECT * FROM views_display WHERE display_plugin = 'page'");
    while($view = db_fetch_array($result)){
      $view_vars = unserialize($view['display_options']);
      if (strpos($view_vars['path'],'admin/') === false && strpos($view_vars['path'],'front') === false){
        $url = url($view_vars['path']);
        $tmp_url = _trim_url_query($url);
        //this removes the fake extension if one exists
        $tmp_url = _html_export_remove_ext($tmp_url);
        // Add url to array
        $nids[$url] = $tmp_url . '.html';
      }
    }
  }
  return $nids;
}

/**
 * _set_nids_for_custom_pages
 * カスタムページ用にnidsにマッピング
 */
function _set_nids_for_custom_pages($nids){
  // Export custom pages
  $pages = explode(',',_html_export_make_list(variable_get('html_export_pages', '')));
  foreach ($pages as $page) {
    if ($page != ""){
      $nids[$page] = $page . '.html';
    }
  }
  return $nids;
}

/**
 * _set_nids_for_term
 * termをtidsにセットする
 */
function _set_nids_for_term($nids, $tid){
  // TODO:余力があればターム名で
  //file_check_directory(file_create_path($export_path . '/'. _html_export_urlencode($term['name'])),1);
  $url = url('taxonomy/term/'. $tid);
  $url = _trim_url_query($url);
  // URLの設定
  if ($url == 'taxonomy/term/'. $tid){
    $nids['taxonomy/term/'. $tid] = 'term'. $tid. '/index.html';
  }
  return $nids;
}

/**
 * blogsをnidsにセットする
 */
function _set_nids_for_blogs($export_path, $nids){
  $result = db_query("SELECT uid FROM {users} WHERE uid <> 0 ORDER BY uid");
  $blogs_page = array();
  $exist_blog = false;

  while ($user = db_fetch_array($result)){
    $url = url('blog/'. $user['uid']);
    if (200 == $code = drupal_http_request($url)){
      $exist_blog = true;
      file_check_directory(file_create_path($export_path . '/blog'. $user['uid']), 1);
      $result_page = db_query(
        "SELECT truncate(count(*)/".variable_get('default_nodes_main', 10). ", 0) num FROM {node} WHERE type = 'blog' AND uid = ".$user['uid']
      );
      // 必要なページ数
      $pagenumber = db_fetch_array($result_page);
      $page = $pagenumber['num'];
      if ($page > 0){
        // pagenationが必要な場合だけ保存する
        $blogs_page[$user['uid']] = $page;
      }
      for ($i = $page; $i > 0; $i--){
        $nids['blog/'. $user['uid']. '&amp;page='. $i] = 'blog'. $user['uid']. '/page'. $i. '.html';
      }
      $url = _trim_url_query($url);
      if ($url == 'blog/'.$user['uid']){
        $nids['blog/'. $user['uid']] = 'blog'. $user['uid']. '/index.html';
      }
    }
  }

  if ($exist_blog){
    file_check_directory(file_create_path($export_path . '/blog'), 1);

    $result = db_query(
      "SELECT truncate(count(*)/".variable_get('default_nodes_main', 10). ", 0) num FROM {node} WHERE type = 'blog'"
    );
    // 必要なページ数
    $pagenumber = db_fetch_array($result);
    $page = $pagenumber['num'];
    if ($page > 0){
      // pagenationが必要な場合だけ保存する
      $blogs_page[0] = $page;
    }
    for ($i = $page; $i > 0; $i--){
      $nids['blog&amp;page='. $i] = 'blog/page'. $i. '.html';
    }
    // 全体のブログを管理するURLのマッピング
    $nids['blog'] = 'blog/index.html';
  }

  return array($nids, $blogs_page);
}

/**
 * _set_nids_for_termpagenation
 * tidのpagenationをnidsにマッピング
 */
function _set_nids_for_termpagenation($nids, $tid, $pagenumber){
  for ($i = $pagenumber; $i > 0; $i--){
    $url = 'taxonomy/term/'. $tid. '&amp;page='. $i;
    $nids[$url] = 'term'. $tid. '/page'. $i. '.html';
  }
  return $nids;
}

/**
 * _set_nids_for_xml
 * xmlをnidsに登録する
 */
function _set_nids_for_xml($nids){
  $url = url("rss.xml");
  $url = _trim_url_query($url);
  if ($url == "rss.xml"){
    $nids["rss.xml"] = "rss.xml";
  }
  return $nids;
}

/**
 * _get_tids_having_pagenation
 * ページナビゲーションが必要なtidを返す
 */
function _get_tids_having_pagenation(){
  $default_nodes_main = variable_get('default_nodes_main', 10);
  $pagenation_num = $default_nodes_main + 1;
  // このクエリは正しくないが、大きめに取っているので問題はない
  $query = "SELECT tid FROM (SELECT DISTINCT nid, tid FROM {term_node} ) AS temp GROUP BY tid HAVING COUNT(*) >= "
    . $pagenation_num;
  $result = db_query($query);
  $tids = array();
  while($tid = db_fetch_array($result)){
    $tids[] = $tid['tid'];
  }

  return $tids;
}

/**
 * _get_index_pages_having_pagenation
 * ページナビゲーションが必要なインデックスページのページ数を返す
 */
function _get_index_pages_having_pagenation(){
  $default_nodes_main = variable_get('default_nodes_main', 10);
  $pagenation_num = $default_nodes_main + 1;
  $query = "SELECT CEIL(count(*) DIV ". variable_get('default_nodes_main', 10).
    ") num FROM {node} WHERE status = 1 AND type in ('story', 'blog')";
  $result = db_query($query);
  $nodecount = db_fetch_array($result);

  return $nodecount['num'] - 1;
}

/**
 * _trim_url_query
 * ex.) ?q=node/1 -> node/1
 */
function _trim_url_query($url){
  if (strpos(' ' . $url,'/?q=') != 0){
    $url = substr($url,4 + strpos($url,'/?q='));
  }
  if (($pos = strpos(' '.$url, '/edit')) !== false){
    $url = substr($url, 0, $pos - 1);
  }
  return $url;
}

/**
 * _export_nodes
 * nodesをhtml出力する
 */
function _export_nodes($root, $nids, $export_path, $current_node_id = 0){
  if ($current_node_id == 0){
    $result = db_query("SELECT nid FROM {node} WHERE status=1 ORDER BY nid DESC");
  }else{
    $result = db_query("SELECT nid FROM {node} WHERE status = 1 AND nid = ".$current_node_id);
    $node_hidden = true;
  }
  while($node = db_fetch_array($result)){
    if ($node['nid'] == $current_node_id){
      $node_hidden = false;
    }
    $data = _get_html_data($root. "index.php?q=node/" . $node['nid']);
    //Rewrite all links
    //TODO:_html_export_rewrite_urlsはblogに対応していないので修正する必要有。
    $data = _html_export_rewrite_relative_urls($data,$nids, ".");
    // Write HTML to file
    _export_html_file($data, $nids['node/' . $node['nid']], $export_path, false);
  }
  if ($node_hidden){
    _delete_html_file($export_path, $current_node_id);
  }
}

/**
 * _export_views
 * viewsをhtml出力する
 */
function _export_views($root, $nids, $export_path){
  if (module_exists('views')) {
    //run through all the views and render pages to add to the zip file
    $result = db_query("SELECT * FROM views_display WHERE display_plugin = 'page'");
    while($view = db_fetch_array($result)){
      $view_vars = unserialize($view['display_options']);
      if (strpos($view_vars['path'],'admin/') === false && strpos($view_vars['path'],'front') === false){
        $data = _get_html_data($root. $view_vars['path']);
        $data = _html_export_rewrite_relative_urls($data,$nids, ".");
        // Write HTML to file
        _export_html_file($data, $nids[$view_vars['path']], $export_path, false);
      }
    }
  }
}

/**
 * _export_terms
 * termをhtml出力する
 */
function _export_terms($root, $tids_need_pagenation, $tids_page, $nids, $export_path, $current_node_id = 0){
  if ($current_node_id != 0){
    $result = db_query("SELECT DISTINCT tid FROM {term_node} WHERE nid = ". $current_node_id);
  }else{
    $result = db_query("SELECT tid FROM {term_data} ORDER BY tid DESC");
  }
  while ($term = db_fetch_array($result)){
    if (in_array($term['tid'], $tids_need_pagenation)){
      // pagenationが必要であれば
      for ($i = $tids_page[$term['tid']]; $i > 0; $i--){
        $data = _get_html_data($root. '?q=taxonomy/term/'. $term['tid']. '&page='. $i);
        //$data = _html_export_rewrite_urls($data,$nids);
        $data = _html_export_rewrite_relative_urls($data, $nids, "..");
        // Write HTML to file
        $page = $nids['taxonomy/term/'. $term['tid']. '&amp;page='. $i];
        _export_html_file($data, $page, $export_path, false);
      }
    }
    // ex.) index.php?q=taxonomy/term/1
    $url = url('taxonomy/term/'. $term['tid']);
    $data = _get_html_data($root. "index.php". $url);
    //$data = _html_export_rewrite_urls($data,$nids);
    $data = _html_export_rewrite_relative_urls($data,$nids, "..");
    // Write HTML to file
    $page = $nids['taxonomy/term/'. $term['tid']];
    _export_html_file($data, $page, $export_path, false);
  }
}

/**
 * _export_blogs
 * blogをhtml出力する
 */
function _export_blogs($root, $nids, $export_path, $blogs_page){
  $result = db_query("SELECT uid FROM {users} WHERE uid <> 0 ORDER BY uid");
  while($user = db_fetch_array($result)){
    if (array_key_exists('blog/'. $user['uid'], $nids)){
      if (array_key_exists($user['uid'], $blogs_page)){
        // pagenationが必要であれば
        for ($i = $blogs_page[$user['uid']]; $i > 0; $i--){
          $data = _get_html_data($root. "?q=blog/". $user['uid']. "&page=". $i);
          $data = _html_export_rewrite_relative_urls($data, $nids, "..");
          _export_html_file($data, $nids['blog/'. $user['uid']. '&amp;page='. $i], $export_path, false);
        }
      }
      $data = _get_html_data($root. "?q=blog/" . $user['uid']);
      //Rewrite all links
      $data = _html_export_rewrite_relative_urls($data,$nids, "..");
      // Write HTML to file
      _export_html_file($data, $nids['blog/' . $user['uid']], $export_path, false);
    }
  }
}

/**
 * blogフロントページのhtml出力
 */
function _export_blogfront_page($root, $nids, $export_path, $blogs_page){
  if (array_key_exists('blog', $nids)){
    // フロントページのpagenation
    _export_blogfront_pagenation($root, $blogs_page, $nids, $export_path);
    // get html
    $data = _get_html_data($root. "?q=blog");
    //$data = _html_export_rewrite_urls($data,$nids);
    $data = _html_export_rewrite_relative_urls($data,$nids, "..");
    // Write HTML to file
    _export_html_file($data, $nids['blog'], $export_path, false);
  }
}

/**
 * blogフロントページpagenationのhtml出力
 */
function _export_blogfront_pagenation($root, $blogs_page, $nids, $export_path){
  for ($i = $blogs_page[0]; $i > 0; $i--){
    $data = _get_html_data($root. '?q=blog&page='. $i);
    //$data = _html_export_rewrite_urls($data,$nids);
    $data = _html_export_rewrite_relative_urls($data,$nids, "..");
    // Write HTML to file
    $page = $nids['blog&amp;page='. $i];
    _export_html_file($data, $page, $export_path, false);
  }
}

/**
 * _export_custom_pages
 * カスタムページをhtml出力する
 */
function _export_custom_pages($root, $nids, $export_path){
  $pages = explode(',',_html_export_make_list(variable_get('html_export_pages', '')));
  foreach ($pages as $page) {
    $data = _get_html_data($root. $page);
    //$data = _html_export_rewrite_urls($data,$nids);
    $data = _html_export_rewrite_relative_urls($data,$nids, ".");
    // Write HTML to file
    _export_html_file($data, $page, $export_path, true);
  }
}

/**
 * フロントページのhtml出力
 */
function _export_front_page($root, $nodecount, $nids, $export_path){
  // フロントページのpagenation
  _export_front_pagenation($root, $nodecount, $nids, $export_path);
  // get html
  $data = _get_html_data($root. "index.php");
  //$data = _html_export_rewrite_urls($data,$nids);
  $data = _html_export_rewrite_relative_urls($data,$nids, ".");
  // Write HTML to file
  _export_html_file($data, "index", $export_path, true);
}

/**
 * フロントページpagenationのhtml出力
 */
function _export_front_pagenation($root, $nodecount, $nids, $export_path){
  for ($i = $nodecount; $i > 0; $i--){
    $data = _get_html_data($root. '?q=node&page='. $i);
    //$data = _html_export_rewrite_urls($data,$nids);
    $data = _html_export_rewrite_relative_urls($data,$nids, ".");
    // Write HTML to file
    $page = $nids['node&amp;page='. $i];
    _export_html_file($data, $page, $export_path, false);
  }
}

/**
 * _get_htmL_data
 */
function _get_html_data($page){
  $drupal_site = drupal_http_request($page);
  $data = $drupal_site->data;
  return $data;
}

/**
 * _export_html_file
 * 静的HTMLファイルとして出力する
 */
function _export_html_file($data, $page, $export_path, $html){
  // Write HTML to file
  if ($html){
    $file = fopen($export_path . "/". $page .".html","w");
  }else{
    $file = fopen($export_path . "/". $page,"w");
  }
  fwrite($file,$data);
  fclose($file);
}

/**
 * 既に存在するhtmlファイルを削除する
 */
function _delete_html_file($export_path, $current_node_id){
  $path = $export_path. "/node". $current_node_id. ".html";
  if (is_file($path)){
    unlink($path);
  }
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
 * 相対パスでurlエンコードするヘルパ
 * _html_export_rewrite_relative_urls($html, $nids, $relative)
 * extends _html_export_rewrite_urls($html, $nids)
 */
function _html_export_rewrite_relative_urls($html, $nids, $relative){
  $root = _html_export_root_domain(false);
  //strip out file paths that have the full server in them
  $html = _trim_absolute_path($root, $html);
  // Custom replacements
  // $replacements = explode(',',_html_export_make_list(variable_get('html_export_replace', '')));
  // stylesheetなどのパスは相対的に変更する
  $html = _files_replacements($relative, $html);

  // /taxonomy/term/10 => /taxonomy/term/1/index.html0などになってしまうので、
  // krsortをかける
  krsort($nids);

  //strip out just the node/ if it's left over and replace it with the correct form of the link so that they actually find each other
  foreach($nids as $key => $nidpath){
    //get rid of a base path if there is one
    if (base_path() != '/'){
      $html = str_replace(base_path(),'',$html);
    }
    //account for links back to home where they are just a backslash cause it's at the root
    $html = _home_path_replace($html, $key, $nidpath, $relative);
  }
  $html = _home_link_replace($html, $relative);

  return $html;
}

/**
 * _trim_absolute_path
 * 絶対パスでルートを削る
 */
function _trim_absolute_path($root, $html){
  $html = str_replace($root . base_path(),"",$html);
  $html = str_replace($root,"",$html);
  $html = str_replace(_html_export_urlencode($root . base_path()),"",$html);
  $html = str_replace(_html_export_urlencode($root),"",$html);
  return $html;
}

/**
 * _files_replacements
 * drupalのファイルを相対パスに置換する
 */
function _files_replacements($relative, $html){
  $replacements = array(
    'modules|'.$relative.'/modules',
    conf_path().'/files|'. $relative. '/files',
    'sites/all|'.$relative. '/all',
    'sites|'.$relative. '/sites',
    'themes|'. $relative. '/themes',
    'misc|'. $relative. '/misc',
    '/./|/',
    'all/../|all/',
  );
  foreach ($replacements as $replacement) {
    $keys = explode('|',$replacement);
    $html = str_replace($keys[0],$keys[1],$html);
  }
  return $html;
}

/**
 * homeへのリンクを相対パスに変換する
 */
function _home_path_replace($html, $key, $nidpath, $relative){
  $html = str_replace('index.php/?q=' . $key,$relative. '/'. $nidpath,$html);
  $html = str_replace('index.php?q=' . $key,$relative. '/'. $nidpath,$html);
  $html = str_replace('/?q=' . $key,$relative. '/'. $nidpath,$html);
  $html = str_replace('?q=' . $key,$relative. '/'. $nidpath,$html);
  return $html;
}

/**
 * ホームへのhtmlリンクを相対パスに変換
 */
function _home_link_replace($html, $relative){
  $html = str_replace('?q=','',$html);
  $html = str_replace('<a href="/"','<a href="'. $relative. '/'. 'index.html"',$html);
  $html = str_replace('href="/','href="',$html);
  $html = str_replace('src="/','src="',$html);
  $html = str_replace('<a href=""','<a href="'. $relative. '/'. 'index.html"',$html);
  return $html;
}

/**
 * Helper function to Convert a list to a comma seperated list.
 */
function _html_export_make_list($text) {
  $cslist = str_replace(array("\r\n", "\n", "\r"), '', $text);
  $cslist = str_replace(' ', '', $cslist);
  $cslist = str_replace(';', ',', $cslist);
  if (substr($cslist, -1) == ',') $cslist = substr_replace($cslist,'',-1);

  return $cslist;
}

/**
 * Helper function to Convert a list to a comma seperated list.
 */
function _html_export_root_domain($visit = true) {
  //TODO:要確認
  //$root = $_SERVER['HTTP_HOST'];
  $root = substr($_SERVER['HTTP_REFERER'],0,strpos($_SERVER['HTTP_REFERER'],$_GET['q']));
  if (strpos($root,'?q=') != 0){
    $root = substr($root,0,strpos($root,'?q='));
  }

/*
  if (variable_get('html_export_domain', ''))  $root = variable_get('html_export_domain', '');
  //drupal_set_message($root);
  //remove the ?q= if clean URLs are off
  if (strpos($root,'?q=') != 0){
    $root = substr($root,0,strpos($root,'?q='));
  }
  if (!$visit){
    $aDomain = explode('@',$root);
    $root = $aDomain[1];
  }
  if (substr($root, 0, 7)!='http://') $root= 'http://'.$root;
  if (substr($root, -1) != "/") $root = $root.'/';
 */
/*
    if ($root=='localhost'){
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
