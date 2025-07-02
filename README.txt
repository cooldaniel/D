/**
 * README FILE FOR DUMPER CLASS
 * 
 * @author Daniel Luo <295313207@qq.com>
 * @copyright Copyright &copy; 2010-2015
 * @version 2.0
 */

如果需要使用自动加载方式加载类D，请在引导文件中直接包含autoload.php文件；
否则，可以直接包含D.php文件。

如果需要使用类D的错误异常处理机制，请在引导文件中直接包含autoload.php文件。


【打印变量】

\D::fp()    // 脚印：打印一行文字，用来验证是否执行到这里
\D::bk()    // 断点：打印一行文字，用来验证是否执行到这里，并且退出脚本

\D::pd()    // html高亮打印，适合在浏览器页面输出打印结果
\D::pde()   // html高亮打印并退出脚本

\D::pds()   // 普通打印，适合在命令行输出打印结果
\D::pdse()  // 普通打印并退出脚本

\D::log()   // 记录打印内容到文件，默认文件是执行脚本所在目录的DumperLogFile.ig.txt文件，文件名使用.ig.txt是为了方便将*.ig.*加入到git的ignore文件里，避免提交到版本库
\D::logc()  // 记录打印内容到文件，并且清除之前的内容，在每次都希望覆盖日志内容的时候很方便
\D:loge()   // 记录打印内容到文件，并且退出脚本
\D::logce() // 记录打印内容到文件，并且清除之前的内容，以及退出脚本


【查看程序执行堆栈】

\D::getTrace()          // 获取程序执行堆栈信息，文本格式
\D::getTraceAsArray()   // 获取程序执行堆栈信息，数组格式
\D::trace()             // 直接输出程序执行堆栈信息，文本格式
\D::traceHtml()         // 直接输出程序执行堆栈信息，文本格式，但是调用nl2br()转成html格式
\D::traceArray()        // 调用\D::pd()打印堆栈数组


【抛出异常】

\D::throw()     // 抛出500异常Exception实例，常用于try-catch调试
\D::error()     // trigger_error()抛出E_USER_ERROR用户定义异常，用于异常机制调试，不常用
\D::notice()    // trigger_error()抛出E_USER_NOTICE用户定义异常，用于异常机制调试，不常用
\D::warning()   // trigger_error()抛出E_USER_WARNING用户定义异常，用于异常机制调试，不常用


【查看程序执行性能】

\D::profile()   // 添加性能检测点，指定$token参数，可以实现嵌套检测效果
\D::profilee()  // 获取性能检测结果，默认是调用\D::log()记录到日志文件，可以设置参数返回结果


【查看反射信息】

\D::ref()       // 打印类或者对象的反射定义信息，常用于不确定类或者对象定义在哪个文件，有哪些属性或者方法可以使用的时候
\D::refe()      // 打印类或者对象的反射定义信息，并且退出脚本
\D::refF()      // 打印函数的反射信息，常用于想要了解一个函数的定义信息时
\D::refFe()     // 打印函数的反射信息，并且退出脚本
