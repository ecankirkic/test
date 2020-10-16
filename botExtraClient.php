<?php
/**
 * Atolye Mavi botextra.com Client
 *
 * @package     Client
 * @version     3.0
 * @copyright   Copyright (c) 2016, Atolye Mavi
 * @link        http://botextra.com
 */
# Konuyla ilgili sorunlarınızı botextra.com da destek bolumunden paylaşabilirsiniz.
 
    @error_reporting(E_ALL & ~ E_NOTICE & ~ E_DEPRECATED);
    @ini_set('display_errors',1);
    @header("HTTP/1.1 200 OK");
    
    $action     = $_GET['action'];
    $token      = $_GET['token'];
    $contentId  = $_GET['id'];

    $safePost   = $_POST;
    include_once('botextra/obj.botExtraClient.php');
    include_once("botextra/client.botextra.php");

    $scriptObj = new botExtraScript();
    $scriptObj->controlAction($_GET['action'],$_GET['token']);

    $params = $scriptObj->getParamaters($_GET['action']);
    $scriptObj->{$_GET['action']}($params);
?>
