<?php
/**
 * D组件临时文件使用说明.
 *
 * 注意：
 * 1.不提供类似\DTempFile::skipTempFileAvailableCheck()这样的方法在程序里自动跳过已存在的临时文件.
 * 因为开发这套方法的目的在于手动单步调试，不需要理想的自动化处理，完全可以通过手动删除临时文件的方式达到相同的目的.
 * 如果临时希望多次执行总是跳过已存在的临时文件，还可以注释掉部分代码达到目的.
 * 因此增加这样的方法不是必要的，而且会增加代码复杂度.开发调试除了达到调试目的以外，要尽量保持调试手段简单易用.
 */

/**
 * 1.单进程一次写入.
 *
 * 一次保存，多次读取.要重新保存就删除文件.
 * 要么从临时文件获取数据，要么从接口获取数据
 * 一旦从接口获取导数据并写入临时文件后，总是从临时文件获取数据
 */

$filenameId = 'test';

if (!\DTempFile::isTempFileAvailable($filenameId))
{
    $data = getDataFromApi();
    \DTempFile::saveTempFile($data, $filenameId);
}
else
{
    $data = \DTempFile::getFromTempFile($filenameId);
}

/**
 * 2.单进程多次写入.
 *
 * 单进程多次写入后，文件作为整体供多次读取，和“1.单进程一次写入”一样.
 */

$filenameId = 'test';

if (!\DTempFile::isTempFileAvailable($filenameId))
{
    $data = getDataFromApi();

    // 删除文件
    \DTempFile::deleteTempFile($filenameId);

    // 多次写入
    foreach ($data as $item)
    {
        \DTempFile::saveToTempFileAppend($item, $filenameId);
    }
}
else
{
    $data = \DTempFile::getFromTempFile($filenameId);
}

/**
 * 3.多进程每个进程一次写入.
 *
 * 在“1.单进程一次写入”基础上，将$filenameId初始化为进程或者任务相关的唯一ID，分别将不同进程的数据写到独立的文件里.
 * 如果想要把多个进程的结果写入到一个文件里，参考“5.多进程写入一个文件”一节.
 */

/**
 * 4.多进程每个进程多次写入.
 *
 * 在“2.单进程多次写入.基础上，将$filenameId初始化为进程或者任务相关的唯一ID，分别将不同进程的数据写到独立的文件里.
 * 如果想要把多个进程的结果写入到一个文件里，参考“5.多进程写入一个文件”一节.
 */

/**
 * 5.多进程写入一个文件
 *
 * 不要把多个进程的数据写到一个文件里，因为这样会出现进程并发写文件导致的数据交叉问题，而且单进程后续读取文件内容也有问题，
 * 所以这里不提供写入到一个文件的方法，尽量将问题简化.
 *
 * 以上只是为了单步简单调试目的设置的调试程序.如果依然想要把多个进程的结果写入到一个文件里进行调试，可以借助数据库，
 * 写入到文件的方式在重新读取数据时不容易解析，而且数据量太大的时候，解析文件会有性能问题，而数据库方式这个问题.
 */


