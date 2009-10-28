<?php

ini_set("display_errors","Off");

include_once (dirname(realpath(__FILE__)).'/common.php');
include_once (dirname(realpath(__FILE__)).'/functions.php');

header('Content-Type: text/html; charset=UTF-8',true);

if (!$cache->used || ($cache->get('next_cleanup') < TIMENOW))
{
	cleanup();
}

if (get_magic_quotes_gpc())
{
	array_deep($_REQUEST, 'stripslashes');
}

if (isset($_GET['isp_list']) AND $city = intval($_GET['isp_list']))
{
	echo isp_select($city,0);
	exit;
}

ob_start();
session_start(); // Start a session for store the options

$http_query = $_GET;
unset($http_query['start']);

$http_query = array_merge(array_keys($http_query), array_values($http_query));
$query_id = md5(join('&', $http_query));

unset($http_query);

if(!isset($_GET['start']) && isset($_SESSION['last_search']) && $_SESSION['last_search'] > (TIMENOW - $search_intrv))
{
	$seconds = ($search_intrv + $_SESSION['last_search']) - TIMENOW;
	include_once 'message.htm';
	die();
}

$_SESSION['last_search'] = TIMENOW;

?>
<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />
<meta name="Robots" content="INDEX,NOFOLLOW" />

<link rel="stylesheet" href="<?=$cfg['base_url'];?>main.css?v=1" type="text/css">

<script type="text/javascript" src="<?=$cfg['base_url'];?>jquery.pack.js?v=1"></script>
<script type="text/javascript" src="<?=$cfg['base_url'];?>main.js?v=1"></script>

<style type="text/css">
#tor-tbl s { display: none; }
.seed-leech { padding-left: 1px; padding-right: 0; }
.tr_tm { margin-top: 2px; font-size: 10px; color: #676767; }
.ch { font-style: italic; color: #0080FF; }
</style>

<script type="text/javascript">
function initExternalLinks(context)
{
	var context = context || 'body';
	$("a:not([@href*='"+ window.location.hostname +"/'])", context).attr({ target: '_blank' });
}

function getElText (e)
{
	var t = '';
	if (e.textContent !== undefined) {
		t = e.textContent;
	}
	else if (e.innerText !== undefined) {
		t = e.innerText;
	}
	else {
		t = jQuery(e).text();
	}
	return t;
}
function escHTML (txt)
{
	return txt.replace(/</g, '&lt;');
}
$(document).ready(function()
{
	initExternalLinks('#tor-tbl');
	$('#tor-tbl').tablesorter(); //	{debug: true}
});
</script>

<title>Re-Tracker.ru :: Torrent List</title>

</head>

<body>

<div id="body_container">

<!--page_content-->
<div id="page_content">
<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;"><tr>

<!--main_content-->
<td id="main_content">
<div id="main_content_wrap">

<?
echo "<h4>Статистика</h4>";

$stats = $cache->get('stats');

if(!$stats)
{
	$row = $db->fetch_row("SHOW TABLE STATUS LIKE '$tracker'");
	$peers_num = isset($row['Rows'])?(int)$row['Rows']:0;

	$row = $db->fetch_row("SHOW TABLE STATUS LIKE '$tracker_stats'");
	$torrents_num = isset($row['Rows'])?(int)$row['Rows']:0;

	$stats = array(
		'peers_num'    => $peers_num,
		'torrents_num' => $torrents_num
	);

	$stats_cached = $cache->set('stats', $stats, STATS_EXPIRE);
}
echo "Всего пиров: <b>{$stats['peers_num']}</b>, всего торрентов: <b>{$stats['torrents_num']}</b><br />\n";

$req_type = isset($_GET['o']) ? '_GET' : '_COOKIE';

$GPC = array(
#	  var_name                                          key_name    def_value        type      store into COOKIE
	'start'         => array('start', 0,      'int',  false),
	//'admin'         => array('adm',   0,      'int',   true),
	// Options
	'active'        => array('a',    0,       'int',   true),
	'my'            => array('my',   0,       'int',   true),
	'title_match'   => array('nm',   0,    'string',  false),
	'order'         => array('o',    1,       'int',   true),
	'sort'          => array('s',    2,       'int',   true),
	'seed_exist'    => array('sd',   0,       'int',   true),
	'desc_exist'    => array('ds',   0,       'int',   true),
	// City & ISP
	'city'          => array('city', 0,       'int',   true),
	'isp'           => array('isp',  0,       'int',   true),
);

// Define all GPC vars with default values
foreach ($GPC as $name => $params)
{
	$$name = isset(${$req_type}[$params[0]]) ? 	${$req_type}[$params[0]] : $params[1];
	$params[3] ? setcookie($params[0], $$name, TIMENOW + $search_opt_keep) : null;

	switch($params[2])
	{
		case 'int':    $$name = intval($$name); break;
		case 'string': $$name = strval($$name); break;
		default :      $$name = intval($$name); break;
	}
}

$title_match = sqlwildcardesc($db->escape($title_match));

switch($order)
{
	case '1':
		$order_sql = 'ts.reg_time';
		break;
	case '2':
		$order_sql = "ts.name";
		break;
	case '3':
		$order_sql = 'ts.seeders';
		break;
	case '4':
		$order_sql = 'ts.leechers';
		break;
	case '5':
		$order_sql = 'ts.size';
		break;
	default:
		$order_sql = 'ts.reg_time';
		break;
}

$from = "$tracker_stats ts";
$join_tr = false;

$iptype = verify_ip($_SERVER['REMOTE_ADDR']);
$ip = $db->escape(encode_ip($_SERVER['REMOTE_ADDR']));

$where = array();

if($seed_exist)  { $where[] = "ts.seeders > 0";}
if($active)      { $where[] = "(ts.seeders > 0 OR ts.leechers > 0)";}
if($desc_exist)  { $where[] = "ts.name != ''";}
if($title_match) { $where[] = "ts.name LIKE '%$title_match%'";}

if ($city || $isp || $my)
{
	$where = array_reverse($where);
	$tmp = array();
	$city ? $tmp[] = "tr.city = ".$city : NULL;
	$isp ? $tmp[] = "tr.isp = ".$isp : NULL;
	$my && ($iptype == 'ipv4') ? $tmp[] = "tr.ip   = '$ip'" : null;
	$my && ($iptype == 'ipv6') ? $tmp[] = "tr.ipv6 = '$ip'" : null;
	$_sql = "ts.torrent_id IN (SELECT DISTINCT tr.torrent_id FROM ".$tracker." tr WHERE ".implode(" AND ",$tmp).")";
	array_push($where,$_sql);
	unset($tmp, $_sql);
	$where = array_reverse($where);
}

$where_sql = !empty($where) ? 'WHERE '. implode(' AND ', $where) : '';

switch($sort)
{
	case '1':
		$sort_sql = 'ASC';
		break;
	case '2':
		$sort_sql = 'DESC';
		break;
	default:
		$sort_sql = 'DESC';
		break;
}


$admin = (isset($_REQUEST['adm']) AND $_REQUEST['adm']) ? true : false;
setcookie('adm', $admin, TIMENOW + $search_opt_keep);
?>

<form method="GET" name="post" action="torrents.php?<?=SID;?>">

<table class="bordered w100" cellspacing="0">
<col class="row1">
<tr>
	<th class="thHead">Опции показа торрентов</th>
</tr>
<tr>
	<td class="row4" style="padding: 4px";>

		<table class="fieldsets borderless bCenter pad_0" cellspacing="0">
		<tr>
			<td height="1" width="20%">
				<fieldset>
				<legend>Упорядочить по</legend>
				<div class="med">
					<p class="select">
						<select name="o" id="o">
							<option value="1" <?=($order==1) ? 'selected="selected"' : '' ;?>>&nbsp;Добавлен&nbsp;</option>
							<option value="2" <?=($order==2) ? 'selected="selected"' : '' ;?>>&nbsp;Название торрента&nbsp;</option>
							<option value="3" <?=($order==3) ? 'selected="selected"' : '' ;?>>&nbsp;Seeders&nbsp;</option>
							<option value="4" <?=($order==4) ? 'selected="selected"' : '' ;?>>&nbsp;Leechers&nbsp;</option>
							<option value="5" <?=($order==5) ? 'selected="selected"' : '' ;?>>&nbsp;Размер&nbsp;</option>
						</select>
					</p>
					<p class="radio"><label><input type="radio" name="s" value="1" <?=($sort==1) ? 'checked="checked"' : '' ;?> /> по возрастанию</label></p>
					<p class="radio"><label><input type="radio" name="s" value="2" <?=($sort==2) ? 'checked="checked"' : '' ;?> /> по убыванию</label></p>
				</div>
				</fieldset>
			</td>
			<td width="30%">
				<fieldset>
				<legend>Показывать только</legend>
				<div class="gen">
					<p class="chbox">
						<label><input type="checkbox"  name="my"  value="1" <?=($my) ? 'checked="checked"' : '' ;?> />&nbsp;Мои раздачи&nbsp;</label>[<b>&reg;</b>]
					</p>
					<p class="chbox">
						<label><input type="checkbox"  name="a"  value="1" <?=($active) ? 'checked="checked"' : '' ;?> />&nbsp;Активные (есть seeder или leecher)&nbsp;</label>
					</p>
					<p class="chbox">
						<label><input type="checkbox"  name="ds"  value="1" <?=($desc_exist) ? 'checked="checked"' : '' ;?> />&nbsp;Есть описание&nbsp;</label>
					</p>
				</div>
				</fieldset>
			</td>
		</tr>
		<tr>
			<td colspan="1" width="50%">
				<fieldset>
				<legend>Название содержит</legend>
				<div>
					<p class="input">
						<input style="width: 95%;" class="post" type="text" size="50" maxlength="60" name="nm" id="nm" value="<?=($title_match) ? $title_match : '';?>" />
					</p>
					<p class="chbox med">
						<a class="med" href="#" onclick="$('#nm').val(''); return false;">Очистить</a>&nbsp;&middot;
						<a class="med" href="http://re-tracker.ru/index.php?showtopic=131">Помощь по поиску</a>
					</p>
				</div>
				</fieldset>
			</td>
			<td colspan="1" width="50%">
				<fieldset>
				<legend>Город и провайдер</legend>
				<div>
					<p class="select">
						<select name="city" id="city" onchange="if(this.value>0) $('#isp').load('torrents.php?isp_list='+$('#city').val());">
							<option value="0">&raquo; Выберите город</option>
							<?=city_select($city);?>
						</select>
						<select name="isp" id="isp">
							<?=isp_select($city, $isp);?>
						</select>
					</p>
				</div>
				</fieldset>
			</td>
		</tr>
		</table>

	</td>
</tr>
<tr>
	<td class="row3 pad_4 tCenter">
		<input class="bold long" type="submit" name="" value="&nbsp;&nbsp;Поиск&nbsp;&nbsp;" />
	</td>
</tr>
</table>

</form>

<div class="spacer_6"></div>

<table class="forumline tablesorter" id="tor-tbl">
<thead>
<tr>
	<th class="{sorter: 'text'}" width="5%"><b class="tbs-text">Tracker</b></th>
	<th class="{sorter: 'text'}" width="75%" title="Название"><b class="tbs-text">Name</b></th>
	<th class="{sorter: 'digit'}" title="Размер"><b class="tbs-text">Size</b></th>
	<th class="{sorter: 'digit'}" title="Seeders"><b class="tbs-text">S</b></th>
	<th class="{sorter: 'digit'}" title="Leechers"><b class="tbs-text">L</b></th>
	<th class="{sorter: 'digit'}" title="Added"><b class="tbs-text">Added</b></th>
</tr>
</thead>
<?

	$count_sql = "SELECT COUNT(ts.torrent_id) AS count FROM $from $where_sql LIMIT 1";
	$count_key = md5($count_sql);
	$count = (int)$cache->get($count_key);
	if (!$count && $my)
	{
		$row = $db->fetch_row($count_sql);
		$count = (int) $row['count'];
		$cache->set($count_key, $count, 1800);
	} else {
		$count = 1000;
	}

	$sql = "SELECT ts.torrent_id, ts.info_hash, ts.seeders, ts.leechers, ts.reg_time,
				ts.name,
				ts.size,
				ts.comment,
				ts.last_check
			FROM $from
			$where_sql
			ORDER BY $order_sql $sort_sql
			LIMIT $start, 25";
	$torset = $db->fetch_rowset($sql);

	$empty = array(
		'torrent_id' => 0,
		'seeders'    => 0,
		'leechers'   => 0,
		'name'       => "",
		'comment'    => "",
		'city'       => 0,
		'isp'        => 0,
		'size'       => 0,
		'reg_time'   => 0,
		'last_check' => time(),
	);

	foreach($torset as $tor)
	{
		$tor = array_merge($empty, $tor);
		$torrent_id = $tor['torrent_id'];
		$info_hash  = $tor['info_hash'];

		$seeders  = !empty($tor['seeders'])  ? $tor['seeders'] : '0';
		$leechers = !empty($tor['leechers']) ? $tor['leechers'] : '0';

		$name = $tor['name'];
		
		$size = !empty($tor['size']) ? humn_size($tor['size']) : ' - ' ;

		$comment = trim($tor['comment']);
		$is_url = is_url($comment);

		$path = @parse_url($comment);
		if(isset($path['scheme']) && isset($path['host']))
		{
			$host = $path['scheme'] .'://'. $path['host'];
		}
		else
		{
			$host = "http://re-tracker.ru";
		}
		
		$download = "";
		if ($is_url && isset($path['host']))
		{
			$simple = array();
			$simple[] = "pornolab.net";
			$simple[] = "torrents.ru";
			if (in_array($path['host'], $simple))
			{
				$download = str_replace("viewtopic", "dl", $comment);
			}
		}

		$isp = $tor['city'] . '+' . $tor['isp'];

		if ($admin)
		{
			$tr = rawurlencode("http://re-tracker.ru/announce.php?name=$name&size={$tor['size']}&comment=$comment&isp=$isp");
			$magnet = create_magnet(urlencode($name), $tor['size'], strtoupper(base32_encode(hex2bin($info_hash))), $tr);
		}
		else $magnet = null;

		$added_time = create_date('H:i', $tor['reg_time']);
		$added_date = create_date('j-M-y', $tor['reg_time']);

		$tor_url = ($is_url) ? $comment : (!empty($name) ? "http://google.com/search?q=".urlencode($name) : '');

		$allow_check = (($tor['last_check'] + $min_check_intrv) < TIMENOW);
?>
<tr class="tCenter" id="tor_<?=$torrent_id;?>">
	<td class="row1">	<img src="<?=($is_url?$host:"http://re-tracker.ru");?>/favicon.ico" alt="pic" title="<?=($is_url?$path['host']:"Unknown");?>" width="16" height="16"/>	<div style="visibility: hidden; position: absolute; left: -1000px; top: -2000px;"><?=$host;?></div>	</td>
	<td class="row4 med tLeft">
		<span id="name_<?=$torrent_id;?>">
		<? if ($tor_url) :?>
			<a class="genmed" href="<?=$tor_url;?>"><?=(!empty($name) ? "<b>".$name."</b>" : "ссылка");?></a>
		<? else : ?>
			<i>не задано</i> - <a class="gen" href="http://google.com/search?q=<?=$info_hash;?>" title="Search at Google">[Google]</a> <a class="gen" title="Search at isoHunt" href="http://isohunt.com/torrents/?ihq=<?=$info_hash;?>">[isoHunt]</a>
		<? endif; ?>
		<? if($is_url && $allow_check) :?>
			<a href="javascript:void(0);" onclick="$(this).replaceWith('<im'+'g src=<?=$cfg['base_url'];?>images/updating.gif alt=pic title=Updating>'); $('#name_<?=$torrent_id;?>').load('checkname.php?torrent_id=<?=$torrent_id;?>&return=1'); return false;"><img src="<?=$cfg['base_url'];?>images/update.gif" alt="pic" title="Update"></a>
		<? endif; ?>
		</span>
		<? if ($download) : ?><a class="seed dlDown" href="<?=$download;?>" title="Download torrent"><b>[D]</b></a><? endif; ?>
		<p>
			<span style="color: silver;">[hash: <?=$info_hash;?>]</span>
			<?=(!empty($comment) && (!$is_url)) ? "<i><u>комментарий:</u></i> ".make_url($comment) : "" ; ?>
		</p>
	</td>
	<td class="row4 small nowrap">
		<s><?=$tor['size'];?></s>
		<? if($admin) { ?>
		<a class="small tr-dl" href="<?=$magnet;?>"><?=$size;?></a>
		<? } else { ?>
		<p class="small tr-dl"><?=$size;?></p>
		<? } ?>
	</td>
	<td class="row4 seedmed" title="Раздают"><b><?=$seeders;?></b></td>
	<td class="row4 leechmed" title="Качают"><b><?=$leechers;?></b></td>
	<td class="row4 small nowrap" style="padding: 0 1px 3px 2px;" title="Добавлен">
		<s><?=$tor['reg_time'];?></s>
		<p><?=$added_time;?></p>
		<p><?=$added_date;?></p>
	</td>
</tr>
<?
}
?>
<tfoot>
<tr>
	<td class="catBottom" colspan="11">&nbsp;</td>
</tr>
</tfoot>
</table>
<?
$request = @parse_url($_SERVER['REQUEST_URI']);
$query   = isset($request['query']) ? $request['query'] : "";
parse_str ($query, $_args);

if (empty($_args))
{
	$_args = array(
		'o' => $order,
		's' => $sort,
	);
}
unset($_args['start']);

$_args_str = http_build_query($_args);

$pg_url = basename(__FILE__) .'?'. $_args_str;
$pagination = generate_pagination($pg_url, $count, 25, $start);
?>

<div class="nav">
	<p style="float: right"><?=$pagination; ?></p>
	<div class="clear"></div>
</div>
</div><!--/main_content_wrap-->
</td><!--/main_content-->
</tr></table></div><!--/page_content-->
</div><!--/body_container-->
		<div class="copyright tCenter" align="center">
			Powered by <a href="http://re-tracker.ru/" target="_blank">Re-Tracker.ru</a> &copy; <strong>RoadTrain, FreeM@N</strong>
		</div>
</body>
</html>
<?
ob_end_flush();
?>