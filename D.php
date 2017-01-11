<?php
/**
 * D class file.
 * 
 * @author Daniel Luo <295313207@qq.com>
 * @copyright Copyright &copy; 2010-2015
 * @version 2.0
 */

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

    public static function pdsException()
    {
        self::$_pds_exception = true;
    }

	/**
	 * 关闭D组件.
	 */
	public static function close()
	{
		self::$_closed = true;
	}
	
	/**
	 * 设置是否打印为数组.
	 */
	public static function asa()
	{
		self::$_asa = true;
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
		self::logInternal(func_get_args());
	}
	
	/**
	 * 同 {@link log}，但会清空文件原有内容.
	 */
	public static function logc()
	{
		self::$_clear = true;
		self::logInternal(func_get_args());
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
		self::logInternal(func_get_args(), true);
	}
	
	/**
	 * 同 {@link logc}，但会终止程序.
	 */
	public static function logce()
	{
		self::$_clear = true;
		self::logInternal(func_get_args(), true);
	}
	
	/**
	 * 内部调用方法，用于打印前初始化被打印参数列表.
	 * @params array $args 被打印的参数列表数组.
	 * 要求必须提供被打印参数列表.
	 */
	private static function init_args($args)
	{
		$count = count($args);
		if ($count == 0)
		{
			exit('You must input data when using D methods.');
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
			self::init_args($args);
			
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
				if (count($lines) > 11)
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
			self::init_args($args);
				
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
				self::init_args($args);
				
				self::$_arg_pos = 0;
				foreach ($args as $arg)
				{
					$content = self::pdo($arg);
				
					$content = self::prefixMessage($content);
					$content = date('H:i:s Y/m/d', time()) . ' ' . $content;
					$content = self::iconv($content);
					
					// save to file
					$file = self::getLogPath() . '/DumperLogFile.txt';
					if (self::$_clear)
					{
						file_put_contents($file, $content);
						self::$_clear = false;
					}
					else
					{
						file_put_contents($file, $content, FILE_APPEND);
					}
					
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
			return CVarDumper::dumpAsString($arg);
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
        $d = debug_backtrace();

        // 调用位置
        $v = array();
        foreach($d as $row)
        {
            if(isset($row['file']) && (strpos($row['file'], 'D.php') === false))
            {
                $v = $row;
                break;
            }
        }

        if ($v !== array())
        {
            $position = self::fetchPosition($v);
        }
        else
        {
            $position = '';

        }

        //var_dump($d);

        if (self::$_message != '')
        {
            // 指定名称
            $message = self::$_message;
        }
        else
        {
            if ($v !== array())
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
			exit('<pre> !!! breakpoint !!! </pre>');
		}
	}
	
	public static function fp()
	{
		if (!self::$_closed)
		{
			echo '<pre> ~~~ footprint ~~~ </pre>';
		}
	}
	
	public static function rp($file)
	{
		self::pd(array(
		    'file'=>$file,
		    'realpath'=>realpath($file),
            'file_exists'=>file_exists($file)
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
	
	public static function count($items)
	{
		self::pd(count($items));
	}
	public static function counte($items)
	{
		self::pde(count($items));
	}
	
	public static function rand()
	{
		self::pd(rand());
	}
	
	public static function rande()
	{
		self::pde(rand());
	}
	
	public static function args($log=false)
	{
		$d = debug_backtrace();
		
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
			
			// 参数名列表
			//$caller['arg names'] = array();
			foreach ($object->getParameters() as $param)
			{
				//$caller['params'][] = $param;
				//$caller['arg names'][] = $param->getName();
			}
		}
		else
		{
			$caller = 'Not inside a function.';
		}
		$log ? self::log($caller) : self::pd($caller);
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

	public static function usage($log=false)
	{
		$u = memory_get_usage();
		$pu = memory_get_peak_usage();
		$k = 1024;
		$m = 1024 * 1024;
		$usage = array(
			'memory_get_usage'=>array('B'=>$u, 'K'=>$u/$k, 'M'=>$u/$m),
			'memory_get_peak_usage'=>array('B'=>$pu, 'K'=>$pu/$k, 'M'=>$pu/$m),
			'memory_limit'=>ini_get('memory_limit'),
		);
		$log ? D::log($usage) : D::pd($usage);
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
	
	// @todo 2012/11/5 等待重新开发
	public static function trace($terminate=true, $html=true)
	{
		$d=debug_backtrace();
		$all = true;
		if($all)
		{
			foreach($d as $k=>$item)
				if(strpos($item['file'],'D.php')!==false)
					unset($d[$k]);
			$new=array();
			foreach($d as $k=>$item)
				$new[]=$item;
			$d=$new;
		}
		else
		{
			$sn=0;
			foreach($d as $k=>$item)
			{
				if(strpos($item['file'],'D.php')===false)
				{
					$sn=$k;
					break;
				}
			}
			$d=array($d[$sn]);
		}
		
		$trace='';
		foreach($d as $k=>$v)
		{
			$prefix = '00';
			if($k<100)
				$prefix = '0';
			if($k<10)
				$prefix = '00';
			$k = $prefix . $k;
			if(isset($v['class']))
				$trace.="#{$k} {$v['file']}({$v['line']}): {$v['class']}{$v['type']}{$v['function']}()\n";
			else
				$trace.="#{$k} {$v['file']}({$v['line']}) {$v['function']}()\n";
		}
		$trace.="#{main} REQUEST_URI=".$_SERVER['REQUEST_URI']."\n\n";
		echo  $html ? nl2br($trace) : $trace;
        $terminate && exit;
	}
	
	
	
	public static function handleError($error, $message, $file, $line)
	{
		restore_error_handler();
		//restore_exception_handler();
		
		if ($error & error_reporting())
		{
            $method = self::$_pds_exception ? 'pds' : 'pd';
			self::$_message = 'Error';
			self::pds(array(
				'error' => $error,
				'message' => $message,
				'file' => $file,
				'line' => $line
			));
			self::$_message = '';
		}
		
		if (DUMPER_HANDLER_DISCARD_OUTPUT)
		{
			self::discardOutput();
		}
	}
	
	public static function handleException($e)
	{
		//restore_error_handler();
		restore_exception_handler();

        $method = self::$_pds_exception ? 'pds' : 'pd';
		self::$_message = 'Exception';
		self::pds(array(
			'exception' => $e->getCode(),
			'message' => $e->getMessage(),
			'file' => $e->getFile(),
			'line' => $e->getLine(),
			'trace' => $e->getTrace(),
		));
		self::$_message = '';
		
		if (DUMPER_HANDLER_DISCARD_OUTPUT)
		{
			self::discardOutput();
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
		$highlight ? self::pd(self::refExplode($object)) : self::pds(self::refExplode($object));
	}
	
	/**
	 * 同 {@link ref}，但会终止程序.
	 */
	public static function refe($class, $highhight=true)
	{
		exit(self::ref($class, $highhight));
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
}