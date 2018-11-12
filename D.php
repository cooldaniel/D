<?php
/**
 * D class file.
 * 
 * @author Daniel Luo <295313207@qq.com>
 * @copyright Copyright &copy; 2010-2015
 * @version 2.0
 */

// 如果存在DB函数，表示在使用laravel的database库，加载use-laravel-database.php，引入它的命名空间
// 不直接在这里使用use语句，是因为use语句前面不能有任何其它php语句
if (function_exists('DB')) {
    require_once dirname(__FILE__) . '/use-laravel-database.php';
}

/**
 * 类名D是Dumper的缩写，意指该静态类的功用是打印变量信息。它由一系列的变量打印方法组成：
 * 
 * 1.打印参数
 * 打印多个参数的时候，使用逗号将参数分隔.
 * D::pd(): Power-dumping with highlight
 * D::pds(): Power-dumping slight
 * D::log(): Power-dumping with loging
 * D::logc(): Same as D::log() but which overrides the original content with the current content
 * 他们还有以下变形，区别是附加了终止程序执行功能：
 * D::pde(): D::pd() with exiting
 * D::pdse(): D::pds() with exiting
 * D::loge(): D::log() with exiting
 * D::logce(): D::logc() with exiting
 * 
 * 2.利用反射打印反射对象
 * D::ref()以及它的附加了终止程序执行功能的变形D::refe().
 * 使用 D::pd() 打印对象，输出的是对象的属性值，D::ref() 打印的是类定义信息，两者目的不同。
 * D::refF()及D::refFe(): 打印给定函数的信息.
 * 
 * 3.打印PHP环境变量
 * D::post(): Dump the $_POST variable
 * D::get(): Dump the $_GET variable
 * D::request(): Dump the $_REQUEST variable
 * D::cookie(): Dump the $_COOKIE variable
 * D::session(): Dump the $_SESSION variable
 * D::file(): Dump the $_FILES variable
 * D::server(): Dump the $_SERVER variable
 * D::globals(): Dump the $GLOBALS variable
 * 它们附带终止程序执行功能的变形如下：
 * D::poste(): D::post() with exiting
 * D::gete(): D::get() with exiting
 * D::requeste(): D::request() with exiting
 * D::cookiee(): D::cookie() with exiting
 * D::sessione(): D::session() with exiting
 * D::filee(): D::file() with exiting
 * D::servere(): D::server() with exiting
 * D::globalse(): D::globals() with exiting
 * 
 * 4.其它辅助方法
 * D::close(): 关闭D组件
 * D::bk(): 打印断点
 * D::fp(): 留下脚印
 * D::trace(): 打印程序调用堆栈
 * D::count(): 调用PHP函数 {@link count} 计算数组长度.
 * D::counte() : 同D::count()并终止程序.
 * D::rand(): 调用PHP函数 {@link rand} 打印随机数. 
 * D::rande(): 同D::rande()并终止程序.
 * D::rp(): 调用PHP函数 {@link realpath} 打印其结果.
 * D::args(): 打印当前函数的参数列表.
 * 
 * @todo 目前来讲，因为内部处理仅仅涉及英文字符，所以不会出现中文处理乱码问题。
 * 如果能实现内部字符集编码则更好。但是，因为该类的内部处理工作很少，而且基本不变，
 * 不会涉及中文，所以，实现内部字符集编码没实际必要。
 */

/**
 * 该常量定义了在使用Power Dumper定义的错误异常处理机制时是否丢弃输出，默认是false。
 */
defined('DUMPER_HANDLER_DISCARD_OUTPUT') or define('DUMPER_HANDLER_DISCARD_OUTPUT', false);
/**
 * 该常量定义了在调试与非调试状态下使用D::log(), D::loge(), D::logc()时它们是否真正记录信息到文件，默认是true。
 */
defined('DUMPER_LOG_ACTIVE') or define('DUMPER_LOG_ACTIVE', true);

register_shutdown_function('D::shutdown');

class D
{
	/**
	 * 用于 {@link iconv} 的转换编码.
	 */
	const UTF8 = 1;
	const GBK = 2;
    const LINE = '------------------------------------------------------------------';

	private static $_logPath;
	private static $_iconv = null;
	private static $_message = '';
	private static $_arg_pos = 0;
	private static $_args = array();
	private static $_closed = false;
	private static $_clear = false;
	private static $_asa = false;
	private static $_js_included = false;
    private static $_positions = array();
    private static $_pds_exception=false;
    private static $_profile = [];
    private static $_profile_cost = [];
    private static $_first_log = false;
    private static $_no_clean = false;
    private static $_shutdownLog = [];

    public static function pdsException()
    {
        self::$_pds_exception = true;
    }

    /**
     * 跳过"第一次记录时清空之前的记录"的逻辑
     */
    public static function noclean()
    {
        self::$_no_clean = true;
    }

	/**
	 * 设置是否关闭D组件.
	 */
	public static function close($reverse=false)
	{
		self::$_closed = !$reverse;
	}
	
	/**
	 * 设置是否打印为数组.
	 */
	public static function asa($reverse=false)
	{
		self::$_asa = !$reverse;
	}

    /**
     * 切换字符集到GBK.
     */
	public static function sc()
    {
        self::$_iconv = self::GBK;
    }
	
	/**
	 * 设置字符集.
	 */
	public static function charset($charset)
	{
		if (in_array($charset, array(self::UTF8, self::GBK)))
		{
			self::$_iconv = $charset;
		}
		else
		{
			throw new Exception('You must use D::UTF8 or D::GBK as the charset.');
		}
	}

    /**
     * 设置字符集为GBK.
     */
	public static function gbk()
    {
        self::$_iconv = self::GBK;
    }

    /**
     * 设置字符集为utf8.
     */
    public static function utf8()
    {
        self::$_iconv = self::UTF8;
    }

    /**
     * 在当前页面输出字符集设置header.
     */
    public static function header($charset=null)
    {
        if (!$charset || !in_array($charset, array(self::UTF8, self::GBK)))
        {
            $charset = self::GBK;
        }
        $text = ($charset == self::UTF8) ? 'UTF-8' : 'GB2312';
        header('Content-Type:text/html; charset=' . $text);
    }
	
	/**
	 * 高亮打印参数.
	 */
	public static function pd()
	{
		self::pdInternal(func_get_args());
	}
	
	/**
	 * 直接打印参数.
	 */
	public static function pds()
	{
		self::pdsInternal(func_get_args());
	}
	
	/**
	 * 打印参数并将结果记录到文件中.
	 */
	public static function log()
	{
        // 第一次记录时清空之前的记录
        if (self::$_first_log === false){
            self::$_clear = true;
            self::$_first_log = true;
        }

        // 跳过"第一次记录时清空之前的记录"的逻辑
        if (self::$_no_clean === true){
            self::$_clear = false;
        }

		self::logInternal(func_get_args());
	}
	
	/**
	 * 同 {@link log}，但会清空文件原有内容.
	 */
	public static function logc()
	{
		self::$_clear = true;
		self::logInternal(self::initLogcDefaultArgs(func_get_args()));
	}
	
	/**
	 * 同 {@link pd}，但会终止程序.
	 */
	public static function pde()
	{
		self::pdInternal(func_get_args(), true);
	}
	
	/**
	 * 同 {@link pds}，但会终止程序.
	 */
	public static function pdse()
	{
		self::pdsInternal(func_get_args(), true);
	}
	
	/**
	 * 同 {@link log}，但会终止程序.
	 */
	public static function loge()
	{
        // 第一次记录时清空之前的记录
        if (self::$_first_log === false){
            self::$_clear = true;
            self::$_first_log = true;
        }

        // 跳过"第一次记录时清空之前的记录"的逻辑
        if (self::$_no_clean === true){
            self::$_clear = false;
        }

		self::logInternal(func_get_args(), true);
	}
	
	/**
	 * 同 {@link logc}，但会终止程序.
	 */
	public static function logce()
	{
		self::$_clear = true;
		self::logInternal(self::initLogcDefaultArgs(func_get_args()), true);
	}
	
	/**
	 * 内部调用方法，用于打印前初始化被打印参数列表.
	 * @params array $args 被打印的参数列表数组.
	 * 要求必须提供被打印参数列表.
	 */
	private static function initArgs($args)
	{
		$count = count($args);
		if ($count == 0)
		{
			exit('You must input data when using D methods.');
		}
	}

    /**
     * 内部调用方法，当logc()/logce()没有给参数时，打印默认的内容标识重新初始化日志文件内容，
     * 方便在需要重新初始化日志文件内容时可以不传参数.
     * @return array
     */
	private static function initLogcDefaultArgs($args)
    {
        if ($args === array()){
            return array('Rebooting the log file ... ' . rand());
        }else{
            return $args;
        }
    }
	
	/**
	 * 内部调用方法，打印参数并高亮显示输出.
	 * @param array $args 要打印的参数列表数组.
	 * @param boolean $terminate 打印后是否终止程序执行，true表示是，false表示否，默认是false.
	 * @return void 该方法不返回数据，打印后高亮显示输出结果.
	 */
	private static function pdInternal($args, $terminate=false)
	{
		if (!self::$_closed)
		{
			self::initArgs($args);
			
			self::$_arg_pos = 0;
			foreach ($args as $arg)
			{
				$content = self::pdo($arg);

				// highlight
				$content = highlight_string("\n<?php\n$content", true);

				// 过滤掉最外层 PHP 开始和结束标签
				$content = substr_replace($content, '', strpos($content, '<br />'), strlen('<br />'));
				$content = substr_replace($content, '', strpos($content, '&lt;?php<br />'), strlen('&lt;?php<br />'));
				
				// 默认只显示
				$lines = explode("<br />", $content);
				if (count($lines) > 25)
				{
					$content = str_replace('<code>', '<code class="hide">', $content);
				}
				
				$content = self::prefixMessage($content, true);
				$content = '<div>' . $content . '</div>';
				$content = self::iconv($content);
				
				// js
				if (!self::$_js_included)
				{
					$js = '<script type="text/javascript">';
					$js .= file_get_contents(dirname(__FILE__) . '/jquery-1.7.1.min.js');
					$js .= ';$(document).ready(function(){
								$(".toggle").click(function(){
									$(this).next("code").fadeToggle(0);
								});
							});';
					$js .= '</script>';
					$js .= '<style type="text/css">.hide{display:none;}</style>';
					
					$content = $js . $content;
					self::$_js_included = true;
				}
				
				echo $content;
				
				self::$_arg_pos++;
			}
			self::$_arg_pos = 0;
			
			if ($terminate)
			{
				exit;
			}
		}
	}
	
	/**
	 * 内部调用方法，直接打印参数并输出.
	 * @param array $args 要打印的参数列表数组.
	 * @param boolean $terminate 打印后是否终止程序执行，true表示是，false表示否，默认是false.
	 * @return void 该方法不返回数据，打印后直接输出结果.
	 */
	private static function pdsInternal($args, $terminate=false)
	{
		if (!self::$_closed)
		{
			self::initArgs($args);
				
			self::$_arg_pos = 0;
			foreach ($args as $arg)
			{
				$content = self::pdo($arg);
				$content = self::prefixMessage($content);
				$content = self::iconv($content);
				echo $content;

				self::$_arg_pos++;
			}
			self::$_arg_pos = 0;
			
			if ($terminate)
			{
				exit;
			}
		}
	}

	public static function msecDate($timestamp=null, $timezone=false)
    {
        if (!$timestamp){
            $timestamp = microtime(true);
        }
        // 固定小数位数，保持时间列对齐
        $timestamp = number_format($timestamp, 6, '.', '');
        list($sec, $usec) = explode(".", $timestamp);
        $res = date('H:i:s', $sec) . '.' . $usec . ' ' . date('Y/m/d', $sec);
        if ($timezone){
            $res .= ' ' . date_default_timezone_get();
        }
        return $res;
    }
	
	/**
	 * 内部调用方法，打印参数并将结果记录到文件中.
	 * @param array $args 要打印的参数列表数组.
	 * @param boolean $terminate 打印后是否终止程序执行，true表示是，false表示否，默认是false.
	 * @return void 该方法不返回数据，默认会把打印的数据记录到站点根目录下名为DumperLogFile.txt
	 * 的文件中.如果指定了{@link $logPath}则会记录到该目录下这个文件中.
	 */
	private static function logInternal($args, $terminate=false)
	{
		if (!self::$_closed)
		{
			if (DUMPER_LOG_ACTIVE)
			{
				self::initArgs($args);
				
				self::$_arg_pos = 0;
				foreach ($args as $arg)
				{
					$content = self::pdo($arg);
				
					$content = self::prefixMessage($content);
					$content = self::msecDate(null, true) . ' ' . $content;
					$content = self::iconv($content);

                    self::logSaveToFile($content);
					self::$_arg_pos++;
				}
				self::$_arg_pos = 0;
			}
			
			if ($terminate)
			{
				exit;
			}
		}
	}

	// save to file
	private static function logSaveToFile($content)
    {
        $file = self::getLogPath() . '/DumperLogFile.ig.txt';
        if (self::$_clear)
        {
            file_put_contents($file, $content);
            self::$_clear = false;
        }
        else
        {
            file_put_contents($file, $content, FILE_APPEND);
        }
    }

	/**
     * 获取记录文件所在目录.
	 * @return string 使用带文件记录的打印方式时，可以设置这个目录用来保存记录文件.
	 * 如果没有指定，web调用默认使用站点根目录，命令行调用默认使用第一次调用脚本所在目录.
     * 要求该目录可读写.
     * @link {setLogPath}
	 */
	public static function getLogPath()
    {
        if (self::$_logPath === null)
        {
            $path = ($_SERVER['DOCUMENT_ROOT'] != '') ? $_SERVER['DOCUMENT_ROOT'] : '.';
            self::setLogPath($path);
        }
        return self::$_logPath;
    }

    /**
     * 设置记录文件所在目录.
     * @param $path
     */
    public static function setLogPath($path)
    {
        $path = realpath($path);
        if (is_dir($path) && is_readable($path) && is_writeable($path))
        {
            self::$_logPath = rtrim(str_replace('\\', '/', $path), '/');
        }
        else
        {
            throw new Exception('Please make sure that the log path is an existent, readable and writerable directory.');
        }
    }
	
	/**
	 * 内部调用方法，打印参数.
	 * @param array $arg 要打印的参数.
	 * @return string 打印参数并返回打印结果文本.
	 */
	private static function pdo($arg)
	{
		if (self::$_asa)
		{
			return CVarDumper::dumpAsString($arg) . "\n";
		}
		else
		{		
			/*
			// 清掉之前开启的缓存,避免后续捕捉到
			if ($level = ob_get_level())
			{
				for ($i=0; $i<$level; $i++)
				{
					ob_end_clean();
				}
			}
			*/
			
			// 开启缓存,打印,并捕捉打印内容
			ob_start();
			var_dump($arg);
			$content = ob_get_clean();
            self::formatDumpContent($content);
			return $content;
		}
	}

	private static function formatDumpContent(&$content)
    {
        // 去除箭头两端以及数组大括号前端的空格,去除空数组大括号之间的空白
        $content = preg_replace('/=>\s*/', ' => ', $content);
        $content = preg_replace('/(array\(\d+\))\s{/', '\1{', $content);
        $content = preg_replace('/(array\(\d+\){)\s*}/', '\1}', $content);

        // 将数组项数字和字符串长度数字设置最低长度为3位，不足则前补0
        $content = preg_replace_callback('/string\((\d+)\)/', array('D', 'zeroPadding'), $content);
        //$content = preg_replace('/string\((\d+)\)/', '', $content);
        $content = preg_replace_callback('/\[(\d+)\]/', array('D', 'zeroPadding'), $content);

        // 去除数组元素索引的双引号
        //$content=preg_replace('/\[\"/','[',$content);
        //$content=preg_replace('/\"\]/',']',$content);
    }
	
	/**
	 * 内部调用方法，在打印结果的数组序号部分填充0保持对齐.
	 * @param array $matches 调用PHP函数 {@link preg_replace_callback} 的匹配结果.
	 * @return string 返回填充后文本.只处理序号位数在3以内的.
	 * 
	 * @todo 如果能够根据数组元素的大小来决定填充0的个数就更好
	 * 但是，因为是字符正则匹配替换，所以可能实现起来有困难。
	 * 前补0换作前补空格或者其它字符会不会更好？
	 */
	private static function zeroPadding($matches)
	{
		$text = $matches[0];
		$num = $matches[1];
		$repeats = (3 - strlen($num)) < 0 ? 0 : (3 - strlen($num));
		$prefix = str_repeat('0', $repeats);
		return preg_replace('/\d+/', $prefix . $num, $text);
	}
	
	private static function namesMap($funcName)
	{
		$map = array(
			'rand'=>'rand()',
			'rande'=>'rand()',
			'args'=>'args',
			'post'=>'$_POST',
			'poste'=>'$_POST',
			'get'=>'$_GET',
			'gete'=>'$_GET',
			'request'=>'$_REQUEST',
			'requeste'=>'$_REQUEST',
			'session'=>'$_SESSION',
			'sessione'=>'$_SESSION',
			'cookie'=>'$_COOKIE',
			'cookiee'=>'$_COOKIE',
			'files'=>'$_FILES',
			'filese'=>'$_FILES',
			'server'=>'$_SERVER',
			'servere'=>'$_SERVER',
			'globals'=>'$GLOBALS',
			'globalse'=>'$GLOBALS',
			'usage'=>'usage',
		);
		
		return isset($map[$funcName]) ? $map[$funcName] : '';
	}
	
	/**
	 * 内部调用方法，在打印结果文本前面附加被打印参数名称.
	 * @param string $content 打印的结果文本.
	 * @param boolean $html 是否高亮打印获取参数名，true表示是，false表示否，默认是false.
	 * @return string 返回附加参数名之后的打印结果文本.
	 */
	private static function prefixMessage($content, $highlight=false)
	{
        // 调用堆栈
        $d = self::getDebugBacktrace();

        // 调用位置行
        $v = self::getDebugBacktraceRow($d);

        // 调用位置
        $position = self::getPositionFromDebugBacktraceRow($v);

        if (self::$_message != '')
        {
            // 指定名称
            $message = self::$_message;
        }
        else
        {
            if ($v !== [])
            {
                $message = self::namesMap($v['function']);
                if ($message == '')
                {
                    $message = self::fetchArgName($v['file'], $v['line']);
                    if ($v['function'] == 'count')
                    {
                        $message = 'count(' . $message . ')';
                    }
                }
            }
            else
            {
                $message = "Can't get the arg message.";
            }
        }
		
		return $highlight ? '<span class="toggle">' . $position . '#' . $message . '</span> '. $content : $position . '#' . $message . ' ' . $content;
	}

	private static function getPositionFromDebugBacktrace()
    {
//        return 'dd';

        // 调用堆栈
        $d = self::getDebugBacktrace();

        // 调用位置行
        $v = self::getDebugBacktraceRow($d);

        // 调用位置
        $position = self::getPositionFromDebugBacktraceRow($v);

        return $position;
    }

	private static function getDebugBacktrace()
    {
        return debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | !DEBUG_BACKTRACE_PROVIDE_OBJECT);
    }

    private static function getDebugBacktraceRow($d)
    {
        $v = array();
        foreach($d as $row)
        {
            if(isset($row['file']) && (strpos($row['file'], 'D.php') === false))
            {
                $v = $row;
                break;
            }
        }
        return $v;
    }

    private static function getPositionFromDebugBacktraceRow($v)
    {
        if ($v !== array())
        {
            $position = self::fetchPosition($v);
        }
        else
        {
            $position = '';

        }
        return $position;
    }

	private static function fetchPosition(&$fileinfo)
    {
        $key = md5($fileinfo['file']);
        if (isset(self::$_positions[$key]))
        {
            $position = self::$_positions[$key];
        }
        else
        {
            $pathinfo = pathinfo($fileinfo['file']);
            $lastdir = substr($pathinfo['dirname'], strrpos($pathinfo['dirname'], DIRECTORY_SEPARATOR));
            $position = $lastdir . DIRECTORY_SEPARATOR . $pathinfo['basename'] . '({line})';
            $position = str_replace(DIRECTORY_SEPARATOR, '/', $position);
            self::$_positions[$key] = $position;
        }
        return str_replace('{line}', $fileinfo['line'], $position);
    }
	
	/**
	 * 内部调用方法，根据调用堆栈文件和行号获取调用参数名.
	 * @param string $file 调用堆栈文件路径.
	 * @param int $line_num 调用堆栈行号.
	 */
	private static function fetchArgName($file, $line_num)
	{
		if (count(self::$_args) == 0)
		{
			// 把调用行合并成单行
			$text = '';
			$lines = file($file);
			$line_num = $line_num - 1;
			while (strpos(($line = $lines[$line_num]), 'D::') === false)
			{
				$text = $line . $text;
				$line_num--;
			}
			$text = $line . $text;
			$text = substr($text, strpos($text, 'D::'));
			
			// 遍历查找参数
			$args = array();
			
			// 去除所有空白字符，方便后面字符定位
			$text = preg_replace('/\s*/', '', $text);
			preg_match('/\((.*)\)/', $text, $matches);
			$s = $matches[1];
			
			while ($s != '')
			{
				if (($start = strpos($s, ',')) !== false)
				{
					$end = strpos($s, ')', $start);
					while (substr($s, $end + 1, 1) == ')')
					{
						// 连续闭合括号取最后那个
						$end++;
					}
					
					$middle = strpos(substr($s, $start, $end), '(');
					if ($end !== false && $middle === false)
					{
						// 逗号在闭合括号里面，而且逗号和闭合括号之间没有开始括号，
						// 此时逗号不是参数分割符，取闭合括号内所有内容为一个参数
						$args[] = substr($s, 0, $end + 1);
						$s = substr($s, $end + 1);
					}
					else
					{
						$args[] = substr($s, 0, $start);
						$s = substr($s, $start + 1);
					}
					
					$s = ltrim($s, ',');
				}
				else
				{
					// 只有一个参数
					$args[] = $s;
					$s = '';
				}
			}
			
			self::$_args = $args;
		}
		
		return array_shift(self::$_args);
	}
	
	/**
	 * 内部调用方法，字符集转换.
	 * @param string $content 要转换的文本.
	 * @return string 返回转换后的文本.
	 */
	private static function iconv($content)
	{
		if (self::$_iconv === self::UTF8)
		{
			$content = iconv('gbk', 'utf-8', $content);
		}
		else if (self::$_iconv === self::GBK)
		{
			$content = iconv('utf-8', 'gbk', $content);
		}
		return $content;
	}
	
	public static function bk()
	{
		if (!self::$_closed)
		{
            if (func_num_args()){
                self::pde(func_get_args());
            }else{
                $position = self::getPositionFromDebugBacktrace();
			    exit('<pre> '.$position.'# !!! breakpoint !!! </pre>');
            }
		}
	}
	
	public static function fp()
	{
		if (!self::$_closed)
		{
            $position = self::getPositionFromDebugBacktrace();
			echo '<pre> '.$position.'# ~~~ footprint - '.rand().' ~~~ </pre>';
		}
	}

	public static function blank($num=3, $html=true)
    {
        if (!self::$_closed)
		{
            $str = $html ? "<br/>" : "\n";
            echo str_repeat($str, $num);
		}
    }
	
	public static function rp($file)
	{
		self::pd(array(
		    'file'=>$file,
		    'realpath'=>realpath($file),
            'file_exists'=>file_exists($file),
            'pathinfo'=>pathinfo($file),
        ));
	}

    /**
     * 显示大块文本.
     */
	public static function e($content)
    {
        echo '<pre>' . $content . '</pre>';
    }

    public static function eg($content)
    {
        self::charset(self::UTF8);
        $content = self::iconv($content);
        self::e($content);
    }
	
	public static function date($timestamp=null)
	{
		if ($timestamp === null)
		{
			$timestamp = time();
		}
		self::pd(date('Y-m-d H:i:s', $timestamp));
	}

    /**
     * Begin a profile. The name p means 'beginProfile' for convenience.
     * @param string $token
     * @throws Exception
     */
	public static function p($token='profile')
    {
        if(!isset(self::$_profile[$token])){
            self::$_profile[$token] = microtime(true);
        }else{
            throw new Exception('Profile token ' . $token . ' has been existent.');
        }
    }

    /**
     * End a profile. The name pp means 'endProfie' for convenience.
     * @param string $token
     * @param bool $return
     * @throws Exception
     */
    public static function pp($token='profile', $return=false)
    {
        if(isset(self::$_profile[$token])){
            $now = microtime(true);
            $last = self::$_profile[$token];
            $cost = $now - $last;

            // log cost for compare profile
            self::$_profile_cost[$token] = $cost;
            if ($return) {
                return $cost;
            } else {
                self::$_message = 'profile::' . $token;
                $cost = sprintf('%.9f', $cost);
                self::log($cost);
            }
        }else{
            throw new Exception('Profile token ' . $token . ' is not found.');
        }
    }

    /**
     * Compare two profile. The name p means 'compProfile' for convenience.
     * @param $token_new
     * @param $token_old
     * @throws Exception
     */
    public static function pc($token_new, $token_old)
    {
        if (!isset(self::$_profile_cost[$token_old])){
            throw new Exception('Profile token ' . $token_old . ' is not found.');
        }

        if (!isset(self::$_profile_cost[$token_new])){
            throw new Exception('Profile token ' . $token_new . ' is not found.');
        }

        $diff = self::$_profile_cost[$token_new] - self::$_profile_cost[$token_old];
        $percent = ($diff / self::$_profile_cost[$token_old]) * 100;
        $percent = number_format($percent, 2) . '%';
        self::log(array('diff'=>$diff, 'percent'=>$percent));
    }
	
	public static function count($items)
	{
		self::pd(count($items));
	}
	public static function counte($items)
	{
		self::pde(count($items));
	}
	
	public static function rand($slight=false)
	{
		$slight ? self::pds(rand()) : self::pd(rand());
	}
	
	public static function rande($slight=false)
	{
		$slight ? self::pdse(rand()) : self::pde(rand());
	}

	public static function join()
    {
        return implode('-', func_get_args());
    }

    /**
     * @param bool $log
     * @todo 对匿名函数不起作用.
     */
	public static function args($log=false)
	{
		$d = debug_backtrace(!DEBUG_BACKTRACE_PROVIDE_OBJECT);
		
		// 取第二项
		if (isset($d[1]))
		{
			$caller = $d[1];
			
			// 函数或方法的结构定义
			if (!isset($caller['class']))
			{
				$object = new ReflectionFunction($caller['function']);
			}
			else
			{
				$object = new ReflectionMethod($caller['class'], $caller['function']);
			}
			$res = $caller['args'];
			
			// 参数名列表
//            $res = [];
//			foreach ($object->getParameters() as $index => $param)
//			{
//                $res[$param->getName()] = $caller['args'][$index];
//			}
		}
		else
		{
			$res = 'Not inside a function.';
		}
		$log ? self::log($res) : self::pd($res);
	}
	
	public static function post(){self::pd($_POST);}
	public static function poste(){self::pde($_POST);}
	
	public static function get(){self::pd($_GET);}
	public static function gete(){self::pde($_GET);}
	
	public static function request(){self::pd($_REQUEST);}
	public static function requeste(){self::pde($_REQUEST);}
	
	public static function session(){self::pd($_SESSION);}
	public static function sessione(){self::pde($_SESSION);}
	
	public static function cookie(){self::pd($_COOKIE);}
	public static function cookiee(){self::pde($_COOKIE);}
	
	public static function files(){self::pd($_FILES);}
	public static function filese(){self::pde($_FILES);}
	
	public static function server(){self::pd($_SERVER);}
	public static function servere(){self::pde($_SERVER);}
	
	public static function globals(){self::pd($GLOBALS);}
	public static function globalse(){self::pde($GLOBALS);}

    public static function iget($name, $return=false)
    {
        if ($return)
        {
            return ini_get($name);
        }
        else
        {
            self::pd(ini_get($name));
        }
    }
    public static function igete($name){self::pde(ini_get($name));}

	public static function usage($log=false, $string=false)
	{
        $k = 1024;
		$m = 1024 * 1024;

        if (!$string){
            $u = memory_get_usage();
            $u_real = memory_get_usage(true);
            $pu = memory_get_peak_usage();
            $pu_real = memory_get_peak_usage(true);

            $usage = array(
                'memory_get_usage'=>array('B'=>$u, 'K'=>$u/$k, 'M'=>$u/$m),
                'memory_get_usage_real'=>array('B'=>$u_real, 'K'=>$u_real/$k, 'M'=>$u_real/$m),
                'memory_get_peak_usage'=>array('B'=>$pu, 'K'=>$pu/$k, 'M'=>$pu/$m),
                'memory_get_peak_usage_real'=>array('B'=>$pu_real, 'K'=>$pu_real/$k, 'M'=>$pu_real/$m),
                'memory_limit'=>ini_get('memory_limit'),
            );
        }
        else{
            $u = mb_strwidth($string, 'UTF-8');
            $usage = array(
                'string_usage'=>array('B'=>$u, 'K'=>$u/$k, 'M'=>$u/$m),
                'memory_limit'=>ini_get('memory_limit'),
            );
        }

		$log ? D::log($usage) : D::pd($usage);
	}

	public static function usagee($log=false)
    {
        self::usage($log);
        exit;
    }
	
	/**
	 * 转换秒数成年月日数据.
	 * @param int 秒数.
	 * @return array 返回包含年月日数据的数组，格式如下：
	 * array(
	 * 	  'years' => 1,
	 * 	  'days' => 2,
	 * 	  'hours' => 3,
	 * 	  'minutes' => 4,
	 * 	  'seconds' => 5,
	 * )
	 */
	public static function sec2time($seconds)
	{
		$items = array(
			'years'=>0,
			'days'=>0,
			'hours'=>0,
			'minutes'=>0,
			'seconds'=>0
		);
		
		$seconds = (int)$seconds;
		if($seconds >= 31556926)
		{
			$items['years'] = floor($seconds/31556926);
			$seconds = ($seconds%31556926);
		}
		if($seconds >= 86400)
		{
			$items['days'] = floor($seconds/86400);
			$seconds = ($seconds%86400);
		}
		if($seconds >= 3600)
		{
			$items['hours'] = floor($seconds/3600);
			$seconds = ($seconds%3600);
		}
		if($seconds >= 60)
		{
			$items['minutes'] = floor($seconds/60);
			$seconds = ($seconds%60);
		}
		$items['seconds'] = floor($seconds);
		self::pd($items);
	}

	public static function tracelog($filter=false)
    {
        self::log(self::getTraceAsArray($filter));
    }

	// echo trace as plain text string
	public static function trace($filter=false)
	{
        echo self::traceInternal($filter);
	}

    // echo trace as html string
	public static function traceHtml($filter=false)
    {
        echo nl2br(self::traceInternal($filter));
    }

    // echo trace as html string
	public static function traceArray($filter=false)
    {
        self::pd(self::getTraceAsArray($filter));
    }

	// return trace as plain text string
	public static function getTrace($filter=false)
    {
        return self::traceInternal($filter);
    }

    // return trace as plain text string
    public static function getTraceAsArray($filter=false)
    {
        return self::traceInternal($filter, false);
    }

    /**
     * 生成堆栈信息.
     * @param bool $filter 要过滤掉的路径关键词，false表示未指定，默认是false.
     * @param bool $return_string true表示以字符串格式返回，false表示按数组格式返回，默认是true.
     * @return array|string 按字符串返回时，按\n换行符换行；按数组返回时，不含换行符.
     */
	private static function traceInternal($filter=false, $return_string=true)
    {
        // init filter param
        if (is_string($filter) && trim($filter) != ''){
            $filter = trim($filter);
        }else{
            $filter = false;
        }

        $res = [];
        $d = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | !DEBUG_BACKTRACE_PROVIDE_OBJECT);
        $k = 0;
		foreach($d as $i=>$v)
		{
            $file = isset($v['file']) ? $v['file'] : '';
            $line = isset($v['line']) ? $v['line'] : '';

            // filter
            if ($filter && strpos($file, $filter) !== false){
                continue;
            }
            if (strpos($file, 'D.php') !== false){
                continue;
            }

            $prefix = '00';
			if($k<100){
				$prefix = '0';
            }
			if($k<10){
				$prefix = '00';
            }

			if(isset($v['class'])){
				$text = '#'.$prefix.$k.' '.$file.'('.$line.'): '.$v['class'].$v['type'].$v['function'].'()';
            }else{
				$text = '#'.$prefix.$k.' '.$file.'('.$line.'): '.$v['function'].'()';
            }

            // add line break as string
            if ($return_string){
                $text .= "\n";
            }

            $res[] = $text;

            $k++;
		}
		$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
		$res[] = "#{main} REQUEST_URI={$request_uri}";

        return $return_string ? implode($res) : $res;
    }
	
	public static function handleError($error, $message, $file, $line)
	{
		restore_error_handler();
		//restore_exception_handler();

		if ($error & error_reporting())
		{
            try{

                $log="$message ($file:$line)\nStack trace:\n";
                $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS | !DEBUG_BACKTRACE_PROVIDE_OBJECT);
                // skip the first 3 stacks as they do not tell the error position
                if(count($trace)>3)
                    $trace=array_slice($trace,3);
                foreach($trace as $i=>$t)
                {
                    if(!isset($t['file']))
                        $t['file']='unknown';
                    if(!isset($t['line']))
                        $t['line']=0;
                    if(!isset($t['function']))
                        $t['function']='unknown';
                    $log.="#$i {$t['file']}({$t['line']}): ";
                    if(isset($t['object']) && is_object($t['object']))
                        $log.=get_class($t['object']).'->';
                    $log.="{$t['function']}()\n";
                }
                if(isset($_SERVER['REQUEST_URI']))
                    $log.='REQUEST_URI='.$_SERVER['REQUEST_URI'];

                // set message
                self::$_message = 'Error';

                // print
                $data = array(
                    'error' => $error,
                    'message' => $message,
                    'file' => $file,
                    'line' => $line
                );
                $method = self::$_pds_exception ? 'pds' : 'pd';
                self::$method($data);

                // log
                self::log($data);

                // reset message
                self::$_message = '';

                // discard output
                if (DUMPER_HANDLER_DISCARD_OUTPUT)
                {
                    self::discardOutput();
                }

                // stop processing this error by the next handler
                exit(1);

            }catch(Exception $e){
                // use the most primitive way to log error
				$msg = get_class($e).': '.$e->getMessage().' ('.$e->getFile().':'.$e->getLine().")\n";
				$msg .= $e->getTraceAsString()."\n";
				$msg .= "Previous error:\n";
				$msg .= $log."\n";
				$msg .= '$_SERVER='.var_export($_SERVER,true);
				error_log($msg);
				exit(1);
            }
        }
	}
	
	public static function handleException($exception)
	{
		//restore_error_handler();
		restore_exception_handler();

        try{

            // set message
		    self::$_message = 'Exception';

            // print
            $data = array(
                'exception' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTrace(),
            );
            $method = self::$_pds_exception ? 'pds' : 'pd';
            self::$method($data);

            // log
            self::log($data);

            // reset message
            self::$_message = '';

            // discard output
            if (DUMPER_HANDLER_DISCARD_OUTPUT)
            {
                self::discardOutput();
            }

            // stop processing this error by the next handler
            exit(1);

        }catch(Exception $e){
            // use the most primitive way to log error
			$msg = get_class($e).': '.$e->getMessage().' ('.$e->getFile().':'.$e->getLine().")\n";
			$msg .= $e->getTraceAsString()."\n";
			$msg .= "Previous exception:\n";
			$msg .= get_class($exception).': '.$exception->getMessage().' ('.$exception->getFile().':'.$exception->getLine().")\n";
			$msg .= $exception->getTraceAsString()."\n";
			$msg .= '$_SERVER='.var_export($_SERVER,true);
			error_log($msg);
			exit(1);
        }
	}
	
	// 丢弃输出，该功能未实现
	private static function discardOutput()
	{
		ob_clean();
	}
	
	/**
	 * Reflect a function.
	 * param string $function 函数名.
	 @param boolean $highlight 是否高亮打印，true表示是，false表示否，默认是true.
	 */
	public static function refF($function, $highhight=true)
	{
		$object = new ReflectionFunction($function);
		
		$name = $function . '()';
		$info = array(
			'Name'=>$name,
			'File'=>$object->getFileName(),
			'Lines'=>'{' . $object->getStartLine() . ', ' . $object->getEndLine() . '}',
		);
		self::$_message = 'Function ' . $name;
		self::pd($info);
		self::$_message = '';
	}
	
	/**
	 * 同 {@link refF}，但会终止程序.
	 */
	public static function refFe($function, $highhight=true)
	{
		exit(self::refF($function, $highhight));
	}
	
	/**
	 * Reflect a class or object.
	 * @param mixed $class 类名或者对象.
	 * @param boolean $highlight 是否高亮打印，true表示是，false表示否，默认是true.
	 */
	public static function ref($class, $highlight=true)
	{
		$data = self::refExplodeInternal($class);
		$highlight ? self::pd($data) : self::pds($data);
	}
	
	/**
	 * 同 {@link ref}，但会终止程序.
	 */
	public static function refe($class, $highhight=true)
	{
		exit(self::ref($class, $highhight));
	}

    private static function refExplodeInternal($class)
    {
        if (is_string($class) && (trim($class) != ''))
		{
			$object = new ReflectionClass($class);
		}
		else if (is_object($class))
		{
			$object = ($class instanceof ReflectionClass) ? $class : new ReflectionObject($class);
		}
		else
		{
			throw new Exception('Class name or object should be given.');
		}
		return self::refExplode($object);
    }
	
	// explode reflection object
	private static function refExplode($object)
	{
		$ref = array(
			'className' => $object->getName(),
			'fileName' => $object->getFileName(),
			'interfaceNames' => $object->getInterfaceNames(),
			'properties' => self::refExplodeProperties($object),
			'methods' => self::refExplodeMethods($object),
		);
		
		if (($parent=$object->getParentClass()) instanceof ReflectionClass)
			$ref['parentClass'] = self::refExplode($parent);
		
		return $ref;
	}
	
	// explode reflection properties
	private static function refExplodeProperties($object)
	{
		$properties = array();
		foreach ($object->getProperties() as $property)
		{
			if ($property->isPublic())
				$properties['public properties'][] = $property->getName();
			else if ($property->isProtected())
				$properties['protected properties'][] = $property->getName();
		}
		if (array_key_exists('public properties', $properties))
			sort($properties['public properties']);
		if (array_key_exists('protected properties', $properties))
			sort($properties['protected properties']);
		return $properties;
	}
	
	// explode reflection methods
	private static function refExplodeMethods($object)
	{
		$methods = array();
		foreach ($object->getMethods() as $method)
		{
			if ($method->isPublic())
				$methods['public methods'][] = $method->getName();
			else if ($method->isProtected())
				$methods['protected methods'][] = $method->getName();
		}
		if (array_key_exists('public methods', $methods))
			sort($methods['public methods']);
		if (array_key_exists('protected methods', $methods))
			sort($methods['protected methods']);
		return $methods;
	}

	public static function len($string)
    {
        self::pd(strlen($string));
    }
    
    public static function json($data, $header=false)
    {
        if ($header){
            header('Content-Type:application/json; Charset=utf-8;');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE & JSON_UNESCAPED_SLASHES);
    }

    public static function jsone($data, $header=false)
    {
        if ($header){
            header('Content-Type:application/json; Charset=utf-8;');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE & JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function md5($data)
    {
        self::pd(md5(json_encode($data)));
    }

    public static function md5e($data)
    {
        self::md5($data);
        exit;
    }

    public static function met($sec=1000000)
    {
        ini_set('max_execution_time', $sec);
    }

    public static function curlToFiddler($ch)
    {
        curl_setopt($ch, CURLOPT_PROXY,'127.0.0.1:8888');
    }

    public static function curlLog($ch)
    {
        $hd = fopen(self::getLogPath().'/curllog_'.date('YmdHis').'_'.rand().'.log', 'w');
        curl_setopt($ch, CURLOPT_STDERR , $hd);
    }

    // 转换数组到postman格式
    public static function postman($data, &$res='', $prefix='')
    {
        foreach ($data as $index => $row){
            if (is_array($row)){
                $prefix_next = ($prefix == '') ? $index : $prefix . "[{$index}]";
                self::postman($row, $res, $prefix_next);
            }else{
                // 前缀为空表示一维数组，否则表示多维数组
                if ($prefix == '') {
                    $res .= "\n{$index}:{$row}";
                } else {
                    $res .= "\n{$prefix}[{$index}]:{$row}";
                }
            }
        }
    }

    public static function unpostman($postman, &$res)
    {
        $lines = explode("\n", $postman);
        foreach ($lines as &$line) {
            $line = trim($line);
            $map = explode(':', $line);
            if (is_string($map[1])) {
                $line = "'{$map[0]}' => '{$map[1]}',";
            } else {
                $line = "'{$map[0]}' => {$map[1]},";
            }
        }
        $array = implode("", $lines);
        $array = rtrim($array, ",");
        $array = "[" . $array . "]";
        $res = self::eval($array);
        \D::log($res, $lines);
    }

    // 转换数组到likearray格式
    public static function likearray($data, &$res='', $prefix='', $level=1)
    {
        $res .= "[";
        foreach ($data as $index => $row){
            if (is_array($row)){
                $prefix_next = ($prefix == '') ? $index : $prefix . "[{$index}]";
                self::likearray($row, $res, $prefix_next, $level+1);
            }else{
                $space = str_repeat(" ", $level*4);
                $res .= "\n{$space}'{$index}' => '{$row}'";
            }
        }
        $res .= "\n]";
    }

    /**
     * @todo 目前只能处理一维数组.
     * @param $likearray
     * @param $res
     */
    public static function unlikearray($likearray, &$res)
    {
        // array - add commas
        $lines = explode("\n", $likearray);
        foreach ($lines as &$line) {
            if (trim($line) != '[' && trim($line) != ']' && trim($line) != 'array(' && trim($line) != ');') {
                $line = trim($line, "\r\n") . ",\n";
            }
        }
        $array = implode("", $lines);
        $res = self::eval($array);
    }

    public static function eval($_expression_, $_data_=array())
	{
		if(is_string($_expression_))
		{
			extract($_data_);
			return eval('return '.$_expression_.';');
		}
		else
		{
			$_data_[]=self;
			return call_user_func_array($_expression_, $_data_);
		}
	}

	public static function ob_start()
    {
        ob_start();
    }

    public static function ob_end($log=true)
    {
        if ($log) {
            self::log(ob_get_clean());
        } else {
            return ob_get_clean();
        }
    }

    public static function debugon()
    {
        xdebug_start_trace();
    }

    public static function debugoff()
    {
        xdebug_stop_trace();
    }

    public static function match($pattern, $data, $log=false)
    {
        if (is_string($data)) {
            $data = [$data];
        }

        $res = [];
        foreach ($data as $item) {
            $item = (string)$item;
            preg_match($pattern, $item, $res[$item]);
        }
        //var_dump($res);
        //self::pd($res);
        $log ? self::log($res) : self::pd($res);
    }

    public static function line($log=false)
    {
        $res = str_repeat("=", 100);
        if ($log) {
            self::log($res);
        } else {
            echo "<div>$res</div>";
        }
    }

    /**
     * 将数据转成数组格式.
     * 用于在使用laravel框架式，针对collection和model等存储数据的数据对象收集成数组，方便记录到日志查看.
     * 获取数据后，记录到文件里.
     * @param mixed $row 数组或者可以用数组方式访问的对象，否则抛出异常.
     */
    public static function arrayable($row)
    {
        if (!empty($row)) {

            // 参数必须是数组或者可以用数组方式访问
            // @todo 如果是对象，而且可以用数组方式访问，则假设它一定实现了toArray()，这里后续可能需要针对多种情况优化
            if (!is_array($row) && !($row instanceof ArrayAccess)) {
                throw new Exception('The data parameter must be an array. Your input is '.gettype($row));
            }

            // 如果是对象，则自动调用对象的toArray()方法转转成数组
            if (is_object($row)) {
                $row = $row->toArray();
            }

            // 针对数组遍历获取数据
            $res = self::arrayableInternal($row);

            // 记录
            self::log($res);

        } else {

            self::log($row);
        }
    }

    public static function arrayablee($row)
    {
        self::arrayable($row);
        exit();
    }

    /**
     * 将数据转成数组格式.
     * 用于在使用laravel框架式，针对collection和model等存储数据的数据对象收集成数组，方便记录到日志查看.
     * 该方法只允许被{@see arrayable}调用.
     * @param array $row 数组数据.如果元素是对象而且可以用数组方式访问，则调用toArray()方法获取数据.
     * @return array 返回处理之后的数组.
     */
    private static function arrayableInternal($row)
    {
        // 针对可以用数组方式访问的元素值，调用它的toArray()方法转成数组
        foreach ($row as $index => $value) {
            if (is_object($value) && $value instanceof ArrayAccess) {
                $row[$index] = $value->toArray();
            }
        }

        // 继续递归处理下一级
        foreach ($row as $index => $value) {
            if (is_array($value)) {
                $row[$index] = self::arrayableInternal($value);
            }
        }

        return $row;
    }

    /**
     * Begin the laravel database sql query log. The name s means 'beginSqlLog' for convenience.
     */
    public static function s()
    {
        self::p('s_total_profile');
        DB::enableQueryLog();
    }

    public static function sd()
    {
        $data = DB::getQueryLog();
        self::$_message = 'query log';
        self::loge($data);
    }

    /**
     * End the laravel database sql query log. The name ss means 'endSqlLog' for convenience.
     * @param bool $asString Join the sql as one string when true.
     */
    public static function ss($asString=false)
    {
        // time total
        $timeTotal = self::pp('s_total_profile', true);

        // query list
        $data = DB::getQueryLog();

        if (count($data)) {

            // parse query list
            $data = QueryLog::parseQueryLog($data);

            // explain
            QueryLog::explain($data);

            // sign and repeat
            QueryLog::signAndRepeat($data);
        }

        // stat the query list
        $stat = QueryLog::stat($data, $timeTotal);

        if (count($data)) {

            // mark avg time
            QueryLog::markAvgTime($data, $stat);
        }

        self::$_message = '$stat';
        self::log(QueryLog::formatStat($stat) . QueryLog::formatDataToDisplay($data));
    }

    public static function sss()
    {
        $data = DB::getQueryLog();

        $data = self::parseQueryLog($data);

        if ($asString) {

            // only join the query field
            $data = array_column($data, 'query');

            // format the sql line for pretty showing
            $data = array_map(function ($value) {

                // replace the (?,?,?) to (?) for reducing the sql
                $value = preg_replace('/\(.*\)/', '(?)', $value);

                // prepend md5 value to mark the line
                $value = md5($value) . ' ' . $value;

                return $value;

            }, $data);

            // join to one line with breaklines
            $data = "\n" . count($data) . "\n" . implode("\n", $data) . "\n";
        }

        self::log($data);
    }

    public static function readpath($path, $exclude=[])
    {
        $files = [];

        if (!file_exists($path)) {
            throw new \Exception("路径 {$path} 不存在");
        }

        if (!is_dir($path)) {
            throw new \Exception("路径 {$path} 不是一个目录");
        }

        if (!is_readable($path)) {
            throw new \Exception("路径 {$path} 不可读");
        }

        $dir = opendir($path);
        while ($file = readdir($dir)) {

            // 过滤
            if ($file == '.' || $file == '..' || in_array($file, $exclude)) {
               continue;
            }

            $files[] = $file;
        }

        return $files;
    }

    public static function listpath($path, $exclude=[])
    {
        // 读取文件
        $files = self::readpath($path, $exclude);

        // 格式化
        $data = [];
        foreach ($files as $file) {
            $data[] = ['text'=>$file, 'url'=>'./'.$file];
        }

        // 渲染
        self::listurl($data);
    }

    public static function listurl($data)
    {
        $html = '<div><ul>';
        foreach ($data as $row) {
            $html .= "<li><a href='./{$row['url']}' target='_blank'>{$row['text']}</a></li>";
        }
        $html .= '</ul></div>';

        echo $html;
    }

    public static function step($end=false)
    {
        $content = self::prefixMessage('');
        if ($end) {
            $content .= ' end';
        } else {
            $content .= ' begin';
        }
        $content = self::msecDate(null, true) . ' ' . $content . '';
        self::logSaveToFile("$content\n");
    }

    public static function xdebug()
    {
        ini_set('xdebug.overload_var_dump', 0);
    }

    public static function shutdown()
    {
        if (!empty(self::$_shutdownLog)) {
            self::log(self::$_shutdownLog);
        }

        if (function_exists('DB')) {
            \D::ss();
        }
    }

    public static function shutdownLog($data)
    {
        self::$_shutdownLog[] = $data;
    }

    public static function tableList($data, $return=false)
    {
        $html = '<div>';

        foreach ($data as $title => $list) {
            $html .= '<div>';
            $html .= '<h3>';
            $html .= self::table($list, true);
            $html .= '</h3>';
            $html .= '</div>';
        }

        $html .= '</div>';

        if ($return) {
            return $html;
        } else {
            echo $html;
        }
    }

    public static function table($data, $return=false)
    {
        $html = '<table>';

        // header
        $html .= '<thead>';

        $multi = is_array(pos($data));

        if ($multi) {
            // 多维数组
            $keyList = array_keys(pos($data));
        } else {
            // 一维数组
            $keyList = array_keys($data);
        }

        foreach ($keyList as $item) {
            $html .= '<th>';
            $html .= $item;
            $html .= '</th>';
        }

        $html .= '</thead>';

        // body
        $html .= '<tbody>';

        foreach ($data as $row) {

            if ($multi) {
                foreach ($row as $item) {
                    self::tableTd($html, $item);
                }
            } else {
                self::tableTd($html, $row);
            }
        }

        $html .= '</tbody>';
        $html .= '</table>';

        if ($return) {
            return $html;
        } else {
            echo $html;
        }
    }

    private static function tableTd(&$html, $item)
    {
        $html .= '<td>';
        if (is_array($item) || is_object($item)) {
            $html .= json_encode($item, JSON_UNESCAPED_UNICODE);
        } else {
            $html .= $item;
        }

        $html .= '</td>';
    }

    public static function br($count=2)
    {
        while ($count) {
            echo '<br/>';
            $count--;
        }
    }

    public static function div($string, $return=false)
    {
        $string = '<div>'.$string.'</div>';

        if ($return) {
            return $string;
        } else {
            echo $string;
        }
    }

    public static function fpl()
    {
        self::$_message = 'footprint';
        self::log('~~~ footprint - '.rand().' ~~~');
    }

    public static function baseinfo()
    {
        $constList = get_defined_constants();
        ksort($constList);
        self::log($constList);
    }
}

class QueryLog
{
    /**
     * Optimize level.
     */
    const OPTIMIZE_LEVEL_URGENCY = 1;
    const OPTIMIZE_LEVEL_SHOULD = 2;
    const OPTIMIZE_LEVEL_SUGGEST = 3;
    const OPTIMIZE_LEVEL_NEEDLESS = 4;

    /**
     * Time limit.
     */
    const TIME_TOTAL_LIMIT = 3000;
    const SQL_TIME_TOTAL_LIMIT = 1000;

    private static $avgCountList;

    public static function parseQueryLog($data)
    {
        // 记录laravel数据库sql时，sql只有占位符和绑定数据，不方便复制查询，这里查找并替换问号
        foreach ($data as $index => $row) {

            $query = $row['query'];

            // 处理绑定关系
            if (!empty($row['bindings'])) {
                $bindings = $row['bindings'];

                // 循环查找并替换
                $start = 0; // 开始查找位置
                $key = 0; // 绑定内容位置
                while (($pos = strpos($query, '?', $start)) !== false) {

                    // 绑定内容
                    $value = $bindings[$key];

                    // 绑定内容是字符串，加双引号
                    if (is_string($value)) {
                        $value = "'{$value}'";
                    }

                    // 替换问号出现的位置 - 截取问号前后部分，中间用值代替
                    $query = substr($query, 0, $pos) . $value . substr($query, $pos + 1);

                    // 查找下一个问号
                    $start = $pos+1;

                    // 绑定内容位置进一
                    $key++;
                }
            }

            $row = array_merge(['replace'=>$query], $row);

            $data[$index] = $row;
        }

        return $data;
    }

    public static function explain(&$data)
    {
        foreach ($data as &$row) {

            // unset the query and bindings field
            unset($row['query'], $row['bindings']);

            // create sql sign
            $row['sign'] = md5($row['replace']);

            // is the sql slow?
            if ($row['time'] > self::SQL_TIME_TOTAL_LIMIT) {
                $row['slow'] = '慢，超过 ' . self::SQL_TIME_TOTAL_LIMIT / 1000 . ' 秒';
            }

            // time as second
            $row['time_second'] = $row['time'] / 1000;

            // explain the select sql
            $row['explain'] = [];
            $explainList = \DB::select('explain '.$row['replace']);
            foreach ($explainList as $explain) {
                $explainString = '';
                foreach ($explain as $attribute => $value) {
                    $explainString .= strtoupper($attribute) . '=' . $value . '  ';
                }
                $row['explain'][] = $explainString;
            }
        }
    }

    public static function signAndRepeat(&$data)
    {
        // 标识列表
        $signList = array_column($data, 'sign');

        // 附加次数
        foreach ($data as $index => &$row) {

            $repeatRow = [];

            foreach ($signList as $key => $sign) {

                // 跳过当前行
                if ($index == $key) {
                    continue;
                }

                // 记录重复行号
                if ($sign == $row['sign']) {
                    $repeatRow[] = $key;
                }
            }

            // 显示重复行号
            if (count($repeatRow)) {
                $row['repeat'] = '重复：和第 ' . implode(', ', $repeatRow) . ' 行重复，一共重复 ' . count($repeatRow) . ' 次';
            }

            unset($row['sign']);
        }
    }

    public static function stat($data, $timeTotal)
    {
        $stat = [];

        // total time stat
        $stat['time_total'] = $timeTotal;
        $stat['time_total_limit'] = self::TIME_TOTAL_LIMIT / 1000;
        $timeTotalIsExceeded = self::getIsExceeded($stat['time_total'], $stat['time_total_limit']);
        $stat['time_is_exceeded'] = $timeTotalIsExceeded;
        $stat['time_total_exceeded'] = self::formatIsExceeded($timeTotalIsExceeded);

        // sql count
        $stat['sql_count_total'] = count($data);

        if ($stat['sql_count_total']) {

            // sql time stat
            $stat['sql_time_total_micro'] = array_sum(array_column($data, 'time'));
            $stat['sql_time_total'] = $stat['sql_time_total_micro'] / 1000;
            $stat['sql_time_total_limit'] = self::SQL_TIME_TOTAL_LIMIT / 1000;
            $sqlTimeTotalIsExceeded = self::getIsExceeded($stat['sql_time_total'], $stat['sql_time_total_limit']);
            $stat['sql_time_is_exceeded'] = $sqlTimeTotalIsExceeded;
            $stat['sql_time_total_exceeded'] = self::formatIsExceeded($sqlTimeTotalIsExceeded);
            $stat['sql_time_percent_of_total'] = self::getRate($timeTotal, $stat['sql_time_total']);

            // else time stat
            $stat['else_time_total'] = $timeTotal - $stat['sql_time_total'];
            $stat['else_time_total_limit'] = $stat['time_total_limit'] - $stat['sql_time_total_limit'];
            $elseTimeTotalIsExceeded = self::getIsExceeded($stat['else_time_total'], $stat['else_time_total_limit']);
            $stat['else_time_is_exceeded'] = $elseTimeTotalIsExceeded;
            $stat['else_time_total_exceeded'] = self::formatIsExceeded($elseTimeTotalIsExceeded);
            $stat['else_time_percent_of_total'] = self::getRate($timeTotal, $stat['else_time_total']);

            // sql time avg
            $stat['sql_time_avg'] = $stat['sql_time_total'] / $stat['sql_count_total'];
            $stat['sql_time_avg_micro'] = $stat['sql_time_total_micro'] / $stat['sql_count_total'];

            // sql count avg
            $avgCountList = self::getSqlExceededAvgList($stat['sql_time_avg_micro'], $data);
            $avgCount = count($avgCountList);
            if ($avgCount) {
                $stat['sql_exceeded_avg_count'] = $avgCount . ' 个，分别是 ' . implode(', ', $avgCountList);
            } else {
                $stat['sql_exceeded_avg_count'] = '0 个';
            }

            // compare sql time avg with sql time total exceeded
            if ($sqlTimeTotalIsExceeded['exceeded'] > 0) {
                $stat['sql_time_avg_with_time_total_exceeded'] = sprintf('%.2f', $sqlTimeTotalIsExceeded['exceeded'] / $stat['sql_time_avg']);
            } else {
                $stat['sql_time_avg_with_time_total_exceeded'] = 0;
            }

            // sql repeat list
            $sqlRepeatList = self::getSqlRepeatList($data);
            $sqlRepeatCount = count($sqlRepeatList) / 2;
            $stat['sql_repeat_list'] = "，{$sqlRepeatCount} 个重复";
            if ($sqlRepeatCount) {
                $stat['sql_repeat_list'] .= "，分别是 " . implode(', ', $sqlRepeatList);
            }
        }

        return $stat;
    }

    public static function getSqlRepeatList($data)
    {
        $repeatList = [];
        foreach ($data as $index => $item) {
            if (isset($item['repeat'])) {
                $repeatList[] = $index;
            }
        }
        return $repeatList;
    }

    public static function markAvgTime(&$data, $stat)
    {
        // avg count list
        $avgCountList = self::getSqlExceededAvgList($stat['sql_time_avg_micro'], $data);

        // time elapse order
        $timeList = array_column($data, 'time');
        rsort($timeList);

        foreach ($data as $index => &$row) {

            // exceeded avg time
            $isExceededAvgTime = in_array($index, $avgCountList);
            if ($isExceededAvgTime) {

                $row['exceeded_avg_time'] = '超过了平均时间（'.$stat['sql_time_avg_micro'].' 毫秒）';
            }

            // sort order
            if ($isExceededAvgTime) {

                $order = array_search($row['time'], $timeList) + 1;
                $row['time_elapse_order'] = '耗时排名第 ' . $order . ' 位';
            }
        }
    }

    public static function getSqlExceededAvgList($timeAvgMicro, $data)
    {
        if (self::$avgCountList === null) {

            self::$avgCountList = [];
            foreach ($data as $index => $row) {
                if ($row['time'] > $timeAvgMicro) {
                    self::$avgCountList[] = $index;
                }
            }
        }
        return self::$avgCountList;
    }

    public static function getIsExceeded($time, $limit)
    {
        $exceeded = $time - $limit;

        $rate = self::getRate($time, $exceeded);
        $level = self::getLevelByRate($rate);
        $levelText = self::getLevelText($level);

        return [
            'exceeded'=>$exceeded,
            'rate'=>$rate,
            'levelText'=>$levelText,
        ];
    }

    public static function formatIsExceeded($isExceeded)
    {
        extract($isExceeded);

        if ($rate <= 0) {
            return '没有超过限制';
        } else {
            return "超限 {$exceeded} 秒，超限 {$rate}%，{$levelText}";
        }
    }

    public static function getRate($total, $part)
    {
        return sprintf('%.2f', $part / $total) * 100;
    }

    public static function getLevelByRate($rate)
    {
        if ($rate <= 0) {
            return self::OPTIMIZE_LEVEL_NEEDLESS;
        } elseif ($rate <= 25) {
            return self::OPTIMIZE_LEVEL_SUGGEST;
        } elseif ($rate <= 75) {
            return self::OPTIMIZE_LEVEL_SHOULD;
        } else {
            return self::OPTIMIZE_LEVEL_URGENCY;
        }
    }

    public static function getLevelText($level)
    {
        $data = [
            self::OPTIMIZE_LEVEL_URGENCY => '紧急优化',
            self::OPTIMIZE_LEVEL_SHOULD => '应该优化',
            self::OPTIMIZE_LEVEL_SUGGEST => '建议优化',
            self::OPTIMIZE_LEVEL_NEEDLESS => '不需要优化',
        ];

        return $data[$level] ?? 'unknown';
    }

    public static function formatStat($stat)
    {
        if ($stat['sql_count_total']) {

            $info = <<<EOF


性能统计
    
    总时间：限制 {time_total_limit} 秒，实际 {time_total} 秒，{time_total_exceeded}
    sql总时间：限制 {sql_time_total_limit} 秒，实际 {sql_time_total} 秒，占总时间 {sql_time_percent_of_total}%，{sql_time_total_exceeded}
    其它总时间：限制 {else_time_total_limit} 秒，实际 {else_time_total} 秒，占总时间 {else_time_percent_of_total}%，{else_time_total_exceeded}
    
    sql总数量：{sql_count_total} 个{sql_repeat_list}
    sql总时间：{sql_time_total} 秒 / {sql_time_total_micro} 毫秒
    sql平均时间：{sql_time_avg} 秒 / {sql_time_avg_micro} 毫秒
    超过平均时间的sql：{sql_exceeded_avg_count}
    
    sql超限时间是平均时间的 {sql_time_avg_with_time_total_exceeded} 倍


EOF;

        } else {

        $info = <<<EOF


性能统计
    
    总时间：限制 {time_total_limit} 秒，实际 {time_total} 秒，{time_total_exceeded}
    sql总时间：没有sql查询
    其它总时间：等于总时间


EOF;

        }

        self::replaceKey($stat);
        return strtr($info, $stat);
    }

    public static function replaceKey(&$data, $prefix='{', $postfix='}')
    {
        foreach ($data as $key => $value) {
            $newKey = $prefix . $key . $postfix;
            $data[$newKey] = $value;

            unset($data[$key]);
        }
    }

    public static function breakWithLength($string, $len)
    {
//        $stringLen = strlen($string);
//
//        if ($len >= $stringLen) {
//            return $string;
//        }
//
//        $times = $stringLen / $len;
//
//        while(true) {
//
//
//
//        }

    }

    public static function formatDataToDisplay($data)
    {
        $rowInfo = <<<EOD

[{index}]
    {replace}
    
    {explain_list}
    
    {time_elapse_order}{exceeded_avg_time}耗时：{time_second} 秒 / {time} 毫秒{slow}{repeat}
    
EOD;

        $res = "\nsql查询列表\n";

        $allSql = '';

        foreach ($data as $index => $row) {

            $res .= strtr($rowInfo, [
                '{index}'=>$index,
                '{replace}'=>$row['replace'],
                '{explain_list}'=>implode("\n    ", $row['explain']),
                '{time}'=>$row['time'],
                '{time_second}'=>$row['time_second'],
                '{exceeded_avg_time}'=>isset($row['exceeded_avg_time']) ? $row['exceeded_avg_time'] . "\n    " : '',
                '{time_elapse_order}'=>isset($row['time_elapse_order']) ? $row['time_elapse_order'] . "\n    " : '',
                '{slow}'=>isset($row['slow']) ? "\n    " . $row['slow'] : '',
                '{repeat}'=>isset($row['repeat']) ? "\n    " . $row['repeat'] : '',
            ]);

            $allSql .= $row['replace'] . ";\n";
        }

        $res .= "\n";
        $res .= $allSql;

        $res .= "\n";

        return $res;
    }
}