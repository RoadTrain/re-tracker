<?php

include_once (dirname(realpath(__FILE__)) . '/common.php');

if (isset($_REQUEST['info_hash']))
{
	$info_hash = strtolower($_GET['info_hash']);
	print_r($cache->get(PEERS_LIST_PREFIX . $info_hash));
	
	if (isset($_REQUEST['rm']))
	{
		$cache->rm(PEERS_LIST_PREFIX . $info_hash);
	}
}

?>
<html>
<form action="debug.php" method="get"><input type="text"
	name="info_hash" value="<?=(!empty($info_hash) ? $info_hash : '');?>">
<input type="submit" name="" value="View"> <input type="submit"
	name="rm" value="Remove"></form>
	<?
	if (!empty($info_hash))
	{
		?>
	<a
	href="<?='magnet:?xt=urn:btih:' . strtoupper(base32_encode(hex2bin($info_hash)));?>">Magnet</a>
	<?
	}
	?>
</html>