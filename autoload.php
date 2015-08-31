<?php
function autoloadDumperComponentD()
{
	$basepath = dirname(__FILE__);
	require_once $basepath . '/D.php';
	require_once $basepath . '/CVarDumper.php';
}

spl_autoload_register('autoloadDumperComponentD');

// 不能把以下代码放在D.php文件中，因为该文件只有在使用类D时才会被加载
/**
 * 该常量定义了是否使用Power Dumper定义的错误异常处理机制，默认是false。
 */
defined('DUMPER_HANDLER_ACTIVE') or define('DUMPER_HANDLER_ACTIVE', false);
if (DUMPER_HANDLER_ACTIVE)
{
	set_error_handler(array('D', 'handleError'), error_reporting());
	set_exception_handler(array('D', 'handleException'));
}