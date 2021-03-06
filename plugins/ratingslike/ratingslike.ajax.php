<?php
/* ====================
[BEGIN_COT_EXT]
Hooks=ajax
[END_COT_EXT]
==================== */

defined('COT_CODE') or die('Wrong URL');

require_once cot_incfile('ratings', 'plug');
require_once cot_incfile('ratingslike', 'plug');
require_once cot_incfile('page', 'module');
require_once cot_incfile('comments', 'plug');

$area = cot_import('area', 'G', 'ALP');
$code = cot_import('code', 'G', 'ALP');
$cat = cot_import('cat', 'G', 'TXT');
$inr = cot_import('inr', 'G', 'ALP');

$newrate = cot_import('newrate', 'G', 'INT');

$enabled = cot_ratings_enabled($area, $cat, $code);
list($auth_read, $auth_write, $auth_admin) = cot_auth('plug', 'ratingslike');

if ($inr == 'send' && ($newrate == -1 || $newrate == 1) && $auth_write && $enabled)
{

	// Get current item rating
	$sql = $db->query("SELECT * FROM $db_ratings
		WHERE rating_area = ? AND rating_code = ? LIMIT 1",
		array($area, $code));

	if ($row = $sql->fetch())
	{
		$rating_summ = $row['rating_summ'];
		$item_has_rating = true;

		$rating_cntround = $rating_summ;
	}
	else
	{
		$item_has_rating = false;
		$rating_summ = 0;
		$rating_cntround = 0;
	}

	// Check if this user has already voted
	$already_rated = $db->query("SELECT COUNT(*) FROM $db_rated
		WHERE rated_userid = ? AND rated_area = ? AND rated_code = ?",
		array($usr['id'], $area, $code))->fetchColumn();

	/* == Hook for the plugins == */
	foreach (cot_getextplugins('ratingslike.send.first') as $pl)
	{
		include $pl;
	}
	/* ===== */

	if (!$cfg['plugin']['ratings']['ratings_allowchange'] && $already_rated)
	{
		// Can't vote twice
		cot_die_message(403, TRUE);
		exit;
	}

	// Delete previous votes if any
	if ($already_rated)
	{
		$db->delete($db_rated, 'rated_userid = ? AND rated_area = ? AND rated_code = ?',
			array($usr['id'], $area, $code));
	}

	// Insert new rating for the item if none is present
	if (!$item_has_rating)
	{
		$db->insert($db_ratings, array(
			'rating_code' => $code,
			'rating_area' => $area,
			'rating_state' => 0,
			'rating_summ' => (int) $newrate,
			'rating_creationdate' => (int) $sys['now'],
			'rating_text' => ''
		));
	}

	// Insert new vote and recalculate average value
	$db->insert($db_rated, array(
		'rated_code' => $code,
		'rated_area' => $area,
		'rated_userid' => $usr['id'],
		'rated_value' => (int) $newrate
	));
	$rating_voters = $db->query("SELECT COUNT(*) FROM $db_rated
		WHERE rated_area = ? AND rated_code = ?",
		array($area, $code))->fetchColumn();

	if ($rating_voters > 0)
	{
		$ratingnewsumm = $db->query("SELECT SUM(rated_value) FROM $db_rated
			WHERE rated_area = ? AND rated_code = ?",
			array($area, $code))->fetchColumn();
		$db->update($db_ratings, array('rating_summ' => $ratingnewsumm),
			'rating_area = ? AND rating_code = ?', array($area, $code));
	}
	else
	{
		$ratingnewsumm = 0;
	}
	
	$itemid = str_replace('like_', '', $code);
	
	switch($area)
	{
		case 'page':
		$db->query("UPDATE $db_pages
		SET page_rating=".(int)$ratingnewsumm."
		WHERE page_id = ".(int)$itemid."");
		break;
		
		case 'com':		
		$sql = $db->query("UPDATE $db_com
		SET com_rating=".(int)$ratingnewsumm."
		WHERE com_id = ".(int)$itemid);
		break;
	}
	
	/* == Hook for the plugins == */
	foreach (cot_getextplugins('ratingslike.send.done') as $pl)
	{
		include $pl;
	}
	/* ===== */

	// Done, output results
	if (!COT_AJAX && cot_url_check($_SERVER['HTTP_REFERER']))
	{
		cot_redirect($_SERVER['HTTP_REFERER']);
	}
	else
	{
		echo $ratingnewsumm;
	}
	exit;
}

?>
