<?php
/**
 * @copyright (c) 2019 Patrick Webster
 * @license https://opensource.org/licenses/GPL-2.0
 */

define('IN_PHPBB', true);
$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'includes/common.' . $phpEx);
include($phpbb_root_path . 'includes/bbcode.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
include($phpbb_root_path . 'includes/functions_archive.' . $phpEx);

// Purge the cache from any previous runs
$cache->purge();


// Start session management
$user->session_begin();
$auth->acl($user->data);

if (sizeof($style_data))
{
	$user->setup(false, $style_data['style_id'], $style_data);
}
else
{
	$user->setup(false);
}

// Find guest visible forums if none specified
if (!sizeof($forums))
{
	$forums = get_guest_forums();
}

$forum_data = get_forum_data();

if (!set_time_limit(0))
{
    echo 'Unable to disable execution time limit. This script may time out.' . LINE_ENDING . LINE_ENDING;
}

$topics = $posts = 0;
echo 'The following forums will be converted to static pages:' . LINE_ENDING;
foreach ($forums as $id)
{
	echo $id . ': ' . $forum_data[$id]['name'] . LINE_ENDING;
	$topics += $forum_data[$id]['topics'];
	$posts += $forum_data[$id]['posts'];
}

echo LINE_ENDING;
echo 'Creating index page... ';

generate_index_page($out_folder);
echo 'Done' . LINE_ENDING . LINE_ENDING;

echo 'Processing forums: ' . $topics . ' topics, ' . $posts . ' posts' . LINE_ENDING;
foreach ($forums as $forum)
{
	echo $forum . ': ' . $forum_data[$forum]['name'] . ' (' . $forum_data[$forum]['topics'] . ' topics, ' . $forum_data[$forum]['posts'] . ' posts) ... ';
	generate_forum_pages($forum, $out_folder);
	generate_topics_pages($forum, $out_folder);
	echo 'Done' . LINE_ENDING;
}

echo 'Conversion done.' . LINE_ENDING;