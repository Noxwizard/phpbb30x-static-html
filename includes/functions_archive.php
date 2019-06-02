<?php
/**
 * @copyright (c) 2019 Patrick Webster
 * @license https://opensource.org/licenses/GPL-2.0
 */

/**
 * Functions related to board archival
 */

function get_guest_forums()
{
	global $db, $auth;

	$parent_id = 0;
	$sql_from = '';
	$root_data = array('forum_id' => 0);
	$sql_where = '';

	$sql_array = array(
		'SELECT'	=> 'f.*',
		'FROM'		=> array(
			FORUMS_TABLE		=> 'f'
		),
		'LEFT_JOIN'	=> array(),
	);

	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> $sql_array['SELECT'],
		'FROM'		=> $sql_array['FROM'],
		'LEFT_JOIN'	=> $sql_array['LEFT_JOIN'],

		'WHERE'		=> $sql_where,

		'ORDER_BY'	=> 'f.left_id',
	));

	$forums = array();
	$result = $db->sql_query($sql);
	while ($row = $db->sql_fetchrow($result))
	{
		$forum_id = $row['forum_id'];

		// Category with no members
		if ($row['forum_type'] == FORUM_CAT && ($row['left_id'] + 1 == $row['right_id']))
		{
			continue;
		}

		// Skip branch
		if (isset($right_id))
		{
			if ($row['left_id'] < $right_id)
			{
				continue;
			}
			unset($right_id);
		}

		if (!$auth->acl_get('f_list', $forum_id))
		{
			// if the user does not have permissions to list this forum, skip everything until next branch
			$right_id = $row['right_id'];
			continue;
		}

		//
		if ($row['parent_id'] == $root_data['forum_id'] || $row['parent_id'] == $branch_root_id)
		{
			// Direct child of current branch
			$parent_id = $forum_id;
			$forums[] = $forum_id;

			if ($row['forum_type'] == FORUM_CAT && $row['parent_id'] == $root_data['forum_id'])
			{
				$branch_root_id = $forum_id;
			}
		}
		else if ($row['forum_type'] != FORUM_CAT)
		{
			$forums[] = $forum_id;
		}
	}
	$db->sql_freeresult($result);

	return $forums;
}

function get_forum_data()
{
	global $db;
	$forum_data = array();

	$sql = 'SELECT forum_id, forum_name, forum_topics, forum_posts FROM ' . FORUMS_TABLE;
	$result = $db->sql_query($sql);

	while ($row = $db->sql_fetchrow($result))
	{
		$forum_data[$row['forum_id']] = array(
			'name' => $row['forum_name'],
			'topics' => (int) $row['forum_topics'],
			'posts' => (int) $row['forum_posts'],
		);
	}

	$db->sql_freeresult($result);

	return $forum_data;
}

function generate_index_page($out_folder)
{
	global $config, $user, $template;
	archive_init();
	$user->add_lang('viewforum');
	//$user->add_lang('viewtopic');

	$total_posts	= $config['num_posts'];
	$total_topics	= $config['num_topics'];
	$total_users	= $config['num_users'];

	$l_total_user_s = ($total_users == 0) ? 'TOTAL_USERS_ZERO' : 'TOTAL_USERS_OTHER';
	$l_total_post_s = ($total_posts == 0) ? 'TOTAL_POSTS_ZERO' : 'TOTAL_POSTS_OTHER';
	$l_total_topic_s = ($total_topics == 0) ? 'TOTAL_TOPICS_ZERO' : 'TOTAL_TOPICS_OTHER';
	
	display_forums('', $config['load_moderators']);
	
	// Assign index specific vars
	$template->assign_vars(array(
		'TOTAL_POSTS'	=> sprintf($user->lang[$l_total_post_s], $total_posts),
		'TOTAL_TOPICS'	=> sprintf($user->lang[$l_total_topic_s], $total_topics),
		'TOTAL_USERS'	=> sprintf($user->lang[$l_total_user_s], $total_users),
		'NEWEST_USER'	=> sprintf($user->lang['NEWEST_USER'], get_username_string('full', $config['newest_user_id'], $config['newest_username'], $config['newest_user_colour'])),
	
	
		'FORUM_IMG'				=> $user->img('forum_read', 'NO_UNREAD_POSTS'),
		'FORUM_UNREAD_IMG'			=> $user->img('forum_unread', 'UNREAD_POSTS'),
		'FORUM_LOCKED_IMG'		=> $user->img('forum_read_locked', 'NO_UNREAD_POSTS_LOCKED'),
		'FORUM_UNREAD_LOCKED_IMG'	=> $user->img('forum_unread_locked', 'UNREAD_POSTS_LOCKED'),
	
		'S_LOGIN_ACTION'			=> '',
		'S_DISPLAY_BIRTHDAY_LIST'	=> false,
	
		'U_MARK_FORUMS'		=> '',
		'U_MCP'				=> '')
	);
	
	ob_start();
	page_header($user->lang['INDEX']);
	
	$template->set_filenames(array(
		'body' => 'index_body.html')
	);
	
	page_footer();

	$contents = ob_get_clean();

	$fp = fopen($out_folder . '/' . 'index.php.html', 'w');
	fwrite($fp, $contents);
	fclose($fp);
}

function generate_forum_pages($forum_id, $out_folder)
{
	global $config, $db;

	$sql = 'SELECT COUNT(topic_id) AS num_topics
			FROM ' . TOPICS_TABLE . "
			WHERE forum_id = $forum_id
				AND ((topic_type <> " . POST_GLOBAL . ")
					OR topic_type = " . POST_ANNOUNCE . ")
				AND topic_approved = 1";
	$result = $db->sql_query($sql);
	$topics_count = (int) $db->sql_fetchfield('num_topics');
	$db->sql_freeresult($result);

	/*
	if ($topics_count <= 0)
	{
		//echo 'No topics (genforum): ' . $forum_id . "\n";
		return false;
	}*/

	$pages = ($topics_count > 0) ? $topics_count / $config['topics_per_page'] : 1;
	for ($i = 0; $i < $pages; $i++)
	{
		$start = $i * $config['topics_per_page'];
		$content = generate_forum_page($forum_id, $start);
		if ($content['accessible'])
		{
			$fp = fopen($out_folder . '/' . 'viewforum.php_f=' . $forum_id . ($i != 0 ? '_start=' . $start : '') . '.html', 'w');
			fwrite($fp, $content['contents']);
			fclose($fp);
		}
	}
}

function generate_topics_pages($forum_id, $out_folder)
{
	global $config, $db;

	$sql = 'SELECT topic_id
			FROM ' . TOPICS_TABLE . "
			WHERE forum_id = $forum_id
				AND topic_approved = 1";
	$result = $db->sql_query($sql);

	$topic_ids = array();
	while ($row = $db->sql_fetchrow($result))
	{
		$topic_ids[] = (int) $row['topic_id'];
	}
	$db->sql_freeresult($result);
	$topics_count = sizeof($topic_ids);

	if ($topics_count <= 0)
	{
		//fwrite(STDERR, 'No topics in ' . $forum_id . "\n");
		return false;
	}

	for ($i = 0; $i < $topics_count; $i++)
	{
		$sql = 'SELECT COUNT(post_id) AS num_posts
				FROM ' . POSTS_TABLE . "
				WHERE topic_id = $topic_ids[$i]
					AND post_approved = 1";
		$result = $db->sql_query($sql);
		$post_count = (int) $db->sql_fetchfield('num_posts');
		$db->sql_freeresult($result);

		if ($post_count <= 0)
		{
			//fwrite(STDERR, 'No posts in topic ' . $topic_ids[$i] . "\n");
		}
			
		$pages = $post_count / $config['posts_per_page'];
		for ($j = 0; $j < $pages; $j++)
		{
			$start = $j * $config['posts_per_page'];
			$content = generate_topic_page($forum_id, $topic_ids[$i], $start);
			//fwrite(STDERR, 'Topic: ' . $topic_ids[$i] . ', Start: ' . $start . "\n");
			if ($content['accessible'])
			{
				$fp = fopen($out_folder . '/' . 'viewtopic.php_f=' . $forum_id . '_t=' . $topic_ids[$i] . ($j != 0 ? '_start=' . $start : '') . '.html', 'w');
				fwrite($fp, $content['contents']);
				fclose($fp);
			}
		}
	}
}

function generate_forum_page($forum_id, $start)
{
	global $auth, $cache, $config, $db, $phpbb_root_path, $phpEx, $user, $template;
	archive_init();
	$user->add_lang('viewforum');
	ob_start();


	// Start initial var setup
	//$start = 0;

	$sort_days	= 0;
	$sort_key	= 't';
	$sort_dir	= 'd';

	// Check if the user has actually sent a forum ID with his/her request
	// If not give them a nice error page.
	if (!$forum_id)
	{
		ob_end_clean();
		return array(
			'accessible' => false,
			'contents' => '',
		);
	}

	$sql_from = FORUMS_TABLE . ' f';

	// Grab appropriate forum data

	$sql = "SELECT f.*
		FROM $sql_from
		WHERE f.forum_id = $forum_id";
	$result = $db->sql_query($sql);
	$forum_data = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	if (!$forum_data)
	{
		ob_end_clean();
		return array(
			'accessible' => false,
			'contents' => '',
		);
	}

	// Permissions check
	if (!$auth->acl_gets('f_list', 'f_read', $forum_id) || ($forum_data['forum_type'] == FORUM_LINK && $forum_data['forum_link'] && !$auth->acl_get('f_read', $forum_id)))
	{
		ob_end_clean();
		return array(
			'accessible' => false,
			'contents' => '',
		);
	}

	// Is this forum a link? ... User got here either because the
	// number of clicks is being tracked or they guessed the id
	if ($forum_data['forum_type'] == FORUM_LINK && $forum_data['forum_link'])
	{
		ob_end_clean();
		return array(
			'accessible' => false,
			'contents' => '',
		);
	}

	// Build navigation links
	generate_forum_nav($forum_data);

	// Forum Rules
	if ($auth->acl_get('f_read', $forum_id))
	{
		generate_forum_rules($forum_data);
	}

	// Do we have subforums?
	$active_forum_ary = $moderators = array();

	if ($forum_data['left_id'] != $forum_data['right_id'] - 1)
	{
		list($active_forum_ary, $moderators) = display_forums($forum_data, $config['load_moderators'], $config['load_moderators']);
	}
	else
	{
		$template->assign_var('S_HAS_SUBFORUM', false);
		if ($config['load_moderators'])
		{
			get_moderators($moderators, $forum_id);
		}
	}

	// Dump out the page header and load viewforum template
	page_header($user->lang['VIEW_FORUM'] . ' - ' . $forum_data['forum_name'], true, $forum_id);

	$template->set_filenames(array(
		'body' => 'viewforum_body.html')
	);

	$template->assign_vars(array(
		'U_VIEW_FORUM'			=> append_sid("{$phpbb_root_path}viewforum.$phpEx", "f=$forum_id" . (($start == 0) ? '' : "_start=$start")) . '.html',
	));

	// Ok, if someone has only list-access, we only display the forum list.
	// We also make this circumstance available to the template in case we want to display a notice. ;)
	/*if (!$auth->acl_get('f_read', $forum_id))
	{
		ob_end_clean();
		return array(
			'accessible' => false,
			'contents' => '',
		);
	}*/

	// Is a forum specific topic count required?
	if ($forum_data['forum_topics_per_page'])
	{
		$config['topics_per_page'] = $forum_data['forum_topics_per_page'];
	}

	// Forum rules and subscription info
	$s_watching_forum = array(
		'link'			=> '',
		'title'			=> '',
		'is_watching'	=> false,
	);

	$s_forum_rules = '';
	gen_forum_auth_level('forum', $forum_id, $forum_data['forum_status']);

	// Topic ordering options
	$limit_days = array(0 => $user->lang['ALL_TOPICS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);

	$sort_by_text = array('a' => $user->lang['AUTHOR'], 't' => $user->lang['POST_TIME'], 'r' => $user->lang['REPLIES'], 's' => $user->lang['SUBJECT'], 'v' => $user->lang['VIEWS']);
	$sort_by_sql = array('a' => 't.topic_first_poster_name', 't' => 't.topic_last_post_time', 'r' => 't.topic_replies', 's' => 't.topic_title', 'v' => 't.topic_views');

	$s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';

	// Limit topics to certain time frame, obtain correct topic count
	// global announcements must not be counted, normal announcements have to
	// be counted, as forum_topics(_real) includes them
	if ($sort_days)
	{
		$min_post_time = time() - ($sort_days * 86400);

		$sql = 'SELECT COUNT(topic_id) AS num_topics
			FROM ' . TOPICS_TABLE . "
			WHERE forum_id = $forum_id
				AND ((topic_type <> " . POST_GLOBAL . " AND topic_last_post_time >= $min_post_time)
					OR topic_type = " . POST_ANNOUNCE . ")
			" . (($auth->acl_get('m_approve', $forum_id)) ? '' : 'AND topic_approved = 1');
		$result = $db->sql_query($sql);
		$topics_count = (int) $db->sql_fetchfield('num_topics');
		$db->sql_freeresult($result);

		if (isset($_POST['sort']))
		{
			$start = 0;
		}
		$sql_limit_time = "AND t.topic_last_post_time >= $min_post_time";

		// Make sure we have information about day selection ready
		$template->assign_var('S_SORT_DAYS', true);
	}
	else
	{
		$topics_count = ($auth->acl_get('m_approve', $forum_id)) ? $forum_data['forum_topics_real'] : $forum_data['forum_topics'];
		$sql_limit_time = '';
	}

	// Make sure $start is set to the last page if it exceeds the amount
	if ($start < 0 || $start > $topics_count)
	{
		$start = ($start < 0) ? 0 : floor(($topics_count - 1) / $config['topics_per_page']) * $config['topics_per_page'];
	}

	// Basic pagewide vars
	$post_alt = ($forum_data['forum_status'] == ITEM_LOCKED) ? $user->lang['FORUM_LOCKED'] : $user->lang['POST_NEW_TOPIC'];

	// Display active topics?
	$s_display_active = ($forum_data['forum_type'] == FORUM_CAT && ($forum_data['forum_flags'] & FORUM_FLAG_ACTIVE_TOPICS)) ? true : false;

	$s_search_hidden_fields = array('fid' => array($forum_id));

	if (!empty($_EXTRA_URL))
	{
		foreach ($_EXTRA_URL as $url_param)
		{
			$url_param = explode('=', $url_param, 2);
			$s_search_hidden_fields[$url_param[0]] = $url_param[1];
		}
	}

	$template->assign_vars(array(
		'MODERATORS'	=> (!empty($moderators[$forum_id])) ? implode(', ', $moderators[$forum_id]) : '',

		'POST_IMG'					=> ($forum_data['forum_status'] == ITEM_LOCKED) ? $user->img('button_topic_locked', $post_alt) : $user->img('button_topic_new', $post_alt),
		'NEWEST_POST_IMG'			=> $user->img('icon_topic_newest', 'VIEW_NEWEST_POST'),
		'LAST_POST_IMG'				=> $user->img('icon_topic_latest', 'VIEW_LATEST_POST'),
		'FOLDER_IMG'				=> $user->img('topic_read', 'NO_UNREAD_POSTS'),
		'FOLDER_UNREAD_IMG'			=> $user->img('topic_unread', 'UNREAD_POSTS'),
		'FOLDER_HOT_IMG'			=> $user->img('topic_read_hot', 'NO_UNREAD_POSTS_HOT'),
		'FOLDER_HOT_UNREAD_IMG'		=> $user->img('topic_unread_hot', 'UNREAD_POSTS_HOT'),
		'FOLDER_LOCKED_IMG'			=> $user->img('topic_read_locked', 'NO_UNREAD_POSTS_LOCKED'),
		'FOLDER_LOCKED_UNREAD_IMG'	=> $user->img('topic_unread_locked', 'UNREAD_POSTS_LOCKED'),
		'FOLDER_STICKY_IMG'			=> $user->img('sticky_read', 'POST_STICKY'),
		'FOLDER_STICKY_UNREAD_IMG'	=> $user->img('sticky_unread', 'POST_STICKY'),
		'FOLDER_ANNOUNCE_IMG'		=> $user->img('announce_read', 'POST_ANNOUNCEMENT'),
		'FOLDER_ANNOUNCE_UNREAD_IMG'=> $user->img('announce_unread', 'POST_ANNOUNCEMENT'),
		'FOLDER_MOVED_IMG'			=> $user->img('topic_moved', 'TOPIC_MOVED'),
		'REPORTED_IMG'				=> $user->img('icon_topic_reported', 'TOPIC_REPORTED'),
		'UNAPPROVED_IMG'			=> $user->img('icon_topic_unapproved', 'TOPIC_UNAPPROVED'),
		'GOTO_PAGE_IMG'				=> $user->img('icon_post_target', 'GOTO_PAGE'),

		'L_NO_TOPICS' 			=> ($forum_data['forum_status'] == ITEM_LOCKED) ? $user->lang['POST_FORUM_LOCKED'] : $user->lang['NO_TOPICS'],

		'S_DISPLAY_POST_INFO'	=> ($forum_data['forum_type'] == FORUM_POST && ($auth->acl_get('f_post', $forum_id) || $user->data['user_id'] == ANONYMOUS)) ? true : false,

		'S_IS_POSTABLE'			=> ($forum_data['forum_type'] == FORUM_POST) ? true : false,
		'S_USER_CAN_POST'		=> ($auth->acl_get('f_post', $forum_id)) ? true : false,
		'S_DISPLAY_ACTIVE'		=> $s_display_active,
		'S_SELECT_SORT_DIR'		=> $s_sort_dir,
		'S_SELECT_SORT_KEY'		=> $s_sort_key,
		'S_SELECT_SORT_DAYS'	=> $s_limit_days,
		'S_TOPIC_ICONS'			=> ($s_display_active && sizeof($active_forum_ary)) ? max($active_forum_ary['enable_icons']) : (($forum_data['enable_icons']) ? true : false),
		'S_WATCH_FORUM_LINK'	=> $s_watching_forum['link'],
		'S_WATCH_FORUM_TITLE'	=> $s_watching_forum['title'],
		'S_WATCHING_FORUM'		=> $s_watching_forum['is_watching'],
		'S_FORUM_ACTION'		=> append_sid("{$phpbb_root_path}viewforum.$phpEx", "f=$forum_id" . (($start == 0) ? '' : "_start=$start") . '.html'),
		'S_DISPLAY_SEARCHBOX'	=> ($auth->acl_get('u_search') && $auth->acl_get('f_search', $forum_id) && $config['load_search']) ? true : false,
		'S_SEARCHBOX_ACTION'	=> append_sid("{$phpbb_root_path}search.$phpEx"),
		'S_SEARCH_LOCAL_HIDDEN_FIELDS'	=> '',
		'S_SINGLE_MODERATOR'	=> (!empty($moderators[$forum_id]) && sizeof($moderators[$forum_id]) > 1) ? false : true,
		'S_IS_LOCKED'			=> ($forum_data['forum_status'] == ITEM_LOCKED) ? true : false,
		'S_VIEWFORUM'			=> true,

		'U_MCP'				=> '',
		'U_POST_NEW_TOPIC'	=> '',
		'U_VIEW_FORUM'		=> append_sid("{$phpbb_root_path}viewforum.$phpEx", "f=$forum_id" . ((strlen($u_sort_param)) ? "_$u_sort_param" : '') . (($start == 0) ? '' : "_start=$start") . '.html'),
		'U_MARK_TOPICS'		=> '',
	));

	// Grab icons
	$icons = $cache->obtain_icons();

	// Grab all topic data
	$rowset = $announcement_list = $topic_list = $global_announce_list = array();

	$sql_array = array(
		'SELECT'	=> 't.*',
		'FROM'		=> array(
			TOPICS_TABLE		=> 't'
		),
		'LEFT_JOIN'	=> array(),
	);

	$sql_approved = ($auth->acl_get('m_approve', $forum_id)) ? '' : 'AND t.topic_approved = 1';

	if ($forum_data['forum_type'] == FORUM_POST)
	{
		// Obtain announcements ... removed sort ordering, sort by time in all cases
		$sql = $db->sql_build_query('SELECT', array(
			'SELECT'	=> $sql_array['SELECT'],
			'FROM'		=> $sql_array['FROM'],
			'LEFT_JOIN'	=> $sql_array['LEFT_JOIN'],

			'WHERE'		=> 't.forum_id IN (' . $forum_id . ', 0)
				AND t.topic_type IN (' . POST_ANNOUNCE . ', ' . POST_GLOBAL . ')',

			'ORDER_BY'	=> 't.topic_time DESC',
		));
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			if (!$row['topic_approved'] && !$auth->acl_get('m_approve', $row['forum_id']))
			{
				// Do not display announcements that are waiting for approval.
				continue;
			}

			$rowset[$row['topic_id']] = $row;
			$announcement_list[] = $row['topic_id'];

			if ($row['topic_type'] == POST_GLOBAL)
			{
				$global_announce_list[$row['topic_id']] = true;
			}
			else
			{
				$topics_count--;
			}
		}
		$db->sql_freeresult($result);
	}

	// If the user is trying to reach late pages, start searching from the end
	$store_reverse = false;
	$sql_limit = $config['topics_per_page'];
	if ($start > $topics_count / 2)
	{
		$store_reverse = true;

		if ($start + $config['topics_per_page'] > $topics_count)
		{
			$sql_limit = min($config['topics_per_page'], max(1, $topics_count - $start));
		}

		// Select the sort order
		$sql_sort_order = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'ASC' : 'DESC');
		$sql_start = max(0, $topics_count - $sql_limit - $start);
	}
	else
	{
		// Select the sort order
		$sql_sort_order = $sort_by_sql[$sort_key] . ' ' . (($sort_dir == 'd') ? 'DESC' : 'ASC');
		$sql_start = $start;
	}

	if ($forum_data['forum_type'] == FORUM_POST || !sizeof($active_forum_ary))
	{
		$sql_where = 't.forum_id = ' . $forum_id;
	}
	else if (empty($active_forum_ary['exclude_forum_id']))
	{
		$sql_where = $db->sql_in_set('t.forum_id', $active_forum_ary['forum_id']);
	}
	else
	{
		$get_forum_ids = array_diff($active_forum_ary['forum_id'], $active_forum_ary['exclude_forum_id']);
		$sql_where = (sizeof($get_forum_ids)) ? $db->sql_in_set('t.forum_id', $get_forum_ids) : 't.forum_id = ' . $forum_id;
	}

	// Grab just the sorted topic ids
	$sql = 'SELECT t.topic_id
		FROM ' . TOPICS_TABLE . " t
		WHERE $sql_where
			AND t.topic_type IN (" . POST_NORMAL . ', ' . POST_STICKY . ")
			$sql_approved
			$sql_limit_time
		ORDER BY t.topic_type " . ((!$store_reverse) ? 'DESC' : 'ASC') . ', ' . $sql_sort_order;
	$result = $db->sql_query_limit($sql, $sql_limit, $sql_start);

	while ($row = $db->sql_fetchrow($result))
	{
		$topic_list[] = (int) $row['topic_id'];
	}
	$db->sql_freeresult($result);

	// For storing shadow topics
	$shadow_topic_list = array();

	if (sizeof($topic_list))
	{
		// SQL array for obtaining topics/stickies
		$sql_array = array(
			'SELECT'		=> $sql_array['SELECT'],
			'FROM'			=> $sql_array['FROM'],
			'LEFT_JOIN'		=> $sql_array['LEFT_JOIN'],

			'WHERE'			=> $db->sql_in_set('t.topic_id', $topic_list),
		);

		// If store_reverse, then first obtain topics, then stickies, else the other way around...
		// Funnily enough you typically save one query if going from the last page to the middle (store_reverse) because
		// the number of stickies are not known
		$sql = $db->sql_build_query('SELECT', $sql_array);
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			if ($row['topic_status'] == ITEM_MOVED)
			{
				$shadow_topic_list[$row['topic_moved_id']] = $row['topic_id'];
			}

			$rowset[$row['topic_id']] = $row;
		}
		$db->sql_freeresult($result);
	}

	// If we have some shadow topics, update the rowset to reflect their topic information
	if (sizeof($shadow_topic_list))
	{
		$sql = 'SELECT *
			FROM ' . TOPICS_TABLE . '
			WHERE ' . $db->sql_in_set('topic_id', array_keys($shadow_topic_list));
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$orig_topic_id = $shadow_topic_list[$row['topic_id']];

			// If the shadow topic is already listed within the rowset (happens for active topics for example), then do not include it...
			if (isset($rowset[$row['topic_id']]))
			{
				// We need to remove any trace regarding this topic. :)
				unset($rowset[$orig_topic_id]);
				unset($topic_list[array_search($orig_topic_id, $topic_list)]);
				$topics_count--;

				continue;
			}

			// Do not include those topics the user has no permission to access
			if (!$auth->acl_get('f_read', $row['forum_id']))
			{
				// We need to remove any trace regarding this topic. :)
				unset($rowset[$orig_topic_id]);
				unset($topic_list[array_search($orig_topic_id, $topic_list)]);
				$topics_count--;

				continue;
			}

			// We want to retain some values
			$row = array_merge($row, array(
				'topic_moved_id'	=> $rowset[$orig_topic_id]['topic_moved_id'],
				'topic_status'		=> $rowset[$orig_topic_id]['topic_status'],
				'topic_type'		=> $rowset[$orig_topic_id]['topic_type'],
				'topic_title'		=> $rowset[$orig_topic_id]['topic_title'],
			));

			// Shadow topics are never reported
			$row['topic_reported'] = 0;

			$rowset[$orig_topic_id] = $row;
		}
		$db->sql_freeresult($result);
	}
	unset($shadow_topic_list);

	// Ok, adjust topics count for active topics list
	if ($s_display_active)
	{
		$topics_count = 1;
	}

	// We need to readd the local announcements to the forums total topic count, otherwise the number is different from the one on the forum list
	$total_topic_count = $topics_count + sizeof($announcement_list) - sizeof($global_announce_list);

	$template->assign_vars(array(
		'PAGINATION'	=> generate_pagination(append_sid("{$phpbb_root_path}viewforum.$phpEx", "f=$forum_id"), $topics_count, $config['topics_per_page'], $start),
		'PAGE_NUMBER'	=> on_page($topics_count, $config['topics_per_page'], $start),
		'TOTAL_TOPICS'	=> ($s_display_active) ? false : (($total_topic_count == 1) ? $user->lang['VIEW_FORUM_TOPIC'] : sprintf($user->lang['VIEW_FORUM_TOPICS'], $total_topic_count)))
	);

	$topic_list = ($store_reverse) ? array_merge($announcement_list, array_reverse($topic_list)) : array_merge($announcement_list, $topic_list);
	$topic_tracking_info = $tracking_topics = array();

	// Okay, lets dump out the page ...
	if (sizeof($topic_list))
	{
		$mark_forum_read = true;
		$mark_time_forum = 0;

		// Active topics?
		if ($s_display_active && sizeof($active_forum_ary))
		{
			// Generate topic forum list...
			$topic_forum_list = array();
			foreach ($rowset as $t_id => $row)
			{
				$topic_forum_list[$row['forum_id']]['forum_mark_time'] = 0;
				$topic_forum_list[$row['forum_id']]['topics'][] = $t_id;
			}

			unset($topic_forum_list);
		}

		$s_type_switch = 0;
		foreach ($topic_list as $topic_id)
		{
			$row = &$rowset[$topic_id];

			$topic_forum_id = ($row['forum_id']) ? (int) $row['forum_id'] : $forum_id;

			// This will allow the style designer to output a different header
			// or even separate the list of announcements from sticky and normal topics
			$s_type_switch_test = ($row['topic_type'] == POST_ANNOUNCE || $row['topic_type'] == POST_GLOBAL) ? 1 : 0;

			// Replies
			$replies = ($auth->acl_get('m_approve', $topic_forum_id)) ? $row['topic_replies_real'] : $row['topic_replies'];

			$unread_topic = false;
			if ($row['topic_status'] == ITEM_MOVED)
			{
				$topic_id = $row['topic_moved_id'];
			}

			// Get folder img, topic status/type related information
			$folder_img = $folder_alt = $topic_type = '';
			topic_status($row, $replies, $unread_topic, $folder_img, $folder_alt, $topic_type);

			// Generate all the URIs ...
			$view_topic_url_params = 'f=' . $topic_forum_id . '_t=' . $topic_id;
			$view_topic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", $view_topic_url_params);

			$topic_unapproved = false;
			$posts_unapproved = false;
			$u_mcp_queue = '';

			// Send vars to template
			$template->assign_block_vars('topicrow', array(
				'FORUM_ID'					=> $topic_forum_id,
				'TOPIC_ID'					=> $topic_id,
				'TOPIC_AUTHOR'				=> get_username_string('username', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'TOPIC_AUTHOR_COLOUR'		=> get_username_string('colour', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'TOPIC_AUTHOR_FULL'			=> get_username_string('full', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'FIRST_POST_TIME'			=> $user->format_date($row['topic_time']),
				'LAST_POST_SUBJECT'			=> censor_text($row['topic_last_post_subject']),
				'LAST_POST_TIME'			=> $user->format_date($row['topic_last_post_time']),
				'LAST_VIEW_TIME'			=> $user->format_date($row['topic_last_view_time']),
				'LAST_POST_AUTHOR'			=> get_username_string('username', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
				'LAST_POST_AUTHOR_COLOUR'	=> get_username_string('colour', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
				'LAST_POST_AUTHOR_FULL'		=> get_username_string('full', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),

				'PAGINATION'		=> topic_generate_pagination($replies, $view_topic_url),
				'REPLIES'			=> $replies,
				'VIEWS'				=> $row['topic_views'],
				'TOPIC_TITLE'		=> censor_text($row['topic_title']),
				'TOPIC_TYPE'		=> $topic_type,

				'TOPIC_FOLDER_IMG'		=> $user->img($folder_img, $folder_alt),
				'TOPIC_FOLDER_IMG_SRC'	=> $user->img($folder_img, $folder_alt, false, '', 'src'),
				'TOPIC_FOLDER_IMG_ALT'	=> $user->lang[$folder_alt],
				'TOPIC_FOLDER_IMG_WIDTH'=> $user->img($folder_img, '', false, '', 'width'),
				'TOPIC_FOLDER_IMG_HEIGHT'	=> $user->img($folder_img, '', false, '', 'height'),

				'TOPIC_ICON_IMG'		=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['img'] : '',
				'TOPIC_ICON_IMG_WIDTH'	=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['width'] : '',
				'TOPIC_ICON_IMG_HEIGHT'	=> (!empty($icons[$row['icon_id']])) ? $icons[$row['icon_id']]['height'] : '',
				'ATTACH_ICON_IMG'		=> ($auth->acl_get('u_download') && $auth->acl_get('f_download', $topic_forum_id) && $row['topic_attachment']) ? $user->img('icon_topic_attach', $user->lang['TOTAL_ATTACHMENTS']) : '',
				'UNAPPROVED_IMG'		=> ($topic_unapproved || $posts_unapproved) ? $user->img('icon_topic_unapproved', ($topic_unapproved) ? 'TOPIC_UNAPPROVED' : 'POSTS_UNAPPROVED') : '',

				'S_TOPIC_TYPE'			=> $row['topic_type'],
				'S_USER_POSTED'			=> false,
				'S_UNREAD_TOPIC'		=> $unread_topic,
				'S_TOPIC_REPORTED'		=> false,
				'S_TOPIC_UNAPPROVED'	=> $topic_unapproved,
				'S_POSTS_UNAPPROVED'	=> $posts_unapproved,
				'S_HAS_POLL'			=> ($row['poll_start']) ? true : false,
				'S_POST_ANNOUNCE'		=> ($row['topic_type'] == POST_ANNOUNCE) ? true : false,
				'S_POST_GLOBAL'			=> ($row['topic_type'] == POST_GLOBAL) ? true : false,
				'S_POST_STICKY'			=> ($row['topic_type'] == POST_STICKY) ? true : false,
				'S_TOPIC_LOCKED'		=> ($row['topic_status'] == ITEM_LOCKED) ? true : false,
				'S_TOPIC_MOVED'			=> ($row['topic_status'] == ITEM_MOVED) ? true : false,

				'U_NEWEST_POST'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", $view_topic_url_params . '&amp;view=unread') . '#unread',
				'U_LAST_POST'			=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", $view_topic_url_params . '&amp;p=' . $row['topic_last_post_id']) . '.html#p' . $row['topic_last_post_id'],
				'U_LAST_POST_AUTHOR'	=> get_username_string('profile', $row['topic_last_poster_id'], $row['topic_last_poster_name'], $row['topic_last_poster_colour']),
				'U_TOPIC_AUTHOR'		=> get_username_string('profile', $row['topic_poster'], $row['topic_first_poster_name'], $row['topic_first_poster_colour']),
				'U_VIEW_TOPIC'			=> $view_topic_url . '.html',
				'U_MCP_REPORT'			=> append_sid("{$phpbb_root_path}mcp.$phpEx", 'i=reports&amp;mode=reports&amp;f=' . $topic_forum_id . '&amp;t=' . $topic_id, true, $user->session_id),
				'U_MCP_QUEUE'			=> $u_mcp_queue,

				'S_TOPIC_TYPE_SWITCH'	=> ($s_type_switch == $s_type_switch_test) ? -1 : $s_type_switch_test)
			);

			$s_type_switch = ($row['topic_type'] == POST_ANNOUNCE || $row['topic_type'] == POST_GLOBAL) ? 1 : 0;

			unset($rowset[$topic_id]);
		}
	}

	page_footer();

	$contents = ob_get_clean();

	return array(
		'accessible' => true,
		'contents' => $contents,
	);
}

function generate_topic_page($forum_id, $topic_id, $start)
{
	global $auth, $cache, $config, $db, $phpbb_root_path, $phpEx, $template, $user;
	archive_init();
	$user->add_lang('viewtopic');
	ob_start();
	
	// Initial var setup
	$post_id	= 0;
	$voted_id	= array('' => 0);

	$voted_id = (sizeof($voted_id) > 1) ? array_unique($voted_id) : $voted_id;


	$sort_days	= 0;
	$sort_key	= 't';
	$sort_dir	= 'a';

	$s_can_vote = false;


	// This rather complex gaggle of code handles querying for topics but
	// also allows for direct linking to a post (and the calculation of which
	// page the post is on and the correct display of viewtopic)
	$sql_array = array(
		'SELECT'	=> 't.*, f.*',

		'FROM'		=> array(FORUMS_TABLE => 'f'),
	);

	// The FROM-Order is quite important here, else t.* columns can not be correctly bound.
	if ($post_id)
	{
		$sql_array['SELECT'] .= ', p.post_approved, p.post_time, p.post_id';
		$sql_array['FROM'][POSTS_TABLE] = 'p';
	}

	// Topics table need to be the last in the chain
	$sql_array['FROM'][TOPICS_TABLE] = 't';

	if (!$post_id)
	{
		$sql_array['WHERE'] = "t.topic_id = $topic_id";
	}
	else
	{
		$sql_array['WHERE'] = "p.post_id = $post_id AND t.topic_id = p.topic_id";
	}

	$sql_array['WHERE'] .= ' AND (f.forum_id = t.forum_id';
	$sql_array['WHERE'] .= ' OR (t.topic_type = ' . POST_GLOBAL . "
		AND f.forum_id = $forum_id)";
	$sql_array['WHERE'] .= ')';

	// Join to forum table on topic forum_id unless topic forum_id is zero
	// whereupon we join on the forum_id passed as a parameter ... this
	// is done so navigation, forum name, etc. remain consistent with where
	// user clicked to view a global topic
	$sql = $db->sql_build_query('SELECT', $sql_array);
	$result = $db->sql_query($sql);
	$topic_data = $db->sql_fetchrow($result);
	$db->sql_freeresult($result);

	// link to unapproved post or incorrect link
	if (!$topic_data)
	{
		// If post_id was submitted, we try at least to display the topic as a last resort...
		if ($post_id && $topic_id)
		{
			redirect(append_sid("{$phpbb_root_path}viewtopic.$phpEx", "t=$topic_id" . (($forum_id) ? "&amp;f=$forum_id" : '')));
		}

		trigger_error('NO_TOPIC');
	}

	$forum_id = (int) $topic_data['forum_id'];
	// This is for determining where we are (page)
	if ($post_id)
	{
		// are we where we are supposed to be?
		if (!$topic_data['post_approved'] && !$auth->acl_get('m_approve', $topic_data['forum_id']))
		{
			// If post_id was submitted, we try at least to display the topic as a last resort...
			if ($topic_id)
			{
				redirect(append_sid("{$phpbb_root_path}viewtopic.$phpEx", "t=$topic_id" . (($forum_id) ? "&amp;f=$forum_id" : '')));
			}

			trigger_error('NO_TOPIC');
		}
		if ($post_id == $topic_data['topic_first_post_id'] || $post_id == $topic_data['topic_last_post_id'])
		{
			$check_sort = ($post_id == $topic_data['topic_first_post_id']) ? 'd' : 'a';

			if ($sort_dir == $check_sort)
			{
				$topic_data['prev_posts'] = ($auth->acl_get('m_approve', $forum_id)) ? $topic_data['topic_replies_real'] : $topic_data['topic_replies'];
			}
			else
			{
				$topic_data['prev_posts'] = 0;
			}
		}
		else
		{
			$sql = 'SELECT COUNT(p.post_id) AS prev_posts
				FROM ' . POSTS_TABLE . " p
				WHERE p.topic_id = {$topic_data['topic_id']}
					AND p.post_approved = 1";

			if ($sort_dir == 'd')
			{
				$sql .= " AND (p.post_time > {$topic_data['post_time']} OR (p.post_time = {$topic_data['post_time']} AND p.post_id >= {$topic_data['post_id']}))";
			}
			else
			{
				$sql .= " AND (p.post_time < {$topic_data['post_time']} OR (p.post_time = {$topic_data['post_time']} AND p.post_id <= {$topic_data['post_id']}))";
			}

			$result = $db->sql_query($sql);
			$row = $db->sql_fetchrow($result);
			$db->sql_freeresult($result);

			$topic_data['prev_posts'] = $row['prev_posts'] - 1;
		}
	}

	$topic_id = (int) $topic_data['topic_id'];
	//
	$topic_replies = ($auth->acl_get('m_approve', $forum_id)) ? $topic_data['topic_replies_real'] : $topic_data['topic_replies'];

	// Check sticky/announcement time limit
	if (($topic_data['topic_type'] == POST_STICKY || $topic_data['topic_type'] == POST_ANNOUNCE) && $topic_data['topic_time_limit'] && ($topic_data['topic_time'] + $topic_data['topic_time_limit']) < time())
	{
		$sql = 'UPDATE ' . TOPICS_TABLE . '
			SET topic_type = ' . POST_NORMAL . ', topic_time_limit = 0
			WHERE topic_id = ' . $topic_id;
		$db->sql_query($sql);

		$topic_data['topic_type'] = POST_NORMAL;
		$topic_data['topic_time_limit'] = 0;
	}

	if (!$topic_data['topic_approved'] && !$auth->acl_get('m_approve', $forum_id))
	{
		trigger_error('NO_TOPIC');
	}

	// Start auth check
	if (!$auth->acl_get('f_read', $forum_id))
	{
		return array(
			'accessible' => false,
			'contents' => '',
		);
	}

	// What is start equal to?
	if ($post_id)
	{
		$start = floor(($topic_data['prev_posts']) / $config['posts_per_page']) * $config['posts_per_page'];
	}

	// Post ordering options
	$limit_days = array(0 => $user->lang['ALL_POSTS'], 1 => $user->lang['1_DAY'], 7 => $user->lang['7_DAYS'], 14 => $user->lang['2_WEEKS'], 30 => $user->lang['1_MONTH'], 90 => $user->lang['3_MONTHS'], 180 => $user->lang['6_MONTHS'], 365 => $user->lang['1_YEAR']);

	$sort_by_text = array('a' => $user->lang['AUTHOR'], 't' => $user->lang['POST_TIME'], 's' => $user->lang['SUBJECT']);
	$sort_by_sql = array('a' => array('u.username_clean', 'p.post_id'), 't' => 'p.post_time', 's' => array('p.post_subject', 'p.post_id'));
	$join_user_sql = array('a' => true, 't' => false, 's' => false);

	$s_limit_days = $s_sort_key = $s_sort_dir = $u_sort_param = '';

	// Obtain correct post count and ordering SQL if user has
	// requested anything different
	if ($sort_days)
	{
		$min_post_time = time() - ($sort_days * 86400);

		$sql = 'SELECT COUNT(post_id) AS num_posts
			FROM ' . POSTS_TABLE . "
			WHERE topic_id = $topic_id
				AND post_time >= $min_post_time
			" . (($auth->acl_get('m_approve', $forum_id)) ? '' : 'AND post_approved = 1');
		$result = $db->sql_query($sql);
		$total_posts = (int) $db->sql_fetchfield('num_posts');
		$db->sql_freeresult($result);

		$limit_posts_time = "AND p.post_time >= $min_post_time ";

		if (isset($_POST['sort']))
		{
			$start = 0;
		}
	}
	else
	{
		$total_posts = $topic_replies + 1;
		$limit_posts_time = '';
	}

	// Make sure $start is set to the last page if it exceeds the amount
	if ($start < 0 || $start >= $total_posts)
	{
		$start = ($start < 0) ? 0 : floor(($total_posts - 1) / $config['posts_per_page']) * $config['posts_per_page'];
	}

	// General Viewtopic URL for return links
	$viewtopic_url = append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f={$forum_id}_t=$topic_id" . (($start == 0) ? '' : "_start=$start") . ((strlen($u_sort_param)) ? "_$u_sort_param" : ''));

	// Are we watching this topic?
	$s_watching_topic = array(
		'link'			=> '',
		'title'			=> '',
		'is_watching'	=> false,
	);

	// Grab ranks
	$ranks = $cache->obtain_ranks();

	// Grab icons
	$icons = $cache->obtain_icons();

	// Grab extensions
	$extensions = array();
	if ($topic_data['topic_attachment'])
	{
		$extensions = $cache->obtain_attach_extensions($forum_id);
	}

	// Forum rules listing
	$s_forum_rules = '';
	gen_forum_auth_level('topic', $forum_id, $topic_data['forum_status']);

	// If we've got a hightlight set pass it on to pagination.
	$pagination = generate_pagination(append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f={$forum_id}_t=$topic_id" . ((strlen($u_sort_param)) ? "_$u_sort_param" : '')), $total_posts, $config['posts_per_page'], $start);

	// Navigation links
	generate_forum_nav($topic_data);

	// Forum Rules
	generate_forum_rules($topic_data);

	// Moderators
	$forum_moderators = array();
	if ($config['load_moderators'])
	{
		get_moderators($forum_moderators, $forum_id);
	}

	// This is only used for print view so ...
	$server_path = generate_board_url() . '/';

	// Replace naughty words in title
	$topic_data['topic_title'] = censor_text($topic_data['topic_title']);

	$s_search_hidden_fields = array(
		't' => $topic_id,
		'sf' => 'msgonly',
	);

	if (!empty($_EXTRA_URL))
	{
		foreach ($_EXTRA_URL as $url_param)
		{
			$url_param = explode('=', $url_param, 2);
			$s_search_hidden_fields[$url_param[0]] = $url_param[1];
		}
	}

	// Send vars to template
	$template->assign_vars(array(
		'FORUM_ID' 		=> $forum_id,
		'FORUM_NAME' 	=> $topic_data['forum_name'],
		'FORUM_DESC'	=> generate_text_for_display($topic_data['forum_desc'], $topic_data['forum_desc_uid'], $topic_data['forum_desc_bitfield'], $topic_data['forum_desc_options']),
		'TOPIC_ID' 		=> $topic_id,
		'TOPIC_TITLE' 	=> $topic_data['topic_title'],
		'TOPIC_POSTER'	=> $topic_data['topic_poster'],

		'TOPIC_AUTHOR_FULL'		=> get_username_string('full', $topic_data['topic_poster'], $topic_data['topic_first_poster_name'], $topic_data['topic_first_poster_colour']),
		'TOPIC_AUTHOR_COLOUR'	=> get_username_string('colour', $topic_data['topic_poster'], $topic_data['topic_first_poster_name'], $topic_data['topic_first_poster_colour']),
		'TOPIC_AUTHOR'			=> get_username_string('username', $topic_data['topic_poster'], $topic_data['topic_first_poster_name'], $topic_data['topic_first_poster_colour']),

		'PAGINATION' 	=> $pagination,
		'PAGE_NUMBER' 	=> on_page($total_posts, $config['posts_per_page'], $start),
		'TOTAL_POSTS'	=> ($total_posts == 1) ? $user->lang['VIEW_TOPIC_POST'] : sprintf($user->lang['VIEW_TOPIC_POSTS'], $total_posts),
		'U_MCP' 		=> '',
		'MODERATORS'	=> (isset($forum_moderators[$forum_id]) && sizeof($forum_moderators[$forum_id])) ? implode(', ', $forum_moderators[$forum_id]) : '',

		'POST_IMG' 			=> ($topic_data['forum_status'] == ITEM_LOCKED) ? $user->img('button_topic_locked', 'FORUM_LOCKED') : $user->img('button_topic_new', 'POST_NEW_TOPIC'),
		'QUOTE_IMG' 		=> $user->img('icon_post_quote', 'REPLY_WITH_QUOTE'),
		'REPLY_IMG'			=> ($topic_data['forum_status'] == ITEM_LOCKED || $topic_data['topic_status'] == ITEM_LOCKED) ? $user->img('button_topic_locked', 'TOPIC_LOCKED') : $user->img('button_topic_reply', 'REPLY_TO_TOPIC'),
		'EDIT_IMG' 			=> $user->img('icon_post_edit', 'EDIT_POST'),
		'DELETE_IMG' 		=> $user->img('icon_post_delete', 'DELETE_POST'),
		'INFO_IMG' 			=> $user->img('icon_post_info', 'VIEW_INFO'),
		'PROFILE_IMG'		=> $user->img('icon_user_profile', 'READ_PROFILE'),
		'SEARCH_IMG' 		=> $user->img('icon_user_search', 'SEARCH_USER_POSTS'),
		'PM_IMG' 			=> $user->img('icon_contact_pm', 'SEND_PRIVATE_MESSAGE'),
		'EMAIL_IMG' 		=> $user->img('icon_contact_email', 'SEND_EMAIL'),
		'WWW_IMG' 			=> $user->img('icon_contact_www', 'VISIT_WEBSITE'),
		'ICQ_IMG' 			=> $user->img('icon_contact_icq', 'ICQ'),
		'AIM_IMG' 			=> $user->img('icon_contact_aim', 'AIM'),
		'MSN_IMG' 			=> $user->img('icon_contact_msnm', 'MSNM'),
		'YIM_IMG' 			=> $user->img('icon_contact_yahoo', 'YIM'),
		'JABBER_IMG'		=> $user->img('icon_contact_jabber', 'JABBER') ,
		'REPORT_IMG'		=> $user->img('icon_post_report', 'REPORT_POST'),
		'REPORTED_IMG'		=> $user->img('icon_topic_reported', 'POST_REPORTED'),
		'UNAPPROVED_IMG'	=> $user->img('icon_topic_unapproved', 'POST_UNAPPROVED'),
		'WARN_IMG'			=> $user->img('icon_user_warn', 'WARN_USER'),

		'S_IS_LOCKED'			=> ($topic_data['topic_status'] == ITEM_UNLOCKED && $topic_data['forum_status'] == ITEM_UNLOCKED) ? false : true,
		'S_SELECT_SORT_DIR' 	=> $s_sort_dir,
		'S_SELECT_SORT_KEY' 	=> $s_sort_key,
		'S_SELECT_SORT_DAYS' 	=> $s_limit_days,
		'S_SINGLE_MODERATOR'	=> (!empty($forum_moderators[$forum_id]) && sizeof($forum_moderators[$forum_id]) > 1) ? false : true,
		'S_TOPIC_ACTION' 		=> append_sid("{$phpbb_root_path}viewtopic.$phpEx", "f={$forum_id}_t=$topic_id" . (($start == 0) ? '' : "_start=$start")),
		'S_TOPIC_MOD' 			=> '',
		'S_MOD_ACTION' 			=> '',

		'S_VIEWTOPIC'			=> true,
		'S_DISPLAY_SEARCHBOX'	=> false,
		'S_SEARCHBOX_ACTION'	=> append_sid("{$phpbb_root_path}search.$phpEx"),
		'S_SEARCH_LOCAL_HIDDEN_FIELDS'	=> '',

		'S_DISPLAY_POST_INFO'	=> ($topic_data['forum_type'] == FORUM_POST && ($auth->acl_get('f_post', $forum_id) || $user->data['user_id'] == ANONYMOUS)) ? true : false,
		'S_DISPLAY_REPLY_INFO'	=> ($topic_data['forum_type'] == FORUM_POST && ($auth->acl_get('f_reply', $forum_id) || $user->data['user_id'] == ANONYMOUS)) ? true : false,
		'S_ENABLE_FEEDS_TOPIC'	=> false,

		'U_TOPIC'				=> "{$server_path}viewtopic.{$phpEx}_f={$forum_id}_t=$topic_id",
		'U_FORUM'				=> $server_path,
		'U_VIEW_TOPIC' 			=> $viewtopic_url,
		'U_VIEW_FORUM' 			=> append_sid("{$phpbb_root_path}viewforum.$phpEx", 'f=' . $forum_id),
		'U_VIEW_OLDER_TOPIC'	=> '',
		'U_VIEW_NEWER_TOPIC'	=> '',
		'U_PRINT_TOPIC'			=> '',
		'U_EMAIL_TOPIC'			=> '',

		'U_WATCH_TOPIC' 		=> $s_watching_topic['link'],
		'L_WATCH_TOPIC' 		=> $s_watching_topic['title'],
		'S_WATCHING_TOPIC'		=> $s_watching_topic['is_watching'],

		'U_BOOKMARK_TOPIC'		=> '',
		'L_BOOKMARK_TOPIC'		=> $user->lang['BOOKMARK_TOPIC'],

		'U_POST_NEW_TOPIC' 		=> '',
		'U_POST_REPLY_TOPIC' 	=> '',
		'U_BUMP_TOPIC'			=> '')
	);

	// Does this topic contain a poll?
	if (!empty($topic_data['poll_start']))
	{
		$sql = 'SELECT o.*, p.bbcode_bitfield, p.bbcode_uid
			FROM ' . POLL_OPTIONS_TABLE . ' o, ' . POSTS_TABLE . " p
			WHERE o.topic_id = $topic_id
				AND p.post_id = {$topic_data['topic_first_post_id']}
				AND p.topic_id = o.topic_id
			ORDER BY o.poll_option_id";
		$result = $db->sql_query($sql);

		$poll_info = array();
		while ($row = $db->sql_fetchrow($result))
		{
			$poll_info[] = $row;
		}
		$db->sql_freeresult($result);

		$cur_voted_id = array();

		// Can not vote at all if no vote permission
		$s_can_vote = false;
		$s_display_results = true;

		$poll_total = 0;
		foreach ($poll_info as $poll_option)
		{
			$poll_total += $poll_option['poll_option_total'];
		}

		if ($poll_info[0]['bbcode_bitfield'])
		{
			$poll_bbcode = new bbcode();
		}
		else
		{
			$poll_bbcode = false;
		}

		for ($i = 0, $size = sizeof($poll_info); $i < $size; $i++)
		{
			$poll_info[$i]['poll_option_text'] = censor_text($poll_info[$i]['poll_option_text']);

			if ($poll_bbcode !== false)
			{
				$poll_bbcode->bbcode_second_pass($poll_info[$i]['poll_option_text'], $poll_info[$i]['bbcode_uid'], $poll_option['bbcode_bitfield']);
			}

			$poll_info[$i]['poll_option_text'] = bbcode_nl2br($poll_info[$i]['poll_option_text']);
			$poll_info[$i]['poll_option_text'] = smiley_text($poll_info[$i]['poll_option_text']);
		}

		$topic_data['poll_title'] = censor_text($topic_data['poll_title']);

		if ($poll_bbcode !== false)
		{
			$poll_bbcode->bbcode_second_pass($topic_data['poll_title'], $poll_info[0]['bbcode_uid'], $poll_info[0]['bbcode_bitfield']);
		}

		$topic_data['poll_title'] = bbcode_nl2br($topic_data['poll_title']);
		$topic_data['poll_title'] = smiley_text($topic_data['poll_title']);

		unset($poll_bbcode);

		foreach ($poll_info as $poll_option)
		{
			$option_pct = ($poll_total > 0) ? $poll_option['poll_option_total'] / $poll_total : 0;
			$option_pct_txt = sprintf("%.1d%%", round($option_pct * 100));

			$template->assign_block_vars('poll_option', array(
				'POLL_OPTION_ID' 		=> $poll_option['poll_option_id'],
				'POLL_OPTION_CAPTION' 	=> $poll_option['poll_option_text'],
				'POLL_OPTION_RESULT' 	=> $poll_option['poll_option_total'],
				'POLL_OPTION_PERCENT' 	=> $option_pct_txt,
				'POLL_OPTION_PCT'		=> round($option_pct * 100),
				'POLL_OPTION_IMG' 		=> $user->img('poll_center', $option_pct_txt, round($option_pct * 250)),
				'POLL_OPTION_VOTED'		=> (in_array($poll_option['poll_option_id'], $cur_voted_id)) ? true : false)
			);
		}

		$poll_end = $topic_data['poll_length'] + $topic_data['poll_start'];

		$template->assign_vars(array(
			'POLL_QUESTION'		=> $topic_data['poll_title'],
			'TOTAL_VOTES' 		=> $poll_total,
			'POLL_LEFT_CAP_IMG'	=> $user->img('poll_left'),
			'POLL_RIGHT_CAP_IMG'=> $user->img('poll_right'),

			'L_MAX_VOTES'		=> ($topic_data['poll_max_options'] == 1) ? $user->lang['MAX_OPTION_SELECT'] : sprintf($user->lang['MAX_OPTIONS_SELECT'], $topic_data['poll_max_options']),
			'L_POLL_LENGTH'		=> ($topic_data['poll_length']) ? sprintf($user->lang[($poll_end > time()) ? 'POLL_RUN_TILL' : 'POLL_ENDED_AT'], $user->format_date($poll_end)) : '',

			'S_HAS_POLL'		=> true,
			'S_CAN_VOTE'		=> $s_can_vote,
			'S_DISPLAY_RESULTS'	=> $s_display_results,
			'S_IS_MULTI_CHOICE'	=> ($topic_data['poll_max_options'] > 1) ? true : false,
			'S_POLL_ACTION'		=> $viewtopic_url,

			'U_VIEW_RESULTS'	=> $viewtopic_url . '&amp;view=viewpoll')
		);

		unset($poll_end, $poll_info, $voted_id);
	}

	// If the user is trying to reach the second half of the topic, fetch it starting from the end
	$store_reverse = false;
	$sql_limit = $config['posts_per_page'];
	$sql_sort_order = $direction = '';

	if ($start > $total_posts / 2)
	{
		$store_reverse = true;

		if ($start + $config['posts_per_page'] > $total_posts)
		{
			$sql_limit = min($config['posts_per_page'], max(1, $total_posts - $start));
		}

		// Select the sort order
		$direction = (($sort_dir == 'd') ? 'ASC' : 'DESC');
		$sql_start = max(0, $total_posts - $sql_limit - $start);
	}
	else
	{
		// Select the sort order
		$direction = (($sort_dir == 'd') ? 'DESC' : 'ASC');
		$sql_start = $start;
	}

	if (is_array($sort_by_sql[$sort_key]))
	{
		$sql_sort_order = implode(' ' . $direction . ', ', $sort_by_sql[$sort_key]) . ' ' . $direction;
	}
	else
	{
		$sql_sort_order = $sort_by_sql[$sort_key] . ' ' . $direction;
	}

	// Container for user details, only process once
	$post_list = $user_cache = $id_cache = $attachments = $attach_list = $rowset = $update_count = $post_edit_list = array();
	$has_attachments = $display_notice = false;
	$bbcode_bitfield = '';
	$i = $i_total = 0;

	// Go ahead and pull all data for this topic
	$sql = 'SELECT p.post_id
		FROM ' . POSTS_TABLE . ' p' . (($join_user_sql[$sort_key]) ? ', ' . USERS_TABLE . ' u': '') . "
		WHERE p.topic_id = $topic_id
			" . ((!$auth->acl_get('m_approve', $forum_id)) ? 'AND p.post_approved = 1' : '') . "
			" . (($join_user_sql[$sort_key]) ? 'AND u.user_id = p.poster_id': '') . "
			$limit_posts_time
		ORDER BY $sql_sort_order";
	$result = $db->sql_query_limit($sql, $sql_limit, $sql_start);

	$i = ($store_reverse) ? $sql_limit - 1 : 0;
	while ($row = $db->sql_fetchrow($result))
	{
		$post_list[$i] = (int) $row['post_id'];
		($store_reverse) ? $i-- : $i++;
	}
	$db->sql_freeresult($result);

	if (!sizeof($post_list))
	{
		if ($sort_days)
		{
			trigger_error('NO_POSTS_TIME_FRAME');
		}
		else
		{
			trigger_error('NO_TOPIC');
		}
	}

	// Holding maximum post time for marking topic read
	// We need to grab it because we do reverse ordering sometimes
	$max_post_time = 0;

	$sql = $db->sql_build_query('SELECT', array(
		'SELECT'	=> 'u.*, z.friend, z.foe, p.*',

		'FROM'		=> array(
			USERS_TABLE		=> 'u',
			POSTS_TABLE		=> 'p',
		),

		'LEFT_JOIN'	=> array(
			array(
				'FROM'	=> array(ZEBRA_TABLE => 'z'),
				'ON'	=> 'z.user_id = ' . $user->data['user_id'] . ' AND z.zebra_id = p.poster_id'
			)
		),

		'WHERE'		=> $db->sql_in_set('p.post_id', $post_list) . '
			AND u.user_id = p.poster_id'
	));

	$result = $db->sql_query($sql);

	$now = phpbb_gmgetdate(time() + $user->timezone + $user->dst);

	// Posts are stored in the $rowset array while $attach_list, $user_cache
	// and the global bbcode_bitfield are built
	while ($row = $db->sql_fetchrow($result))
	{
		// Set max_post_time
		if ($row['post_time'] > $max_post_time)
		{
			$max_post_time = $row['post_time'];
		}

		$poster_id = (int) $row['poster_id'];

		// Does post have an attachment? If so, add it to the list
		if ($row['post_attachment'] && $config['allow_attachments'])
		{
			$attach_list[] = (int) $row['post_id'];

			if ($row['post_approved'])
			{
				$has_attachments = true;
			}
		}

		$rowset[$row['post_id']] = array(
			'hide_post'			=> false,

			'post_id'			=> $row['post_id'],
			'post_time'			=> $row['post_time'],
			'user_id'			=> $row['user_id'],
			'username'			=> $row['username'],
			'user_colour'		=> $row['user_colour'],
			'topic_id'			=> $row['topic_id'],
			'forum_id'			=> $row['forum_id'],
			'post_subject'		=> $row['post_subject'],
			'post_edit_count'	=> $row['post_edit_count'],
			'post_edit_time'	=> $row['post_edit_time'],
			'post_edit_reason'	=> $row['post_edit_reason'],
			'post_edit_user'	=> $row['post_edit_user'],
			'post_edit_locked'	=> $row['post_edit_locked'],

			// Make sure the icon actually exists
			'icon_id'			=> (isset($icons[$row['icon_id']]['img'], $icons[$row['icon_id']]['height'], $icons[$row['icon_id']]['width'])) ? $row['icon_id'] : 0,
			'post_attachment'	=> $row['post_attachment'],
			'post_approved'		=> $row['post_approved'],
			'post_reported'		=> $row['post_reported'],
			'post_username'		=> $row['post_username'],
			'post_text'			=> $row['post_text'],
			'bbcode_uid'		=> $row['bbcode_uid'],
			'bbcode_bitfield'	=> $row['bbcode_bitfield'],
			'enable_smilies'	=> $row['enable_smilies'],
			'enable_sig'		=> $row['enable_sig'],
			'friend'			=> $row['friend'],
			'foe'				=> $row['foe'],
		);

		// Define the global bbcode bitfield, will be used to load bbcodes
		$bbcode_bitfield = $bbcode_bitfield | base64_decode($row['bbcode_bitfield']);

		// Is a signature attached? Are we going to display it?
		if ($row['enable_sig'] && $config['allow_sig'] && $user->optionget('viewsigs'))
		{
			$bbcode_bitfield = $bbcode_bitfield | base64_decode($row['user_sig_bbcode_bitfield']);
		}

		// Cache various user specific data ... so we don't have to recompute
		// this each time the same user appears on this page
		if (!isset($user_cache[$poster_id]))
		{
			if ($poster_id == ANONYMOUS)
			{
				$user_cache[$poster_id] = array(
					'joined'		=> '',
					'posts'			=> '',
					'from'			=> '',

					'sig'					=> '',
					'sig_bbcode_uid'		=> '',
					'sig_bbcode_bitfield'	=> '',

					'online'			=> false,
					'avatar'			=> get_user_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']),
					'rank_title'		=> '',
					'rank_image'		=> '',
					'rank_image_src'	=> '',
					'sig'				=> '',
					'profile'			=> '',
					'pm'				=> '',
					'email'				=> '',
					'www'				=> '',
					'icq_status_img'	=> '',
					'icq'				=> '',
					'aim'				=> '',
					'msn'				=> '',
					'yim'				=> '',
					'jabber'			=> '',
					'search'			=> '',
					'age'				=> '',

					'username'			=> $row['username'],
					'user_colour'		=> $row['user_colour'],

					'warnings'			=> 0,
					'allow_pm'			=> 0,
				);

				get_user_rank($row['user_rank'], false, $user_cache[$poster_id]['rank_title'], $user_cache[$poster_id]['rank_image'], $user_cache[$poster_id]['rank_image_src']);
			}
			else
			{
				$user_sig = '';

				// We add the signature to every posters entry because enable_sig is post dependant
				if ($row['user_sig'] && $config['allow_sig'] && $user->optionget('viewsigs'))
				{
					$user_sig = $row['user_sig'];
				}

				$id_cache[] = $poster_id;

				$user_cache[$poster_id] = array(
					'joined'		=> $user->format_date($row['user_regdate']),
					'posts'			=> $row['user_posts'],
					'warnings'		=> (isset($row['user_warnings'])) ? $row['user_warnings'] : 0,
					'from'			=> (!empty($row['user_from'])) ? $row['user_from'] : '',

					'sig'					=> $user_sig,
					'sig_bbcode_uid'		=> (!empty($row['user_sig_bbcode_uid'])) ? $row['user_sig_bbcode_uid'] : '',
					'sig_bbcode_bitfield'	=> (!empty($row['user_sig_bbcode_bitfield'])) ? $row['user_sig_bbcode_bitfield'] : '',

					'viewonline'	=> $row['user_allow_viewonline'],
					'allow_pm'		=> $row['user_allow_pm'],

					'avatar'		=> get_user_avatar($row['user_avatar'], $row['user_avatar_type'], $row['user_avatar_width'], $row['user_avatar_height']),
					'age'			=> '',

					'rank_title'		=> '',
					'rank_image'		=> '',
					'rank_image_src'	=> '',

					'username'			=> $row['username'],
					'user_colour'		=> $row['user_colour'],

					'online'		=> false,
					'profile'		=> append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=viewprofile&amp;u=$poster_id"),
					'www'			=> $row['user_website'],
					'aim'			=> ($row['user_aim'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=aim&amp;u=$poster_id") : '',
					'msn'			=> ($row['user_msnm'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=msnm&amp;u=$poster_id") : '',
					'yim'			=> ($row['user_yim']) ? 'http://edit.yahoo.com/config/send_webmesg?.target=' . urlencode($row['user_yim']) . '&amp;.src=pg' : '',
					'jabber'		=> ($row['user_jabber'] && $auth->acl_get('u_sendim')) ? append_sid("{$phpbb_root_path}memberlist.$phpEx", "mode=contact&amp;action=jabber&amp;u=$poster_id") : '',
					'search'		=> ($auth->acl_get('u_search')) ? append_sid("{$phpbb_root_path}search.$phpEx", "author_id=$poster_id&amp;sr=posts") : '',

					'author_full'		=> get_username_string('full', $poster_id, $row['username'], $row['user_colour']),
					'author_colour'		=> get_username_string('colour', $poster_id, $row['username'], $row['user_colour']),
					'author_username'	=> get_username_string('username', $poster_id, $row['username'], $row['user_colour']),
					'author_profile'	=> get_username_string('profile', $poster_id, $row['username'], $row['user_colour']),
				);

				get_user_rank($row['user_rank'], $row['user_posts'], $user_cache[$poster_id]['rank_title'], $user_cache[$poster_id]['rank_image'], $user_cache[$poster_id]['rank_image_src']);

				$user_cache[$poster_id]['email'] = '';

				if (!empty($row['user_icq']))
				{
					$user_cache[$poster_id]['icq'] = 'http://www.icq.com/people/' . urlencode($row['user_icq']) . '/';
					$user_cache[$poster_id]['icq_status_img'] = '<img src="http://web.icq.com/whitepages/online?icq=' . $row['user_icq'] . '&amp;img=5" width="18" height="18" alt="" />';
				}
				else
				{
					$user_cache[$poster_id]['icq_status_img'] = '';
					$user_cache[$poster_id]['icq'] = '';
				}
			}
		}
	}
	$db->sql_freeresult($result);

	// Load custom profile fields
	if ($config['load_cpf_viewtopic'])
	{
		if (!class_exists('custom_profile'))
		{
			include($phpbb_root_path . 'includes/functions_profile_fields.' . $phpEx);
		}
		$cp = new custom_profile();

		// Grab all profile fields from users in id cache for later use - similar to the poster cache
		$profile_fields_tmp = $cp->generate_profile_fields_template('grab', $id_cache);

		// filter out fields not to be displayed on viewtopic. Yes, it's a hack, but this shouldn't break any MODs.
		$profile_fields_cache = array();
		foreach ($profile_fields_tmp as $profile_user_id => $profile_fields)
		{
			$profile_fields_cache[$profile_user_id] = array();
			foreach ($profile_fields as $used_ident => $profile_field)
			{
				if ($profile_field['data']['field_show_on_vt'])
				{
					$profile_fields_cache[$profile_user_id][$used_ident] = $profile_field;
				}
			}
		}
		unset($profile_fields_tmp);
	}
	unset($id_cache);

	// Pull attachment data
	if (sizeof($attach_list))
	{
		$sql = 'SELECT *
			FROM ' . ATTACHMENTS_TABLE . '
			WHERE ' . $db->sql_in_set('post_msg_id', $attach_list) . '
				AND in_message = 0
			ORDER BY filetime DESC, post_msg_id ASC';
		$result = $db->sql_query($sql);

		while ($row = $db->sql_fetchrow($result))
		{
			$attachments[$row['post_msg_id']][] = $row;
		}
		$db->sql_freeresult($result);

		// No attachments exist, but post table thinks they do so go ahead and reset post_attach flags
		if (!sizeof($attachments))
		{
			// We need to update the topic indicator too if the complete topic is now without an attachment
			if (sizeof($rowset) != $total_posts)
			{
				// Not all posts are displayed so we query the db to find if there's any attachment for this topic
				$sql = 'SELECT a.post_msg_id as post_id
					FROM ' . ATTACHMENTS_TABLE . ' a, ' . POSTS_TABLE . " p
					WHERE p.topic_id = $topic_id
						AND p.post_approved = 1
						AND p.topic_id = a.topic_id";
				$result = $db->sql_query_limit($sql, 1);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);
			}
		}
		else if ($has_attachments && !$topic_data['topic_attachment'])
		{
			$topic_data['topic_attachment'] = 1;
		}
	}

	// Instantiate BBCode if need be
	if ($bbcode_bitfield !== '')
	{
		$bbcode = new bbcode(base64_encode($bbcode_bitfield));
	}

	$i_total = sizeof($rowset) - 1;
	$prev_post_id = '';

	$template->assign_vars(array(
		'S_NUM_POSTS' => sizeof($post_list))
	);

	// Output the posts
	$first_unread = $post_unread = false;
	for ($i = 0, $end = sizeof($post_list); $i < $end; ++$i)
	{
		// A non-existing rowset only happens if there was no user present for the entered poster_id
		// This could be a broken posts table.
		if (!isset($rowset[$post_list[$i]]))
		{
			continue;
		}

		$row =& $rowset[$post_list[$i]];
		$poster_id = $row['user_id'];

		// End signature parsing, only if needed
		if ($user_cache[$poster_id]['sig'] && $row['enable_sig'] && empty($user_cache[$poster_id]['sig_parsed']))
		{
			$user_cache[$poster_id]['sig'] = censor_text($user_cache[$poster_id]['sig']);

			if ($user_cache[$poster_id]['sig_bbcode_bitfield'])
			{
				$bbcode->bbcode_second_pass($user_cache[$poster_id]['sig'], $user_cache[$poster_id]['sig_bbcode_uid'], $user_cache[$poster_id]['sig_bbcode_bitfield']);
			}

			$user_cache[$poster_id]['sig'] = bbcode_nl2br($user_cache[$poster_id]['sig']);
			$user_cache[$poster_id]['sig'] = smiley_text($user_cache[$poster_id]['sig']);
			$user_cache[$poster_id]['sig_parsed'] = true;
		}

		// Parse the message and subject
		$message = censor_text($row['post_text']);

		// Second parse bbcode here
		if ($row['bbcode_bitfield'])
		{
			$bbcode->bbcode_second_pass($message, $row['bbcode_uid'], $row['bbcode_bitfield']);
		}

		$message = bbcode_nl2br($message);
		$message = smiley_text($message);

		if (!empty($attachments[$row['post_id']]))
		{
			parse_attachments($forum_id, $message, $attachments[$row['post_id']], $update_count);
		}

		// Replace naughty words such as farty pants
		$row['post_subject'] = censor_text($row['post_subject']);

		// Editing information
		if (($row['post_edit_count'] && $config['display_last_edited']) || $row['post_edit_reason'])
		{
			// Get usernames for all following posts if not already stored
			if (!sizeof($post_edit_list) && ($row['post_edit_reason'] || ($row['post_edit_user'] && !isset($user_cache[$row['post_edit_user']]))))
			{
				// Remove all post_ids already parsed (we do not have to check them)
				$post_storage_list = (!$store_reverse) ? array_slice($post_list, $i) : array_slice(array_reverse($post_list), $i);

				$sql = 'SELECT DISTINCT u.user_id, u.username, u.user_colour
					FROM ' . POSTS_TABLE . ' p, ' . USERS_TABLE . ' u
					WHERE ' . $db->sql_in_set('p.post_id', $post_storage_list) . '
						AND p.post_edit_count <> 0
						AND p.post_edit_user <> 0
						AND p.post_edit_user = u.user_id';
				$result2 = $db->sql_query($sql);
				while ($user_edit_row = $db->sql_fetchrow($result2))
				{
					$post_edit_list[$user_edit_row['user_id']] = $user_edit_row;
				}
				$db->sql_freeresult($result2);

				unset($post_storage_list);
			}

			$l_edit_time_total = ($row['post_edit_count'] == 1) ? $user->lang['EDITED_TIME_TOTAL'] : $user->lang['EDITED_TIMES_TOTAL'];

			if ($row['post_edit_reason'])
			{
				// User having edited the post also being the post author?
				if (!$row['post_edit_user'] || $row['post_edit_user'] == $poster_id)
				{
					$display_username = get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']);
				}
				else
				{
					$display_username = get_username_string('full', $row['post_edit_user'], $post_edit_list[$row['post_edit_user']]['username'], $post_edit_list[$row['post_edit_user']]['user_colour']);
				}

				$l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($row['post_edit_time'], false, true), $row['post_edit_count']);
			}
			else
			{
				if ($row['post_edit_user'] && !isset($user_cache[$row['post_edit_user']]))
				{
					$user_cache[$row['post_edit_user']] = $post_edit_list[$row['post_edit_user']];
				}

				// User having edited the post also being the post author?
				if (!$row['post_edit_user'] || $row['post_edit_user'] == $poster_id)
				{
					$display_username = get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']);
				}
				else
				{
					$display_username = get_username_string('full', $row['post_edit_user'], $user_cache[$row['post_edit_user']]['username'], $user_cache[$row['post_edit_user']]['user_colour']);
				}

				$l_edited_by = sprintf($l_edit_time_total, $display_username, $user->format_date($row['post_edit_time'], false, true), $row['post_edit_count']);
			}
		}
		else
		{
			$l_edited_by = '';
		}

		// Bump information
		if ($topic_data['topic_bumped'] && $row['post_id'] == $topic_data['topic_last_post_id'] && isset($user_cache[$topic_data['topic_bumper']]) )
		{
			// It is safe to grab the username from the user cache array, we are at the last
			// post and only the topic poster and last poster are allowed to bump.
			// Admins and mods are bound to the above rules too...
			$l_bumped_by = sprintf($user->lang['BUMPED_BY'], $user_cache[$topic_data['topic_bumper']]['username'], $user->format_date($topic_data['topic_last_post_time'], false, true));
		}
		else
		{
			$l_bumped_by = '';
		}

		$cp_row = array();

		//
		if ($config['load_cpf_viewtopic'])
		{
			$cp_row = (isset($profile_fields_cache[$poster_id])) ? $cp->generate_profile_fields_template('show', false, $profile_fields_cache[$poster_id]) : array();
		}

		$post_unread = false;

		$s_first_unread = false;
		if (!$first_unread && $post_unread)
		{
			$s_first_unread = $first_unread = true;
		}


		$postrow = array(
			'POST_AUTHOR_FULL'		=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_full'] : get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
			'POST_AUTHOR_COLOUR'	=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_colour'] : get_username_string('colour', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
			'POST_AUTHOR'			=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_username'] : get_username_string('username', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),
			'U_POST_AUTHOR'			=> ($poster_id != ANONYMOUS) ? $user_cache[$poster_id]['author_profile'] : get_username_string('profile', $poster_id, $row['username'], $row['user_colour'], $row['post_username']),

			'RANK_TITLE'		=> $user_cache[$poster_id]['rank_title'],
			'RANK_IMG'			=> $user_cache[$poster_id]['rank_image'],
			'RANK_IMG_SRC'		=> $user_cache[$poster_id]['rank_image_src'],
			'POSTER_JOINED'		=> $user_cache[$poster_id]['joined'],
			'POSTER_POSTS'		=> $user_cache[$poster_id]['posts'],
			'POSTER_FROM'		=> $user_cache[$poster_id]['from'],
			'POSTER_AVATAR'		=> $user_cache[$poster_id]['avatar'],
			'POSTER_WARNINGS'	=> $user_cache[$poster_id]['warnings'],
			'POSTER_AGE'		=> $user_cache[$poster_id]['age'],

			'POST_DATE'			=> $user->format_date($row['post_time'], false, false),
			'POST_SUBJECT'		=> $row['post_subject'],
			'MESSAGE'			=> $message,
			'SIGNATURE'			=> ($row['enable_sig']) ? $user_cache[$poster_id]['sig'] : '',
			'EDITED_MESSAGE'	=> $l_edited_by,
			'EDIT_REASON'		=> $row['post_edit_reason'],
			'BUMPED_MESSAGE'	=> $l_bumped_by,

			'MINI_POST_IMG'			=> $user->img('icon_post_target', 'POST'),
			'POST_ICON_IMG'			=> ($topic_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['img'] : '',
			'POST_ICON_IMG_WIDTH'	=> ($topic_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['width'] : '',
			'POST_ICON_IMG_HEIGHT'	=> ($topic_data['enable_icons'] && !empty($row['icon_id'])) ? $icons[$row['icon_id']]['height'] : '',
			'ICQ_STATUS_IMG'		=> $user_cache[$poster_id]['icq_status_img'],
			'ONLINE_IMG'			=> $user->img('icon_user_offline', 'OFFLINE'),
			'S_ONLINE'				=> false,

			'U_EDIT'			=> '',
			'U_QUOTE'			=> '',
			'U_INFO'			=> '',
			'U_DELETE'			=> '',

			'U_PROFILE'		=> $user_cache[$poster_id]['profile'],
			'U_SEARCH'		=> $user_cache[$poster_id]['search'],
			'U_PM'			=> '',
			'U_EMAIL'		=> $user_cache[$poster_id]['email'],
			'U_WWW'			=> $user_cache[$poster_id]['www'],
			'U_ICQ'			=> $user_cache[$poster_id]['icq'],
			'U_AIM'			=> $user_cache[$poster_id]['aim'],
			'U_MSN'			=> $user_cache[$poster_id]['msn'],
			'U_YIM'			=> $user_cache[$poster_id]['yim'],
			'U_JABBER'		=> $user_cache[$poster_id]['jabber'],

			'U_REPORT'			=> '',
			'U_MCP_REPORT'		=> '',
			'U_MCP_APPROVE'		=> '',
			'U_MINI_POST'		=> '',
			'U_NEXT_POST_ID'	=> ($i < $i_total && isset($rowset[$post_list[$i + 1]])) ? $rowset[$post_list[$i + 1]]['post_id'] : '',
			'U_PREV_POST_ID'	=> $prev_post_id,
			'U_NOTES'			=> '',
			'U_WARN'			=> '',

			'POST_ID'			=> $row['post_id'],
			'POST_NUMBER'		=> $i + $start + 1,
			'POSTER_ID'			=> $poster_id,

			'S_HAS_ATTACHMENTS'	=> (!empty($attachments[$row['post_id']])) ? true : false,
			'S_POST_UNAPPROVED'	=> ($row['post_approved']) ? false : true,
			'S_POST_REPORTED'	=> ($row['post_reported'] && $auth->acl_get('m_report', $forum_id)) ? true : false,
			'S_DISPLAY_NOTICE'	=> $display_notice && $row['post_attachment'],
			'S_FRIEND'			=> ($row['friend']) ? true : false,
			'S_UNREAD_POST'		=> $post_unread,
			'S_FIRST_UNREAD'	=> $s_first_unread,
			'S_CUSTOM_FIELDS'	=> (isset($cp_row['row']) && sizeof($cp_row['row'])) ? true : false,
			'S_TOPIC_POSTER'	=> ($topic_data['topic_poster'] == $poster_id) ? true : false,

			'S_IGNORE_POST'		=> ($row['hide_post']) ? true : false,
			'L_IGNORE_POST'		=> ($row['hide_post']) ? sprintf($user->lang['POST_BY_FOE'], get_username_string('full', $poster_id, $row['username'], $row['user_colour'], $row['post_username']), '<a href="' . $viewtopic_url . "&amp;p={$row['post_id']}&amp;view=show#p{$row['post_id']}" . '">', '</a>') : '',
		);

		if (isset($cp_row['row']) && sizeof($cp_row['row']))
		{
			$postrow = array_merge($postrow, $cp_row['row']);
		}

		// Dump vars into template
		$template->assign_block_vars('postrow', $postrow);

		if (!empty($cp_row['blockrow']))
		{
			foreach ($cp_row['blockrow'] as $field_data)
			{
				$template->assign_block_vars('postrow.custom_fields', $field_data);
			}
		}

		// Display not already displayed Attachments for this post, we already parsed them. ;)
		if (!empty($attachments[$row['post_id']]))
		{
			foreach ($attachments[$row['post_id']] as $attachment)
			{
				$template->assign_block_vars('postrow.attachment', array(
					'DISPLAY_ATTACHMENT'	=> $attachment)
				);
			}
		}

		$prev_post_id = $row['post_id'];

		unset($rowset[$post_list[$i]]);
		unset($attachments[$row['post_id']]);
	}
	unset($rowset, $user_cache);


	$template->assign_vars(array(
		'U_VIEW_UNREAD_POST'	=> '',
	));





	// Output the page
	page_header($user->lang['VIEW_TOPIC'] . ' - ' . $topic_data['topic_title'], true, $forum_id);

	$template->set_filenames(array(
		'body' => 'viewtopic_body.html')
	);

	page_footer();

	$contents = ob_get_clean();

	return array(
		'accessible' => true,
		'contents' => $contents,
	);
}

function archive_init()
{
	global $template, $user;
	//unset($template);
	//$template = new template();
	$template->destroy();
	$template->set_template();

}