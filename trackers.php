<?php
include_once (dirname(realpath(__FILE__)) . '/common.php');
include_once (dirname(realpath(__FILE__)) . '/functions.php');
include_once (dirname(realpath(__FILE__)) . '/sendmail.class.php');
header('Content-Type: text/html; charset=UTF-8', true);

function AddRetracker($city, $isp, $retracker, $email) {
	global $db, $cache;
	
	$city = (int)$city;
	$isp = (int)$isp;
	
	if(!ValidateRetracker($retracker)) {
		return "Некорректный формат адреса ретрекера";
	}
	if (!ValidateEmail($email)) {
		return "Некорректный формат e-mail";
	}
	
	$ip = $_SERVER['REMOTE_ADDR'];
	$ban_key = md5("ban_".$ip);
	$banned = (int)$cache->get($ban_key);
	
	if($banned>3) {
		return "Вы забанены ещё ".($banned-time())." секунд";
	}
	
	$already_have = (int)$db->fetch_row("SELECT `id` FROM `tracker_retrackers` WHERE `email`='".$db->escape($email)."'");
	$exists = (int)$db->fetch_row("SELECT `id` FROM `tracker_retrackers` WHERE (`retracker`='".$db->escape($retracker)."' OR `new_retracker`='".$db->escape($retracker)."') AND `id_city`=".$db->escape($city)." AND `id_prov`=".$db->escape($isp));
	if ($already_have || $exists) {
		$banned++;
		if ($banned>3) {
			$cache->rm($ban_key);
			$cache->set($ban_key,time()+86400,86400);
		} else {
			$cache->set($ban_key,$banned,86400);
		}
		return "Данный e-mail или ретрекер уже зарегистрирован.";
	}
	
	$key = strtoupper(md5(time().$ip.$retracker.rand(100,1000).$email.rand(1000,10000)));
	$sql = "INSERT INTO
				`tracker_retrackers`
			VALUES(NULL,
					".$db->escape($city).",
					".$db->escape($isp).",
					".time().",
					'',
					0,
					'".$db->escape($retracker)."',
					'',
					'".$db->escape($email)."',
					'".$key."')";
	if(!$db->query($sql)) {
		$banned++;
		if ($banned>3) {
			$cache->rm($ban_key);
			$cache->set($ban_key,time()+86400,86400);
		} else {
			$cache->set($ban_key,$banned,86400);
		}
		return "При добавлении ретрекера произошла ошибка, попробуйте повторить позже.";
	}
	ob_end_clean();
	ob_start();
	require_once 'mail.php';
	$message = ob_get_contents();
	ob_clean();
	
	$mail = new ml_Mail("no-reply@re-tracker.ru");
	$mail->make("Re-Tracker.ru: Добавление ретрекера",$message);
	
	return $mail->send($email)?"OK":"При отправке уведомления произошла ошибка, попробуйте добавить ретрекер ещё раз или свяжитесь с админимтрацией.";
}

function ValidateRetracker($retracker = null){
	return preg_match('%\bhttps?://[-A-Z0-9.]+:?[0-9]{1,5}/[-A-Z0-9/.]*\??[-A-Z0-9/.=]*%i', $retracker);
}

function ValidateEmail($email = null) {
	return preg_match('/[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+(?:\.[a-z0-9!#$%&\'*+\/=?^_`{|}~-]+)*@(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+(?:[A-Z]{2}|com|org|net|gov|mil|biz|info|mobi|name|aero|jobs|museum)\b/i', $email);
}

function ConfirmAction($email, $code) {
	global $db, $cache;
	
	$ip = $_SERVER['REMOTE_ADDR'];
	$ban_key = md5("ban_".$ip);
	$banned = (int)$cache->get($ban_key);
	
	if($banned>3) {
		return "Вы забанены ещё ".($banned-time())." секунд";
	}
	
	if (!ValidateEmail($email)) {
		return "Некорректный формат e-mail";
	}
	
	if(!preg_match('#^[A-Z0-9]{32}$#', $code)) {
		$banned++;
		if ($banned>3) {
			$cache->rm($ban_key);
			$cache->set($ban_key,time()+86400,86400);
		} else {
			$cache->set($ban_key,$banned,86400);
		}
		return "Некорректный код";
	}
	$db->query("UPDATE `tracker_retrackers` SET `retracker`=`new_retracker`, `allow`=1, `key`='' WHERE `email`='".$db->escape($email)."' AND `key`='".$db->escape($code)."'");
	$cache->rm("citys");
	$cache->rm("providers_tag");
	$cache->rm("retrackers_tag");
	$cache->rm("new_trackers");
	return "OK";
}

$save = isset($_POST['save'])?intval($_POST['save']):0;
$city = isset($_POST['city'])?intval($_POST['city']):0;
$isp = isset($_POST['isp'])?intval($_POST['isp']):0;

if ($save) {
	ob_clean();
	$mail = isset($_POST['eml'])?trim((string)$_POST['eml']):"";
	$retr = isset($_POST['retracker'])?trim((string)$_POST['retracker']):"";
	$code = isset($_POST['code'])?trim($_POST['code']):0;
	switch ($save) {
		case 1:{
			$result = AddRetracker($city, $isp, $retr, $mail);
			if($result == "OK") {
				die("На указанный e-mail отправлена инструкция для завершения регистрации ретрекера.");
			} else {
				die($result);
			}
			break;
		}
		case 2:{
			//Ещё не сделано
		}
		case 3: {
			$result = ConfirmAction($mail, $code);
			if($result == "OK") {
				die("Поздравляем, ваш ретрекер добавлен.");
			} else {
				die($result);
			}
		}
		default:{
			die("Что-то не так");
		}
	}
	die("wtf");
}

$city_all = isset($_GET['city_all'])?intval($_GET['city_all']):0;
if(($city||$city_all) && !$isp) {
	ob_clean();
	
	echo("<option value='0'>&raquo; Выберите провайдера</option>");
	foreach (GetProviders($city?$city:NULL) as $id => $name) {
		echo("<option value='{$id}'>{$name}</option>");
	}
	ob_end_flush();
	die();
}

?>
<html>

<head>
<meta http-equiv="content-type" content="text/html; charset=UTF-8" />

<link rel="stylesheet" href="<?=$cfg['base_url'];?>main.css?" type="text/css">

<script type="text/javascript" src="<?=$cfg['base_url'];?>jquery.pack.js?v=1"></script>
<script type="text/javascript" src="<?=$cfg['base_url'];?>main.js?v=1"></script>

<title>Re-Tracker.ru :: Add or Edit your re-tracker</title>
<style type="text/css">
fieldset {
	margin: 5px auto;
	width: 550px;
}
fieldset fieldset {
	border: none;
}
fieldset button {
	width: 250px;
}

</style>
</head>

<body>
<script type="text/javascript">
function SaveChanges(save) {
	if(save=='undefined' || save>3) {
		return false;
	}

	$('#butt_'+save).attr("disabled", true);

	if(save==1) {
	$.post('trackers.php',{save:save,city:$('#city').val(),isp:$('#isp').val(),retracker:$('#new').val(),eml:$('#eml').val()},function(data){
		alert(data);
		$('#butt_'+save).removeAttr("disabled");
		$('#city').val('0');
		$('#isp').val('0');
		$('#retracker').hide('fast');
		});
	} else if(save==3) {
		$.post('trackers.php',{save:save,eml:$('#eml3').val(),code:$('#code').val()},function(data){
			alert(data);
			$('#butt_'+save).removeAttr("disabled");
			});
	}
	
}
</script>
<div id="body_container">

<!--page_content-->
<div id="page_content">
<table cellspacing="0" cellpadding="0" border="0" style="width: 100%;"><tr>

<!--main_content-->
<td id="main_content">
<div id="main_content_wrap">

<form method="POST" name="post" action="" enctype="multipart/form-data">
<table class="bordered w100" cellspacing="0">
<tr>
	<th class="thHead">Выберите необходимое действие</th>
</tr>
<tr>
	<td class="row4 tCenter" style="padding: 4px";>
		<fieldset>
		<h2>Добавить ретрекер</h2>
		<div class="catBottom" style="margin: auto 0;">
			<select name="city" id="city" onchange="if(this.value>0 && $('#isp').val()>0) {$('#retracker').show('fast');} else {$('#retracker').hide('fast');}">
				<option value="0">&raquo; Выберите город</option>
				<? foreach (GetCitys() as $id => $name) : ?>
					<option value="<?=$id;?>"><?=$name;?></option>
				<? endforeach; ?>
			</select>
			<select name="isp" id="isp" onchange="if(this.value>0 && $('#city').val()>0) {$('#retracker').show('fast');} else {$('#retracker').hide('fast');}">
				<option value="0">&raquo; Выберите провайдера</option>
				<? foreach (GetProviders() as $id => $name) : ?>
					<option value="<?=$id;?>"><?=$name;?></option>
				<? endforeach; ?>
			</select>
			<br>
			<div style="display: none;" id="retracker">
				<fieldset>
				<label for="retracker">Ретрекер</label>
				<input style="width: 400px;" type="text" maxlength="255" id="new" name="retracker">
				</fieldset>
				<fieldset>
				<label for="eml">Ваш e-mail</label>
				<input style="width: 200px;" type="text" maxlength="255" name="eml" id="eml">
				</fieldset>
				<button type="button" onclick="SaveChanges('1');" id="butt_1">Сохранить</button>
			</div>
		</div>
		</fieldset>
		<fieldset style="display: none;">
		<button type="button">Редактировать ретрекер</button>
		</fieldset>
		<fieldset>
		<button type="button" onclick="$('#confirm').show('fast'); this.style.display = 'none';">Подтвердить действие</button>
		<div class="catBottom" style="margin: auto 0; display: none;" id="confirm">
			<h2>Подтвердить действие</h2>
			<fieldset>
			<label for="eml">Ваш e-mail</label>
			<input style="width: 200px;" type="text" maxlength="255" name="eml3" id="eml3">
			</fieldset>
			<fieldset>
			<label for="code">Код подтвеждения</label>
			<input style="width: 200px;" type="text" maxlength="255" name="code" id="code">
			</fieldset>
			<button type="button" onclick="SaveChanges('3');" id="butt_3">Подтвердить</button>
		</div>
		</fieldset>
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
ob_end_flush();
?>