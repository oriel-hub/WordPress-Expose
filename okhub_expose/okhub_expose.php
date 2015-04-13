<?php
/*
Plugin Name: OKHub Expose
Plugin URI: http://api.ids.ac.uk/category/plugins/
Description: Exposes content to be incorporated to the IDS Knowledge Services Hub.
Version: 1.0
Author: Pablo Accuosto for the Institute of Development Studies (IDS)
Author URI: http://api.ids.ac.uk/
License: GPLv3

    Copyright 2014  Institute of Development Studies (IDS)

    This program is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/

if (!defined('IDS_API_ENVIRONMENT')) define('IDS_API_ENVIRONMENT', 'wordpress');

if (!defined('IDS_API_LIBRARY_DIR')) {
  if ($dirs_wrapper = glob(WP_PLUGIN_DIR . '{/*/,/}idswrapper/', GLOB_BRACE)) {
    define('IDS_API_LIBRARY_DIR', $dirs_wrapper[0]);
    define('IDS_API_LIBRARY_PATH', plugin_basename($dirs_wrapper[0]));
  }
  else {
    wp_die(__('OKHub Expose: The required directory "idswrapper" was not found. Please download it from https://github.com/IDS-API/PHP-wrapper/archive/master.zip and place it in the plugins directory.'));
  }
}
if (file_exists(IDS_API_LIBRARY_DIR) && is_readable(IDS_API_LIBRARY_DIR)) {
  require_once(IDS_API_LIBRARY_DIR . 'idswrapper.wrapper.inc');
} else {
  wp_die(__('OKHub Expose: The directory "idswrapper" or its contents could not be read.'));
}

if (!defined('IDS_COMMON_FILES_DIR')) {
  if ($dirs_common = glob(WP_PLUGIN_DIR . '{/*/,/}idsplugins_common/', GLOB_BRACE)) {
    define('IDS_COMMON_FILES_DIR', $dirs_common[0]);
    define('IDS_COMMON_FILES_PATH', plugin_basename($dirs_common[0]));
  }
  else {
    wp_die(__('OKHub Expose: The required directory "idsplugins_common" was not found. Please download it from https://github.com/IDS-API/WordPress-Common-Files/archive/master.zip and place it in the plugins directory.'));
  }
}
if (file_exists(IDS_COMMON_FILES_DIR) && is_readable(IDS_COMMON_FILES_DIR)) {
  require_once(IDS_COMMON_FILES_DIR . 'idsplugins.customtypes.inc');
  require_once(IDS_COMMON_FILES_DIR . 'idsplugins.functions.inc');
  require_once(IDS_COMMON_FILES_DIR . 'idsplugins.html.inc');
} else {
  wp_die(__('OKHub Expose: The directory "idsplugins_common" or its contents could not be read.'));
}

require_once('okhub_expose.admin.inc');
require_once('okhub_expose.filters.inc');

// In order to use the idsimport_taxonomy metadata table even if the IDS Import plugin has been de-activated.
if (!defined('IDS_IMPORT_TAXONOMY')) define('IDS_IMPORT_TAXONOMY', 'idsimport_taxonomy');

//-------------------------------- Set-up hooks ---------------------------------

//register_activation_hook(dirname(__FILE__), 'okhub_expose_activate');
add_action('init', 'okhub_expose_init');
add_action('admin_init', 'okhub_expose_admin_init');
add_action('admin_menu', 'okhub_expose_add_options_page');
add_action('admin_menu', 'okhub_expose_add_menu', 9);
add_action('admin_notices', 'okhub_expose_admin_notices');
add_filter('plugin_action_links', 'okhub_expose_plugin_action_links', 10, 2);
add_action('wp_enqueue_scripts', 'okhub_expose_add_stylesheet');
add_action('admin_enqueue_scripts', 'okhub_expose_add_admin_stylesheet');
add_action('admin_enqueue_scripts', 'okhub_expose_add_javascript');
add_action('do_feed_ids_assets', 'okhub_expose_feed_ids_assets', 10, 1);
add_action('do_feed_ids_categories', 'okhub_expose_feed_ids_categories', 10, 1);
add_filter('wp_dropdown_cats', 'okhub_expose_dropdown_cats' );

//--------------------------- Set-up / init functions ----------------------------


// Initialize plugin.
function okhub_expose_init() {
  ids_check_permalinks_changed('okhub_expose');
}

// Initialize the plugin's admin options
function okhub_expose_admin_init(){
  register_setting('okhub_expose', 'okhub_expose_options', 'okhub_expose_validate_options');
  $options = get_option('okhub_expose_options');
  if(!is_array($options)) { // The options are corrupted.
    okhub_expose_delete_plugin_options();
  }
}

// Delete options entries
function okhub_expose_delete_plugin_options() {
	delete_option('okhub_expose_options');
}

// Enqueue stylesheet. We keep separate functions as in the future we might want to use different stylesheets for each plugin.
function okhub_expose_add_stylesheet() {
    wp_register_style('okhub_expose_style', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'idsplugins.css', dirname(__FILE__)));
    wp_enqueue_style('okhub_expose_style');
}

// Enqueue admin stylesheet
function okhub_expose_add_admin_stylesheet() {
  okhub_expose_add_stylesheet();
  wp_register_style('okhub_expose_chosen_style', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'chosen/chosen.css', dirname(__FILE__)));
  wp_enqueue_style('okhub_expose_chosen_style');
  wp_register_style('okhub_expose_jqwidgets_style', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'jqwidgets/styles/jqx.base.css', dirname(__FILE__)));
  wp_enqueue_style('okhub_expose_jqwidgets_style');
}

// Enqueue javascript
function okhub_expose_add_javascript($hook) {
  if (($hook == 'settings_page_okhub_expose') || ($hook == 'okhub-expose_page_okhub_expose_create_feeds')) { // Only in the admin pages.
    wp_print_scripts( 'jquery' );
    wp_print_scripts( 'jquery-ui-tabs' );
    wp_register_script('okhub_expose_javascript', plugins_url(IDS_PLUGINS_SCRIPTS_PATH . 'idsplugins.js', dirname(__FILE__)));
    wp_enqueue_script('okhub_expose_javascript');
    ids_init_javascript('okhub_expose');
  }
}

// Display a 'Settings' link on the main Plugins page
function okhub_expose_plugin_action_links($links, $file) {
	if ($file == plugin_basename(dirname(__FILE__))) {
		$idsapi_links = '<a href="' . get_admin_url() . 'options-general.php?page=okhub_expose">' . __('Settings') . '</a>';
		array_unshift($links, $idsapi_links);
	}
	return $links;
}

// Make categories selects multiple.
function okhub_expose_dropdown_cats($output) {
  if (preg_match('/<select name=\'okhub_expose/', $output)) {
    $output = preg_replace('/<select /', '<select multiple="multiple" ', $output);
  }
  return $output;
}

// Add settings link
function okhub_expose_add_options_page() {
  add_options_page('OKHub Expose Settings Page', 'OKHub Expose', 'manage_options', 'okhub_expose', 'okhub_expose_admin_main');
}

// Add menu
function okhub_expose_add_menu() {
  add_menu_page('OKHub Expose', 'OKHub Expose', 'manage_options', 'okhub_expose_menu', 'okhub_expose_general_page', plugins_url(IDS_IMAGES_PATH . '/ids.png', dirname(__FILE__)));
  add_submenu_page('okhub_expose_menu', 'Settings', 'Settings', 'manage_options', 'options-general.php?page=okhub_expose');
  add_submenu_page('okhub_expose_menu', 'Feeds', 'Feeds', 'manage_options', 'okhub_expose_create_feeds', 'okhub_expose_create_feeds_page');
  add_submenu_page('okhub_expose_menu', 'Help', 'Help', 'manage_options', 'okhub_expose_help', 'okhub_expose_help_page');
}

function okhub_expose_feed_ids_assets() {
	load_template(dirname(__FILE__) . '/templates/okhub_expose_assets_template.php');
}

function okhub_expose_feed_ids_categories() {
	load_template(dirname(__FILE__) . '/templates/okhub_expose_categories_template.php');
}

function okhub_expose_get_post_types() {
  $array_post_types = array();//array('post' => 'Default Wordpress posts');
  $post_types = get_post_types(array('public' => true), 'objects') ;
  foreach ($post_types as $post_type) {
    $array_post_types[$post_type->name] = $post_type->labels->menu_name;
  }
  return $array_post_types;
}

function okhub_expose_get_taxonomies() {
  $array_taxonomies = array();
  $taxonomies = get_taxonomies(array('public' => true), 'objects');
  foreach ($taxonomies as $taxonomy) {
    $array_taxonomies[$taxonomy->name] = $taxonomy->labels->menu_name;
  }
  return $array_taxonomies;
}

function okhub_expose_get_xml($tag, $value) {
  $ret = '';
  if ($unserialized = @unserialize($value)) {
    $value = $unserialized;
  }
  if (is_array ($value)) {
    $ret = "<$tag>";
    foreach ($value as $val) {
      $ret .= okhub_expose_get_xml('list-item', $val);
    }
    $ret .= "</$tag>";
  }
  elseif (is_object($value)) {
    $ret = "<$tag>";
    foreach ($value as $key => $val) {
      $ret .= okhub_expose_get_xml($key, $val);
    }
    $ret .= "</$tag>";
  }
  elseif (is_scalar($value)) {
    $ret = "<$tag>" . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . "</$tag>";
  }
  return $ret;
}

function okhub_expose_print_xml($tag, $value) {
  if ($value) {
    if (is_array($value)) {
      foreach ($value as $val) {
        echo okhub_expose_get_xml($tag, $val);
      }
    }
    else {
      echo okhub_expose_get_xml($tag, $value);
    }
  }
}
