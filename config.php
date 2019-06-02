<?php
// Database connection details
$dbms = 'mysqli';
$dbhost = 'localhost';
$dbport = '';
$dbname = '';
$dbuser = '';
$dbpasswd = '';
$table_prefix = 'phpbb_';

// Ignore
$acm_type = 'file';
$load_extensions = '';
@define('IN_PHPBB', true);
@define('PHPBB_INSTALLED', true);
$style_data = array();

// If you want to use a style that isn't installed, change the three "path" fields to use
// the name of the style's folder.
// If you want to use the board's default style, just remove these lines
// Style inheritance hasn't been tested
$style_data['style_id'] = 99999;
$style_data['template_id'] = 99999;
$style_data['template_storedb'] = false;
$style_data['template_path'] = 'Annihilation_Classic';
$style_data['bbcode_bitfield'] = '+Ng=';
$style_data['theme_path'] = 'Annihilation_Classic';
$style_data['theme_name'] = 'Annihilation Classic';
$style_data['theme_storedb'] = false;
$style_data['theme_id'] = 99999;
$style_data['imageset_path'] = 'Annihilation_Classic';
$style_data['imageset_id'] = 99999;
$style_data['imageset_name'] = 'Annihilation Classic';

// Name of the folder to output all of the files to
$out_folder = 'out';

// List of forum IDs to convert or leave as array() to convert all guest viewable forums
$forums = array(1, 2, 3);