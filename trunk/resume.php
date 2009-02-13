<?php

include_once (dirname(realpath(__FILE__)).'/common.php');
include_once (dirname(realpath(__FILE__)).'/functions.php');
unset($dir);

$trackers = get_trackers();
	
if (isset($_GET['tr_list']))
{
	$city = intval(@$_GET['city']);
	$isp = intval(@$_GET['isp']);
	
	echo iconv('CP1251', 'UTF-8', tr_list($trackers['Ретрекеры '.$trackers['Город'][$city].' '.$trackers['Провайдеры '. $trackers['Город'][$city]][$isp]]));
	exit;
}

$city = (int) @$_REQUEST['city'];
$isp =  (int) @$_REQUEST['isp'];

ob_start();
session_start();

if (isset($_REQUEST['submit']) && !empty($_FILES['resume']))
{
	if (!is_uploaded_file($_FILES['resume']['tmp_name'])) die("Неудача");
	if (!($_SESSION['resume'] = bdecode_file($_FILES['resume']['tmp_name']))) die("Invalid resume.dat file");
}

if (empty($_SESSION['resume']) || !isset($_REQUEST['act']))
{
?>
<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=windows-1251" />

<link rel="stylesheet" href="./main.css?" type="text/css">

<script type="text/javascript" src="./jquery.pack.js?v=1"></script>
<script type="text/javascript" src="./main.js?v=1"></script>

<title>Re-Tracker.ru :: resume.dat patcher</title>

</head>

<body>

<div id="body_container">

<!--page_content-->
<div id="page_content">
<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;"><tr>

<!--main_content-->
<td id="main_content">
<div id="main_content_wrap">

<form method="POST" name="post" action="?<?=SID;?>&act=1" enctype="multipart/form-data">
<table class="bordered w100" cellspacing="0">
<tr>
	<th class="thHead">resume.dat patcher</th>
</tr>
<tr>
	<td class="row4 tCenter" style="padding: 4px";>
		<p>Выберите свой <i>resume.dat</i> (находится в <b>%appdata%\uTorrent\resume.dat</b>)</p> <br />
			<input style="width: 25%;" type="file" size="50" name="resume" />&nbsp;
		<br>
		<p>Выберите свой город и провайдера</p>
			<p class="select">
				<select name="city" id="city" onchange="$('#isp').load('torrents.php?isp_list='+$('#city').val());">
					<option value="0">&raquo; Выберите город</option>
					<?=city_select($trackers['Город'], $city);?>
				</select>
				<select name="isp" id="isp"
				onchange="$('#tr').load('resume.php?tr_list=1&city='+$('#city').val()+'&isp='+$('#isp').val());">
					<option value="0">&raquo; Выберите провайдера</option>
				</select>
			</p>
			<p>
				<textarea class="mrg_4" name="tr" id="tr" rows="18" cols="92" style="width: 25%;"><?=(isset($_COOKIE['tr_list']) ? $_COOKIE['tr_list'] : '');?></textarea>
			</p>
			<p>
				<label>
				<input class="checkbox" type="checkbox" name="rt" value="1" checked="checked"/>Запомнить трекеры</label>
			</p>
		<input class="bold long" type="submit" name="submit" value="&nbsp;&nbsp;Поехали!&nbsp;&nbsp;" />
	</td>
</tr>
</table>
</form>

</div><!--/main_content_wrap-->
</td><!--/main_content-->
</tr></table></div><!--/page_content-->
</div><!--/body_container-->

</body>

</html>

<?
}
else
{
	(isset($_REQUEST['rt'])) ? setcookie('tr_list', $_REQUEST['tr'], TIMENOW + 30*86400) : null;
	
	$resume = & $_SESSION['resume'];
	
	$tr_list = explode("\n", trim($_REQUEST['tr']));
	array_deep($tr_list, 'trim');
	//print_r($tr_list);
	//exit;
	
	foreach ($resume as $item => $data)
	{
		if (is_array($data))
		{
			(empty($data['blocks'])) ? ($data['blocks'] = array()) : null;
			$trackers = & $data['trackers'];

			for($i = 0; $i < count($trackers); $i++)
			{
				$tr = & $trackers[$i];
				$query = array(
					'name'    => $data['caption'],
					'size'    => '',
					'comment' => '',
					'isp'     => $city .'+'. $isp,
				);
				
				if (strpos($tr, 're-tracker.ru'))
				{
					$parts = @parse_url($tr);
					parse_str($parts['query'], $q);
					
					$query['size']    = $q['size'];
					$query['comment'] = $q['comment'];
					unset($tr);
				}
			}
			$trackers[] = "http://re-tracker.ru/announce.php?". http_build_query($query);
			$trackers = array_merge($trackers, $tr_list);
			$trackers = array_unique($trackers);
			//array_deep($trackers, 'trim');
			$resume[$item] = $data;
		}
	}
	// Send to client
	header("Content-Type: application/octet-stream; charset=windows-1251; name=\"resume.dat\"");
	header("Content-Disposition: attachment; filename=\"resume.dat\"");
	echo bencode($resume);
}

ob_end_flush();
?>