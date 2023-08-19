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

// 引入sql格式化组件
if (!class_exists('SqlFormatter'))
{
    require_once dirname(__FILE__) . '/SqlFormatter.php';
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
            try {
                $explainList = \DB::select('explain '.$row['replace']);
            } catch (\Exception $e) {
                $explainList = [];
            }
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

class DTempFile
{
    /**
     * 删除临时文件
     * @param string $filenameId
     */
    public static function deleteTempFile($filenameId='')
    {
        $file = self::getTempFile($filenameId);

        unlink($file);
    }

    /**
     * 保存数据到临时文件.
     * 用json格式保存，文件已存在就不处理，如果想重试，必须删除已有文件.
     * 调用远程接口的时候，接口耗时、调用次数限制、网络不稳定等，可以将调用结果保存为临时文件，避免调试的时候总是调用接口，节省时间.
     * @param $data
     * @param string $filenameId
     */
    public static function saveToTempFileAppend($data, $filenameId='')
    {
        $file = self::getTempFile($filenameId);

        file_put_contents($file, json_encode($data)."\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * 保存数据到临时文件.
     * 用json格式保存，文件已存在就不处理，如果想重试，必须删除已有文件.
     * 调用远程接口的时候，接口耗时、调用次数限制、网络不稳定等，可以将调用结果保存为临时文件，避免调试的时候总是调用接口，节省时间.
     * @param $data
     * @param string $filenameId
     */
    public static function saveTempFile($data, $filenameId='')
    {
        $file = self::getTempFile($filenameId);

        if (!file_exists($file))
        {
            file_put_contents($file, json_encode($data), LOCK_EX);
        }
    }

    /**
     * 从临时文件获取数据.
     * @param string $filenameId
     * @param bool $jsonDecode
     * @return bool|mixed
     */
    public static function getFromTempFile($filenameId='', $jsonDecode=true)
    {
        $file = self::getTempFile($filenameId);

        if (!file_exists($file))
        {
            return false;
        }

        return $jsonDecode ? json_decode(file_get_contents($file), true) : file_get_contents($file);
    }

    /**
     * 检查临时文件是否可用.
     * @param string $filenameId
     * @return bool
     */
    public static function isTempFileAvailable($filenameId='')
    {
        $file = self::getTempFile($filenameId);

        return file_exists($file);
    }

    /**
     * 获取临时文件名.
     * 默认文件名加上指定的文件ID，方便记录到多个文件.
     * @param string $filenameId
     * @return string
     */
    public static function getTempFile($filenameId='')
    {
        $filenameId = trim($filenameId);
        return self::getLogPath() . '/' . "DumperLogFileTempFile-{$filenameId}.txt";
    }

    /**
     * 获取随机临时文件名.
     * 调试csv文件下载的时候，想要临时生成csv文件，可以给一个临时文件名结合CsvHelper进行调试.
     * @return string
     */
    public static function getTempFileRand()
    {
        return self::getTempFile('Rand-'.rand());
    }
}

class D
{
    /**
     * 用于 {@link iconv} 的转换编码.
     */
    const UTF8 = 1;
    const GBK = 2;
    const LINE = '------------------------------------------------------------------';

    const SORT_ASC = 1;
    const SORT_DESC = 2;

    private static $_logPath;
    private static $_logFileName;
    private static $_logFileNameDefault = 'DumperLogFile.ig.txt';
    private static $_iconv = null;
    private static $_message = '';
    private static $_arg_pos = 0;
    private static $_args = array();
    private static $_closed = false;
    private static $_clear = false;
    private static $_asa = false;
    private static $_js_included = false;
    private static $_positions = array();
    private static $_pds_exception = false;
    private static $_profile = [];
    private static $_profile_cost = [];
    private static $_first_log = false;
    private static $_no_clean = true;
    private static $_shutdownLog = [];
    private static $_line_chars = ['-', '=', '*', '!', '#', '@', '$', '%', '^', '&', '<', '>'];
    private static $_line_char_index = 0;
    private static $_yiiLogSql = false;
    private static $_yiiLogUseSqlLogFile = false;
    private static $_ignore_message=false;
    private static $_unfold_count = 25;

    public static function unfold_count($count=0)
    {
        self::$_unfold_count = $count;
    }

    public static function ignoreMessage($ignore=true)
    {
        self::$_ignore_message = (bool)$ignore;
    }

    public static function setMessage($message)
    {
        self::$_message = $message;
    }

    public static function getYiiLogSql()
    {
        return self::$_yiiLogSql;
    }

    public static function enableYiiLogSql($enable = true)
    {
        self::$_yiiLogSql = (bool)$enable;
    }

    public static function yiiLogUseSqlLogFile($use = false)
    {
        self::$_yiiLogUseSqlLogFile = $use;
    }

    public static function yiiLogSql($cmd, $par)
    {
        if (self::getYiiLogSql()) {
            // 使用sql文件记录
            if (self::$_yiiLogUseSqlLogFile) {
                self::setLogFileName('DumperLogFile_sql.ig.txt');
            }

            // 获取sql和trace
            $sql = "\n\n" . $cmd->getText() . $par . "\n";
            $sql .= "\n" . self::getTrace() . "\n";

            // 记录sql
            self::setMessage('sql');
            self::log($sql);
            self::setMessage('');

            // 恢复原来的记录文件
            if (self::$_yiiLogUseSqlLogFile) {
                self::logToDefault();
            }
        }
    }

    public static function yiiLogSql2($cmd)
    {
        if (self::getYiiLogSql()) {
            // 使用sql文件记录
            if (self::$_yiiLogUseSqlLogFile) {
                self::setLogFileName('DumperLogFile_sql.ig.txt');
            }

            // 获取sql和trace
            $sql = "\n\n" . $cmd->getRawSql() . "\n";
            $sql .= "\n" . self::getTrace() . "\n";

            // 记录sql
            self::setMessage('sql');
            self::log($sql);
            self::setMessage('');

            // 恢复原来的记录文件
            if (self::$_yiiLogUseSqlLogFile) {
                self::logToDefault();
            }
        }
    }

    /**
     * 设置日志文件名.
     * @param $filename
     * @throws Exception
     */
    public static function setLogFileName($filename)
    {
        if (strpos('/', $filename) !== false || strpos('\\', $filename) !== false) {
            throw new \Exception('D::logFile不能包含斜杠和反斜杠');
        }

        self::$_logFileName = $filename;
    }

    public static function logTo($filename)
    {
        self::setLogFileName($filename);
    }

    public static function logToDefault()
    {
        self::setLogFileName(self::$_logFileNameDefault);
    }

    public static function logToOther($num)
    {
        $parts = explode('.', self::$_logFileNameDefault);
        $res = [
            $parts[0] . "-{$num}",
            $parts[1],
            $parts[2],
        ];
        $fileName = implode('.', $res);
        self::setLogFileName($fileName);
    }

    /**
     * 如果打印的内容只需要原格式输出，可以跳过pdo处理，例如记录已经格式化好的sql的时候.
     * @var bool
     */
    private static $_skip_pdo = false;

    /**
     * 自动检测换行符.
     * @param bool $enable
     * @link https://www.php.net/manual/zh/function.file.php
     * Note: 在读取在 Macintosh 电脑中或由其创建的文件时， 如果 PHP 不能正确的识别行结束符，启用运行时配置可选项 auto_detect_line_endings 也许可以解决此问题。
     */
    private static function autoDetectLineEndings($enable=true)
    {
        ini_set('auto_detect_line_endings', (bool)$enable);
    }

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
     * 恢复"第一次记录时清空之前的记录"的逻辑
     */
    public static function clean()
    {
        self::$_no_clean = false;
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
     * 抛出一个测试异常.
     * @throws Exception
     */
    public static function throw($code=500)
    {
        if ($code instanceof Exception)
        {
            throw $code;
        }

        throw new Exception("A test exception throwed by D component: code={$code}.", $code);
    }

    /**
     * 触发一个notice测试错误.
     */
    public static function notice()
    {
        trigger_error('A notice triggered by D component.', E_USER_NOTICE);
    }

    /**
     * 触发一个warning测试错误.
     */
    public static function warning()
    {
        trigger_error('A warning triggered by D component.', E_USER_WARNING);
    }

    /**
     * 触发一个faltal测试错误.
     */
    public static function error()
    {
        trigger_error('An error triggered by D component.', E_USER_ERROR);
    }

    /**
     * Sort the array.
     * @param $array
     * @param $sort
     * @param $sortAssoc
     * @param $sortByKey
     * @param $sortByRecurse
     * @param $natsort
     */
    public static function sort(&$array, $sort=self::SORT_ASC, $sortAssoc=false, $sortByKey=false, $sortByRecurse=false, $natsort=false)
    {
        // Sort an array and maintain index association
        if ($sortAssoc)
        {
            if ($sort == self::SORT_ASC)
            {
                asort($array);
            }
            elseif ($sort == self::SORT_DESC)
            {
                arsort($array);
            }
        }
        else
        {
            // Sort by key or value
            if ($sortByKey)
            {
                if ($sort == self::SORT_ASC)
                {
                    ksort($array);
                }
                elseif ($sort == self::SORT_DESC)
                {
                    krsort($array);
                }
            }
            else
            {
                if ($sort == self::SORT_ASC)
                {
                    sort($array);
                }
                elseif ($sort == self::SORT_DESC)
                {
                    rsort($array);
                }
            }
        }

        // Sort by recurse
        if ($sortByRecurse)
        {
            foreach ($array as &$item)
            {
                if (!is_array($item) || count($item) == 0)
                {
                    continue;
                }

                self::sort($item, $sort, $sortAssoc, $sortByKey, $sortByRecurse, $natsort);
            }
        }
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
				if (self::$_unfold_count && count($lines) > self::$_unfold_count)
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
                    // 有时候为了方便直接使用记录的文本，不想加前缀信息，可以跳过
				    if (self::$_ignore_message) {
                        self::$_skip_pdo = true;
                        $content = self::pdo($arg);
                    } else {
                        $content = self::pdo($arg);
                        $content = self::prefixMessage($content);
                        $content = self::msecDate(null, true) . ' ' . $content;
                    }

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
        if (empty(self::$_logFileName))
        {
            $file = self::getLogPath() . '/' . self::$_logFileNameDefault;
        }
        else
        {
            $file = self::getLogPath() . '/' . self::$_logFileName;
        }

        if (self::$_clear)
        {
            file_put_contents($file, $content, LOCK_EX);
            self::$_clear = false;
        }
        else
        {
            file_put_contents($file, $content, FILE_APPEND | LOCK_EX);
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
	    // 如果跳过格式化处理，就直接返回原变量内容
	    if (self::$_skip_pdo && is_string($arg))
        {
            return $arg;
        }

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
        list($message, $position) = self::generatePrefixMessage();

		return $highlight ? '<span class="toggle">' . $position . '#' . $message . '</span> '. $content : $position . '#' . $message . ' ' . $content;
	}

    /**
     * 生成message.
     * @return mixed|string
     */
	private static function generatePrefixMessage()
    {
        // 调用堆栈
        $d = self::getDebugBacktrace();

        // 调用位置行
        $v = self::getDebugBacktraceRow($d);

        // 调用位置
        $position = self::getPositionFromDebugBacktraceRow($v);

        // 指定名称
        if (self::$_message != '')
        {
            return [self::$_message, $position];
        }

        if (empty($v))
        {
            return ["Can't get the arg message.", $position];
        }

        // 预定义函数名
        $message = self::namesMap($v['function']);

        if ($message == '')
        {
            $message = self::fetchArgName($v['file'], $v['line']);

            if ($v['function'] == 'count')
            {
                $message = 'count(' . $message . ')';
            }
        }

        return [$message, $position];
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

			// 读取文件内容的时候实时开启和关闭文件换行符检测，避免影响其它程序
			self::autoDetectLineEndings();
			$lines = file($file);
			self::autoDetectLineEndings(false);

			$line_num = $line_num - 1;
			if ($line_num < 0)
            {
                $line_num = 0;
            }

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
                echo "<pre> {$position}# !!! breakpoint !!! </pre>\n";
			    exit();
            }
		}
	}

	public static function fp()
	{
		if (!self::$_closed)
		{
            $position = self::getPositionFromDebugBacktrace();
            $rand = rand();
            echo "<pre> {$position}# ~~~ footprint - {$rand} ~~~ </pre>\n";
		}
	}

	public static function done()
    {
        if (!self::$_closed)
		{
            $position = self::getPositionFromDebugBacktrace();
            $rand = rand();
			echo "<pre> {$position}# ~~~ done - {$rand} ~~~ </pre>\n";
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
	public static function profile($token=null)
    {
        if ($token === null) {
            $token = 'profile';
        }

        if(!isset(self::$_profile[$token])){
            self::$_profile[$token] = microtime(true);
        }else{
            throw new Exception('Profile token ' . $token . ' has been existent.');
        }
    }

    /**
     * End a profile.
     * @param string $token
     * @param bool $return
     * @throws Exception
     */
    public static function profilee($token=null, $return=false)
    {
        if ($token === null) {
            $token = 'profile';
        }

        if(!isset(self::$_profile[$token])){
            throw new Exception('Profile token ' . $token . ' is not found.');
        }

        $now = microtime(true);
        $last = self::$_profile[$token];
        $cost = $now - $last;

        // log cost for compare profile
        self::$_profile_cost[$token] = $cost;
        if ($return) {
            return $cost;
        } else {
            // set the message manually
            self::$_message = 'profile::' . $token;
            $cost = sprintf('%.9f', $cost);
            self::log($cost);
            // should clean the message manually
            self::$_message = '';
        }
    }

    /**
     * Compare two profile.
     * @param $token_new
     * @param $token_old
     * @throws Exception
     */
    public static function profilec($token_new, $token_old)
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

	public static function count($items, $highlight=true, $log=false)
	{
	    $count = count($items);
	    $log ? self::log($count) : ($highlight ? self::pd($count) : self::pds($count));
	}
	public static function counte($items, $highlight=true, $log=false)
	{
		self::count($items, $highlight, $log);
		exit();
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

	public static function post($slight=false){$slight ? self::pds($_POST) : self::pd($_POST);}
	public static function poste($slight=false){$slight ? self::pdse($_POST) : self::pde($_POST);}

	public static function get($slight=false){$slight ? self::pds($_GET) : self::pd($_GET);}
	public static function gete($slight=false){$slight ? self::pdse($_GET) : self::pde($_GET);}

	public static function request($slight=false){$slight ? self::pds($_REQUEST) : self::pd($_REQUEST);}
	public static function requeste($slight=false){$slight ? self::pdse($_REQUEST) : self::pde($_REQUEST);}

	public static function session($slight=false){$slight ? self::pds($_SESSION) : self::pd($_SESSION);}
	public static function sessione($slight=false){$slight ? self::pdse($_SESSION) : self::pde($_SESSION);}

	public static function cookie($slight=false){$slight ? self::pds($_COOKIE) : self::pd($_COOKIE);}
	public static function cookiee($slight=false){$slight ? self::pdse($_COOKIE) : self::pde($_COOKIE);}

	public static function files($slight=false){$slight ? self::pds($_FILES) : self::pd($_FILES);}
	public static function filese($slight=false){$slight ? self::pdse($_FILES) : self::pde($_FILES);}

	public static function server($slight=false){$slight ? self::pds($_SERVER) : self::pd($_SERVER);}
	public static function servere($slight=false){$slight ? self::pdse($_SERVER) : self::pde($_SERVER);}

	public static function globals($slight=false){$slight ? self::pds($GLOBALS) : self::pd($GLOBALS);}
	public static function globalse($slight=false){$slight ? self::pdse($GLOBALS) : self::pde($GLOBALS);}

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

	public static function usage($log=false, $string=false, $return=false)
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

        if ($return) {
            return $usage;
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
	 * @param string $function 函数名.
	 * @param boolean $highlight 是否高亮打印，true表示是，false表示否，默认是true.
     * @param boolean $log 是否记录到日志文件.
	 */
	public static function refF($function, $highlight=true, $log=false)
	{
		$object = new ReflectionFunction($function);

		$name = $function . '()';
		$data = array(
			'Name'=>$name,
			'File'=>$object->getFileName(),
			'Lines'=>'{' . $object->getStartLine() . ', ' . $object->getEndLine() . '}',
		);
		self::$_message = 'Function ' . $name;
		$log ? self::log($data) : ($highlight ? self::pd($data) : self::pds($data));
		self::$_message = '';
	}

	/**
	 * 同 {@link refF}，但会终止程序.
	 */
	public static function refFe($function, $highlight=true, $log=false)
	{
		exit(self::refF($function, $highlight, $log));
	}

	/**
	 * Reflect a class or object.
	 * @param mixed $class 类名或者对象.
	 * @param boolean $highlight 是否高亮打印，true表示是，false表示否，默认是true.
     * @param boolean $log 是否记录到日志文件.
	 */
	public static function ref($class, $highlight=true, $log=false)
	{
		$data = self::refExplodeInternal($class);
		$log ? self::log($data) : ($highlight ? self::pd($data) : self::pds($data));
	}

	/**
	 * 同 {@link ref}，但会终止程序.
	 */
	public static function refe($class, $highlight=true, $log=false)
	{
		exit(self::ref($class, $highlight, $log));
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
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }

    public static function jsone($data, $header=false)
    {
        if ($header){
            header('Content-Type:application/json; Charset=utf-8;');
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }

    public static function jsontest()
    {
        self::json(array('D::jsontest()'=>range(1, 10)));
    }

    public static function jsonteste()
    {
        self::jsontest();
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

    public static function met($sec=0)
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

    public static function curlArray($url, $params = [], $post=false, $curlOptions=[], $https=false)
    {
        return self::curl($url, $params, $post, true, $curlOptions, $https);
    }

    /**
     * @param string $url 请求网址
     * @param array $params 请求参数
     * @param bool $post 请求方式
     * @param bool $returnArray 是否以数组方式返回结果
     * @param array $curlOptions curl选项
     * @param bool $https https协议
     * @param bool $highlight
     * @param bool $log
     * @return bool|mixed
     */
    public static function curl($url, $params = [], $post=false, $returnArray=false, $curlOptions=[], $https=false, $highlight=true, $log=false)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
        curl_setopt($ch, CURLOPT_USERAGENT, self::get_user_agent());
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $options = [
//            CURLOPT_USERAGENT      => 'Yii Framework ' . Yii::getVersion() . ' ' . __CLASS__,
//            CURLOPT_RETURNTRANSFER => false,
//            CURLOPT_HEADER         => false,
            // http://www.php.net/manual/en/function.curl-setopt.php#82418
            CURLOPT_HTTPHEADER     => [
                'Expect:',
                'Content-Type: application/json',
            ],
        ];

        curl_setopt_array($ch, $options);

        if ($https) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // 对认证证书来源的检查
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); // 从证书中检查SSL加密算法是否存在
        }

        if (!empty($curlOptions) && is_array($curlOptions)) {
            foreach ($curlOptions as $option => $value) {
                curl_setopt($ch, $option, $value);
            }
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
        curl_setopt($ch, CURLOPT_URL, $url);

        if ($post) {
            curl_setopt($ch, CURLOPT_POST, true);

            curl_setopt($ch, CURLOPT_URL, $url);
        } else {
            if (!empty($params) && is_array($params)) {
               // curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
            } else {
                curl_setopt($ch, CURLOPT_URL, $url);
            }
        }

        $response = curl_exec($ch);

        if ($response === false) {

            self::$_message = 'D::curl() curl error';
            $errors = array(
                'curl_errno'=>curl_errno($ch),
                'curl_error'=>curl_error($ch),
                'http_code'=>curl_getinfo($ch, CURLINFO_HTTP_CODE),
                'http_info'=>curl_getinfo($ch),
            );
            $log ? self::log($errors) : ($highlight ? self::pd($errors) : self::pds($errors));
            self::$_message = '';

            curl_close($ch);

            return false;
        }

        curl_close($ch);

        if ($returnArray) {
            $res = @json_decode($response, true);
            if (!$res) {
                self::$_message = 'D::curl() json error';
                $errors = array(
                    'json_last_error'=>json_last_error(),
                    'json_last_error_msg'=>json_last_error_msg(),
                    'response'=>substr($response, 1, 2048),
                );
                $log ? self::log($errors) : ($highlight ? self::pd($errors) : self::pds($errors));
                self::$_message = '';

            } else {
                return $res;
            }
        } else {
            return $response;
        }
    }

    /**
     * 获取随机user agent.
     * @return mixed
     */
    public static function get_user_agent()
    {
        $json = '{"browsers": {"chrome": ["Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2228.0 Safari/537.36", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.1 Safari/537.36", "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.0 Safari/537.36", "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2227.0 Safari/537.36", "Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2226.0 Safari/537.36", "Mozilla/5.0 (Windows NT 6.4; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2225.0 Safari/537.36", "Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2225.0 Safari/537.36", "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/41.0.2224.3 Safari/537.36", "Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/40.0.2214.93 Safari/537.36", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.124 Safari/537.36", "Mozilla/5.0 (Windows NT 6.3; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36", "Mozilla/5.0 (Windows NT 4.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2049.0 Safari/537.36", "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.67 Safari/537.36", "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.67 Safari/537.36", "Mozilla/5.0 (X11; OpenBSD i386) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1985.125 Safari/537.36", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/36.0.1944.0 Safari/537.36", "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.3319.102 Safari/537.36", "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.2309.372 Safari/537.36", "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.2117.157 Safari/537.36", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.47 Safari/537.36", "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1866.237 Safari/537.36", "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.137 Safari/4E423F", "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/34.0.1847.116 Safari/537.36 Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.10", "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/33.0.1750.517 Safari/537.36", "Mozilla/5.0 (Windows NT 6.2; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1667.0 Safari/537.36", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1664.3 Safari/537.36", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/32.0.1664.3 Safari/537.36", "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1650.16 Safari/537.36", "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/31.0.1623.0 Safari/537.36", "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/30.0.1599.17 Safari/537.36", "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.62 Safari/537.36", "Mozilla/5.0 (X11; CrOS i686 4319.74.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.57 Safari/537.36", "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/29.0.1547.2 Safari/537.36", "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1468.0 Safari/537.36", "Mozilla/5.0 (Windows NT 6.2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1467.0 Safari/537.36", "Mozilla/5.0 (Windows NT 6.2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/28.0.1464.0 Safari/537.36", "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1500.55 Safari/537.36", "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36", "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36", "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36", "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.93 Safari/537.36", "Mozilla/5.0 (Windows NT 6.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.90 Safari/537.36", "Mozilla/5.0 (X11; NetBSD) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.116 Safari/537.36", "Mozilla/5.0 (X11; CrOS i686 3912.101.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/27.0.1453.116 Safari/537.36", "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1312.60 Safari/537.17", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_8_2) AppleWebKit/537.17 (KHTML, like Gecko) Chrome/24.0.1309.0 Safari/537.17", "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.15 (KHTML, like Gecko) Chrome/24.0.1295.0 Safari/537.15", "Mozilla/5.0 (Windows NT 6.2; WOW64) AppleWebKit/537.14 (KHTML, like Gecko) Chrome/24.0.1292.0 Safari/537.14"], "opera": ["Opera/9.80 (X11; Linux i686; Ubuntu/14.10) Presto/2.12.388 Version/12.16", "Opera/9.80 (Windows NT 6.0) Presto/2.12.388 Version/12.14", "Mozilla/5.0 (Windows NT 6.0; rv:2.0) Gecko/20100101 Firefox/4.0 Opera 12.14", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.0) Opera 12.14", "Opera/12.80 (Windows NT 5.1; U; en) Presto/2.10.289 Version/12.02", "Opera/9.80 (Windows NT 6.1; U; es-ES) Presto/2.9.181 Version/12.00", "Opera/9.80 (Windows NT 5.1; U; zh-sg) Presto/2.9.181 Version/12.00", "Opera/12.0(Windows NT 5.2;U;en)Presto/22.9.168 Version/12.00", "Opera/12.0(Windows NT 5.1;U;en)Presto/22.9.168 Version/12.00", "Mozilla/5.0 (Windows NT 5.1) Gecko/20100101 Firefox/14.0 Opera/12.0", "Opera/9.80 (Windows NT 6.1; WOW64; U; pt) Presto/2.10.229 Version/11.62", "Opera/9.80 (Windows NT 6.0; U; pl) Presto/2.10.229 Version/11.62", "Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; fr) Presto/2.9.168 Version/11.52", "Opera/9.80 (Macintosh; Intel Mac OS X 10.6.8; U; de) Presto/2.9.168 Version/11.52", "Opera/9.80 (Windows NT 5.1; U; en) Presto/2.9.168 Version/11.51", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; de) Opera 11.51", "Opera/9.80 (X11; Linux x86_64; U; fr) Presto/2.9.168 Version/11.50", "Opera/9.80 (X11; Linux i686; U; hu) Presto/2.9.168 Version/11.50", "Opera/9.80 (X11; Linux i686; U; ru) Presto/2.8.131 Version/11.11", "Opera/9.80 (X11; Linux i686; U; es-ES) Presto/2.8.131 Version/11.11", "Mozilla/5.0 (Windows NT 5.1; U; en; rv:1.8.1) Gecko/20061208 Firefox/5.0 Opera 11.11", "Opera/9.80 (X11; Linux x86_64; U; bg) Presto/2.8.131 Version/11.10", "Opera/9.80 (Windows NT 6.0; U; en) Presto/2.8.99 Version/11.10", "Opera/9.80 (Windows NT 5.1; U; zh-tw) Presto/2.8.131 Version/11.10", "Opera/9.80 (Windows NT 6.1; Opera Tablet/15165; U; en) Presto/2.8.149 Version/11.1", "Opera/9.80 (X11; Linux x86_64; U; Ubuntu/10.10 (maverick); pl) Presto/2.7.62 Version/11.01", "Opera/9.80 (X11; Linux i686; U; ja) Presto/2.7.62 Version/11.01", "Opera/9.80 (X11; Linux i686; U; fr) Presto/2.7.62 Version/11.01", "Opera/9.80 (Windows NT 6.1; U; zh-tw) Presto/2.7.62 Version/11.01", "Opera/9.80 (Windows NT 6.1; U; zh-cn) Presto/2.7.62 Version/11.01", "Opera/9.80 (Windows NT 6.1; U; sv) Presto/2.7.62 Version/11.01", "Opera/9.80 (Windows NT 6.1; U; en-US) Presto/2.7.62 Version/11.01", "Opera/9.80 (Windows NT 6.1; U; cs) Presto/2.7.62 Version/11.01", "Opera/9.80 (Windows NT 6.0; U; pl) Presto/2.7.62 Version/11.01", "Opera/9.80 (Windows NT 5.2; U; ru) Presto/2.7.62 Version/11.01", "Opera/9.80 (Windows NT 5.1; U;) Presto/2.7.62 Version/11.01", "Opera/9.80 (Windows NT 5.1; U; cs) Presto/2.7.62 Version/11.01", "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2.13) Gecko/20101213 Opera/9.80 (Windows NT 6.1; U; zh-tw) Presto/2.7.62 Version/11.01", "Mozilla/5.0 (Windows NT 6.1; U; nl; rv:1.9.1.6) Gecko/20091201 Firefox/3.5.6 Opera 11.01", "Mozilla/5.0 (Windows NT 6.1; U; de; rv:1.9.1.6) Gecko/20091201 Firefox/3.5.6 Opera 11.01", "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; de) Opera 11.01", "Opera/9.80 (X11; Linux x86_64; U; pl) Presto/2.7.62 Version/11.00", "Opera/9.80 (X11; Linux i686; U; it) Presto/2.7.62 Version/11.00", "Opera/9.80 (Windows NT 6.1; U; zh-cn) Presto/2.6.37 Version/11.00", "Opera/9.80 (Windows NT 6.1; U; pl) Presto/2.7.62 Version/11.00", "Opera/9.80 (Windows NT 6.1; U; ko) Presto/2.7.62 Version/11.00", "Opera/9.80 (Windows NT 6.1; U; fi) Presto/2.7.62 Version/11.00", "Opera/9.80 (Windows NT 6.1; U; en-GB) Presto/2.7.62 Version/11.00", "Opera/9.80 (Windows NT 6.1 x64; U; en) Presto/2.7.62 Version/11.00", "Opera/9.80 (Windows NT 6.0; U; en) Presto/2.7.39 Version/11.00"], "firefox": ["Mozilla/5.0 (Windows NT 6.1; WOW64; rv:40.0) Gecko/20100101 Firefox/40.1", "Mozilla/5.0 (Windows NT 6.3; rv:36.0) Gecko/20100101 Firefox/36.0", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10; rv:33.0) Gecko/20100101 Firefox/33.0", "Mozilla/5.0 (X11; Linux i586; rv:31.0) Gecko/20100101 Firefox/31.0", "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:31.0) Gecko/20130401 Firefox/31.0", "Mozilla/5.0 (Windows NT 5.1; rv:31.0) Gecko/20100101 Firefox/31.0", "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:29.0) Gecko/20120101 Firefox/29.0", "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:25.0) Gecko/20100101 Firefox/29.0", "Mozilla/5.0 (X11; OpenBSD amd64; rv:28.0) Gecko/20100101 Firefox/28.0", "Mozilla/5.0 (X11; Linux x86_64; rv:28.0) Gecko/20100101  Firefox/28.0", "Mozilla/5.0 (Windows NT 6.1; rv:27.3) Gecko/20130101 Firefox/27.3", "Mozilla/5.0 (Windows NT 6.2; Win64; x64; rv:27.0) Gecko/20121011 Firefox/27.0", "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:25.0) Gecko/20100101 Firefox/25.0", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:25.0) Gecko/20100101 Firefox/25.0", "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:24.0) Gecko/20100101 Firefox/24.0", "Mozilla/5.0 (Windows NT 6.0; WOW64; rv:24.0) Gecko/20100101 Firefox/24.0", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:24.0) Gecko/20100101 Firefox/24.0", "Mozilla/5.0 (Windows NT 6.2; rv:22.0) Gecko/20130405 Firefox/23.0", "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:23.0) Gecko/20130406 Firefox/23.0", "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:23.0) Gecko/20131011 Firefox/23.0", "Mozilla/5.0 (Windows NT 6.2; rv:22.0) Gecko/20130405 Firefox/22.0", "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:22.0) Gecko/20130328 Firefox/22.0", "Mozilla/5.0 (Windows NT 6.1; rv:22.0) Gecko/20130405 Firefox/22.0", "Mozilla/5.0 (Microsoft Windows NT 6.2.9200.0); rv:22.0) Gecko/20130405 Firefox/22.0", "Mozilla/5.0 (Windows NT 6.2; Win64; x64; rv:16.0.1) Gecko/20121011 Firefox/21.0.1", "Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:16.0.1) Gecko/20121011 Firefox/21.0.1", "Mozilla/5.0 (Windows NT 6.2; Win64; x64; rv:21.0.0) Gecko/20121011 Firefox/21.0.0", "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:21.0) Gecko/20130331 Firefox/21.0", "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:21.0) Gecko/20100101 Firefox/21.0", "Mozilla/5.0 (X11; Linux i686; rv:21.0) Gecko/20100101 Firefox/21.0", "Mozilla/5.0 (Windows NT 6.2; WOW64; rv:21.0) Gecko/20130514 Firefox/21.0", "Mozilla/5.0 (Windows NT 6.2; rv:21.0) Gecko/20130326 Firefox/21.0", "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:21.0) Gecko/20130401 Firefox/21.0", "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:21.0) Gecko/20130331 Firefox/21.0", "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:21.0) Gecko/20130330 Firefox/21.0", "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:21.0) Gecko/20100101 Firefox/21.0", "Mozilla/5.0 (Windows NT 6.1; rv:21.0) Gecko/20130401 Firefox/21.0", "Mozilla/5.0 (Windows NT 6.1; rv:21.0) Gecko/20130328 Firefox/21.0", "Mozilla/5.0 (Windows NT 6.1; rv:21.0) Gecko/20100101 Firefox/21.0", "Mozilla/5.0 (Windows NT 5.1; rv:21.0) Gecko/20130401 Firefox/21.0", "Mozilla/5.0 (Windows NT 5.1; rv:21.0) Gecko/20130331 Firefox/21.0", "Mozilla/5.0 (Windows NT 5.1; rv:21.0) Gecko/20100101 Firefox/21.0", "Mozilla/5.0 (Windows NT 5.0; rv:21.0) Gecko/20100101 Firefox/21.0", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.8; rv:21.0) Gecko/20100101 Firefox/21.0", "Mozilla/5.0 (Windows NT 6.2; Win64; x64;) Gecko/20100101 Firefox/20.0", "Mozilla/5.0 (Windows x86; rv:19.0) Gecko/20100101 Firefox/19.0", "Mozilla/5.0 (Windows NT 6.1; rv:6.0) Gecko/20100101 Firefox/19.0", "Mozilla/5.0 (Windows NT 6.1; rv:14.0) Gecko/20100101 Firefox/18.0.1", "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:18.0)  Gecko/20100101 Firefox/18.0", "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:17.0) Gecko/20100101 Firefox/17.0.6"], "internetexplorer": ["Mozilla/5.0 (Windows NT 6.1; WOW64; Trident/7.0; AS; rv:11.0) like Gecko", "Mozilla/5.0 (compatible, MSIE 11, Windows NT 6.3; Trident/7.0;  rv:11.0) like Gecko", "Mozilla/5.0 (compatible; MSIE 10.6; Windows NT 6.1; Trident/5.0; InfoPath.2; SLCC1; .NET CLR 3.0.4506.2152; .NET CLR 3.5.30729; .NET CLR 2.0.50727) 3gpp-gba UNTRUSTED/1.0", "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 7.0; InfoPath.3; .NET CLR 3.1.40767; Trident/6.0; en-IN)", "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; WOW64; Trident/6.0)", "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/6.0)", "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/5.0)", "Mozilla/5.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/4.0; InfoPath.2; SV1; .NET CLR 2.0.50727; WOW64)", "Mozilla/5.0 (compatible; MSIE 10.0; Macintosh; Intel Mac OS X 10_7_3; Trident/6.0)", "Mozilla/4.0 (Compatible; MSIE 8.0; Windows NT 5.2; Trident/6.0)", "Mozilla/4.0 (compatible; MSIE 10.0; Windows NT 6.1; Trident/5.0)", "Mozilla/1.22 (compatible; MSIE 10.0; Windows 3.1)", "Mozilla/5.0 (Windows; U; MSIE 9.0; WIndows NT 9.0; en-US))", "Mozilla/5.0 (Windows; U; MSIE 9.0; Windows NT 9.0; en-US)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 7.1; Trident/5.0)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; Media Center PC 6.0; InfoPath.3; MS-RTC LM 8; Zune 4.7)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; Media Center PC 6.0; InfoPath.3; MS-RTC LM 8; Zune 4.7", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; Zune 4.0; InfoPath.3; MS-RTC LM 8; .NET4.0C; .NET4.0E)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; chromeframe/12.0.742.112)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; .NET CLR 3.5.30729; .NET CLR 3.0.30729; .NET CLR 2.0.50727; Media Center PC 6.0)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Win64; x64; Trident/5.0; .NET CLR 3.5.30729; .NET CLR 3.0.30729; .NET CLR 2.0.50727; Media Center PC 6.0)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Win64; x64; Trident/5.0; .NET CLR 2.0.50727; SLCC2; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; Zune 4.0; Tablet PC 2.0; InfoPath.3; .NET4.0C; .NET4.0E)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Win64; x64; Trident/5.0", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0; yie8)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; InfoPath.2; .NET CLR 1.1.4322; .NET4.0C; Tablet PC 2.0)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0; FunWebProducts)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0; chromeframe/13.0.782.215)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0; chromeframe/11.0.696.57)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/5.0) chromeframe/10.0.648.205", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; Trident/4.0; GTB7.4; InfoPath.1; SV1; .NET CLR 2.8.52393; WOW64; en-US)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.0; Trident/5.0; chromeframe/11.0.696.57)", "Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.0; Trident/4.0; GTB7.4; InfoPath.3; SV1; .NET CLR 3.1.76908; WOW64; en-US)", "Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.1; Trident/4.0; GTB7.4; InfoPath.2; SV1; .NET CLR 3.3.69573; WOW64; en-US)", "Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; .NET CLR 1.0.3705; .NET CLR 1.1.4322)", "Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0; InfoPath.1; SV1; .NET CLR 3.8.36217; WOW64; en-US)", "Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0; .NET CLR 2.7.58687; SLCC2; Media Center PC 5.0; Zune 3.4; Tablet PC 3.6; InfoPath.3)", "Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 5.2; Trident/4.0; Media Center PC 4.0; SLCC1; .NET CLR 3.0.04320)", "Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0; SLCC1; .NET CLR 3.0.4506.2152; .NET CLR 3.5.30729; .NET CLR 1.1.4322)", "Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0; InfoPath.2; SLCC1; .NET CLR 3.0.4506.2152; .NET CLR 3.5.30729; .NET CLR 2.0.50727)", "Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 5.1; Trident/4.0; .NET CLR 1.1.4322; .NET CLR 2.0.50727)", "Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 5.1; SLCC1; .NET CLR 1.1.4322)", "Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 5.0; Trident/4.0; InfoPath.1; SV1; .NET CLR 3.0.4506.2152; .NET CLR 3.5.30729; .NET CLR 3.0.04506.30)", "Mozilla/5.0 (compatible; MSIE 7.0; Windows NT 5.0; Trident/4.0; FBSMTWB; .NET CLR 2.0.34861; .NET CLR 3.0.3746.3218; .NET CLR 3.5.33652; msn OptimizedIE8;ENUS)", "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.2; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0)", "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; Media Center PC 6.0; InfoPath.2; MS-RTC LM 8)", "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; Media Center PC 6.0; InfoPath.2; MS-RTC LM 8", "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; Media Center PC 6.0; .NET CLR 3.5.30729; .NET CLR 3.0.30729; .NET4.0C)", "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; InfoPath.3; .NET4.0C; .NET4.0E; .NET CLR 3.5.30729; .NET CLR 3.0.30729; MS-RTC LM 8)", "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; InfoPath.2)", "Mozilla/4.0 (compatible; MSIE 8.0; Windows NT 6.1; WOW64; Trident/4.0; SLCC2; .NET CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; Media Center PC 6.0; Zune 3.0)"], "safari": ["Mozilla/5.0 (Macintosh; Intel Mac OS X 10_9_3) AppleWebKit/537.75.14 (KHTML, like Gecko) Version/7.0.3 Safari/7046A194A", "Mozilla/5.0 (iPad; CPU OS 6_0 like Mac OS X) AppleWebKit/536.26 (KHTML, like Gecko) Version/6.0 Mobile/10A5355d Safari/8536.25", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/537.13+ (KHTML, like Gecko) Version/5.1.7 Safari/534.57.2", "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/534.55.3 (KHTML, like Gecko) Version/5.1.3 Safari/534.53.10", "Mozilla/5.0 (iPad; CPU OS 5_1 like Mac OS X) AppleWebKit/534.46 (KHTML, like Gecko ) Version/5.1 Mobile/9B176 Safari/7534.48.3", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_8; de-at) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1", "Mozilla/5.0 (Windows; U; Windows NT 6.1; tr-TR) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Windows; U; Windows NT 6.1; ko-KR) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Windows; U; Windows NT 6.1; fr-FR) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Windows; U; Windows NT 6.1; cs-CZ) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Windows; U; Windows NT 6.0; ja-JP) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; PPC Mac OS X 10_5_8; zh-cn) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; PPC Mac OS X 10_5_8; ja-jp) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; ja-jp) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; zh-cn) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; sv-se) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; ko-kr) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; ja-jp) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; it-it) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; fr-fr) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; es-es) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; en-us) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; en-gb) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; de-de) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.4 Safari/533.20.27", "Mozilla/5.0 (Windows; U; Windows NT 6.1; sv-SE) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Windows; U; Windows NT 6.1; ja-JP) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Windows; U; Windows NT 6.1; de-DE) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Windows; U; Windows NT 6.0; hu-HU) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Windows; U; Windows NT 6.0; de-DE) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru-RU) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Windows; U; Windows NT 5.1; ja-JP) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Windows; U; Windows NT 5.1; it-IT) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US) AppleWebKit/533.20.25 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; en-us) AppleWebKit/534.16+ (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_6; fr-ch) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_5; de-de) AppleWebKit/534.15+ (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_5; ar) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Android 2.2; Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.3 Safari/533.19.4", "Mozilla/5.0 (Windows; U; Windows NT 6.1; zh-HK) AppleWebKit/533.18.1 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5", "Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5", "Mozilla/5.0 (Windows; U; Windows NT 6.0; tr-TR) AppleWebKit/533.18.1 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5", "Mozilla/5.0 (Windows; U; Windows NT 6.0; nb-NO) AppleWebKit/533.18.1 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5", "Mozilla/5.0 (Windows; U; Windows NT 6.0; fr-FR) AppleWebKit/533.18.1 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5", "Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-TW) AppleWebKit/533.19.4 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5", "Mozilla/5.0 (Windows; U; Windows NT 5.1; ru-RU) AppleWebKit/533.18.1 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5", "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_5_8; zh-cn) AppleWebKit/533.18.1 (KHTML, like Gecko) Version/5.0.2 Safari/533.18.5"]}, "randomize": {"344": "chrome", "819": "firefox", "346": "chrome", "347": "chrome", "340": "chrome", "341": "chrome", "342": "chrome", "343": "chrome", "810": "internetexplorer", "811": "internetexplorer", "812": "internetexplorer", "813": "firefox", "348": "chrome", "349": "chrome", "816": "firefox", "817": "firefox", "737": "chrome", "719": "chrome", "718": "chrome", "717": "chrome", "716": "chrome", "715": "chrome", "714": "chrome", "713": "chrome", "712": "chrome", "711": "chrome", "710": "chrome", "421": "chrome", "129": "chrome", "420": "chrome", "423": "chrome", "422": "chrome", "425": "chrome", "619": "chrome", "424": "chrome", "427": "chrome", "298": "chrome", "299": "chrome", "296": "chrome", "297": "chrome", "294": "chrome", "295": "chrome", "292": "chrome", "293": "chrome", "290": "chrome", "291": "chrome", "591": "chrome", "590": "chrome", "593": "chrome", "592": "chrome", "595": "chrome", "594": "chrome", "597": "chrome", "596": "chrome", "195": "chrome", "194": "chrome", "197": "chrome", "196": "chrome", "191": "chrome", "190": "chrome", "193": "chrome", "192": "chrome", "270": "chrome", "271": "chrome", "272": "chrome", "273": "chrome", "274": "chrome", "275": "chrome", "276": "chrome", "277": "chrome", "278": "chrome", "279": "chrome", "569": "chrome", "497": "chrome", "524": "chrome", "525": "chrome", "526": "chrome", "527": "chrome", "520": "chrome", "521": "chrome", "522": "chrome", "523": "chrome", "528": "chrome", "529": "chrome", "449": "chrome", "448": "chrome", "345": "chrome", "443": "chrome", "442": "chrome", "441": "chrome", "440": "chrome", "447": "chrome", "446": "chrome", "445": "chrome", "444": "chrome", "47": "chrome", "108": "chrome", "109": "chrome", "102": "chrome", "103": "chrome", "100": "chrome", "101": "chrome", "106": "chrome", "107": "chrome", "104": "chrome", "105": "chrome", "902": "firefox", "903": "firefox", "39": "chrome", "38": "chrome", "906": "firefox", "907": "firefox", "904": "firefox", "905": "firefox", "33": "chrome", "32": "chrome", "31": "chrome", "30": "chrome", "37": "chrome", "36": "chrome", "35": "chrome", "34": "chrome", "641": "chrome", "640": "chrome", "643": "chrome", "642": "chrome", "645": "chrome", "644": "chrome", "438": "chrome", "439": "chrome", "436": "chrome", "437": "chrome", "434": "chrome", "435": "chrome", "432": "chrome", "433": "chrome", "430": "chrome", "431": "chrome", "826": "firefox", "339": "chrome", "338": "chrome", "335": "chrome", "334": "chrome", "337": "chrome", "336": "chrome", "331": "chrome", "330": "chrome", "333": "chrome", "332": "chrome", "559": "chrome", "745": "chrome", "854": "firefox", "818": "firefox", "856": "firefox", "857": "firefox", "850": "firefox", "851": "firefox", "852": "firefox", "0": "chrome", "858": "firefox", "859": "firefox", "748": "chrome", "6": "chrome", "43": "chrome", "99": "chrome", "98": "chrome", "91": "chrome", "90": "chrome", "93": "chrome", "92": "chrome", "95": "chrome", "94": "chrome", "97": "chrome", "96": "chrome", "814": "firefox", "815": "firefox", "153": "chrome", "740": "chrome", "741": "chrome", "742": "chrome", "743": "chrome", "744": "chrome", "558": "chrome", "746": "chrome", "747": "chrome", "555": "chrome", "554": "chrome", "557": "chrome", "556": "chrome", "551": "chrome", "550": "chrome", "553": "chrome", "552": "chrome", "238": "chrome", "239": "chrome", "234": "chrome", "235": "chrome", "236": "chrome", "237": "chrome", "230": "chrome", "231": "chrome", "232": "chrome", "233": "chrome", "1": "chrome", "155": "chrome", "146": "chrome", "147": "chrome", "618": "chrome", "145": "chrome", "142": "chrome", "143": "chrome", "140": "chrome", "141": "chrome", "612": "chrome", "613": "chrome", "610": "chrome", "611": "chrome", "616": "chrome", "617": "chrome", "148": "chrome", "149": "chrome", "46": "chrome", "154": "chrome", "948": "safari", "949": "safari", "946": "safari", "947": "safari", "944": "safari", "945": "safari", "942": "safari", "943": "safari", "940": "safari", "941": "safari", "689": "chrome", "688": "chrome", "685": "chrome", "684": "chrome", "687": "chrome", "686": "chrome", "681": "chrome", "680": "chrome", "683": "chrome", "682": "chrome", "458": "chrome", "459": "chrome", "133": "chrome", "132": "chrome", "131": "chrome", "130": "chrome", "137": "chrome", "136": "chrome", "135": "chrome", "134": "chrome", "494": "chrome", "495": "chrome", "139": "chrome", "138": "chrome", "490": "chrome", "491": "chrome", "492": "chrome", "493": "chrome", "24": "chrome", "25": "chrome", "26": "chrome", "27": "chrome", "20": "chrome", "21": "chrome", "22": "chrome", "23": "chrome", "28": "chrome", "29": "chrome", "820": "firefox", "407": "chrome", "406": "chrome", "405": "chrome", "404": "chrome", "403": "chrome", "402": "chrome", "401": "chrome", "400": "chrome", "933": "firefox", "932": "firefox", "931": "firefox", "930": "firefox", "937": "safari", "452": "chrome", "409": "chrome", "408": "chrome", "453": "chrome", "414": "chrome", "183": "chrome", "415": "chrome", "379": "chrome", "378": "chrome", "228": "chrome", "829": "firefox", "828": "firefox", "371": "chrome", "370": "chrome", "373": "chrome", "372": "chrome", "375": "chrome", "374": "chrome", "377": "chrome", "376": "chrome", "708": "chrome", "709": "chrome", "704": "chrome", "705": "chrome", "706": "chrome", "707": "chrome", "700": "chrome", "144": "chrome", "702": "chrome", "703": "chrome", "393": "chrome", "392": "chrome", "88": "chrome", "89": "chrome", "397": "chrome", "396": "chrome", "395": "chrome", "394": "chrome", "82": "chrome", "83": "chrome", "80": "chrome", "81": "chrome", "86": "chrome", "87": "chrome", "84": "chrome", "85": "chrome", "797": "internetexplorer", "796": "internetexplorer", "795": "internetexplorer", "794": "internetexplorer", "793": "internetexplorer", "792": "internetexplorer", "791": "internetexplorer", "790": "internetexplorer", "749": "chrome", "799": "internetexplorer", "798": "internetexplorer", "7": "chrome", "170": "chrome", "586": "chrome", "587": "chrome", "584": "chrome", "585": "chrome", "582": "chrome", "583": "chrome", "580": "chrome", "581": "chrome", "588": "chrome", "589": "chrome", "245": "chrome", "244": "chrome", "247": "chrome", "246": "chrome", "241": "chrome", "614": "chrome", "243": "chrome", "242": "chrome", "615": "chrome", "249": "chrome", "248": "chrome", "418": "chrome", "419": "chrome", "519": "chrome", "518": "chrome", "511": "chrome", "510": "chrome", "513": "chrome", "512": "chrome", "515": "chrome", "514": "chrome", "517": "chrome", "516": "chrome", "623": "chrome", "622": "chrome", "621": "chrome", "620": "chrome", "627": "chrome", "626": "chrome", "625": "chrome", "624": "chrome", "450": "chrome", "451": "chrome", "629": "chrome", "628": "chrome", "454": "chrome", "455": "chrome", "456": "chrome", "457": "chrome", "179": "chrome", "178": "chrome", "177": "chrome", "199": "chrome", "175": "chrome", "174": "chrome", "173": "chrome", "172": "chrome", "171": "chrome", "198": "chrome", "977": "opera", "182": "chrome", "975": "opera", "974": "opera", "973": "opera", "972": "opera", "971": "opera", "970": "opera", "180": "chrome", "979": "opera", "978": "opera", "656": "chrome", "599": "chrome", "654": "chrome", "181": "chrome", "186": "chrome", "187": "chrome", "184": "chrome", "185": "chrome", "652": "chrome", "188": "chrome", "189": "chrome", "658": "chrome", "653": "chrome", "650": "chrome", "651": "chrome", "11": "chrome", "10": "chrome", "13": "chrome", "12": "chrome", "15": "chrome", "14": "chrome", "17": "chrome", "16": "chrome", "19": "chrome", "18": "chrome", "863": "firefox", "862": "firefox", "865": "firefox", "864": "firefox", "867": "firefox", "866": "firefox", "354": "chrome", "659": "chrome", "44": "chrome", "883": "firefox", "882": "firefox", "881": "firefox", "880": "firefox", "887": "firefox", "886": "firefox", "885": "firefox", "884": "firefox", "889": "firefox", "888": "firefox", "116": "chrome", "45": "chrome", "657": "chrome", "355": "chrome", "322": "chrome", "323": "chrome", "320": "chrome", "321": "chrome", "326": "chrome", "327": "chrome", "324": "chrome", "325": "chrome", "328": "chrome", "329": "chrome", "562": "chrome", "201": "chrome", "200": "chrome", "203": "chrome", "202": "chrome", "205": "chrome", "204": "chrome", "207": "chrome", "206": "chrome", "209": "chrome", "208": "chrome", "779": "internetexplorer", "778": "internetexplorer", "77": "chrome", "76": "chrome", "75": "chrome", "74": "chrome", "73": "chrome", "72": "chrome", "71": "chrome", "70": "chrome", "655": "chrome", "567": "chrome", "79": "chrome", "78": "chrome", "359": "chrome", "358": "chrome", "669": "chrome", "668": "chrome", "667": "chrome", "666": "chrome", "665": "chrome", "664": "chrome", "663": "chrome", "662": "chrome", "661": "chrome", "660": "chrome", "215": "chrome", "692": "chrome", "693": "chrome", "690": "chrome", "691": "chrome", "696": "chrome", "697": "chrome", "694": "chrome", "695": "chrome", "698": "chrome", "699": "chrome", "542": "chrome", "543": "chrome", "540": "chrome", "541": "chrome", "546": "chrome", "547": "chrome", "544": "chrome", "545": "chrome", "8": "chrome", "548": "chrome", "549": "chrome", "598": "chrome", "869": "firefox", "868": "firefox", "120": "chrome", "121": "chrome", "122": "chrome", "123": "chrome", "124": "chrome", "125": "chrome", "126": "chrome", "127": "chrome", "128": "chrome", "2": "chrome", "219": "chrome", "176": "chrome", "214": "chrome", "563": "chrome", "928": "firefox", "929": "firefox", "416": "chrome", "417": "chrome", "410": "chrome", "411": "chrome", "412": "chrome", "413": "chrome", "920": "firefox", "498": "chrome", "922": "firefox", "923": "firefox", "924": "firefox", "925": "firefox", "926": "firefox", "927": "firefox", "319": "chrome", "318": "chrome", "313": "chrome", "312": "chrome", "311": "chrome", "310": "chrome", "317": "chrome", "316": "chrome", "315": "chrome", "314": "chrome", "921": "firefox", "496": "chrome", "832": "firefox", "833": "firefox", "830": "firefox", "831": "firefox", "836": "firefox", "837": "firefox", "834": "firefox", "835": "firefox", "838": "firefox", "839": "firefox", "3": "chrome", "368": "chrome", "369": "chrome", "366": "chrome", "367": "chrome", "364": "chrome", "365": "chrome", "362": "chrome", "363": "chrome", "360": "chrome", "361": "chrome", "218": "chrome", "380": "chrome", "861": "firefox", "382": "chrome", "383": "chrome", "384": "chrome", "385": "chrome", "386": "chrome", "387": "chrome", "388": "chrome", "389": "chrome", "784": "internetexplorer", "785": "internetexplorer", "786": "internetexplorer", "787": "internetexplorer", "780": "internetexplorer", "781": "internetexplorer", "782": "internetexplorer", "381": "chrome", "788": "internetexplorer", "789": "internetexplorer", "860": "firefox", "151": "chrome", "579": "chrome", "578": "chrome", "150": "chrome", "573": "chrome", "572": "chrome", "571": "chrome", "570": "chrome", "577": "chrome", "576": "chrome", "575": "chrome", "574": "chrome", "60": "chrome", "61": "chrome", "62": "chrome", "259": "chrome", "64": "chrome", "65": "chrome", "66": "chrome", "67": "chrome", "68": "chrome", "253": "chrome", "250": "chrome", "251": "chrome", "256": "chrome", "257": "chrome", "254": "chrome", "255": "chrome", "499": "chrome", "157": "chrome", "156": "chrome", "939": "safari", "731": "chrome", "730": "chrome", "733": "chrome", "938": "safari", "735": "chrome", "734": "chrome", "508": "chrome", "736": "chrome", "506": "chrome", "738": "chrome", "504": "chrome", "505": "chrome", "502": "chrome", "503": "chrome", "500": "chrome", "501": "chrome", "630": "chrome", "631": "chrome", "632": "chrome", "633": "chrome", "469": "chrome", "468": "chrome", "636": "chrome", "637": "chrome", "465": "chrome", "464": "chrome", "467": "chrome", "466": "chrome", "461": "chrome", "900": "firefox", "463": "chrome", "462": "chrome", "901": "firefox", "168": "chrome", "169": "chrome", "164": "chrome", "165": "chrome", "166": "chrome", "167": "chrome", "160": "chrome", "161": "chrome", "162": "chrome", "163": "chrome", "964": "safari", "965": "safari", "966": "safari", "967": "safari", "960": "safari", "961": "safari", "962": "safari", "963": "safari", "783": "internetexplorer", "968": "safari", "969": "opera", "936": "firefox", "935": "firefox", "934": "firefox", "908": "firefox", "909": "firefox", "722": "chrome", "426": "chrome", "878": "firefox", "879": "firefox", "876": "firefox", "877": "firefox", "874": "firefox", "875": "firefox", "872": "firefox", "873": "firefox", "870": "firefox", "871": "firefox", "9": "chrome", "890": "firefox", "891": "firefox", "892": "firefox", "893": "firefox", "894": "firefox", "647": "chrome", "896": "firefox", "897": "firefox", "898": "firefox", "899": "firefox", "646": "chrome", "649": "chrome", "648": "chrome", "357": "chrome", "356": "chrome", "809": "internetexplorer", "808": "internetexplorer", "353": "chrome", "352": "chrome", "351": "chrome", "350": "chrome", "803": "internetexplorer", "802": "internetexplorer", "801": "internetexplorer", "800": "internetexplorer", "807": "internetexplorer", "806": "internetexplorer", "805": "internetexplorer", "804": "internetexplorer", "216": "chrome", "217": "chrome", "768": "chrome", "769": "chrome", "212": "chrome", "213": "chrome", "210": "chrome", "211": "chrome", "762": "chrome", "763": "chrome", "760": "chrome", "761": "chrome", "766": "chrome", "767": "chrome", "764": "chrome", "765": "chrome", "40": "chrome", "41": "chrome", "289": "chrome", "288": "chrome", "4": "chrome", "281": "chrome", "280": "chrome", "283": "chrome", "282": "chrome", "285": "chrome", "284": "chrome", "287": "chrome", "286": "chrome", "678": "chrome", "679": "chrome", "674": "chrome", "675": "chrome", "676": "chrome", "677": "chrome", "670": "chrome", "671": "chrome", "672": "chrome", "673": "chrome", "263": "chrome", "262": "chrome", "261": "chrome", "260": "chrome", "267": "chrome", "266": "chrome", "265": "chrome", "264": "chrome", "269": "chrome", "268": "chrome", "59": "chrome", "58": "chrome", "55": "chrome", "54": "chrome", "57": "chrome", "56": "chrome", "51": "chrome", "258": "chrome", "53": "chrome", "52": "chrome", "537": "chrome", "536": "chrome", "535": "chrome", "63": "chrome", "533": "chrome", "532": "chrome", "531": "chrome", "530": "chrome", "152": "chrome", "539": "chrome", "538": "chrome", "775": "internetexplorer", "774": "internetexplorer", "982": "opera", "983": "opera", "980": "opera", "981": "opera", "777": "internetexplorer", "984": "opera", "50": "chrome", "115": "chrome", "252": "chrome", "117": "chrome", "776": "internetexplorer", "111": "chrome", "110": "chrome", "113": "chrome", "69": "chrome", "771": "chrome", "119": "chrome", "118": "chrome", "770": "chrome", "773": "internetexplorer", "772": "internetexplorer", "429": "chrome", "428": "chrome", "534": "chrome", "919": "firefox", "918": "firefox", "915": "firefox", "914": "firefox", "917": "firefox", "916": "firefox", "911": "firefox", "910": "firefox", "913": "firefox", "912": "firefox", "308": "chrome", "309": "chrome", "855": "firefox", "300": "chrome", "301": "chrome", "302": "chrome", "303": "chrome", "304": "chrome", "305": "chrome", "306": "chrome", "307": "chrome", "895": "firefox", "825": "firefox", "824": "firefox", "827": "firefox", "847": "firefox", "846": "firefox", "845": "firefox", "844": "firefox", "843": "firefox", "842": "firefox", "841": "firefox", "840": "firefox", "821": "firefox", "853": "firefox", "849": "firefox", "848": "firefox", "823": "firefox", "822": "firefox", "240": "chrome", "390": "chrome", "732": "chrome", "753": "chrome", "752": "chrome", "751": "chrome", "750": "chrome", "757": "chrome", "756": "chrome", "755": "chrome", "754": "chrome", "560": "chrome", "561": "chrome", "759": "chrome", "758": "chrome", "564": "chrome", "565": "chrome", "566": "chrome", "701": "chrome", "739": "chrome", "229": "chrome", "507": "chrome", "227": "chrome", "226": "chrome", "225": "chrome", "224": "chrome", "223": "chrome", "222": "chrome", "221": "chrome", "220": "chrome", "114": "chrome", "391": "chrome", "726": "chrome", "727": "chrome", "724": "chrome", "725": "chrome", "568": "chrome", "723": "chrome", "720": "chrome", "721": "chrome", "728": "chrome", "729": "chrome", "605": "chrome", "604": "chrome", "607": "chrome", "606": "chrome", "601": "chrome", "600": "chrome", "603": "chrome", "602": "chrome", "159": "chrome", "158": "chrome", "112": "chrome", "609": "chrome", "608": "chrome", "976": "opera", "634": "chrome", "399": "chrome", "635": "chrome", "959": "safari", "958": "safari", "398": "chrome", "48": "chrome", "49": "chrome", "951": "safari", "950": "safari", "953": "safari", "952": "safari", "42": "chrome", "954": "safari", "957": "safari", "956": "safari", "638": "chrome", "5": "chrome", "639": "chrome", "460": "chrome", "489": "chrome", "488": "chrome", "487": "chrome", "486": "chrome", "485": "chrome", "484": "chrome", "483": "chrome", "482": "chrome", "481": "chrome", "480": "chrome", "509": "chrome", "955": "safari", "472": "chrome", "473": "chrome", "470": "chrome", "471": "chrome", "476": "chrome", "477": "chrome", "474": "chrome", "475": "chrome", "478": "chrome", "479": "chrome"}}';
        $data = json_decode($json, true);

        // 随机取数字id
        $key = array_rand(array_keys($data['randomize']));

        // 随机数字id对应的浏览器名称
        $name = $data['randomize'][$key];

        // 浏览器名称下随机取一项
        return $data['browsers'][$name][array_rand($data['browsers'][$name])];
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

    public static function line2($log=false)
    {
        $res = str_repeat(self::$_line_chars[self::$_line_char_index], 100);

        self::$_line_char_index++;
        if (self::$_line_char_index == count(self::$_line_chars)) {
            self::$_line_char_index = 0;
        }

        if ($log) {
            self::log($res);
        } else {
            echo "<div>$res</div>";
        }
    }

    public static function line($log=false, $title='')
    {
        if ($title !== '') {
            $title = ' ' . $title . ' ';
        }
        $res = str_repeat('-', 50) . $title . str_repeat('-', 50);
        if ($log) {
            self::log($res);
        } else {
            echo "<div>$res</div>";
        }
    }

    public static function lines($log=false, $title='')
    {
        if ($title !== '') {
            $title = ' ' . $title . ' ';
        }
        $res = str_repeat('-', 50) . $title . str_repeat('-', 50);
        if ($log) {
            self::log($res);
        } else {
            echo "$res\n";
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

    private static $_beginSqlLog = false;

    public static function getBeginSqlLog()
    {
        return self::$_beginSqlLog;
    }

    /**
     * Begin the laravel database sql query log. The name s means 'beginSqlLog' for convenience.
     */
    public static function beginSqlLog()
    {
        self::$_beginSqlLog = true;

        self::profile('s_total_profile');
        DB::enableQueryLog();
    }

    public static function endSqlLog3()
    {
        $data = DB::getQueryLog();
        self::$_message = 'query log';
        self::loge($data);
    }

    /**
     * End the laravel database sql query log. The name ss means 'endSqlLog' for convenience.
     * @param bool $asString Join the sql as one string when true.
     */
    public static function endSqlLog($asString=false)
    {
        if (!self::$_beginSqlLog)
        {
            return;
        }

        // time total
        $timeTotal = self::profilee('s_total_profile', true);

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
        $content = '';
        if (isset($_SERVER['SERVER_NAME'])) {
            $content .= "\n\n" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI']; // the current request url
        }
        $content .= QueryLog::formatStat($stat); // stat info
        $content .= QueryLog::formatDataToDisplay($data); // sql list

        self::log($content);
    }

    public static function endSqlLog2()
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
            $target = $row['self'] ? "" : "target='_blank'";
            $html .= "<li><a href='{$row['url']}' {$target}>{$row['text']}</a></li>";
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

        if (self::$_beginSqlLog) {
            self::endSqlLog();
        }

        if (function_exists('DB')) {

            if(!isset(self::$_profile['s_total_profile'])){
                return true;
            }

            \D::ss();
        }
    }

    public static function shutdownLog($data)
    {
        self::$_shutdownLog[] = $data;
    }

    public static function tableList($data, $keyList=[], $return=false)
    {
        $html = '<div>';

        foreach ($data as $title => $list) {
            $html .= '<div>';
            $html .= '<h3>';
            $html .= self::table($list, $keyList, true);
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

    public static function table($data, $keyList=[], $return=false)
    {
        if (!$data) {
            $html = '<div>没有找到记录</div>';
            if ($return) {
                return $html;
            } else {
                echo $html;
            }
        }

        $html = '';
        $html .= '
        <style>
.d-dump-table{border: 1px solid #CCCCCC; border-collapse: collapse; margin: 16px;}
.d-dump-table td, .d-dump-table th{border: 1px solid #CCCCCC; padding: 3px 20px; font-size: 12px; padding: 3px 12px;}
</style>
        ';
        $html .= '<table class="d-dump-table">';

        // header
        $html .= '<thead>';

        $multi = is_array(pos($data));

        if (!$keyList) {
            if ($multi) {
                // 多维数组
                $keyList = array_keys(pos($data));
            } else {
                // 一维数组
                $keyList = array_keys($data);
            }
        }

        $html .= '<tr>';
        foreach ($keyList as $item) {
            $html .= '<th>';
            $html .= $item;
            $html .= '</th>';
        }
        $html .= '</tr>';

        $html .= '</thead>';

        // body
        $html .= '<tbody>';

        foreach ($data as $row) {

            $html .= '<tr>';
            if ($multi) {
                foreach ($row as $item) {
                    self::tableTd($html, $item);
                }
            } else {
                self::tableTd($html, $row);
            }
            $html .= '</tr>';
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

    public static function formatSql($sql, $highlight=false)
    {
        $sql = SqlFormatter::format($sql, $highlight);
        $sql = self::finetuneFormatSql($sql, $highlight);
        return "\n".$sql."\n";
    }

    private static function finetuneFormatSql($sql)
    {
        return $sql;
    }

    public static function pdSql($sql, $slight=false)
    {
        self::$_skip_pdo = true;
        $slight ? self::pds(self::formatSql($sql, false)) : self::pd(self::formatSql($sql));
        self::$_skip_pdo = false;
    }

    public static function pdeSql($sql, $slight=false)
    {
        self::$_skip_pdo = true;
        $slight ? self::pdse(self::formatSql($sql, false)) : self::pde(self::formatSql($sql));
        self::$_skip_pdo = false;
    }

    public static function logSql($sql)
    {
        self::$_skip_pdo = true;
        self::log(self::formatSql($sql, false));
        self::$_skip_pdo = false;
    }

    public static function logeSql($sql)
    {
        self::$_skip_pdo = true;
        self::loge(self::formatSql($sql, false));
        self::$_skip_pdo = false;
    }

    public static function logcSql($sql)
    {
        self::$_skip_pdo = true;
        self::logc(self::formatSql($sql, false));
        self::$_skip_pdo = false;
    }

    public static function logceSql($sql)
    {
        self::$_skip_pdo = true;
        self::logce(self::formatSql($sql, false));
        self::$_skip_pdo = false;
    }

    public static function implode_by_comma($array, $is_string=false)
    {
        if (!$is_string)
        {
            return implode(',', $array);
        }

        return "'" . implode("','", $array) . "'";
    }

    public static function yiisql($model, $dump=false, $html=false)
    {
        self::$_message = 'yiisql';
        $sql = $model->createCommand()->getRawSql();
        if ($dump) {
            $html ? self::pd($sql) : self::pds($sql);
        } else {
            self::logSql($sql);
        }
        self::$_message = '';
    }

    public static function yiisqlc($model, $dump=false, $html=false)
    {
        self::logc();
        self::yiisql($model, $dump, $html);
    }

    public static function yiisqlce($model, $dump=false, $html=false)
    {
        self::logc();
        self::yiisql($model, $dump, $html);
        exit();
    }

    public static function yiiCheckDataBySql($text)
    {
        $block_list = explode(';', $text);

        echo '<div style="font-size: 12px; margin: 30px;">';
        echo '<h1>Yii check data by sql</h1>';

        $num = 0;
        foreach ($block_list as $index => $block) {

            $block = trim($block);

            if ($block === '') {
                continue;
            }

            // 总是假设用SELECT *开始一个sql
            // 子查询里总是SELECT单个字段，所以这种拆分方式可行
            // 拆分后要给sql补回去
            $parts = explode('SELECT *', $block);
            $title = trim($parts[0]);
            $sql = 'SELECT * ' . trim($parts[1]);

            $num++;

            // 没写标题就用sql做标题
            if (!$title) {
                $title = $sql;
            }

            // 显示标题
            $title = str_replace('# ', '', $title);
            echo "<h3 style=\"margin-top:30px;\">{$num}. {$title}</h3>";

            // 先显示sql：sql执行可能出错
            echo "<div style=\"padding-left:20px;\">{$sql}</div>";

            // 显示结果
            $res = \Yii::$app->db->createCommand($sql)->queryAll();
            \D::table($res);
        }

        echo '</div>';
    }

    public static $show_table_count = 0;
    public static function showTable($title, $sql, $data)
    {
        self::$show_table_count++;
        $num = self::$show_table_count;

        echo '<div style="font-size: 12px; margin: 30px;">';

        if ($num == 1) {
            echo '<h1>Yii check data by sql</h1>';
        }

        // 显示标题
        $title = str_replace('# ', '', $title);
        echo "<h3 style=\"margin-top:30px;\">{$num}. {$title}</h3>";

        // 先显示sql：sql执行可能出错
        echo "<div style=\"padding-left:20px;\">{$sql}</div>";

        // 显示结果
        \D::table($data);

        echo '</div>';
    }

    public static function yiiCheckDataBySqle($text)
    {
        self::yiiCheckDataBySql($text);
        exit();
    }

    public static function formatTime($time, $format='Y-m-d H:i:s')
    {
        return date($format, $time);
    }

    public static function lastquery($model)
    {
        $sql = $model->db->last_query();
        self::$_message = 'sql';
        self::log($sql);
        self::$_message = '';
    }

    public static function lastquerye($model)
    {
        self::lastquery($model);
        exit();
    }

    public static function compare($a, $b, $sort=false)
    {
        // 有时候可以对数组排序后进行比较
        if ($sort)
        {
            self::sort($a);
            self::sort($b);
        }

        // 进行字符串md5比较
        $a = md5(json_encode($a));
        $b = md5(json_encode($b));

        return $a == $b;
    }

    public static function pdCompare($a, $b, $sort=false)
    {
        self::$_message = 'compare';
        self::pd(self::compare($a, $b, $sort));
        self::$_message = '';
    }

    public static function pdsCompare($a, $b, $sort=false)
    {
        self::$_message = 'compare';
        self::pds(self::compare($a, $b, $sort));
        self::$_message = '';
    }

    public static function logCompare($a, $b, $sort=false)
    {
        self::$_message = 'compare';
        self::log(self::compare($a, $b, $sort));
        self::$_message = '';
    }

    public static function yiiCollectArData($data)
    {
        // 不可遍历就退出
        if (!is_array($data) && !is_iterable($data))
        {
            return $data;
        }

        // 遍历获取ar的attributes
        foreach ($data as $index => $item)
        {
            // 是ar就获取attributes
            if ($item instanceof CActiveRecord)
            {
                $item = $item->attributes;
            }

            // 获取的attributes元素可能也是ar或者ar数组
            if (is_array($item))
            {
                $item = self::yiiCollectArData($item);
            }

            $data[$index] = $item;
        }

        return $data;
    }

    private static $_stop_on_count = 0;

    public static function stopOnCount($count, $exit=true)
    {
        self::$_stop_on_count++;

        if (self::$_stop_on_count >= $count)
        {
            self::$_stop_on_count = 0;

            // 直接退出
            if ($exit)
            {
                exit();
            }

            // 让调用者自己决定如何处理
            // 例如在循环里调用，可能只是需要break
            return true;
        }

        return false;
    }

    public static function displayAllError()
    {
        ini_set('display_errors', 1);
        error_reporting(E_ALL);
    }

    public static function echo()
    {
        self::echoInternal(func_get_args());
    }

    public static function echoHtml()
    {
        self::echoInternal(func_get_args(), true);
    }

    private static function echoInternal($data, $html=false)
    {
        $breakLine = $html ? '<br/>' : "\n";
        if (!empty($data))
        {
            echo implode('-', $data) . $breakLine;
        }
        else
        {
            echo 'echo by D: rand=' . rand() . $breakLine;
        }
    }

    public static function apidocRequest($data)
    {
        $res = [];
        $res[] = "##### 参数\n";
        $res[] = '|参数名|必选|类型|说明|';
        $res[] = '|:-----|:-----|:-----|-----|';
        foreach ($data as $column => $item) {
            $parts = explode('|', $item);
            $required = ($parts[0] == 'true') ? '是' : '否';
            $res[] = "|{$column}  |{$required}}  |{$parts[1]}  |{$parts[2]}  |";
        }
        $res = "\n\n".implode("\n", $res)."\n\n";

        self::log($res);
    }

    public static function apidocResponse($data)
    {
        $res = [];
        $res[] = "##### 返回参数说明\n";
        $res[] = '|参数名|类型|说明|';
        $res[] = '|:-----|:-----|-----|';
        foreach ($data as $column => $item) {
            $parts = explode('|', $item);
            $res[] = "|{$column}  |{$parts[0]}  |{$parts[1]}  |";
        }
        $res = "\n\n".implode("\n", $res)."\n\n";

        self::log($res);
    }

    public static function logUrl()
    {
        if (isset($_SERVER['REQUEST_URI'])) {
            $url = $_SERVER['SCRIPT_NAME'] . $_SERVER['REQUEST_URI'];
            self::log($url);
        }
    }
}
